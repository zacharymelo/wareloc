<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    tab_warehouse_locations.php
 * \ingroup binloc
 * \brief   Warehouse card tab — shows all product bin locations in this warehouse
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/stock.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
dol_include_once('/binloc/class/binlocproductlocation.class.php');

$langs->loadLangs(array('products', 'stocks', 'binloc@binloc'));

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mode   = GETPOST('mode', 'aZ09');
$search = GETPOST('search_product', 'alphanohtml');

// Pagination
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'p.ref';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
$limit     = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset    = $limit * max(0, $page);

$object = new Entrepot($db);
if ($id > 0) {
	$object->fetch($id);
}

if (empty($object->id) || $object->id <= 0) {
	accessforbidden('Warehouse not found');
}

$levelObj = new BinlocWarehouseLevel($db);
$locObj   = new BinlocProductLocation($db);

$wh_levels = $levelObj->fetchByWarehouse($id);

// ---- ACTIONS ----

if ($action === 'saveinline' && $user->hasRight('binloc', 'write')) {
	$edit_product_id = GETPOSTINT('edit_product_id');
	if ($edit_product_id > 0) {
		$locObj->fk_product  = $edit_product_id;
		$locObj->fk_entrepot = $id;
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

// ---- VIEW ----

llxHeader('', $langs->trans('BinLocations').' - '.$object->ref, '');

$head = stock_prepare_head($object);
print dol_get_fiche_head($head, 'binloc', $langs->trans('Warehouse'), -1, 'stock');

// Warehouse banner
$linkback = '<a href="'.DOL_URL_ROOT.'/product/stock/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if (empty($wh_levels)) {
	print '<div class="info marginbottomonly">';
	print $langs->trans('NoLevelsConfigured').' ';
	if ($user->hasRight('binloc', 'admin') || $user->admin) {
		$setup_url = dol_buildpath('/binloc/admin/warehouse_levels.php?fk_entrepot='.$id, 1);
		print '<a href="'.$setup_url.'" class="button smallpaddingimp">'.$langs->trans('ConfigureLevels').'</a>';
	}
	print '</div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// Level names summary
print '<div class="opacitymedium marginbottomonly small">';
print $langs->trans('WarehouseLevels').': ';
$label_strs = array();
foreach ($wh_levels as $cfg) {
	$label_strs[] = dol_escape_htmltag($cfg->label);
}
print implode(' &rarr; ', $label_strs);
print '</div>';

// Search bar
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<div class="marginbottomonly">';
print '<input type="text" name="search_product" class="flat minwidth200" value="'.dol_escape_htmltag($search).'" placeholder="'.dol_escape_htmltag($langs->trans('SearchProduct')).'">';
print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Search').'">';
if (!empty($search)) {
	print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'" class="button smallpaddingimp">'.$langs->trans('Reset').'</a>';
}
print '</div>';
print '</form>';

// Fetch product locations for this warehouse
$locations = $locObj->fetchAllByWarehouse($id, $search, $sortfield, $sortorder, $limit, $offset);
$total     = $locObj->countByWarehouse($id, $search);

$edit_product = ($mode === 'edit') ? GETPOSTINT('edit_pid') : 0;

if (!empty($locations) || !empty($search)) {
	// Pagination
	print_barre_liste(
		$langs->trans('ProductsInWarehouse', $object->ref),
		$page,
		$_SERVER['PHP_SELF'],
		'&id='.$id.(!empty($search) ? '&search_product='.urlencode($search) : ''),
		$sortfield,
		$sortorder,
		'',
		count($locations),
		$total,
		'',
		0,
		'',
		'',
		$limit
	);

	// Check if any location has a lot/serial
	$has_lots = false;
	foreach ($locations as $loc) {
		if (!empty($loc->lot_batch)) {
			$has_lots = true;
			break;
		}
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print_liste_field_titre('ProductRef', $_SERVER['PHP_SELF'], 'p.ref', '', '&id='.$id, '', $sortfield, $sortorder);
	print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 'p.label', '', '&id='.$id, '', $sortfield, $sortorder);
	if ($has_lots) {
		print '<td>'.$langs->trans('Lot').'/'.$langs->trans('Serial').'</td>';
	}
	print '<td class="right">'.$langs->trans('Stock').'</td>';
	foreach ($wh_levels as $num => $cfg) {
		print '<td>'.dol_escape_htmltag($cfg->label).'</td>';
	}
	print '<td>'.$langs->trans('LocationNote').'</td>';
	print '<td class="center">'.$langs->trans('Actions').'</td>';
	print '</tr>';

	if (empty($locations)) {
		$colspan = 5 + count($wh_levels) + ($has_lots ? 1 : 0);
		print '<tr class="oddeven"><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans('NoResult').'</td></tr>';
	}

	foreach ($locations as $loc) {
		$is_editing = ($edit_product == $loc->fk_product);
		$product_url = dol_buildpath('/product/card.php?id='.$loc->fk_product, 1);

		if ($is_editing) {
			print '<tr class="oddeven">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			print '<input type="hidden" name="action" value="saveinline">';
			print '<input type="hidden" name="edit_product_id" value="'.$loc->fk_product.'">';
			if (!empty($search)) {
				print '<input type="hidden" name="search_product" value="'.dol_escape_htmltag($search).'">';
			}
			print '<td><a href="'.$product_url.'">'.dol_escape_htmltag($loc->product_ref).'</a></td>';
			print '<td>'.dol_escape_htmltag($loc->product_label).'</td>';
			if ($has_lots) {
				print '<td>'.(!empty($loc->lot_batch) ? dol_escape_htmltag($loc->lot_batch) : '').'</td>';
			}
			print '<td class="right">'.price2num($loc->stock, 0).'</td>';
			foreach ($wh_levels as $num => $cfg) {
				$val = $loc->{'level'.$num.'_value'};
				print '<td>'.binloc_render_level_input($cfg, 'level'.$num.'_value', $val, 'flat width75').'</td>';
			}
			print '<td><input type="text" name="loc_note" class="flat width100" value="'.dol_escape_htmltag($loc->note).'"></td>';
			print '<td class="center nowraponall">';
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
			print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$id.(!empty($search) ? '&search_product='.urlencode($search) : '').'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
			print '</td>';
			print '</form>';
			print '</tr>';
		} else {
			print '<tr class="oddeven">';
			print '<td><a href="'.$product_url.'">'.dol_escape_htmltag($loc->product_ref).'</a></td>';
			print '<td>'.dol_escape_htmltag($loc->product_label).'</td>';
			if ($has_lots) {
				print '<td>'.(!empty($loc->lot_batch) ? dol_escape_htmltag($loc->lot_batch) : '').'</td>';
			}
			print '<td class="right">'.price2num($loc->stock, 0).'</td>';
			foreach ($wh_levels as $num => $cfg) {
				$val = $loc->{'level'.$num.'_value'};
				print '<td>'.($val !== null && $val !== '' ? dol_escape_htmltag($val) : '<span class="opacitymedium">—</span>').'</td>';
			}
			print '<td>'.($loc->note ? dol_escape_htmltag($loc->note) : '').'</td>';
			print '<td class="center">';
			if ($user->hasRight('binloc', 'write')) {
				$edit_url = $_SERVER['PHP_SELF'].'?id='.$id.'&mode=edit&edit_pid='.$loc->fk_product;
				if (!empty($search)) {
					$edit_url .= '&search_product='.urlencode($search);
				}
				print '<a href="'.$edit_url.'">'.img_picto($langs->trans('EditLocation'), 'edit').'</a>';
			}
			print '</td>';
			print '</tr>';
		}
	}

	print '</table>';
} else {
	print '<div class="opacitymedium">'.$langs->trans('NoProductsWithStock').'</div>';
}

// Link to bulk assign
if ($user->hasRight('binloc', 'write')) {
	$bulk_url = dol_buildpath('/binloc/bulk_assign.php?fk_entrepot='.$id, 1);
	print '<div class="margintoponly">';
	print '<a href="'.$bulk_url.'" class="button">';
	print img_picto('', 'edit', 'class="pictofixedwidth"').$langs->trans('BulkBinAssign');
	print '</a>';

	if ($user->hasRight('binloc', 'admin') || $user->admin) {
		$setup_url = dol_buildpath('/binloc/admin/warehouse_levels.php?fk_entrepot='.$id, 1);
		print ' <a href="'.$setup_url.'" class="button">';
		print img_picto('', 'setup', 'class="pictofixedwidth"').$langs->trans('ManageLevels');
		print '</a>';
	}
	print '</div>';
}

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
