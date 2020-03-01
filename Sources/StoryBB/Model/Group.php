<?php

/**
 * This class handles the database processing for a membergroup.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Model;

/**
 * This class handles the database processing for a membergroup.
 */
class Group
{
	const GUEST = -1;
	const UNGROUPED_ACCOUNT = 0;
	const ADMINISTRATOR = 1;
	const BOARD_MODERATOR = 3;

	/**
	 * Identifies whether a given group is a character-based or account based.
	 *
	 * @param int $group The group ID
	 * @return bool True if the group is a character-based group
	 */
	public static function is_character_group(int $group): bool
	{
		global $smcFunc;

		$request = $smcFunc['db']->query('', '
			SELECT is_character
			FROM {db_prefix}membergroups
			WHERE id_group = {int:group}
			LIMIT {int:limit}',
			[
				'group' => $group,
				'limit' => 1,
			]
		);
		$is_character_group = false;
		if ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$is_character_group = !empty($row['is_character']);
		}
		$smcFunc['db']->free_result($request);

		return $is_character_group;
	}
}
