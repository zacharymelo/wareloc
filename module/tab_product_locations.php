<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    tab_product_locations.php
 * \ingroup binloc
 * \brief   Product card tab — shows bin locations across all warehouses
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
dol_include_once('/binloc/class/binlocproductlocation.class.php');

$langs->loadLangs(array('products', 'stocks', 'binloc@binloc'));

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$mode   = GETPOST('mode', 'aZ09');

$object = new Product($db);
if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
	$id = $object->id;
}

if (empty($id) || $id <= 0) {
	accessforbidden('Product not found');
}

$levelObj = new BinlocWarehouseLevel($db);
$locObj   = new BinlocProductLocation($db);

// ---- ACTIONS ----

if ($action === 'savelocation' && $user->hasRight('binloc', 'write')) {
	$loc_fk_entrepot = GETPOSTINT('loc_fk_entrepot');
	if ($loc_fk_entrepot > 0) {
		$locObj->fk_product  = $id;
		$locObj->fk_entrepot = $loc_fk_entrepot;
		for ($i = 1; $i <= 6; $i++) {
			$locObj->{'level'.$i.'_value'} = GETPOST('level'.$i.'_value', 'alphanohtml');
		}
		$locObj->note = GETPOST('loc_note', 'alphanohtml');

		$result = $locObj->createOrUpdate($user);
		if ($result > 0) {
			setEventMessages($langs->trans('LocationSaved'), null, 'mesgs');
		} else {
			setEventMessages($locObj->error, null, 'errors');
		}
	}
	$action = '';
}

if ($action === 'confirmdeletelocation' && $user->hasRight('binloc', 'write')) {
	$loc_id = GETPOSTINT('loc_id');
	if ($loc_id > 0) {
		$locObj->fetch($loc_id);
		$result = $locObj->delete($user);
		if ($result > 0) {
			setEventMessages($langs->trans('LocationRemoved'), null, 'mesgs');
		} else {
			setEventMessages($locObj->error, null, 'errors');
		}
	}
	$action = '';
}

// ---- VIEW ----

llxHeader('', $langs->trans('BinLocations').' - '.$object->ref, '');

$head = product_prepare_head($object);
print dol_get_fiche_head($head, 'binloc', $langs->trans('Product'), -1, $object->picto);

// Product banner
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Fetch all existing locations for this product
$locations = $locObj->fetchAllByProduct($id);

// Build map of warehouse IDs that already have a location
$located_wh_ids = array();
foreach ($locations as $loc) {
	$located_wh_ids[$loc->fk_entrepot] = true;
}

// Fetch warehouses where product has stock but NO location assigned yet
$unlocated_warehouses = array();
$sql = "SELECT ps.fk_entrepot, ps.reel as stock, e.ref, e.lieu";
$sql .= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = ps.fk_entrepot";
$sql .= " WHERE ps.fk_product = ".(int) $id;
$sql .= " AND ps.reel > 0";
$sql .= " AND e.entity IN (".getEntity('stock').")";
$sql .= " AND e.statut = 1";
$sql .= " ORDER BY e.ref ASC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if (!isset($located_wh_ids[(int) $obj->fk_entrepot])) {
			$unlocated_warehouses[] = $obj;
		}
	}
	$db->free($resql);
}

$edit_loc_wh  = ($mode === 'edit') ? GETPOSTINT('edit_wh') : 0;
$assign_wh    = ($mode === 'assign') ? GETPOSTINT('assign_wh') : 0;
$add_mode     = ($mode === 'add');

