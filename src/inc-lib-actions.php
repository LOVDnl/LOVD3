<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-12-17
 * Modified    : 2022-11-22
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}





function lovd_addAllDefaultCustomColumns ($sObjectType, $ID, $nUserID = false)
{
    // This function enables all (HGVS)standard custom columns for the given gene or disease.
    global $_AUTH, $_DB;

    // In LOVD+, we don't "do" shared columns.
    if (LOVD_plus) {
        return false;
    }

    if ($sObjectType == 'gene') {
        $sCategory = 'VariantOnTranscript';
        $sTableName = TABLE_VARIANTS_ON_TRANSCRIPTS;
        $sSQLCols = '?, NULL';
    } elseif ($sObjectType == 'disease') {
        $sCategory = 'Phenotype';
        $sTableName = TABLE_PHENOTYPES;
        $sSQLCols = 'NULL, ?';
    } else {
        return false;
    }

    if ($nUserID === false) {
        $nUserID = $_AUTH['id'];
    }

    // Gather all the required lists of columns.
    $aCols = $_DB->q('SELECT c.*, (ac.colid IS NOT NULL) AS active FROM ' . TABLE_COLS . ' AS c LEFT OUTER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id LIKE "' . $sCategory . '/%" AND (c.standard = 1 OR c.hgvs = 1)')->fetchAllAssoc();
    $aActiveChecked = $_DB->q('DESCRIBE ' . $sTableName)->fetchAllColumn();
    $nAdded = 0;

    // Loop columns to first do all ALTER TABLE's, if necessary.
    foreach ($aCols as $aCol) {
        if (!in_array($aCol['id'], $aActiveChecked)) {
            $_DB->q('ALTER TABLE ' . $sTableName . ' ADD COLUMN `' . $aCol['id'] . '` ' . stripslashes($aCol['mysql_type']));
        }
        if (!$aCol['active']) {
            $_DB->q('INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES(?, ?, NOW())', array($aCol['id'], $nUserID));
        }
    }

    // Then, actually add the column(s) to the specified object's data.
    foreach ($aCols as $aCol) {
        $q = $_DB->q('INSERT IGNORE INTO ' . TABLE_SHARED_COLS . ' VALUES (' . $sSQLCols . ', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL, NULL)', array($ID, $aCol['id'], $aCol['col_order'], $aCol['width'], $aCol['mandatory'], $aCol['description_form'], $aCol['description_legend_short'], $aCol['description_legend_full'], $aCol['select_options'], $aCol['public_view'], $aCol['public_add'], $nUserID));
        $nAdded += $q->rowCount();
    }

    return $nAdded;
}
?>
