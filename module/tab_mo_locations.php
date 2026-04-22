<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    tab_mo_locations.php
 * \ingroup binloc
 * \brief   Manufacturing Order tab — bulk-assign bin locations for serials produced by this MO
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
require_once DOL_DOCUMENT_ROOT.'/mrp/lib/mrp_mo.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
dol_include_once('/binloc/class/binlocproductlocation.class.php');

$langs->loadLangs(array('mrp', 'products', 'stocks', 'productbatch', 'binloc@binloc'));

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new Mo($db);
if ($id > 0) {
	$object->fetch($id);
}

if (empty($object->id) || $object->id <= 0) {
	accessforbidden('MO not found');
}

$levelObj = new BinlocWarehouseLevel($db);

// ---- ACTIONS ----

if ($action === 'bulksavelocations' && $user->hasRight('binloc', 'write')) {
	$lot_ids = GETPOST('lot_ids', 'array');
	$saved = 0;
	$errors = 0;

	if (is_array($lot_ids) && !empty($lot_ids)) {
		$db->begin();

		foreach ($lot_ids as $lot_id) {
			$lot_id = (int) $lot_id;
			if ($lot_id <= 0) {
				continue;
			}

			$wh_id = GETPOSTINT('wh_'.$lot_id);
			if ($wh_id <= 0) {
				continue;
			}

			// Collect level values for this lot
			$has_value = false;
			$loc = new BinlocProductLocation($db);
			$loc->fk_product_lot = $lot_id;
			$loc->fk_entrepot    = $wh_id;
			$loc->fk_product     = GETPOSTINT('prod_'.$lot_id);

			for ($i = 1; $i <= 6; $i++) {
				$val = GETPOST('level'.$i.'_'.$lot_id, 'alphanohtml');
				$loc->{'level'.$i.'_value'} = $val;
				if ($val !== null && $val !== '') {
					$has_value = true;
				}
			}
			$loc->note = GETPOST('note_'.$lot_id, 'alphanohtml');
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
}

// ---- VIEW ----

llxHeader('', $langs->trans('BinLocations').' - '.$object->ref, '');

$head = moPrepareHead($object);
print dol_get_fiche_head($head, 'binloc', $langs->trans('ManufacturingOrder'), -1, $object->picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/mrp/mo_list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Fetch all serials/lots produced by this MO, with their destination warehouse
$sql = "SELECT mp.rowid as mp_rowid, mp.fk_product, mp.batch, mp.qty,";
$sql .= " mp.fk_warehouse, e.ref as warehouse_ref,";
$sql .= " p.ref as product_ref, p.label as product_label,";
$sql .= " lot.rowid as lot_id,";
$sql .= " pl.rowid as loc_rowid,";
$sql .= " pl.fk_entrepot as loc_fk_entrepot,";
$sql .= " pl.level1_value, pl.level2_value, pl.level3_value,";
$sql .= " pl.level4_value, pl.level5_value, pl.level6_value,";
$sql .= " pl.note as loc_note";
$sql .= " FROM ".MAIN_DB_PREFIX."mrp_production as mp";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = mp.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = mp.fk_warehouse";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_lot as lot ON (lot.fk_product = mp.fk_product AND lot.batch = mp.batch)";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."binloc_product_location as pl";
$sql .= "   ON (pl.fk_product_lot = lot.rowid AND pl.entity IN (".getEntity('stock')."))";
$sql .= " WHERE mp.fk_mo = ".(int) $object->id;
$sql .= " AND mp.role = 'produced'";
$sql .= " AND mp.batch IS NOT NULL AND mp.batch != ''";
$sql .= " ORDER BY p.ref ASC, mp.batch ASC";

$resql = $db->query($sql);
$rows = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	$db->free($resql);
}

if (empty($rows)) {
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('NoSerializedOutputFromMo').'</div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// Preload level configs for every active warehouse so the JS can reconfigure
// a row's inputs when its warehouse dropdown changes
$all_warehouses = binloc_get_warehouses($db);
$wh_level_cache = array();
foreach ($all_warehouses as $wh) {
	$cfg = $levelObj->fetchByWarehouse($wh->rowid);
	if (!empty($cfg)) {
		$wh_level_cache[(int) $wh->rowid] = $cfg;
	}
}

// Find max level count across all warehouses in this MO
$max_levels = 0;
foreach ($wh_level_cache as $levels) {
	if (count($levels) > $max_levels) {
		$max_levels = count($levels);
	}
}
if ($max_levels == 0) {
	$max_levels = 4; // Fallback
}

print '<div class="opacitymedium marginbottomonly">'.$langs->trans('MoBinAssignDesc').'</div>';

// ---- Bulk save form ----
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="binloc-mo-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id" value="'.$object->id.'">';
print '<input type="hidden" name="action" value="bulksavelocations">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Product').'</td>';
print '<td>'.$langs->trans('Batch').'</td>';
print '<td class="right">'.$langs->trans('Qty').'</td>';
print '<td>'.$langs->trans('Warehouse').'</td>';
print '<td colspan="'.$max_levels.'">'.$langs->trans('BinLocation').'</td>';
print '<td>'.$langs->trans('LocationNote').'</td>';
print '</tr>';

// JSON for per-warehouse level labels (for JS placeholder updates on warehouse change)
$wh_levels_json = json_encode($wh_level_cache, JSON_HEX_TAG | JSON_HEX_QUOT);

foreach ($rows as $row) {
	$lot_id    = !empty($row->lot_id) ? (int) $row->lot_id : 0;
	$target_wh = !empty($row->loc_fk_entrepot) ? (int) $row->loc_fk_entrepot : (int) $row->fk_warehouse;
	$wh_levels = isset($wh_level_cache[$target_wh]) ? $wh_level_cache[$target_wh] : array();

	if ($lot_id <= 0) {
		// Lot record hasn't been created yet — MO production may not be fully complete
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($row->product_ref).'</td>';
		print '<td><em>'.dol_escape_htmltag($row->batch).'</em></td>';
		print '<td class="right">'.price2num($row->qty, 0).'</td>';
		print '<td colspan="'.($max_levels + 2).'" class="opacitymedium">'.$langs->trans('LotNotYetCreated').'</td>';
		print '</tr>';
		continue;
	}

	$lot_url = dol_buildpath('/product/stock/productlot_card.php?id='.$lot_id, 1);
	$product_url = dol_buildpath('/product/card.php?id='.$row->fk_product, 1);

	print '<tr class="oddeven">';
	print '<input type="hidden" name="lot_ids[]" value="'.$lot_id.'">';
	print '<input type="hidden" name="prod_'.$lot_id.'" value="'.(int) $row->fk_product.'">';

	print '<td><a href="'.$product_url.'">'.dol_escape_htmltag($row->product_ref).'</a>';
	print '<br><span class="opacitymedium small">'.dol_escape_htmltag($row->product_label).'</span></td>';

	print '<td><a href="'.$lot_url.'"><span class="badge badge-info">'.dol_escape_htmltag($row->batch).'</span></a></td>';

	print '<td class="right">'.price2num($row->qty, 0).'</td>';

	// Warehouse selector — defaults to the MO's destination warehouse
	print '<td>';
	print '<select name="wh_'.$lot_id.'" class="flat minwidth150 binloc-wh-select" data-lot-id="'.$lot_id.'" onchange="binlocOnWhChange(this)">';
	foreach ($all_warehouses as $wh) {
		$sel = ((int) $wh->rowid == $target_wh) ? ' selected' : '';
		print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref).'</option>';
	}
	print '</select>';
	print '</td>';

	// Level inputs — one cell per level, extras blank
	for ($i = 1; $i <= $max_levels; $i++) {
		$val = $row->{'level'.$i.'_value'};
		print '<td class="binloc-level-cell" data-lot-id="'.$lot_id.'" data-level-num="'.$i.'">';
		if (isset($wh_levels[$i])) {
			print binloc_render_level_input($wh_levels[$i], 'level'.$i.'_'.$lot_id, $val, 'flat width75');
		} else {
			// No level at this depth for the default warehouse — render empty text input
			print '<input type="text" name="level'.$i.'_'.$lot_id.'" class="flat width75" value="'.dol_escape_htmltag($val).'">';
		}
		print '</td>';
	}

	print '<td><input type="text" name="note_'.$lot_id.'" class="flat width100" value="'.dol_escape_htmltag($row->loc_note).'"></td>';

	print '</tr>';
}

print '</table>';

print '<div class="margintoponly">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('BulkSaveAll')).'">';
print '</div>';

print '</form>';

// JS: when warehouse changes, rebuild the level inputs for that row
// matching the new warehouse's level configs (label, datatype, options)
print '<script>
var binlocWhLevels = '.$wh_levels_json.';

function binlocBuildLevelInput(levelCfg, inputName, currentVal) {
	if (levelCfg && levelCfg.datatype === "list" && levelCfg.options && levelCfg.options.length) {
		var html = \'<select name="\' + inputName + \'" class="flat width75">\';
		html += \'<option value=""></option>\';
		levelCfg.options.forEach(function(opt) {
			var sel = (opt === currentVal) ? " selected" : "";
			html += \'<option value="\' + binlocEsc(opt) + \'"\' + sel + \'>\' + binlocEsc(opt) + \'</option>\';
		});
		html += \'</select>\';
		return html;
	}
	var type = (levelCfg && levelCfg.datatype === "number") ? "number" : "text";
	var label = (levelCfg && levelCfg.label) ? levelCfg.label : "";
	return \'<input type="\' + type + \'" name="\' + inputName + \'" class="flat width75" value="\' + binlocEsc(currentVal || "") + \'" placeholder="\' + binlocEsc(label) + \'">\';
}

function binlocEsc(s) {
	return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function binlocOnWhChange(selectEl) {
	var lotId = selectEl.dataset.lotId;
	var whId = parseInt(selectEl.value);
	var levels = binlocWhLevels[whId] || {};
	document.querySelectorAll(".binloc-level-cell[data-lot-id=\"" + lotId + "\"]").forEach(function(cell) {
		var num = cell.dataset.levelNum;
		var cfg = levels[num] || null;
		var inputName = "level" + num + "_" + lotId;
		// Preserve current value if possible
		var existing = cell.querySelector("input, select");
		var currentVal = existing ? existing.value : "";
		cell.innerHTML = binlocBuildLevelInput(cfg, inputName, currentVal);
	});
}
</script>';

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
