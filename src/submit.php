<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-21
 * Modified    : 2012-08-28
 * For LOVD    : 3.0-beta-08
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Require manager clearance.
lovd_requireAUTH(LEVEL_SUBMITTER);




if (PATH_COUNT == 1 && !ACTION) {
    // URL: /submit
    // Submission process

    // 2012-07-10; 3.0-beta-07; Submitters are no longer allowed to add variants without individual data.
    if (!lovd_isAuthorized('gene', $_AUTH['curates'], false)) {
        header('Location: ' . lovd_getInstallURL() . 'individuals?create');
        exit;
    }

    define('PAGE_TITLE', 'Submit new information to this database');
    $_T->printHeader();
    $_T->printTitle();
    require ROOT_PATH . 'inc-lib-form.php';
    print('      Do you have any information available regarding an individual or a group of individuals?<BR><BR>' . "\n\n");

    $aOptionsList = array();
    $aOptionsList['options'][0]['onclick'] = 'individuals?create';
    $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to submit information on individuals</B>, such as phenotype or variant screening information';
    $aOptionsList['options'][1]['onclick'] = 'javascript:if(confirm(\'Please reconsider to submit individual data as well, as it makes the data you submit much more valuable!\nDo you want to continue anyway?\')){window.location.href=\'' . lovd_getInstallURL() . 'variants?create\';}';
    $aOptionsList['options'][1]['option_text'] = '<B>No, I will only submit summary variant data</B>';

    print(lovd_buildOptionTable($aOptionsList));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'individual' && ctype_digit($_PE[2]) && !ACTION) {
    // URL: /submit/individual/00000001
    // Individual submission
    global $_DB, $_AUTH;
    
    define('LOG_EVENT', 'SubmitIndividual');

    lovd_requireAUTH(LEVEL_SUBMITTER);

    $nID = sprintf('%08d', $_PE[2]);

    $zData = $_DB->query('SELECT * FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!isset($_AUTH['saved_work']['submissions']['individual'][$nID])) {
        define('PAGE_TITLE', 'Submit');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }
    if (!isset($_SESSION['work']['submits']['individual'])) {
        $_SESSION['work']['submits']['individual'] = array();
    }
    while (count($_SESSION['work']['submits']['individual']) >= 10) {
        unset($_SESSION['work']['submits']['individual'][min(array_keys($_SESSION['work']['submits']['individual']))]);
    }
    $aSubmit = $_SESSION['work']['submits']['individual'][$nID] = $_AUTH['saved_work']['submissions']['individual'][$nID];

    $zData['diseases'] = $_DB->query('SELECT diseaseid FROM ' . TABLE_IND2DIS . ' WHERE individualid = ?', array($nID))->fetchAllColumn();
    if (count($zData['diseases'])) {
        $zDiseases = $_DB->query('SELECT id, name, symbol FROM ' . TABLE_DISEASES . ' WHERE id IN (?' . str_repeat(', ?', count($zData['diseases']) - 1) . ')', $zData['diseases'])->fetchAllAssoc();
        $aDiseases = array();
        foreach ($zDiseases as $a) {
            $aDiseases[$a['id']] = array('name' => $a['name'], 'symbol' => $a['symbol']);
        }
    }
    $bVariants = false;
    if (!empty($aSubmit['variants'])) {
        $bVariants = true;
    } elseif (!empty($aSubmit['confirmedVariants'])) {
        foreach ($aSubmit['confirmedVariants'] as $nVariants) {
            $bVariants = true;
            break;
        }
    } elseif (!empty($aSubmit['uploads'])) {
        foreach ($aSubmit['uploads'] as $aUpload) {
            if ($aUpload['num_variants'] >= 1) {
                $bVariants = true;
                break;
            }
        }
    }
    if (!empty($aSubmit['screenings'])) {
        $nScreeningsWithoutVariants = $_DB->query('SELECT COUNT(s.id) FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s.variants_found = 1 AND s2v.variantid IS NULL AND s.id IN (?' . str_repeat(', ?', count($aSubmit['screenings']) - 1) . ')', $aSubmit['screenings'])->fetchColumn();
        if ($nScreeningsWithoutVariants) {
            $bVariants = false;
        }
    }

    define('PAGE_TITLE', 'Submission of individual #' . $nID);

    $_T->printHeader();
    $_T->printTitle();
    require ROOT_PATH . 'inc-lib-form.php';
    print('      What would you like to do?<BR><BR>' . "\n\n");

    $sPersons = ($zData['panel_size'] > 1? 'this group of individuals' : 'this individual');
    $sScreeningsViewListID = 'Screenings_Submission_' . $nID;
    $sPhenotypesViewListID = 'Phenotypes_Submission_' . $nID . '_';

    $aOptionsList = array();
    //$aOptionsList['options'][0]['onclick'] = 'individuals/' . $nID . '?edit';
    //$aOptionsList['options'][0]['option_text'] = '<B>I want to edit ' . $sPersons . '</B>';

    if (count($zData['diseases'])) {
        $nDiseases = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SHARED_COLS . ' WHERE diseaseid IN (?' . str_repeat(', ?', count($zData['diseases']) - 1) . ') GROUP BY diseaseid', $zData['diseases'])->fetchColumn();
        $sMessage = 'The disease' . (count($zData['diseases']) > 1? 's' : '') . ' added to ' . $sPersons . ' do' . (count($zData['diseases']) > 1? '' : 'es') . ' not have phenotype columns enabled yet.';
    } else {
        $nDiseases = 0;
        $sMessage = 'No diseases were selected for ' . $sPersons . '.\nThe phenotype information that can be submitted depends on the selected diseases.';
    }
    if (!$nDiseases) {
        $aOptionsList['options'][1]['disabled'] = true;
        $aOptionsList['options'][1]['onclick'] = 'javascript:alert(\'' . $sMessage . '\')';
    } else {
        $aOptionsList['options'][1]['onclick'] = 'phenotypes?create&amp;target=' . $nID;
    }
    $aOptionsList['options'][1]['option_text'] = '<B>I want to add phenotype information to ' . $sPersons . '</B>';

    //if (empty($aSubmit['phenotypes'])) {
    //    $aOptionsList['options'][2]['disabled'] = true;
    //    $aOptionsList['options'][2]['onclick'] = 'javascript:alert(\'No phenotypes are added yet to ' . $sPersons . '!\')';
    //} else {
    //    $aOptionsList['options'][2]['onclick'] = 'javascript:$(\'#container_screenings\').hide(); $(\'#container_phenotypes\').toggle(); var aDiseases = [\'' . implode('\', \'', $zData['diseases']) . '\']; for (i in aDiseases) { lovd_stretchInputs(\'' . $sPhenotypesViewListID . '\' + aDiseases[i]); }';
    //}
    //$aOptionsList['options'][2]['option_text'] = '<B>I want to edit a previously added phenotype entry</B>';
    $aOptionsList['options'][3]['onclick'] = 'screenings?create&amp;target=' . $nID;
    $aOptionsList['options'][3]['option_text'] = '<B>I want to add a variant screening to ' . $sPersons . '</B>';

    //if (empty($aSubmit['screenings'])) {
    //    $aOptionsList['options'][4]['disabled'] = true;
    //    $aOptionsList['options'][4]['onclick'] = 'javascript:alert(\'No variant screenings are added yet to ' . $sPersons . '!\')';
    //} else {
    //    $aOptionsList['options'][4]['onclick'] = 'javascript:$(\'#container_phenotypes\').hide(); $(\'#container_screenings\').toggle(); lovd_stretchInputs(\'' . $sScreeningsViewListID . '\');';
    //}
    //$aOptionsList['options'][4]['option_text'] = '<B>I want to manage a previously added variant screening</B>';

    if (!$bVariants) {
        $aOptionsList['options'][5]['disabled'] = true;
        $aOptionsList['options'][5]['onclick']  = 'javascript:alert(\'You cannot finish your submission, because ' . (!empty($nScreeningsWithoutVariants)? 'not all screenings added to ' . $sPersons . ' have variants yet!' : 'no variants have been added to ' . $sPersons . ' yet!') . '\')';
    } else {
        $aOptionsList['options'][5]['onclick'] = 'submit/finish/individual/' . $nID;
    }
    $aOptionsList['options'][5]['option_text'] = '<B>I want to finish this submission</B>';

    print(lovd_buildOptionTable($aOptionsList));

    /*require ROOT_PATH . 'class/object_screenings.php';
    $_GET['page_size'] = 10;
    $_DATA['screening'] = new LOVD_Screening();
    $_DATA['screening']->setRowLink($sScreeningsViewListID, 'submit/screening/' . $_DATA['screening']->sRowID);
    $_GET['search_individualid'] = $nID;
    $_GET['search_screeningid'] = (isset($aSubmit['screenings'])? implode('|', $aSubmit['screenings']) : 0);
    print('      <DIV id="container_screenings"><BR>' . "\n"); // Extra div is to prevent "No entries in the database yet!" error to show up if there are no genes in the database yet.
    lovd_showInfoTable('Please select a screening you would like to manage', 'information');
    $_DATA['screening']->viewList($sScreeningsViewListID, array('individualid', 'created_date', 'edited_date', 'owned_by_'), true, true);
    unset($_GET['search_screeningid']);

    $_DATA['phenotype'] = array();
    require ROOT_PATH . 'class/object_phenotypes.php';
    $_GET['search_id_'] = (isset($aSubmit['phenotypes'])? implode('|', $aSubmit['phenotypes']) : 0);
    print('      </DIV>' . "\n" .
          '      <DIV id="container_phenotypes"><BR>' . "\n"); // Extra div is to prevent "No entries in the database yet!" error to show up if there are no genes in the database yet.
    lovd_showInfoTable('Please select a phenotype you would like to edit', 'information');
    foreach($zData['diseases'] as $nDisease) {
        $_GET['search_diseaseid'] = $nDisease;
        $_DATA['phenotype'][$nDisease] = new LOVD_Phenotype($nDisease);
        $_DATA['phenotype'][$nDisease]->setSortDefault('phenotypeid');
        print('<B>' . $aDiseases[$nDisease]['name'] . ' (<A href="diseases/' . $nDisease . '">' . $aDiseases[$nDisease]['symbol'] . '</A>)</B>');
        $_DATA['phenotype'][$nDisease]->setRowLink($sPhenotypesViewListID . $nDisease, 'phenotypes/' . $_DATA['phenotype'][$nDisease]->sRowID . '?edit');
        $_DATA['phenotype'][$nDisease]->viewList($sPhenotypesViewListID . $nDisease, array('id_', 'individualid', 'diseaseid'), true, true);
    }
    print('      </DIV>' . "\n" .
          '      <SCRIPT type="text/javascript">' . "\n" .
          '        $("#container_screenings").hide();' . "\n" .
          '        $("#container_phenotypes").hide();' . "\n" .
          '      </SCRIPT>' . "\n");*/

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'screening' && ctype_digit($_PE[2]) && !ACTION) {
    // URL: /submit/screening/00000001
    // Screening submission
    global $_DB, $_AUTH;

    define('LOG_EVENT', 'SubmitScreening');

    lovd_requireAUTH(LEVEL_SUBMITTER);

    $nID = sprintf('%010d', $_PE[2]);

    $zData = $_DB->query('SELECT * FROM ' . TABLE_SCREENINGS . ' WHERE id = ? AND created_by = ?', array($nID, $_AUTH['id']))->fetchAssoc();
    if (empty($zData)) {
        define('PAGE_TITLE', 'Submit');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }
    if (isset($_AUTH['saved_work']['submissions']['screening'][$nID])) {
        if (!isset($_SESSION['work']['submits']['screening'])) {
            $_SESSION['work']['submits']['screening'] = array();
        }
        while (count($_SESSION['work']['submits']['screening']) >= 10) {
            unset($_SESSION['work']['submits']['screening'][min(array_keys($_SESSION['work']['submits']['screening']))]);
        }
        $aSubmit = $_SESSION['work']['submits']['screening'][$nID] = $_AUTH['saved_work']['submissions']['screening'][$nID];
        $sSubmitType = 'screening';
    } elseif (isset($_AUTH['saved_work']['submissions']['individual'][$zData['individualid']]['screenings']) && in_array($nID, $_AUTH['saved_work']['submissions']['individual'][$zData['individualid']]['screenings'])) {
        if (!isset($_SESSION['work']['submits']['individual'])) {
            $_SESSION['work']['submits']['individual'] = array();
        }
        while (count($_SESSION['work']['submits']['individual']) >= 10) {
            unset($_SESSION['work']['submits']['individual'][min(array_keys($_SESSION['work']['submits']['individual']))]);
        }
        $aSubmit = $_SESSION['work']['submits']['individual'][$zData['individualid']] = $_AUTH['saved_work']['submissions']['individual'][$zData['individualid']];
        $sSubmitType = 'individual';
    } else {
        define('PAGE_TITLE', 'Submit');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    lovd_isAuthorized('screening', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    $zData['variants'] = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchColumn();

    define('PAGE_TITLE', 'Submission of screening #' . $nID);

    $_T->printHeader();
    $_T->printTitle();
    require ROOT_PATH . 'inc-lib-form.php';
    print('      What would you like to do?<BR><BR>' . "\n\n");

    $sViewListID = 'VOG_VOT_Submission_' . $nID;

    $aOptionsList = array();
    //$aOptionsList['options'][0]['onclick'] = 'screenings/' . $nID . '?edit';
    //$aOptionsList['options'][0]['option_text'] = '<B>I want to edit this variant screening entry</B>';

    if (!$zData['variants_found']) {
        $aOptionsList['options'][1]['disabled'] = true;
        $aOptionsList['options'][1]['onclick'] = 'javascript:alert(\'You cannot add variants to this screening, because the checkbox &quot;Have variants been found?&quot;\nhas been unchecked for this screening!\')';
    } else {
        $aOptionsList['options'][1]['onclick'] = 'variants?create&amp;target=' . $nID;
    }
    $aOptionsList['options'][1]['option_text'] = '<B>I want add a variant to this screening</B>';

    //if (!$zData['variants']) {
    //    $aOptionsList['options'][2]['disabled'] = true;
    //    $aOptionsList['options'][2]['onclick'] = 'javascript:alert(\'No variants are added yet to this screening!\')';
    //} else {
    //    $aOptionsList['options'][2]['onclick'] = 'javascript:$(\'#container\').toggle(); lovd_stretchInputs(\'' . $sViewListID . '\');';
    //}
    //$aOptionsList['options'][2]['option_text'] = '<B>I want edit a previously added variant entry</B>';

    if ($sSubmitType == 'screening' && $zData['variants_found'] && !$zData['variants']) {
        $aOptionsList['options'][3]['disabled'] = true;
        $aOptionsList['options'][3]['onclick'] = 'javascript:alert(\'You cannot finish your submission, because no variants were added to this screening!\')';
        $aOptionsList['options'][3]['option_text'] = '<B>I want to finish this submission</B>';
    } elseif ($sSubmitType == 'individual') {
        $aOptionsList['options'][3]['onclick'] = 'submit/individual/' . $zData['individualid'];
        $aOptionsList['options'][3]['option_text'] = '<B>I want to return to the individual</B>';
    } else {
        $aOptionsList['options'][3]['onclick'] = 'submit/finish/screening/' . $nID;
        $aOptionsList['options'][3]['option_text'] = '<B>I want to finish this submission</B>';
    }

    print(lovd_buildOptionTable($aOptionsList));

    /*require ROOT_PATH . 'class/object_genome_variants.php';
    $_GET['page_size'] = 10;
    $_DATA = new LOVD_GenomeVariant();
    $_DATA->setRowLink($sViewListID, 'variants/' . $_DATA->sRowID . '?edit&submission=' . $nID);
    $_GET['search_screeningids'] = $nID;
    $_GET['search_created_by'] = $_AUTH['id'];
    print('      <DIV id="container"><BR>' . "\n"); // Extra div is to prevent "No entries in the database yet!" error to show up if there are no genes in the database yet.
    lovd_showInfoTable('Please select a variant you would like to edit', 'information');
    $_DATA->viewList($sViewListID, array('id_', 'owned_by_', 'status'), true);
    print('      </DIV>' . "\n" .
          '      <SCRIPT type="text/javascript">' . "\n" .
          '        $("#container").hide();' . "\n" .
          '      </SCRIPT>' . "\n");*/

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 4 && $_PE[1] == 'finish' && in_array($_PE[2], array('individual', 'screening', 'variant', 'phenotype', 'upload', 'confirmedVariants')) && ctype_digit($_PE[3])) {
    // URL: /submit/finish/(variant|individual|screening|phenotype|upload)/00000001

    // Try to find the genes and or diseases associated with the submission.
    switch ($_PE[2]) {
        case 'individual':
            $sURI = 'individuals/';
            $sTitle = 'an individual';
            $nID = sprintf('%08d', $_PE[3]);
            $zData = $_DB->query('SELECT i.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids, GROUP_CONCAT(DISTINCT i2d.diseaseid SEPARATOR ";") AS diseaseids FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (s2v.variantid = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE i.id = ? AND i.created_by = ? GROUP BY i.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
            lovd_isAuthorized('individual', $nID);
            break;
        case 'confirmedVariants':
            $sURI = 'screenings/';
            $sTitle = 'confirmed variants';
            $nID = sprintf('%010d', $_PE[3]);
            $aConfirmedVariants = $_SESSION['work']['submits']['confirmedVariants'][$nID];
            $zData = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id IN (?' . str_repeat(', ?', count($aConfirmedVariants) - 1) . ') ORDER BY t.geneid ASC', $aConfirmedVariants)->fetchAllColumn();
            $zData = array('geneids' => implode(';', $zData));
            lovd_isAuthorized('screening', $nID);
            break;
        case 'screening':
            $sURI = 'screenings/';
            $sTitle = 'a screening';
            $nID = sprintf('%010d', $_PE[3]);
            $zData = $_DB->query('SELECT s.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (s2v.variantid = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE s.id = ? AND s.created_by = ? GROUP BY s.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
            lovd_isAuthorized('screening', $nID);
            break;
        case 'variant':
            $sURI = 'variants/';
            $sTitle = 'a variant';
            $nID = sprintf('%010d', $_PE[3]);
            $zData = $_DB->query('SELECT v.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE v.id = ? AND v.created_by = ? GROUP BY v.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
            lovd_isAuthorized('variant', $nID);
            break;
        case 'phenotype':
            $sURI = 'phenotypes/';
            $sTitle = 'a phenotype';
            $nID = sprintf('%010d', $_PE[3]);
            $zData = $_DB->query('SELECT p.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids, GROUP_CONCAT(DISTINCT p.diseaseid SEPARATOR ";") AS diseaseids FROM ' . TABLE_PHENOTYPES . ' AS p LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s USING (individualid) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (s2v.variantid = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE p.id = ? AND p.created_by = ? GROUP BY p.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
            lovd_isAuthorized('phenotype', $nID);
            break;
        case 'upload':
            $sURI = 'variants/upload/';
            $sTitle = 'an upload';
            $nID = sprintf('%015d', $_PE[3]);
            // Setting zData to a valid, non-empty array, yet without any data because there is none.
            $zData = array('geneids' => '', 'diseaseids' => '');
            break;
    }
    $aGenes = (!empty($zData['geneids'])? explode(';', $zData['geneids']) : array());
    $aDiseases = (!empty($zData['diseaseids'])? explode(';', $zData['diseaseids']) : array());
    if (!$zData || !isset($_SESSION['work']['submits'][$_PE[2]][$nID])) {
        exit;
    }

    // Making sure we can always refer to the same variables so that it doesn't matter how we enter this submission.
    $aSubmit = array();
    if (in_array($_PE[2], array('individual', 'screening'))) {
        $aSubmit = $_SESSION['work']['submits'][$_PE[2]][$nID];
    }
    if ($_PE[2] == 'upload') {
        $aSubmit['uploads'][$nID] = $_SESSION['work']['submits']['upload'][$nID];
    } elseif ($_PE[2] == 'confirmedVariants') {
        $aSubmit['confirmedVariants'][$nID] = $_SESSION['work']['submits']['confirmedVariants'][$nID];
    } elseif ($_PE[2] != 'individual') {
        $aSubmit[$_PE[2] . 's'] = array($nID);
    }
    
    $_DB->beginTransaction();
    if ($_AUTH['level'] == LEVEL_OWNER) {
        // If the user is not a curator or a higher, then the status will be set from "In Progress" to "Pending".
        if (!empty($aSubmit['variants'])) {
            $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET statusid = ? WHERE id IN (?' . str_repeat(', ?', count($aSubmit['variants']) - 1) . ')', array_merge(array(STATUS_PENDING), $aSubmit['variants']));
        }
        if (!empty($aSubmit['phenotypes'])) {
            $q = $_DB->query('UPDATE ' . TABLE_PHENOTYPES . ' SET statusid = ? WHERE id IN (?' . str_repeat(', ?', count($aSubmit['phenotypes']) - 1) . ')', array_merge(array(STATUS_PENDING), $aSubmit['phenotypes']));
        }
        if ($_PE[2] == 'individual') {
            $q = $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET statusid = ? WHERE id = ?', array(STATUS_PENDING, $nID));
        }
    } elseif ($_AUTH['level'] == LEVEL_CURATOR && !empty($aSubmit['variants'])) {
        foreach ($aSubmit['variants'] as $nVariantID) {
            // $_AUTH['level'] will be set here to properly check the level for this variant. We have to keep in mind that the $_AUTH['level'] of the individual/screening check is overwritten.
            lovd_isAuthorized('variant', $nVariantID, true);
            if ($_AUTH['level'] == LEVEL_OWNER) {
                $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET statusid = ? WHERE id = ?', array_merge(array(STATUS_PENDING), $nVariantID));
            }
        }
    }
    $_DB->commit();

    // Remove the submission information from $_SESSION and close the session file, so that other scripts can use it without having to wait for this script to finish.
    unset($_SESSION['work']['submits'][$_PE[2]][$nID]);
    if (isset($_AUTH['saved_work']['submissions'][$_PE[2]][$nID])) {
        unset($_AUTH['saved_work']['submissions'][$_PE[2]][$nID]);
        lovd_saveWork();
    }

    session_write_close();
    define('LOG_EVENT', 'Submit' . ucfirst($_PE[2]));
    define('PAGE_TITLE', 'Submit ' . $sTitle);

    $_T->printHeader();
    $_T->printTitle();
    $aTo = array();
    if (!empty($aGenes)) {
        // Check if there are any genomic variants without a variant on transcript entry, in which case we would need to mail all managers.
        switch ($_PE[2]) {
            case 'individual':
            case 'screening':
            case 'variant':
            case 'confirmedVariants':
                $nNullTranscript = 0;
                if (!empty($aSubmit['variants'])) {
                    $nNullTranscript = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE vot.id IS NULL AND v.id IN (?' . str_repeat(', ?', count($aSubmit['variants']) - 1) . ')', $aSubmit['variants'])->fetchColumn();
                }
                if (!empty($aSubmit['uploads'])) {
                    $a = array();
                    foreach ($aSubmit['uploads'] as $nUploadID => $aUpload) {
                        $a[] = $aUpload['tCreatedDate'];
                    }
                    $nNullTranscript += $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING(id) WHERE vot.id IS NULL AND v.created_date IN (?' . str_repeat(', ?', count($a) - 1) . ') AND v.created_by = ?', array_merge($a, array($_AUTH['id'])))->fetchColumn();
                }
                if (!empty($aSubmit['confirmedVariants'])) {
                    $a = array();
                    foreach ($aSubmit['confirmedVariants'] as $nScreeningID => $aVariants) {
                        $a = array_merge($a, $aVariants);
                    }
                    $a = array_unique($a);
                    $nNullTranscript += $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE vot.id IS NULL AND v.id IN (?' . str_repeat(', ?', count($a) - 1) . ')', $a)->fetchColumn();
                }
                break;
            case 'phenotype':
                $nNullTranscript = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_PHENOTYPES . ' AS p LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s USING (individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (s2v.variantid = vot.id) WHERE vot.id IS NULL AND p.id = ?', array($nID))->fetchColumn();
                break;
            case 'upload':
                $nNullTranscript = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING(id) WHERE vot.id IS NULL AND v.created_date = ? AND v.created_by = ?', array($aSubmit['uploads'][$nID]['tCreatedDate'], $_AUTH['id']))->fetchColumn();
                break;
        }

        if ($nNullTranscript) {
            $aTo = $_DB->query('SELECT name, email FROM ' . TABLE_USERS . ' WHERE level = ' . LEVEL_MANAGER)->fetchAllRow();
        }
        // Select all curators that need to be mailed.
        $aTo = array_merge($aTo, $_DB->query('SELECT DISTINCT u.name, u.email FROM ' . TABLE_CURATES . ' AS c, ' . TABLE_USERS . ' AS u WHERE c.userid = u.id ' . (count($aTo)? 'AND u.level != ' . LEVEL_MANAGER . ' ' : '') . 'AND c.geneid IN (?' . str_repeat(', ?', count($aGenes) - 1) . ') AND allow_edit = 1 ORDER BY u.level DESC, u.name', $aGenes)->fetchAllRow());
    } else {
        // If there are no genes then we can only mail managers
        $aTo = $_DB->query('SELECT name, email FROM ' . TABLE_USERS . ' WHERE level = ' . LEVEL_MANAGER)->fetchAllRow();
    }

    // Arrays containing submitter & data fields.
    $aSubmitterDetails =
     array(
            '_AUTH',
            'id' => 'User ID',
            'name' => 'Name',
            'institute' => 'Institute',
            'department' => 'Department',
            'address' => 'Address',
            'city' => 'City',
            'country_' => 'Country',
            'email' => 'Email address',
            'telephone' => 'Telephone',
            'reference' => 'Reference',
          );
    $aIndividualFields =
     array(
            'zIndividualDetails',
            'id' => 'Individual ID',
            'panel_' => 'Panel?',
            'panel_size' => 'Panel size',
          );
    $aPhenotypeFields =
     array(
            '',
            'individualid' => 'Individual ID',
            'diseaseid_' => 'Disease',
            'id' => 'Phenotype ID',
          );
    $aScreeningFields =
     array(
            '',
            'individualid' => 'Individual ID',
            'id' => 'Screening ID',
            'variants_found_' => 'Variants found',
          );
    $aVariantOnGenomeFields =
     array(
            '',
            'screeningid' => 'Screening ID',
            'id' => 'Variant ID',
            'allele_' => 'Allele',
            'effect_reported' => 'Affects function (reported)',
            'effect_concluded' => 'Affects function (concluded)',
            'chromosome' => 'Chromosome',
          );
    $aVariantOnTranscriptFields =
     array(
            '',
            'id_ncbi_' => 'On transcript',
            'effect_reported' => 'Affects function (reported)',
            'effect_concluded' => 'Affects function (concluded)',
          );
    $aUploadFields =
     array(
            '',
            'screeningid' => 'Screening ID',
            'file_name' => 'File name',
            'file_type' => 'File type',
            'upload_date' => 'Timestamp',
            'num_variants' => 'Variants imported',
            'num_variants_unsupported' => 'Variants not imported',
            'num_genes' => 'Genes created',
            'num_transcripts' => 'Transcripts created',
            'num_variants_on_transcripts' => 'Transcript variants imported',
            'mapping_flags_' => 'Automatic mapping',
          );

    // Load the non-shared custom columns and add them to the fields list.
    $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id NOT LIKE "VariantOnTranscript/%" ' . (empty($aSubmit['screenings']) && $_PE[2] != 'confirmedVariants'? 'AND c.id NOT LIKE "Screening/%" ' : '') . ($_PE[2] != 'individual'? 'AND c.id NOT LIKE "Individual/%" ' : '') . 'AND c.id NOT LIKE "Phenotype/%" ORDER BY c.col_order', array());
    if ($_PE[2] == 'individual') {
        unset($aScreeningFields['individualid']);
        unset($aPhenotypeFields['individualid']);
        unset($aVariantOnGenomeFields['screeningid']);
        unset($aUploadFields['screeningid']);
    } elseif ($_PE[2] == 'screening') {
        unset($aVariantOnGenomeFields['screeningid']);
        unset($aUploadFields['screeningid']);
    } elseif ($_PE[2] == 'confirmedVariants') {
        unset($aScreeningFields['individualid']);
    }

    while ($aCol = $qCols->fetchAssoc()) {
        $aFieldsName = explode('/', $aCol['id']);
        $sFieldsName = 'a' . $aFieldsName[0] . 'Fields';
        ${$sFieldsName}[$aCol['id']] = $aCol['head_column'];
    }

    // Load all VariantOnTranscript custom columns for each gene and put them in seperate variable variables
    foreach ($aGenes as $sGene) {
        $sColNamesVOT = 'VOTCols_' . $sGene;
        $$sColNamesVOT = $aVariantOnTranscriptFields;
        $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) WHERE c.id LIKE "VariantOnTranscript/%" AND sc.geneid = ? ORDER BY sc.col_order', array($sGene));
        while ($aCol = $qCols->fetchAssoc()) {
            ${$sColNamesVOT}[$aCol['id']] = $aCol['head_column'];
        }
    }

    // Load all Phenotype custom columns for each disease and put them in separate variable variables
    foreach ($aDiseases as $sDisease) {
        $sColNamesPhen = 'PhenCols_' . $sDisease;
        $$sColNamesPhen = $aPhenotypeFields;
        $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) WHERE c.id LIKE "Phenotype/%" AND sc.diseaseid = ? ORDER BY sc.col_order', array($sDisease));
        while ($aCol = $qCols->fetchAssoc()) {
            ${$sColNamesPhen}[$aCol['id']] = $aCol['head_column'];
        }
    }

    $_AUTH['country_'] = $_DB->query('SELECT name FROM ' . TABLE_COUNTRIES . ' WHERE id = ?', array($_AUTH['countryid']))->fetchColumn();

    // Select all data from the database that belong to this submission and put it in a variable.
    $bUnpublished = false;
    if ($_PE[2] == 'individual') {
        $zIndividualDetails = $_DB->query('SELECT i.*, u.name AS owned_by_ FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (i.owned_by = u.id) WHERE i.id = ?', array($nID))->fetchAssoc();
        $zIndividualDetails['panel_'] = ($zIndividualDetails['panel_size'] <= 1? 'No' : 'Yes');
        ($zIndividualDetails['owned_by'] != $_AUTH['id']? $aIndividualDetails['owned_by_'] = 'Data owner' : false);
        $zIndividualDetails['statusid_'] = $_SETT['data_status'][$zIndividualDetails['statusid']];
        $bUnpublished = ($bUnpublished || $zIndividualDetails['statusid'] < STATUS_MARKED);
        if ($zIndividualDetails['panel_size'] <= 1) {
            unset($aIndividualFields['panel_size']);
        }
        if ($zIndividualDetails['edited_by'] != null) {
            $aIndividualFields['edited_by'] = 'Edited by';
            $aIndividualFields['edited_date'] = 'Edited date';
        }
        $aIndividualFields['statusid_'] = 'Data status';
        $aIndividualDetails = $aIndividualFields;
    }

    if (!empty($aSubmit['phenotypes'])) {
        $aPhenotypeDetails = array();
        foreach ($aSubmit['phenotypes'] as $nPhenotypeID) {
            $z = $_DB->query('SELECT p.*, d.id AS diseaseid, d.name AS diseaseid_, u.name AS owned_by_ FROM ' . TABLE_PHENOTYPES . ' AS p LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (p.diseaseid = d.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (p.owned_by = u.id) WHERE p.id = ?', array($nPhenotypeID))->fetchAssoc();
            $sVariableNamePhen = 'zPhenotypeDetails_' . $nPhenotypeID;
            $sColNamesPhen = 'PhenCols_' . $z['diseaseid'];
            $a = $$sColNamesPhen;
            $a[0] = $sVariableNamePhen;
            ($z['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
            $a['statusid_'] = 'Data status';
            $aPhenotypeDetails[] = $a;
            $z['statusid_'] = $_SETT['data_status'][$z['statusid']];
            if ($z['edited_by'] != null) {
                $a['edited_by'] = 'Edited by';
                $a['edited_date'] = 'Edited date';
            }
            $bUnpublished = ($bUnpublished || $z['statusid'] < STATUS_MARKED);
            $$sVariableNamePhen = $z;
        }
    }

    if (!empty($aSubmit['screenings'])) {
        $aScreeningDetails = array();
        foreach ($aSubmit['screenings'] as $nScreeningID) {
            $sVariableNameSCR = 'zScreeningsDetails_' . $nScreeningID;
            $a = $aScreeningFields;
            $a[0] = $sVariableNameSCR;
            $$sVariableNameSCR = $_DB->query('SELECT s.*, u.name AS owned_by_ FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (s.owned_by = u.id) WHERE s.id = ?', array($nScreeningID))->fetchAssoc();
            if (${$sVariableNameSCR}['variants_found']) {
                ${$sVariableNameSCR}['variants_found_'] = $_DB->query('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nScreeningID))->fetchColumn();
                if (${$sVariableNameSCR}['variants_found_'] && isset($aSubmit['confirmedVariants'][$nScreeningID])) {
                    ${$sVariableNameSCR}['variants_found_'] .= ' (inc. ' . count($aSubmit['confirmedVariants'][$nScreeningID]) . ' confirmed)';
                } elseif (!${$sVariableNameSCR}['variants_found_']) {
                    ${$sVariableNameSCR}['variants_found_'] = 0;
                }
            } else {
                ${$sVariableNameSCR}['variants_found_'] = 'None';
            }
            (${$sVariableNameSCR}['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
            if (${$sVariableNameSCR}['edited_by'] != null) {
                $a['edited_by'] = 'Edited by';
                $a['edited_date'] = 'Edited date';
            }
            $aScreeningDetails[] = $a;
        }
    } elseif (!empty($aSubmit['confirmedVariants']) && $_PE[2] == 'confirmedVariants') {
        $aScreeningDetails = array();
        $sVariableNameSCR = 'zScreeningsDetails_' . $nID;
        $a = $aScreeningFields;
        $a[0] = $sVariableNameSCR;
        $$sVariableNameSCR = $_DB->query('SELECT s.*, u.name AS owned_by_ FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (s.owned_by = u.id) WHERE s.id = ?', array($nID))->fetchAssoc();
        ${$sVariableNameSCR}['variants_found_'] = $_DB->query('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchColumn();
        ${$sVariableNameSCR}['variants_found_'] = (${$sVariableNameSCR}['variants_found_'] - count($aSubmit['confirmedVariants'][$nID])) . ' + ' . count($aSubmit['confirmedVariants'][$nID]) . ' additionaly confirmed';
        (${$sVariableNameSCR}['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
        $aScreeningDetails[] = $a;
    }

    if (!empty($aSubmit['variants'])) {
        $aVariantDetails = array();
        foreach ($aSubmit['variants'] as $nVariantID) {
            $sVariableNameVOG = 'zVariantOnGenomeDetails_' . $nVariantID;
            $a = $aVariantOnGenomeFields;
            $a[0] = $sVariableNameVOG;
            if ($_PE[2] == 'variant') {
                $$sVariableNameVOG = $_DB->query('SELECT v.*, a.name AS allele_, u.name AS owned_by_, IFNULL(s2v.screeningid, "") AS screeningid FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (v.id = s2v.variantid) LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (v.owned_by = u.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (v.allele = a.id) WHERE v.id = ?', array($nVariantID))->fetchAssoc();
            } else {
                $$sVariableNameVOG = $_DB->query('SELECT v.*, a.name AS allele_, u.name AS owned_by_ FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (v.owned_by = u.id) LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (v.allele = a.id) WHERE v.id = ?', array($nVariantID))->fetchAssoc();
            }
            if (empty(${$sVariableNameVOG}['screeningid'])) {
                unset($a['screeningid']);
            }
            ${$sVariableNameVOG}['effect_reported'] = $_SETT['var_effect'][${$sVariableNameVOG}['effectid']{0}];
            ${$sVariableNameVOG}['effect_concluded'] = $_SETT['var_effect'][${$sVariableNameVOG}['effectid']{1}];
            (${$sVariableNameVOG}['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
            ${$sVariableNameVOG}['statusid_'] = $_SETT['data_status'][${$sVariableNameVOG}['statusid']];
            $bUnpublished = ($bUnpublished || ${$sVariableNameVOG}['statusid'] < STATUS_MARKED);
            $a['statusid_'] = 'Data status';
            if (${$sVariableNameVOG}['edited_by'] != null) {
                $a['edited_by'] = 'Edited by';
                $a['edited_date'] = 'Edited date';
            }
            $aVariantDetails[] = $a;

            $q = $_DB->query('SELECT vot.*, CONCAT(t.id_ncbi, " (", t.geneid, ")") AS id_ncbi_, t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id = ?', array($nVariantID));
            while ($z = $q->fetchAssoc()) {
                $sVariableNameVOT = 'zVariantOnTranscriptDetails_' . $nVariantID . '_' . $z['transcriptid'];
                $sColNamesVOT = 'VOTCols_' . $z['geneid'];
                ${$sColNamesVOT}[0] = $sVariableNameVOT;
                $aVariantDetails[] = $$sColNamesVOT;
                $z['effect_reported'] = $_SETT['var_effect'][$z['effectid']{0}];
                $z['effect_concluded'] = $_SETT['var_effect'][$z['effectid']{1}];
                $$sVariableNameVOT = $z;
            }
            $aVariantDetails[] = 'skip';
            $aVariantDetails[] = 'hr';
        }
        array_pop($aVariantDetails);
        array_pop($aVariantDetails);
    }

    if (!empty($aSubmit['uploads'])) {
        $aUploadDetails = array();
        foreach ($aSubmit['uploads'] as $nUploadID => $zUploadDetails) {
            $sVariableNameUpload = 'zUploadDetails_' . $nUploadID;
            $a = $aUploadFields;
            $a[0] = $sVariableNameUpload;
            $$sVariableNameUpload = $zUploadDetails;
            foreach (array('num_variants_unsupported', 'num_genes', 'num_transcripts') as $sKey) {
                if ($zUploadDetails[$sKey] == 0) {
                    // Don't show the 'Variants not imported', 'Genes created' and/or 'Transcripts created' fields if they are 0.
                    // Note that the Genes and Transcripts counters are always zero for VCF files.
                    unset($a[$sKey]);
                }
            }
            if ($zUploadDetails['file_type'] == 'VCF') {
                // Also don't show the VOT counter for VCF files.
                unset($a['num_variants_on_transcripts']);
            } else {
                // And don't show the mapping flags field for SeattleSeq files.
                unset($a['mapping_flags_']);
            }
            if (!isset($zUploadDetails['screeningid']) || $_PE[2] == 'screening') {
                // Hide the screening ID field if the upload is not added to an existing screening.
                unset($a['screeningid']);
            }
            if ($zUploadDetails['owned_by'] != $_AUTH['id']) {
                // Include 'Data owner' field if owner is not the submitter.
                $a['owned_by_'] = 'Data owner';
                ${$sVariableNameUpload}['owned_by_'] = $_DB->query('SELECT name FROM ' . TABLE_USERS . ' WHERE id = ?', array($zUploadDetails['owned_by']))->fetchColumn();
            }
            ${$sVariableNameUpload}['mapping_flags_'] = (($zUploadDetails['mapping_flags'] & MAPPING_ALLOW)? 'On' . (($zUploadDetails['mapping_flags'] & MAPPING_ALLOW_CREATE_GENES)? ', creating genes as needed' : '') : 'Off');

            // Include 'Data status' as the very last field.
            $a['statusid_'] = 'Data status';
            ${$sVariableNameUpload}['statusid_'] = $_SETT['data_status'][$zUploadDetails['statusid']];
            $aUploadDetails[] = $a;
        }
    }

    // Introduction message to curators/managers.
    $sMessage = 'Dear Curator' . (count($aTo) > 1? 's' : '') . ',' . "\n\n" .
                $_AUTH['name'] . ($_PE[2] == 'confirmedVariants'? ' has indicated that additional variants were also confirmed by an existing screening.' : ' has submitted an addition to the LOVD database.') . "\n";
    if ($bUnpublished) {
        $sMessage .= '(Part of) this submission won\'t be viewable to the public until you as curator agree with the additions. Below is a copy of the submission.' . "\n\n";
    }

    if ($_CONF['location_url']) {
        $sMessage .= 'To view the ' . ($_PE[2] == 'confirmedVariants'? 'screening' : 'new') . ' entry, click this link (you may need to log in first):' . "\n" .
                     $_CONF['location_url'] . $sURI . $nID . "\n\n";
    }
    $sMessage .= 'Regards,' . "\n" .
                 '    LOVD system at ' . $_CONF['institute'] . "\n\n";

    // Build the mail format.
    $aBody = array($sMessage, 'submitter_details' => $aSubmitterDetails);
    if ($_PE[2] == 'individual') {
        $aBody['individual_details'] = $aIndividualDetails;
    }
    if (!empty($aSubmit['phenotypes'])) {
        $aBody['phenotype_details'] = $aPhenotypeDetails;
    }
    if (!empty($aSubmit['screenings']) || $_PE[2] == 'confirmedVariants') {
        $aBody['screening_details'] = $aScreeningDetails;
    }
    if (!empty($aSubmit['variants'])) {
        $aBody['variant_details'] = $aVariantDetails;
    }
    if (!empty($aSubmit['uploads'])) {
        $aBody['upload_details'] = $aUploadDetails;
    }
    require ROOT_PATH . 'inc-lib-form.php';
    $sBody = lovd_formatMail($aBody);

    // Set proper subject.
    $sSubject = 'LOVD submission' . (!empty($aGenes)? ' (' . implode(', ', array_slice($aGenes, 0, 20)) . (count($aGenes) > 20? ', ...' : '') . ')' : ''); // Don't just change this; lovd_sendMail() is parsing it.

    // Set submitter address.
    $aSubmitter = array(array($_AUTH['name'], $_AUTH['email']));

    // Send mail.
    $bMail = lovd_sendMail($aTo, $sSubject, $sBody, $_SETT['email_headers'], $_CONF['send_admin_submissions'], $aSubmitter);

    // FIXME; When messaging system is built in, maybe queue message for curators?
    if ($bMail) {
        lovd_showInfoTable('Successfully processed your submission and sent an email notification to the relevant curator(s)!', 'success');

        // Forward only if there was no error sending the email.
        print('      <SCRIPT type="text/javascript">setTimeout("window.location.href=\'' . lovd_getInstallURL() . $sURI . $nID . '\'", 3000);</SCRIPT>' . "\n");
    } else {
        lovd_showInfoTable('Successfully processed your submission, but LOVD wasn\'t able to send an email notification to the relevant curator(s)!<BR>Please contact one of the relevant curators and notify them of your submission so that they can curate your data!', 'warning');
    }

    $_T->printFooter();
    exit;
}
?>
