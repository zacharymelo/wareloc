<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    tab_reception_locations.php
 * \ingroup binloc
 * \brief   Reception card tab — bulk-assign bin locations for the lines of this reception
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/reception.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
dol_include_once('/binloc/class/binlocproductlocation.class.php');

$langs->loadLangs(array('receptions', 'products', 'stocks', 'productbatch', 'binloc@binloc'));

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new Reception($db);
if ($id > 0) {
	$object->fetch($id);
	$object->fetch_lines();
}

if (empty($object->id) || $object->id <= 0) {
	accessforbidden('Reception not found');
}

$levelObj = new BinlocWarehouseLevel($db);

// ---- ACTIONS ----

if ($action === 'bulksavelocations' && $user->hasRight('binloc', 'write')) {
	$row_keys = GETPOST('row_keys', 'array');
	$saved = 0;
	$errors = 0;

	if (is_array($row_keys) && !empty($row_keys)) {
		$db->begin();

		foreach ($row_keys as $rk) {
			$rk = preg_replace('/[^a-zA-Z0-9_]/', '', $rk);
			if (empty($rk)) {
				continue;
			}

			$fk_product     = GETPOSTINT('prod_'.$rk);
			$fk_entrepot    = GETPOSTINT('wh_'.$rk);
			$fk_product_lot = GETPOSTINT('lot_'.$rk);

			if ($fk_product <= 0 || $fk_entrepot <= 0) {
				continue;
			}

			$has_value = false;
			$loc = new BinlocProductLocation($db);
			$loc->fk_product     = $fk_product;
			$loc->fk_entrepot    = $fk_entrepot;
			$loc->fk_product_lot = $fk_product_lot > 0 ? $fk_product_lot : null;

			for ($i = 1; $i <= 6; $i++) {
				$val = GETPOST('level'.$i.'_'.$rk, 'alphanohtml');
				$loc->{'level'.$i.'_value'} = $val;
				if ($val !== null && $val !== '') {
					$has_value = true;
				}
			}
			$loc->note = GETPOST('note_'.$rk, 'alphanohtml');
			if ($loc->note) {
				$has_value = true;
			}

			if (!$has_value) {
				continue;
			}

			$result = $loc->createOrUpdate($user);
			if ($result < 0) {
				$errors++;
				setEventMessages($loc->error, null, 'errors');
				break;
			}
			$saved++;
		}

		if ($errors > 0) {
			$db->rollback();
		} else {
			$db->commit();
			if ($saved > 0) {
				setEventMessages($langs->trans('BulkSaved', $saved), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('BulkNothingChanged'), null, 'warnings');
			}
		}
	}
	$action = '';
	// Reload lines after save to reflect new locations
	$object->fetch_lines();
}

// ---- VIEW ----

llxHeader('', $langs->trans('BinPlacement').' - '.$object->ref, '');

