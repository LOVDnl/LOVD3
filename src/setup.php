<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-11
 * Modified    : 2024-09-03
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

define('PAGE_TITLE', 'LOVD' . (LOVD_plus? '+' : '') . ' Setup');
$_T->printHeader();
$_T->printTitle();

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);





// Some info & statistics.
$nUsers       = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE id > 0')->fetchColumn();
$nLogs        = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_LOGS)->fetchColumn();
$nIndividuals = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS)->fetchColumn();
$nGenes       = count(lovd_getGeneList());
$aTotalVars   = array();
$nTotalVars   = 0;
if (!LOVD_plus) {
    $q = $_DB->q('SELECT COUNT(*), statusid FROM ' . TABLE_VARIANTS . ' GROUP BY statusid ORDER BY statusid');
    while ($r = $q->fetchRow()) {
        $aTotalVars[$r[1]] = $r[0];
        $nTotalVars += $r[0];
    }
}



// Setup main table.
print('      <TABLE border="0" cellpadding="0" cellspacing="0" width="100%">' . "\n" .
      '        <TR>' . "\n" .
      '          <TD valign="top" style="padding-right : 10px; border-right : 1px solid #224488;">' . "\n" .
      '            <TABLE border="0" cellpadding="0" cellspacing="0" class="setup" width="250">' . "\n" .
      '              <TR>' . "\n" .
      '                <TH>Leiden Open Variation Database</TH></TR>' . "\n" .
      '              <TR>' . "\n" .
      '                <TD>' . "\n" .
      '                  Installed : ' . $_STAT['installed_date'] . '<BR>' . "\n" .
      '                  Updated : ' . ($_STAT['updated_date']?: '-') . '</TD></TR>' . "\n" .
      '              <TR>' . "\n" .
      '                <TH>Statistics</TH></TR>' . "\n" .
      '              <TR>' . "\n" .
      '                <TD>' . "\n" .
      '                  Users : ' . $nUsers . '<BR>' . "\n" .
      '                  Log entries : ' . $nLogs . '<BR>----------<BR>' . "\n" .
      '                  Individuals : ' . $nIndividuals . '<BR>' . "\n" .
      '                  Genes : ' . $nGenes . '</TD></TR>');
if ($nTotalVars) {
    print("\n" .
          '              <TR>' . "\n" .
          '                <TH>Variants</TH></TR>' . "\n" .
          '              <TR>' . "\n" .
          '                <TD>' . "\n" .
          '                  Total : ' . $nTotalVars);
    foreach ($aTotalVars as $nStatus => $nVars) {
        print('<BR>' . "\n" .
            '                  ' . $_SETT['data_status'][$nStatus] . ' : ' . $nVars);
    }
    print('</TD></TR>');
}
print('</TABLE><BR>' . "\n\n");

// Mention that LOVD can be updated!
if (!LOVD_plus && $_STAT['update_level']) { // Not for LOVD+, unless we build a separate list.
    $_STAT['update_level'] = 7;
    lovd_showInfoTable('LOVD update available:<BR><B>' . $_STAT['update_version'] . '</B><BR>' . ($_STAT['update_level'] >= 7? ' It is ' . strtolower($_SETT['update_levels'][$_STAT['update_level']]) . ' to upgrade!' : '') . '<BR><A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'check_update\', \'CheckUpdate\', 650, 175); return false;">More information &raquo;</A>', ($_STAT['update_level'] >= 7? 'warning' : 'information'));
}

// Check if we have a system-default license, and if we need one at all anyway.
if ($_DB->q('SELECT default_license FROM ' . TABLE_USERS . ' WHERE id = 0')->fetchColumn() == '') {
    // There's no default license. Do we need one?
    if ($_DB->q('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE created_by = 0')->fetchColumn() > 0) {
        lovd_showInfoTable(
            'Some of the data in this LOVD instance isn\'t created by a specific user, but there is no default data license selected for these entries. Please help promote data sharing by selecting a default license for this data.',
            'warning',
            '100%',
            '$.get(\'ajax/licenses.php/user/00000?edit\').fail(function(){alert(\'Error viewing license information, please try again later.\');});'
        );
    }
}



print('          </TD>' . "\n" .
      '          <TD valign="top" width="50%" style="padding-left : 10px; padding-right : 10px; border-right : 1px solid #224488;" id="setupLeft">' . "\n\n");

