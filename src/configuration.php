<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-07-11
 * Modified    : 2012-07-23
 * For LOVD    : 3.0-beta-07
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

define('PAGE_TITLE', (!$_SESSION['currdb']? 'Gene' : $_SESSION['currdb']) . ' configuration');
$_T->printHeader();
$_T->printTitle();

// Require curator clearance (any).
if (!lovd_isAuthorized('gene', $_AUTH['curates'], false)) {
    lovd_requireAUTH(LEVEL_CURATOR);
}





if (ACTION) {
    // We're receiving an action, but we're not designed to handle one on this page...
    lovd_showInfoTable('Can\'t perform requested action.', 'stop');
    $_T->printFooter();
    exit;
}





if (PATH_COUNT != 2 || !$_SESSION['currdb']) {
    // URL: /configuration
    // URL: /configuration/GENETHATDOESNOTEXIST
    // URL: /configuration/DMD/something_that_should_not_be_there
    // Force user to select different gene.

    // Print LOVD2-style type of selection list with genes this person is curator of (if LEVEL_CURATOR). If only one gene exists, select that gene inmediately.
    if (!$_SESSION['currdb']) {
        if (LEVEL_CURATOR) {
            $qGenes = $_DB->query('SELECT g.id, CONCAT(g.id, " (", g.name, ")") AS name FROM ' . TABLE_CURATES . ' AS c INNER JOIN ' . TABLE_GENES . ' AS g ON (c.geneid = g.id) WHERE c.userid = ? AND c.allow_edit = 1 ORDER BY g.id', array($_AUTH['id']));
        } else {
            $qGenes = $_DB->query('SELECT g.id, CONCAT(g.id, " (", g.name, ")") AS name FROM ' . TABLE_GENES . ' AS g ORDER BY g.id', array());
        }
        $aGenes = $qGenes->fetchAllRow();
        $nGenes = count($aGenes);

        // If there are no genes, we're done here.
        if (!$nGenes) {
            lovd_showInfoTable('There is currently no gene configured in LOVD yet.<BR>Maybe you want to <A href="genes?create">create a new gene</A> now?', 'stop');
            $_T->printFooter();
            exit;

        } else {
            print('    Please select a gene database:<BR>' . "\n" .
                  '    <FORM action="' . CURRENT_PATH . '" id="formSelectGeneDB" onsubmit="window.location.href = \'' . lovd_getInstallURL() . $_PE[0] . '/\' + $(this).children(\'select\').val(); return false;" method="GET">' . "\n" .
                  '      <SELECT name="select_db" onchange="$(\'#formSelectGeneDB\').submit();">' . "\n");
            foreach ($aGenes as $aGene) {
                list($sID, $sName) = $aGene;
                // This will shorten the gene names nicely, to prevent long gene names from messing up the form.
                $sName = lovd_shortenString($sName, 100);
                if (substr($sName, -3) == '...') {
                    $sName .= str_repeat(')', substr_count($sName, '('));
                }
                print('      <OPTION value="' . $sID . '">' . $sName . '</OPTION>' . "\n");
            }
            print('      </SELECT><BR>' . "\n" .
                  '      <INPUT type="submit" value="Select gene database">' . "\n" .
                  '    </FORM>' . "\n\n");

            if ($nGenes == 1) {
                // Just one gene, submit form now.
                print('      <SCRIPT type="text/javascript">' . "\n" .
                      '        $("#formSelectGeneDB").submit();' . "\n" .
                      '      </SCRIPT>' . "\n\n");
            }
            $_T->printFooter();
            exit;
        }
    }

} else {
    // Needs to be curator for THIS gene.
    if (!lovd_isAuthorized('gene', $_SESSION['currdb'])) {
        // NOTE that this does not unset certain links in the top menu. Links are still available.
        lovd_showInfoTable('You are not allowed access to the configuration of this gene database. If you think this is an error, please contact your manager or the database administrator to grant you access.', 'stop');
        $_T->printFooter();
        exit;
    }

}





