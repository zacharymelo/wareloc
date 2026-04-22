<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/assign_location.php
 * \ingroup binloc
 * \brief   AJAX endpoint to assign a bin location during reception
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

dol_include_once('/binloc/class/binlocproductlocation.class.php');

header('Content-Type: application/json');

// Check permissions
if (!$user->hasRight('binloc', 'write')) {
	http_response_code(403);
	print json_encode(array('error' => 'Permission denied'));
	exit;
}

$fk_product  = GETPOSTINT('fk_product');
$fk_entrepot = GETPOSTINT('fk_entrepot');

if ($fk_product <= 0 || $fk_entrepot <= 0) {
	http_response_code(400);
	print json_encode(array('error' => 'Missing product or warehouse'));
	exit;
}

$loc = new BinlocProductLocation($db);
$loc->fk_product  = $fk_product;
$loc->fk_entrepot = $fk_entrepot;

$has_value = false;
for ($i = 1; $i <= 6; $i++) {
	$val = GETPOST('level'.$i.'_value', 'alphanohtml');
	$loc->{'level'.$i.'_value'} = $val;
	if ($val !== null && $val !== '') {
		$has_value = true;
	}
}

if (!$has_value) {
	http_response_code(400);
	print json_encode(array('error' => 'No location values provided'));
	exit;
}

$result = $loc->createOrUpdate($user);

if ($result > 0) {
	print json_encode(array('success' => true, 'id' => $result));
} else {
	http_response_code(500);
	print json_encode(array('error' => $loc->error ?: 'Unknown error'));
}

$db->close();
