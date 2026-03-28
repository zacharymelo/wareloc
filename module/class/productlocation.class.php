<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    class/productlocation.class.php
 * \ingroup wareloc
 * \brief   Class for ProductLocation — sub-warehouse product placement
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class ProductLocation
 *
 * Tracks where a product is stored within a warehouse at a granular level
 * (Row, Bay, Shelf, Bin — configurable via llx_wareloc_level).
 */
class ProductLocation extends CommonObject
{
	/**
	 * @var string Trigger prefix for call_trigger()
	 */
	public $TRIGGER_PREFIX = 'WARELOC_PRODUCTLOCATION';

	/**
	 * @var string Module name — REQUIRED for getElementType() to return prefixed name
	 */
	public $module = 'wareloc';

	/**
	 * @var string Element type — simple lowercase, no prefix
	 */
	public $element = 'productlocation';

	/**
	 * @var string Table name without llx_ prefix
	 */
	public $table_element = 'wareloc_product_location';

	/**
	 * @var string Icon
	 */
	public $picto = 'stock';

	// Status constants — SMALLINT values, NEVER ENUM
	const STATUS_DRAFT     = 0;
	const STATUS_ACTIVE    = 1;
	const STATUS_MOVED     = 5;
	const STATUS_CANCELLED = 9;

	/**
	 * @var int Entity
	 */
	public $entity;

	/**
	 * @var int Product FK
	 */
	public $fk_product;

	/**
	 * @var int Warehouse FK
	 */
	public $fk_entrepot;

	/**
	 * @var string Level 1 value
	 */
	public $level_1;

	/**
	 * @var string Level 2 value
	 */
	public $level_2;

	/**
	 * @var string Level 3 value
	 */
	public $level_3;

	/**
	 * @var string Level 4 value
	 */
	public $level_4;

	/**
	 * @var string Level 5 value
	 */
	public $level_5;

	/**
	 * @var string Level 6 value
	 */
	public $level_6;

	/**
	 * @var double Quantity at this location
	 */
	public $qty;

	/**
	 * @var int Whether this is the default location (0 or 1)
	 */
	public $is_default;

	/**
	 * @var int Reception FK (nullable)
	 */
	public $fk_reception;

	/**
	 * @var string Private note
	 */
	public $note_private;

	/**
	 * @var string Public note
	 */
	public $note_public;

	/**
	 * @var int Status
	 */
	public $status;

	/**
	 * @var int|string Creation date
	 */
	public $date_creation;

	/**
	 * @var int|string Validation date
	 */
	public $date_validation;

	/**
	 * @var int Creator user ID
	 */
	public $fk_user_creat;

	/**
	 * @var int Validator user ID
	 */
	public $fk_user_valid;

	/**
	 * @var int Last modifier user ID
	 */
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
	 * Create object in database
	 *
	 * @param  User $user       User creating
	 * @param  int  $notrigger  0=launch triggers, 1=disable triggers
	 * @return int              >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$error = 0;
		$this->db->begin();

