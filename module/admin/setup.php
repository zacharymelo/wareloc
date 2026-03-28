<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    admin/setup.php
 * \ingroup wareloc
 * \brief   Wareloc module setup page — hierarchy config + general settings
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/wareloc/lib/wareloc.lib.php');

$langs->loadLangs(array('admin', 'wareloc@wareloc'));

if (!$user->admin) { accessforbidden(); }

$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$levelid = GETPOSTINT('levelid');

$error = 0;

// ---- ACTIONS ----

if ($cancel) {
	$action = '';
}

// Add a new hierarchy level
if ($action === 'addlevel') {
	$code     = GETPOST('level_code', 'aZ09');
	$label    = GETPOST('level_label', 'alpha');
	$datatype = GETPOST('level_datatype', 'alpha');
	$list_val = GETPOST('level_list_values', 'restricthtml');
	$required = GETPOSTINT('level_required');

	if (empty($code) || empty($label)) {
		setEventMessages('Code and Label are required', null, 'errors');
		$error++;
	}

	if (!in_array($datatype, array('freetext', 'integer', 'list'))) {
		$datatype = 'freetext';
	}

	if (!$error) {
		// Check max 6 levels
		$sql = "SELECT COUNT(rowid) as cnt FROM ".MAIN_DB_PREFIX."wareloc_level";
		$sql .= " WHERE active = 1 AND entity = ".((int) $conf->entity);
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		if ($obj->cnt >= 6) {
			setEventMessages($langs->trans('MaxLevelsReached'), null, 'errors');
			$error++;
		}
	}

	if (!$error) {
		// Get next position
		$sql = "SELECT MAX(position) as maxpos FROM ".MAIN_DB_PREFIX."wareloc_level";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		$next_pos = ($obj->maxpos ? $obj->maxpos + 1 : 1);

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."wareloc_level (";
		$sql .= "entity, position, code, label, datatype, list_values, required, active, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $conf->entity);
		$sql .= ", ".((int) $next_pos);
		$sql .= ", '".$db->escape($code)."'";
		$sql .= ", '".$db->escape($label)."'";
		$sql .= ", '".$db->escape($datatype)."'";
		$sql .= ", ".($list_val ? "'".$db->escape($list_val)."'" : "NULL");
		$sql .= ", ".((int) $required);
		$sql .= ", 1";
		$sql .= ", '".$db->idate(dol_now())."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans('LevelAdded'), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	$action = '';
}

// Update a hierarchy level
if ($action === 'updatelevel' && $levelid > 0) {
	$label    = GETPOST('level_label', 'alpha');
	$datatype = GETPOST('level_datatype', 'alpha');
	$list_val = GETPOST('level_list_values', 'restricthtml');
	$required = GETPOSTINT('level_required');

	if (!in_array($datatype, array('freetext', 'integer', 'list'))) {
		$datatype = 'freetext';
	}

	$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_level SET";
	$sql .= " label = '".$db->escape($label)."'";
	$sql .= ", datatype = '".$db->escape($datatype)."'";
	$sql .= ", list_values = ".($list_val ? "'".$db->escape($list_val)."'" : "NULL");
	$sql .= ", required = ".((int) $required);
	$sql .= " WHERE rowid = ".((int) $levelid);
	$sql .= " AND entity = ".((int) $conf->entity);

	$resql = $db->query($sql);
	if ($resql) {
		setEventMessages($langs->trans('LevelUpdated'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	$action = '';
}

// Deactivate (soft-delete) a level
if ($action === 'deletelevel' && $levelid > 0) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_level SET active = 0";
	$sql .= " WHERE rowid = ".((int) $levelid);
	$sql .= " AND entity = ".((int) $conf->entity);

	$resql = $db->query($sql);
	if ($resql) {
		setEventMessages($langs->trans('LevelDeactivated'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	$action = '';
}

// Move level up
if ($action === 'movelevelup' && $levelid > 0) {
	_wareloc_swap_level_position($db, $conf, $levelid, 'up');
	$action = '';
}

// Move level down
if ($action === 'moveleveldown' && $levelid > 0) {
	_wareloc_swap_level_position($db, $conf, $levelid, 'down');
	$action = '';
}

// Save general settings
if ($action === 'update') {
	dolibarr_set_const($db, 'WARELOC_AUTO_ASSIGN_ON_RECEPTION', GETPOST('WARELOC_AUTO_ASSIGN_ON_RECEPTION', 'alpha'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'WARELOC_DEFAULT_STATUS_ON_RECEPTION', GETPOST('WARELOC_DEFAULT_STATUS_ON_RECEPTION', 'alpha'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'WARELOC_DEBUG_MODE', GETPOST('WARELOC_DEBUG_MODE', 'alpha'), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// ---- VIEW ----

llxHeader('', $langs->trans('WarelocSetup'), '');

$head = wareloc_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('WarelocSetup'), -1, 'stock');

// ========================================
// SECTION 1: Hierarchy Levels
// ========================================

print load_fiche_titre($langs->trans('HierarchyConfig'), '', '');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('HierarchyConfigDesc').'</div>';

// Fetch current active levels
$levels = array();
$sql = "SELECT rowid, position, code, label, datatype, list_values, required";
$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_level";
$sql .= " WHERE active = 1 AND entity = ".((int) $conf->entity);
$sql .= " ORDER BY position ASC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$levels[] = $obj;
	}
	$db->free($resql);
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('LevelPosition').'</td>';
print '<td>'.$langs->trans('LevelCode').'</td>';
print '<td>'.$langs->trans('LevelLabel').'</td>';
print '<td>'.$langs->trans('LevelDatatype').'</td>';
print '<td>'.$langs->trans('LevelListValues').'</td>';
print '<td class="center">'.$langs->trans('LevelRequired').'</td>';
print '<td class="right">'.$langs->trans('Actions').'</td>';
print '</tr>';

$datatype_labels = array(
	'freetext' => $langs->trans('DatatypeFreetext'),
	'integer'  => $langs->trans('DatatypeInteger'),
	'list'     => $langs->trans('DatatypeList'),
);

$edit_levelid = ($action === 'editlevel') ? $levelid : 0;

foreach ($levels as $idx => $lev) {
	$is_editing = ($edit_levelid == $lev->rowid);

	print '<tr class="oddeven">';

	if ($is_editing) {
		// Edit mode
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="updatelevel">';
		print '<input type="hidden" name="levelid" value="'.$lev->rowid.'">';

		print '<td>'.$lev->position.'</td>';
		print '<td>'.dol_escape_htmltag($lev->code).'</td>';
		print '<td><input type="text" name="level_label" class="flat minwidth150" value="'.dol_escape_htmltag($lev->label).'"></td>';
		print '<td><select name="level_datatype" class="flat" onchange="document.getElementById(\'listvals_edit\').style.display=(this.value==\'list\'?\'inline\':\'none\')">';
		foreach ($datatype_labels as $dt => $dtlabel) {
			$sel = ($lev->datatype === $dt) ? ' selected' : '';
			print '<option value="'.$dt.'"'.$sel.'>'.$dtlabel.'</option>';
		}
		print '</select></td>';
		print '<td><input type="text" id="listvals_edit" name="level_list_values" class="flat minwidth200" value="'.dol_escape_htmltag($lev->list_values).'"'.($lev->datatype !== 'list' ? ' style="display:none"' : '').'></td>';
		print '<td class="center"><input type="checkbox" name="level_required" value="1"'.($lev->required ? ' checked' : '').'></td>';
		print '<td class="right">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a>';
		print '</td>';
		print '</form>';
	} else {
		// View mode
		print '<td>'.$lev->position.'</td>';
		print '<td>'.dol_escape_htmltag($lev->code).'</td>';
		print '<td>'.dol_escape_htmltag($lev->label).'</td>';
		print '<td>'.dol_escape_htmltag($datatype_labels[$lev->datatype]).'</td>';
		print '<td>'.($lev->datatype === 'list' ? dol_escape_htmltag(dol_trunc($lev->list_values, 50)) : '-').'</td>';
		print '<td class="center">'.($lev->required ? img_picto('Yes', 'tick') : '').'</td>';
		print '<td class="right nowraponall">';

		// Move up
		if ($idx > 0) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=movelevelup&levelid='.$lev->rowid.'&token='.newToken().'">'.img_picto($langs->trans('MoveLevelUp'), 'up', 'class="pictofixedwidth"').'</a>';
		}
		// Move down
		if ($idx < count($levels) - 1) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=moveleveldown&levelid='.$lev->rowid.'&token='.newToken().'">'.img_picto($langs->trans('MoveLevelDown'), 'down', 'class="pictofixedwidth"').'</a>';
		}
		// Edit
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=editlevel&levelid='.$lev->rowid.'&token='.newToken().'">'.img_picto($langs->trans('EditLevel'), 'edit', 'class="pictofixedwidth"').'</a>';
		// Delete
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=deletelevel&levelid='.$lev->rowid.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteLevel').'\')">'.img_picto($langs->trans('DeleteLevel'), 'delete', 'class="pictofixedwidth"').'</a>';

		print '</td>';
	}
	print '</tr>';
}

// Add new level form
if (count($levels) < 6 && $action !== 'editlevel') {
	print '<tr class="oddeven">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addlevel">';

	print '<td><em>'.(count($levels) + 1).'</em></td>';
	print '<td><input type="text" name="level_code" class="flat maxwidth100" placeholder="e.g., row"></td>';
	print '<td><input type="text" name="level_label" class="flat minwidth150" placeholder="e.g., Row"></td>';
	print '<td><select name="level_datatype" class="flat" onchange="document.getElementById(\'listvals_new\').style.display=(this.value==\'list\'?\'inline\':\'none\')">';
	foreach ($datatype_labels as $dt => $dtlabel) {
		print '<option value="'.$dt.'">'.$dtlabel.'</option>';
	}
	print '</select></td>';
	print '<td><input type="text" id="listvals_new" name="level_list_values" class="flat minwidth200" placeholder="Left,Right" style="display:none"></td>';
	print '<td class="center"><input type="checkbox" name="level_required" value="1"></td>';
	print '<td class="right"><input type="submit" class="button" value="'.$langs->trans('AddLevel').'"></td>';

	print '</form>';
	print '</tr>';
}

print '</table>';

print '<br>';

// ========================================
// SECTION 2: General Settings
// ========================================

print load_fiche_titre($langs->trans('GeneralSettings'), '', '');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '</tr>';

// Auto-assign on reception
print '<tr class="oddeven">';
print '<td>'.$langs->trans('AutoAssignOnReception').'</td>';
print '<td>';
$chk = getDolGlobalString('WARELOC_AUTO_ASSIGN_ON_RECEPTION') ? ' checked' : '';
print '<input type="checkbox" name="WARELOC_AUTO_ASSIGN_ON_RECEPTION" value="1"'.$chk.'>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('AutoAssignOnReceptionDesc').'</td>';
print '</tr>';

// Default status on reception
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DefaultStatusOnReception').'</td>';
print '<td>';
$cur_status = getDolGlobalString('WARELOC_DEFAULT_STATUS_ON_RECEPTION', '1');
print '<select name="WARELOC_DEFAULT_STATUS_ON_RECEPTION" class="flat">';
print '<option value="0"'.($cur_status == '0' ? ' selected' : '').'>'.$langs->trans('StatusDraft').'</option>';
print '<option value="1"'.($cur_status == '1' ? ' selected' : '').'>'.$langs->trans('StatusActive').'</option>';
print '</select>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('DefaultStatusOnReception').'</td>';
print '</tr>';

// Debug mode
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DebugMode').'</td>';
print '<td>';
$chk_debug = getDolGlobalString('WARELOC_DEBUG_MODE') ? ' checked' : '';
print '<input type="checkbox" name="WARELOC_DEBUG_MODE" value="1"'.$chk_debug.'>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('DebugModeDesc').'</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();


// ---- HELPER FUNCTIONS ----

/**
 * Swap position of a level with its neighbor
 *
 * @param  DoliDB $db    Database
 * @param  Conf   $conf  Configuration
 * @param  int    $id    Level rowid
 * @param  string $dir   'up' or 'down'
 * @return void
 */
function _wareloc_swap_level_position($db, $conf, $id, $dir)
{
	global $langs;

	// Get current position
	$sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."wareloc_level";
	$sql .= " WHERE rowid = ".((int) $id);
	$sql .= " AND entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if (!$resql) return;
	$current = $db->fetch_object($resql);
	if (!$current) return;

	// Find neighbor
	$neighbor_pos = ($dir === 'up') ? $current->position - 1 : $current->position + 1;
	$sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."wareloc_level";
	$sql .= " WHERE position = ".((int) $neighbor_pos);
	$sql .= " AND active = 1 AND entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if (!$resql) return;
	$neighbor = $db->fetch_object($resql);
	if (!$neighbor) return;

	// Swap positions
	$db->begin();

	$sql1 = "UPDATE ".MAIN_DB_PREFIX."wareloc_level SET position = ".((int) $neighbor->position);
	$sql1 .= " WHERE rowid = ".((int) $current->rowid);

	$sql2 = "UPDATE ".MAIN_DB_PREFIX."wareloc_level SET position = ".((int) $current->position);
	$sql2 .= " WHERE rowid = ".((int) $neighbor->rowid);

	if ($db->query($sql1) && $db->query($sql2)) {
		$db->commit();
		setEventMessages($langs->trans('LevelMoved'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	}
}
