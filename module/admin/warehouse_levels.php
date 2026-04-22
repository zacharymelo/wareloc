<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    admin/warehouse_levels.php
 * \ingroup binloc
 * \brief   Per-warehouse level configuration — define location hierarchy labels and types
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');

$langs->loadLangs(array('admin', 'stocks', 'binloc@binloc'));

if (!$user->admin && !$user->hasRight('binloc', 'admin')) {
	accessforbidden();
}

$action      = GETPOST('action', 'aZ09');
$fk_entrepot = GETPOSTINT('fk_entrepot');

$levelObj = new BinlocWarehouseLevel($db);

// Placeholder hints per depth
$level_hints = array(
	1 => 'LevelHint1',
	2 => 'LevelHint2',
	3 => 'LevelHint3',
	4 => 'LevelHint4',
	5 => 'LevelHint5',
	6 => 'LevelHint6',
);

// ---- ACTIONS ----

if ($action === 'savelevels' && $fk_entrepot > 0) {
	$labels      = GETPOST('labels', 'array');
	$datatypes   = GETPOST('datatypes', 'array');
	$list_values = GETPOST('list_values', 'array');

	$clean = array();
	$num = 1;
	if (is_array($labels)) {
		foreach ($labels as $idx => $label) {
			$label = trim($label);
			if (!empty($label) && $num <= 6) {
				$dt = isset($datatypes[$idx]) ? $datatypes[$idx] : 'text';
				$lv = isset($list_values[$idx]) ? trim($list_values[$idx]) : '';
				$clean[$num] = array(
					'label'       => $label,
					'datatype'    => in_array($dt, array('text', 'number', 'list'), true) ? $dt : 'text',
					'list_values' => $lv,
				);
				$num++;
			}
		}
	}

	if (empty($clean)) {
		$levelObj->deleteByWarehouse($fk_entrepot);
		setEventMessages($langs->trans('LevelsSaved'), null, 'mesgs');
	} else {
		$result = $levelObj->saveWarehouseLevels($fk_entrepot, $clean, $user);
		if ($result > 0) {
			setEventMessages($langs->trans('LevelsSaved'), null, 'mesgs');
		} else {
			setEventMessages($levelObj->error, null, 'errors');
		}
	}
	$action = '';
}

if ($action === 'copylevels' && $fk_entrepot > 0) {
	$source_wh = GETPOSTINT('source_wh');
	if ($source_wh > 0 && $source_wh != $fk_entrepot) {
		$result = $levelObj->copyFromWarehouse($source_wh, $fk_entrepot, $user);
		if ($result > 0) {
			setEventMessages($langs->trans('CopyLevelsDone'), null, 'mesgs');
		} else {
			setEventMessages($levelObj->error, null, 'errors');
		}
	}
	$action = '';
}

// ---- VIEW ----

$page_name = 'BinlocSetup';
llxHeader('', $langs->trans($page_name), '');

$head = binloc_admin_prepare_head();
print dol_get_fiche_head($head, 'warehouselevels', $langs->trans($page_name), -1, 'stock');

// Warehouse selector
$warehouses = binloc_get_warehouses($db);

print '<div class="marginbottomonly">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
print '<strong>'.$langs->trans('Warehouse').'</strong>: ';
print '<select name="fk_entrepot" class="flat minwidth250" onchange="this.form.submit()">';
print '<option value="0">'.$langs->trans('SelectWarehouse').'</option>';
foreach ($warehouses as $wh) {
	$sel = ($fk_entrepot == $wh->rowid) ? ' selected' : '';
	print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref);
	if ($wh->lieu) {
		print ' - '.dol_escape_htmltag($wh->lieu);
	}
	print '</option>';
}
print '</select>';
print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Select').'">';
print '</form>';
print '</div>';

