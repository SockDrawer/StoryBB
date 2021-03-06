<?php

/**
 * This file has the important job of taking care of help messages and the help center.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Container;
use StoryBB\Helper\Parser;

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 * @uses Help template and Manual language file.
 */
function ShowHelp()
{
	global $context, $txt, $scripturl, $smcFunc, $user_info, $language;

	loadLanguage('Manual');

	$container = \StoryBB\Container::instance();
	$urlgenerator = $container->get('urlgenerator');

	$subActions = [
		'index' => 'HelpIndex',
	];

	$context['manual_sections'] = [
		'smileys' => [
			'link' => $urlgenerator->generate('help_smileys'),
			'title' => $txt['manual_smileys'],
			'desc' => $txt['manual_smileys_desc'],
		],
	];

	$request = $smcFunc['db']->query('', '
		SELECT p.id_policy, pt.policy_type, p.language, p.title, p.description
		FROM {db_prefix}policy_types AS pt
			INNER JOIN {db_prefix}policy AS p ON (p.policy_type = pt.id_policy_type)
		WHERE pt.show_help = 1
		AND p.language IN ({array_string:languages})',
		[
			'languages' => [$language, $user_info['language']],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$subActions[$row['policy_type']] = 'HelpPolicy';
		if (!isset($context['manual_sections'][$row['policy_type']]))
		{
			$context['manual_sections'][$row['policy_type']] = [
				'link' => $scripturl . '?action=help;sa=' . $row['policy_type'],
				'title' => $row['title'],
				'desc' => $row['description'],
				'id_policy' => $row['id_policy'],
			];
		}
		elseif ($row['language'] == $user_info['language'])
		{
			// So we matched multiple, we previously had one (in site language) and now we have one for the user language, so use that.
			$context['manual_sections'][$row['policy_type']]['title'] = $row['title'];
			$context['manual_sections'][$row['policy_type']]['desc'] = $row['description'];
			$context['manual_sections'][$row['policy_type']]['id_policy'] = $row['id_policy'];
		}
	}
	$smcFunc['db']->free_result($request);

	// Now let's see if there are any pages for the user.
	$pages = get_custom_help_pages();
	if (!empty($pages))
	{
		foreach ($pages as $id_page => $page)
		{
			$context['manual_sections']['page_' . $id_page] = [
				'title' => $page['page_title'],
				'desc' => '',
				'link' => $scripturl . '?action=pages;page=' . $page['page_name'],
			];
		}
	}

	// CRUD $subActions as needed.
	routing_integration_hook('integrate_manage_help', [&$subActions]);

	$context['subaction'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'index';
	call_helper($subActions[$context['subaction']]);
}

function get_custom_help_pages()
{
	global $smcFunc, $user_info;

	$pages = [];

	$base_access = allowedTo('admin_forum') ? 'a' : 'x';

	$request = $smcFunc['db']->query('', '
		SELECT id_page, page_name, page_title
		FROM {db_prefix}page
		WHERE show_help = 1
		ORDER BY page_title');
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['access'] = $base_access;
		$pages[$row['id_page']] = $row;
	}
	$smcFunc['db']->free_result($request);

	if (empty($pages))
	{
		return [];
	}

	// Admins don't need to check.
	if (allowedTo('admin_forum'))
	{
		return $pages;
	}

	$request = $smcFunc['db']->query('', '
		SELECT id_page, MAX(allow_deny) AS access
		FROM {db_prefix}page_access
		WHERE id_page IN ({array_int:pages})
			AND id_group IN ({array_int:groups})
		GROUP BY id_page',
		[
			'pages' => array_keys($pages),
			'groups' => $user_info['groups'],
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$pages[$row['id_page']]['access'] = $row['access'] ? 'd' : 'a';
	}
	$smcFunc['db']->free_result($request);

	foreach ($pages as $id_page => $page)
	{
		if ($page['access'] != 'a')
		{
			unset($pages[$id_page]);
		}
	}

	return $pages;
}

/**
 * The main page for the Help section
 */
function HelpIndex()
{
	global $scripturl, $context, $txt;

	$context['canonical_url'] = $scripturl . '?action=help';

	// Build the link tree.
	$context['linktree'][] = [
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	];

	// Lastly, some minor template stuff.
	$context['page_title'] = $txt['manual_storybb_user_help'];
	$context['sub_template'] = 'help_manual';
}

/**
 * Display a policy page.
 */
function HelpPolicy()
{
	global $scripturl, $context, $txt, $smcFunc, $cookiename, $modSettings;

	$context['canonical_url'] = $scripturl . '?action=help;sa=' . $context['subaction'];

	// Build the link tree.
	$context['linktree'][] = [
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	];
	$context['linktree'][] = [
		'url' => $context['canonical_url'],
		'name' => $context['manual_sections'][$context['subaction']]['title'],
	];
	
	// We know if we're here the policy exists.
	$request = $smcFunc['db']->query('', '
		SELECT p.id_policy, pr.last_change, pr.revision_text
		FROM {db_prefix}policy_revision AS pr
			INNER JOIN {db_prefix}policy AS p ON (p.last_revision = pr.id_revision)
		WHERE p.id_policy = {int:policy}',
		[
			'policy' => $context['manual_sections'][$context['subaction']]['id_policy'],
		]
	);
	$row = $smcFunc['db']->fetch_assoc($request);

	$context['policy_name'] = $context['manual_sections'][$context['subaction']]['title'];

	// Replace out some of the placeholders in our text.
	$replacements = [
		'{$forum_name}' => $context['forum_name_html_safe'],
		'{$contact_url}' => $scripturl . '?action=contact',
		'{$cookiename}' => $cookiename,
		'{$age}' => $modSettings['minimum_age'],
		'{$cookiepolicy}' => $scripturl . '?action=help;sa=cookies',
	];
	$context['policy_text'] = Parser::parse_bbc(strtr($row['revision_text'], $replacements), false);
	$context['last_updated'] = timeformat($row['last_change']);

	$context['page_title'] = $context['policy_name'];
	$context['sub_template'] = 'help_policy';
}

/**
 * Show some of the more detailed help to give the admin an idea...
 * It shows a popup for administrative or user help.
 * It uses the help parameter to decide what string to display and where to get
 * the string from. ($helptxt or $txt?)
 * It is accessed via ?action=helpadmin;help=?.
 * @uses ManagePermissions language file, if the help starts with permissionhelp.
 * @uses Help template, popup sub template, no layers.
 */
function ShowAdminHelp()
{
	global $txt, $helptxt, $context, $scripturl;

	if (!isset($_GET['help']) || !is_string($_GET['help']))
		fatal_lang_error('no_access', false);

	if (!isset($helptxt))
		$helptxt = [];

	// Load the admin help language file and template.
	loadLanguage('Help');

	// Permission specific help?
	if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	// Allow mods to load their own language file here
	call_integration_hook('integrate_helpadmin');

	StoryBB\Template::set_layout('popup');

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];
	$context['popup_id'] = 'help_popup';

	// Don't show any template layers, just the popup sub template.
	$context['sub_template'] = 'help_text';

	// What help string should be used?
	if (isset($helptxt[$_GET['help']]))
		$context['help_text'] = $helptxt[$_GET['help']];
	elseif (isset($txt[$_GET['help']]))
		$context['help_text'] = $txt[$_GET['help']];
	else
		$context['help_text'] = $_GET['help'];

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text']))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}
