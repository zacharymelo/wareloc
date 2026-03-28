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
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
dol_include_once('/wareloc/lib/wareloc.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('admin', 'wareloc@wareloc'));

if (!$user->admin) { accessforbidden(); }

$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$levelid = GETPOSTINT('levelid');
$selected_wh = GETPOSTINT('wh'); // 0 = global default, >0 = warehouse override

$error = 0;

// Build warehouse fk_entrepot SQL fragment for queries
$wh_sql = ($selected_wh > 0) ? " AND fk_entrepot = ".((int) $selected_wh) : " AND fk_entrepot IS NULL";

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
		// Check max 6 levels in this warehouse context
		$sql = "SELECT COUNT(rowid) as cnt FROM ".MAIN_DB_PREFIX."wareloc_level";
		$sql .= " WHERE active = 1 AND entity = ".((int) $conf->entity);
		$sql .= $wh_sql;
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		if ($obj->cnt >= 6) {
			setEventMessages($langs->trans('MaxLevelsReached'), null, 'errors');
			$error++;
		}
	}

	if (!$error) {
		// Get next position in this warehouse context
		$sql = "SELECT MAX(position) as maxpos FROM ".MAIN_DB_PREFIX."wareloc_level";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= $wh_sql;
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		$next_pos = ($obj->maxpos ? $obj->maxpos + 1 : 1);

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."wareloc_level (";
		$sql .= "entity, fk_entrepot, position, code, label, datatype, list_values, required, active, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $conf->entity);
		$sql .= ", ".($selected_wh > 0 ? ((int) $selected_wh) : "NULL");
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
	_wareloc_swap_level_position($db, $conf, $levelid, 'up', $selected_wh);
	$action = '';
}

// Move level down
if ($action === 'moveleveldown' && $levelid > 0) {
	_wareloc_swap_level_position($db, $conf, $levelid, 'down', $selected_wh);
	$action = '';
}

// Initialize warehouse levels from global defaults
if ($action === 'initfromglobal' && $selected_wh > 0) {
	// Fetch global levels
	$sql = "SELECT position, code, label, datatype, list_values, required";
	$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_level";
	$sql .= " WHERE active = 1 AND fk_entrepot IS NULL";
	$sql .= " AND entity = ".((int) $conf->entity);
	$sql .= " ORDER BY position ASC";

	$resql = $db->query($sql);
	if ($resql) {
		$db->begin();
		$copy_error = 0;
		while ($obj = $db->fetch_object($resql)) {
			$isql = "INSERT INTO ".MAIN_DB_PREFIX."wareloc_level (";
			$isql .= "entity, fk_entrepot, position, code, label, datatype, list_values, required, active, date_creation, fk_user_creat";
			$isql .= ") VALUES (";
			$isql .= ((int) $conf->entity);
			$isql .= ", ".((int) $selected_wh);
			$isql .= ", ".((int) $obj->position);
			$isql .= ", '".$db->escape($obj->code)."'";
			$isql .= ", '".$db->escape($obj->label)."'";
			$isql .= ", '".$db->escape($obj->datatype)."'";
			$isql .= ", ".($obj->list_values ? "'".$db->escape($obj->list_values)."'" : "NULL");
			$isql .= ", ".((int) $obj->required);
			$isql .= ", 1";
			$isql .= ", '".$db->idate(dol_now())."'";
			$isql .= ", ".((int) $user->id);
			$isql .= ")";
			if (!$db->query($isql)) {
				$copy_error++;
			}
		}
		if ($copy_error) {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			$db->commit();
			setEventMessages($langs->trans('LevelsInitialized'), null, 'mesgs');
		}
	}
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

// Helper: build URL with wh param preserved
$base_url = $_SERVER['PHP_SELF'].($selected_wh > 0 ? '?wh='.$selected_wh : '');
$wh_param = $selected_wh > 0 ? '&wh='.$selected_wh : '';
$wh_hidden = $selected_wh > 0 ? '<input type="hidden" name="wh" value="'.$selected_wh.'">' : '';

// ---- VIEW ----

llxHeader('', $langs->trans('WarelocSetup'), '');

$form = new Form($db);
$head = wareloc_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('WarelocSetup'), -1, 'stock');

// ========================================
// SECTION 1: Hierarchy Levels
// ========================================

print load_fiche_titre($langs->trans('HierarchyConfig'), '', '');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('HierarchyConfigDesc').'</div>';

