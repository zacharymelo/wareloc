<?php
/* Copyright (C) 2026 Zachary Melo */

if (empty($conf) || !is_object($conf)) { print "Error"; exit(1); }
print "<!-- BEGIN PHP TEMPLATE wareloc/productlocation/tpl/linkedobjectblock.tpl.php -->\n";
$langs->load("wareloc@wareloc");

$ilink = 0;
foreach ($linkedObjectBlock as $key => $objectlink) {
	$ilink++;
	$trclass = 'oddeven';
	if ($ilink == count($linkedObjectBlock) && empty($noMoreLinkedObjectBlockAfter) && count($linkedObjectBlock) <= 1) {
		$trclass .= ' liste_sub_total';
	}
	print '<tr class="'.$trclass.'">';
	print '<td class="linkedcol-element tdoverflowmax100">'.$langs->trans("ProductLocation").'</td>';
	print '<td class="linkedcol-name tdoverflowmax150">'.$objectlink->getNomUrl(1).'</td>';
	print '<td class="linkedcol-ref tdoverflowmax150">';
	if (method_exists($objectlink, 'getLocationLabel')) {
		print $objectlink->getLocationLabel();
	}
	print '</td>';
	print '<td class="linkedcol-date center">'.dol_print_date($objectlink->date_creation, 'day').'</td>';
	print '<td class="linkedcol-amount right">'.(isset($objectlink->qty) ? $objectlink->qty : '').'</td>';
	print '<td class="linkedcol-statut right">'.$objectlink->getLibStatut(3).'</td>';
	print '<td class="linkedcol-action right"><a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=dellink&token='.newToken().'&dellinkid='.$key.'">'.img_picto($langs->transnoentitiesnoconv("RemoveLink"), 'unlink').'</a></td>';
	print "</tr>\n";
}
print "<!-- END PHP TEMPLATE -->\n";