// URL: /configuration/DMD
// View all gene-specific configuration options, like downloads, graphs, custom column settings, etc.

// Some info & statistics.
$aVarStatuses = array_combine(array_keys($_SETT['data_status']), array_fill(0, count($_SETT['data_status']), 0));
$aVarCounts = $_DB->query('SELECT vog.statusid, COUNT(DISTINCT vog.id) FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE t.geneid = ? GROUP BY vog.statusid ORDER BY statusid', array($_SESSION['currdb']))->fetchAllCombine()
    + $aVarStatuses;
ksort($aVarCounts);
// In progress would just confuse users, so remove if it's not present.
if (!$aVarCounts[STATUS_IN_PROGRESS]) {
    unset($aVarCounts[STATUS_IN_PROGRESS]);
}
$nTotalVars = array_sum($aVarCounts);
$nUncurated =

$sLink = 'view/' . $_SESSION['currdb'] . '?search_var_status=';
print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
      '              <TR>' . "\n" .
      '                <TD colspan="' . (count($aVarCounts) - 3) . '"><B>Variants</B></TD>' . "\n" .
      '                <TD colspan="' . (count($aVarCounts) - 2) . '"><B><A href="' . $sLink . 'Pending%7CNon">All uncurated</A></B>: ' . (int) ($aVarCounts[STATUS_PENDING] + $aVarCounts[STATUS_HIDDEN]) . '</TD>' . "\n" .
      '                <TD colspan="' . (count($aVarCounts) - 2) . '"><B><A href="' . $sLink . 'Marked%7CPublic%20%21Non">All curated</A></B>: ' . (int) ($aVarCounts[STATUS_MARKED] + $aVarCounts[STATUS_OK]) . '</TD></TR>' . "\n" .
      '              <TR class="S11">' . "\n" .
      '                <TD><A href="' . $sLink . '">Total</A>: ' . $nTotalVars . '</TD>');
foreach ($aVarCounts as $nStatus => $nVars) {
    print("\n" .
          '                <TD><A href="' . $sLink . '%3D%22' . $_SETT['data_status'][$nStatus] . '%22">' . $_SETT['data_status'][$nStatus] . '</A>: ' . $nVars . '</TD>');
}
print('</TR></TABLE><BR>' . "\n\n");



// Do some basic checks to try and trigger curator's actions.
// It is important to have at least one transcript.
$nTranscript = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ?', array($_SESSION['currdb']))->fetchColumn();
if (!$nTranscript) {
    lovd_showInfoTable('You currently do not have a transcript configured for the ' . $_SESSION['currdb'] . ' gene database. Without a transcript added, you can only store genomic variants, and thus you will not have any gene-specific variant overviews.<BR>Please <A href="transcripts?create&amp;target=' . $_SESSION['currdb'] . '">add a transcript to your gene</A>.', 'warning');
}



// Main table.
print('      <TABLE border="0" cellpadding="0" cellspacing="0" width="100%">' . "\n" .
      '        <TR>' . "\n" .
      '          <TD valign="top" width="50%" style="padding-right : 10px; border-right : 1px solid #224488;" id="configLeft">' . "\n");

// Do we want statistics here, on the left? If so, take the code from Setup.php.



