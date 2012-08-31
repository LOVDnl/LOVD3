<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2012-08-07
 * For LOVD    : 3.0-beta-08
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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





if (!ACTION && (empty($_PE[1]) || preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])))) {
    // URL: /transcripts
    // URL: /transcripts/DMD
    // View all entries.

    if (empty($_PE[1])) {
        $sGene = '';
    } else {
        $sGene = rawurldecode($_PE[1]);
        $_GET['search_geneid'] = '="' . $sGene . '"';
        lovd_isAuthorized('gene', $sGene);
    }
    define('PAGE_TITLE', 'View transcripts' . ($sGene? ' of gene ' . $sGene : ''));
    $_T->printHeader();
    $_T->printTitle();
    if ($sGene) {
        lovd_printGeneHeader();
    }

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->sSortDefault = ($sGene? 'variants' : 'geneid');
    $_DATA->viewList('Transcripts', ($sGene? 'geneid' : ''), false, false, (bool) ($_AUTH['level'] >= LEVEL_CURATOR));

    if ($sGene) {
        lovd_printGeneFooter();
    }
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /transcripts/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View transcript #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this transcript.
    lovd_isAuthorized('transcript', $nID); // This call will make database queries if necessary.

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        $aNavigation[CURRENT_PATH . '?edit']      = array('menu_edit.png', 'Edit transcript information', 1);
        $aNavigation[CURRENT_PATH . '?delete']    = array('cross.png', 'Delete transcript entry', 1);
    }
    lovd_showJGNavigation($aNavigation, 'Transcripts');

    $_GET['search_transcriptid'] = $nID;
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Variants', 'H4');
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = new LOVD_TranscriptVariant($zData['geneid']);
    $_DATA->sSortDefault = 'VariantOnTranscript/DNA';
    $_DATA->setRowLink('VOT_for_T_VE', 'javascript:window.location.href=\'' . lovd_getInstallURL() . 'variants/{{ID}}#{{transcriptid}}\'; return false');
    $_DATA->viewList('VOT_for_T_VE', array('geneid', 'transcriptid', 'id_ncbi', 'id_'));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && !ctype_digit($_PE[1]) && !ACTION) {
    // URL: /transcripts/NM_004006.2
    // Try to find a transcripts by its NCBI ID and forward.
    // When we have multiple hits, refer to listView.

    $sID = rawurldecode($_PE[1]);
    if ($nID = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($sID))->fetchColumn()) {
        header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);
    } else {
        define('PAGE_TITLE', 'View transcript');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
    }
    exit;
}





