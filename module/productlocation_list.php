<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    productlocation_list.php
 * \ingroup wareloc
 * \brief   List page for ProductLocation records
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
dol_include_once('/wareloc/class/productlocation.class.php');
dol_include_once('/wareloc/lib/wareloc.lib.php');

$langs->loadLangs(array('wareloc@wareloc', 'products', 'stocks'));

$action     = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$toselect   = GETPOST('toselect', 'array');

$search_ref       = GETPOST('search_ref', 'alpha');
$search_product   = GETPOSTINT('search_product');
$search_warehouse = GETPOSTINT('search_warehouse');
$search_status    = GETPOST('search_status', 'intcomma');

// Pre-filter from product tab link
$fk_product_filter = GETPOSTINT('fk_product');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTINT('page');
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset    = $limit * $page;

if (empty($sortfield)) { $sortfield = 't.rowid'; }
if (empty($sortorder)) { $sortorder = 'DESC'; }

if (!$user->hasRight('wareloc', 'productlocation', 'read')) {
	accessforbidden();
}

// Reset filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_ref = '';
	$search_product = 0;
	$search_warehouse = 0;
	$search_status = '';
	$fk_product_filter = 0;
}

// Load active levels for dynamic column headers (warehouse-filtered when searching a specific warehouse)
$levels = wareloc_get_active_levels(null, $search_warehouse > 0 ? $search_warehouse : 0);

// ---- BUILD SQL ----

$sql = "SELECT t.rowid, t.ref, t.fk_product, t.fk_entrepot";
$sql .= ", t.level_1, t.level_2, t.level_3, t.level_4, t.level_5, t.level_6";
$sql .= ", t.qty, t.is_default, t.status, t.date_creation";
$sql .= ", p.ref as product_ref, p.label as product_label";
$sql .= ", e.ref as warehouse_ref, e.lieu as warehouse_label";
$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_product_location as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = t.fk_entrepot";
$sql .= " WHERE t.entity IN (".getEntity('productlocation').")";

if (!empty($search_ref)) {
	$sql .= natural_search('t.ref', $search_ref);
}
if ($search_product > 0) {
	$sql .= " AND t.fk_product = ".((int) $search_product);
}
if ($fk_product_filter > 0) {
	$sql .= " AND t.fk_product = ".((int) $fk_product_filter);
}
if ($search_warehouse > 0) {
	$sql .= " AND t.fk_entrepot = ".((int) $search_warehouse);
}
if ($search_status !== '' && $search_status !== '-1') {
	$sql .= " AND t.status = ".((int) $search_status);
}

// Level search filters
for ($i = 1; $i <= 6; $i++) {
	$search_level = GETPOST('search_level_'.$i, 'alpha');
	if (!empty($search_level)) {
		$sql .= natural_search('t.level_'.$i, $search_level);
	}
}

// Count total
$sql_count = preg_replace('/^SELECT[^]*FROM/Us', 'SELECT COUNT(t.rowid) as total FROM', $sql);
$resql = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$nbtotalofrecords = (int) $obj->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);


// ---- VIEW ----

$form = new Form($db);

$title = $langs->trans('ProductLocationList');
if ($fk_product_filter > 0) {
	$product_tmp = new Product($db);
	if ($product_tmp->fetch($fk_product_filter) > 0) {
		$title .= ' - '.$product_tmp->ref;
	}
}

llxHeader('', $title, '');

$newurl = dol_buildpath('/wareloc/productlocation_card.php', 1).'?action=create';
if ($fk_product_filter > 0) {
	$newurl .= '&fk_product='.$fk_product_filter;
}
$newbutton = dolGetButtonTitle($langs->trans('NewProductLocation'), '', 'fa fa-plus-circle', $newurl, '', $user->hasRight('wareloc', 'productlocation', 'write'));

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], '', $sortfield, $sortorder, '', $nbtotalofrecords, $nbtotalofrecords, 'stock', 0, $newbutton, '', $limit, 0, 0, 1);

// Search form
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
if ($fk_product_filter > 0) {
	print '<input type="hidden" name="fk_product" value="'.$fk_product_filter.'">';
}

print '<table class="noborder centpercent">';

