<?php

/**
 * This task handles notifying users when a message gets reported.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Helper\Mail;

/**
 * This task handles notifying users when a message gets reported.
 */
class MsgReportNotify extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// We need to know who can moderate this board - and therefore who can see this report.
		// First up, people who have moderate_board in the board this topic was in.
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_board', $this->_details['board_id']);

		// Second, anyone assigned to be a moderator of this board directly.
		$request = $smcFunc['db']->query('', '
			SELECT id_member
			FROM {db_prefix}moderators
			WHERE id_board = {int:current_board}',
			[
				'current_board' => $this->_details['board_id'],
			]
		);
		while ($row = $smcFunc['db']->fetch_assoc($request))
			$members[] = $row['id_member'];
		$smcFunc['db']->free_result($request);

		// Thirdly, anyone assigned to be a moderator of this group as a group->board moderator.
		$request = $smcFunc['db']->query('', '
			SELECT mem.id_member
			FROM {db_prefix}members AS mem, {db_prefix}moderator_groups AS bm
			WHERE bm.id_board = {int:current_board}
				AND(
					mem.id_group = bm.id_group
					OR FIND_IN_SET(bm.id_group, mem.additional_groups) != 0
				)',
			[
				'current_board' => $this->_details['board_id'],
			]
		);

		while ($row = $smcFunc['db']->fetch_assoc($request))
			$members[] = $row['id_member'];
		$smcFunc['db']->free_result($request);

		// And now weed out the duplicates.
		$members = array_flip(array_flip($members));

		// And don't send it to them if they're the one who reported it.
		$members = array_diff($members, [$this->_details['sender_id']]);

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'msg_report', true);

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => 0x01,
			'email' => 0x02,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
				if ($pref_option['msg_report'] & $bitvalue)
					$notifies[$type][] = $member;
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
					'content_type' => 'msg',
					'content_id' => $this->_details['msg_id'],
					'content_action' => 'report',
					'is_read' => 0,
					'extra' => json_encode(
						[
							'report_link' => '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'], // We don't put $scripturl in these!
						]
					),
				];
			}

			$smcFunc['db']->insert('insert',
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

			// Second, get some details that might be nice for the report email.
			// We don't bother cluttering up the tasks data for this, when it's really no bother to fetch it.
			$request = $smcFunc['db']->query('', '
				SELECT lr.subject, lr.membername, lr.body
				FROM {db_prefix}log_reported AS lr
				WHERE id_report = {int:report}',
				[
					'report' => $this->_details['report_id'],
				]
			);
			list ($subject, $poster_name, $comment) = $smcFunc['db']->fetch_row($request);
			$smcFunc['db']->free_result($request);

			// Third, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = [
					'TOPICSUBJECT' => $subject,
					'POSTERNAME' => $poster_name,
					'REPORTERNAME' => $this->_details['sender_name'],
					'TOPICLINK' => $scripturl . '?topic=' . $this->_details['topic_id'] . '.msg' . $this->_details['msg_id'] . '#msg' . $this->_details['msg_id'],
					'REPORTLINK' => $scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'],
					'COMMENT' => $comment,
				];

				$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $email_address)
					Mail::send($email_address, $emaildata['subject'], $emaildata['body'], null, 'report' . $this->_details['report_id'], $emaildata['is_html'], 2);
			}
		}

		// And now we're all done.
		return true;
	}
}
