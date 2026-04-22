<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/debug.php
 * \ingroup binloc
 * \brief   Comprehensive debug diagnostics for the binloc module.
 *          Gated by admin permission + BINLOC_DEBUG_MODE setting.
 *
 * Modes (via ?mode=):
 *   overview    — Module config, hook contexts, trigger registration, DB table health (default)
 *   object      — Deep inspect a single object (?mode=object&type=productlocation&id=11)
 *   settings    — All BINLOC_* constants from llx_const
 *   classes     — Class loading + method availability for all module objects
 *   sql         — Run a read-only diagnostic query (?mode=sql&q=SELECT...)
 *   triggers    — List all registered triggers and check ours is loaded
 *   hooks       — Show registered hook contexts and verify our hooks fire
 *   levels      — Show warehouse level configurations across all warehouses
 *   all         — Run every diagnostic at once
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->admin) { http_response_code(403); print 'Admin only'; exit; }
if (!getDolGlobalInt('BINLOC_DEBUG_MODE')) {
	http_response_code(403);
	print 'Debug mode not enabled. Set constant BINLOC_DEBUG_MODE = 1 in Setup > Other Setup.';
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'alpha') ?: 'overview';
$run_all = ($mode === 'all');

$MODULE_NAME   = 'binloc';
$MODULE_UPPER  = 'BINLOC';
$OBJECTS = array(
	'warehouselevel' => array(
		'class'      => 'BinlocWarehouseLevel',
		'classfile'  => 'binlocwarehouselevel',
		'table'      => 'binloc_warehouse_levels',
		'fk_fields'  => array('fk_entrepot'),
	),
	'productlocation' => array(
		'class'      => 'BinlocProductLocation',
		'classfile'  => 'binlocproductlocation',
		'table'      => 'binloc_product_location',
		'fk_fields'  => array('fk_product', 'fk_entrepot'),
	),
);

print "=== BINLOC DEBUG DIAGNOSTICS ===\n";
print "Timestamp: ".date('Y-m-d H:i:s T')."\n";
print "Dolibarr: ".(defined('DOL_VERSION') ? DOL_VERSION : 'unknown')."\n";
print "DB prefix: ".MAIN_DB_PREFIX."\n";
print "Mode: $mode\n";
print "Usage: ?mode=overview|object|settings|classes|sql|triggers|hooks|levels|all\n";
print "       ?mode=object&type=productlocation&id=11\n";
print "       ?mode=sql&q=SELECT+rowid,fk_product+FROM+".MAIN_DB_PREFIX."binloc_product_location+LIMIT+5\n";
print str_repeat('=', 60)."\n\n";


// =====================================================================
// OVERVIEW
// =====================================================================
if ($mode === 'overview' || $run_all) {
	print "--- MODULE STATUS ---\n";
	print "isModEnabled('$MODULE_NAME'): ".(isModEnabled($MODULE_NAME) ? 'YES' : 'NO')."\n";

	print "\nRegistered module_parts:\n";
	if (isset($conf->modules_parts)) {
		foreach (array('hooks', 'triggers', 'tpl') as $part) {
			if (isset($conf->modules_parts[$part])) {
				$found = false;
				foreach ($conf->modules_parts[$part] as $k => $v) {
					if (stripos($k, $MODULE_NAME) !== false || stripos(print_r($v, true), $MODULE_NAME) !== false) {
						print "  $part: ".print_r($v, true);
						$found = true;
					}
				}
				if (!$found) print "  $part: ($MODULE_NAME not found in registered parts)\n";
			} else {
				print "  $part: (not in modules_parts)\n";
			}
		}
	}

	print "\n--- DATABASE TABLES ---\n";
	$tables = array('binloc_warehouse_levels', 'binloc_product_location', 'binloc_level_options');
	foreach ($tables as $tbl) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$tbl;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  ".MAIN_DB_PREFIX."$tbl: ".$obj->cnt." rows\n";
		} else {
			print "  ".MAIN_DB_PREFIX."$tbl: TABLE MISSING OR ERROR\n";
		}
	}

	print "\n--- PERMISSIONS ---\n";
	$perms = array('read', 'write', 'admin');
	foreach ($perms as $p) {
		print "  binloc.$p: ".($user->hasRight('binloc', $p) ? 'YES' : 'NO')."\n";
	}

	print "\n--- TAB REGISTRATION ---\n";
	// Check if tabs are registered
	$sql = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_BINLOC_TABS_0' AND entity IN (0, ".((int) $conf->entity).")";
	$resql = $db->query($sql);
	if ($resql && ($row = $db->fetch_object($resql))) {
		print "  Product tab: $row->value\n";
	} else {
		print "  Product tab: (checking via conf)\n";
	}

	// Check for the tab files
	$tab_files = array('tab_product_locations.php', 'tab_warehouse_locations.php', 'bulk_assign.php');
	foreach ($tab_files as $tf) {
		$fullpath = dol_buildpath('/'.$MODULE_NAME.'/'.$tf);
		print "  $tf: ".(file_exists($fullpath) ? 'EXISTS' : 'MISSING ('.$fullpath.')')."\n";
	}

	print "\n";
}


