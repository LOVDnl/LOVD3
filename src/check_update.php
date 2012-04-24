<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-15
 * Modified    : 2012-04-18
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if (!isset($_GET['icon'])) {
    // Only authorized people...
    lovd_isAuthorized('gene', $_AUTH['curates']); // Will set user's level to LEVEL_CURATOR if he is one at all.
    lovd_requireAUTH(LEVEL_CURATOR);
}

// For the first time, or forced check.
if ($_STAT['update_checked_date'] == NULL || (isset($_GET['force_check']) && md5($_STAT['update_checked_date']) == $_GET['force_check'])) {
    // Any date surely in the past.
    $_STAT['update_checked_date'] = '1970-01-01';
}

// If the date of last update check was longer than one day ago, check again.
if ((time() - strtotime($_STAT['update_checked_date'])) > (60*60*24)) {
    // If we're checking for updates, we want to see if we're sending statistics as well.
    $sURLVars = '?version=' . $_SETT['system']['version'] . '&signature=' . $_STAT['signature'];
    // Some variables will be sent over POST, because of the size limit that GET has.
    $sPOSTVars = '';

    // Software information.
    $sServer = PHP_OS . ' ' . $_SERVER['SERVER_SOFTWARE'];
    // Remove excessive module information.
    if (preg_match('/^([^\(\)]+\(.+\))[^\(\)]+$/', $sServer, $aRegs)) {
        // Too much! Remove all after "(Platform)"!
        $sServer = $aRegs[1];
    }
    if (!substr_count($sServer, 'PHP')) {
        // PHP stuff hidden. Alright, then.
        $sServer .= ' PHP/' . PHP_VERSION;
    }
    $sServer .= ' MySQL/' . $_DB->getServerInfo();
    $sPOSTVars .= '&software=' . rawurlencode($sServer);
    $sGeneList = '';

    if ($_CONF['send_stats']) {
        // Collect stats...
        // Number of submitters.
        $nSubs = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' AS u WHERE u.id > 0 AND u.id NOT IN (SELECT c.userid FROM ' . TABLE_CURATES . ' AS c WHERE c.userid = u.id AND allow_edit = 1)')->fetchColumn();
        $sPOSTVars .= '&submitter_count=' . $nSubs;

        // Number of genes.
        $aGenes = lovd_getGeneList();
        $nGenes = count($aGenes);
        $sGeneList = implode(',', $aGenes); // Used later.
        $sPOSTVars .= '&gene_count=' . $nGenes;

        // Individual count.
        $nIndividuals = $_DB->query('SELECT SUM(panel_size) FROM ' . TABLE_INDIVIDUALS . ' WHERE statusid >= ' . STATUS_MARKED . ' AND panelid IS NULL')->fetchColumn();
        $sPOSTVars .= '&patient_count=' . $nIndividuals;

        // Number of unique variants.
        // FIXME; enforce there is no other value behind the DBID?
        $nUniqueVariants = $_DB->query('SELECT COUNT(DISTINCT `VariantOnGenome/DBID`) FROM ' . TABLE_VARIANTS . ' WHERE statusid >= ' . STATUS_MARKED)->fetchColumn();
        $sPOSTVars .= '&uniquevariant_count=' . $nUniqueVariants;

        // Number of variants.
        // FIXME: variants still need a "Variant/Times_reported" column for panels to indicate on how many chromosomes the variant was found. Now it defaults to 100%.
        // Various ways of doing this. The simplest query should use PHP to add up all the values. Could be quite time-consuming on large databases perhaps.
        // The subselect options do all the calculations in MySQL, and are therefore hopefully faster and more efficient.
        // WITH UNION                         SELECT COUNT(DISTINCT v.id) FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) WHERE v.statusid >= ' . STATUS_MARKED . ' AND s2v.screeningid IS NULL UNION ALL
        //                                    SELECT (IF(i.statusid < 7, 1, i.panel_size) * COUNT(DISTINCT v.id)) FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) INNER JOIN ' . TABLE_VARIANTS . ' AS v ON (s2v.variantid = v.id) WHERE v.statusid >= 7 GROUP BY i.id;
        //$nVariants = array_sum($_DB->query('SELECT COUNT(DISTINCT v.id) FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) WHERE v.statusid >= ' . STATUS_MARKED . ' AND s2v.screeningid IS NULL UNION ALL
        //                                    SELECT (IF(i.statusid < 7, 1, i.panel_size) * COUNT(DISTINCT v.id)) FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) INNER JOIN ' . TABLE_VARIANTS . ' AS v ON (s2v.variantid = v.id) WHERE v.statusid >= 7 GROUP BY i.id')->fetchAllColumn());
        // USING SUBSELECTS                   SELECT variants_without_individuals + SUM(variants_on_individuals) FROM (SELECT (IF(i.statusid < 7, 1, i.panel_size) * COUNT(DISTINCT v.id)) AS variants_on_individuals FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) INNER JOIN ' . TABLE_VARIANTS . ' AS v ON (s2v.variantid = v.id) WHERE v.statusid >= 7 GROUP BY i.id) AS sub1, (SELECT COUNT(DISTINCT v.id) AS variants_without_individuals FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) WHERE v.statusid >= ' . STATUS_MARKED . ' AND s2v.screeningid IS NULL) AS sub2;
        //$nVariants = array_sum($_DB->query('SELECT variants_without_individuals + SUM(variants_on_individuals) FROM (SELECT (IF(i.statusid < 7, 1, i.panel_size) * COUNT(DISTINCT v.id)) AS variants_on_individuals FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) INNER JOIN ' . TABLE_VARIANTS . ' AS v ON (s2v.variantid = v.id) WHERE v.statusid >= 7 GROUP BY i.id) AS sub1, (SELECT COUNT(DISTINCT v.id) AS variants_without_individuals FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) WHERE v.statusid >= ' . STATUS_MARKED . ' AND s2v.screeningid IS NULL) AS sub2')->fetchAllColumn());
        // EVEN SHORTER                     SELECT SUM(v) FROM (SELECT (IFNULL(IF(i.statusid < 7, 1, i.panel_size), 1) * COUNT(DISTINCT v.id)) AS v FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) WHERE v.statusid >= 7 GROUP BY i.id) AS sub
        $nVariants = array_sum($_DB->query('SELECT SUM(v) FROM (SELECT (IFNULL(IF(i.statusid < 7, 1, i.panel_size), 1) * COUNT(DISTINCT v.id)) AS v FROM ' . TABLE_VARIANTS . ' AS v LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) WHERE v.statusid >= 7 GROUP BY i.id) AS sub')->fetchAllColumn());
        $sPOSTVars .= '&variant_count=' . $nVariants;
    }

    if ($_CONF['include_in_listing']) {
        // Fetch install directory and gene listings.
        $sPOSTVars .= '&install_name=' . rawurlencode($_CONF['system_title']);

        // Get the installation location from the database, if available.
        $sInstallDir = (!empty($_CONF['location_url'])? $_CONF['location_url'] : lovd_getInstallURL());
        $sPOSTVars .= '&install_dir=' . rawurlencode($sInstallDir) . '&gene_listing=' . rawurlencode($sGeneList);

        // Send gene edit dates, curator names, emails & institutes as well.
        // This is not very efficient, but for something done once a day (max) it will do.
        $aData = array('genes' => array(), 'users' => array(), 'diseases' => array());
        $aUserIDs = array(); // Temporary array for query purposes only.
        $aDiseaseIDs = array(); // Temporary array for query purposes only.

        // First, get the gene info (we store name, diseases, date last updated and curator ids).
        $q = $_DB->query('SELECT g.id, g.name, g.updated_date, GROUP_CONCAT(DISTINCT u2g.userid ORDER BY u2g.show_order) AS users, GROUP_CONCAT(DISTINCT d.id ORDER BY d.name) AS diseases FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid AND u2g.allow_edit = 1 AND u2g.show_order > 0) LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) WHERE u2g.show_order > 0 GROUP BY g.id ORDER BY g.id', array());
        while ($z = $q->fetchAssoc()) {
            $aData['genes'][$z['id']] =
                     array(
                            'gene_name' => $z['name'],
                            'diseases' => explode(',', $z['diseases']),
                            'updated_date' => $z['updated_date'],
                            'curators' => explode(',', $z['users']));
            $aUserIDs = array_merge($aUserIDs, explode(',', $z['users']));
            if ($z['diseases']) {
                $aDiseaseIDs = array_merge($aDiseaseIDs, explode(',', $z['diseases']));
            }
        }
        $aUserIDs = array_values(array_unique($aUserIDs)); // Keys must be in order, otherwise PDO returns a query error.
        $aDiseaseIDs = array_values(array_unique($aDiseaseIDs)); // Keys must be in order, otherwise PDO returns a query error.

        // Then, get the actual curator data (name, email, institute).
        $q = $_DB->query('SELECT id, name, email, institute FROM ' . TABLE_USERS . ' WHERE id IN (?' . str_repeat(', ?', count($aUserIDs)-1) . ') ORDER BY id', $aUserIDs, false);
        while ($z = $q->fetchAssoc()) {
            $aData['users'][$z['id']] = array('name' => $z['name'], 'email' => $z['email'], 'institute' => $z['institute']);
        }

        // Finally, get the actual disease data (ID, symbol, name).
        $q = $_DB->query('SELECT id, symbol, name FROM ' . TABLE_DISEASES . ' WHERE id IN (?' . str_repeat(', ?', count($aDiseaseIDs)-1) . ') ORDER BY id', $aDiseaseIDs, false);
        while ($z = $q->fetchAssoc()) {
            $aData['diseases'][$z['id']] = array('symbol' => $z['symbol'], 'name' => $z['name']);
        }
        $sData = serialize($aData);
        $sPOSTVars .= '&data=' . rawurlencode($sData);

        // Send setting for wiki indexing.
        $bAllowIndex = $_DB->query('SELECT MAX(allow_index_wiki) FROM ' . TABLE_GENES)->fetchColumn();
        $sPOSTVars .= '&allow_index_wiki=' . (int) $bAllowIndex;
    }

    // Contact upstream.
    $aOutput = lovd_php_file($_SETT['update_URL'] . $sURLVars, false, ltrim($sPOSTVars, '&'));
    // Check if output is valid.
    $sUpdates = (!is_array($aOutput)? '' : implode("\n", $aOutput));

    $sNow = date('Y-m-d H:i:s');
    if (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*' . $_SETT['system']['version'] . '(\nReleased\s*:\s*[0-9]{4}\-[0-9]{2}\-[0-9]{2})?$/', $sUpdates)) {
        // No update available.
        $_DB->query('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = ?, update_level = 0, update_description = "", update_released_date = NULL', array($sNow, $_SETT['system']['version']));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = $_SETT['system']['version'];
        $_STAT['update_released_date'] = '';
        $_STAT['update_level'] = 0;
        $_STAT['update_description'] = '';

    } elseif (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*([1-9]\.[0-9](\.[0-9])?(\-[0-9a-z-]{2,11})?)(\nReleased\s*:\s*[0-9]{4}\-[0-9]{2}\-[0-9]{2})?$/', $sUpdates, $aUpdates) && is_array($aUpdates)) {
        // Weird version conflict?
        // FIXME; shouldn't we check maybe if the given version *IS* actually newer? Maybe it's just a non-registered release?
        lovd_writeLog('Error', 'CheckUpdate', 'Version conflict while parsing upstream server output: current version (' . $_SETT['system']['version'] . ') > ' . $aUpdates[1]);
        $_DB->query('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = "Error", update_level = 0, update_description = "", update_released_date = NULL', array($sNow));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = 'Error';
        $_STAT['update_released_date'] = '';
        $_STAT['update_level'] = 0;
        $_STAT['update_description'] = '';

    } elseif (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*([1-9]\.[0-9](\.[0-9])?\-([0-9a-z-]{2,11}))(\nReleased\s*:\s*([0-9]{4}\-[0-9]{2}\-[0-9]{2}))?\nPriority\s*:\s*([0-9])\nDescription\s*:\s*(.+)$/s', $sUpdates, $aUpdates) && is_array($aUpdates)) {
        // Now update the database - new version detected.
        $_DB->query('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = ?, update_level = ?, update_description = ?, update_released_date = ?', array($sNow, $aUpdates[1], $aUpdates[6], $aUpdates[7], $aUpdates[5]));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = $aUpdates[1];
        $_STAT['update_released_date'] = $aUpdates[5];
        $_STAT['update_level'] = $aUpdates[6];
        $_STAT['update_description'] = rtrim($aUpdates[7]);

    } else {
        // Error during update check.
        lovd_writeLog('Error', 'CheckUpdate', 'Could not parse upstream server output:' . "\n" . $sUpdates);
        $_DB->query('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = "Error", update_level = 0, update_description = "", update_released_date = NULL', array($sNow));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = 'Error';
        $_STAT['update_released_date'] = '';
        $_STAT['update_level'] = 0;
        $_STAT['update_description'] = '';
    }
}