if ($fk_entrepot > 0) {
	$current_levels = $levelObj->fetchByWarehouse($fk_entrepot);

	// ---- Copy from another warehouse ----
	$other_wh_with_levels = array();
	foreach ($warehouses as $wh) {
		if ($wh->rowid == $fk_entrepot) {
			continue;
		}
		$wh_levels = $levelObj->fetchByWarehouse($wh->rowid);
		if (!empty($wh_levels)) {
			$wh->levels = $wh_levels;
			$other_wh_with_levels[] = $wh;
		}
	}

	if (!empty($other_wh_with_levels)) {
		print '<div class="marginbottomonly">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="copylevels">';
		print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
		print $langs->trans('CopyFromWarehouse').': ';
		print '<select name="source_wh" class="flat minwidth200">';
		print '<option value="0">---</option>';
		foreach ($other_wh_with_levels as $wh) {
			$label_parts = array();
			foreach ($wh->levels as $lcfg) {
				$label_parts[] = $lcfg->label;
			}
			print '<option value="'.$wh->rowid.'">'.dol_escape_htmltag($wh->ref);
			print ' ('.implode(' &rarr; ', array_map('dol_escape_htmltag', $label_parts)).')';
			print '</option>';
		}
		print '</select>';
		print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('CopyLevels')).'">';
		print '</form>';
		print '</div>';
	}

	// ---- Level editor ----
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="binloc-level-form">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savelevels">';
	print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';

	print '<table class="noborder centpercent" id="binloc-level-table">';
	print '<tr class="liste_titre">';
	print '<td class="center" width="60">'.$langs->trans('Level').'</td>';
	print '<td width="200">'.$langs->trans('LevelLabel').'</td>';
	print '<td width="120">'.$langs->trans('Type').'</td>';
	print '<td>'.$langs->trans('ListValues').' <span class="opacitymedium small">('.$langs->trans('ListValuesHint').')</span></td>';
	print '<td class="center" width="40"></td>';
	print '</tr>';

	// Render existing levels (or one empty row if none)
	if (!empty($current_levels)) {
		$display_levels = $current_levels;
	} else {
		$empty = new stdClass();
		$empty->label = '';
		$empty->datatype = 'text';
		$empty->list_values = '';
		$display_levels = array(1 => $empty);
	}

	$row_num = 0;
	foreach ($display_levels as $num => $cfg) {
		$row_num++;
		$hint = isset($level_hints[$row_num]) ? $langs->trans($level_hints[$row_num]) : '';
		$label_val = is_object($cfg) ? $cfg->label : (string) $cfg;
		$dt_val    = is_object($cfg) ? $cfg->datatype : 'text';
		$lv_val    = (is_object($cfg) && !empty($cfg->list_values)) ? $cfg->list_values : '';
		$lv_visible = ($dt_val === 'list') ? '' : ' style="display:none"';

		print '<tr class="oddeven binloc-level-row">';
		print '<td class="center opacitymedium"><span class="binloc-level-num">'.$row_num.'</span></td>';
		print '<td><input type="text" name="labels[]" class="flat minwidth150" value="'.dol_escape_htmltag($label_val).'" placeholder="'.dol_escape_htmltag($hint).'"></td>';
		print '<td>';
		print '<select name="datatypes[]" class="flat binloc-type-select" onchange="binlocOnTypeChange(this)">';
		print '<option value="text"'.($dt_val === 'text' ? ' selected' : '').'>'.$langs->trans('TypeText').'</option>';
		print '<option value="number"'.($dt_val === 'number' ? ' selected' : '').'>'.$langs->trans('TypeNumber').'</option>';
		print '<option value="list"'.($dt_val === 'list' ? ' selected' : '').'>'.$langs->trans('TypeList').'</option>';
		print '</select>';
		print '</td>';
		print '<td><input type="text" name="list_values[]" class="flat centpercent binloc-listvalues-input" value="'.dol_escape_htmltag($lv_val).'" placeholder="'.dol_escape_htmltag($langs->trans('ListValuesPlaceholder')).'"'.$lv_visible.'></td>';
		print '<td class="center">';
		if ($row_num > 1 || count($display_levels) > 1) {
			print '<a href="#" class="binloc-remove-level" onclick="binlocRemoveLevel(this); return false;" title="'.$langs->trans('RemoveLevel').'">';
			print img_picto($langs->trans('RemoveLevel'), 'delete');
			print '</a>';
		}
		print '</td>';
		print '</tr>';
	}

	print '</table>';

	print '<div class="margintoponly">';
	print '<a href="#" id="binloc-add-level" class="button smallpaddingimp" onclick="binlocAddLevel(); return false;">';
	print img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('AddLevel');
	print '</a>';
	print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	print '</div>';

	print '</form>';

	// ---- JS for dynamic rows ----
	$level_hints_json = json_encode(array(
		1 => $langs->trans('LevelHint1'),
		2 => $langs->trans('LevelHint2'),
		3 => $langs->trans('LevelHint3'),
		4 => $langs->trans('LevelHint4'),
		5 => $langs->trans('LevelHint5'),
		6 => $langs->trans('LevelHint6'),
	));
	$js_max_msg     = dol_escape_js($langs->trans('MaxLevelsReached'));
	$js_remove      = dol_escape_js($langs->trans('RemoveLevel'));
	$js_delete_icon = dol_escape_js(img_picto($langs->trans('RemoveLevel'), 'delete'));
	$js_type_text   = dol_escape_js($langs->trans('TypeText'));
	$js_type_number = dol_escape_js($langs->trans('TypeNumber'));
	$js_type_list   = dol_escape_js($langs->trans('TypeList'));
	$js_lv_placeholder = dol_escape_js($langs->trans('ListValuesPlaceholder'));

	print '<script>
