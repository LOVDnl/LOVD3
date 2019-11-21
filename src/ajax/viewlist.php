<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-18
 * Modified    : 2019-11-21
 * For LOVD    : 3.0-23
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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
require_once ROOT_PATH . 'inc-lib-viewlist.php';

// Get viewlist-identifying arguments from request and check their validity.
$sViewListID = (isset($_REQUEST['viewlistid'])? $_REQUEST['viewlistid'] : '');
$sObject = (isset($_REQUEST['object'])? $_REQUEST['object'] : '');
$sObjectID = (isset($_REQUEST['object_id'])? $_REQUEST['object_id'] : '');
$nID = (isset($_REQUEST['id'])? $_REQUEST['id'] : '');

if (empty($sViewListID) || empty($sObject) || !preg_match('/^[A-Z_]+$/i', $sObject)) {
    die(AJAX_DATA_ERROR);
}

// The required security to load the viewList() depends on the data that is shown.
// To prevent security problems if we forget to set a requirement here, we default to LEVEL_ADMIN.
$aNeededLevel =
         array(
                'Column' => LEVEL_CURATOR,
                'Custom_ViewList' => 0,
                'Custom_ViewListMOD' => 0, // LOVD+
                'Disease' => 0,
                'Gene' => 0,
                'Gene_Panel' => LEVEL_SUBMITTER, // LOVD+
                'Gene_Panel_Gene' => LEVEL_SUBMITTER, // LOVD+
                'Gene_Panel_Gene_REV' => LEVEL_SUBMITTER, // LOVD+
                'Gene_Statistic' => LEVEL_SUBMITTER, // LOVD+
                'Genome_Variant' => 0,
                'Individual' => 0,
                'IndividualMOD' => 0, // LOVD+
                'Link' => LEVEL_MANAGER,
                'Log' => (LOVD_plus? LEVEL_SUBMITTER : LEVEL_MANAGER),
                'Phenotype' => 0,
                'Screening' => 0,
                'ScreeningMOD' => 0, // LOVD+
                'Shared_Column' => LEVEL_CURATOR,
                'Summary_Annotation_REV' => $_SETT['user_level_settings']['summary_annotation_view_history'],
                'Transcript' => 0,
                'Transcript_Variant' => 0,
                'User' => LEVEL_SUBMITTER, // Certain fields will be forcefully removed, though.
              );
if (isset($aNeededLevel[$sObject])) {
    $nNeededLevel = $aNeededLevel[$sObject];
} else {
    $nNeededLevel = LEVEL_ADMIN;
}

// 2013-06-28; 3.0-06; We can't allow just any custom viewlist without actually checking the shown objects. Screenings, for instance, does not have a built-in status check (since it doesn't have a status).
// Building list of allowed combinations of objects for custom viewlists.
if ($sObject == 'Custom_ViewList' && (!isset($sObjectID) || !in_array($sObjectID,
            array(
                'VariantOnGenome,Scr2Var,VariantOnTranscript', // Variants on I and S VEs.
                'Transcript,VariantOnTranscript,VariantOnGenome', // IN_GENE.
                'Transcript,VariantOnTranscript,VariantOnGenome,Screening,Individual', // LOVD-wide full data view (external viewer).
                'VariantOnTranscript,VariantOnGenome', // Gene-specific variant view.
                'VariantOnTranscriptUnique,VariantOnGenome', // Gene-specific unique variant view.
                'VariantOnTranscript,VariantOnGenome,Screening,Individual', // Gene-specific full data view.
                'Gene,Transcript,DistanceToVar')))) { // Map variant to transcript.
    die(AJAX_DATA_ERROR);
}