// ---- Section 1: Warehouses needing location assignment ----
if (!empty($unlocated_warehouses) && $user->hasRight('binloc', 'write')) {
	print '<div class="underbanner" style="margin-bottom:8px;">';
	print '<strong>'.img_picto('', 'warning', 'class="pictofixedwidth"').$langs->trans('StockWithoutLocation').'</strong>';
	print '</div>';

	foreach ($unlocated_warehouses as $uwh) {
		$wh_id     = (int) $uwh->fk_entrepot;
		$wh_levels = $levelObj->fetchByWarehouse($wh_id);
		$wh_url    = dol_buildpath('/product/stock/card.php?id='.$wh_id, 1);
		$is_assigning = ($assign_wh == $wh_id);

		print '<div class="fichecenter" style="margin-bottom:8px; padding:8px; border:1px solid #e8c96e; border-radius:4px; background:#fffdf0;">';

		print '<div style="display:flex; justify-content:space-between; align-items:center;">';
		print '<div>';
		print '<strong>'.img_picto('', 'stock', 'class="pictofixedwidth"');
		print '<a href="'.$wh_url.'">'.dol_escape_htmltag($uwh->ref).'</a></strong>';
		if ($uwh->lieu) {
			print ' <span class="opacitymedium">'.dol_escape_htmltag($uwh->lieu).'</span>';
		}
		print ' &mdash; '.$langs->trans('Stock').': '.price2num($uwh->stock, 0);
		print ' &mdash; <span class="opacitymedium small">'.$langs->trans('NoBinLocationAssigned').'</span>';
		print '</div>';

		if (!$is_assigning) {
			if (!empty($wh_levels)) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&mode=assign&assign_wh='.$wh_id.'" class="button smallpaddingimp">';
				print img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('AssignLocation');
				print '</a>';
			} else {
				$setup_url = dol_buildpath('/binloc/admin/warehouse_levels.php?fk_entrepot='.$wh_id, 1);
				print '<a href="'.$setup_url.'" class="button smallpaddingimp">';
				print img_picto('', 'setup', 'class="pictofixedwidth"').$langs->trans('ConfigureLevels');
				print '</a>';
			}
		}
		print '</div>';

		if ($is_assigning && !empty($wh_levels)) {
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="margin-top:6px;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<input type="hidden" name="action" value="savelocation">';
			print '<input type="hidden" name="loc_fk_entrepot" value="'.$wh_id.'">';

			print '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:end;">';
			foreach ($wh_levels as $num => $cfg) {
				print '<div>';
				print '<label class="opacitymedium small">'.dol_escape_htmltag($cfg->label).'</label><br>';
				print binloc_render_level_input($cfg, 'level'.$num.'_value', '', 'flat width100');
				print '</div>';
			}
			print '<div>';
			print '<label class="opacitymedium small">'.$langs->trans('LocationNote').'</label><br>';
			print '<input type="text" name="loc_note" class="flat minwidth150">';
			print '</div>';
			print '<div>';
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
			print '</div>';
			print '</div>';
			print '</form>';
		}

		print '</div>';
	}
}

// ---- Section 2: Existing locations ----
if (!empty($locations)) {
	if (!empty($unlocated_warehouses)) {
		print '<div class="underbanner" style="margin-bottom:8px; margin-top:12px;">';
		print '<strong>'.img_picto('', 'stock', 'class="pictofixedwidth"').$langs->trans('AssignedLocations').'</strong>';
		print '</div>';
	}

	foreach ($locations as $loc) {
		$wh_levels = $levelObj->fetchByWarehouse($loc->fk_entrepot);
		$is_editing = ($edit_loc_wh == $loc->fk_entrepot);

		$wh_url = dol_buildpath('/product/stock/card.php?id='.$loc->fk_entrepot, 1);

		print '<div class="fichecenter" style="margin-bottom:8px; padding:8px; border:1px solid #ddd; border-radius:4px;">';

		// Header
		print '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">';
		print '<div>';
		print '<strong>'.img_picto('', 'stock', 'class="pictofixedwidth"');
		print '<a href="'.$wh_url.'">'.dol_escape_htmltag($loc->warehouse_ref).'</a></strong>';
		if ($loc->warehouse_label) {
			print ' <span class="opacitymedium">'.dol_escape_htmltag($loc->warehouse_label).'</span>';
		}
		if (!empty($loc->lot_batch)) {
			print ' &mdash; <span class="badge badge-info">'.dol_escape_htmltag($loc->lot_batch).'</span>';
		}
		print ' &mdash; '.$langs->trans('Stock').': '.price2num($loc->stock, 0);
		print '</div>';

		if ($user->hasRight('binloc', 'write')) {
			print '<div>';
			if (!$is_editing) {
				$edit_url = $_SERVER['PHP_SELF'].'?id='.$id.'&mode=edit&edit_wh='.$loc->fk_entrepot;
				print '<a href="'.$edit_url.'" class="button smallpaddingimp">'.$langs->trans('EditLocation').'</a>';
				print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=confirmdeletelocation&loc_id='.$loc->rowid.'&token='.newToken().'"';
				print ' class="button smallpaddingimp" onclick="return confirm(\''.dol_escape_js($langs->trans('ConfirmRemoveLocation', $loc->warehouse_ref)).'\');">';
				print $langs->trans('RemoveLocation').'</a>';
			}
			print '</div>';
		}
		print '</div>';

		if ($is_editing) {
			// Edit form
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<input type="hidden" name="action" value="savelocation">';
			print '<input type="hidden" name="loc_fk_entrepot" value="'.$loc->fk_entrepot.'">';

			print '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:end;">';
			foreach ($wh_levels as $num => $cfg) {
				$val = $loc->{'level'.$num.'_value'};
				print '<div>';
				print '<label class="opacitymedium small">'.dol_escape_htmltag($cfg->label).'</label><br>';
				print binloc_render_level_input($cfg, 'level'.$num.'_value', $val, 'flat width100');
				print '</div>';
			}
			print '<div>';
			print '<label class="opacitymedium small">'.$langs->trans('LocationNote').'</label><br>';
			print '<input type="text" name="loc_note" class="flat minwidth150" value="'.dol_escape_htmltag($loc->note).'">';
			print '</div>';
			print '<div>';
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
			print '</div>';
			print '</div>';
			print '</form>';
		} else {
			// Display
			if (!empty($wh_levels)) {
				print '<div style="display:flex; flex-wrap:wrap; gap:12px;">';
				foreach ($wh_levels as $num => $cfg) {
					$val = $loc->{'level'.$num.'_value'};
					if ($val !== null && $val !== '') {
						print '<span><span class="opacitymedium small">'.dol_escape_htmltag($cfg->label).':</span> <strong>'.dol_escape_htmltag($val).'</strong></span>';
					}
				}
				if ($loc->note) {
					print '<span class="opacitymedium small">('.dol_escape_htmltag($loc->note).')</span>';
				}
				print '</div>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NoLevelsConfigured').'</span>';
			}
		}

		print '</div>';
	}
} elseif (empty($unlocated_warehouses)) {
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('NoLocationsFound').'</div>';
}

