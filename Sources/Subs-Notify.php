<?php

/**
 * Provides some supporting functionality regarding notifications, notably getting user preferences.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

/**
 * Fetches the list of preferences (or a single/subset of preferences) for
 * notifications for one or more users.
 *
 * @param int|array $members A user id or an array of (integer) user ids to load preferences for
 * @param string|array $prefs An empty string to load all preferences, or a string (or array) of preference name(s) to load
 * @param bool $process_default Whether to apply the default values to the members' values or not.
 * @return array An array of user ids => array (pref name -> value), with user id 0 representing the defaults
 */
function getNotifyPrefs($members, $prefs = '', $process_default = false)
{
	global $smcFunc;

	// We want this as an array whether it is or not.
	$members = is_array($members) ? $members : (array) $members;

	if (!empty($prefs))
		$prefs = is_array($prefs) ? $prefs : (array) $prefs;

	$result = [];

	// We want to now load the default, which is stored with a member id of 0.
	$members[] = 0;

	$request = $smcFunc['db']->query('', '
		SELECT id_member, alert_pref, alert_value
		FROM {db_prefix}user_alerts_prefs
		WHERE id_member IN ({array_int:members})' . (!empty($prefs) ? '
			AND alert_pref IN ({array_string:prefs})' : ''),
		[
			'members' => $members,
			'prefs' => $prefs,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$result[$row['id_member']][$row['alert_pref']] = $row['alert_value'];
	}

	// We may want to keep the default values separate from a given user's. Or we might not.
	if ($process_default && isset($result[0]))
	{
		foreach ($members as $member)
		{
			foreach ($result[0] as $key => $value)
			{
				if (!isset($result[$member][$key]))
				{
					$result[$member][$key] = $value;
				}
			}
		}

		unset ($result[0]);
	}

	return $result;
}

/**
 * Sets the list of preferences for a single user.
 *
 * @param int $memID The user whose preferences you are setting
 * @param array $prefs An array key of pref -> value
 */
function setNotifyPrefs($memID, $prefs = [])
{
	global $smcFunc;

	if (empty($prefs) || !is_int($memID))
		return;

	$update_rows = [];
	foreach ($prefs as $k => $v)
		$update_rows[] = [$memID, $k, $v];

	$smcFunc['db']->insert('replace',
		'{db_prefix}user_alerts_prefs',
		['id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'],
		$update_rows,
		['id_member', 'alert_pref']
	);
}

/**
 * Deletes notification preference
 *
 * @param int $memID The user whose preference you're setting
 * @param array $prefs The preferences to delete
 */
function deleteNotifyPrefs($memID, array $prefs)
{
	global $smcFunc;

	if (empty($prefs) || empty($memID))
		return;

	$smcFunc['db']->query('', '
		DELETE FROM {db_prefix}user_alerts_prefs
		WHERE id_member = {int:member}
			AND alert_pref IN ({array_string:prefs})',
		[
			'member' => $memID,
			'prefs' => $prefs,
		]
	);
}