// We can't authorize Curators and Collaborators without loading their level!
// 2014-03-13; 3.0-10; Collaborators should of course also get their level loaded!
if ($_AUTH['level'] < LEVEL_MANAGER && (!empty($_AUTH['curates']) || !empty($_AUTH['collaborates']))) {
    if ($sObject == 'Column') {
        lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
    } elseif ($sObject == 'Individual' && isset($_REQUEST['search_genes_searched']) && preg_match('/^="([^"]+)"$/', $_REQUEST['search_genes_searched'], $aRegs)) {
        lovd_isAuthorized('gene', $aRegs[1]); // Authorize for the gene currently searched (it currently restricts the view).
        // Since we're authorizing on $_REQUEST which also contains $_POST data, make sure the $_GET (what we actually filter on) matches what we authorize on!!!
        $_GET['search_genes_searched'] = $_REQUEST['search_genes_searched'];
    } elseif ($sObject == 'Transcript' && isset($_REQUEST['search_geneid']) && preg_match('/^="([^"]+)"$/', $_REQUEST['search_geneid'], $aRegs)) {
        lovd_isAuthorized('gene', $aRegs[1]); // Authorize for the gene currently searched (it currently restricts the view).
        // Since we're authorizing on $_REQUEST which also contains $_POST data, make sure the $_GET (what we actually filter on) matches what we authorize on!!!
        $_GET['search_geneid'] = $_REQUEST['search_geneid'];
    } elseif ($sObject == 'Shared_Column' && isset($_REQUEST['object_id'])) {
        lovd_isAuthorized('gene', $sObjectID); // Authorize for the gene currently loaded.
    } elseif ($sObject == 'Screening' && $sViewListID == 'Screenings_for_I_VE' && isset($_REQUEST['search_individualid']) && ctype_digit($_REQUEST['search_individualid'])) {
        // Screenings_for_I_VE has no ID but authorizes on search_individualid.
        lovd_isAuthorized('individual', $_REQUEST['search_individualid']); // Authorize for the Screening(s) currently searched (it restricts the view).
        // Since we're authorizing on $_REQUEST which also contains $_POST data, make sure the $_GET (what we actually filter on) matches what we authorize on!!!
        $_GET['search_individualid'] = $_REQUEST['search_individualid'];
    } elseif ($sObject == 'Custom_ViewList') {
        // 2013-06-28; 3.0-06; We can't just authorize users based on the given ID without actually checking the shown objects and checking if the search results are actually limited or not.
        // CustomVL_IN_GENE has no ID and does not require authorization (only public VOGs loaded).

        if (!empty($_REQUEST['id'])) {
            // CustomVL_VOT_VOG_<<GENE>> is restricted per gene in the object argument, and search_transcriptid should contain a transcript ID that matches.
            // CustomVL_VIEW_<<GENE>> is restricted per gene in the object argument, and search_transcriptid should contain a transcript ID that matches.
            if (in_array($sObjectID,
                    array(
                        'VariantOnTranscript,VariantOnGenome',
                        'VariantOnTranscriptUnique,VariantOnGenome',
                        'VariantOnTranscript,VariantOnGenome,Screening,Individual'
                    )) && (!isset($_REQUEST['search_transcriptid'])
                    || !$_DB->query('SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ? AND geneid = ?', array($_REQUEST['search_transcriptid'], $_REQUEST['id']))->fetchColumn())) {
                die(AJAX_NO_AUTH);
            }
            lovd_isAuthorized('gene', $nID); // Authorize for the gene currently loaded.

        } elseif ($sObjectID == 'VariantOnGenome,Scr2Var,VariantOnTranscript' && isset($_REQUEST['search_screeningid'])) {
            // CustomVL_VOT_for_I_VE has no ID but authorizes on search_screeningid (can contain multiple IDs).
            // CustomVL_VOT_for_S_VE has no ID but authorizes on search_screeningid.

            // When receiving multiple screenings, we intend of course to authorize on its individual.
            // We should not allow to add an "OR screeningid = ?" clause including an owned Screening to force authorization.
            // Check if we have multiple screening IDs. If so, make sure they belong together.
            $aScreeningIDs = explode('|', $_REQUEST['search_screeningid']);
            if (count($aScreeningIDs) > 1) {
                $nIndividuals = $_DB->query('SELECT COUNT(DISTINCT individualid) FROM ' . TABLE_SCREENINGS . ' WHERE id IN (?' . str_repeat(', ?', count($aScreeningIDs) - 1) . ')',
                    $aScreeningIDs)->fetchColumn();
                if ($nIndividuals > 1) {
                    // This custom VL is only loaded for authorization on Screenings, and there's no reason to have multiple Individuals.
                    die(AJAX_NO_AUTH);
                }
            }
            lovd_isAuthorized('screening', $aScreeningIDs); // Authorize for the Screening(s) currently searched (it restricts the view).
            // Since we're authorizing on $_REQUEST which also contains $_POST data, make sure the $_GET (what we actually filter on) matches what we authorize on!!!
            $_GET['search_screeningid'] = $_REQUEST['search_screeningid'];
        }
    }
}



// Require special clearance?
if ($nNeededLevel && (!$_AUTH || $_AUTH['level'] < $nNeededLevel)) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

// Load the columns to skip from the request. The external viewer uses this.
$aColsToSkip = (!empty($_REQUEST['skip'])? $_REQUEST['skip'] : array());

// Submitters should not be allowed to retrieve more information
//  about users than the info the access sharing page gives them.
if ($sObject == 'User' && $_AUTH['level'] < LEVEL_MANAGER) {
    // Force removal of certain columns, regardless of this has been requested or not.
    // We cannot trust this was set in $_SESSION already since the VL can be loaded independently.
    $aColsToSkip = array_unique(
        array_merge(
            $aColsToSkip,
            array(
                'username',
                'status_',
                'last_login_',
                'created_date_',
                'curates',
                'level_'
            )));
}