// Warehouse selector
$warehouses = array();
$sql_wh = "SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot";
$sql_wh .= " WHERE entity IN (".getEntity('stock').")";
$sql_wh .= " AND statut = 1";
$sql_wh .= " ORDER BY ref ASC";
$resql_wh = $db->query($sql_wh);
if ($resql_wh) {
	while ($obj_wh = $db->fetch_object($resql_wh)) {
		$warehouses[$obj_wh->rowid] = $obj_wh->ref.($obj_wh->lieu ? ' - '.$obj_wh->lieu : '');
	}
	$db->free($resql_wh);
}

print '<div class="marginbottomonly">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
print $form->textwithpicto('<strong>'.$langs->trans('Warehouse').'</strong>', $langs->trans('WarehouseSelectorDesc')).' ';
print '<select name="wh" class="flat minwidth200" onchange="this.form.submit()">';
print '<option value="0">'.$langs->trans('GlobalDefault').'</option>';
foreach ($warehouses as $wh_id => $wh_label) {
	$has_override = wareloc_warehouse_has_overrides($wh_id);
	$sel = ($selected_wh == $wh_id) ? ' selected' : '';
	print '<option value="'.$wh_id.'"'.$sel.'>'.dol_escape_htmltag($wh_label).($has_override ? ' *' : '').'</option>';
}
print '</select>';
print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Refresh').'">';
print '</form>';
print ' <span class="opacitymedium small">'.$langs->trans('WarehouseOverrideHint').'</span>';
print '</div>';

// Fetch current active levels for selected warehouse context
$levels = array();
$sql = "SELECT rowid, position, code, label, datatype, list_values, required";
$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_level";
$sql .= " WHERE active = 1 AND entity = ".((int) $conf->entity);
$sql .= $wh_sql;
$sql .= " ORDER BY position ASC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$levels[] = $obj;
	}
	$db->free($resql);
}

