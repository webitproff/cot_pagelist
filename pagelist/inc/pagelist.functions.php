<?php
/**
 * Functions for the PageList Plugin
 *
 * @package PageList
 * @copyright (c) 2012-2023 seditio.by
 */

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('page', 'module');

/**
 * Returns condition as SQL string
 * @param  string $cc_mode Selection mode: single, black or white
 * @param  string $cc_cats Cat (or cats semicolon separated)
 * @return string           Condition as SQL string
 */
function cot_compilecats($cc_mode = '', $cc_cats = '', $cc_sub = TRUE) {

	global $db, $structure;

	if (!empty($cc_cats) && ($cc_mode == 'single' || $cc_mode == 'array' || $cc_mode == 'white' || $cc_mode == 'black')) {

		$cc_cats = str_replace(' ', '', $cc_cats);

		if ($cc_mode == 'single') {
			$cc_cats = cot_structure_children('page', $cc_cats, $cc_sub);
			if (count($cc_cats) > 1) {
				$cc_where = "AND page_cat IN ('" . implode("','", $cc_cats) . "')";
			}
			else {
				$cc_where = "AND page_cat = " . $db->quote($cc_cats[0]);
			}
		}

    elseif ($cc_mode == 'array') {
      $cc_cats = implode(',', $cc_cats);
      $cc_cats = '"'.$cc_cats.'"';
  		$cc_cats = str_replace(',', '","', $cc_cats);
      $cc_where = "AND page_id IN ($cc_cats)";
    }

		else {
			if ($cc_mode == 'white') {
				$wl = explode(';', $cc_cats);
			}
			else {
				$bl = explode(';', $cc_cats);
			}
			$tempcats = array();
			foreach ($structure['page'] as $code => $row) {
				if ($cc_mode == 'black' && !in_array($code, $bl) || $cc_mode == 'white' && in_array($code, $wl)) {
					$tempcats[] = $code;
				}
			}
			if ($cc_mode == 'white') {
				$cc_cats = array_intersect($tempcats, $wl);
			}
			else {
				$cc_cats = array_diff($tempcats, $bl);
			}
			$cc_where = "AND page_cat IN ('" . implode("','", $cc_cats) . "')";
		}
	}
	return $cc_where;

}

/**
 * Generates PageList widget
 * @param  string  $tpl        01. Template code
 * @param  integer $items      02. Number of items to show. 0 - all items
 * @param  string  $order      03. Sorting order (SQL)
 * @param  string  $condition  04. Custom selection filter (SQL)
 * @param  string  $mode       05. Ctegory selection mode (single, array, white, black)
 * @param  string  $cats       06. Category [list, semicolon separated]
 * @param  boolean $sub        07. Include subcategories TRUE/FALSE
 * @param  string  $pagination 08. Pagination parameter name for the URL, e.g. 'pld'. Make sure it does not conflict with other paginations.
 * @param  boolean $noself     09. Exclude the current page from the rowset for pages.
 * @param  int     $offset     10. Exclude specified number of records
 * @return string              Parsed HTML
 */