$aItems =
    array(
        'Curating ' . $_SESSION['currdb'] . ' variants' =>
            array(
                array('view/' . $_SESSION['currdb'] . '?search_var_status=Submitted%7CNon%7CMarked', 'lovd_variants_curate.png', 'Curate ' . $_SESSION['currdb'] . ' variants', 'View all uncurated variant entries in the ' . $_SESSION['currdb'] . ' gene database (newly submitted, non public and marked entries).'),
                array('view/' . $_SESSION['currdb'], 'lovd_variants_edit.png', 'View ' . $_SESSION['currdb'] . ' variants', 'View all data submissions in the ' . $_SESSION['currdb'] . ' gene database.'),
                // Curate all: there is a very slim chance that a submission is not OK (checkFields() wise), simply because the submitter had the same checkFields(). However, we would like to be sure.
                // I guess it will be quite intensive to check all variants, you will need to instanciate the objects all the time, run checkFields(), run updateEntry(), it'll be quite some code...
//                array('onclick="javascript:if(!confirm(\'Curate (publish) all marked and non-public variants?\\n\\nAll variants will be checked, and published only if no problems have been found.\\nPlease note that this process may take some time, if your database contains a lot of uncurated variants.\')){return false;}else{window.location.href=\'' . ROOT_PATH . 'config_variants.php?action=curate_all\';}"', 'lovd_variants_curate_all.png', 'Curate all ' . $_SESSION['currdb'] . ' non-public variants', 'Curate (publish) all marked and non-public entries in the ' . $_SESSION['currdb'] . ' gene database.'),
            ),
        /*
            // Free edit.
            print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
                  '              <TR>' . "\n" .
                  '                <TD colspan="2"><B>Free Edit - Advanced edit features</B></TD></TR>' . "\n" .
                  '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'config_free_edit.php?action=fnr' . lovd_showSID(true, true) . '\';">' . "\n" .
                  '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_free_edit_fnr.png" alt="Find & Replace" width="32" height="32"></TD>' . "\n" .
                  '                <TD>Find &amp; Replace: Find a certain value in a specific column and replace it with a different value.</TD></TR>' . "\n" .
                  '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'config_free_edit.php?action=copy' . lovd_showSID(true, true) . '\';">' . "\n" .
                  '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_free_edit_copy.png" alt="Copy Column" width="32" height="32"></TD>' . "\n" .
                  '                <TD>Copy Column: Copy or move one column\'s contents into another column.</TD></TR></TABLE><BR>' . "\n");
        */
        'Custom columns for ' . $_SESSION['currdb'] =>
            array(
                // FIXME; Can we implement an overview of columns NOT present in gene X?
                array('columns/VariantOnTranscript', 'lovd_columns_add.png', 'Add pre-configured custom column to the ' . $_SESSION['currdb'] . ' gene', 'View all available pre-configured variant custom columns to add to the ' . $_SESSION['currdb'] . ' gene database.'),
                array('genes/' . $_SESSION['currdb'] . '/columns', 'lovd_columns_view.png', 'Manage custom columns in the ' . $_SESSION['currdb'] . ' gene', 'View the variant custom columns currently enabled for the ' . $_SESSION['currdb'] . ' gene.'),
            ),
    );



foreach ($aItems as $sTitle => $aLinks) {
    print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
          '              <TR>' . "\n" .
          '                <TH colspan="2">' . $sTitle . '</TH></TR>');
    foreach ($aLinks as $val) {
        list($sLink, $sIMG, $sAlt, $sText) = $val;
        $sLink = (substr($sLink, 0, 11) == 'javascript:'? substr($sLink, 11) . ' return false;' : 'window.location.href=\'' . lovd_getInstallURL(false) . $sLink . '\'');
        print("\n" .
            '              <TR class="pointer" onclick="' . $sLink . '">' . "\n" .
            '                <TD align="center" width="40"><IMG src="gfx/' . $sIMG . '" alt="' . $sAlt . '" width="32" height="32"></TD>' . "\n" .
              '                <TD>' . $sText . '</TD></TR>');
    }
    print('</TABLE><BR>' . "\n\n");
}



print('          </TD>' . "\n" .
    '          <TD valign="top" width="50%" style="padding-left : 10px;" id="configRight">' . "\n\n");