$head = reception_prepare_head($object);
print dol_get_fiche_head($head, 'binloc', $langs->trans('Reception'), -1, $object->picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/reception/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if (empty($object->lines)) {
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('NoReceptionLines').'</div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// Preload level configs for every active warehouse so JS can swap inputs on warehouse change
$all_warehouses = binloc_get_warehouses($db);
$wh_level_cache = array();
foreach ($all_warehouses as $wh) {
	$cfg = $levelObj->fetchByWarehouse($wh->rowid);
	if (!empty($cfg)) {
		$wh_level_cache[(int) $wh->rowid] = $cfg;
	}
}

// Find max level count across all warehouses so table columns stay uniform
$max_levels = 0;
foreach ($wh_level_cache as $levels) {
	if (count($levels) > $max_levels) {
		$max_levels = count($levels);
	}
}
if ($max_levels == 0) {
	$max_levels = 4;
}

// Build display rows from reception lines, looking up existing locations
$display_rows = array();
foreach ($object->lines as $idx => $line) {
	$fk_product = isset($line->fk_product) ? (int) $line->fk_product : 0;
	$fk_entrepot = isset($line->fk_entrepot) ? (int) $line->fk_entrepot : 0;
	$batch = isset($line->batch) ? trim((string) $line->batch) : '';

	if ($fk_product <= 0 || $fk_entrepot <= 0) {
		continue;
	}

	// If serialized, look up the lot ID
	$fk_product_lot = 0;
	if (!empty($batch)) {
		$sql_lot = "SELECT rowid FROM ".MAIN_DB_PREFIX."product_lot";
		$sql_lot .= " WHERE fk_product = ".$fk_product;
		$sql_lot .= " AND batch = '".$db->escape($batch)."'";
		$sql_lot .= " AND entity IN (".getEntity('stock').")";
		$resql_lot = $db->query($sql_lot);
		if ($resql_lot) {
			$obj_lot = $db->fetch_object($resql_lot);
			if ($obj_lot) {
				$fk_product_lot = (int) $obj_lot->rowid;
			}
			$db->free($resql_lot);
		}
	}

	// Fetch any existing location for this product + warehouse + lot
	$loc = new BinlocProductLocation($db);
	if ($fk_product_lot > 0) {
		$loc->fetchAnyByLot($fk_product_lot);
	} else {
		$loc->fetchByProductWarehouse($fk_product, $fk_entrepot);
	}
	$existing = ($loc->id > 0) ? $loc : null;

	$row_key = 'r'.$idx;

	$display_rows[] = array(
		'row_key'        => $row_key,
		'line'           => $line,
		'fk_product'     => $fk_product,
		'fk_entrepot'    => $fk_entrepot,
		'fk_product_lot' => $fk_product_lot,
		'batch'          => $batch,
		'product_ref'    => isset($line->ref) ? $line->ref : '',
		'product_label'  => isset($line->label) ? $line->label : (isset($line->product) && isset($line->product->label) ? $line->product->label : ''),
		'qty'            => isset($line->qty) ? (float) $line->qty : 0,
		'existing'       => $existing,
	);
}

if (empty($display_rows)) {
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('NoReceptionLines').'</div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

print '<div class="opacitymedium marginbottomonly">'.$langs->trans('ReceptionBinPlacementDesc').'</div>';

// ---- Bulk save form ----
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="binloc-reception-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id" value="'.$object->id.'">';
print '<input type="hidden" name="action" value="bulksavelocations">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td>'.$langs->trans('Batch').'/'.$langs->trans('Serial').'</td>';
print '<td class="right">'.$langs->trans('Qty').'</td>';
print '<td>'.$langs->trans('Warehouse').'</td>';
for ($i = 1; $i <= $max_levels; $i++) {
	print '<td>'.$langs->trans('Level').' '.$i;
	// Fill-down button
	print ' <a href="#" onclick="binlocFillDown('.$i.'); return false;" title="'.dol_escape_htmltag($langs->trans('FillDownHint')).'" class="opacitymedium" style="font-size:0.85em;">';
	print '&darr;</a>';
	print '</td>';
}
print '<td>'.$langs->trans('LocationNote').'</td>';
print '</tr>';

$wh_levels_json = json_encode($wh_level_cache, JSON_HEX_TAG | JSON_HEX_QUOT);

foreach ($display_rows as $row) {
	$rk = $row['row_key'];
	$target_wh = $row['fk_entrepot'];
	$existing  = $row['existing'];

	$wh_levels = isset($wh_level_cache[$target_wh]) ? $wh_level_cache[$target_wh] : array();

	$product_url = dol_buildpath('/product/card.php?id='.$row['fk_product'], 1);

	print '<tr class="oddeven">';
	print '<input type="hidden" name="row_keys[]" value="'.$rk.'">';
	print '<input type="hidden" name="prod_'.$rk.'" value="'.$row['fk_product'].'">';
	print '<input type="hidden" name="lot_'.$rk.'" value="'.$row['fk_product_lot'].'">';

	print '<td><a href="'.$product_url.'">'.dol_escape_htmltag($row['product_ref']).'</a>';
	if ($row['product_label']) {
		print '<br><span class="opacitymedium small">'.dol_escape_htmltag($row['product_label']).'</span>';
	}
	print '</td>';

	print '<td>';
	if (!empty($row['batch'])) {
		if ($row['fk_product_lot'] > 0) {
			$lot_url = dol_buildpath('/product/stock/productlot_card.php?id='.$row['fk_product_lot'], 1);
			print '<a href="'.$lot_url.'"><span class="badge badge-info">'.dol_escape_htmltag($row['batch']).'</span></a>';
		} else {
			print '<span class="badge badge-info">'.dol_escape_htmltag($row['batch']).'</span>';
		}
	} else {
		print '<span class="opacitymedium">—</span>';
	}
	print '</td>';

	print '<td class="right">'.price2num($row['qty'], 0).'</td>';

	// Warehouse selector — defaults to the line's destination warehouse
	print '<td>';
	print '<select name="wh_'.$rk.'" class="flat minwidth150 binloc-wh-select" data-row-key="'.$rk.'" onchange="binlocOnWhChange(this)">';
	foreach ($all_warehouses as $wh) {
		$sel = ((int) $wh->rowid == $target_wh) ? ' selected' : '';
		print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref).'</option>';
	}
	print '</select>';
	print '</td>';

	// Level inputs
	for ($i = 1; $i <= $max_levels; $i++) {
		$val = $existing ? $existing->{'level'.$i.'_value'} : '';
		print '<td class="binloc-level-cell" data-row-key="'.$rk.'" data-level-num="'.$i.'">';
		if (isset($wh_levels[$i])) {
			print binloc_render_level_input($wh_levels[$i], 'level'.$i.'_'.$rk, $val, 'flat width75 binloc-level-cell-input', 'data-level="'.$i.'"');
		} else {
			print '<input type="text" name="level'.$i.'_'.$rk.'" class="flat width75 binloc-level-cell-input" data-level="'.$i.'" value="'.dol_escape_htmltag($val).'">';
		}
		print '</td>';
	}

	$note_val = $existing ? $existing->note : '';
	print '<td><input type="text" name="note_'.$rk.'" class="flat width100" value="'.dol_escape_htmltag($note_val).'"></td>';

	print '</tr>';
}

print '</table>';

print '<div class="margintoponly">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('BulkSaveAll')).'">';
print '</div>';

print '</form>';

// JS: fill-down + warehouse-change input rebuilding
print '<script>
var binlocWhLevels = '.$wh_levels_json.';

function binlocEsc(s) {
	return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function binlocBuildLevelInput(levelCfg, inputName, currentVal, levelNum) {
	var dataAttr = " data-level=\"" + levelNum + "\"";
	if (levelCfg && levelCfg.datatype === "list" && levelCfg.options && levelCfg.options.length) {
		var html = "<select name=\"" + inputName + "\" class=\"flat width75 binloc-level-cell-input\"" + dataAttr + ">";
		html += "<option value=\"\"></option>";
		levelCfg.options.forEach(function(opt) {
			var sel = (opt === currentVal) ? " selected" : "";
			html += "<option value=\"" + binlocEsc(opt) + "\"" + sel + ">" + binlocEsc(opt) + "</option>";
		});
		html += "</select>";
		return html;
	}
	var type = (levelCfg && levelCfg.datatype === "number") ? "number" : "text";
	var label = (levelCfg && levelCfg.label) ? levelCfg.label : "";
	return "<input type=\"" + type + "\" name=\"" + inputName + "\" class=\"flat width75 binloc-level-cell-input\"" + dataAttr + " value=\"" + binlocEsc(currentVal || "") + "\" placeholder=\"" + binlocEsc(label) + "\">";
}

function binlocOnWhChange(selectEl) {
	var rk = selectEl.dataset.rowKey;
	var whId = parseInt(selectEl.value);
	var levels = binlocWhLevels[whId] || {};
	document.querySelectorAll(".binloc-level-cell[data-row-key=\"" + rk + "\"]").forEach(function(cell) {
		var num = cell.dataset.levelNum;
		var cfg = levels[num] || null;
		var inputName = "level" + num + "_" + rk;
		var existing = cell.querySelector("input, select");
		var currentVal = existing ? existing.value : "";
		cell.innerHTML = binlocBuildLevelInput(cfg, inputName, currentVal, num);
	});
}

function binlocFillDown(levelNum) {
	var inputs = document.querySelectorAll(".binloc-level-cell-input[data-level=\"" + levelNum + "\"]");
	if (inputs.length === 0) return;
	var sourceVal = "";
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].value !== "") {
			sourceVal = inputs[i].value;
			break;
		}
	}
	if (sourceVal === "") {
		sourceVal = window.prompt("Enter value to fill down:");
		if (sourceVal === null || sourceVal === "") return;
	}
	inputs.forEach(function(inp) {
		if (inp.value === "") {
			inp.value = sourceVal;
		}
	});
}
</script>';

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
