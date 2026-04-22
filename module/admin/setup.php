<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    admin/setup.php
 * \ingroup binloc
 * \brief   Binloc module general settings
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/binloc/lib/binloc.lib.php');

$langs->loadLangs(array('admin', 'binloc@binloc'));

if (!$user->admin) {
	accessforbidden();
}

// ---- VIEW ----

$page_name = 'BinlocSetup';
llxHeader('', $langs->trans($page_name), '');

$head = binloc_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'stock');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td class="center" width="100">'.$langs->trans('Value').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '</tr>';

// Clear on zero stock
print '<tr class="oddeven">';
print '<td>'.$langs->trans('ClearOnZeroStock').'</td>';
print '<td class="center">';
print ajax_constantonoff('BINLOC_CLEAR_ON_ZERO_STOCK');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('ClearOnZeroStockDesc').'</td>';
print '</tr>';

// Debug mode
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DebugMode').'</td>';
print '<td class="center">';
print ajax_constantonoff('BINLOC_DEBUG_MODE');
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('DebugModeDesc').'</td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
