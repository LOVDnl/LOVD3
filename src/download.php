<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-10
 * Modified    : 2012-06-11
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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

$_GET['format'] = 'text/plain'; // To make sure all possible error functions output text.
define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

// FIXME; koppelinstabellen missen nog. SCR2VAR, SCR2GENES, DIS2GENES, IND2DIS.
header('Content-type: text/plain; charset=UTF-8');





// None of the URLs accept an ACTION, all require at least $_PE[1].
if (ACTION || PATH_COUNT < 2) {
    exit;
}





if ($_PE[1] == 'all' && (empty($_PE[2]) || $_PE[2] == 'mine')) {
    // URL: /download/all
    // URL: /download/all/mine
    // Download all data from the database, possibly restricted by ownership.

    if (empty($_PE[2])) {
        $nID = 0;
        lovd_requireAuth(LEVEL_MANAGER);
    } else {
        $nID = $_AUTH['id'];
        lovd_requireAuth();
    }

    // If we get here, we can print the header already.
    header('Content-Disposition: attachment; filename="LOVD_' . ($nID? 'owned_data' : 'full_download') . '_' . date('Y-m-d_H.i.s') . '.txt"');
    header('Pragma: public');
    print('### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### ' . ($nID? 'Owned' : 'Full') . ' data download ### To import, do not remove this header ###' . "\r\n");
    if ($nID) {
        print('## Filter: (created_by = ' . $nID . ' || owned_by = ' . $nID . ')' . "\r\n");
    }
    print('# charset=UTF-8' . "\r\n\r\n");



    // Prepare file creation by defining headers, columns and filters.
    // All data types have same settings: optional ownership filter, no columns hidden.
    $aDataTypeSettings = 
         array(
                'hide_columns' => array(),
                'filters' => array(),
              );
    if ($nID) {
        $aDataTypeSettings['filters']['owner'] = $nID;
    }
    $aFormat =
         array(
                'Individuals' => $aDataTypeSettings,
                'Phenotypes'  => $aDataTypeSettings,
                'Screenings' => $aDataTypeSettings,
                'Variants' => $aDataTypeSettings,
                'Variants_On_Transcripts' => $aDataTypeSettings,
              );
}





if (empty($aFormat) || !is_array($aFormat)) {
    // File format has not been defined, exit.
    exit;
}





foreach ($aFormat as $sObject => $aSettings) {
    print('## ' . $sObject . ' ## Do not remove this header ##' . "\r\n");

    $sTable = @constant('TABLE_' . strtoupper($sObject));
    if (!$sTable) {
        print('Error: could not find data table.' . "\r\n");
        continue; // Perhaps break?
    }

    // We could fetch the first row and analyze, but I'd rather do it separately.
    // FIXME; Apply some sorting mechanism. Based on average order?
    $aColumns = $_DB->query('DESCRIBE ' . $sTable)->fetchAllColumn();

    // Ugly hack: we will change $sTable for the VOT to a string that joins VOG such that we can apply filters.
    if ($sObject == 'Variants_On_Transcripts') {
        $sTable = TABLE_VARIANTS_ON_TRANSCRIPTS . ' INNER JOIN ' . TABLE_VARIANTS . ' USING (id)';
    }

    $sWHERE = '';
    $aArgs = array();
    if ($aSettings['filters']) {
        $sWHERE = ' WHERE ';
        $i = 0;
        foreach ($aSettings['filters'] as $sFilter => $sValue) {
            $sWHERE .= (!$i++? '' : ' AND ');
            switch ($sFilter) {
                case 'owner':
                    $sWHERE .= '(created_by = ? OR owned_by = ?)';
                    $aArgs[] = $_AUTH['id'];
                    $aArgs[] = $_AUTH['id'];
                    break;
                default:
                    // Filter not understood.
                    $sWHERE .= '1=1';
            }
        }
    }

    // First, print counts. This is as information for the user, but also it shows easily when there is no data to show.
    $nCount = $_DB->query('SELECT COUNT(*) FROM ' . $sTable . $sWHERE, $aArgs)->fetchColumn();
    print('## Count = ' . $nCount . "\r\n");
    if (!$nCount) {
        // Nothing to print...
        print("\r\n\r\n");
        continue;
    }





    // Print headers.
    foreach ($aColumns as $key => $sCol) {
        print((!$key? '' : "\t") . '"{{' . $sCol . '}}"');
    }
    print("\r\n");

    // Fetch and print the data.
    $q = $_DB->query('SELECT * FROM ' . $sTable . $sWHERE, $aArgs);
    while ($z = $q->fetchAssoc()) {
        // Quote data.
        $z = array_map('addslashes', $z);

        foreach ($aColumns as $key => $sCol) {
            // Replace line endings and tabs (they should not be there but oh well), so they don't cause problems with importing.
            print(($key? "\t" : '') . '"' . str_replace(array("\r\n", "\r", "\n", "\t"), array('\r\n', '\r', '\n', '\t'), $z[$sCol]) . '"');
        }
        print("\r\n");
    }
    print("\r\n\r\n");
}
?>
