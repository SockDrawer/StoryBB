<?php

/**
 * This class handles identifying whether a character has a birthday or not.
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
 * This class handles identifying whether a character has a birthday or not.
 */
class CharacterBirthday extends AbstractCriteria implements CharacterAchievement
{
	public static function parameters(): array
	{
		return [
			'years' => [
				'type' => 'int',
				'min' => '1',
				'max' => '30',
				'optional' => false,
			],
		];
	}

	public static function get_criteria_members($criteria, $account_id = null, $character_id = null)
	{
		global $smcFunc;

		if (!static::validate_parameters($criteria))
		{
			return;
		}

		$criteria = json_decode($criteria, true);

		$birthday_timestamp = strtotime('-' . $criteria['years'] . ' years');
		$matches = [];

		$result = $smcFunc['db_query']('', '
			SELECT id_member, id_character, date_created
			FROM {db_prefix}characters
			WHERE date_created < {int:birthday_timestamp}
				AND is_main = {int:not_main}',
			[
				'birthday_timestamp' => $birthday_timestamp,
				'not_main' => 0,
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db_free_result']($result);
	}
}
