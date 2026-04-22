<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    class/binlocwarehouselevel.class.php
 * \ingroup binloc
 * \brief   Business class for warehouse level configuration
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class BinlocWarehouseLevel
 *
 * Manages per-warehouse level definitions (e.g. Level 1 = "Row", Level 2 = "Bay").
 */
class BinlocWarehouseLevel extends CommonObject
{
	/** @var string */
	public $element = 'binlocwarehouselevel';

	/** @var string */
	public $table_element = 'binloc_warehouse_levels';

	/** @var int */
	public $fk_entrepot;

	/** @var int */
	public $level_num;

	/** @var string */
	public $label;

	/** @var string Input type: 'text' | 'number' | 'list' */
	public $datatype = 'text';

	/** @var string Comma-separated allowed values for datatype='list' */
	public $list_values;

	/** @var int */
	public $active = 1;

	/** @var int */
	public $fk_user_creat;

	/** @var int */
	public $fk_user_modif;

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
	 * Create a level record
	 *
	 * @param  User $user User performing action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function create($user)
	{
		$now = dol_now();

		$datatype = in_array($this->datatype, array('text', 'number', 'list'), true) ? $this->datatype : 'text';

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "entity, fk_entrepot, level_num, label, datatype, list_values, active, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= (int) getEntity('stock');
		$sql .= ", ".(int) $this->fk_entrepot;
		$sql .= ", ".(int) $this->level_num;
		$sql .= ", '".$this->db->escape($this->label)."'";
		$sql .= ", '".$this->db->escape($datatype)."'";
		$sql .= ", ".($this->list_values ? "'".$this->db->escape($this->list_values)."'" : "NULL");
		$sql .= ", ".(int) $this->active;
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".(int) $user->id;
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->fk_user_creat = $user->id;

		return $this->id;
	}

	/**
	 * Fetch all level definitions for a warehouse
	 *
	 * Returns a map keyed by level_num where each value is an stdClass with:
	 *   - label       (string)
	 *   - datatype    ('text' | 'number' | 'list')
	 *   - list_values (string|null, raw comma-separated)
	 *   - options     (array, parsed from list_values, empty if not list type)
	 *
	 * For backward compatibility with older code that treated the returned
	 * value as `label`, callers should reference `->label` explicitly.
	 *
	 * @param  int   $fk_entrepot Warehouse ID
	 * @return array              level_num => stdClass map, or empty array
	 */
	public function fetchByWarehouse($fk_entrepot)
	{
		$levels = array();

		$sql = "SELECT level_num, label, datatype, list_values FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_entrepot = ".(int) $fk_entrepot;
		$sql .= " AND entity IN (".getEntity('stock').")";
		$sql .= " AND active = 1";
		$sql .= " ORDER BY level_num ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $levels;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$cfg = new stdClass();
			$cfg->label       = $obj->label;
			$cfg->datatype    = in_array($obj->datatype, array('text', 'number', 'list'), true) ? $obj->datatype : 'text';
			$cfg->list_values = $obj->list_values;
			$cfg->options     = array();
			if ($cfg->datatype === 'list' && !empty($obj->list_values)) {
				$parts = explode(',', $obj->list_values);
				foreach ($parts as $p) {
					$p = trim($p);
					if ($p !== '') {
						$cfg->options[] = $p;
					}
				}
			}
			$levels[(int) $obj->level_num] = $cfg;
		}
		$this->db->free($resql);

		return $levels;
	}

	/**
	 * Fetch just the label map (backward-compatibility helper)
	 *
	 * Returns a simple level_num => label string map, dropping datatype info.
	 *
	 * @param  int   $fk_entrepot Warehouse ID
	 * @return array              level_num => label string map
	 */
	public function fetchLabelsByWarehouse($fk_entrepot)
	{
		$labels = array();
		foreach ($this->fetchByWarehouse($fk_entrepot) as $num => $cfg) {
			$labels[$num] = $cfg->label;
		}
		return $labels;
	}

	/**
	 * Get the number of configured levels for a warehouse
	 *
	 * @param  int $fk_entrepot Warehouse ID
	 * @return int              Number of active levels (0-6)
	 */
	public function getMaxLevel($fk_entrepot)
	{
		$levels = $this->fetchByWarehouse($fk_entrepot);
		return count($levels);
	}

	/**
	 * Delete all level definitions for a warehouse
	 *
	 * @param  int $fk_entrepot Warehouse ID
	 * @return int              >0 if OK, <0 if KO
	 */
	public function deleteByWarehouse($fk_entrepot)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_entrepot = ".(int) $fk_entrepot;
		$sql .= " AND entity IN (".getEntity('stock').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Save a complete set of levels for a warehouse (delete + re-insert)
	 *
	 * Accepts either:
	 *   - A flat level_num => label string map (legacy), or
	 *   - A level_num => array('label' => ..., 'datatype' => ..., 'list_values' => ...) map
	 *
	 * @param  int   $fk_entrepot Warehouse ID
	 * @param  array $levels      level_num => config array or label string
	 * @param  User  $user        User performing action
	 * @return int                >0 if OK, <0 if KO
	 */
	public function saveWarehouseLevels($fk_entrepot, $levels, $user)
	{
		$this->db->begin();

		$result = $this->deleteByWarehouse($fk_entrepot);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		foreach ($levels as $num => $cfg) {
			if (is_array($cfg)) {
				$label       = isset($cfg['label']) ? trim($cfg['label']) : '';
				$datatype    = isset($cfg['datatype']) ? $cfg['datatype'] : 'text';
				$list_values = isset($cfg['list_values']) ? trim($cfg['list_values']) : '';
			} else {
				$label       = trim((string) $cfg);
				$datatype    = 'text';
				$list_values = '';
			}

			if ($label === '') {
				continue;
			}

			$this->fk_entrepot = $fk_entrepot;
			$this->level_num   = (int) $num;
			$this->label       = $label;
			$this->datatype    = in_array($datatype, array('text', 'number', 'list'), true) ? $datatype : 'text';
			$this->list_values = ($this->datatype === 'list' && $list_values !== '') ? $list_values : null;
			$this->active      = 1;
			$this->id          = 0;

			$result = $this->create($user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Copy level configuration from one warehouse to another
	 *
	 * @param  int  $source_fk_entrepot Source warehouse ID
	 * @param  int  $target_fk_entrepot Target warehouse ID
	 * @param  User $user               User performing action
	 * @return int                       >0 if OK, <0 if KO
	 */
	public function copyFromWarehouse($source_fk_entrepot, $target_fk_entrepot, $user)
	{
		$source_levels = $this->fetchByWarehouse($source_fk_entrepot);
		if (empty($source_levels)) {
			$this->error = 'No levels configured on source warehouse';
			return -1;
		}

		// Convert stdClass objects from fetchByWarehouse into the array shape
		// expected by saveWarehouseLevels
		$payload = array();
		foreach ($source_levels as $num => $cfg) {
			$payload[$num] = array(
				'label'       => $cfg->label,
				'datatype'    => $cfg->datatype,
				'list_values' => $cfg->list_values,
			);
		}

		return $this->saveWarehouseLevels($target_fk_entrepot, $payload, $user);
	}
}
