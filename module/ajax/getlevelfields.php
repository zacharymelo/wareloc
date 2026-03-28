<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/getlevelfields.php
 * \ingroup wareloc
 * \brief   AJAX endpoint — returns rendered level field HTML for a given warehouse
 */

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

dol_include_once('/wareloc/lib/wareloc.lib.php');

$fk_entrepot = GETPOSTINT('fk_entrepot');
$prefix      = GETPOST('prefix', 'aZ09') ?: 'wareloc';
$mode        = GETPOST('mode', 'alpha') ?: 'edit';
$values_json = GETPOST('values', 'restricthtml');

$values = array();
if (!empty($values_json)) {
	$values = json_decode($values_json, true);
	if (!is_array($values)) {
		$values = array();
	}
}

$levels = wareloc_get_active_levels(null, $fk_entrepot);

header('Content-Type: text/html; charset=UTF-8');

if (empty($levels)) {
	print '<tr><td colspan="2"><div class="warning">'.$langs->trans('NoLevelsConfigured').'</div></td></tr>';
} else {
	print wareloc_render_level_fields($levels, $values, $prefix, $mode);
}