// ---- Section 3: Add to any other warehouse (general fallback) ----
if ($add_mode && $user->hasRight('binloc', 'write')) {
	$add_wh = GETPOSTINT('add_wh');
	$warehouses = binloc_get_warehouses($db);

	print '<div class="fichecenter" style="margin-bottom:8px; padding:8px; border:1px solid #9c9; border-radius:4px; background:#f8fff8;">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="action" value="savelocation">';

	print '<div class="marginbottomonly">';
	print '<strong>'.$langs->trans('Warehouse').'</strong>: ';
	print '<select name="loc_fk_entrepot" class="flat minwidth250" onchange="binlocLoadAddLevels(this.value)">';
	print '<option value="0">'.$langs->trans('SelectWarehouse').'</option>';

	// Exclude warehouses that already have a location OR are in the unlocated list
	$exclude_wh_ids = $located_wh_ids;
	foreach ($unlocated_warehouses as $uwh) {
		$exclude_wh_ids[(int) $uwh->fk_entrepot] = true;
	}
	foreach ($warehouses as $wh) {
		if (isset($exclude_wh_ids[$wh->rowid])) {
			continue;
		}
		$sel = ($add_wh == $wh->rowid) ? ' selected' : '';
		print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref);
		if ($wh->lieu) {
			print ' - '.dol_escape_htmltag($wh->lieu);
		}
		print '</option>';
	}
	print '</select>';
	print '</div>';

	print '<div id="binloc-add-levels">';
	if ($add_wh > 0) {
		$add_levels = $levelObj->fetchByWarehouse($add_wh);
		if (!empty($add_levels)) {
			print '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:end;">';
			foreach ($add_levels as $num => $cfg) {
				print '<div>';
				print '<label class="opacitymedium small">'.dol_escape_htmltag($cfg->label).'</label><br>';
				print binloc_render_level_input($cfg, 'level'.$num.'_value', '', 'flat width100');
				print '</div>';
			}
			print '<div>';
			print '<label class="opacitymedium small">'.$langs->trans('LocationNote').'</label><br>';
			print '<input type="text" name="loc_note" class="flat minwidth150">';
			print '</div>';
			print '</div>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('NoLevelsConfigured').'</span>';
		}
	}
	print '</div>';

	print '<div class="margintoponly">';
	print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';
	print '</div>';

	print '<script>
function binlocLoadAddLevels(whId) {
	if (whId > 0) {
		window.location.href = "'.$_SERVER['PHP_SELF'].'?id='.$id.'&mode=add&add_wh=" + whId;
	}
}
</script>';
}

// Add to other warehouse button (shown when not already in add mode)
if (!$add_mode && $user->hasRight('binloc', 'write')) {
	print '<div class="margintoponly">';
	print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&mode=add" class="button">';
	print img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('AddToOtherWarehouse');
	print '</a>';
	print '</div>';
}

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
