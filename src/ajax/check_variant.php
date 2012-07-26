<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-05-25
 * Modified    : 2012-07-11
 * For LOVD    : 3.0-beta-07
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
if (empty($_GET['variant']) || empty($_GET['gene']) || !in_array($_GET['gene'], $aGenes) || !preg_match('/^(UD_\d{12}\(' . $_GET['gene'] . '_v\d{3}\)):c\..+$/', $_GET['variant'], $aVariantMatches)) {
    die(AJAX_DATA_ERROR);
}
$sProteinPrefix = str_replace('_v', '_i', $aVariantMatches[1]);

// Requires at least LEVEL_SUBMITTER, anything lower has no $_AUTH whatsoever.
if (!$_AUTH) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

require ROOT_PATH . 'class/REST2SOAP.php';
$_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
$aOutput = $_MutalyzerWS->moduleCall('runMutalyzer', array('variant' => $_GET['variant']));
if (is_array($aOutput) && !empty($aOutput)) {
    if (!empty($aOutput['messages'][0]['c'])) {
        $aMessages = lovd_getChildFromElement('messages', $aOutput);

        foreach ($aMessages['SoapMessage'] as $aMessage) {
            if (isset($aMessage['c']['errorcode'])) {
                print(trim($aMessage['c']['errorcode'][0]['v']) . ':' . trim($aMessage['c']['message'][0]['v']));
            }
            print('|');
        }
    } else {
        print('|');
    }
    $sProteinDescriptions = implode('|', lovd_getAllValuesFromSingleElement('proteinDescriptions/string', $aOutput));
    preg_match('/' . preg_quote($sProteinPrefix) . ':(p\..+?)(\||$)/', $sProteinDescriptions, $aProteinMatches);
    print('|' . (isset($aProteinMatches[1])? $aProteinMatches[1] : ''));
} else {
    die(AJAX_FALSE);
}
?>
