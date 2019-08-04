<?php

/**
 * This class handles identifying whether an account has a birthday or not.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

use StoryBB\Achievement\CharacterAchievement;

/**
 * This class handles identifying whether an account has any of a list of specified achievements.
 */
class CharacterMetaAchievement extends AbstractCriteria implements CharacterAchievement
{
	public static function parameters(): array
	{
		return [
			'achievements' => [
				'type' => 'array_int',
				'optional' => false,
			],
		];
	}

	public static function get_criteria_members($criteria, $account_id = null, $character_id = null)
	{
		global $smcFunc;

		$criteria = static::validate_parameters($criteria);

		$achievements = $criteria['achievements'];

		$result = $smcFunc['db_query']('', '
			SELECT au.id_member, au.id_character
			FROM {db_prefix}achieve_user AS au
			JOIN {db_prefix}characters AS chars ON (chars.id_character = au.id_character AND chars.id_member = au.id_member)
			WHERE chars.is_main = {int:not_main}
				AND au.id_achieve IN {{array_int:achieve_ids})' . (!$account_id || !$character_id ? '' : '
				AND chars.id_character = {int:character_id}
				AND chars.id_member = {int:account_id}') . '
			GROUP BY au.id_member, au.id_character',
			[
				'not_main' => 0,
				'achieve_ids' => $achievements,
				'account_id' => $account_id,
				'character_id' => $character_id,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db_free_result']($result);
		return;
	}
}