// If warehouse selected with no overrides, show init option
if ($selected_wh > 0 && empty($levels)) {
	print '<div class="info marginbottomonly">';
	print $langs->trans('WarehouseUsesGlobal');
	print ' <a class="butAction button smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?action=initfromglobal&wh='.$selected_wh.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('ConfirmInitFromGlobal').'\')">';
	print $langs->trans('InitFromGlobal');
	print '</a>';
	print '</div>';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$form->textwithpicto($langs->trans('LevelPosition'), $langs->trans('LevelPositionDesc')).'</td>';
print '<td>'.$form->textwithpicto($langs->trans('LevelCode'), $langs->trans('LevelCodeDesc')).'</td>';
print '<td>'.$form->textwithpicto($langs->trans('LevelLabel'), $langs->trans('LevelLabelDesc')).'</td>';
print '<td>'.$form->textwithpicto($langs->trans('LevelDatatype'), $langs->trans('LevelDatatypeDesc')).'</td>';
print '<td>'.$form->textwithpicto($langs->trans('LevelListValues'), $langs->trans('LevelListValuesDesc')).'</td>';
print '<td class="center">'.$form->textwithpicto($langs->trans('LevelRequired'), $langs->trans('LevelRequiredDesc')).'</td>';
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
		print $wh_hidden;

		print '<td>'.$lev->position.'</td>';
		print '<td>'.dol_escape_htmltag($lev->code).'</td>';
		print '<td><input type="text" name="level_label" class="flat minwidth150" value="'.dol_escape_htmltag($lev->label).'" title="'.dol_escape_htmltag($langs->trans('LevelLabelDesc')).'"></td>';
		print '<td><select name="level_datatype" class="flat" onchange="warelocDatatypeChange(\'edit\', this.value)">';
		foreach ($datatype_labels as $dt => $dtlabel) {
			$sel = ($lev->datatype === $dt) ? ' selected' : '';
			print '<option value="'.$dt.'"'.$sel.'>'.$dtlabel.'</option>';
		}
		print '</select></td>';

		// List values builder (edit)
		$edit_list_preview = '';
		if ($lev->list_values) {
			$edit_vals_arr = array_filter(array_map('trim', explode(',', $lev->list_values)));
			$edit_list_preview = dol_escape_htmltag(implode(', ', $edit_vals_arr));
		} else {
			$edit_list_preview = $langs->trans('NoListValues');
		}
		$builder_display = ($lev->datatype === 'list') ? '' : ' style="display:none"';
		print '<td>';
		print '<div id="listbuilder_edit_wrap"'.$builder_display.'>';
		print '<span class="wareloc-listbuilder-preview" id="listbuilder_edit_preview">'.dol_escape_htmltag($edit_list_preview).'</span> ';
		print '<button type="button" class="button smallpaddingimp" onclick="warelocOpenListBuilder(\'edit\')">'.$langs->trans('EditListValues').'</button>';
		print '<input type="hidden" id="listvals_edit" name="level_list_values" value="'.dol_escape_htmltag($lev->list_values).'">';
		print '<div id="listbuilder_edit_panel" class="wareloc-listbuilder-panel" style="display:none">';
		print '<div id="listbuilder_edit_rows"></div>';
		print '<div class="margintoponly">';
		print '<button type="button" class="button smallpaddingimp" onclick="warelocAddListRow(\'edit\', \'\')">'.$langs->trans('AddListValue').'</button> ';
		print '<button type="button" class="button smallpaddingimp" onclick="warelocApplyList(\'edit\')">'.$langs->trans('ApplyListValues').'</button> ';
		print '<button type="button" class="smallpaddingimp" onclick="warelocCloseListBuilder(\'edit\')">'.$langs->trans('Cancel').'</button>';
		print '</div>';
		print '</div>';
		print '</div>';
		print '</td>';
		print '<td class="center"><input type="checkbox" name="level_required" value="1"'.($lev->required ? ' checked' : '').'></td>';
		print '<td class="right">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?'.$wh_param.'">'.$langs->trans('Cancel').'</a>';
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
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=movelevelup&levelid='.$lev->rowid.'&token='.newToken().$wh_param.'">'.img_picto($langs->trans('MoveLevelUp'), 'up', 'class="pictofixedwidth"').'</a>';
		}
		// Move down
		if ($idx < count($levels) - 1) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=moveleveldown&levelid='.$lev->rowid.'&token='.newToken().$wh_param.'">'.img_picto($langs->trans('MoveLevelDown'), 'down', 'class="pictofixedwidth"').'</a>';
		}
		// Edit
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=editlevel&levelid='.$lev->rowid.'&token='.newToken().$wh_param.'">'.img_picto($langs->trans('EditLevel'), 'edit', 'class="pictofixedwidth"').'</a>';
		// Delete
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=deletelevel&levelid='.$lev->rowid.'&token='.newToken().$wh_param.'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteLevel').'\')">'.img_picto($langs->trans('DeleteLevel'), 'delete', 'class="pictofixedwidth"').'</a>';

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
	print $wh_hidden;

	print '<td><em>'.(count($levels) + 1).'</em></td>';
	print '<td><input type="text" name="level_code" class="flat maxwidth100" placeholder="e.g., row" title="'.dol_escape_htmltag($langs->trans('LevelCodeDesc')).'"></td>';
	print '<td><input type="text" name="level_label" class="flat minwidth150" placeholder="e.g., Row" title="'.dol_escape_htmltag($langs->trans('LevelLabelDesc')).'"></td>';
	print '<td><select name="level_datatype" class="flat" onchange="warelocDatatypeChange(\'new\', this.value)">';
	foreach ($datatype_labels as $dt => $dtlabel) {
		print '<option value="'.$dt.'">'.$dtlabel.'</option>';
	}
	print '</select></td>';

	// List values builder (new)
	print '<td>';
	print '<div id="listbuilder_new_wrap" style="display:none">';
	print '<span class="wareloc-listbuilder-preview" id="listbuilder_new_preview">'.$langs->trans('NoListValues').'</span> ';
	print '<button type="button" class="button smallpaddingimp" onclick="warelocOpenListBuilder(\'new\')">'.$langs->trans('EditListValues').'</button>';
	print '<input type="hidden" id="listvals_new" name="level_list_values" value="">';
	print '<div id="listbuilder_new_panel" class="wareloc-listbuilder-panel" style="display:none">';
	print '<div id="listbuilder_new_rows"></div>';
	print '<div class="margintoponly">';
	print '<button type="button" class="button smallpaddingimp" onclick="warelocAddListRow(\'new\', \'\')">'.$langs->trans('AddListValue').'</button> ';
	print '<button type="button" class="button smallpaddingimp" onclick="warelocApplyList(\'new\')">'.$langs->trans('ApplyListValues').'</button> ';
	print '<button type="button" class="smallpaddingimp" onclick="warelocCloseListBuilder(\'new\')">'.$langs->trans('Cancel').'</button>';
	print '</div>';
	print '</div>';
	print '</div>';
	print '</td>';

	print '<td class="center"><input type="checkbox" name="level_required" value="1" title="'.dol_escape_htmltag($langs->trans('LevelRequiredDesc')).'"></td>';
	print '<td class="right"><input type="submit" class="button" value="'.$langs->trans('AddLevel').'"></td>';

	print '</form>';
	print '</tr>';
}

