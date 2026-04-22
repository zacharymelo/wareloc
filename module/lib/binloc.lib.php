<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    lib/binloc.lib.php
 * \ingroup binloc
 * \brief   Helper functions for Binloc module
 */

/**
 * Build admin page tab header
 *
 * @return array Array of tab definitions
 */
function binloc_admin_prepare_head()
{
	global $langs, $conf;

	$langs->load('binloc@binloc');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/binloc/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/binloc/admin/warehouse_levels.php', 1);
	$head[$h][1] = $langs->trans('WarehouseLevels');
	$head[$h][2] = 'warehouselevels';
	$h++;

	return $head;
}

/**
 * Get warehouse level configuration (shorthand)
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $fk_entrepot  Warehouse ID
 * @return array                level_num => stdClass config map
 */
function binloc_get_warehouse_levels($db, $fk_entrepot)
{
	dol_include_once('/binloc/class/binlocwarehouselevel.class.php');

	$lvl = new BinlocWarehouseLevel($db);
	return $lvl->fetchByWarehouse($fk_entrepot);
}

/**
 * Render a level input element based on its configured datatype
 *
 * Produces a text input, number input, or select dropdown depending on
 * the level's datatype configuration. Used consistently across all UIs
 * that collect level values (product tab, warehouse tab, MO tab, bulk assign).
 *
 * @param  object $level_cfg   stdClass from fetchByWarehouse (label, datatype, options)
 * @param  string $input_name  HTML name attribute for the input
 * @param  string $current_val Current value to pre-populate (if any)
 * @param  string $css_class   Optional CSS class override (default: 'flat width100')
 * @param  string $extra_attrs Optional extra HTML attributes
 * @return string              HTML input element
 */
function binloc_render_level_input($level_cfg, $input_name, $current_val = '', $css_class = 'flat width100', $extra_attrs = '')
{
	$datatype = isset($level_cfg->datatype) ? $level_cfg->datatype : 'text';
	$label    = isset($level_cfg->label) ? $level_cfg->label : '';
	$current_val = (string) $current_val;

	if ($datatype === 'list' && !empty($level_cfg->options)) {
		$html = '<select name="'.dol_escape_htmltag($input_name).'" class="'.dol_escape_htmltag($css_class).'" '.$extra_attrs.'>';
		$html .= '<option value=""></option>';
		foreach ($level_cfg->options as $opt) {
			$sel = ($current_val === (string) $opt) ? ' selected' : '';
			$html .= '<option value="'.dol_escape_htmltag($opt).'"'.$sel.'>'.dol_escape_htmltag($opt).'</option>';
		}
		$html .= '</select>';
		return $html;
	}

	if ($datatype === 'number') {
		return '<input type="number" name="'.dol_escape_htmltag($input_name).'" class="'.dol_escape_htmltag($css_class).'" value="'.dol_escape_htmltag($current_val).'" placeholder="'.dol_escape_htmltag($label).'" '.$extra_attrs.'>';
	}

	// Default: text
	return '<input type="text" name="'.dol_escape_htmltag($input_name).'" class="'.dol_escape_htmltag($css_class).'" value="'.dol_escape_htmltag($current_val).'" placeholder="'.dol_escape_htmltag($label).'" '.$extra_attrs.'>';
}

/**
 * Get formatted location string for a product in a warehouse
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $fk_product   Product ID
 * @param  int    $fk_entrepot  Warehouse ID
 * @return string               Formatted location or empty string
 */
function binloc_format_location($db, $fk_product, $fk_entrepot)
{
	dol_include_once('/binloc/class/binlocproductlocation.class.php');

	$loc = new BinlocProductLocation($db);
	$result = $loc->fetchByProductWarehouse($fk_product, $fk_entrepot);
	if ($result <= 0) {
		return '';
	}

	$levels = binloc_get_warehouse_levels($db, $fk_entrepot);
	if (empty($levels)) {
		return '';
	}

	return $loc->getFormattedLocation($levels);
}

/**
 * Get all active warehouses (no parent filter — all warehouses, for the level config page)
 *
 * @param  DoliDB $db Database handler
 * @return array      Array of warehouse objects (rowid, ref, lieu, stock)
 */
function binloc_get_warehouses($db)
{
	$warehouses = array();

	$sql = "SELECT e.rowid, e.ref, e.lieu, e.statut,";
	$sql .= " SUM(ps.reel) as stock";
	$sql .= " FROM ".MAIN_DB_PREFIX."entrepot as e";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.fk_entrepot = e.rowid";
	$sql .= " WHERE e.entity IN (".getEntity('stock').")";
	$sql .= " AND e.statut = 1";
	$sql .= " GROUP BY e.rowid, e.ref, e.lieu, e.statut";
	$sql .= " ORDER BY e.ref ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $warehouses;
	}

	while ($obj = $db->fetch_object($resql)) {
		$warehouses[] = $obj;
	}
	$db->free($resql);

	return $warehouses;
}

/**
 * Get all products with stock in a specific warehouse (for bulk assign)
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $fk_entrepot  Warehouse ID
 * @param  string $search       Optional ref/label search filter
 * @param  string $sortfield    Sort field
 * @param  string $sortorder    Sort order
 * @param  int    $limit        Max rows
 * @param  int    $offset       Offset
 * @return array                Array of product objects with stock and location data
 */
function binloc_get_products_in_warehouse($db, $fk_entrepot, $search = '', $sortfield = 'p.ref', $sortorder = 'ASC', $limit = 0, $offset = 0)
{
	$products = array();

	$sql = "SELECT p.rowid as fk_product, p.ref, p.label,";
	$sql .= " ps.reel as stock,";
	$sql .= " pl.rowid as loc_rowid,";
	$sql .= " pl.level1_value, pl.level2_value, pl.level3_value,";
	$sql .= " pl.level4_value, pl.level5_value, pl.level6_value,";
	$sql .= " pl.note";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = ps.fk_product";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."binloc_product_location as pl";
	$sql .= "   ON (pl.fk_product = ps.fk_product AND pl.fk_entrepot = ps.fk_entrepot";
	$sql .= "   AND pl.entity IN (".getEntity('stock')."))";
	$sql .= " WHERE ps.fk_entrepot = ".(int) $fk_entrepot;
	$sql .= " AND ps.reel > 0";
	$sql .= " AND p.entity IN (".getEntity('product').")";

	if (!empty($search)) {
		$sql .= " AND (p.ref LIKE '%".$db->escape($search)."%'";
		$sql .= " OR p.label LIKE '%".$db->escape($search)."%')";
	}

	$sql .= $db->order($sortfield, $sortorder);
	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		return $products;
	}

	while ($obj = $db->fetch_object($resql)) {
		$products[] = $obj;
	}
	$db->free($resql);

	return $products;
}

/**
 * Count products with stock in a specific warehouse
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $fk_entrepot  Warehouse ID
 * @param  string $search       Optional search filter
 * @return int                  Count
 */
function binloc_count_products_in_warehouse($db, $fk_entrepot, $search = '')
{
	$sql = "SELECT COUNT(*) as nb";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
	if (!empty($search)) {
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = ps.fk_product";
	}
	$sql .= " WHERE ps.fk_entrepot = ".(int) $fk_entrepot;
	$sql .= " AND ps.reel > 0";

	if (!empty($search)) {
		$sql .= " AND (p.ref LIKE '%".$db->escape($search)."%'";
		$sql .= " OR p.label LIKE '%".$db->escape($search)."%')";
	}

	$resql = $db->query($sql);
	if (!$resql) {
		return 0;
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);

	return (int) $obj->nb;
}