// =====================================================================
// OBJECT
// =====================================================================
if ($mode === 'object' || $run_all) {
	$otype = GETPOST('type', 'alpha') ?: 'productlocation';
	$oid   = GETPOSTINT('id');

	if ($oid <= 0 && !$run_all) {
		print "--- OBJECT DIAGNOSIS ---\nUsage: ?mode=object&type=productlocation&id=11\n";
		print "       ?mode=object&type=warehouselevel&id=1\n\n";
	} elseif ($oid > 0) {
		$odef = isset($OBJECTS[$otype]) ? $OBJECTS[$otype] : null;
		if (!$odef) {
			print "--- OBJECT DIAGNOSIS ---\nUnknown type '$otype'. Available: ".implode(', ', array_keys($OBJECTS))."\n\n";
		} else {
			print "--- OBJECT DIAGNOSIS: $otype id=$oid ---\n";
			dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
			$classname = $odef['class'];

			if (!class_exists($classname)) {
				print "  Class $classname NOT FOUND after include!\n\n";
			} else {
				$obj = new $classname($db);
				$fetch_result = $obj->fetch($oid);
				print "  fetch() returned: $fetch_result\n";

				if ($fetch_result > 0) {
					print "  element: $obj->element\n";
					print "  table_element: $obj->table_element\n";

					print "\n  FK fields:\n";
					foreach ($odef['fk_fields'] as $fk) {
						$val = isset($obj->$fk) ? $obj->$fk : null;
						print "    $fk = ".($val !== null ? $val : 'NULL')."\n";
					}

					// For product locations, show level values
					if ($otype === 'productlocation') {
						print "\n  Level values:\n";
						for ($i = 1; $i <= 6; $i++) {
							$val = $obj->{'level'.$i.'_value'};
							if ($val !== null && $val !== '') {
								print "    level$i = $val\n";
							}
						}
						print "  note: ".($obj->note ?: '(empty)')."\n";

						// Show formatted location
						dol_include_once('/'.$MODULE_NAME.'/class/'.$OBJECTS['warehouselevel']['classfile'].'.class.php');
						$lvl = new BinlocWarehouseLevel($db);
						$levels = $lvl->fetchByWarehouse($obj->fk_entrepot);
						if (!empty($levels)) {
							print "  formatted: ".$obj->getFormattedLocation($levels)."\n";
						}
					}
				}
			}
			print "\n";
		}
	}
}


// =====================================================================
// SETTINGS
// =====================================================================
if ($mode === 'settings' || $run_all) {
	print "--- BINLOC SETTINGS ---\n";

	$sql = "SELECT name, value, note FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'BINLOC%' AND entity IN (0, ".((int) $conf->entity).") ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			$display_val = strlen($row->value) > 80 ? substr($row->value, 0, 80).'...' : $row->value;
			print "  $row->name = $display_val\n";
			$cnt++;
		}
		if ($cnt == 0) print "  (no BINLOC_* constants found)\n";
	}

	// Also show MAIN_MODULE_BINLOC* constants
	$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_MODULE_BINLOC%' AND entity IN (0, ".((int) $conf->entity).") ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		print "\n  Module registration constants:\n";
		while ($row = $db->fetch_object($resql)) {
			$display_val = strlen($row->value) > 80 ? substr($row->value, 0, 80).'...' : $row->value;
			print "    $row->name = $display_val\n";
		}
	}
	print "\n";
}