if (ACTION == 'create') {
    // URL: /transcripts?create
    // URL: /transcripts/DMD?create
    // Add a new transcript to a gene

    define('LOG_EVENT', 'TranscriptCreate');

    // If no gene given, ask for it and forward user.
    if (!isset($_PE[1])) {
        define('PAGE_TITLE', 'Add transcript to a gene');

        // Is user authorized in any gene?
        lovd_isAuthorized('gene', $_AUTH['curates']);
        lovd_requireAUTH(LEVEL_CURATOR);

        $_T->printHeader();
        $_T->printTitle();
        require ROOT_PATH . 'inc-lib-form.php';

        print('      Please select the gene on which you wish to add a transcript.<BR>' . "\n" .
              '      <BR>' . "\n\n" .
              '      <FORM name="transcriptsCreate" method="post" onsubmit="window.location = \'' . $_PE[0] . '/\' + this.geneSymbol.options[this.geneSymbol.selectedIndex].value + \'?' . ACTION . '\'; return false;">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $sSQL = 'SELECT id, CONCAT(id, " (", name, ")") AS name FROM ' . TABLE_GENES . ' ORDER BY id';
            $aSQL = array();
        } else {
            $sSQL = 'SELECT g.id, CONCAT(g.id, " (", g.name, ")") AS name FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_CURATES . ' AS cu ON (cu.geneid = g.id) WHERE cu.userid = ? AND allow_edit = 1 ORDER BY g.id';
            $aSQL = array($_AUTH['id']);
        }

        $aSelectGene = $_DB->query($sSQL, $aSQL)->fetchAllCombine();

        // Select currently selected gene, if any.
        $_POST['geneSymbol'] = $_SESSION['currdb'];

        // Array which will make up the form table.
        $aFormData = array(
                            array('POST', '', '', '', '20%', '14', '80%'),
                            array('Gene', '', 'select', 'geneSymbol', 1, $aSelectGene, false, false, false),
                            array('', '', 'submit', 'Continue &raquo;'),
                          );
        lovd_viewForm($aFormData);
        print('</TABLE></FORM>' . "\n\n");

        $_T->printFooter();
        exit;
    }



    define('PAGE_TITLE', 'Add transcript to gene ' . $_PE[1]);
    $sPath = CURRENT_PATH . '?' . ACTION;
    $sPathBase = $_PE[0] . '?' . ACTION;
    // Gene given, check validity.
    if (!in_array($_PE[1], lovd_getGeneList())) {
        header('Refresh: 3; url=' . lovd_getInstallURL() . $sPathBase);
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Invalid gene symbol. Redirecting to gene selection...', 'warning');

        $_T->printFooter();
        exit;
    }



    // Is user authorized for the selected gene?
    lovd_isAuthorized('gene', $_PE[1]);
    lovd_requireAUTH(LEVEL_CURATOR);





    // Form has not been submitted yet, build $_SESSION array with transcript data for this gene.
    if (!POST) {
        if (!isset($_SESSION['work'][$sPathBase])) {
            $_SESSION['work'][$sPathBase] = array();
        }

        while (count($_SESSION['work'][$sPathBase]) >= 5) {
            unset($_SESSION['work'][$sPathBase][min(array_keys($_SESSION['work'][$sPathBase]))]);
        }

        $zGene = $_DB->query('SELECT id, name, chromosome, refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PE[1]))->fetchAssoc();

        $_T->printHeader();
        $_T->printTitle();
        require ROOT_PATH . 'class/progress_bar.php';
        require ROOT_PATH . 'inc-lib-form.php';

        // Generate a unique workID, that is sortable.
        $nTime = gettimeofday();
        $_POST['workID'] = $nTime['sec'] . $nTime['usec'];

        $sFormNextPage = '<FORM action="' . $sPath . '" id="createTranscript" method="post">' . "\n" .
                         '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                         '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                         '        </FORM>';

        $_BAR = new ProgressBar('', 'Collecting transcript information...', $sFormNextPage);

        $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.

        // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
        flush();

        require ROOT_PATH . 'class/REST2SOAP.php';
        $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
        $_BAR->setMessage('Collecting all available transcripts...');
        $_BAR->setProgress(0);

        $aOutput = $_MutalyzerWS->moduleCall('getTranscriptsAndInfo', array('genomicReference' => $zGene['refseq_UD'], 'geneName' => $zGene['id']));
        if (!is_array($aOutput) && !empty($aOutput)) {
            $_MutalyzerWS->soapError('getTranscriptsAndInfo', array('genomicReference' => $zGene['refseq_UD'], 'geneName' => $zGene['id']), $aOutput);
        } else {
            $_SESSION['work'][$sPathBase][$_POST['workID']]['values'] = array();
            if (!empty($aOutput)) {
                $aTranscriptsInfo = lovd_getElementFromArray('TranscriptInfo', $aOutput, '');
                $aTranscripts = array();
                $aTranscriptsName = array();
                $aTranscriptsMutalyzer = array();
                $aTranscriptsPositions = array();
                $aTranscriptsProtein = array();
                $aResultTranscriptsAdded = $_DB->query('SELECT GROUP_CONCAT(DISTINCT id_ncbi ORDER BY id_ncbi SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid="' . $zGene['id'] . '"')->fetchColumn();
                $aTranscriptsAdded = explode(';', $aResultTranscriptsAdded);
                $nTranscripts = 0;
                foreach ($aTranscriptsInfo as $aTranscript) {
                    if (!in_array($aTranscript['c']['id'][0]['v'], $aTranscriptsAdded)) {
                        $nTranscripts += 1;
                    }
                }
                $nTranscripts = max($nTranscripts, 1);
                $nProgress = 0;
                foreach($aTranscriptsInfo as $aTranscriptInfo) {
                    $aTranscriptInfo = $aTranscriptInfo['c'];
                    $aTranscriptValues = lovd_getAllValuesFromArray('', $aTranscriptInfo);
                    if (!in_array($aTranscriptValues['id'], $aTranscriptsAdded)) {
                        $nProgress += (100/$nTranscripts);
                        $_BAR->setMessage('Collecting ' . $aTranscriptValues['id'] . ' info...');
                        if ($aTranscriptValues['id']) {
                            $aTranscripts[] = $aTranscriptValues['id'];
                            $aTranscriptsName[preg_replace('/\.\d+/', '', $aTranscriptValues['id'])] = str_replace($zGene['name'] . ', ', '', $aTranscriptValues['product']);
                            $aTranscriptsMutalyzer[preg_replace('/\.\d+/', '', $aTranscriptValues['id'])] = str_replace($zGene['id'] . '_v', '', $aTranscriptValues['name']);
                            $aTranscriptsPositions[$aTranscriptValues['id']] = array('chromTransStart' => $aTranscriptValues['chromTransStart'], 'chromTransEnd' => $aTranscriptValues['chromTransEnd'], 'cTransStart' => $aTranscriptValues['cTransStart'], 'cTransEnd' => $aTranscriptValues['sortableTransEnd'], 'cCDSStop' => $aTranscriptValues['cCDSStop']);
                            $aTranscriptsProtein[$aTranscriptValues['id']] = lovd_getElementFromArray('proteinTranscript/id', $aTranscriptInfo, 'v');
                        }
                        $_BAR->setProgress($nProgress);
                    }
                }
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values'] = array(
                                                                          'gene' => $zGene,
                                                                          'transcripts' => $aTranscripts,
                                                                          'transcriptMutalyzer' => $aTranscriptsMutalyzer,
                                                                          'transcriptsProtein' => $aTranscriptsProtein,
                                                                          'transcriptNames' => $aTranscriptsName,
                                                                          'transcriptPositions' => $aTranscriptsPositions,
                                                                          'transcriptsAdded' => $aTranscriptsAdded
                                                                        );
            }
        }

        $_BAR->setProgress(100);
        $_BAR->setMessage('Information collected, now building form...');
        $_BAR->setMessageVisibility('done', true);

        print('<SCRIPT type="text/javascript">' . "\n" .
              '  document.forms[\'createTranscript\'].submit();' . "\n" .
              '</SCRIPT>' . "\n\n");

        print('</BODY>' . "\n" .
          '</HTML>' . "\n");
        exit;
    }

    // Now make sure we have a valid workID.
    if (!isset($_POST['workID']) || !array_key_exists($_POST['workID'], $_SESSION['work'][$sPathBase])) {
        exit;
    }





    require ROOT_PATH . 'inc-lib-form.php';
    // FIXME; $aData would have been a better name.
    $zData = $_SESSION['work'][$sPathBase][$_POST['workID']]['values'];
    if (count($_POST) > 1) {
        // Transcripts have been selected.
        lovd_errorClean();

        // Check if transcripts are in the list, so no data manipulation from user!
        foreach ($_POST['active_transcripts'] as $sTranscript) {
            if ($sTranscript && (!in_array($sTranscript, $zData['transcripts']) || in_array($sTranscript, $zData['transcriptsAdded']))) {
                return lovd_errorAdd('active_transcripts', 'Please select a proper transcriptomic reference from the selection box.');
            }
        }

        if (!lovd_error()) {
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            // FIXME; shouldn't this check be done before looping through active_transcripts above? This setup allows submission of the form when selecting "No transcripts available".
            if (!empty($_POST['active_transcripts']) && $_POST['active_transcripts'][0] != '') {
                $aSuccessTranscripts = array();
                foreach($_POST['active_transcripts'] as $sTranscript) {
                    // Add transcript to gene.
                    $sTranscriptProtein = (isset($zData['transcriptsProtein'][$sTranscript])? $zData['transcriptsProtein'][$sTranscript] : '');
                    $nMutalyzerID = $zData['transcriptMutalyzer'][preg_replace('/\.\d+/', '', $sTranscript)];
                    $sTranscriptName = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)];
                    $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                    $q = $_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_mutalyzer, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) ' .
                                     'VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                                     array($zData['gene']['id'], $sTranscriptName, $nMutalyzerID, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $_POST['created_by']));
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - could not be added to gene ' . $zData['gene']['id']);
                    } else {
                        $aSuccessTranscripts[] = $sTranscript;

                        // Turn off the MAPPING_DONE flags for variants within range of this transcript, so that automatic mapping will pick them up again.
                        $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = mapping_flags & ~' . MAPPING_DONE . ' WHERE chromosome = ? AND ' .
                                         '(position_g_start BETWEEN ? AND ?) OR ' .
                                         '(position_g_end   BETWEEN ? AND ?) OR ' .
                                         '(position_g_start < ? AND position_g_end > ?)',
                                         array($zData['gene']['chromosome'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd']));
                        if ($q->rowCount()) {
                            // If we have changed variants, turn on mapping immediately.
                            $_SESSION['mapping']['time_complete'] = 0;
                        }
                    }
                }
                if (count($aSuccessTranscripts)) {
                    lovd_writeLog('Event', LOG_EVENT, 'Transcript information entries successfully added to gene ' . $zData['gene']['id'] . ' - ' . $zData['gene']['name']);
                }
            }

            unset($_SESSION['work'][$sPathBase][$_POST['workID']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'genes/' . rawurlencode($zData['gene']['id']));

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully added the transcript(s) to gene ' . $zData['gene']['id'], 'success');

            $_T->printFooter();
            exit;
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    if (empty($zData['transcripts'])) {
        lovd_showInfoTable('No more transcripts available that have not been added yet!', 'warning');
        $_T->printFooter();
        exit;
    }

    if (!lovd_error()) {
        print('      To add the selected transcripts to the gene, please press "Add" at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    $atranscriptNames = array();
    $aTranscriptsForm = array();
    foreach ($zData['transcripts'] as $sTranscript) {
        if (!isset($aTranscriptNames[preg_replace('/\.\d+/', '', $sTranscript)])) {
            $aTranscriptsForm[$sTranscript] = lovd_shortenString($zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)], 50);
            $aTranscriptsForm[$sTranscript] .= str_repeat(')', substr_count($aTranscriptsForm[$sTranscript], '(')) . ' (' . $sTranscript . ')';
        }
    }
    asort($aTranscriptsForm);

    $nTranscriptsFormSize = (count($aTranscriptsForm) < 10? count($aTranscriptsForm) : 10);

    // Table.
    print('      <FORM action="' . $sPath . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Transcriptomic reference sequence(s)', 'Select transcript references (NM accession numbers). Please note that transcripts already added to this gene database, are not shown', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, true),
                        array('', '', 'submit', 'Add transcript(s) to gene'),
                      ));
    lovd_viewForm($aForm);

    print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /transcripts/00001?edit
    // Edit a transcript

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit transcript #' . $nID);
    define('LOG_EVENT', 'TranscriptEdit');

    // Load appropiate user level for this transcript.
    lovd_isAuthorized('transcript', $nID); // This call will make database queries if necessary.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_transcripts.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA = new LOVD_Transcript();
    $zData = $_DATA->loadEntry($nID);

    if (count($_POST) > 1) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array(
                            'id_ensembl', 'id_protein_ensembl', 'id_protein_uniprot',
                            );

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited transcript information entry #' . $nID . ' (' . $zData['geneid'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the gene information entry!', 'success');

            $_T->printFooter();
            exit;
        }
    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }

    $_T->printHeader();
    $_T->printTitle();

    if (!lovd_error()) {
        print('      To edit this transcript, please complete the form below and press "Edit" at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit transcript information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /transcripts/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete transcript information entry #' . $nID);
    define('LOG_EVENT', 'TranscriptDelete');

    // Load appropiate user level for this transcript.
    lovd_isAuthorized('transcript', $nID); // This call will make database queries if necessary.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in variants.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted transcript information entry ' . $nID . ' - ' . $zData['geneid'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the transcript information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Deleting transcript information entry', '', 'print', $nID . ' - ' . $zData['name'] . ' (' . $zData['geneid'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete transcript information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