// Header row
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 't.ref', '', '', '', $sortfield, $sortorder);
if (empty($fk_product_filter)) {
	print_liste_field_titre('Product', $_SERVER['PHP_SELF'], 'p.ref', '', '', '', $sortfield, $sortorder);
}
print_liste_field_titre('Warehouse', $_SERVER['PHP_SELF'], 'e.ref', '', '', '', $sortfield, $sortorder);

// Dynamic level columns
foreach ($levels as $lev) {
	print_liste_field_titre($lev->label, $_SERVER['PHP_SELF'], 't.level_'.$lev->position, '', '', '', $sortfield, $sortorder);
}

print_liste_field_titre('Quantity', $_SERVER['PHP_SELF'], 't.qty', '', '', 'class="right"', $sortfield, $sortorder);
print_liste_field_titre('IsDefault', $_SERVER['PHP_SELF'], 't.is_default', '', '', 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 't.status', '', '', 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER['PHP_SELF'], 't.date_creation', '', '', 'class="center"', $sortfield, $sortorder);
print '</tr>';

// Search row
print '<tr class="liste_titre">';
print '<td><input type="text" name="search_ref" class="flat maxwidth100" value="'.dol_escape_htmltag($search_ref).'"></td>';
if (empty($fk_product_filter)) {
	print '<td>';
	print $form->select_produits($search_product, 'search_product', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth200');
	print '</td>';
}
print '<td>';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
$formproduct = new FormProduct($db);
$formproduct->selectWarehouses($search_warehouse, 'search_warehouse', '', 1);
print '</td>';

// Level search inputs
foreach ($levels as $lev) {
	$sval = GETPOST('search_level_'.$lev->position, 'alpha');
	print '<td><input type="text" name="search_level_'.$lev->position.'" class="flat maxwidth75" value="'.dol_escape_htmltag($sval).'"></td>';
}

print '<td></td>'; // qty
print '<td></td>'; // default
print '<td class="center">';
$status_array = array(
	'-1' => '',
	'0' => $langs->trans('StatusDraft'),
	'1' => $langs->trans('StatusActive'),
	'5' => $langs->trans('StatusMoved'),
	'9' => $langs->trans('StatusCancelled'),
);
print $form->selectarray('search_status', $status_array, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
print '<td class="center">';
print '<input type="submit" class="liste_titre button_search" name="button_search" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '<input type="submit" class="liste_titre button_removefilter" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans('RemoveFilter')).'">';
print '</td>';
print '</tr>';

// Data rows
$resql = $db->query($sql);
if ($resql) {
	$i = 0;
	while ($i < min($db->num_rows($resql), $limit)) {
		$obj = $db->fetch_object($resql);

		$loc = new ProductLocation($db);
		$loc->id = $obj->rowid;
		$loc->ref = $obj->ref;
		$loc->status = $obj->status;

		print '<tr class="oddeven">';

		// Ref
		print '<td>'.$loc->getNomUrl(1).'</td>';

		// Product
		if (empty($fk_product_filter)) {
			print '<td>';
			if ($obj->fk_product > 0) {
				$p = new Product($db);
				$p->id = $obj->fk_product;
				$p->ref = $obj->product_ref;
				$p->label = $obj->product_label;
				print $p->getNomUrl(1);
			}
			print '</td>';
		}

		// Warehouse
		print '<td>';
		if ($obj->fk_entrepot > 0) {
			$e = new Entrepot($db);
			$e->id = $obj->fk_entrepot;
			$e->ref = $obj->warehouse_ref;
			$e->label = $obj->warehouse_label;
			$e->lieu = $obj->warehouse_label;
			print $e->getNomUrl(1);
		}
		print '</td>';

		// Level values
		foreach ($levels as $lev) {
			$key = 'level_'.$lev->position;
			print '<td>'.dol_escape_htmltag($obj->$key).'</td>';
		}

		// Qty
		print '<td class="right">'.dol_escape_htmltag($obj->qty).'</td>';

		// Default
		print '<td class="center">'.($obj->is_default ? img_picto('Default', 'tick') : '').'</td>';

		// Status
		print '<td class="center">'.$loc->getLibStatut(5).'</td>';

		// Date
		print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';

		print '</tr>';
		$i++;
	}

	if ($i == 0) {
		$colspan = 7 + count($levels) + (empty($fk_product_filter) ? 1 : 0);
		print '<tr class="oddeven"><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
	}

	$db->free($resql);
} else {
	dol_print_error($db);
}

print '</table>';
print '</form>';

llxFooter();
$db->close();
