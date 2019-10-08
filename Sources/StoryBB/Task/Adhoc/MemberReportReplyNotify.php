<?php

/**
 * This task handles notifying users when they've commented to a moderation report and someone else replies to them.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2019 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

/**
 * This task handles notifying users when they've commented to a moderation report and someone else replies to them.
 */
class MemberReportReplyNotify extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// Let's see. Let us, first of all, establish the list of possible people.
		$possible_members = [];
		$request = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}log_comments
			WHERE id_notice = {int:report}
				AND comment_type = {literal:reportc}
				AND id_comment < {int:last_comment}',
			[
				'report' => $this->_details['report_id'],
				'last_comment' => $this->_details['comment_id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_row($request))
			$possible_members[] = $row[0];
		$smcFunc['db']->free_result($request);

		// Presumably, there are some people?
		if (!empty($possible_members))
		{
			$possible_members = array_flip(array_flip($possible_members));
			$possible_members = array_diff($possible_members, [$this->_details['sender_id']]);
		}
		if (empty($possible_members))
			return true;

		// We need to know who can moderate this board - and therefore who can see this report.
		// First up, people who have moderate_board in the board this topic was in.
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_forum');

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'member_report_reply', true);

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => 0x01,
			'email' => 0x02,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
			{
				if ($pref_option['member_report_reply'] & $bitvalue)
					$notifies[$type][] = $member;
			}
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert']))
		{
			// Alerts are relatively easy.
			$insert_rows = [];
			foreach ($notifies['alert'] as $member)
			{
				$insert_rows[] = [
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['sender_id'],
					'member_name' => $this->_details['sender_name'],
					'content_type' => 'profile',
					'content_id' => $this->_details['user_id'],
					'content_action' => 'report_reply',
					'is_read' => 0,
					'extra' => json_encode(
						[
							'report_link' => '?action=moderate;area=reportedmembers;sa=details;rid=' . $this->_details['report_id'], // We don't put $scripturl in these!
							'user_name' => $this->_details['user_name'],
						]
					),
				];
			}

			$smcFunc['db_insert']('insert',
				'{db_prefix}user_alerts',
				['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
					'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
					'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
				$insert_rows,
				['id_alert']
			);

			// And update the count of alerts for those people.
			updateMemberData($notifies['alert'], ['alerts' => '+']);
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email']))
		{
			// Emails are a bit complicated. We have to do language stuff.
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// First, get everyone's language and details.
			$emails = [];
			$request = $smcFunc['db']->query('', '
				SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				[
					'members' => $notifies['email'],
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (empty($row['lngfile']))
					$row['lngfile'] = $language;
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			$smcFunc['db']->free_result($request);

			// Iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = [
					'MEMBERNAME' => $this->_details['member_name'],
					'COMMENTERNAME' => $this->_details['sender_name'],
					'PROFILELINK' => $scripturl . 'action=profile;u=' . $this->_details['user_id'],
					'REPORTLINK' => $scripturl . '?action=moderate;area=userreports;report=' . $this->_details['report_id'],
				];

				$emaildata = loadEmailTemplate('reply_to_user_reports', $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address)
					StoryBB\Helper\Mail::send($email_address, $emaildata['subject'], $emaildata['body'], null, 'urptrpy' . $this->_details['comment_id'], $emaildata['is_html'], 3);
			}
		}

		// And now we're all done.
		return true;
	}
}
