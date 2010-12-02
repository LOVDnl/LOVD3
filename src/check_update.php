<?php
// FIXME; recompare to LOVD 2.0 version, because it has changed significantly.
// DMD_SPECIFIC; finish this file later when website has package_update.php
/******************************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-15
 * Modified    : 2010-01-28
 * For LOVD    : 3.0-pre-02
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
    lovd_requireAuth(LEVEL_CURATOR);
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
    $sServer = rawurlencode($sServer . ' MySQL/' . mysql_get_server_info());
    $sURLVars .= '&software=' . $sServer;
    $sGeneList = '';

    if ($_CONF['send_stats']) {
        // Collect stats...
        // Number of users.
        list($nUsers) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_USERS));
        $sURLVars .= '&user_count=' . $nUsers;

        // Number of genes.
        $aGenes = lovd_getGeneList();
        $nGenes = count($aGenes);
        $sGeneList = implode(',', $aGenes);
        $sURLVars .= '&gene_count=' . $nGenes;

        // Patient count.
        list($nPatients) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_PATIENTS));
        $sURLVars .= '&patient_count=' . $nPatients;

        // Number of unique variants.
// DMD_SPECIFIC, I disabled this.
//        list($nUniqueVariants) = mysql_fetch_row(mysql_query('SELECT COUNT(DISTINCT geneid, REPLACE(REPLACE(REPLACE(`Variant/DNA`, "(", ""), ")", ""), "?", "")) FROM ' . TABLE_VARIANTS));
list($nUniqueVariants) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS));
        $sURLVars .= '&uniquevariant_count=' . $nUniqueVariants;

// DMD_SPECIFIC, I disabled this part. Fix it. Re-enable CurrDB.
        // Number of variants.
        // $_CURRDB does not necessarily exists, if there is no gene this will return an error.
        if (false && isset($_CURRDB) && $_CURRDB->colExists('Patient/Times_Reported')) {
            list($nVariants) = mysql_fetch_row(lovd_queryDB('SELECT SUM(p.`Patient/Times_Reported`) FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_VARIANTS . ' AS v ON (p.id = v.patientid)'));
            settype($nVariants, 'int'); // Convert NULL to 0.
        } else {
            list($nVariants) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_VARIANTS));
        }
        $sURLVars .= '&variant_count=' . $nVariants;
    }

    if ($_CONF['include_in_listing']) {
        // Fetch install directory and gene listings.
        $sURLVars .= '&install_name=' . rawurlencode($_CONF['system_title']);

        // Get the installation location from the database, if available.
        if (!empty($_CONF['location_url'])) {
            $sInstallDir = $_CONF['location_url'];
        } else {
            $sInstallDir = PROTOCOL . $_SERVER['HTTP_HOST'] . lovd_cleanDirName(dirname($_SERVER['PHP_SELF']) . '/' . ROOT_PATH);
        }
        $sURLVars .= '&install_dir=' . rawurlencode($sInstallDir) . '&gene_listing=' . rawurlencode($sGeneList);

        // Send setting for wiki indexing.
        list($bAllowIndex) = mysql_fetch_row(lovd_queryDB('SELECT MAX(allow_index_wiki) FROM ' . TABLE_GENES));
        $sURLVars .= '&allow_index_wiki=' . (int) $bAllowIndex;
    }
////////////////////////////////////////////////////////////////////////////////

    // Contact upstream.
// DMD_SPECIFIC; fix this, test all this code below.
    $aOutput = lovd_php_file($_SETT['update_URL'] . $sURLVars);

    $sUpdates = '';
    foreach ($aOutput as $sLine) {
        if (!trim($sLine)) {
            break;
        }
        $sUpdates .= $sLine;
    }

    $sNow = date('Y-m-d H:i:s');
    if (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*' . $_SETT['system']['version'] . '(\nReleased\s*:\s*[0-9]{4}\-[0-9]{2}\-[0-9]{2})?$/', $sUpdates)) {
        // No update available.
        lovd_queryDB('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = ?, update_level = 0, update_description = "", update_released_date = NULL', array($sNow, $_SETT['system']['version']));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = $_SETT['system']['version'];
        $_STAT['update_released_date'] = '';
        $_STAT['update_level'] = 0;
        $_STAT['update_description'] = '';

    } elseif (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*([1-9]\.[0-9](\.[0-9])?(\-[0-9a-z-]{2,11})?)(\nReleased\s*:\s*[0-9]{4}\-[0-9]{2}\-[0-9]{2})?$/', $sUpdates, $aUpdates) && is_array($aUpdates)) {
        // Weird version conflict?
        lovd_writeLog('Error', 'CheckUpdate', 'Version conflict while parsing upstream server output: current version (' . $_SETT['system']['version'] . ') > ' . $aUpdates[1]);
        lovd_queryDB('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = "Error", update_level = 0, update_description = "", update_released_date = NULL', array($sNow));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = 'Error';
        $_STAT['update_released_date'] = '';
        $_STAT['update_level'] = 0;
        $_STAT['update_description'] = '';

    } elseif (preg_match('/^Package\s*:\s*LOVD\nVersion\s*:\s*([1-9]\.[0-9](\.[0-9])?\-([0-9a-z-]{2,11}))(\nReleased\s*:\s*([0-9]{4}\-[0-9]{2}\-[0-9]{2}))?\nPriority\s*:\s*([0-9])\nDescription\s*:\s*(.+)$/', $sUpdates, $aUpdates) && is_array($aUpdates)) {
        // Now update the database - new version detected.
        lovd_queryDB('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = ?, update_level = ?, update_description = ?, update_releaseded_date = ?', array($sNow, $aUpdates[1], $aUpdates[6], $aUpdates[7], $aUpdates[5]));
        $_STAT['update_checked_date'] = $sNow;
        $_STAT['update_version'] = $aUpdates[1];
        $_STAT['update_released_date'] = $aUpdates[5];
        $_STAT['update_level'] = $aUpdates[6];
        $_STAT['update_description'] = $aUpdates[7];

    } else {
        // Error during update check.
        lovd_writeLog('Error', 'CheckUpdate', 'Could not parse upstream server output:' . "\n" . $sUpdates);
        lovd_queryDB('UPDATE ' . TABLE_STATUS . ' SET update_checked_date = ?, update_version = "Error", update_level = 0, update_description = "", update_released_date = NULL', array($sNow));
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
                '<B>Release info</B>: ' . $_STAT['update_description'] . '<BR>' . "\n" .
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
    require ROOT_PATH . 'inc-top-clean.php';
    
    // 2009-02-25; 2.0-16; Added "check now" option.
    print('      <TABLE border="0" cellpadding="2" cellspacing="0" width="100%" class="info" style="font-size : 11px;">' . "\n" .
          '        <TR>' . "\n" .
          '          <TD valign="top" align="center" width="40"><IMG src="gfx/lovd_update_' . $sType . '.png" alt="' . ucfirst($sType) . '" title="' . ucfirst($sType) . '" width="32" height="32" hspace="4" vspace="4"></TD>' . "\n" .
          '          <TD valign="middle">Last checked for updates ' . date('Y-m-d H:i:s', strtotime($_STAT['update_checked_date'])) . ' (<A href="check_update?force_check=' . md5($_STAT['update_checked_date']) . '">check now</A>)<BR>' . "\n" .
          '            ' . str_replace("\n", "\n" . '            ', $sMessage) . '</TD></TR></TABLE>' . "\n\n");
    
    require ROOT_PATH . 'inc-bot-clean.php';
}
?>