// Process...
if ($_STAT['update_version'] == 'Error') {
    $sType = 'error';
    $sMessage = 'An error occured while checking for updates. For more information, see the error log. Please try again later.';

} elseif (lovd_calculateVersion($_STAT['update_version']) > lovd_calculateVersion($_SETT['system']['version'])) {
    $sType = 'newer';
    $sMessage = 'There is an update to LOVD available. More information is below.<BR>' . "\n" .
                '<B>Latest version</B>: ' . $_STAT['update_version'] . '<BR>' . "\n" .
                '<B>Release date</B>: ' . $_STAT['update_released_date'] . '<BR>' . "\n" .
                '<B>Priority level</B>: ' . $_SETT['update_levels'][$_STAT['update_level']] . '<BR>' . "\n" .
                '<B>Release info</B>: ' . str_replace("\n", '<BR>', $_STAT['update_description']) . '<BR>' . "\n" .
                '<B>Download</B>: <A href="' . dirname($_SETT['update_URL']) . '/download.php?version=' . $_STAT['update_version'] . '&amp;type=tar.gz">GZIPped TARball</A> or <A href="' . dirname($_SETT['update_URL']) . '/download.php?version=' . $_STAT['update_version'] . '&amp;type=zip">ZIP archive</A><BR>' . "\n" .
                '<A href="' . $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/changelog.txt" target="_blank">See the changelog</A>' . "\n";

} else {
    $sType = 'newest';
    $sMessage = 'There are currently no updates. Your LOVD installation is completely up to date.';
}





// If we're requested to show the icon, we will do that and quit. Else we will provide some info.
if (isset($_GET['icon'])) {
    // Create icon.
    header('Content-type: image/png');
    readfile('gfx/lovd_update_' . $sType . '_blue.png');
    exit;

} else {
    // Print what we know about new versions...
    $_T->printHeader(false);
    
    print('      <TABLE border="0" cellpadding="2" cellspacing="0" width="100%" class="info" style="font-size : 11px;">' . "\n" .
          '        <TR>' . "\n" .
          '          <TD valign="top" align="center" width="40"><IMG src="gfx/lovd_update_' . $sType . '.png" alt="' . ucfirst($sType) . '" title="' . ucfirst($sType) . '" width="32" height="32" hspace="4" vspace="4"></TD>' . "\n" .
          '          <TD valign="middle">Last checked for updates ' . date('Y-m-d H:i:s', strtotime($_STAT['update_checked_date'])) . ' (<A href="check_update?force_check=' . md5($_STAT['update_checked_date']) . '">check now</A>)<BR>' . "\n" .
          '            ' . str_replace("\n", "\n" . '            ', $sMessage) . '</TD></TR></TABLE>' . "\n\n");
    
    $_T->printFooter();
}
?>