// =====================================================================
// CLASSES
// =====================================================================
if ($mode === 'classes' || $run_all) {
	print "--- CLASS LOADING & METHODS ---\n";

	foreach ($OBJECTS as $bare => $odef) {
		print "  $bare ({$odef['class']}):\n";
		$inc = @dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
		print "    dol_include_once: ".($inc ? 'OK' : 'FAILED')."\n";
		print "    class_exists: ".(class_exists($odef['class']) ? 'YES' : 'NO')."\n";

		if (class_exists($odef['class'])) {
			$obj = new $odef['class']($db);
			print "    \$element: ".$obj->element."\n";
			print "    \$table_element: ".$obj->table_element."\n";

			// Check key methods
			$methods_to_check = array('create', 'fetch', 'update', 'delete');
			if ($bare === 'warehouselevel') {
				$methods_to_check = array_merge($methods_to_check, array('fetchByWarehouse', 'saveWarehouseLevels', 'copyFromWarehouse', 'getMaxLevel'));
			}
			if ($bare === 'productlocation') {
				$methods_to_check = array_merge($methods_to_check, array('fetchByProductWarehouse', 'fetchAllByProduct', 'fetchAllByWarehouse', 'createOrUpdate', 'getFormattedLocation', 'clearIfZeroStock'));
			}

			$missing = array();
			foreach ($methods_to_check as $m) {
				if (!method_exists($obj, $m)) {
					$missing[] = $m;
				}
			}
			print "    Methods: ".(empty($missing) ? 'ALL PRESENT ('.count($methods_to_check).')' : 'MISSING: '.implode(', ', $missing))."\n";
		}
		print "\n";
	}
}


// =====================================================================
// SQL
// =====================================================================
if ($mode === 'sql') {
	$q = GETPOST('q', 'restricthtml');
	print "--- SQL QUERY ---\n";

	if (empty($q)) {
		print "Usage: ?mode=sql&q=SELECT+rowid,fk_product+FROM+".MAIN_DB_PREFIX."binloc_product_location+LIMIT+5\n";
		print "\nUseful queries:\n";
		print "  ?mode=sql&q=SELECT rowid,fk_entrepot,level_num,label FROM ".MAIN_DB_PREFIX."binloc_warehouse_levels ORDER BY fk_entrepot,level_num\n";
		print "  ?mode=sql&q=SELECT rowid,fk_product,fk_entrepot,level1_value,level2_value,level3_value,level4_value FROM ".MAIN_DB_PREFIX."binloc_product_location ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT pl.rowid,p.ref,e.ref as warehouse,pl.level1_value,pl.level2_value,pl.level3_value,pl.level4_value FROM ".MAIN_DB_PREFIX."binloc_product_location pl LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid=pl.fk_product LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid=pl.fk_entrepot LIMIT 10\n";
	} else {
		$q_trimmed = trim($q);
		if (stripos($q_trimmed, 'SELECT') !== 0) {
			print "ERROR: Only SELECT queries allowed.\n";
		} else {
			$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT', 'REVOKE');
			$safe = true;
			foreach ($blocked as $kw) {
				if (stripos($q_trimmed, $kw) !== false && stripos($q_trimmed, $kw) !== stripos($q_trimmed, 'SELECT')) {
					$safe = false;
					break;
				}
			}
			if (!$safe) {
				print "ERROR: Query contains blocked keywords.\n";
			} else {
				if (stripos($q_trimmed, 'LIMIT') === false) {
					$q_trimmed .= ' LIMIT 50';
				}

				print "Query: $q_trimmed\n\n";
				$resql = $db->query($q_trimmed);
				if ($resql) {
					$first = true;
					$row_num = 0;
					while ($obj = $db->fetch_array($resql)) {
						if ($first) {
							print implode("\t", array_keys($obj))."\n";
							print str_repeat('-', 80)."\n";
							$first = false;
						}
						$row_num++;
						$vals = array();
						foreach ($obj as $v) {
							$vals[] = ($v === null) ? 'NULL' : (strlen($v) > 40 ? substr($v, 0, 40).'...' : $v);
						}
						print implode("\t", $vals)."\n";
					}
					print "\n$row_num rows returned.\n";
				} else {
					print "SQL ERROR: ".$db->lasterror()."\n";
				}
			}
		}
	}
	print "\n";
}


// =====================================================================
// TRIGGERS
// =====================================================================
if ($mode === 'triggers' || $run_all) {
	print "--- TRIGGER REGISTRATION ---\n";

	$trigger_dir = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/core/triggers';
	if (is_dir($trigger_dir)) {
		$files = scandir($trigger_dir);
		foreach ($files as $f) {
			if (preg_match('/^interface_.*\.class\.php$/', $f)) {
				print "  Found trigger file: $f\n";
				include_once $trigger_dir.'/'.$f;
				$classname = preg_replace('/\.class\.php$/', '', $f);
				print "    Class '$classname' exists: ".(class_exists($classname) ? 'YES' : 'NO')."\n";
			}
		}
	} else {
		print "  Trigger directory not found: $trigger_dir\n";
		// Try alternate path
		$alt_dir = dol_buildpath('/'.$MODULE_NAME.'/core/triggers');
		print "  Alternate path: $alt_dir ".(is_dir($alt_dir) ? '(EXISTS)' : '(NOT FOUND)')."\n";
	}

	print "\n  Expected trigger: InterfaceBinlocTrigger\n";
	print "  Listens for: STOCK_MOVEMENT\n";
	print "  BINLOC_CLEAR_ON_ZERO_STOCK: ".getDolGlobalInt('BINLOC_CLEAR_ON_ZERO_STOCK')."\n";
	print "\n";
}


