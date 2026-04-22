<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    bulk_assign.php
 * \ingroup binloc
 * \brief   Bulk assign bin locations for all products with stock in a warehouse
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/binloc/lib/binloc.lib.php');
dol_include_once('/binloc/class/binlocwarehouselevel.class.php');
dol_include_once('/binloc/class/binlocproductlocation.class.php');

$langs->loadLangs(array('products', 'stocks', 'binloc@binloc'));

if (!$user->hasRight('binloc', 'write')) {
	accessforbidden();
}

$action      = GETPOST('action', 'aZ09');
$fk_entrepot = GETPOSTINT('fk_entrepot');
$search      = GETPOST('search_product', 'alphanohtml');

// Pagination
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'p.ref';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'ASC';
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
$limit     = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset    = $limit * max(0, $page);

$levelObj = new BinlocWarehouseLevel($db);
$locObj   = new BinlocProductLocation($db);

// ---- ACTIONS ----

if ($action === 'bulksave' && $fk_entrepot > 0) {
	$product_ids = GETPOST('product_ids', 'array');
	$saved = 0;

	if (is_array($product_ids)) {
		$db->begin();
		$error = 0;

		foreach ($product_ids as $pid) {
			$pid = (int) $pid;
			if ($pid <= 0) {
				continue;
			}

			// Collect level values for this product
			$has_value = false;
			$loc = new BinlocProductLocation($db);
			$loc->fk_product  = $pid;
			$loc->fk_entrepot = $fk_entrepot;

			for ($i = 1; $i <= 6; $i++) {
				$val = GETPOST('level'.$i.'_'.$pid, 'alphanohtml');
				$loc->{'level'.$i.'_value'} = $val;
				if ($val !== null && $val !== '') {
					$has_value = true;
				}
			}
			$loc->note = GETPOST('note_'.$pid, 'alphanohtml');
			if ($loc->note) {
				$has_value = true;
			}

			if (!$has_value) {
				continue;
			}

			$result = $loc->createOrUpdate($user);
			if ($result < 0) {
				$error++;
				setEventMessages($loc->error, null, 'errors');
				break;
			}
			$saved++;
		}

		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			if ($saved > 0) {
				setEventMessages($langs->trans('BulkSaved', $saved), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('BulkNothingChanged'), null, 'warnings');
			}
		}
	}
	$action = '';
}

// Batch set: apply same values to selected products
if ($action === 'batchset' && $fk_entrepot > 0) {
	$selected = GETPOST('selected', 'array');
	$saved = 0;

	if (is_array($selected) && !empty($selected)) {
		$db->begin();
		$error = 0;

		foreach ($selected as $pid) {
			$pid = (int) $pid;
			if ($pid <= 0) {
				continue;
			}

			$loc = new BinlocProductLocation($db);
			$loc->fk_product  = $pid;
			$loc->fk_entrepot = $fk_entrepot;

			for ($i = 1; $i <= 6; $i++) {
				$loc->{'level'.$i.'_value'} = GETPOST('batch_level'.$i, 'alphanohtml');
			}
			$loc->note = GETPOST('batch_note', 'alphanohtml');

			$result = $loc->createOrUpdate($user);
			if ($result < 0) {
				$error++;
				setEventMessages($loc->error, null, 'errors');
				break;
			}
			$saved++;
		}

		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			if ($saved > 0) {
				setEventMessages($langs->trans('BulkSaved', $saved), null, 'mesgs');
			}
		}
	}
	$action = '';
}

// ---- VIEW ----

llxHeader('', $langs->trans('BulkBinAssign'), '');

print dol_get_fiche_head(array(), '', $langs->trans('BulkBinAssign'), -1, 'stock');

print '<div class="opacitymedium marginbottomonly">'.$langs->trans('BulkAssignDesc').'</div>';

// Warehouse selector
$warehouses = binloc_get_warehouses($db);

