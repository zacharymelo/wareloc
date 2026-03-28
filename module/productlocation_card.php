<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    productlocation_card.php
 * \ingroup wareloc
 * \brief   Card page for ProductLocation
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/wareloc/class/productlocation.class.php');
dol_include_once('/wareloc/lib/wareloc.lib.php');

$langs->loadLangs(array('wareloc@wareloc', 'products', 'stocks', 'other'));

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new ProductLocation($db);

// ExtraFields
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// Permissions
$permread     = $user->hasRight('wareloc', 'productlocation', 'read');
$permwrite    = $user->hasRight('wareloc', 'productlocation', 'write');
$permdelete   = $user->hasRight('wareloc', 'productlocation', 'delete');
$permvalidate = $user->hasRight('wareloc', 'productlocation', 'validate');

if (!$permread) { accessforbidden(); }

// Fetch object
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
	$id = $object->id;
}

// Load active levels (warehouse-aware: use object's warehouse if known, else global)
$wh_for_levels = ($object->id > 0 && $object->fk_entrepot > 0) ? $object->fk_entrepot : 0;
$levels = wareloc_get_active_levels(null, $wh_for_levels);

// ---- ACTIONS ----

if ($cancel) {
	if (!empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	if ($id > 0) {
		$action = '';
	} else {
		header("Location: productlocation_list.php");
		exit;
	}
}

// CREATE
if ($action === 'add' && $permwrite) {
	$object->fk_product  = GETPOSTINT('fk_product');
	$object->fk_entrepot = GETPOSTINT('fk_entrepot');
	$object->qty         = (float) GETPOST('qty', 'alpha');
	$object->is_default  = GETPOSTINT('is_default');
	$object->note_private = GETPOST('note_private', 'restricthtml');
	$object->note_public  = GETPOST('note_public', 'restricthtml');

	for ($i = 1; $i <= 6; $i++) {
		$object->{'level_'.$i} = GETPOST('wareloc_level_'.$i, 'alpha');
	}

	// ExtraFields
	$ret = $extrafields->setOptionalsFromPost(null, $object);

	$error = 0;
	if (empty($object->fk_product)) {
		setEventMessages('Product is required', null, 'errors');
		$error++;
	}
	if (empty($object->fk_entrepot)) {
		setEventMessages('Warehouse is required', null, 'errors');
		$error++;
	}

	// Check required levels
	foreach ($levels as $lev) {
		if ($lev->required && empty($object->{'level_'.$lev->position})) {
			setEventMessages($lev->label.' is required', null, 'errors');
			$error++;
		}
	}

	if (!$error) {
		$result = $object->create($user);
		if ($result > 0) {
			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	} else {
		$action = 'create';
	}
}

// UPDATE
if ($action === 'update' && $permwrite) {
	$object->fk_product  = GETPOSTINT('fk_product');
	$object->fk_entrepot = GETPOSTINT('fk_entrepot');
	$object->qty         = (float) GETPOST('qty', 'alpha');
	$object->is_default  = GETPOSTINT('is_default');
	$object->note_private = GETPOST('note_private', 'restricthtml');
	$object->note_public  = GETPOST('note_public', 'restricthtml');

	for ($i = 1; $i <= 6; $i++) {
		$object->{'level_'.$i} = GETPOST('wareloc_level_'.$i, 'alpha');
	}

	$ret = $extrafields->setOptionalsFromPost(null, $object);

	$result = $object->update($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

// VALIDATE
if ($action === 'confirm_validate' && $confirm === 'yes' && $permvalidate) {
	$result = $object->validate($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// SET MOVED
if ($action === 'confirm_setmoved' && $confirm === 'yes' && $permwrite) {
	$result = $object->setMoved($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// CANCEL
if ($action === 'confirm_cancel' && $confirm === 'yes' && $permwrite) {
	$result = $object->cancel($user);
	if ($result > 0) {
		header("Location: ".$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// DELETE
if ($action === 'confirm_delete' && $confirm === 'yes' && $permdelete) {
	$result = $object->delete($user);
	if ($result > 0) {
		header("Location: productlocation_list.php");
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}


// ---- VIEW ----

$form = new Form($db);

llxHeader('', $langs->trans('ProductLocation'), '');

// CREATE FORM
if ($action === 'create') {
	print load_fiche_titre($langs->trans('NewProductLocation'), '', 'stock');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if (!empty($backtopage)) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">';

	// Product
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Product').'</td><td>';
	$preselected_product = GETPOSTINT('fk_product');
	print $form->select_produits($preselected_product, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500 widthcentpercentminusxx');
	print '</td></tr>';

	// Warehouse
	print '<tr><td class="fieldrequired">'.$langs->trans('Warehouse').'</td><td>';
	require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
	$formproduct = new FormProduct($db);
	$preselected_wh = GETPOSTINT('fk_entrepot');
	$formproduct->selectWarehouses($preselected_wh, 'fk_entrepot', '', 1);
	print '</td></tr>';

	// Location levels (dynamic — reloaded via AJAX when warehouse changes)
	print '<tbody id="wareloc_level_fields">';
	if (!empty($levels)) {
		$values = array();
		for ($i = 1; $i <= 6; $i++) {
			$values['level_'.$i] = GETPOST('wareloc_level_'.$i, 'alpha');
		}
		print wareloc_render_level_fields($levels, $values, 'wareloc', 'edit');
	} else {
		print '<tr><td colspan="2"><div class="warning">'.$langs->trans('NoLevelsConfigured').'</div></td></tr>';
	}
	print '</tbody>';

	// Quantity
	print '<tr><td>'.$langs->trans('Quantity').'</td><td>';
	print '<input type="text" name="qty" class="flat maxwidth100" value="'.dol_escape_htmltag(GETPOST('qty', 'alpha')).'">';
	print '</td></tr>';

	// Default location flag
	print '<tr><td>'.$langs->trans('IsDefault').'</td><td>';
	print '<input type="checkbox" name="is_default" value="1"'.(GETPOSTINT('is_default') ? ' checked' : '').'>';
	print '</td></tr>';

	// Notes
	print '<tr><td>'.$langs->trans('NotePrivate').'</td><td>';
	print '<textarea name="note_private" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag(GETPOST('note_private', 'restricthtml'), 0, 1).'</textarea>';
	print '</td></tr>';

	// ExtraFields
	print $object->showOptionals($extrafields, 'create');

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('Create').'">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';

	// JS: reload level fields when warehouse changes on create form
	$ajax_url = dol_buildpath('/wareloc/ajax/getlevelfields.php', 1);
	print '<script>
	$(document).ready(function() {
		$("select[name=fk_entrepot]").on("change", function() {
			var wh = $(this).val();
			$.get("'.dol_escape_js($ajax_url).'", {fk_entrepot: wh, prefix: "wareloc", mode: "edit"}, function(html) {
				$("#wareloc_level_fields").html(html);
			});
		});
	});
	</script>';

} elseif ($object->id > 0) {

	// CONFIRMATION DIALOGS
	if ($action === 'validate') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('Validate'), $langs->trans('Confirm'), 'confirm_validate', '', '', 1);
	}
	if ($action === 'setmoved') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('StatusMoved'), $langs->trans('Confirm'), 'confirm_setmoved', '', '', 1);
	}
	if ($action === 'cancel_record') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('StatusCancelled'), $langs->trans('Confirm'), 'confirm_cancel', '', '', 1);
	}
	if ($action === 'delete') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('Confirm'), 'confirm_delete', '', '', 1);
	}

	// CARD VIEW / EDIT
	$head = productlocation_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('ProductLocation'), -1, 'stock');

	$linkback = '<a href="'.dol_buildpath('/wareloc/productlocation_list.php', 1).'">'.$langs->trans('BackToList').'</a>';

	dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

	if ($action === 'edit' && $permwrite) {
		// EDIT FORM
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfieldedit">';

		// Product
		print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Product').'</td><td>';
		print $form->select_produits($object->fk_product, 'fk_product', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth500 widthcentpercentminusxx');
		print '</td></tr>';

		// Warehouse
		print '<tr><td class="fieldrequired">'.$langs->trans('Warehouse').'</td><td>';
		require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
		$formproduct = new FormProduct($db);
		$formproduct->selectWarehouses($object->fk_entrepot, 'fk_entrepot', '', 1);
		print '</td></tr>';

		// Location levels
		if (!empty($levels)) {
			$values = array();
			for ($i = 1; $i <= 6; $i++) {
				$values['level_'.$i] = $object->{'level_'.$i};
			}
			print wareloc_render_level_fields($levels, $values, 'wareloc', 'edit');
		}

		// Quantity
		print '<tr><td>'.$langs->trans('Quantity').'</td><td>';
		print '<input type="text" name="qty" class="flat maxwidth100" value="'.dol_escape_htmltag($object->qty).'">';
		print '</td></tr>';

		// Default
		print '<tr><td>'.$langs->trans('IsDefault').'</td><td>';
		print '<input type="checkbox" name="is_default" value="1"'.($object->is_default ? ' checked' : '').'>';
		print '</td></tr>';

		// ExtraFields
		print $object->showOptionals($extrafields, 'edit');

		print '</table>';
		print '</div>';

		print dol_get_fiche_end();

		print '<div class="center">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
		print '</div>';

		print '</form>';

	} else {
		// VIEW MODE
		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">';

		// Product
		print '<tr><td class="titlefield">'.$langs->trans('Product').'</td><td>';
		if ($object->fk_product > 0) {
			$product = new Product($db);
			if ($product->fetch($object->fk_product) > 0) {
				print $product->getNomUrl(1);
			}
		}
		print '</td></tr>';

		// Warehouse
		print '<tr><td>'.$langs->trans('Warehouse').'</td><td>';
		if ($object->fk_entrepot > 0) {
			$entrepot = new Entrepot($db);
			if ($entrepot->fetch($object->fk_entrepot) > 0) {
				print $entrepot->getNomUrl(1);
			}
		}
		print '</td></tr>';

		// Location levels (view)
		if (!empty($levels)) {
			$values = array();
			for ($i = 1; $i <= 6; $i++) {
				$values['level_'.$i] = $object->{'level_'.$i};
			}
			print wareloc_render_level_fields($levels, $values, 'wareloc', 'view');
		}

		// Location path (combined)
		print '<tr><td>'.$langs->trans('LocationPath').'</td><td>';
		print $object->getLocationLabel();
		print '</td></tr>';

		// Quantity
		print '<tr><td>'.$langs->trans('Quantity').'</td><td>';
		print dol_escape_htmltag($object->qty);
		print '</td></tr>';

		// Default flag
		print '<tr><td>'.$langs->trans('IsDefault').'</td><td>';
		print $object->is_default ? img_picto('Yes', 'tick') : '';
		print '</td></tr>';

		// Reception
		if ($object->fk_reception > 0) {
			print '<tr><td>'.$langs->trans('Reception').'</td><td>';
			print '#'.$object->fk_reception;
			print '</td></tr>';
		}

		// Status
		print '<tr><td>'.$langs->trans('Status').'</td><td>';
		print $object->getLibStatut(5);
		print '</td></tr>';

		// ExtraFields
		print $object->showOptionals($extrafields, 'view');

		print '</table>';
		print '</div>';

		print dol_get_fiche_end();

		// Linked objects
		$object->showLinkedObjectBlock();

		// Action buttons
		print '<div class="tabsAction">';

		if ($object->status == ProductLocation::STATUS_DRAFT && $permwrite) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'">'.$langs->trans('Modify').'</a>';
		}

		if ($object->status == ProductLocation::STATUS_DRAFT && $permvalidate) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=validate&token='.newToken().'">'.$langs->trans('Validate').'</a>';
		}

		if ($object->status == ProductLocation::STATUS_ACTIVE && $permwrite) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=setmoved&token='.newToken().'">'.$langs->trans('StatusMoved').'</a>';
		}

		if (in_array($object->status, array(ProductLocation::STATUS_DRAFT, ProductLocation::STATUS_ACTIVE)) && $permwrite) {
			print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancel_record&token='.newToken().'">'.$langs->trans('StatusCancelled').'</a>';
		}

		if ($permdelete) {
			print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
		}

		print '</div>';
	}
}

llxFooter();
$db->close();
