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

$action = GETPOST('action', 'aZ09');

// ---- ACTIONS ----

if ($action === 'update') {
	$clear_on_zero = GETPOSTINT('BINLOC_CLEAR_ON_ZERO_STOCK');
	dolibarr_set_const($db, 'BINLOC_CLEAR_ON_ZERO_STOCK', $clear_on_zero, 'chaine', 0, '', $conf->entity);
	$debug_mode = GETPOSTINT('BINLOC_DEBUG_MODE');
	dolibarr_set_const($db, 'BINLOC_DEBUG_MODE', $debug_mode, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// ---- VIEW ----

$page_name = 'BinlocSetup';
llxHeader('', $langs->trans($page_name), '');

$head = binloc_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'stock');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td class="center" width="100">'.$langs->trans('Value').'</td>';
print '</tr>';

// Clear on zero stock
print '<tr class="oddeven">';
print '<td>'.$langs->trans('ClearOnZeroStock');
print '<br><span class="opacitymedium small">'.$langs->trans('ClearOnZeroStockDesc').'</span>';
print '</td>';
print '<td class="center">';
$value = getDolGlobalInt('BINLOC_CLEAR_ON_ZERO_STOCK', 0);
print '<input type="checkbox" name="BINLOC_CLEAR_ON_ZERO_STOCK" value="1"'.($value ? ' checked' : '').'>';
print '</td>';
print '</tr>';

// Debug mode
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DebugMode');
print '<br><span class="opacitymedium small">'.$langs->trans('DebugModeDesc').'</span>';
print '</td>';
print '<td class="center">';
$debug_val = getDolGlobalInt('BINLOC_DEBUG_MODE', 0);
print '<input type="checkbox" name="BINLOC_DEBUG_MODE" value="1"'.($debug_val ? ' checked' : '').'>';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="center margintoponly">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
