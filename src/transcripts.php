<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2022-06-07
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (!ACTION && (empty($_PE[1]) || preg_match('/^[a-z][a-z0-9#@-]*$/i', $_PE[1]))) {
    // URL: /transcripts
    // URL: /transcripts/DMD
    // View all entries.

    if (empty($_PE[1])) {
        $sGene = '';
    } else {
        $sGene = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PE[1]))->fetchColumn();
        $_GET['search_geneid'] = '="' . $sGene . '"';
        lovd_isAuthorized('gene', $sGene);
    }
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();
    if ($sGene) {
        lovd_printGeneHeader();
    }

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    if ($sGene) {
        // Standard sorting is on geneid, that is not useful. Choose an alternative.
        if ($_SETT['customization_settings']['transcripts_VL_show_variant_counts']) {
            $_DATA->sSortDefault = 'variants';
        } else {
            $_DATA->sSortDefault = 'id_ncbi';
        }
    }
    $aVLOptions = array(
        'cols_to_skip' => ($sGene? array('geneid') : array()),
        'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
    );
    $_DATA->viewList('Transcripts', $aVLOptions);

    if ($sGene) {
        lovd_printGeneFooter();
    }
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /transcripts/00000001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this transcript.
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
    $aVLOptions = array(
        'cols_to_skip' => array('geneid', 'transcriptid', 'id_ncbi'),
    );
    $_DATA->viewList('VOT_for_T_VE', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && !ctype_digit($_PE[1]) && !ACTION) {
    // URL: /transcripts/NM_004006.2
    // Try to find a transcripts by its NCBI ID and forward.
    // When we have multiple hits, refer to listView.

    $sID = $_PE[1];
    if ($nID = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($sID))->fetchColumn()) {
        header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);
    } else {
        define('PAGE_TITLE', 'Transcript ' . $sID);
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

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();

    // If no gene given, ask for it and forward user.
    if (!isset($_PE[1])) {
        define('PAGE_TITLE', 'Add transcript entry to a gene');

        // Is user authorized in any gene?
        if ($_AUTH) {
            lovd_isAuthorized('gene', $_AUTH['curates']);
        }
        lovd_requireAUTH(LEVEL_CURATOR);

        $_T->printHeader();
        $_T->printTitle();

        // Show a VL of genes instead of a dropdown.
        $sViewListID = 'Genes_for_createTranscript';
        require ROOT_PATH . 'class/object_genes.php';
        $_GET['page_size'] = 25;
        // For curators, select only the genes they are curator for. That is, if the list is not too big.
        if ($_AUTH['level'] == LEVEL_CURATOR && count($_AUTH['curates']) <= 25) {
            $_GET['search_geneid'] = '="' . implode('"|="', $_AUTH['curates']) . '"';
        }
        // Select currently selected gene, if any.
        // But only if we're managers or if this is a curator that is curator of the currently selected gene.
        if ($_SESSION['currdb'] && ($_AUTH['level'] >= LEVEL_MANAGER
                || (isset($_AUTH['curates']) && in_array($_SESSION['currdb'], $_AUTH['curates'])))) {
            $_GET['search_id_'] = '="' . $_SESSION['currdb'] . '"';
        }
        $_DATA = new LOVD_Gene();
        $_DATA->setRowLink($sViewListID, $_PE[0] . '/' . $_DATA->sRowID . '?create');
        lovd_showInfoTable('Please select the gene to which you wish to add a new transcript. <B>Click on the gene to proceed.</B>', 'information', 600);
        $aVLOptions = array(
            'cols_to_skip' => array('variants', 'uniq_variants', 'updated_date_', 'diseases_'),
        );
        $_DATA->viewList($sViewListID, $aVLOptions);

        $_T->printFooter();
        exit;
    }



    // Gene given, check validity.
    $sGene = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PE[1]))->fetchColumn();
    define('PAGE_TITLE', 'Add transcript entry to ' . (!$sGene? 'a' : ' the ' . $sGene) . ' gene');
    $sPath = CURRENT_PATH . '?' . ACTION;
    $sPathBase = $_PE[0] . '?' . ACTION;
    if (!$sGene) {
        header('Refresh: 3; url=' . lovd_getInstallURL() . $sPathBase);
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Invalid gene symbol. Redirecting to gene selection...', 'warning');

        $_T->printFooter();
        exit;
    }



    // Is user authorized for the selected gene?
    lovd_isAuthorized('gene', $sGene);
    lovd_requireAUTH(LEVEL_CURATOR);





    // Form has not been submitted yet, build $_SESSION array with transcript data for this gene.
    if (!POST) {
        if (!isset($_SESSION['work'][$sPathBase])) {
            $_SESSION['work'][$sPathBase] = array();
        }

        while (count($_SESSION['work'][$sPathBase]) >= 5) {
            unset($_SESSION['work'][$sPathBase][min(array_keys($_SESSION['work'][$sPathBase]))]);
        }

        $zGene = $_DB->query('SELECT id, name, chromosome, refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($sGene))->fetchAssoc();

        $_T->printHeader();
        $_T->printTitle();
        require ROOT_PATH . 'class/progress_bar.php';
        require ROOT_PATH . 'inc-lib-form.php';

        // Generate a unique workID, that is sortable.
        $nTime = gettimeofday();
        $_POST['workID'] = $nTime['sec'] . $nTime['usec'];

        $sFormNextPage = '<FORM action="' . $sPath . '" id="createTranscript" method="post">' . "\n" .
                         '          <INPUT type="hidden" name="workID" value="' . htmlspecialchars($_POST['workID']) . '">' . "\n" .
                         '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                         '        </FORM>';

        $_BAR = new ProgressBar('', 'Collecting transcript information...', $sFormNextPage);

        $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.

        // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
        flush();

        $_BAR->setMessage('Collecting all available transcripts...');
        $_BAR->setProgress(0);

        $aTranscripts = $_DATA->getTranscriptPositions($zGene['refseq_UD'], $zGene['id'], $zGene['name'], $nProgress);

        $_SESSION['work'][$sPathBase][$_POST['workID']]['values'] = array(
                                                                  'gene' => $zGene,
                                                                  'transcripts' => $aTranscripts['id'],
                                                                  'transcriptMutalyzer' => $aTranscripts['mutalyzer'],
                                                                  'transcriptsProtein' => $aTranscripts['protein'],
                                                                  'transcriptNames' => $aTranscripts['name'],
                                                                  'transcriptPositions' => $aTranscripts['positions'],
                                                                  'transcriptsAdded' => $aTranscripts['added'],
                                                                );

        if (!$aTranscripts['id']) {
            // Mutalyzer doesn't see any transcripts at all.
            // The future is VV. However, as we're currently still using
            //  Mutalyzer for mapping, only use VV when Mutalyzer fails.
            require ROOT_PATH . 'class/variant_validator.php';
            $_VV = new LOVD_VV();
            $aData = $_VV->getTranscriptsByGene($zGene['id']);
            $aTranscripts = array();
            foreach ($aData['data'] as $sTranscript => $aTranscript) {
                // Look for transcripts with genomic locations on this build.
                if (!$aTranscript['genomic_positions'] || !isset($aTranscript['genomic_positions'][$_CONF['refseq_build']][$zGene['chromosome']])) {
                    continue;
                }
                // FIXME: When we're switching to VV, fix this ridiculous format.
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values']['transcripts'][] = $sTranscript;
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values']['transcriptMutalyzer'][$sTranscript] = 0;
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values']['transcriptsProtein'][$sTranscript] = $aTranscript['id_ncbi_protein'];
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values']['transcriptNames'][$sTranscript] = $aTranscript['name'];
                $_SESSION['work'][$sPathBase][$_POST['workID']]['values']['transcriptPositions'][$sTranscript] = array(
                    'chromTransStart' => $aTranscript['genomic_positions'][$_CONF['refseq_build']][$zGene['chromosome']]['start'],
                    'chromTransEnd' => $aTranscript['genomic_positions'][$_CONF['refseq_build']][$zGene['chromosome']]['end'],
                    'cTransStart' => -$aTranscript['transcript_positions']['cds_start'] + 1, // FIXME; Fix the database, the VV model is more logical.
                    'cTransEnd' => $aTranscript['transcript_positions']['length'] - $aTranscript['transcript_positions']['cds_start'] + 1, // FIXME; Fix the database, the VV model is more logical.
                    'cCDSStop' => $aTranscript['transcript_positions']['cds_length'],
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
                    if (!$sTranscript) {
                        continue;
                    }
                    // FIXME; If else statement is temporary. Now, we only use the transcript object to save the transcripts for mitochondrial genes.
                    // Later all transcripts will be saved like this. For the sake of clarity this will be done in a separate commit.
                    if (isset($_SETT['mito_genes_aliases'][$zData['gene']['id']])) {
                        $zDataTranscript = array(
                            'geneid' => $zData['gene']['id'],
                            // Until revision 679 the transcript version was not used in the index.
                            // Can not figure out why version is not included. Therefore, for now we will do without.
                            'name' => $zData['transcriptNames'][$sTranscript],
                            'id_mutalyzer' => $zData['transcriptMutalyzer'][$sTranscript],
                            'id_ncbi' => $sTranscript,
                            'id_ensembl' => '',
                            'id_protein_ncbi' => $zData['transcriptsProtein'][$sTranscript],
                            'id_protein_ensembl' => '',
                            'id_protein_uniprot' => '',
                            'remarks' => '',
                            'position_c_mrna_start' => $zData['transcriptPositions'][$sTranscript]['cTransStart'],
                            'position_c_mrna_end' => $zData['transcriptPositions'][$sTranscript]['cTransEnd'], // Is actually sortableTransEnd.
                            'position_c_cds_end' => $zData['transcriptPositions'][$sTranscript]['cCDSStop'],
                            'position_g_mrna_start' => $zData['transcriptPositions'][$sTranscript]['chromTransStart'],
                            'position_g_mrna_end' => $zData['transcriptPositions'][$sTranscript]['chromTransEnd'],
                            'created_date' => date('Y-m-d H:i:s'),
                            'created_by' => $_POST['created_by']
                        );

                        if (!$_DATA->insertEntry($zDataTranscript, array_keys($zDataTranscript))) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - ' . ' - could not be added to gene ' . $_POST['id']);
                            continue;
                        }

                        $aSuccessTranscripts[] = $sTranscript;
                        $_DATA->turnOffMappingDone($zData['gene']['chromosome'], $zData['transcriptPositions'][$sTranscript]);
                    } else {
                        // Add transcript to gene.
                        $sTranscriptProtein = (isset($zData['transcriptsProtein'][$sTranscript])? $zData['transcriptsProtein'][$sTranscript] : '');
                        // Until revision 679 the transcript version was not used in the index.
                        // Can not figure out why version is not included. Therefore, for now we will do without.
                        $nMutalyzerID = $zData['transcriptMutalyzer'][$sTranscript];
                        $sTranscriptName = $zData['transcriptNames'][$sTranscript];

                        $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                        $q = $_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_mutalyzer, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, remarks, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) ' .
                                         'VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                                         array($zData['gene']['id'], $sTranscriptName, $nMutalyzerID, $sTranscript, '', $sTranscriptProtein, '', '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $_POST['created_by']));
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - could not be added to gene ' . $zData['gene']['id']);
                        } else {
                            $aSuccessTranscripts[] = $sTranscript;

                            // Turn off the MAPPING_DONE flags for variants within range of this transcript, so that automatic mapping will pick them up again.
                            $_DATA->turnOffMappingDone($zData['gene']['chromosome'], $aTranscriptPositions);
                        }
                    }
                }
                if (count($aSuccessTranscripts)) {
                    lovd_writeLog('Event', LOG_EVENT, 'Transcript information entries successfully added to gene ' . $zData['gene']['id'] . ' - ' . $zData['gene']['name']);
                }

                // Change updated date for gene.
                lovd_setUpdatedDate($sGene);
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
        // Until revision 679 the transcript version was not used in the index. The version number was removed with preg_replace.
        // Can not figure out why version is not included. Therefore, for now we will do without preg_replace.
        if (!isset($aTranscriptNames[$sTranscript])) {
            $aTranscriptsForm[$sTranscript] = lovd_shortenString($zData['transcriptNames'][$sTranscript], 50);
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

    print('<INPUT type="hidden" name="workID" value="' . htmlspecialchars($_POST['workID']) . '">' . "\n");
    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /transcripts/00000001?edit
    // Edit a transcript

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'TranscriptEdit');

    // Load appropriate user level for this transcript.
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
            $aFields = array('id_ensembl', 'id_protein_ensembl', 'id_protein_uniprot', 'remarks',
                             'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Change updated date for gene.
            lovd_setUpdatedDate($zData['geneid']);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited transcript information entry #' . $nID . ' (' . $zData['geneid'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the transcript!', 'success');

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
    // URL: /transcripts/00000001?delete
    // Drop specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'TranscriptDelete');

    // Load appropriate user level for this transcript.
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

        // User had to enter their password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in variants.
            $_DATA->deleteEntry($nID);

            // Change updated date for gene.
            lovd_setUpdatedDate($zData['geneid']);

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