$aItems =
    array(
        'Gene settings' =>
            array(
                array('genes/' . $_SESSION['currdb'] . '?edit', 'lovd_genes_edit.png', 'Edit ' . $_SESSION['currdb'] . ' gene database', 'Edit ' . $_SESSION['currdb'] . ' gene database.'),
                array('genes/' . $_SESSION['currdb'] . '?authorize', 'lovd_curator_sort.png', 'Sort ' . $_SESSION['currdb'] . ' gene database curator list', 'Edit or sort the list of curators for the ' . $_SESSION['currdb'] . ' gene database, and/or hide curators from the list of curators shown on the gene\'s homepage and in LOVD\'s header.'),
                /*
                array('', '', '', ''),
                    (!$nTotalVars? '' : "\n" .
                        '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'config_genes.php?action=empty\';">' . "\n" .
                        '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_database_empty.png" alt="Empty ' . $_SESSION['currdb'] . ' gene database" width="32" height="32"></TD>' . "\n" .
                        '                <TD>Empty ' . $_SESSION['currdb'] . ' gene database (remove all variants).</TD></TR>') .
                */
            ),


/*
// Download & Import.
print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
    '              <TR>' . "\n" .
    '                <TD colspan="2"><B>Download/Import variant and patient data</B></TD></TR>' .
    (!$nTotalVars? '' : "\n" .
        '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'download.php?action=view_all\';">' . "\n" .
        '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_save.png" alt="Download all data from the ' . $_SESSION['currdb'] . ' gene database" width="32" height="32"></TD>' . "\n" .
        '                <TD>Download all variant and patient data from the ' . $_SESSION['currdb'] . ' gene database.</TD></TR>') . "\n" .
    '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'config_import.php\';">' . "\n" .
    '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_database_import.png" alt="Import variants into the ' . $_SESSION['currdb'] . ' gene database" width="32" height="32"></TD>' . "\n" .
    '                <TD>Import new variant and patient data into the ' . $_SESSION['currdb'] . ' gene database.</TD></TR>' .
    '</TABLE><BR>' . "\n");

// Export central repository format.
print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
    '              <TR>' . "\n" .
    '                <TD colspan="2"><B>Download variant data for central repository</B></TD></TR>' . "\n" .
    '              <TR class="setup" onclick="window.location.href=\'' . ROOT_PATH . 'export_data.php?genes%5B%5D=' . $_SESSION['currdb'] . lovd_showSID(true, true) . '\';">' . "\n" .
    '                <TD align="center" width="40"><IMG src="' . ROOT_PATH . 'gfx/lovd_save.png" alt="Download variant data" width="32" height="32"></TD>' . "\n" .
    '                <TD>Download the variant data for central repositories. This format includes the gene name, DNA change, DB ID, and possible OMIM and DbSNP IDs.</TD></TR>' .
    '</TABLE><BR>' . "\n");
*/

        'LOVD scripts' =>
        array(
            array('javascript:lovd_openWindow(\'' . lovd_getInstallURL() . 'scripts/refseq_parser.php?step=1&amp;symbol=' . $_SESSION['currdb'] . '\', \'RefseqParser\', 900, 500);', 'lovd_scripts.png', 'Reference Sequence Parser', 'The LOVD Reference sequence parser creates a nicely formatted HTML page of a coding DNA reference sequence, including exon/intron boundaries and separate files for upstream, intronic and downstream sequences. It accepts different input formats.'),
        ),
    );



foreach ($aItems as $sTitle => $aLinks) {
    print('            <TABLE border="0" cellpadding="2" cellspacing="0" class="setup" width="100%">' . "\n" .
        '              <TR>' . "\n" .
        '                <TH colspan="2">' . $sTitle . '</TH></TR>');
    foreach ($aLinks as $val) {
        list($sLink, $sIMG, $sAlt, $sText) = $val;
        $sLink = (substr($sLink, 0, 11) == 'javascript:'? substr($sLink, 11) . ' return false;' : 'window.location.href=\'' . lovd_getInstallURL(false) . $sLink . '\'');
        print("\n" .
            '              <TR class="pointer" onclick="' . $sLink . '">' . "\n" .
            '                <TD align="center" width="40"><IMG src="gfx/' . $sIMG . '" alt="' . $sAlt . '" width="32" height="32"></TD>' . "\n" .
            '                <TD>' . $sText . '</TD></TR>');
    }
    print('</TABLE><BR>' . "\n\n");
}

print('          </TD>' . "\n" .
    '        </TR>' . "\n" .
    '      </TABLE>' . "\n");

$_T->printFooter();
?>
