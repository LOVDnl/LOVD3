<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-03-01
 * Modified    : 2012-03-01
 * For LOVD    : 3.0-beta-03
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '../');
}
require_once ROOT_PATH . 'inc-init.php';

if ($_AUTH['level'] < LEVEL_MANAGER) {
    exit(AJAX_NO_AUTH);
}



// Define the list of VariantOnTranscript columns once and for all.
$aVOTCols = array('VariantOnTranscript/Distance_to_splice_site',
                  'VariantOnTranscript/GVS/Function',
                  'VariantOnTranscript/PolyPhen',
                  'VariantOnTranscript/Position');

// We also need to get a list of standard VariantOnTranscript columns.
$aColsStandard = $_DB->query('SELECT id FROM ' . TABLE_COLS . ' WHERE standard = 1 AND id IN ("' . implode('", "', $aVOTCols) . '")')->fetchAllColumn();




$sColumnMessage = '';
if (!$_DB->query('SELECT colid FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = "VariantOnGenome/Conservation_score/GERP"')->fetchColumn()) {
    // Check whether the GERP column is enabled.
    $sColumnMessage = '<BR>VariantOnGenome/Conservation_score/GERP: currently not enabled (<A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'columns/VariantOnGenome/Conservation_score/GERP?add&amp;in_window=true\', \'col\', 800, 300); return false;">enable</A>)';
}

// Check if all VariantOnTranscript columns are activated for all genes and whether they are standard.
$nGenes = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES)->fetchColumn();
$aColCounts = $_DB->query('SELECT colid, COUNT(*) AS count FROM ' . TABLE_SHARED_COLS . ' WHERE colid IN ("' . implode('", "', $aVOTCols) . '") GROUP BY colid')->fetchAllCombine();
foreach ($aVOTCols as $sCol) {
    $b = true;
    if ((!isset($aColCounts[$sCol]) && $nGenes) || (isset($aColCounts[$sCol]) && $aColCounts[$sCol] != $nGenes)) {
        $sColumnMessage .= '<BR>' . $sCol . ': not enabled for some existing genes (<A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'columns/' . $sCol . '?add&amp;in_window=true\', \'col\', 800, 450); return false;">enable</A>)';
        $b = false;
    }
    if (!in_array($sCol, $aColsStandard)) {
        if ($b) {
            $sColumnMessage .= '<BR>' . $sCol . ': ';
        } else {
            $sColumnMessage .= ' and ';
        }
        $sColumnMessage .= 'not enabled for new genes (<A href="#" onclick="lovd_setStandardColumn(\'' . $sCol . '\'); return false;">make standard</A>)';
    }
}

if (!empty($sColumnMessage)) {
    // Only show the infoTable if we have found problematic columns.
    lovd_showInfoTable('SeattleSeq files may contain additional annotations that can be imported into LOVD. To import this data into existing genes, the relevant columns need to be enabled for those genes.' . "\n" .
                       'To import this data into genes that will be created during import, the columns need to be set to \'standard\' so that they are enabled for the new genes.' . "\n" .
                       '(<A href="#" onclick="lovd_checkColumns(); return false;">Re-check</A>)<BR>' . $sColumnMessage);
}
?>