var binlocLevelHints = '.$level_hints_json.';
var binlocDeleteIcon = "'.$js_delete_icon.'";
var binlocRemoveTitle = "'.$js_remove.'";

function binlocRenumberLevels() {
	var rows = document.querySelectorAll(".binloc-level-row");
	rows.forEach(function(row, idx) {
		var num = idx + 1;
		row.querySelector(".binloc-level-num").textContent = num;
		var input = row.querySelector("input[name=\'labels[]\']");
		if (input && binlocLevelHints[num]) {
			input.placeholder = binlocLevelHints[num];
		}
	});
	var addBtn = document.getElementById("binloc-add-level");
	if (addBtn) {
		addBtn.style.display = (rows.length >= 6) ? "none" : "";
	}
}

function binlocOnTypeChange(selectEl) {
	var row = selectEl.closest("tr");
	if (!row) return;
	var lvInput = row.querySelector(".binloc-listvalues-input");
	if (!lvInput) return;
	lvInput.style.display = (selectEl.value === "list") ? "" : "none";
}

function binlocAddLevel() {
	var rows = document.querySelectorAll(".binloc-level-row");
	if (rows.length >= 6) {
		alert("'.$js_max_msg.'");
		return;
	}
	var num = rows.length + 1;
	var hint = binlocLevelHints[num] || "";
	var tr = document.createElement("tr");
	tr.className = "oddeven binloc-level-row";
	tr.innerHTML = "<td class=\"center opacitymedium\"><span class=\"binloc-level-num\">" + num + "</span></td>"
		+ "<td><input type=\"text\" name=\"labels[]\" class=\"flat minwidth150\" value=\"\" placeholder=\"" + hint + "\"></td>"
		+ "<td><select name=\"datatypes[]\" class=\"flat binloc-type-select\" onchange=\"binlocOnTypeChange(this)\">"
		+ "<option value=\"text\">'.$js_type_text.'</option>"
		+ "<option value=\"number\">'.$js_type_number.'</option>"
		+ "<option value=\"list\">'.$js_type_list.'</option>"
		+ "</select></td>"
		+ "<td><input type=\"text\" name=\"list_values[]\" class=\"flat centpercent binloc-listvalues-input\" value=\"\" placeholder=\"'.$js_lv_placeholder.'\" style=\"display:none\"></td>"
		+ "<td class=\"center\"><a href=\"#\" class=\"binloc-remove-level\" onclick=\"binlocRemoveLevel(this); return false;\" title=\"" + binlocRemoveTitle + "\">" + binlocDeleteIcon + "</a></td>";
	document.getElementById("binloc-level-table").querySelector("tbody, table").appendChild(tr);
	binlocRenumberLevels();
	tr.querySelector("input").focus();
}

function binlocRemoveLevel(el) {
	var row = el.closest("tr");
	if (row) {
		row.remove();
		binlocRenumberLevels();
	}
}

binlocRenumberLevels();
</script>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
