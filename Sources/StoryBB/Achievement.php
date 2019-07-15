<?php

/**
 * This class handles the main achievement core for StoryBB.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB;

use StoryBB\Model\Achievement as AchievementModel;

/**
 * This class handles the main achievement core for StoryBB.
 */
class Achievement
{
	/**
	 * Lists the possible criteria that can be used for achievements.
	 *
	 * @return array An array of criteria-id -> criteria class entries.
	 */
	public static function get_possible_criteria(): array
	{
		return [
			'account_birthday' => 'StoryBB\\Achievement\\Criteria\\AccountBirthday',
			'character_birthday' => 'StoryBB\\Achievement\\Criteria\\CharacterBirthday',
		];
	}

	/**
	 * Receives events, essentially, about events that can trigger awarding an achievement.
	 *
	 * @param string $criteria Which trigger has been fired, e.g. 'character-birthday', so that only achievements with that criteria are considered.
	 * @param int $account_id The account ID relating to the event (i.e. the account that could earn the achievement).
	 * @param int $character_id The character ID relating to the event (i.e. the character that could the achievement)..
	 */
	public static function trigger_award_achievement(string $criteria, int $account_id = null, int $character_id = null)
	{
		$possible_criteria = static::get_possible_criteria();
		if (!isset($possible_criteria[$criteria]))
		{
			return; // We don't, currently, know the criteria (uninstalled plugin maybe), so abort.
		}

		// Find achievements with this trigger.
		$achievements = AchievementModel::get_by_criteria($criteria);
		foreach (static::match_criteria($achievements, $account_id, $character_id) as $match)
		{
			list($achievement_id, $account_id, $character_id) = $match;

			list($can_receive_multiple, $instances_received) = AchievementModel::get_awarded_status($achievement_id, $account_id, $character_id);
			if (!$instances_received || $can_receive_multiple)
			{
				AchievementModel::issue_achievement($achievement_id, $account_id, $character_id);
			}
		}
	}

	public static function match_criteria(array $achievements, int $account_id = null, int $character_id = null)
	{
		static $possible_criteria = null;
		if ($possible_criteria === null)
		{
			$possible_criteria = static::get_possible_criteria();
		}

		$possible_members = [];
		$final_matches = [];

		foreach ($achievements as $achievement_id => $rulesets)
		{
			foreach ($rulesets as $ruleset => $rules)
			{
				foreach ($rules as $rule_id => $rule)
				{
					list($criteria_type, $criteria) = $rule;
					if (!isset($possible_criteria[$criteria_type]))
					{
						continue 3; // This isn't a criteria we can currently handle, so abort this achievement.
					}

					$class = $possible_criteria[$criteria_type];
					$fitting_criteria = $class::get_criteria_members($criteria, $account_id, $character_id);
					foreach ($fitting_criteria as $fitting_this_criteria)
					{
						$possible_members[$achievement_id][$ruleset][$rule_id][] = $fitting_this_criteria;
					}

					if (empty($fitting_this_criteria))
					{
						continue 2; // No members fitting this criteria, no point evaluating any further for this achievement ruleset.
					}
				}
			}

			$matches = null;
			foreach ($possible_members[$achievement_id][$ruleset] as $matching_rule)
			{
				// If we're on the first rule, treat the matches directly as fine.
				if ($matches === null)
				{
					$matches = $matching_rule;
					continue;
				}
				// Otherwise try to match the users criteria-wise.
				$matches = array_intersect($matches, $matching_rule);
			}

			if (empty($matches))
			{
				continue;
			}

			foreach ($matches as $account_character_match)
			{
				list($acc, $char) = explode('_', $account_character_match);
				$final_matches[$achievement_id][$acc][$char] = $char;
			}
		}

		// Having built a nested list before (convenient to cheaply deduplicate) we now need to emit a flat list.
		foreach ($final_matches as $achievement_id => $accounts)
		{
			foreach ($accounts as $account => $characters)
			{
				foreach ($characters as $character)
				{
					yield [$achievement_id, $account, $character];
				}
			}
		}
	}
}
