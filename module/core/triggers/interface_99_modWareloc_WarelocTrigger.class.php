<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    core/triggers/interface_99_modWareloc_WarelocTrigger.class.php
 * \ingroup wareloc
 * \brief   Trigger class for wareloc module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceWarelocTrigger
 */
class InterfaceWarelocTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'product';
		$this->description = 'Triggers for wareloc module';
		$this->version = '1.2.0';
		$this->picto = 'stock';
	}

	/**
	 * Return name of trigger
	 *
	 * @return string  Name
	 */
	public function getName()
	{
		return 'WarelocTrigger';
	}

	/**
	 * Return description of trigger
	 *
	 * @return string  Description
	 */
	public function getDesc()
	{
		return 'Triggers for wareloc module';
	}

	/**
	 * Return version of trigger
	 *
	 * @return string  Version
	 */
	public function getVersion()
	{
		return $this->version;
	}

	/**
	 * Run trigger
	 *
	 * @param  string    $action  Trigger action
	 * @param  Object    $object  Object
	 * @param  User      $user    User
	 * @param  Translate $langs   Languages
	 * @param  Conf      $conf    Configuration
	 * @return int                0=OK, -1=error
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Own object triggers
		switch ($action) {
			case 'WARELOC_PRODUCTLOCATION_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'WARELOC_PRODUCTLOCATION_VALIDATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'WARELOC_PRODUCTLOCATION_MODIFY':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;

			case 'WARELOC_PRODUCTLOCATION_DELETE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		// Native module triggers
		switch ($action) {
			case 'RECEPTION_VALIDATE':
				if (!getDolGlobalInt('WARELOC_AUTO_ASSIGN_ON_RECEPTION')) {
					break;
				}

				$this->_handleReceptionValidate($object, $user, $conf);
				break;
		}

		return 0;
	}

	/**
	 * Auto-create ProductLocation records when a reception is validated
	 *
	 * @param  Object $reception  Reception object
	 * @param  User   $user       User
	 * @param  Conf   $conf       Configuration
	 * @return void
	 */
	private function _handleReceptionValidate($reception, $user, $conf)
	{
		dol_include_once('/wareloc/class/productlocation.class.php');
		dol_include_once('/wareloc/lib/wareloc.lib.php');

		$default_status = getDolGlobalInt('WARELOC_DEFAULT_STATUS_ON_RECEPTION', 1);

		// Retrieve location data from session (set by our hook's doActions)
		$session_key = 'wareloc_reception_'.$reception->id;
		$session_data = isset($_SESSION[$session_key]) ? $_SESSION[$session_key] : array();

		// Get the warehouse from the reception
		$warehouse_id = 0;
		if (!empty($reception->fk_entrepot)) {
			$warehouse_id = (int) $reception->fk_entrepot;
		}

		if ($warehouse_id <= 0) {
			return;
		}

		$levels = wareloc_get_active_levels(null, $warehouse_id);
		if (empty($levels)) {
			return;
		}

		// Fetch reception lines if not already loaded
		if (empty($reception->lines)) {
			if (method_exists($reception, 'fetch_lines')) {
				$reception->fetch_lines();
			}
		}

		if (empty($reception->lines)) {
			return;
		}

		foreach ($reception->lines as $line) {
			$product_id = 0;
			if (!empty($line->fk_product) && $line->fk_product > 0) {
				$product_id = (int) $line->fk_product;
			}

			if ($product_id <= 0) {
				continue;
			}

			$qty = !empty($line->qty) ? (float) $line->qty : 0;
			if ($qty <= 0) {
				continue;
			}

			// Determine level values: session overrides > product defaults
			$level_values = array();
			if (!empty($session_data)) {
				$level_values = $session_data;
			} else {
				$level_values = ProductLocation::fetchDefaultForProduct($product_id, $warehouse_id);
			}

			// Skip if no location data available
			$has_location = false;
			foreach ($level_values as $v) {
				if (!empty($v)) {
					$has_location = true;
					break;
				}
			}
			if (!$has_location) {
				continue;
			}

			// Create ProductLocation record
			$loc = new ProductLocation($this->db);
			$loc->fk_product = $product_id;
			$loc->fk_entrepot = $warehouse_id;
			$loc->qty = $qty;
			$loc->is_default = 0;
			$loc->fk_reception = $reception->id;

			for ($i = 1; $i <= 6; $i++) {
				$key = 'level_'.$i;
				$loc->$key = isset($level_values[$key]) ? $level_values[$key] : null;
			}

			$result = $loc->create($user, 1); // notrigger=1 to avoid loops
			if ($result > 0 && $default_status == 1) {
				$loc->validate($user, 1); // notrigger=1
			}
		}

		// Clean up session data
		if (isset($_SESSION[$session_key])) {
			unset($_SESSION[$session_key]);
		}
	}
}
