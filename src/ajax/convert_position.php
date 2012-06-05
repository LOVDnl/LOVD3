<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-09
 * Modified    : 2012-05-24
 * For LOVD    : 3.0-beta-05
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
session_write_close();

$aGenes = lovd_getGeneList();
if (empty($_GET['variant']) || !preg_match('/^(N[RM]_\d{6,9}\.\d{1,2}:c)|(chr.{0,2}:g)\..+$/', $_GET['variant']) || empty($_GET['gene']) || !in_array($_GET['gene'], $aGenes)) {
    die(AJAX_DATA_ERROR);
}

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

require ROOT_PATH . 'class/REST2SOAP.php';
$_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);

$aOutput = $_MutalyzerWS->moduleCall('numberConversion', array('build' => 'hg19', 'variant' => $_GET['variant'], 'gene' => $_GET['gene']));
$sVariants = '';
if (isset($aOutput['string'])) {
    foreach($aOutput['string'] as $aVariant) {
        $sVariants .= ';' . $aVariant['v'];
    }
    $sVariants = ltrim($sVariants, ';');
    print($sVariants);
} else {
    die(AJAX_FALSE);
}
?>