// Managers, and sometimes curators, are allowed to download lists...
if (in_array(ACTION, array('download', 'downloadSelected'))) {
    if ($_AUTH['level'] >= LEVEL_CURATOR) {
        // We need this define() because the Object::viewList() may still throw some error which calls
        // Template::printHeader(), which would then thow a "text/plain not allowed here" error.
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }
}
if (FORMAT == 'text/plain' && !defined('FORMAT_ALLOW_TEXTPLAIN')) {
    die(AJAX_NO_AUTH);
}

$sFile = ROOT_PATH . 'class/object_' . strtolower($sObject) . 's.php';
// For revision tables.
$sFile = str_replace('_revs.php', 's.rev.php', $sFile);
// Exception for LOVD+.
if (LOVD_plus && substr($_GET['object'], -3) == 'MOD') {
    $sFile = str_replace('mods.', 's.mod.', $sFile);
}

if (!file_exists($sFile)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}


require $sFile;
$sObjectClassname = 'LOVD_' . str_replace('_', '', $sObject);
$_DATA = new $sObjectClassname($sObjectID, $nID);



if (POST && ACTION == 'applyFR') {
    // Apply find & replace.

    if ($_AUTH['level'] < LEVEL_CURATOR || !isset($_POST['password']) ||
        !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
        // Not authorized for find & replace.
        die(AJAX_NO_AUTH);
    }
    $aFROptions['sFRMatchType'] = (isset($_POST['FRMatchType_' . $sViewListID])?
        $_POST['FRMatchType_' . $sViewListID] : null);
    $aFROptions['bFRReplaceAll'] = (isset($_POST['FRReplaceAll_' . $sViewListID])?
        $_POST['FRReplaceAll_' . $sViewListID] : null);

    if (!isset($_POST['FRFieldname_' . $sViewListID]) ||
        !isset($_POST['FRSearch_' . $sViewListID]) ||
        !isset($_POST['FRReplace_' . $sViewListID])) {
        die(AJAX_DATA_ERROR);
    }

    // Setup search filters before applying find & replace.
    list($WHERE, $HAVING, $aArguments, $aBadSyntaxColumns, $aColTypes) = $_DATA->processViewListSearchArgs($_POST);

    // Update where/having clauses based on search filters (needed for LOVD_Object->buildSQL()).
    if ($WHERE) {
        $_DATA->aSQLViewList['WHERE'] .= ($_DATA->aSQLViewList['WHERE']? ' AND ' : '') . $WHERE;
    }
    if ($HAVING) {
        $_DATA->aSQLViewList['HAVING'] .= ($_DATA->aSQLViewList['HAVING']? ' AND ' : '') . $HAVING;
    }
    $aArgs = array_merge($aArguments['WHERE'], $aArguments['HAVING']);
    $bResult = $_DATA->applyColumnFindAndReplace($_POST['FRFieldname_' . $sViewListID],
                                                $_POST['FRSearch_' . $sViewListID],
                                                $_POST['FRReplace_' . $sViewListID],
                                                $aArgs, $aFROptions);
    // Return AJAX response.
    die($bResult? AJAX_TRUE : AJAX_FALSE);

} elseif (POST) {
    // Post request with no action specified. We do not allow normal viewlist
    // views via POST.
    die(AJAX_DATA_ERROR);
}

// Restrict the columns of this VL, if given.
if (LOVD_plus && isset($_INSTANCE_CONFIG['viewlists'][$_GET['viewlistid']]['cols_to_show'])) {
    $_DATA->setViewListCols($_INSTANCE_CONFIG['viewlists'][$_GET['viewlistid']]['cols_to_show']);
}

// Show the viewlist.
// Parameters could be assumed to be in $_SESSION. However, certain options we send through here.
// only_rows is checked and sent here because of the logs retrieving single rows from the next page.
// cols_to_skip can be overridden for the external viewer.
$aOptions = array(
    'only_rows' => (!empty($_GET['only_rows'])),
);
if ($aColsToSkip) {
    // Don't let the requested list of columns overwrite the original one. Only additional columns may be hidden.
    $aOptions['cols_to_skip'] = array_unique(array_merge(
        (!isset($_SESSION['viewlists'][$_GET['viewlistid']]['options']['cols_to_skip'])? array()
            : $_SESSION['viewlists'][$_GET['viewlistid']]['options']['cols_to_skip']),
        $aColsToSkip
    ));
}
$_DATA->viewList($_GET['viewlistid'], $aOptions);
?>
