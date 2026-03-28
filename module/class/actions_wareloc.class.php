<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    class/actions_wareloc.class.php
 * \ingroup wareloc
 * \brief   Hook actions for wareloc module
 *
 * Hooks into: elementproperties, productcard, receptioncard, commonobject
 */

/**
 * Class ActionsWareloc
 */
class ActionsWareloc
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array Results array for hook returns
	 */
	public $results = array();

	/**
	 * @var string HTML output for hook injection
	 */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Register element properties for ProductLocation
	 *
	 * @param  array  $parameters  Hook parameters
	 * @param  object $object      Current object
	 * @param  string $action      Action code
	 * @param  object $hookmanager Hook manager
	 * @return int                 0=continue
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? $parameters['elementType'] : '';

		if ($elementType === 'productlocation' || $elementType === 'wareloc_productlocation' || $elementType === 'wareloc') {
			$this->results = array(
				'module'        => 'wareloc',
				'element'       => 'productlocation',
				'table_element' => 'wareloc_product_location',
				'subelement'    => 'productlocation',
				'classpath'     => 'wareloc/class',
				'classfile'     => 'productlocation',
				'classname'     => 'ProductLocation',
			);
		}

		return 0;
	}

	/**
	 * Inject "Link to ProductLocation" into the link dropdown
	 *
	 * @param  array  $parameters  Hook parameters
	 * @param  object $object      Current object
	 * @param  string $action      Action code
	 * @param  object $hookmanager Hook manager
	 * @return int                 0=continue
	 */
	public function showLinkToObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $user;

		if (!isModEnabled('wareloc')) {
			return 0;
		}

		if (!$user->hasRight('wareloc', 'productlocation', 'read')) {
			return 0;
		}

		$this->results = array();

		$this->results['productlocation'] = array(
			'enabled' => 1,
			'perms'   => 1,
			'label'   => 'LinkToProductLocation',
			'sql'     => "SELECT t.rowid, t.ref"
				." FROM ".MAIN_DB_PREFIX."wareloc_product_location as t"
				." WHERE t.entity IN (".getEntity('productlocation').")"
				." AND t.status IN (0, 1)"
				." ORDER BY t.ref DESC",
		);

		return 0;
	}

	/**
	 * Inject location fields onto product card and reception card
	 *
	 * @param  array  $parameters  Hook parameters
	 * @param  object $object      Current object
	 * @param  string $action      Action code
	 * @param  object $hookmanager Hook manager
	 * @return int                 0=continue
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $db, $user, $conf;

		if (!isModEnabled('wareloc')) {
			return 0;
		}

		// ---- PRODUCT CARD HOOK ----
		if (isset($object->element) && $object->element === 'product') {
			if (!$user->hasRight('wareloc', 'productlocation', 'read')) {
				return 0;
			}

			$langs->load('wareloc@wareloc');
			dol_include_once('/wareloc/lib/wareloc.lib.php');

			$levels = wareloc_get_active_levels();
			if (empty($levels)) {
				return 0;
			}

			// Only show on existing products (not during create)
			if (empty($object->id)) {
				return 0;
			}

			// Fetch existing defaults for this product
			$defaults = array();
			$sql = "SELECT rowid, fk_entrepot, level_1, level_2, level_3, level_4, level_5, level_6";
			$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_product_default";
			$sql .= " WHERE fk_product = ".((int) $object->id);
			$sql .= " AND entity IN (".getEntity('wareloc_product_default').")";
			$sql .= " ORDER BY fk_entrepot";

			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$row = array('fk_entrepot' => $obj->fk_entrepot);
					for ($i = 1; $i <= 6; $i++) {
						$row['level_'.$i] = $obj->{'level_'.$i};
					}
					$defaults[] = $row;
				}
				$db->free($resql);
			}

			$html = '';

			if ($action === 'edit') {
				// EDIT MODE: render editable location defaults
				require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
				$entrepot = new Entrepot($db);
				$warehouses = $entrepot->list_array();

				$html .= '<tr class="oddeven"><td class="titlefieldcreate" colspan="2">';
				$html .= '<strong>'.$langs->trans('DefaultLocations').'</strong>';
				$html .= ' <span class="opacitymedium small">('.$langs->trans('DefaultLocationDesc').')</span>';
				$html .= '</td></tr>';

				// Render existing defaults
				$idx = 0;
				if (!empty($defaults)) {
					foreach ($defaults as $def) {
						$html .= $this->_renderProductDefaultRow($idx, $warehouses, $levels, $def);
						$idx++;
					}
				}

				// Empty row for adding new default
				$html .= $this->_renderProductDefaultRow($idx, $warehouses, $levels, array());
				$html .= '<tr class="oddeven"><td colspan="2">';
				$html .= '<a href="#" onclick="warelocAddRow(); return false;" class="butAction small">'.$langs->trans('AddWarehouseDefault').'</a>';
				$html .= '</td></tr>';

				// JavaScript for adding rows
				$html .= '<script>';
				$html .= 'var warelocRowIdx = '.($idx + 1).';';
				$html .= 'function warelocAddRow() {';
				$html .= '  var tmpl = document.getElementById("wareloc_row_template");';
				$html .= '  if (tmpl) { var clone = tmpl.cloneNode(true); clone.id = "wareloc_row_" + warelocRowIdx;';
				$html .= '  clone.style.display = ""; clone.innerHTML = clone.innerHTML.replace(/_IDX_/g, warelocRowIdx);';
				$html .= '  tmpl.parentNode.insertBefore(clone, tmpl); warelocRowIdx++; }';
				$html .= '}';
				$html .= '</script>';
			} else {
				// VIEW MODE: show defaults as compact read-only list
				if (!empty($defaults)) {
					require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

					$html .= '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DefaultLocations').'</td><td>';
					foreach ($defaults as $def) {
						$wh = new Entrepot($db);
						if ($wh->fetch($def['fk_entrepot']) > 0) {
							$wh_label = $wh->label;
						} else {
							$wh_label = '#'.$def['fk_entrepot'];
						}
						$loc_label = wareloc_build_location_label($def, $levels);
						$html .= '<div class="margintoponly">';
						$html .= '<strong>'.dol_escape_htmltag($wh_label).':</strong> ';
						$html .= ($loc_label ? $loc_label : '<span class="opacitymedium">'.$langs->trans('NoDefaultLocation').'</span>');
						$html .= '</div>';
					}
					$html .= '</td></tr>';
				}
			}

			$this->resprints = $html;
			return 0;
		}

		// ---- RECEPTION CARD HOOK ----
		if (isset($object->element) && $object->element === 'reception') {
			if (!$user->hasRight('wareloc', 'productlocation', 'read')) {
				return 0;
			}

			$langs->load('wareloc@wareloc');
			dol_include_once('/wareloc/lib/wareloc.lib.php');

			$levels = wareloc_get_active_levels();
			if (empty($levels)) {
				return 0;
			}

			// Inject location fields for reception
			$html = '';
			$html .= '<tr class="oddeven"><td class="titlefieldcreate" colspan="2">';
			$html .= '<strong>'.$langs->trans('LocationAtReception').'</strong>';
			$html .= ' <span class="opacitymedium small">('.$langs->trans('LocationAtReceptionDesc').')</span>';
			$html .= '</td></tr>';

			// Get the warehouse from the reception if set
			$warehouse_id = 0;
			if (!empty($object->fk_entrepot) && $object->fk_entrepot > 0) {
				$warehouse_id = $object->fk_entrepot;
			}

			// Try to pre-fill from session if returning after a save
			$session_key = 'wareloc_reception_'.$object->id;
			$session_vals = isset($_SESSION[$session_key]) ? $_SESSION[$session_key] : array();

			// Pre-fill from product defaults if we have a warehouse and product
			$prefill = array();
			if ($warehouse_id > 0 && !empty($object->lines)) {
				// Use the first product's default as a starting point
				foreach ($object->lines as $line) {
					if (!empty($line->fk_product) && $line->fk_product > 0) {
						dol_include_once('/wareloc/class/productlocation.class.php');
						$prefill = ProductLocation::fetchDefaultForProduct($line->fk_product, $warehouse_id);
						break;
					}
				}
			}

			// Merge session values over defaults
			$values = array_merge($prefill, $session_vals);

			$html .= wareloc_render_level_fields($levels, $values, 'wareloc_reception', ($action === 'create' || $action === 'edit') ? 'edit' : 'view');

			$this->resprints = $html;
			return 0;
		}

		return 0;
	}

	/**
	 * Handle form submissions from hooked pages
	 *
	 * @param  array  $parameters  Hook parameters
	 * @param  object $object      Current object
	 * @param  string $action      Action code
	 * @param  object $hookmanager Hook manager
	 * @return int                 0=continue
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $db;

		if (!isModEnabled('wareloc')) {
			return 0;
		}

		// ---- PRODUCT CARD: Save default locations ----
		if (isset($object->element) && $object->element === 'product' && $action === 'update') {
			if (!$user->hasRight('wareloc', 'productlocation', 'write')) {
				return 0;
			}

			$product_id = $object->id;
			if (empty($product_id)) {
				return 0;
			}

			// Delete existing defaults for this product in this entity
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."wareloc_product_default";
			$sql .= " WHERE fk_product = ".((int) $product_id);
			$sql .= " AND entity = ".((int) $conf->entity);
			$db->query($sql);

			// Insert new defaults from POST arrays
			$entrepots = GETPOST('wareloc_entrepot', 'array');
			if (!empty($entrepots) && is_array($entrepots)) {
				$now = dol_now();
				foreach ($entrepots as $idx => $entrepot_id) {
					$entrepot_id = (int) $entrepot_id;
					if ($entrepot_id <= 0) {
						continue;
					}

					// Check if any level value is populated
					$has_value = false;
					$level_vals = array();
					for ($i = 1; $i <= 6; $i++) {
						$arr = GETPOST('wareloc_'.$idx.'_level_'.$i, 'array');
						$val = isset($arr[$idx]) ? trim($arr[$idx]) : '';
						// Also try direct naming
						if ($val === '') {
							$val = trim(GETPOST('wareloc_'.$idx.'_level_'.$i, 'alpha'));
						}
						$level_vals[$i] = $val;
						if ($val !== '') {
							$has_value = true;
						}
					}

					if (!$has_value) {
						continue;
					}

					$sql = "INSERT INTO ".MAIN_DB_PREFIX."wareloc_product_default (";
					$sql .= "entity, fk_product, fk_entrepot";
					$sql .= ", level_1, level_2, level_3, level_4, level_5, level_6";
					$sql .= ", date_creation, fk_user_creat";
					$sql .= ") VALUES (";
					$sql .= ((int) $conf->entity);
					$sql .= ", ".((int) $product_id);
					$sql .= ", ".((int) $entrepot_id);
					for ($i = 1; $i <= 6; $i++) {
						$sql .= ", ".($level_vals[$i] !== '' ? "'".$db->escape($level_vals[$i])."'" : "NULL");
					}
					$sql .= ", '".$db->idate($now)."'";
					$sql .= ", ".((int) $user->id);
					$sql .= ")";
					$db->query($sql);
				}
			}

			return 0;
		}

		// ---- RECEPTION CARD: Store location data for trigger ----
		if (isset($object->element) && $object->element === 'reception') {
			if (!$user->hasRight('wareloc', 'productlocation', 'write')) {
				return 0;
			}

			// Store level values in session for the trigger to pick up
			$levels_data = array();
			for ($i = 1; $i <= 6; $i++) {
				$val = GETPOST('wareloc_reception_level_'.$i, 'alpha');
				if ($val !== '') {
					$levels_data['level_'.$i] = $val;
				}
			}

			if (!empty($levels_data) && !empty($object->id)) {
				$_SESSION['wareloc_reception_'.$object->id] = $levels_data;
			}

			return 0;
		}

		return 0;
	}

	/**
	 * Render a single product default location row for the edit form
	 *
	 * @param  int    $idx         Row index
	 * @param  array  $warehouses  Array of warehouse id => label
	 * @param  array  $levels      Active level configs
	 * @param  array  $values      Current values (fk_entrepot, level_1..level_6)
	 * @return string              HTML
	 */
	private function _renderProductDefaultRow($idx, $warehouses, $levels, $values)
	{
		$is_template = empty($values);
		$html = '';

		$style = $is_template ? ' id="wareloc_row_template" style="display:none"' : ' id="wareloc_row_'.$idx.'"';
		$name_idx = $is_template ? '_IDX_' : $idx;

		// Warehouse selector
		$html .= '<tr class="oddeven"'.$style.'>';
		$html .= '<td class="titlefieldcreate fieldrequired">';
		$html .= '<select name="wareloc_entrepot['.$name_idx.']" class="flat minwidth200">';
		$html .= '<option value="">--- Warehouse ---</option>';
		if (is_array($warehouses)) {
			foreach ($warehouses as $wid => $wlabel) {
				$sel = (isset($values['fk_entrepot']) && $values['fk_entrepot'] == $wid) ? ' selected' : '';
				$html .= '<option value="'.$wid.'"'.$sel.'>'.dol_escape_htmltag($wlabel).'</option>';
			}
		}
		$html .= '</select>';
		$html .= '</td><td>';

		// Level fields inline
		foreach ($levels as $level) {
			$key = 'level_'.$level->position;
			$name = 'wareloc_'.$name_idx.'_'.$key;
			$val = isset($values[$key]) ? $values[$key] : '';
			$req = $level->required ? ' *' : '';

			$html .= '<span class="nowraponall marginrightonly">';
			$html .= '<label class="small">'.dol_escape_htmltag($level->label).$req.': </label>';

			switch ($level->datatype) {
				case 'list':
					$items = array_map('trim', explode(',', $level->list_values));
					$html .= '<select name="'.$name.'" class="flat maxwidth100">';
					$html .= '<option value="">&nbsp;</option>';
					foreach ($items as $item) {
						if ($item === '') continue;
						$sel = ($val === $item) ? ' selected' : '';
						$html .= '<option value="'.dol_escape_htmltag($item).'"'.$sel.'>'.dol_escape_htmltag($item).'</option>';
					}
					$html .= '</select>';
					break;
				case 'integer':
					$html .= '<input type="number" name="'.$name.'" class="flat maxwidth75" value="'.dol_escape_htmltag($val).'">';
					break;
				default:
					$html .= '<input type="text" name="'.$name.'" class="flat maxwidth100" value="'.dol_escape_htmltag($val).'">';
					break;
			}

			$html .= '</span>';
		}

		$html .= '</td></tr>';

		return $html;
	}
}
