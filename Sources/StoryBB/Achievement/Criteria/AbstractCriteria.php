<?php

/**
 * This class handles underlying functionality for all achievement criteria..
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Achievement\Criteria;

/**
 * This class handles identifying whether a character has a birthday or not.
 */
abstract class AbstractCriteria
{
	abstract public static function parameters(): array;

	public static function assignable_to()
	{
		return [
			'account' => is_a(static::class, 'StoryBB\\Achievement\\AccountAchievement', true),
			'character' => is_a(static::class, 'StoryBB\\Achievement\\CharacterAchievement', true),
		];
	}

	public static function is_unlockable()
	{
		return is_a(static::class, 'StoryBB\\Achievement\\UnlockableAchievement', true);
	}

	public static function validate_parameters($criteria)
	{
		$criteria = json_decode($criteria, true);
		$valid_criteria = static::parameters();

		foreach ($valid_criteria as $criterion_name => $criterion_type)
		{
			if (!$criterion_type['optional'] && !isset($criteria[$criterion_name]))
			{
				return false;
			}
		}

		foreach ($criteria as $criterion_name => $criterion_requirements)
		{
			if (!isset($valid_criteria[$criterion_name]))
			{
				return false;
			}
			$this_criterion = $valid_criteria[$criterion_name];
			switch ($this_criterion['type'])
			{
				case 'int':
					if (!is_int($criterion_requirements) && (string) (int) $criterion_requirements != $criterion_requirements)
					{
						return false;
					}
					if (isset($this_criterion['min']) && $criterion_requirements < $this_criterion['min'])
					{
						return false;
					}
					if (isset($this_criterion['max']) && $criterion_requirements > $this_criterion['max'])
					{
						return false;
					}
					break;
			}
		}

		return true;
	}

	abstract public static function get_criteria_members($criteria, $account_id = null, $character_id = null);
}
