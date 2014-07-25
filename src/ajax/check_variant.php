<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-25
 * Modified    : 2014-07-25
 * For LOVD    : 3.0-11
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
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
if (empty($_GET['variant']) || empty($_GET['gene']) || !in_array($_GET['gene'], $aGenes) || !preg_match('/^((UD_\d{12}|NG_\d{6,}\.\d{1,2})\(' . $_GET['gene'] . '_v\d{3}\)):[cn]\..+$/', $_GET['variant'], $aVariantMatches)) {
    die(AJAX_DATA_ERROR);
}
$sProteinPrefix = str_replace('_v', '_i', $aVariantMatches[1]);

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

require ROOT_PATH . 'class/soap_client.php';
$_Mutalyzer = new LOVD_SoapClient();
try {
    $oOutput = $_Mutalyzer->runMutalyzer(array('variant' => $_GET['variant']))->runMutalyzerResult;
} catch (SoapFault $e) {
    // FIXME: Perhaps indicate an error? Like in the check_hgvs script?
    die(AJAX_FALSE);
}

if (!empty($oOutput->messages->SoapMessage)) {
    foreach ($oOutput->messages->SoapMessage as $oMessage) {
        if (isset($oMessage->errorcode)) {
            print(trim($oMessage->errorcode) . ':' . trim($oMessage->message));
        }
        print('|');
    }
} else {
    print('|');
}
$sProteinDescriptions = (empty($oOutput->proteinDescriptions->string)? '' : implode('|', $oOutput->proteinDescriptions->string));
preg_match('/' . preg_quote($sProteinPrefix) . ':(p\..+?)(\||$)/', $sProteinDescriptions, $aProteinMatches);
print('|' . (isset($aProteinMatches[1])? $aProteinMatches[1] : ''));
?>