		$now = dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."wareloc_product_location (";
		$sql .= "entity, fk_product, fk_entrepot";
		$sql .= ", level_1, level_2, level_3, level_4, level_5, level_6";
		$sql .= ", qty, is_default, fk_reception";
		$sql .= ", note_private, note_public, status";
		$sql .= ", date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_product);
		$sql .= ", ".((int) $this->fk_entrepot);
		for ($i = 1; $i <= 6; $i++) {
			$val = $this->{'level_'.$i};
			$sql .= ", ".($val !== null && $val !== '' ? "'".$this->db->escape($val)."'" : "NULL");
		}
		$sql .= ", ".((float) $this->qty);
		$sql .= ", ".((int) $this->is_default);
		$sql .= ", ".($this->fk_reception > 0 ? ((int) $this->fk_reception) : "NULL");
		$sql .= ", ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ", ".self::STATUS_DRAFT;
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."wareloc_product_location");
			$this->status = self::STATUS_DRAFT;
			$this->date_creation = $now;
			$this->fk_user_creat = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_CREATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object from database
	 *
	 * @param  int    $id   Id of object
	 * @param  string $ref  Ref of object
	 * @return int          >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id)
	{
		$sql = "SELECT t.rowid, t.entity, t.fk_product, t.fk_entrepot";
		$sql .= ", t.level_1, t.level_2, t.level_3, t.level_4, t.level_5, t.level_6";
		$sql .= ", t.qty, t.is_default, t.fk_reception";
		$sql .= ", t.note_private, t.note_public, t.status";
		$sql .= ", t.date_creation, t.date_validation, t.tms";
		$sql .= ", t.fk_user_creat, t.fk_user_valid, t.fk_user_modif";
		$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_product_location as t";
		$sql .= " WHERE t.entity IN (".getEntity('productlocation').")";
		if ($id > 0) {
			$sql .= " AND t.rowid = ".((int) $id);
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id              = $obj->rowid;
				$this->entity          = $obj->entity;
				$this->fk_product      = $obj->fk_product;
				$this->fk_entrepot     = $obj->fk_entrepot;
				$this->level_1         = $obj->level_1;
				$this->level_2         = $obj->level_2;
				$this->level_3         = $obj->level_3;
				$this->level_4         = $obj->level_4;
				$this->level_5         = $obj->level_5;
				$this->level_6         = $obj->level_6;
				$this->qty             = $obj->qty;
				$this->is_default      = $obj->is_default;
				$this->fk_reception    = $obj->fk_reception;
				$this->note_private    = $obj->note_private;
				$this->note_public     = $obj->note_public;
				$this->status          = $obj->status;
				$this->date_creation   = $this->db->jdate($obj->date_creation);
				$this->date_validation = $this->db->jdate($obj->date_validation);
				$this->fk_user_creat   = $obj->fk_user_creat;
				$this->fk_user_valid   = $obj->fk_user_valid;
				$this->fk_user_modif   = $obj->fk_user_modif;

				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update object in database
	 *
	 * @param  User $user       User modifying
	 * @param  int  $notrigger  0=launch triggers, 1=disable triggers
	 * @return int              >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;
		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_product_location SET";
		$sql .= " fk_product = ".((int) $this->fk_product);
		$sql .= ", fk_entrepot = ".((int) $this->fk_entrepot);
		for ($i = 1; $i <= 6; $i++) {
			$val = $this->{'level_'.$i};
			$sql .= ", level_".$i." = ".($val !== null && $val !== '' ? "'".$this->db->escape($val)."'" : "NULL");
		}
		$sql .= ", qty = ".((float) $this->qty);
		$sql .= ", is_default = ".((int) $this->is_default);
		$sql .= ", fk_reception = ".($this->fk_reception > 0 ? ((int) $this->fk_reception) : "NULL");
		$sql .= ", note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ", status = ".((int) $this->status);
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Delete object from database
	 *
	 * @param  User $user       User deleting
	 * @param  int  $notrigger  0=launch triggers, 1=disable triggers
	 * @return int              >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;
		$this->db->begin();

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			// Delete extrafields
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."wareloc_product_location_extrafields";
			$sql .= " WHERE fk_object = ".((int) $this->id);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error ".$this->db->lasterror();
			}
		}

		if (!$error) {
			// Delete linked objects
			$this->deleteObjectLinked();
		}

		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."wareloc_product_location";
			$sql .= " WHERE rowid = ".((int) $this->id);

			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->errors[] = "Error ".$this->db->lasterror();
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Validate (activate) a product location
	 *
	 * @param  User $user       User validating
	 * @param  int  $notrigger  0=launch triggers, 1=disable triggers
	 * @return int              >0 if OK, <0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		$error = 0;

		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'Can only validate a draft record';
			return -1;
		}

		$this->db->begin();

		$now = dol_now();

		$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_product_location SET";
		$sql .= " status = ".self::STATUS_ACTIVE;
		$sql .= ", date_validation = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_valid = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_ACTIVE;
			$this->date_validation = $now;
			$this->fk_user_valid = $user->id;
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_VALIDATE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Set location as moved (product relocated elsewhere)
	 *
	 * @param  User $user  User performing action
	 * @return int         >0 if OK, <0 if KO
	 */
	public function setMoved($user)
	{
		$error = 0;
		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_product_location SET";
		$sql .= " status = ".self::STATUS_MOVED;
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_MOVED;
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Cancel a product location
	 *
	 * @param  User $user  User cancelling
	 * @return int         >0 if OK, <0 if KO
	 */
	public function cancel($user)
	{
		$error = 0;
		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."wareloc_product_location SET";
		$sql .= " status = ".self::STATUS_CANCELLED;
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		if (!$error) {
			$this->status = self::STATUS_CANCELLED;
			$result = $this->call_trigger('WARELOC_PRODUCTLOCATION_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Return a display label derived from the parent reception ref or fallback to LOC-{id}
	 *
	 * @return string  Display ref
	 */
	public function getDisplayRef()
	{
		if ($this->fk_reception > 0) {
			require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
			$rec = new Reception($this->db);
			if ($rec->fetch($this->fk_reception) > 0) {
				return $rec->ref.' - Loc #'.$this->id;
			}
		}
		return 'LOC-'.$this->id;
	}

	/**
	 * Return clickable link to object
	 *
	 * @param  int    $withpicto   Add picto (0=no, 1=include)
	 * @param  string $option      Options
	 * @param  int    $notooltip   No tooltip
	 * @return string              HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0)
	{
		$url = dol_buildpath('/wareloc/productlocation_card.php', 1).'?id='.$this->id;
		$label = dol_escape_htmltag($this->getDisplayRef());

		$link = '<a href="'.$url.'" title="'.$label.'">';
		$linkend = '</a>';

		$result = $link;
		if ($withpicto) {
			$result .= img_picto('', 'stock', 'class="pictofixedwidth"');
		}
		$result .= $label.$linkend;
		return $result;
	}

	/**
	 * Return status label
	 *
	 * @param  int    $mode  0=long label, 1=short label, 2=Picto+short, 3=Picto, 4=Picto+long, 5=Short+Picto, 6=Long+Picto
	 * @return string        Status label
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->status, $mode);
	}

	/**
	 * Return label for a given status
	 *
	 * @param  int    $status  Status value
	 * @param  int    $mode    0=long,1=short,2=Picto+short,3=Picto,4=Picto+long,5=Short+Picto,6=Long+Picto
	 * @return string          Label
	 */
	public static function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('wareloc@wareloc');

		$statusType = '';
		$statusLabel = '';
		$statusLabelShort = '';

		switch ((int) $status) {
			case self::STATUS_DRAFT:
				$statusType = 'status0';
				$statusLabel = $langs->transnoentitiesnoconv('StatusDraft');
				$statusLabelShort = $langs->transnoentitiesnoconv('StatusDraft');
				break;
			case self::STATUS_ACTIVE:
				$statusType = 'status4';
				$statusLabel = $langs->transnoentitiesnoconv('StatusActive');
				$statusLabelShort = $langs->transnoentitiesnoconv('StatusActive');
				break;
			case self::STATUS_MOVED:
				$statusType = 'status6';
				$statusLabel = $langs->transnoentitiesnoconv('StatusMoved');
				$statusLabelShort = $langs->transnoentitiesnoconv('StatusMoved');
				break;
			case self::STATUS_CANCELLED:
				$statusType = 'status9';
				$statusLabel = $langs->transnoentitiesnoconv('StatusCancelled');
				$statusLabelShort = $langs->transnoentitiesnoconv('StatusCancelled');
				break;
		}

		return dolGetStatus($statusLabel, $statusLabelShort, '', $statusType, $mode);
	}

	/**
	 * Count ProductLocation records for a product (used for tab badge)
	 *
	 * @param  int  $productid  Product ID
	 * @return int              Count
	 */
	public static function countForProduct($productid)
	{
		global $db;

		$sql = "SELECT COUNT(rowid) as cnt FROM ".MAIN_DB_PREFIX."wareloc_product_location";
		$sql .= " WHERE fk_product = ".((int) $productid);
		$sql .= " AND status IN (".self::STATUS_DRAFT.", ".self::STATUS_ACTIVE.")";
		$sql .= " AND entity IN (".getEntity('productlocation').")";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			return (int) $obj->cnt;
		}
		return 0;
	}

	/**
	 * Fetch default location for a product in a specific warehouse
	 *
	 * @param  int   $productid    Product ID
	 * @param  int   $warehouseid  Warehouse ID
	 * @return array               Associative array of level_1..level_6 values, or empty array
	 */
	public static function fetchDefaultForProduct($productid, $warehouseid)
	{
		global $db, $conf;

		$defaults = array();

		$sql = "SELECT level_1, level_2, level_3, level_4, level_5, level_6";
		$sql .= " FROM ".MAIN_DB_PREFIX."wareloc_product_default";
		$sql .= " WHERE fk_product = ".((int) $productid);
		$sql .= " AND fk_entrepot = ".((int) $warehouseid);
		$sql .= " AND entity IN (".getEntity('wareloc_product_default').")";
		$sql .= " LIMIT 1";

		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql)) {
			$obj = $db->fetch_object($resql);
			for ($i = 1; $i <= 6; $i++) {
				$key = 'level_'.$i;
				$defaults[$key] = $obj->$key;
			}
		}

		return $defaults;
	}

	/**
	 * Build a human-readable location label from this object's level values
	 *
	 * @return string  Location path like "Row: Left > Bay: 3 > Shelf: 2"
	 */
	public function getLocationLabel()
	{
		dol_include_once('/wareloc/lib/wareloc.lib.php');

		$vals = array();
		for ($i = 1; $i <= 6; $i++) {
			$vals['level_'.$i] = $this->{'level_'.$i};
		}

		$levels = wareloc_get_active_levels(null, $this->fk_entrepot);
		return wareloc_build_location_label($vals, $levels);
	}
}
