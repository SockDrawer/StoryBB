<?php

/**
 * This file contains a standard way of displaying lists for StoryBB.
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2020 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use LightnCandy\LightnCandy;
use StoryBB\StringLibrary;

/**
 * Create a new list
 * @param array $listOptions An array of options for the list - 'id', 'columns', 'items_per_page', 'get_count', etc.
 */
function createList($listOptions)
{
	global $context, $smcFunc, $txt;

	assert(isset($listOptions['id']));
	assert(isset($listOptions['columns']));
	assert(is_array($listOptions['columns']));
	assert((empty($listOptions['items_per_page']) || (isset($listOptions['get_count']['function'], $listOptions['base_href']) && is_numeric($listOptions['items_per_page']))));
	assert((empty($listOptions['default_sort_col']) || isset($listOptions['columns'][$listOptions['default_sort_col']])));
	assert((!isset($listOptions['form']) || isset($listOptions['form']['href'])));

	call_integration_hook('integrate_' . $listOptions['id'], [&$listOptions]);

	// All the context data will be easily accessible by using a reference.
	$context[$listOptions['id']] = [];
	$list_context = &$context[$listOptions['id']];

	// Figure out the sort.
	if (empty($listOptions['default_sort_col']))
	{
		$list_context['sort'] = [];
		$sort = '1=1';
	}
	else
	{
		$request_var_sort = isset($listOptions['request_vars']['sort']) ? $listOptions['request_vars']['sort'] : 'sort';
		$request_var_desc = isset($listOptions['request_vars']['desc']) ? $listOptions['request_vars']['desc'] : 'desc';
		if (isset($_REQUEST[$request_var_sort], $listOptions['columns'][$_REQUEST[$request_var_sort]], $listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']))
			$list_context['sort'] = [
				'id' => $_REQUEST[$request_var_sort],
				'desc' => isset($_REQUEST[$request_var_desc]) && isset($listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']['reverse']),
			];
		else
			$list_context['sort'] = [
				'id' => $listOptions['default_sort_col'],
				'desc' => (!empty($listOptions['default_sort_dir']) && $listOptions['default_sort_dir'] == 'desc') || (!empty($listOptions['columns'][$listOptions['default_sort_col']]['sort']['default']) && substr($listOptions['columns'][$listOptions['default_sort_col']]['sort']['default'], -4, 4) == 'desc') ? true : false,
			];

		// Set the database column sort.
		$sort = $listOptions['columns'][$list_context['sort']['id']]['sort'][$list_context['sort']['desc'] ? 'reverse' : 'default'];
	}

	$list_context['start_var_name'] = isset($listOptions['start_var_name']) ? $listOptions['start_var_name'] : 'start';
	// In some cases the full list must be shown, regardless of the amount of items.
	if (empty($listOptions['items_per_page']))
	{
		$list_context['start'] = 0;
		$list_context['items_per_page'] = 0;
	}
	// With items per page set, calculate total number of items and page index.
	else
	{
		// First get an impression of how many items to expect.
		if (isset($listOptions['get_count']['file']))
			require_once($listOptions['get_count']['file']);

		$call = call_helper($listOptions['get_count']['function'], true);
		$list_context['total_num_items'] = call_user_func_array($call, empty($listOptions['get_count']['params']) ? [] : $listOptions['get_count']['params']);

		// Default the start to the beginning...sounds logical.
		$list_context['start'] = isset($_REQUEST[$list_context['start_var_name']]) ? (int) $_REQUEST[$list_context['start_var_name']] : 0;
		$list_context['items_per_page'] = $listOptions['items_per_page'];

		// Then create a page index.
		if ($list_context['total_num_items'] > $list_context['items_per_page'])
			$list_context['page_index'] = constructPageIndex($listOptions['base_href'] . (empty($list_context['sort']) ? '' : ';' . $request_var_sort . '=' . $list_context['sort']['id'] . ($list_context['sort']['desc'] ? ';' . $request_var_desc : '')) . ($list_context['start_var_name'] != 'start' ? ';' . $list_context['start_var_name'] . '=%1$d' : ''), $list_context['start'], $list_context['total_num_items'], $list_context['items_per_page'], $list_context['start_var_name'] != 'start');
	}

	// Prepare the headers of the table.
	$list_context['headers'] = [];
	foreach ($listOptions['columns'] as $column_id => $column)
		$list_context['headers'][] = [
			'id' => $column_id,
			'label' => isset($column['header']['eval']) ? eval($column['header']['eval']) : (isset($column['header']['value']) ? $column['header']['value'] : ''),
			'href' => empty($listOptions['default_sort_col']) || empty($column['sort']) ? '' : $listOptions['base_href'] . ';' . $request_var_sort . '=' . $column_id . ($column_id === $list_context['sort']['id'] && !$list_context['sort']['desc'] && isset($column['sort']['reverse']) ? ';' . $request_var_desc : '') . (empty($list_context['start']) ? '' : ';' . $list_context['start_var_name'] . '=' . $list_context['start']),
			'sort_image' => empty($listOptions['default_sort_col']) || empty($column['sort']) || $column_id !== $list_context['sort']['id'] ? null : ($list_context['sort']['desc'] ? 'down' : 'up'),
			'class' => isset($column['header']['class']) ? $column['header']['class'] : '',
			'style' => isset($column['header']['style']) ? $column['header']['style'] : '',
			'colspan' => isset($column['header']['colspan']) ? $column['header']['colspan'] : '',
		];

	// We know the amount of columns, might be useful for the template.
	$list_context['num_columns'] = count($listOptions['columns']);
	$list_context['width'] = isset($listOptions['width']) ? $listOptions['width'] : 0;

	// Get the file with the function for the item list.
	if (isset($listOptions['get_items']['file']))
		require_once($listOptions['get_items']['file']);

	// Call the function and include which items we want and in what order.
	$call = call_helper($listOptions['get_items']['function'], true);
	$list_items = call_user_func_array($call, array_merge([$list_context['start'], $list_context['items_per_page'], $sort], empty($listOptions['get_items']['params']) ? [] : $listOptions['get_items']['params']));
	$list_items = empty($list_items) ? [] : $list_items;

	// Loop through the list items to be shown and construct the data values.
	$list_context['rows'] = [];
	foreach ($list_items as $item_id => $list_item)
	{
		$cur_row = [];
		foreach ($listOptions['columns'] as $column_id => $column)
		{
			$cur_data = [];

			// A value straight from the database?
			if (isset($column['data']['db']))
				$cur_data['value'] = $list_item[$column['data']['db']];

			// Take the value from the database and make it HTML safe.
			elseif (isset($column['data']['db_htmlsafe']))
				$cur_data['value'] = StringLibrary::escape($list_item[$column['data']['db_htmlsafe']]);

			// Using sprintf is probably the most readable way of injecting data.
			elseif (isset($column['data']['sprintf']))
			{
				$params = [];
				foreach ($column['data']['sprintf']['params'] as $sprintf_param => $htmlsafe)
					$params[] = $htmlsafe ? StringLibrary::escape($list_item[$sprintf_param]) : $list_item[$sprintf_param];
				$cur_data['value'] = vsprintf($column['data']['sprintf']['format'], $params);
			}
			elseif (isset($column['data']['yesno']))
			{
				$cur_data['value'] = !empty($list_item[$column['data']['yesno']]) ? $txt['yes'] : $txt['no'];
			}

			// The most flexible way probably is applying a custom function.
			elseif (isset($column['data']['function']))
				$cur_data['value'] = call_user_func_array($column['data']['function'], [$list_item]);

			// A modified value (inject the database values).
			elseif (isset($column['data']['eval']))
				$cur_data['value'] = eval(preg_replace('~%([a-zA-Z0-9\-_]+)%~', '$list_item[\'$1\']', $column['data']['eval']));

			// A literal value.
			elseif (isset($column['data']['value']))
				$cur_data['value'] = $column['data']['value'];

			// Empty value.
			else
				$cur_data['value'] = '';

			// Allow for basic formatting.
			if (!empty($column['data']['comma_format']))
				$cur_data['value'] = comma_format($cur_data['value']);
			elseif (!empty($column['data']['timeformat']))
				$cur_data['value'] = timeformat($cur_data['value']);

			// Set a style class for this column?
			if (isset($column['data']['class']))
				$cur_data['class'] = $column['data']['class'];

			// Fully customized styling for the cells in this column only.
			if (isset($column['data']['style']))
				$cur_data['style'] = $column['data']['style'];

			// Add the data cell properties to the current row.
			$cur_row[$column_id] = $cur_data;
		}

		// Maybe we wat set a custom class for the row based on the data in the row itself
		if (isset($listOptions['data_check']))
		{
			if (isset($listOptions['data_check']['class']))
				$list_context['rows'][$item_id]['class'] = $listOptions['data_check']['class']($list_item);
			if (isset($listOptions['data_check']['style']))
				$list_context['rows'][$item_id]['style'] = $listOptions['data_check']['style']($list_item);
		}

		// Insert the row into the list.
		$list_context['rows'][$item_id]['data'] = $cur_row;
	}

	// The title is currently optional.
	if (isset($listOptions['title']))
		$list_context['title'] = $listOptions['title'];

	// In case there's a form, share it with the template context.
	if (isset($listOptions['form']))
	{
		$list_context['form'] = $listOptions['form'];

		if (!isset($list_context['form']['hidden_fields']))
			$list_context['form']['hidden_fields'] = [];

		// Always add a session check field.
		$list_context['form']['hidden_fields'][$context['session_var']] = $context['session_id'];

		// Will this do a token check?
		if (isset($listOptions['form']['token']))
			$list_context['form']['hidden_fields'][$context[$listOptions['form']['token'] . '_token_var']] = $context[$listOptions['form']['token'] . '_token'];

		// Include the starting page as hidden field?
		if (!empty($list_context['form']['include_start']) && !empty($list_context['start']))
			$list_context['form']['hidden_fields'][$list_context['start_var_name']] = $list_context['start'];

		// If sorting needs to be the same after submitting, add the parameter.
		if (!empty($list_context['form']['include_sort']) && !empty($list_context['sort']))
		{
			$list_context['form']['hidden_fields']['sort'] = $list_context['sort']['id'];
			if ($list_context['sort']['desc'])
				$list_context['form']['hidden_fields']['desc'] = 1;
		}
	}

	// Wanna say something nice in case there are no items?
	if (isset($listOptions['no_items_label']))
	{
		$list_context['no_items_label'] = $listOptions['no_items_label'];
		$list_context['no_items_align'] = isset($listOptions['no_items_align']) ? $listOptions['no_items_align'] : '';
	}

	// A list can sometimes need a few extra rows above and below.
	if (isset($listOptions['additional_rows']))
	{
		$list_context['additional_rows'] = [];
		foreach ($listOptions['additional_rows'] as $row)
		{
			if (empty($row))
				continue;

			// Supported row positions: top_of_list, after_title,
			// above_column_headers, below_table_data, bottom_of_list.
			if (!isset($list_context['additional_rows'][$row['position']]))
				$list_context['additional_rows'][$row['position']] = [];
			$list_context['additional_rows'][$row['position']][] = $row;
		}
	}

	// Add an option for inline JavaScript.
	if (isset($listOptions['javascript']))
		$list_context['javascript'] = $listOptions['javascript'];

	// We want a menu.
	if (isset($listOptions['list_menu']))
		$list_context['list_menu'] = $listOptions['list_menu'];

	/*
		$list_context['list_menu'] = array(
			// This is the style to use.  Tabs or Buttons (Text 1 | Text 2).
			// By default tabs are selected if not set.
			// The main difference between tabs and buttons is that tabs get highlighted if selected.
			// If style is set to buttons and use tabs is disabled then we change the style to old styled tabs.
			'style' => 'tabs',
			// The position of the tabs/buttons.  Left or Right.  By default is set to left.
			'position' => 'left',
			// This is used by the old styled menu.  We *need* to know the total number of columns to span.
			'columns' => 0,
			// This gives you the option to show tabs only at the top, bottom or both.
			// By default they are just shown at the top.
			'show_on' => 'top',
			// Links.  This is the core of the array.  It has all the info that we need.
			'links' => array(
				'name' => array(
					// This will tell use were to go when they click it.
					'href' => $scripturl . '?action=theaction',
					// The name that you want to appear for the link.
					'label' => $txt['name'],
					// If we use tabs instead of buttons we highlight the current tab.
					// Must use conditions to determine if its selected or not.
					'is_selected' => isset($_REQUEST['name']),
				),
			),
		);
	*/

	if (isset($list_context['list_menu']) && !isset($list_context['list_menu']['style']))
	{
		$list_context['list_menu']['style'] = 'tabs';
	}
	if (!isset($list_context['list_menu']['tab_direction']))
	{
		$list_context['list_menu']['tab_direction'] = 'top';
	}

	$list_context['list_menu']['links_list'] = [];
	if (!empty($list_context['list_menu']['links'])) {
		foreach ($list_context['list_menu']['links'] as $id => $link) {
			$list_context['list_menu']['links'][$id]['link_html'] = '<a href="' . $link['href'] . '">' . $link['label'] . '</a>';
			$list_context['list_menu']['links_list'][] = $list_context['list_menu']['links'][$id]['link_html'];
		}
	}
}