// Show "no levels" row if warehouse has overrides but all were deleted, or global has none
if (empty($levels) && ($selected_wh == 0 || wareloc_warehouse_has_overrides($selected_wh))) {
	// Already covered by the add form above — no extra message needed
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
print '<td class="opacitymedium">'.$langs->trans('DefaultStatusOnReceptionDesc').'</td>';
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

// ---- List builder JS + CSS ----
print '<style>
.wareloc-listbuilder-panel {
	background: var(--colorbacktitle1, #f5f5f5);
	border: 1px solid var(--colortextlink, #aaa);
	border-radius: 4px;
	padding: 10px;
	margin-top: 6px;
	display: inline-block;
	min-width: 240px;
}
.wareloc-listrow {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 4px;
}
.wareloc-listrow-input {
	flex: 1;
	min-width: 0;
}
.wareloc-listbuilder-preview {
	color: #888;
	font-style: italic;
	margin-right: 4px;
}
</style>';

$no_list_values_js = dol_escape_js($langs->trans('NoListValues'));
print '<script>
function warelocDatatypeChange(id, val) {
	var wrap = document.getElementById("listbuilder_" + id + "_wrap");
	if (wrap) wrap.style.display = (val === "list") ? "block" : "none";
	if (val !== "list") warelocCloseListBuilder(id);
}
function warelocOpenListBuilder(id) {
	var hidden = document.getElementById("listvals_" + id);
	var rowsDiv = document.getElementById("listbuilder_" + id + "_rows");
	var current = hidden ? hidden.value : "";
	var vals = current ? current.split(",").map(function(v) { return v.trim(); }).filter(Boolean) : [];
	rowsDiv.innerHTML = "";
	if (vals.length === 0) {
		warelocAddListRow(id, "");
	} else {
		vals.forEach(function(v) { warelocAddListRow(id, v); });
	}
	document.getElementById("listbuilder_" + id + "_panel").style.display = "block";
}
function warelocAddListRow(id, value) {
	var rowsDiv = document.getElementById("listbuilder_" + id + "_rows");
	var row = document.createElement("div");
	row.className = "wareloc-listrow";
	var inp = document.createElement("input");
	inp.type = "text";
	inp.className = "flat wareloc-listrow-input";
	inp.value = value || "";
	inp.placeholder = "Enter a value";
	var btn = document.createElement("button");
	btn.type = "button";
	btn.className = "smallpaddingimp";
	btn.title = "Remove";
	btn.textContent = "\u00d7";
	btn.onclick = function() { row.remove(); };
	row.appendChild(inp);
	row.appendChild(btn);
	rowsDiv.appendChild(row);
	inp.focus();
}
function warelocApplyList(id) {
	var rowsDiv = document.getElementById("listbuilder_" + id + "_rows");
	var inputs = rowsDiv.querySelectorAll(".wareloc-listrow-input");
	var vals = [];
	inputs.forEach(function(inp) { var v = inp.value.trim(); if (v) vals.push(v); });
	document.getElementById("listvals_" + id).value = vals.join(",");
	var preview = document.getElementById("listbuilder_" + id + "_preview");
	if (preview) preview.textContent = vals.length ? vals.join(", ") : "'.$no_list_values_js.'";
	document.getElementById("listbuilder_" + id + "_panel").style.display = "none";
}
function warelocCloseListBuilder(id) {
	var panel = document.getElementById("listbuilder_" + id + "_panel");
	if (panel) panel.style.display = "none";
}
</script>';

llxFooter();
$db->close();


// ---- HELPER FUNCTIONS ----

/**
 * Swap position of a level with its neighbor (scoped to warehouse context)
 *
 * @param  DoliDB $db            Database
 * @param  Conf   $conf          Configuration
 * @param  int    $id            Level rowid
 * @param  string $dir           'up' or 'down'
 * @param  int    $fk_entrepot   Warehouse ID (0 for global)
 * @return void
 */
function _wareloc_swap_level_position($db, $conf, $id, $dir, $fk_entrepot = 0)
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

	// Find neighbor in the same warehouse context
	$neighbor_pos = ($dir === 'up') ? $current->position - 1 : $current->position + 1;
	$sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX."wareloc_level";
	$sql .= " WHERE position = ".((int) $neighbor_pos);
	$sql .= " AND active = 1 AND entity = ".((int) $conf->entity);
	if ($fk_entrepot > 0) {
		$sql .= " AND fk_entrepot = ".((int) $fk_entrepot);
	} else {
		$sql .= " AND fk_entrepot IS NULL";
	}
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
