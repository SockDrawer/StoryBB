<?php
/**
 * Daily maintenance tasks.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Schedulable;

use StoryBB\Task;
use StoryBB\Achievement;

/**
 * Daily maintenance.
 */
class DailyMaintenance extends \StoryBB\Task\Schedulable
{
	/**
	 * Daily maintenance.
	 * @return bool True on success
	 */
	public function execute(): bool
	{
		// First clean out the cache.
		clean_cache();

		$this->update_warning_levels();

		$this->prune_login_history();

		// Do the birthday achievements.
		Achievement::trigger_award_achievement('account_birthday');
		Achievement::trigger_award_achievement('character_birthday');

		// Log we've done it...
		return true;
	}

	/**
	 * If warnings are set to reduce over time, update users who previously had warnings.
	 */
	protected function update_warning_levels()
	{
		global $modSettings, $smcFunc;

		// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
		list (, , $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);
		if ($modSettings['warning_decrement'])
		{
			// Find every member who has a warning level...
			$request = $smcFunc['db_query']('', '
				SELECT id_member, warning
				FROM {db_prefix}members
				WHERE warning > {int:no_warning}',
				array(
					'no_warning' => 0,
				)
			);
			$members = [];
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$members[$row['id_member']] = $row['warning'];
			$smcFunc['db_free_result']($request);

			// Have some members to check?
			if (!empty($members))
			{
				// Find out when they were last warned.
				$request = $smcFunc['db_query']('', '
					SELECT id_recipient, MAX(log_time) AS last_warning
					FROM {db_prefix}log_comments
					WHERE id_recipient IN ({array_int:member_list})
						AND comment_type = {string:warning}
					GROUP BY id_recipient',
					array(
						'member_list' => array_keys($members),
						'warning' => 'warning',
					)
				);
				$member_changes = [];
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					// More than 24 hours ago?
					if ($row['last_warning'] <= time() - 86400)
						$member_changes[] = array(
							'id' => $row['id_recipient'],
							'warning' => $members[$row['id_recipient']] >= $modSettings['warning_decrement'] ? $members[$row['id_recipient']] - $modSettings['warning_decrement'] : 0,
						);
				}
				$smcFunc['db_free_result']($request);

				// Have some members to change?
				if (!empty($member_changes))
					foreach ($member_changes as $change)
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}members
							SET warning = {int:warning}
							WHERE id_member = {int:id_member}',
							array(
								'warning' => $change['warning'],
								'id_member' => $change['id'],
							)
						);
			}
		}
	}

	/**
	 * Prune the login history to prevent it becoming too unwieldy.
	 */
	protected function prune_login_history()
	{
		global $smcFunc, $modSettings;

		// Clean up some old login history information.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}member_logins
			WHERE time < {int:oldLogins}',
			array(
				'oldLogins' => time() - (!empty($modSettings['loginHistoryDays']) ? 60 * 60 * 24 * $modSettings['loginHistoryDays'] : 2592000),
		));
	}
}