$aItems =
     array(
            'General LOVD' . (LOVD_plus? '+' : '') . ' Setup' =>
                 array(
                        array('settings?edit', 'lovd_settings.png', 'LOVD' . (LOVD_plus? '+' : '') . ' System settings', 'View and change LOVD' . (LOVD_plus? '+' : '') . ' System settings, including settings on statistics, security and the legend.'),
         'uninstall' => array('uninstall', 'lovd_warning.png', 'Uninstall LOVD' . (LOVD_plus? '+' : '') . '', 'Uninstall LOVD' . (LOVD_plus? '+' : '') . '.'),
                      ),
            'Authorized users' =>
                 array(
                        array('users?create', 'lovd_users_create.png', 'Create new authorized user', 'Create a new authorized user or submitter.'),
                        array('users', 'lovd_users_edit.png', 'View all users', 'Manage authorized users and submitters.'),
                      ),
            'Custom data columns' =>
                 array(
                        array('columns?create', 'lovd_columns_create.png', 'Create new custom data column', 'Create new custom data column.'),
                        array('columns', 'lovd_columns_view.png', 'Browse all custom data columns', 'Browse all custom data columns already available to enable or disable them, or view or edit their settings.'),
          'download' => array('download/columns', 'lovd_save.png', 'Download all LOVD' . (LOVD_plus? '+' : '') . ' custom columns', 'Download all LOVD' . (LOVD_plus? '+' : '') . ' custom columns in the LOVD import format.'),
/*
      '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . 'setup_columns_global_import.php\';">' . "\n" .
      '                <TD align="center" width="40"><IMG src="gfx/lovd_columns_import.png" alt="Import new LOVD custom columns" width="32" height="32"></TD>' . "\n" .
      '                <TD>Import new LOVD custom columns.</TD></TR>
*/
                      ),
            'Custom links' =>
                 array(
                        array('links?create', 'lovd_links_create.png', 'Create new custom link', 'Create a new custom link. Custom links allow you to quickly insert references to other data sources, using short tags.'),
                        array('links', 'lovd_links_edit.png', 'Browse all custom links', 'Browse all available custom links and view and edit their settings.'),
                      ),
/*
// Modules.
$nModules = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_MODULES)->fetchColumn();
print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
      '              <TR>' . "\n" .
      '                <TD colspan="2"><B>Modules</B></TD></TR>' . "\n" .
      '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . 'setup_modules.php?action=scan\';">' . "\n" .
      '                <TD align="center" width="40"><IMG src="gfx/lovd_modules_scan.png" alt="Scan for new modules" width="32" height="32"></TD>' . "\n" .
      '                <TD>Scan LOVD install directory for new modules.</TD></TR>' .
      (!$nModules? '' :
      "\n" .
      '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . 'setup_modules.php?action=view_all\';">' . "\n" .
      '                <TD align="center" width="40"><IMG src="gfx/lovd_modules_manage.png" alt="Manage modules" width="32" height="32"></TD>' . "\n" .
      '                <TD>Manage installed LOVD modules.</TD></TR>') .
      '</TABLE>' . "\n");
*/
            'Download & Import' =>
                 array(
          'download' => array('download/all', 'lovd_save.png', 'Download all data', 'Download all data in LOVD import format (custom columns, genes, transcripts, diseases, individuals, phenotypes, screenings &amp; variants).'),
                        array('import', 'lovd_import.png', 'Import data', 'Import data using the LOVD import format (custom columns, diseases, individuals, phenotypes, screenings &amp; variants).'),
          'schedule' => array('import?schedule', 'lovd_clock.png', 'Schedule data for import', 'Schedule data files to be imported into LOVD' . (LOVD_plus? '+' : '') . '.'),
                      ),
            'Security' =>
                 array(
                        array('rate_limits', 'lovd_rate_limit.png', 'Rate limiting', 'Set up rate limiting for specific IPs or user agents to prevent overloading LOVD or to slow down web scraping.'),
                      ),
            'System logs' =>
                 array(
                        array('logs', 'lovd_logs.png', 'System logs', 'View, search and delete system logs.'),
                      ),
          );
// Remove uninstall.
if ($_CONF['lock_uninstall'] || $_AUTH['level'] < LEVEL_ADMIN) {
    unset($aItems['General LOVD' . (LOVD_plus? '+' : '') . ' Setup']['uninstall']);
}
if (LOVD_plus) {
    unset($aItems['Custom data columns']['download']);
    unset($aItems['Download & Import']['download']);
}

foreach ($aItems as $sTitle => $aLinks) {
    print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
          '              <TR>' . "\n" .
          '                <TH colspan="2">' . $sTitle . '</TH></TR>');
    foreach ($aLinks as $val) {
        list($sLink, $sIMG, $sAlt, $sText) = $val;
        print("\n" .
              '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . $sLink . '\';">' . "\n" .
              '                <TD align="center" width="40"><IMG src="gfx/' . $sIMG . '" alt="' . $sAlt . '" width="32" height="32"></TD>' . "\n" .
              '                <TD>' . $sText . '</TD></TR>');
    }
    print('</TABLE><BR>' . "\n\n");
}