// =====================================================================
// HOOKS
// =====================================================================
if ($mode === 'hooks' || $run_all) {
	print "--- HOOK REGISTRATION ---\n";

	print "  Expected hook contexts: warehousecard, productcard, receptioncard, ordersupplierdispatch\n\n";

	print "  Hook contexts from conf->modules_parts['hooks']:\n";
	if (isset($conf->modules_parts['hooks'])) {
		$found_any = false;
		foreach ($conf->modules_parts['hooks'] as $context => $modules) {
			if (is_array($modules)) {
				foreach ($modules as $mod) {
					if (stripos($mod, $MODULE_NAME) !== false) {
						print "    context='$context' module='$mod'\n";
						$found_any = true;
					}
				}
			} elseif (stripos($modules, $MODULE_NAME) !== false) {
				print "    context='$context' module='$modules'\n";
				$found_any = true;
			}
		}
		if (!$found_any) print "    (no $MODULE_NAME hooks found in modules_parts)\n";
	} else {
		print "    (modules_parts['hooks'] not set)\n";
	}

	print "\n  Actions class:\n";
	$actions_file = dol_buildpath('/'.$MODULE_NAME.'/class/actions_'.$MODULE_NAME.'.class.php');
	print "    File: $actions_file\n";
	print "    Exists: ".(file_exists($actions_file) ? 'YES' : 'NO')."\n";
	if (file_exists($actions_file)) {
		include_once $actions_file;
		$actions_class = 'ActionsBinloc';
		print "    Class '$actions_class' exists: ".(class_exists($actions_class) ? 'YES' : 'NO')."\n";
		if (class_exists($actions_class)) {
			$hook_methods = array('formObjectOptions', 'formAddObjectLine', 'printFieldListValue', 'printFieldListTitle');
			foreach ($hook_methods as $m) {
				print "    method $m(): ".(method_exists($actions_class, $m) ? 'defined' : 'not defined')."\n";
			}
		}
	}

	print "\n  AJAX endpoint:\n";
	$ajax_file = dol_buildpath('/'.$MODULE_NAME.'/ajax/assign_location.php');
	print "    $ajax_file: ".(file_exists($ajax_file) ? 'EXISTS' : 'MISSING')."\n";
	print "\n";
}


// =====================================================================
// LEVELS — warehouse level configurations
// =====================================================================
if ($mode === 'levels' || $run_all) {
	print "--- WAREHOUSE LEVEL CONFIGURATIONS ---\n";

	$sql = "SELECT wl.fk_entrepot, e.ref as warehouse_ref, e.lieu,";
	$sql .= " wl.level_num, wl.label, wl.active";
	$sql .= " FROM ".MAIN_DB_PREFIX."binloc_warehouse_levels wl";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = wl.fk_entrepot";
	$sql .= " WHERE wl.entity IN (".getEntity('stock').")";
	$sql .= " ORDER BY wl.fk_entrepot, wl.level_num";

	$resql = $db->query($sql);
	if ($resql) {
		$current_wh = 0;
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			if ($row->fk_entrepot != $current_wh) {
				if ($current_wh > 0) print "\n";
				$current_wh = $row->fk_entrepot;
				$wh_label = $row->warehouse_ref.($row->lieu ? ' ('.$row->lieu.')' : '');
				print "  Warehouse #$current_wh: $wh_label\n";
			}
			$status = $row->active ? '' : ' [INACTIVE]';
			print "    Level $row->level_num: $row->label$status\n";
			$cnt++;
		}
		if ($cnt == 0) print "  (no level configurations found)\n";
	} else {
		print "  Query error (table may not exist): ".$db->lasterror()."\n";
	}

	// Also show location counts per warehouse
	print "\n--- LOCATION RECORD COUNTS ---\n";
	$sql = "SELECT pl.fk_entrepot, e.ref as warehouse_ref, COUNT(*) as cnt";
	$sql .= " FROM ".MAIN_DB_PREFIX."binloc_product_location pl";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = pl.fk_entrepot";
	$sql .= " WHERE pl.entity IN (".getEntity('stock').")";
	$sql .= " GROUP BY pl.fk_entrepot, e.ref";
	$sql .= " ORDER BY e.ref";

	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			print "  $row->warehouse_ref: $row->cnt products with locations\n";
			$cnt++;
		}
		if ($cnt == 0) print "  (no location records yet)\n";
	} else {
		print "  Query error: ".$db->lasterror()."\n";
	}

	print "\n";
}


print "=== END DEBUG ===\n";
