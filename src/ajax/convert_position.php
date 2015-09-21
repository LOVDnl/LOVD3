<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-09
 * Modified    : 2015-09-21
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
if (empty($_GET['variant']) || !preg_match('/^([A-Z]{2}_\d{6,9}\.\d{1,2}(\([A-Za-z0-9-]+_v\d{3}\))?:[cn])|(chr.{0,2}:[gm])\..+$/', $_GET['variant']) || empty($_GET['gene']) || !in_array($_GET['gene'], $aGenes)) {
    die(AJAX_DATA_ERROR);
}

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

require ROOT_PATH . 'class/soap_client.php';
$_Mutalyzer = new LOVD_SoapClient();

try {
    $oOutput = $_Mutalyzer->numberConversion(array('build' => $_CONF['refseq_build'], 'variant' => $_GET['variant'], 'gene' => $_GET['gene']))->numberConversionResult;
} catch (SoapFault $e) {
    // FIXME: Perhaps indicate an error? Like in the check_hgvs script?
    die(AJAX_FALSE);
}
if ($oOutput && isset($oOutput->string)) {
    $sVariants = implode(';', $oOutput->string);
    die($sVariants);
} else {
    die(AJAX_FALSE);
}
?>