print('          </TD>' . "\n" .
      '          <TD valign="top" width="50%" style="padding-left : 10px;" id="setupRight">' . "\n\n");


$aItems =
    array(
        'Gene panels' =>
            array(
                array('gene_panels?create', 'lovd_genes_create.png', 'Create new gene panel', 'Create a new gene panel.'),
                array('gene_panels', 'lovd_genes_view.png', 'View all gene panels', 'Manage configured gene panels.'),
            ),
        'Gene databases' =>
            array(
                array('genes?create', 'lovd_genes_create.png', 'Create new gene database', 'Create a new gene database.'),
                array('genes', 'lovd_genes_view.png', 'View all gene databases', 'Manage configured gene databases.'),
            ),
            'Transcripts' =>
                 array(
                        array('transcripts?create', 'lovd_transcripts_create.png', 'Create new transcript', 'Create a new transcript.'),
                        array('transcripts', 'lovd_transcripts.png', 'View all transcripts', 'Manage transcripts.'),
                      ),
            'Diseases' =>
                 array(
                        array('diseases?create', 'lovd_diseases_create.png', 'Create new disease', 'Create a new disease information entry.'),
                        array('diseases', 'lovd_diseases.png', 'View all diseases', 'Manage disease information entries.'),
                      ),
            'Individuals' =>
                 array(
                        array('individuals?create', 'lovd_individuals_create.png', 'Create new individual', 'Create new individual entry.'),
                        array('individuals', 'lovd_individuals.png', 'View all individuals', 'Manage individuals.'),
                      ),
            'Variants' =>
                 array(
                        array('variants?create', 'lovd_variants_create.png', 'Create new variant', 'Create a new variant.'),
                        array('variants', 'lovd_variants.png', 'View all variants', 'Manage variants.'),
                      ),
            'Announcements' =>
                 array(
                        array('announcements?create', 'lovd_announcements_create.png', 'Create new announcement', 'Create a new announcement, optionally making LOVD' . (LOVD_plus? '+' : '') . ' read-only.'),
                        array('announcements', 'lovd_information.png', 'View all announcements', 'Manage system announcements.'),
                      ),
/*
// Export central repository format.
print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
      '              <TR>' . "\n" .
      '                <TD colspan="2"><B>Download variant data for central repository</B></TD></TR>' . "\n" .
      '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . 'export_data.php?all_genes\';">' . "\n" .
      '                <TD align="center" width="40"><IMG src="gfx/lovd_save.png" alt="Download variant data" width="32" height="32"></TD>' . "\n" .
      '                <TD>Download the variant data for central repositories. This format includes the gene name, DNA change, DB ID, and possible OMIM and DbSNP IDs.</TD></TR></TABLE><BR>' . "\n");
*/
          );

if (LOVD_plus) {
    unset($aItems['Gene databases'], $aItems['Transcripts'], $aItems['Individuals'], $aItems['Variants']);
} else {
    unset($aItems['Gene panels']);
}

foreach ($aItems as $sTitle => $aLinks) {
    print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
          '              <TR>' . "\n" .
          '                <TH colspan="2">' . $sTitle . '</TH></TR>');
    foreach ($aLinks as $val) {
        list($sLink, $sIMG, $sAlt, $sText) = $val;
        print("\n" .
              '              <TR class="pointer" onclick="window.location.href=\'' . lovd_getInstallURL() . $sLink . '\';">' . "\n" .
              '                <TD align="center" width="40"><IMG src="gfx/' . $sIMG . '" alt="' . $sAlt . '" width="32" height="32"></TD>' . "\n" .
              '                <TD>' . $sText . '</TD></TR>');
    }
    print('</TABLE><BR>' . "\n\n");
}

print('          </TD>' . "\n" .
      '        </TR>' . "\n" .
      '      </TABLE>' . "\n");



// Newly installed? Flash create gene link.
if (isset($_GET['newly_installed'])) {
    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        varTR = document.getElementById(\'setupRight\').getElementsByTagName(\'tr\')[1];' . "\n");
    for ($i = 0; $i < 30; $i ++) {
        print('        setTimeout("varTR.style.background=\'#' . ($i%2? 'F0F3FF' : 'C8DCFA') . '\'", ' . ($i * 1000) . ');' . "\n");
    }
    print('        setTimeout("varTR.style.background=\'\'", ' . ($i * 1000) . ');' . "\n" .
          '      </SCRIPT>' . "\n\n");
}

$_T->printFooter();
?>
