<?php

/**
 * This class handles identifying whether a character has started enough topics.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

use StoryBB\Achievement\CharacterAchievement;
use StoryBB\Achievement\UnlockableAchievement;

/**
 * This class handles identifying whether a character has a birthday or not.
 */
class CharacterTopicStarter extends AbstractCriteria implements CharacterAchievement, UnlockableAchievement
{
	public static function parameters(): array
	{
		return [
			'topics' => [
				'type' => 'int',
				'min' => '1',
				'optional' => false,
			],
			'boards' => [
				'type' => 'boards',
				'optional' => true,
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
			SELECT m.id_creator AS id_member, m.id_character
			FROM {db_prefix}topics AS t 
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE m.id_creator != {int:empty_id_acc}
			    AND m.id_character != {int:empty_id_char}
			    AND t.approved = {int:topic_approved}
			    AND m.approved = {int:message_approved}' . (!empty($account_id) && !empty($character_id) ? '
			    AND m.id_creator = {int:account_id}
			    AND m.id_character = {int:character_id}' : '') . (!empty($criteria['boards']) ? '
			    AND t.id_board IN ({array_int:boards})' : '') . '
			GROUP BY m.id_creator, m.id_character
			HAVING COUNT(m.id_msg) > {int:min_topics}',
			[
				'empty_id_acc' => 0,
				'empty_id_char' => 0,
				'topic_approved' => 1,
				'message_approved' => 1,
				'account_id' => $account_id,
				'character_id' => $character_id,
				'min_topics' => $criteria['topics'],
				'boards' => !empty($criteria['boards']) ? $criteria['boards'] : '',
			]
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			yield $row['id_member'] . '_' . $row['id_character'];
		}
		$smcFunc['db_free_result']($result);
	}
}