function cot_pagelist($tpl = 'pagelist', $items = 0, $order = '', $condition = '', $mode = '', $cats = '', $sub = TRUE, $pagination = NULL, $noself = TRUE, $offset = '0') {

	global $db, $db_pages, $env, $structure, $cot_extrafields, $cfg;

	/* === Hook === */
	foreach (array_merge(cot_getextplugins('pagelist.first')) as $pl)
	{
		include $pl;
	}
	/* ===== */

	$where_cat = cot_compilecats($mode, $cats, $sub);
	$where_condition = (empty($condition)) ? '' : "AND $condition";

	if (($noself === TRUE) && defined('COT_PAGES') && !defined('COT_LIST')) {
		global $id;
		$where_condition .= " AND page_id != $id";
	}

	// Get pagination if necessary
	if (!is_null($pagination)) {
		list($pg, $d, $durl) = cot_import_pagenav($pagination, $items);
	}
	else {
		$d = 0;
	}

	// Display the items
	$t = new XTemplate(cot_tplfile($tpl, 'plug'));

	//
	if ($cfg['plugin']['pagelist']['users']) {
		global $db_users;
		$pagelist_join_columns .= ' , u.* ';
		$pagelist_join_tables .= ' LEFT JOIN '.$db_users.' AS u ON u.user_id = p.page_ownerid ';
	}

	// Add i18n features if installed
	if (cot_plugin_active('i18n')) {
		global $db_i18n_pages, $i18n_locale;
		$pagelist_join_columns .= ' , i18n.* ';
		$pagelist_join_tables .= ' LEFT JOIN '.$db_i18n_pages.' AS i18n ON i18n.ipage_id=p.page_id AND i18n.ipage_locale="'.$i18n_locale.'" AND i18n.ipage_id IS NOT NULL ';
	}

	/* === Hook === */
	foreach (array_merge(cot_getextplugins('pagelist.query')) as $pl) {
		include $pl;
	}
	/* ===== */

	$sql_order = empty($order) ? '' : "ORDER BY $order";

	$d = $d + $offset;
	$sql_limit = ($items > 0) ? "LIMIT $d, $items" : '';

	$res = $db->query("SELECT p.* $pagelist_join_columns
		FROM $db_pages AS p
		$pagelist_join_tables
		WHERE page_state='0' $where_cat $where_condition
		$sql_order $sql_limit");

	$totalitems = $db->query("SELECT COUNT(*) FROM $db_pages AS p $pagelist_join_tables WHERE page_state='0' $where_cat $where_condition")->fetchColumn();

	$jj = 1;
	while ($row = $res->fetch()) {
		$t->assign(cot_generate_pagetags($row, 'PAGE_ROW_'));

		$t->assign(array(
			'PAGE_ROW_NUM'     => $jj,
			'PAGE_ROW_ODDEVEN' => cot_build_oddeven($jj),
			'PAGE_ROW_RAW'     => $row
		));

		if ($cfg['plugin']['pagelist']['users']) {
			$t->assign(cot_generate_usertags($row, 'PAGE_ROW_OWNER_'));
		}

		/* === Hook === */
		foreach (cot_getextplugins('pagelist.loop') as $pl)
		{
			include $pl;
		}
		/* ===== */

		$t->parse("MAIN.PAGE_ROW");
		$jj++;
	}

	// Render pagination
	if (!is_null($pagination)) {
		$url_area = defined('COT_PLUG') ? 'plug' : $env['ext'];
		if (defined('COT_LIST')) {
			global $list_url_path;
			$url_params = $list_url_path;
		}
		elseif (defined('COT_PAGES')) {
			global $al, $id, $pag;
			$url_params = empty($al) ? array('c' => $pag['page_cat'], 'id' => $id) :  array('c' => $pag['page_cat'], 'al' => $al);
		}
		else {
			$url_params = array();
		}
		$url_params[$pagination] = $durl;
		$pagenav = cot_pagenav($url_area, $url_params, $d, $totalitems, $items, $pagination);
		// Assign pagination tags
		$t->assign(array(
			'PAGE_TOP_PAGINATION'  => $pagenav['main'],
			'PAGE_TOP_PAGEPREV'    => $pagenav['prev'],
			'PAGE_TOP_PAGENEXT'    => $pagenav['next'],
			'PAGE_TOP_FIRST'       => $pagenav['first'],
			'PAGE_TOP_LAST'        => $pagenav['last'],
			'PAGE_TOP_CURRENTPAGE' => $pagenav['current'],
			'PAGE_TOP_TOTALLINES'  => $totalitems,
			'PAGE_TOP_MAXPERPAGE'  => $items,
			'PAGE_TOP_TOTALPAGES'  => $pagenav['total']
		));
	}

	/* === Hook === */
	foreach (cot_getextplugins('pagelist.tags') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$t->parse();
	return $t->text();
}