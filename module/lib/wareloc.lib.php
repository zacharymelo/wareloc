<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    lib/wareloc.lib.php
 * \ingroup wareloc
 * \brief   Library functions for wareloc module
 */

/**
 * Prepare head tabs for ProductLocation card
 *
 * @param  ProductLocation $object  Object
 * @return array                    Array of tabs
 */
function productlocation_prepare_head($object)
{
	global $langs, $conf;
	$langs->load('wareloc@wareloc');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/wareloc/productlocation_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/wareloc/productlocation_note.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	if (!empty($object->note_private) || !empty($object->note_public)) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">...</span>';
	}
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'productlocation@wareloc');

	return $head;
}

/**
 * Prepare head tabs for admin setup page
 *
 * @return array  Array of tabs
 */
function wareloc_admin_prepare_head()
{
	global $langs, $conf;
	$langs->load('wareloc@wareloc');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/wareloc/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'wareloc');

	return $head;
}

/**
 * Get active location hierarchy levels for current entity
 *
 * @param  DoliDB $db  Database handler (if null, uses global)
 * @return array       Array of level objects ordered by position, each with: rowid, position, code, label, datatype, list_values, required
 */
function wareloc_get_active_levels($db = null)
{
	global $conf;
	if ($db === null) {
		global $db;
	}

	$levels = array();

	$sql = "SELECT rowid, position, code, label, datatype, list_values, required";
	$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_level";
	$sql .= " WHERE active = 1";
	$sql .= " AND entity IN (".getEntity('wareloc_level').")";
	$sql .= " ORDER BY position ASC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$levels[] = $obj;
		}
		$db->free($resql);
	}

	return $levels;
}

/**
 * Build a human-readable location label from level values
 *
 * @param  array  $level_values  Associative array of level_N => value (e.g., array('level_1' => 'Left', 'level_2' => '3'))
 * @param  array  $levels        Array of level config objects from wareloc_get_active_levels()
 * @return string                Location label like "Row: Left > Bay: 3 > Shelf: 2"
 */
function wareloc_build_location_label($level_values, $levels = null)
{
	if ($levels === null) {
		$levels = wareloc_get_active_levels();
	}

	$parts = array();
	foreach ($levels as $level) {
		$key = 'level_'.$level->position;
		$val = isset($level_values[$key]) ? trim($level_values[$key]) : '';
		if ($val !== '') {
			$parts[] = dol_escape_htmltag($level->label).': '.dol_escape_htmltag($val);
		}
	}

	return implode(' &gt; ', $parts);
}

/**
 * Render location level input fields for forms
 *
 * @param  array   $levels       Active level configs from wareloc_get_active_levels()
 * @param  array   $values       Current values (level_N => value), optional
 * @param  string  $prefix       HTML name prefix (e.g., 'wareloc' or 'wareloc_0')
 * @param  string  $mode         'edit' or 'view'
 * @return string                HTML output
 */
function wareloc_render_level_fields($levels, $values = array(), $prefix = 'wareloc', $mode = 'edit')
{
	global $langs;

	$out = '';
	foreach ($levels as $level) {
		$key = 'level_'.$level->position;
		$name = $prefix.'_'.$key;
		$val = isset($values[$key]) ? $values[$key] : '';
		$req = $level->required ? ' <span class="fieldrequired">*</span>' : '';

		if ($mode === 'view') {
			$out .= '<tr class="oddeven">';
			$out .= '<td class="titlefieldcreate">'.dol_escape_htmltag($level->label).'</td>';
			$out .= '<td>'.dol_escape_htmltag($val).'</td>';
			$out .= '</tr>';
			continue;
		}

		$out .= '<tr class="oddeven">';
		$out .= '<td class="titlefieldcreate">'.dol_escape_htmltag($level->label).$req.'</td>';
		$out .= '<td>';

		switch ($level->datatype) {
			case 'list':
				$list_items = array_map('trim', explode(',', $level->list_values));
				$out .= '<select name="'.$name.'" class="flat minwidth200">';
				$out .= '<option value="">&nbsp;</option>';
				foreach ($list_items as $item) {
					if ($item === '') continue;
					$sel = ($val === $item) ? ' selected' : '';
					$out .= '<option value="'.dol_escape_htmltag($item).'"'.$sel.'>'.dol_escape_htmltag($item).'</option>';
				}
				$out .= '</select>';
				break;
			case 'integer':
				$out .= '<input type="number" name="'.$name.'" class="flat maxwidth100" value="'.dol_escape_htmltag($val).'">';
				break;
			default: // freetext
				$out .= '<input type="text" name="'.$name.'" class="flat minwidth200" value="'.dol_escape_htmltag($val).'">';
				break;
		}

		$out .= '</td>';
		$out .= '</tr>';
	}

	return $out;
}
