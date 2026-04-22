<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    class/actions_binloc.class.php
 * \ingroup binloc
 * \brief   Binloc hooks — warehouse card, reception card, and dispatch integrations
 */

/**
 * Class ActionsBinloc
 *
 * Hook contexts: warehousecard, productcard, receptioncard, ordersupplierdispatch
 */
class ActionsBinloc
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	// =========================================================================
	// warehousecard — show bin location count on warehouse card
	// =========================================================================

	/**
	 * Hook: formObjectOptions — inject location summary on warehouse card
	 *
	 * @param  array   $parameters Hook parameters
	 * @param  object  $object     The Entrepot object
	 * @param  string  $action     Current action
	 * @return int                 0 = continue hooks
	 */
	public function formObjectOptions($parameters, &$object, &$action)
	{
		$contexts = isset($parameters['currentcontext']) ? explode(':', $parameters['currentcontext']) : array();

		if (in_array('warehousecard', $contexts)) {
			return $this->_binloc_warehousecard_options($object);
		}

		return 0;
	}

	/**
	 * Inject the bin location summary row on the warehouse card
	 *
	 * @param  object $object The Entrepot object
	 * @return int            0
	 */
	private function _binloc_warehousecard_options($object)
	{
		global $langs;

		if (empty($object->id) || $object->id <= 0) {
			return 0;
		}

		dol_include_once('/binloc/lib/binloc.lib.php');
		dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
		dol_include_once('/binloc/class/binlocproductlocation.class.php');
		$langs->load('binloc@binloc');

		$levels = binloc_get_warehouse_levels($this->db, $object->id);
		if (empty($levels)) {
			return 0;
		}

		$locObj = new BinlocProductLocation($this->db);
		$count  = $locObj->countByWarehouse($object->id);

		if ($count > 0) {
			$tab_url = dol_buildpath('/binloc/tab_warehouse_locations.php?id='.$object->id, 1);
			$label_strs = array();
			foreach ($levels as $cfg) {
				$label_strs[] = dol_escape_htmltag($cfg->label);
			}
			print '<tr><td>'.$langs->trans('BinLocations').'</td>';
			print '<td><a href="'.$tab_url.'">'.$count.' '.$langs->trans('Products').'</a>';
			print ' <span class="opacitymedium small">('.implode(' &rarr; ', $label_strs).')</span>';
			print '</td></tr>';
		}

		return 0;
	}

	// =========================================================================
	// receptioncard — tab-based approach, see tab_reception_locations.php
	// =========================================================================

	/**
	 * Unused — reception bin placement moved to a dedicated tab that reuses
	 * the bulk assign UX. Kept as a stub so module_parts registration remains
	 * compatible with older installs.
	 *
	 * @param  object $object The Reception object
	 * @return int            0
	 */
	private function _binloc_receptioncard_inject($object)
	{
		return 0;
	}

	/**
	 * Unused — no-op stub for the formAddObjectLine hook slot.
	 *
	 * @param  array   $parameters Hook parameters
	 * @param  object  $object     Reception object
	 * @param  string  $action     Current action
	 * @return int                 0
	 */
	public function formAddObjectLine($parameters, &$object, &$action)
	{
		return 0;
	}


	// =========================================================================
	// ordersupplierdispatch — same location hints on the dispatch page
	// =========================================================================

	/**
	 * Hook: printFieldListValue — inject bin location column on dispatch page
	 *
	 * Adds a "Bin Location" column to the dispatch line table showing where
	 * each product lives (or should go) in the selected warehouse.
	 *
	 * @param  array   $parameters Hook parameters (includes objp, suffix, i, j)
	 * @param  object  $object     The Reception/SupplierOrder object
	 * @param  string  $action     Current action
	 * @return int
	 */
	public function printFieldListValue($parameters, &$object, &$action)
	{
		global $langs;

		if (!in_array('ordersupplierdispatch', explode(':', $parameters['currentcontext']))) {
			return 0;
		}

		// Only output on the first call per row (when j == 0 or first iteration)
		if (isset($parameters['j']) && $parameters['j'] > 0) {
			return 0;
		}

		$objp = isset($parameters['objp']) ? $parameters['objp'] : null;
		if (!$objp || empty($objp->fk_product)) {
			print '<td></td>';
			return 0;
		}

		dol_include_once('/binloc/lib/binloc.lib.php');
		$langs->load('binloc@binloc');

		$suffix = isset($parameters['suffix']) ? $parameters['suffix'] : '';
		$fk_entrepot = GETPOSTINT('entrepot'.$suffix);
		if (empty($fk_entrepot) && isset($objp->fk_entrepot)) {
			$fk_entrepot = (int) $objp->fk_entrepot;
		}

		$location_str = '';
		if ($fk_entrepot > 0) {
			$location_str = binloc_format_location($this->db, (int) $objp->fk_product, $fk_entrepot);
		}

		print '<td class="small">';
		if (!empty($location_str)) {
			print dol_escape_htmltag($location_str);
		} else {
			print '<span class="opacitymedium">&mdash;</span>';
		}
		print '</td>';

		return 0;
	}

	// =========================================================================
	// productlotcard — inline bin location display and edit on lot/serial card
	// =========================================================================

	/**
	 * Hook: doActions — handle location save/delete on the lot card
	 *
	 * @param  array   $parameters Hook parameters
	 * @param  object  $object     The Productlot object
	 * @param  string  $action     Current action
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action)
	{
		global $user;

		if (!in_array('productlotcard', explode(':', $parameters['currentcontext']))) {
			return 0;
		}

		if (!$user->hasRight('binloc', 'write')) {
			return 0;
		}

		dol_include_once('/binloc/class/binlocproductlocation.class.php');

		if ($action === 'binlocsavelotloc') {
			$loc_fk_entrepot = GETPOSTINT('binloc_fk_entrepot');
			if ($loc_fk_entrepot > 0 && !empty($object->id)) {
				$loc = new BinlocProductLocation($this->db);
				$loc->fk_product     = $object->fk_product;
				$loc->fk_entrepot    = $loc_fk_entrepot;
				$loc->fk_product_lot = $object->id;
				for ($i = 1; $i <= 6; $i++) {
					$loc->{'level'.$i.'_value'} = GETPOST('binloc_level'.$i, 'alphanohtml');
				}
				$loc->note = GETPOST('binloc_note', 'alphanohtml');

				$result = $loc->createOrUpdate($user);
				if ($result > 0) {
					setEventMessages($GLOBALS['langs']->trans('LocationSaved'), null, 'mesgs');
				} else {
					setEventMessages($loc->error, null, 'errors');
				}
			}
			$action = '';
		}

		if ($action === 'binlocdeletelotloc') {
			$loc_id = GETPOSTINT('binloc_loc_id');
			if ($loc_id > 0) {
				$loc = new BinlocProductLocation($this->db);
				$loc->fetch($loc_id);
				$result = $loc->delete($user);
				if ($result > 0) {
					setEventMessages($GLOBALS['langs']->trans('LocationRemoved'), null, 'mesgs');
				} else {
					setEventMessages($loc->error, null, 'errors');
				}
			}
			$action = '';
		}

		return 0;
	}

	/**
	 * Hook: addMoreActionsButtons — render bin location inline on the lot/serial card
	 *
	 * Shows the lot's bin location(s) as a compact panel between the card
	 * and the action buttons. Includes inline edit and add capabilities.
	 *
	 * @param  array   $parameters Hook parameters
	 * @param  object  $object     The Productlot object
	 * @param  string  $action     Current action
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $langs, $user, $db;

		if (!in_array('productlotcard', explode(':', $parameters['currentcontext']))) {
			return 0;
		}
		if (empty($object->id) || $object->id <= 0) {
			return 0;
		}

		dol_include_once('/binloc/lib/binloc.lib.php');
		dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
		dol_include_once('/binloc/class/binlocproductlocation.class.php');
		$langs->load('binloc@binloc');

		$levelObj = new BinlocWarehouseLevel($this->db);

		// Fetch locations for this specific lot
		$sql = "SELECT pl.rowid, pl.fk_entrepot, e.ref as warehouse_ref, e.lieu as warehouse_label,";
		$sql .= " pl.level1_value, pl.level2_value, pl.level3_value,";
		$sql .= " pl.level4_value, pl.level5_value, pl.level6_value,";
		$sql .= " pl.note";
		$sql .= " FROM ".MAIN_DB_PREFIX."binloc_product_location as pl";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = pl.fk_entrepot";
		$sql .= " WHERE pl.fk_product_lot = ".(int) $object->id;
		$sql .= " AND pl.entity IN (".getEntity('stock').")";
		$sql .= " ORDER BY e.ref ASC";

		$resql = $this->db->query($sql);
		$locations = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$locations[] = $obj;
			}
			$this->db->free($resql);
		}

		$binloc_mode = GETPOST('binloc_mode', 'aZ09');
		$edit_wh  = ($binloc_mode === 'edit') ? GETPOSTINT('binloc_edit_wh') : 0;
		$add_mode = ($binloc_mode === 'add');

		// ---- Render panel ----
		print '</div>'; // Close the tabsAction div early so we render before action buttons

		print '<div class="fichecenter" style="margin-bottom:12px;">';
		print '<div class="underbanner clearboth">';
		print '<strong>'.img_picto('', 'stock', 'class="pictofixedwidth"').$langs->trans('BinLocations').'</strong>';
		print '</div>';

		if (!empty($locations)) {
			foreach ($locations as $loc) {
				$wh_levels = $levelObj->fetchByWarehouse($loc->fk_entrepot);
				$is_editing = ($edit_wh == $loc->fk_entrepot);
				$wh_url = dol_buildpath('/product/stock/card.php?id='.$loc->fk_entrepot, 1);

				print '<div style="padding:6px 8px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">';

				if ($is_editing && $user->hasRight('binloc', 'write')) {
					// Edit form
					print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" style="display:flex; flex-wrap:wrap; gap:6px; align-items:end; flex:1;">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="action" value="binlocsavelotloc">';
					print '<input type="hidden" name="binloc_fk_entrepot" value="'.$loc->fk_entrepot.'">';

					print '<span><strong><a href="'.$wh_url.'">'.dol_escape_htmltag($loc->warehouse_ref).'</a></strong></span>';

					foreach ($wh_levels as $num => $cfg) {
						$val = $loc->{'level'.$num.'_value'};
						print '<span>';
						print '<span class="opacitymedium small">'.dol_escape_htmltag($cfg->label).':</span> ';
						print binloc_render_level_input($cfg, 'binloc_level'.$num, $val, 'flat', 'style="width:70px;"');
						print '</span>';
					}
					print '<span>';
					print '<span class="opacitymedium small">'.$langs->trans('LocationNote').':</span> ';
					print '<input type="text" name="binloc_note" class="flat" style="width:80px;" value="'.dol_escape_htmltag($loc->note).'">';
					print '</span>';

					print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
					print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
					print '</form>';
				} else {
					// Display
					print '<div>';
					print '<strong><a href="'.$wh_url.'">'.dol_escape_htmltag($loc->warehouse_ref).'</a></strong>';
					if ($loc->warehouse_label) {
						print ' <span class="opacitymedium small">'.dol_escape_htmltag($loc->warehouse_label).'</span>';
					}
					print ' &mdash; ';

					if (!empty($wh_levels)) {
						$parts = array();
						foreach ($wh_levels as $num => $cfg) {
							$val = $loc->{'level'.$num.'_value'};
							if ($val !== null && $val !== '') {
								$parts[] = dol_escape_htmltag($cfg->label).': <strong>'.dol_escape_htmltag($val).'</strong>';
							}
						}
						print implode(' / ', $parts);
						if ($loc->note) {
							print ' <span class="opacitymedium small">('.dol_escape_htmltag($loc->note).')</span>';
						}
					} else {
						print '<span class="opacitymedium">'.$langs->trans('NoLevelsConfigured').'</span>';
					}
					print '</div>';

					if ($user->hasRight('binloc', 'write')) {
						print '<div class="nowraponall">';
						print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&binloc_mode=edit&binloc_edit_wh='.$loc->fk_entrepot.'">';
						print img_picto($langs->trans('EditLocation'), 'edit');
						print '</a> ';
						print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=binlocdeletelotloc&binloc_loc_id='.$loc->rowid.'&token='.newToken().'"';
						print ' onclick="return confirm(\''.dol_escape_js($langs->trans('ConfirmRemoveLocation', $loc->warehouse_ref)).'\');">';
						print img_picto($langs->trans('RemoveLocation'), 'delete');
						print '</a>';
						print '</div>';
					}
				}

				print '</div>';
			}
		} elseif (!$add_mode) {
			print '<div class="opacitymedium" style="padding:6px 8px;">'.$langs->trans('NoLocationsFound').'</div>';
		}

		// Add form
		if ($add_mode && $user->hasRight('binloc', 'write')) {
			$add_wh = GETPOSTINT('binloc_add_wh');
			$warehouses = binloc_get_warehouses($this->db);

			$existing_wh_ids = array();
			foreach ($locations as $loc) {
				$existing_wh_ids[$loc->fk_entrepot] = true;
			}

			print '<div style="padding:6px 8px; background:#f8fff8; border:1px solid #9c9; border-radius:3px; margin-top:4px;">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="binlocsavelotloc">';

			print '<div style="display:flex; flex-wrap:wrap; gap:6px; align-items:end;">';
			print '<span>';
			print '<span class="opacitymedium small">'.$langs->trans('Warehouse').':</span> ';
			print '<select name="binloc_fk_entrepot" class="flat minwidth150" onchange="window.location.href=\''.$_SERVER['PHP_SELF'].'?id='.$object->id.'&binloc_mode=add&binloc_add_wh=\'+this.value">';
			print '<option value="0">'.$langs->trans('SelectWarehouse').'</option>';
			foreach ($warehouses as $wh) {
				if (isset($existing_wh_ids[$wh->rowid])) {
					continue;
				}
				$sel = ($add_wh == $wh->rowid) ? ' selected' : '';
				print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref).'</option>';
			}
			print '</select>';
			print '</span>';

			if ($add_wh > 0) {
				$add_levels = $levelObj->fetchByWarehouse($add_wh);
				if (!empty($add_levels)) {
					foreach ($add_levels as $num => $cfg) {
						print '<span>';
						print '<span class="opacitymedium small">'.dol_escape_htmltag($cfg->label).':</span> ';
						print binloc_render_level_input($cfg, 'binloc_level'.$num, '', 'flat', 'style="width:70px;"');
						print '</span>';
					}
					print '<span>';
					print '<span class="opacitymedium small">'.$langs->trans('LocationNote').':</span> ';
					print '<input type="text" name="binloc_note" class="flat" style="width:80px;">';
					print '</span>';
					print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
				} else {
					print '<span class="opacitymedium">'.$langs->trans('NoLevelsConfigured').'</span>';
				}
			}

			print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" class="button smallpaddingimp">'.$langs->trans('Cancel').'</a>';
			print '</div>';
			print '</form>';
			print '</div>';
		}

		// Add button — hidden when serial already has a location (one serial = one location)
		if (!$add_mode && $user->hasRight('binloc', 'write') && empty($locations)) {
			print '<div style="padding:6px 8px;">';
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&binloc_mode=add" class="button smallpaddingimp">';
			print img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('AddLocation');
			print '</a>';
			print '</div>';
		}

		print '</div>';

		// Reopen the tabsAction div that we closed early
		print '<div class="tabsAction">';

		return 0;
	}

	/**
	 * Hook: printFieldListTitle — add "Bin Location" column header on dispatch page
	 *
	 * @param  array   $parameters Hook parameters
	 * @param  object  $object     Object
	 * @param  string  $action     Current action
	 * @return int
	 */
	public function printFieldListTitle($parameters, &$object, &$action)
	{
		global $langs;

		if (!in_array('ordersupplierdispatch', explode(':', $parameters['currentcontext']))) {
			return 0;
		}

		$langs->load('binloc@binloc');
		print '<td>'.$langs->trans('BinLocations').'</td>';

		return 0;
	}
}
