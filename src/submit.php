<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-21
 * Modified    : 2011-11-14
 * For LOVD    : 3.0-alpha-06
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
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




if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /submit
    // Submission process 

    define('PAGE_TITLE', 'Submit new information to this database');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);
    print('      Do you have any information available regarding an individual or a group of individuals?<BR><BR>' . "\n\n" .
          '      <TABLE border="0" cellpadding="5" cellspacing="1" class="option">' . "\n" .
          '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'individuals?create\'">' . "\n" .
          '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
          '          <TD><B>Yes, I want to submit information on individuals</B>, such as phenotype or mutation screening information</TD></TR>' . "\n" .
          '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'variants?create\'">' . "\n" .
          '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
          '          <TD><B>No, I will only submit summary variant data</B></TD></TR></TABLE><BR>' . "\n\n");
    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && $_PATH_ELEMENTS[1] == 'finish' && in_array($_PATH_ELEMENTS[2], array('variant', 'individual')) && isset($_PATH_ELEMENTS[3]) && ctype_digit($_PATH_ELEMENTS[3])) {
    // URL: /submit/finish/individual/00000001

    if ($_PATH_ELEMENTS[2] == 'variant') {
        $nID = sprintf('%010d', $_PATH_ELEMENTS[3]);
        $zData = $_DB->query('SELECT v.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (v.id = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE v.id = ? AND v.created_by = ? GROUP BY v.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
        $aGenes = (!empty($zData['geneids'])? explode(';', $zData['geneids']) : array(''));
        if (!$zData || !isset($_SESSION['work']['submits']['variantid'][$nID])) {
            exit;
        }
        $aSubmit['variants'] = array($nID);
    } elseif ($_PATH_ELEMENTS[2] == 'individual') {
        $nID = sprintf('%08d', $_PATH_ELEMENTS[3]);
        $zData = $_DB->query('SELECT i.id, GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids, GROUP_CONCAT(DISTINCT i2d.diseaseid SEPARATOR ";") AS diseaseids FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (s2v.variantid = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE i.id = ? AND i.created_by = ? GROUP BY i.id ORDER BY t.geneid ASC', array($nID, $_AUTH['id']))->fetchAssoc();
        $aGenes = (!empty($zData['geneids'])? explode(';', $zData['geneids']) : array(''));
        $aDiseases = (!empty($zData['diseaseids'])? explode(';', $zData['diseaseids']) : array(''));
        if (!$zData || !isset($_SESSION['work']['submits'][$nID])) {
            exit;
        }
        $aSubmit = $_SESSION['work']['submits'][$nID];
    }

    if ($_AUTH['level'] <= LEVEL_OWNER) {
        $_DB->beginTransaction();
        if ($_PATH_ELEMENTS[2] == 'variant') {
            $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET statusid = ? WHERE id = ? AND statusid = ?', array(STATUS_PENDING, $aSubmit['variants'][0], STATUS_IN_PROGRESS));
        } elseif ($_PATH_ELEMENTS[2] == 'individual') {
            if (!empty($aSubmit['phenotypes'])) {
                $q = $_DB->query('UPDATE ' . TABLE_PHENOTYPES . ' SET statusid = ? WHERE id IN (?' . str_repeat(', ?', count($aSubmit['phenotypes']) - 1) . ') AND statusid = ?', array_merge(array(STATUS_PENDING), $aSubmit['phenotypes'], array(STATUS_IN_PROGRESS)));
            }
            if (!empty($aSubmit['variants'])) {
                $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET statusid = ? WHERE id IN (?' . str_repeat(', ?', count($aSubmit['variants']) - 1) . ') AND statusid = ?', array_merge(array(STATUS_PENDING), $aSubmit['variants'], array(STATUS_IN_PROGRESS)));
            }
            $q = $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET statusid = ? WHERE id = ? AND statusid = ?', array(STATUS_PENDING, $nID, STATUS_IN_PROGRESS));
        }
        if (!$q->rowCount()) {
            // This can only happen if a LEVEL_ADMIN deletes(or changes the status of) the entry before the submitter gets here
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Submission entry not found!', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
        $_DB->commit();
    }

    if ($_PATH_ELEMENTS[2] == 'variant') {
        unset($_SESSION['work']['submits']['variantid'][$nID]);
        header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nID);
        define('LOG_EVENT', 'SubmitVariant');
        define('PAGE_TITLE', 'Submit a variant');
    } elseif ($_PATH_ELEMENTS[2] == 'individual') {
        unset($_SESSION['work']['submits'][$nID]);
        header('Refresh: 3; url=' . lovd_getInstallURL() . 'individuals/' . $nID);
        define('LOG_EVENT', 'SubmitIndividual');
        define('PAGE_TITLE', 'Submit an individual');
    }

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);
    $aTo = array();
    $q = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING(id) WHERE vot.id IS NULL AND v.id IN (?' . str_repeat(', ?', count($aSubmit['variants']) - 1) . ')', $aSubmit['variants']);
    if ($q->rowCount()) {
        $aTo = $_DB->query('SELECT name, email FROM ' . TABLE_USERS . ' WHERE level = ' . LEVEL_MANAGER)->fetchAll(PDO::FETCH_NUM);
    }
    $aTo = array_merge($aTo, $_DB->query('SELECT DISTINCT u.name, u.email FROM ' . TABLE_CURATES . ' AS c, ' . TABLE_USERS . ' AS u WHERE c.userid = u.id ' . (count($aTo)? 'AND u.level != ' . LEVEL_MANAGER . ' ' : '') . 'AND c.geneid ' . (empty($aGenes)? 'NOT ' : '') . 'IN (?' . str_repeat(', ?', count($aGenes) - 1) . ') AND allow_edit = 1 ORDER BY u.level DESC, u.name', $aGenes)->fetchAll(PDO::FETCH_NUM));

    // Array containing submitter fields.
    $aSubmitterDetails = array(
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
    $aIndividualFields = array(
                                'zIndividualDetails',
                                'id' => 'Individual ID',
                                'panel_' => 'Panel?',
                                'panel_size' => 'Panel size',
                              );
    $aPhenotypeFields = array(
                               '',
                               'diseaseid_' => 'Disease',
                               'id' => 'Phenotype ID',
                             );
    $aScreeningFields = array(
                               '',
                               'id' => 'Screening ID',
                             );
    $aVariantOnGenomeFields = array(
                                     '',
                                     'id' => 'Variant ID',
                                     'allele_' => 'Allele',
                                     'pathogenicid_' => 'Affects function',
                                     'chromosome' => 'Chromosome',
                                   );
    $aVariantOnTranscriptFields = array(
                                         '',
                                         'id_ncbi_' => 'On transcript',
                                         'pathogenicid_' => 'Affects function',
                                       );

    $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id NOT LIKE "VariantOnTranscript/%" ' . (empty($aSubmit['screenings'])? 'AND c.id NOT LIKE "Screening/%" ' : '') . ($_PATH_ELEMENTS[2] != 'individual'? 'AND c.id NOT LIKE "Individual/%" ' : '') . 'AND c.id NOT LIKE "Phenotype/%" ORDER BY c.col_order', array());
    while ($aCol = $qCols->fetchAssoc()) {
        $aFieldsName = explode('/', $aCol['id']);
        $sFieldsName = 'a' . $aFieldsName[0] . 'Fields';
        ${$sFieldsName}[$aCol['id']] = $aCol['head_column'];
    }

    foreach ($aGenes as $sGene) {
        $sColNamesVOT = 'VOTCols_' . $sGene;
        $$sColNamesVOT = $aVariantOnTranscriptFields;
        $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) WHERE c.id LIKE "VariantOnTranscript/%" AND sc.geneid = ? ORDER BY sc.col_order', array($sGene));
        while ($aCol = $qCols->fetchAssoc()) {
            ${$sColNamesVOT}[$aCol['id']] = $aCol['head_column'];
        }
    }

    if (!empty($aSubmit['phenotypes'])) {
        foreach ($aDiseases as $sDisease) {
            $sColNamesPhen = 'PhenCols_' . $sDisease;
            $$sColNamesPhen = $aPhenotypeFields;
            $qCols = $_DB->query('SELECT c.id, c.head_column FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) WHERE c.id LIKE "Phenotype/%" AND sc.diseaseid = ? ORDER BY sc.col_order', array($sDisease));
            while ($aCol = $qCols->fetchAssoc()) {
                ${$sColNamesPhen}[$aCol['id']] = $aCol['head_column'];
            }
        }
    }

    $_AUTH['country_'] = $_DB->query('SELECT name FROM ' . TABLE_COUNTRIES . ' WHERE id = ?', array($_AUTH['countryid']))->fetchColumn();

    $bUnpublished = false;
    if ($_PATH_ELEMENTS[2] == 'individual') {
        $zIndividualDetails = $_DB->query('SELECT i.*, u.name AS owned_by_ FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (i.owned_by = u.id) WHERE i.id = ?', array($nID))->fetchAssoc();
        $zIndividualDetails['panel_'] = ($zIndividualDetails['panel_size'] <= 1? 'No' : 'Yes');
        ($zIndividualDetails['owned_by'] != $_AUTH['id']? $zIndividualDetails['owned_by_'] = 'Data owner' : false);
        $zIndividualDetails['statusid_'] = $_SETT['data_status'][$zIndividualDetails['statusid']];
        $bUnpublished = ($bUnpublished || $zIndividualDetails['statusid'] < STATUS_MARKED);
        if ($zIndividualDetails['panel_size'] <= 1) { 
            unset($aIndividualFields['panel_size']);
        }
        $aIndividualFields['statusid_'] = 'Data status';
        $aIndividualDetails = $aIndividualFields;

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
                (${$sVariableNameSCR}['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
                $aScreeningDetails[] = $a;
            }
        }
    }

    $aVariantDetails = array();
    foreach ($aSubmit['variants'] as $nVariantID) {
        $sVariableNameVOG = 'zVariantOnGenomeDetails_' . $nVariantID;
        $a = $aVariantOnGenomeFields;
        $a[0] = $sVariableNameVOG;
        $$sVariableNameVOG = $_DB->query('SELECT v.*, u.name AS owned_by_ FROM ' . TABLE_VARIANTS . ' AS v LEFT OUTER JOIN ' . TABLE_USERS . ' AS u ON (v.owned_by = u.id) WHERE v.id = ?', array($nVariantID))->fetchAssoc();
        ${$sVariableNameVOG}['allele_'] = $_SETT['var_allele'][${$sVariableNameVOG}['allele']];
        ${$sVariableNameVOG}['pathogenicid_'] = (!empty(${$sVariableNameVOG}['pathogenicid'])? $_SETT['var_pathogenic'][${$sVariableNameVOG}['pathogenicid']] : '');
        (${$sVariableNameVOG}['owned_by'] != $_AUTH['id']? $a['owned_by_'] = 'Data owner' : false);
        ${$sVariableNameVOG}['statusid_'] = $_SETT['data_status'][${$sVariableNameVOG}['statusid']];
        $bUnpublished = ($bUnpublished || ${$sVariableNameVOG}['statusid'] < STATUS_MARKED);
        $a['statusid_'] = 'Data status';
        $aVariantDetails[] = $a;

        $q = $_DB->query('SELECT vot.*, CONCAT(t.id_ncbi, " (", t.geneid, ")") AS id_ncbi_, t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id = ?', array($nVariantID));
        while ($z = $q->fetchAssoc()) {
            $sVariableNameVOT = 'zVariantOnTranscriptDetails_' . $nVariantID . '_' . $z['transcriptid'];
            $sColNamesVOT = 'VOTCols_' . $z['geneid'];
            ${$sColNamesVOT}[0] = $sVariableNameVOT;
            $aVariantDetails[] = $$sColNamesVOT;
            $z['pathogenicid_'] = (!empty($z['pathogenicid'])? $_SETT['var_pathogenic'][$z['pathogenicid']] : '');
            $$sVariableNameVOT = $z;
        }
        $aVariantDetails[] = 'skip';
        $aVariantDetails[] = 'hr';
    }
    array_pop($aVariantDetails);
    array_pop($aVariantDetails);

    $sMessage = 'Dear Curator' . (count($aTo) > 1? 's' : '') . ',' . "\n\n" .
                $_AUTH['name'] . ' has submitted an addition to the LOVD database.' . "\n";
    if ($bUnpublished) {
        $sMessage .= '(Part of) this submission won\'t be viewable to the public until you as curator agree with the additions. Below is a copy of the submission.' . "\n\n";
    }

    if ($_CONF['location_url']) {
        $sMessage .= 'To view the new entry, click this link (you may need to log in first):' . "\n" .
                     $_CONF['location_url'] . $_PATH_ELEMENTS[2] . 's/' . $nID . "\n\n";
    }
    $sMessage .= 'Regards,' . "\n" .
                 '    LOVD system at ' . $_CONF['institute'] . "\n\n";

    $aBody = array($sMessage, 'submitter_details' => $aSubmitterDetails);
    if ($_PATH_ELEMENTS[2] == 'individual') {
        $aBody['individual_details'] = $aIndividualDetails;
        if (!empty($aSubmit['phenotypes'])) {
            $aBody['phenotype_details'] = $aPhenotypeDetails;
        }
        if (!empty($aSubmit['screenings'])) {
            $aBody['screening_details'] = $aScreeningDetails;
        }
    }
    $aBody['variant_details'] = $aVariantDetails;
    require ROOT_PATH . 'inc-lib-form.php';
    $sBody = lovd_formatMail($aBody);

    // Set proper subject.
    $sSubject = 'LOVD submission' . (!empty($aGenes)? ' (' . implode(', ', array_slice($aGenes, 0, 20)) . (count($aGenes) > 20? ', ...' : '') . ')' : '');

    // Remove the submitter from the CC if he is already mailed as curator.
    $aSubmitter = array(array($_AUTH['name'], $_AUTH['email']));
    foreach ($aTo as $aRecipient) {
        list($sName, $sEmails) = array_values($aRecipient);
        if ($sName == $_AUTH['name']) {
            $aSubmitter = array();
        }
    }    

    // Send mail.
    $bMail = lovd_sendMail($aTo, $sSubject, $sBody, 
                           $_SETT['email_headers'] . PHP_EOL .
                           'Reply-To: ' . $_AUTH['email'], $_CONF['send_admin_submissions'], $aSubmitter);

    lovd_showInfoTable('Successfully processed your submission and sent an e-mail notification to the relevant curator(s)!', 'success');
    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
