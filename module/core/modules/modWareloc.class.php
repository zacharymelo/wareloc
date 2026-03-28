<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \defgroup   wareloc  Module Wareloc
 * \brief      Configurable sub-warehouse location hierarchy for products
 * \file       core/modules/modWareloc.class.php
 * \ingroup    wareloc
 * \brief      Module descriptor for Wareloc
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor class
 */
class modWareloc extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 530000;
		$this->rights_class = 'wareloc';
		$this->family = 'products';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Configurable sub-warehouse location hierarchy for products (Row, Bay, Shelf, Bin)';
		$this->descriptionlong = 'Extends the native warehouse module with configurable location levels. Define hierarchy layers (Row, Bay, Shelf, Bin) with custom data types, set default locations on products, and assign precise locations during reception.';
		$this->editor_name = 'Zachary Melo';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'stock';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array('elementproperties', 'productcard', 'receptioncard', 'commonobject'),
				'entity' => '0',
			),
		);

		$this->dirs = array('/wareloc/temp');

		$this->config_page_url = array('setup.php@wareloc');

		$this->depends = array('modSociete', 'modProduct', 'modStock', 'modReception');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('wareloc@wareloc');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// Constants
		$this->const = array();

		// Tabs on native objects
		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'product:+wareloc_locations:Locations,stock,/wareloc/class/productlocation.class.php,countForProduct:wareloc@wareloc:$user->hasRight(\'wareloc\', \'productlocation\', \'read\'):/wareloc/productlocation_list.php?fk_product=__ID__',
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		// ProductLocation: read
		$r++;
		$this->rights[$r][0] = 530001;
		$this->rights[$r][1] = 'Read product location records';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'productlocation';
		$this->rights[$r][5] = 'read';

		// ProductLocation: write
		$r++;
		$this->rights[$r][0] = 530002;
		$this->rights[$r][1] = 'Create/edit product location records';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'productlocation';
		$this->rights[$r][5] = 'write';

		// ProductLocation: delete
		$r++;
		$this->rights[$r][0] = 530003;
		$this->rights[$r][1] = 'Delete product location records';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'productlocation';
		$this->rights[$r][5] = 'delete';

		// ProductLocation: validate
		$r++;
		$this->rights[$r][0] = 530004;
		$this->rights[$r][1] = 'Validate product location records';
		$this->rights[$r][2] = 'v';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'productlocation';
		$this->rights[$r][5] = 'validate';

		// Admin
		$r++;
		$this->rights[$r][0] = 530005;
		$this->rights[$r][1] = 'Configure wareloc module settings';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$this->rights[$r][5] = 'write';

		// Menus
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r++] = array(
			'fk_menu'  => 0,
			'type'     => 'top',
			'titre'    => 'WarelocMenu',
			'prefix'   => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'wareloc',
			'leftmenu' => '',
			'url'      => '/wareloc/productlocation_list.php',
			'langs'    => 'wareloc@wareloc',
			'position' => 100,
			'enabled'  => 'isModEnabled("wareloc")',
			'perms'    => '$user->hasRight("wareloc", "productlocation", "read")',
			'target'   => '',
			'user'     => 0,
		);

		// Left: Product Locations list
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=wareloc',
			'type'     => 'left',
			'titre'    => 'ProductLocationList',
			'mainmenu' => 'wareloc',
			'leftmenu' => 'wareloc_productlocation_list',
			'url'      => '/wareloc/productlocation_list.php',
			'langs'    => 'wareloc@wareloc',
			'position' => 100,
			'enabled'  => 'isModEnabled("wareloc")',
			'perms'    => '$user->hasRight("wareloc", "productlocation", "read")',
			'target'   => '',
			'user'     => 0,
		);

		// Left: New placement
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=wareloc,fk_leftmenu=wareloc_productlocation_list',
			'type'     => 'left',
			'titre'    => 'NewProductLocation',
			'mainmenu' => 'wareloc',
			'leftmenu' => 'wareloc_productlocation_new',
			'url'      => '/wareloc/productlocation_card.php?action=create',
			'langs'    => 'wareloc@wareloc',
			'position' => 101,
			'enabled'  => 'isModEnabled("wareloc")',
			'perms'    => '$user->hasRight("wareloc", "productlocation", "write")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled
	 *
	 * @param  string $options  Options when enabling module
	 * @return int              1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/wareloc/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->delete_menus();

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled
	 *
	 * @param  string $options  Options when disabling module
	 * @return int              1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