print '<div class="marginbottomonly">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
print '<strong>'.$langs->trans('Warehouse').'</strong>: ';
print '<select name="fk_entrepot" class="flat minwidth250" onchange="this.form.submit()">';
print '<option value="0">'.$langs->trans('SelectWarehouse').'</option>';
foreach ($warehouses as $wh) {
	$sel = ($fk_entrepot == $wh->rowid) ? ' selected' : '';
	print '<option value="'.$wh->rowid.'"'.$sel.'>'.dol_escape_htmltag($wh->ref);
	if ($wh->lieu) {
		print ' - '.dol_escape_htmltag($wh->lieu);
	}
	print '</option>';
}
print '</select>';
print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Select').'">';
print '</form>';
print '</div>';

if ($fk_entrepot > 0) {
	$wh_levels = $levelObj->fetchByWarehouse($fk_entrepot);

	if (empty($wh_levels)) {
		print '<div class="info">';
		print $langs->trans('NoLevelsConfigured').' ';
		if ($user->hasRight('binloc', 'admin') || $user->admin) {
			$setup_url = dol_buildpath('/binloc/admin/warehouse_levels.php?fk_entrepot='.$fk_entrepot, 1);
			print '<a href="'.$setup_url.'" class="button smallpaddingimp">'.$langs->trans('ConfigureLevels').'</a>';
		}
		print '</div>';
	} else {
		// Search bar
		print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
		print '<div class="marginbottomonly">';
		print '<input type="text" name="search_product" class="flat minwidth200" value="'.dol_escape_htmltag($search).'" placeholder="'.dol_escape_htmltag($langs->trans('SearchProduct')).'">';
		print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Search').'">';
		if (!empty($search)) {
			print ' <a href="'.$_SERVER['PHP_SELF'].'?fk_entrepot='.$fk_entrepot.'" class="button smallpaddingimp">'.$langs->trans('Reset').'</a>';
		}
		print '</div>';
		print '</form>';

		// Fetch products with stock in this warehouse
		$products = binloc_get_products_in_warehouse($db, $fk_entrepot, $search, $sortfield, $sortorder, $limit, $offset);
		$total    = binloc_count_products_in_warehouse($db, $fk_entrepot, $search);

		if (!empty($products)) {
			// Level names summary
			print '<div class="opacitymedium marginbottomonly small">';
			$label_strs = array();
			foreach ($wh_levels as $cfg) {
				$label_strs[] = dol_escape_htmltag($cfg->label);
			}
			print implode(' &rarr; ', $label_strs);
			print '</div>';

			// Pagination
			print_barre_liste(
				'',
				$page,
				$_SERVER['PHP_SELF'],
				'&fk_entrepot='.$fk_entrepot.(!empty($search) ? '&search_product='.urlencode($search) : ''),
				$sortfield,
				$sortorder,
				'',
				count($products),
				$total,
				'',
				0,
				'',
				'',
				$limit
			);

			// ---- Bulk save form ----
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="binloc-bulk-form">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="bulksave">';
			print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
			if (!empty($search)) {
				print '<input type="hidden" name="search_product" value="'.dol_escape_htmltag($search).'">';
			}

			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			print '<td class="center" width="30"><input type="checkbox" id="binloc-selectall" onclick="binlocToggleAll(this)"></td>';
			print_liste_field_titre('ProductRef', $_SERVER['PHP_SELF'], 'p.ref', '', '&fk_entrepot='.$fk_entrepot, '', $sortfield, $sortorder);
			print_liste_field_titre('Label', $_SERVER['PHP_SELF'], 'p.label', '', '&fk_entrepot='.$fk_entrepot, '', $sortfield, $sortorder);
			print '<td class="right">'.$langs->trans('Stock').'</td>';
			foreach ($wh_levels as $num => $cfg) {
				print '<td>'.dol_escape_htmltag($cfg->label);
				// Fill-down button per column
				print ' <a href="#" onclick="binlocFillDown('.$num.'); return false;" title="'.dol_escape_htmltag($langs->trans('FillDownHint')).'" class="opacitymedium" style="font-size:0.85em;">';
				print img_picto('', 'arrow_down', 'class="pictofixedwidth"');
				print '&darr;</a>';
				print '</td>';
			}
			print '<td>'.$langs->trans('LocationNote').'</td>';
			print '</tr>';

			foreach ($products as $prod) {
				$product_url = dol_buildpath('/product/card.php?id='.$prod->fk_product, 1);

				print '<tr class="oddeven">';
				print '<td class="center">';
				print '<input type="checkbox" name="selected[]" value="'.$prod->fk_product.'" class="binloc-select-cb">';
				print '<input type="hidden" name="product_ids[]" value="'.$prod->fk_product.'">';
				print '</td>';
				print '<td><a href="'.$product_url.'">'.dol_escape_htmltag($prod->ref).'</a></td>';
				print '<td>'.dol_escape_htmltag($prod->label).'</td>';
				print '<td class="right">'.price2num($prod->stock, 0).'</td>';
				foreach ($wh_levels as $num => $cfg) {
					$val = $prod->{'level'.$num.'_value'};
					print '<td>'.binloc_render_level_input($cfg, 'level'.$num.'_'.$prod->fk_product, $val, 'flat width75 binloc-level-cell', 'data-level="'.$num.'"').'</td>';
				}
				print '<td><input type="text" name="note_'.$prod->fk_product.'" class="flat width100" value="'.dol_escape_htmltag($prod->note).'"></td>';
				print '</tr>';
			}

			print '</table>';

			print '<div class="margintoponly">';
			print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('BulkSaveAll')).'">';
			print '</div>';

			print '</form>';

			// ---- Batch set panel ----
			print '<div class="fichecenter marginbottomonly margintoponly" id="binloc-batch-panel" style="display:none; padding:10px; border:1px solid #9c9; border-radius:4px; background:#f8fff8;">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="binloc-batch-form">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="batchset">';
			print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
			if (!empty($search)) {
				print '<input type="hidden" name="search_product" value="'.dol_escape_htmltag($search).'">';
			}
			print '<div id="binloc-batch-selected"></div>';

			print '<strong>'.$langs->trans('SetSelectedTo').':</strong>';
			print '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:6px;">';
			foreach ($wh_levels as $num => $cfg) {
				print '<div>';
				print '<label class="opacitymedium small">'.dol_escape_htmltag($cfg->label).'</label><br>';
				print binloc_render_level_input($cfg, 'batch_level'.$num, '', 'flat width75');
				print '</div>';
			}
			print '<div>';
			print '<label class="opacitymedium small">'.$langs->trans('LocationNote').'</label><br>';
			print '<input type="text" name="batch_note" class="flat width100">';
			print '</div>';
			print '<div style="align-self:end;">';
			print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Apply')).'">';
			print '</div>';
			print '</div>';
			print '</form>';
			print '</div>';

			// JS
			print '<script>
function binlocFillDown(levelNum) {
	// Find all inputs for this level in the bulk table
	var inputs = document.querySelectorAll("[data-level=\"" + levelNum + "\"]");
	if (inputs.length === 0) return;
	// Take the first non-empty value and fill it down to empty cells
	var sourceVal = "";
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].value !== "") {
			sourceVal = inputs[i].value;
			break;
		}
	}
	if (sourceVal === "") {
		// Prompt the user if no value to copy
		sourceVal = window.prompt("Enter value to fill down:");
		if (sourceVal === null || sourceVal === "") return;
	}
	inputs.forEach(function(inp) {
		if (inp.value === "") {
			inp.value = sourceVal;
		}
	});
}

function binlocToggleAll(master) {
	document.querySelectorAll(".binloc-select-cb").forEach(function(cb) {
		cb.checked = master.checked;
	});
	binlocUpdateBatchPanel();
}

document.querySelectorAll(".binloc-select-cb").forEach(function(cb) {
	cb.addEventListener("change", binlocUpdateBatchPanel);
});

function binlocUpdateBatchPanel() {
	var checked = document.querySelectorAll(".binloc-select-cb:checked");
	var panel = document.getElementById("binloc-batch-panel");
	var container = document.getElementById("binloc-batch-selected");

	if (checked.length > 0) {
		panel.style.display = "block";
		// Copy selected checkboxes into batch form
		container.innerHTML = "";
		checked.forEach(function(cb) {
			var hidden = document.createElement("input");
			hidden.type = "hidden";
			hidden.name = "selected[]";
			hidden.value = cb.value;
			container.appendChild(hidden);
		});
	} else {
		panel.style.display = "none";
	}
}
</script>';
		} else {
			print '<div class="opacitymedium">'.$langs->trans('NoProductsWithStock').'</div>';
		}
	}
}

print dol_get_fiche_end();
llxFooter();
$db->close();
