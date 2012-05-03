<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2012-05-03
 * For LOVD    : 3.0-beta-05
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

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /genes
    // View all entries.

    define('PAGE_TITLE', 'View all genes');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $_DATA->viewList('Genes', 'geneid');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && !ACTION) {
    // URL: /genes/DMD
    // View specific entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'View gene ' . $sID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this gene.
    lovd_isAuthorized('gene', $sID);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->viewEntry($sID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[CURRENT_PATH . '?edit']            = array('menu_edit.png', 'Edit gene information</A>', 1);
        $aNavigation['transcripts/' . $sID . '?create'] = array('menu_plus.png', 'Add transcript(s) to gene', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation[CURRENT_PATH . '?delete']    = array('cross.png', 'Delete gene entry', 1);
            $aNavigation[CURRENT_PATH . '?authorize'] = array('', 'Add/remove curators/collaborators', 1);
        } else {
            $aNavigation[CURRENT_PATH . '?sortCurators'] = array('', 'Sort/hide curators/collaborators names', 1);
        }
        $aNavigation[CURRENT_PATH . '/columns']       = array('menu_columns.png', 'View enabled variant columns', 1);
        $aNavigation[CURRENT_PATH . '/columns?order'] = array('menu_columns.png', 'Re-order enabled variant columns', 1);
    }
    lovd_showJGNavigation($aNavigation, 'Genes');

    // Disclaimer.
    if ($zData['disclaimer']) {
        print('<BR>' . "\n\n" .
              '      <TABLE border="0" cellpadding="0" cellspacing="1" width="950" class="data">' . "\n" .
              '        <TR>' . "\n" .
              '          <TH class="S15">Copyright &amp; disclaimer</TH></TR>' . "\n" .
              '        <TR class="S11">' . "\n" .
              '          <TD>' . $zData['disclaimer_text_'] . '</TD></TR></TABLE><BR>' . "\n\n");
    }

    $_GET['search_geneid'] = '="' . $sID . '"';
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Active transcripts', 'H4');
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->setSortDefault('variants');
    $_DATA->viewList('Transcripts_for_G_VE', 'geneid', true, true);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /genes?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new gene information entry');
    define('LOG_EVENT', 'GeneCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    require ROOT_PATH . 'inc-lib-genes.php';
    $_DATA = new LOVD_Gene();

    $sPath = CURRENT_PATH . '?' . ACTION;
    if (GET) {
        if (!isset($_SESSION['work'][$sPath])) {
            $_SESSION['work'][$sPath] = array();
        } 

        while (count($_SESSION['work'][$sPath]) >= 5) {
            unset($_SESSION['work'][$sPath][min(array_keys($_SESSION['work'][$sPath]))]);
        }

        // Generate an unique workID that is sortable.
        $_POST['workID'] = (string) microtime(true);
        $_SESSION['work'][$sPath][$_POST['workID']]['step'] = '1';
    }

    if ($_SESSION['work'][$sPath][$_POST['workID']]['step'] == '1') {
        if (POST) {
            lovd_errorClean();

            if (empty($_POST['hgnc_id'])) {
                lovd_errorAdd('hgnc_id', 'No HGNC ID or Gene symbol was specified');

            } else {
                // Gene Symbol must be unique.
                // Enforced in the table, but we want to handle this gracefully.
                $sSQL = 'SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE id = ? OR id_hgnc = ?';
                $aSQL = array($_POST['hgnc_id'], $_POST['hgnc_id']);

                if ($_DB->query($sSQL, $aSQL)->fetchColumn()) {
                    lovd_errorAdd('hgnc_id', 'This gene entry is already present in this LOVD installation.');
                } else {
                    // This call already makes the needed lovd_errorAdd() calls.
                    $aGeneInfo = lovd_getGeneInfoFromHgnc($_POST['hgnc_id'], array('gd_hgnc_id', 'gd_app_sym', 'gd_app_name', 'gd_pub_chrom_map', 'gd_pub_eg_id', 'md_mim_id'));
                    if (!empty($aGeneInfo)) {
                        list($sHgncID, $sSymbol, $sGeneName, $sChromLocation, $sEntrez, $sOmim) = array_values($aGeneInfo);
                        list($sEntrez, $sOmim) = array_map('trim', array($sEntrez, $sOmim));
                    }
                }
            }

            if (!lovd_error()) {
                $_T->printHeader();
                require ROOT_PATH . 'class/progress_bar.php';

                $sFormNextPage = '<FORM action="' . CURRENT_PATH . '?' . ACTION . '" id="createGene" method="post">' . "\n" .
                                 '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                                 '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                                 '        </FORM>';

                $_BAR = new ProgressBar('', 'Collecting gene information...', $sFormNextPage);

                $_T->printFooter(false);

                // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
                flush();

                $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);

                // Get LRG if it exists
                $aRefseqGenomic = array();
                $_BAR->setMessage('Checking for LRG...');
                if ($sLRG = lovd_getLRGbyGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sLRG;
                }

                // Get NG if it exists
                $_BAR->setMessage('Checking for NG...');
                $_BAR->setProgress(16);
                if ($sNG = lovd_getNGbyGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sNG;
                }

                // Get NC from LOVD
                $_BAR->setMessage('Checking for NC...');
                $_BAR->setProgress(33);

                if ($sChromLocation == 'mitochondria') {
                    $sChromosome = 'M';
                    $sChromBand = '';
                } else {
                    preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches);
                    $sChromosome = $aMatches[1];
                    $sChromBand = $aMatches[2];
                }
                $aRefseqGenomic[] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$sChromosome];

                // Get UDID from mutalyzer
                $_BAR->setMessage('Making a gene slice of the NC...');
                $_BAR->setProgress(49);
                $sRefseqUD = $_MutalyzerWS->moduleCall('sliceChromosomeByGene', array('geneSymbol' => $sSymbol, 'organism' => 'Man', 'upStream' => '5000', 'downStream' => '2000'));
                if (!is_string($sRefseqUD) || empty($sRefseqUD) || !preg_match('/^UD_/', $sRefseqUD)) {
                    (empty($sRefseqUD)? $sRefseqUD = 'Empty array returned from SOAP' : false);
                    $_MutalyzerWS->soapError('sliceChromosomeByGene', array('geneSymbol' => $sSymbol, 'organism' => 'Man', 'upStream' => '5000', 'downStream' => '2000'), $sRefseqUD);
                }

                // Get all transcripts and info
                $_BAR->setMessage('Collecting all available transcripts...');
                $_BAR->setProgress(66);
                $aOutput = $_MutalyzerWS->moduleCall('getTranscriptsAndInfo', array('genomicReference' => $sRefseqUD, 'geneName' => $sSymbol));
                // FIXME; deze IF-boom kan net wat simpeler denk ik, waardoor je niet twee keer hoeft te checken of $aOutput leeg is. Scheelt een niveau.
                if (!is_array($aOutput) && !empty($aOutput)) {
                    $_MutalyzerWS->soapError('getTranscriptsAndInfo', array('genomicReference' => $sRefseqUD, 'geneName' => $sSymbol), $aOutput);
                } else {
                    $aTranscripts = array();
                    if (!empty($aOutput)) {
                        $aTranscriptsInfo = lovd_getElementFromArray('TranscriptInfo', $aOutput, '');
                        $aTranscriptsName = array();
                        $aTranscriptsPositions = array();
                        $aTranscriptsProtein = array();
                        $nTranscripts = count($aTranscriptsInfo);
                        $nProgress = 0.0;
                        foreach($aTranscriptsInfo as $aTranscriptInfo) {
                            $nProgress += (34/$nTranscripts);
                            $aTranscriptInfo = $aTranscriptInfo['c'];
                            $aTranscriptValues = lovd_getAllValuesFromArray('', $aTranscriptInfo);
                            $_BAR->setMessage('Collecting ' . $aTranscriptValues['id'] . ' info...');
                            $aTranscripts[] = $aTranscriptValues['id'];
                            $aTranscriptsName[preg_replace('/\.\d+/', '', $aTranscriptValues['id'])] = str_replace($sGeneName . ', ', '', $aTranscriptValues['product']);
                            $aTranscriptsPositions[$aTranscriptValues['id']] = array('chromTransStart' => $aTranscriptValues['chromTransStart'], 'chromTransEnd' => $aTranscriptValues['chromTransEnd'], 'cTransStart' => $aTranscriptValues['cTransStart'], 'cTransEnd' => $aTranscriptValues['sortableTransEnd'], 'cCDSStop' => $aTranscriptValues['cCDSStop']);
                            $aTranscriptsProtein[$aTranscriptValues['id']] = lovd_getValueFromElement('proteinTranscript/id', $aTranscriptInfo);
                            $_BAR->setProgress(66 + $nProgress);
                        }
                    }
                }
                $_BAR->setProgress(100);
                $_BAR->setMessage('Information collected, now building form...');
                $_BAR->setMessageVisibility('done', true);
                $_SESSION['work'][$sPath][$_POST['workID']]['step'] = '2';
                $_SESSION['work'][$sPath][$_POST['workID']]['values'] = array(
                                                                  'id' => $sSymbol,
                                                                  'name' => $sGeneName,
                                                                  'chromosome' => $sChromosome,
                                                                  'chrom_band' => $sChromBand,
                                                                  'id_hgnc' => $sHgncID,
                                                                  'id_entrez' => $sEntrez,
                                                                  'id_omim' => $sOmim,
                                                                  'transcripts' => $aTranscripts,
                                                                  'transcriptsProtein' => $aTranscriptsProtein,
                                                                  'transcriptNames' => $aTranscriptsName,
                                                                  'transcriptPositions' => $aTranscriptsPositions,
                                                                  'genomic_references' => $aRefseqGenomic,
                                                                  'refseq_UD' => $sRefseqUD,
                                                                );

                print('<SCRIPT type="text/javascript">' . "\n" .
                      '  document.forms[\'createGene\'].submit();' . "\n" .
                      '</SCRIPT>' . "\n\n" .
                      '</BODY>' . "\n" .
                      '</HTML>' . "\n");
                exit;
            }
        }

        $_T->printHeader();
        $_T->printTitle();

        if (GET) {
            print('      Please fill in the HGNC ID or Gene Symbol for the gene database you wish to create.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

        // Array which will make up the form table.
        $aFormData = array(
                            array('POST', '', '', '', '30%', '14', '70%'),
                            array('HGNC ID or Gene Symbol', '', 'text', 'hgnc_id', 10),
                            array('', '', 'submit', 'Continue &raquo;'),
                          );
        lovd_viewForm($aFormData);
        print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
        print('</TABLE></FORM>' . "\n\n");
        print('<SCRIPT type="text/javascript">' . "\n" .
              '  <!--' . "\n" .
              '    document.forms[0].hgnc_id.focus();' . "\n" .
              '  // -->' . "\n" .
              '</SCRIPT>' . "\n");

        $_T->printFooter();
        exit;
    }




    if ($_SESSION['work'][$sPath][$_POST['workID']]['step'] == '2') {
        $zData = $_SESSION['work'][$sPath][$_POST['workID']]['values'];
        if (count($_POST) > 1) {
            lovd_errorClean();

            $_DATA->checkFields($_POST);

            if (!lovd_error()) {
                // Fields to be used.
                $aFields = array(
                                'id', 'name', 'chromosome', 'chrom_band', 'imprinting', 'refseq_genomic', 'refseq_UD', 'reference', 'url_homepage',
                                'url_external', 'allow_download', 'allow_index_wiki', 'id_hgnc', 'id_entrez', 'id_omim', 'show_hgmd',
                                'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'refseq', 'refseq_url', 'disclaimer', 
                                'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align', 'created_by', 'created_date', 
                                );

                // Prepare values.
                $_POST['created_by'] = $_AUTH['id'];
                if (empty($_POST['created_date']) || @strtotime($_POST['created_date']) > time()) {
                    $_POST['created_date'] = date('Y-m-d H:i:s');
                }
                $_POST['id'] = $zData['id'];
                $_POST['name'] = $zData['name'];
                $_POST['refseq_UD'] = $zData['refseq_UD'];
                $_POST['chromosome'] = $zData['chromosome'];
                $_POST['id_hgnc'] = $zData['id_hgnc'];
                $_POST['id_entrez'] = ($zData['id_entrez']? $zData['id_entrez'] : '');
                $_POST['id_omim'] = ($zData['id_omim']? $zData['id_omim'] : '');

                $_DATA->insertEntry($_POST, $aFields);

                // Add the default custom columns to this gene.
                lovd_addAllDefaultCustomColumnsForGene($_POST['id']);

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $_POST['id'] . ' (' . $_POST['name'] . ')');

                // Make current user curator of this gene.
                $_DB->query('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?)', array($_AUTH['id'], $_POST['id'], 1, 1));

                // Add diseases.
                $aSuccessDiseases = array();
                if (!empty($_POST['active_diseases']) && is_array($_POST['active_diseases'])) {
                    foreach ($_POST['active_diseases'] as $nDisease) {
                        // Add disease to gene.
                        if ($nDisease) {
                            $q = $_DB->query('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($_POST['id'], $nDisease), false);
                            if (!$q) {
                                // Silent error.
                                lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nDisease . ' - could not be added to gene ' . $_POST['id']);
                            } else {
                                $aSuccessDiseases[] = $nDisease;
                            }
                        }
                    }
                }

                // Add transcripts.
                $aSuccessTranscripts = array();
                if (!empty($_POST['active_transcripts'])) {
                    foreach($_POST['active_transcripts'] as $sTranscript) {
                        // Gather transcript information from session.
                        $sTranscriptProtein = $zData['transcriptsProtein'][$sTranscript];
                        $sTranscriptName = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)];
                        $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];

                        // Add transcript to gene.
                        $q = $_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) ' .
                                         'VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                                         array($_POST['id'], $sTranscriptName, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $_POST['created_by']));
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - ' . ' - could not be added to gene ' . $_POST['id']);
                        } else {
                            $aSuccessTranscripts[] = $sTranscript;

                            // Turn off the MAPPING_DONE flags for variants within range of this transcript, so that automatic mapping will pick them up again.
                            $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = mapping_flags & ~' . MAPPING_DONE . ' WHERE chromosome = ? AND ' .
                                             '(position_g_start BETWEEN ? AND ?) OR ' .
                                             '(position_g_end   BETWEEN ? AND ?) OR ' .
                                             '(position_g_start < ? AND position_g_end > ?)',
                                             array($_POST['chromosome'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd']));
                            if ($q->rowCount()) {
                                // If we have changed variants, turn on mapping immediately.
                                $_SESSION['mapping']['time_complete'] = 0;
                            }
                        }
                    }
                }

                if (count($aSuccessDiseases) && count($aSuccessTranscripts)) {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease and transcript entries successfully added to gene ' . $_POST['id'] . ' - ' . $_POST['name']);
                } elseif (count($aSuccessDiseases)) {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccessDiseases) > 1? 'ies' : 'y') . ' successfully added to gene ' . $_POST['id'] . ' - ' . $_POST['name']);
                } elseif (count($aSuccessTranscripts)) {
                    lovd_writeLog('Event', LOG_EVENT, 'Transcript entr' . (count($aSuccessTranscripts) > 1? 'ies' : 'y') . ' successfully added to gene ' . $_POST['id'] . ' - ' . $_POST['name']);
                }

                unset($_SESSION['work'][$sPath][$_POST['workID']]);

                // Set currdb.
                $_SESSION['currdb'] = $_POST['id'];
                // These just to have the header what it needs.
                $_SETT['currdb'] = array('id' => $_POST['id'], 'name' => $_POST['name']);

                // Thank the user...
                // 2012-02-01; 3.0-beta-02; If there is only one user, don't forward to the Add curators page.
                if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE id > 0')->fetchColumn() > 1) {
                    header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $_POST['id'] . '?authorize');
                } else {
                    // FIXME; should be sent to list of columns for this gene, but that page does not exist yet.
                    header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $_POST['id']);
                }

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully created the gene information entry!', 'success');

                $_T->printFooter();
                exit;
            }
        } else {
            // Default values.
            $_DATA->setDefaultValues();
        }

        $_T->printHeader();
        $_T->printTitle();

        if (!lovd_error()) {
            print('      To create a new gene database, please complete the form below and press "Create" at the bottom of the form.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Tooltip JS code.
        lovd_includeJS('inc-js-tooltip.php');
        lovd_includeJS('inc-js-custom_links.php');

        // Table.
        print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array_merge(
                     $_DATA->getForm(),
                     array(
                            array('', '', 'submit', 'Create gene information entry'),
                          ));
        lovd_viewForm($aForm);

        print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
        print('</FORM>' . "\n\n");

        $_T->printFooter();
        exit;
    }
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && ACTION == 'edit') {
    // URL: /genes/DMD?edit
    // Edit an entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Edit gene information entry');
    define('LOG_EVENT', 'GeneEdit');

    // Load appropiate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->loadEntry($sID);

    $sPath = $_PE[0] . '?' . ACTION;
    if (GET) {
        require ROOT_PATH . 'inc-lib-genes.php';

        $aRefseqGenomic = array();
        // Get LRG if it exists
        if ($sLRG = lovd_getLRGbyGeneSymbol($sID)) {
            $aRefseqGenomic[] = $sLRG;
        }
        // Get NG if it exists
        if ($sNG = lovd_getNGbyGeneSymbol($sID)) {
            $aRefseqGenomic[] = $sNG;
        }
        // Get NC from LOVD
        $aRefseqGenomic[] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$zData['chromosome']];

        if (!isset($_SESSION['work'][$sPath])) {
            $_SESSION['work'][$sPath] = array();
        } 

        while (count($_SESSION['work'][$sPath]) >= 5) {
            unset($_SESSION['work'][$sPath][min(array_keys($_SESSION['work'][$sPath]))]);
        }

        // Generate an unique workID that is sortable.
        $_POST['workID'] = (string) microtime(true);
        $_SESSION['work'][$sPath][$_POST['workID']]['values']['genomic_references'] = $aRefseqGenomic;
    }

    $zData['genomic_references'] = $_SESSION['work'][$sPath][$_POST['workID']]['values']['genomic_references'];
    if (count($_POST) > 1) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array(
                            'name', 'chrom_band', 'imprinting', 'refseq_genomic', 'reference', 'url_homepage', 'url_external', 'allow_download',
                            'allow_index_wiki', 'show_hgmd', 'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'refseq',
                            'refseq_url', 'disclaimer', 'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align', 'created_date',
                            'edited_by', 'edited_date', 
                            );

            if (empty($zData['refseq_UD'])) {
                $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
                $sRefseqUD = $_MutalyzerWS->moduleCall('sliceChromosomeByGene', array('geneSymbol' => $sID, 'organism' => 'Man', 'upStream' => '5000', 'downStream' => '2000'));
                if (is_string($sRefseqUD) && substr($sRefseqUD, 0, 3) == 'UD_') {
                    $aFields[] = 'refseq_UD';
                    $_POST['refseq_UD'] = $sRefseqUD;
                }
            }

            // Prepare values.
            if (empty($_POST['created_date'])) {
                $_POST['created_date'] = date('Y-m-d H:i:s');
            }
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');
            $_POST['name'] = $zData['name'];
            
            $_DATA->updateEntry($sID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited gene information entry ' . $sID . ' (' . $_POST['name'] . ')');

            // Change linked diseases?
            // Diseases the gene is currently linked to.

            // Remove diseases.
            $aToRemove = array();
            foreach ($zData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }

            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_GEN2DIS . ' WHERE geneid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene ' . $sID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from gene ' . $sID);
                }
            }

            // Add diseases.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $zData['active_diseases'])) {
                    // Add disease to gene.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($sID, $nDisease), false);
                    if (!$q) {
                        $aFailed[] = $nDisease;
                    } else {
                        $aSuccess[] = $nDisease;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to gene ' . $sID);
            } elseif ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene ' . $sID);
            }

            unset($_SESSION['work'][$sPath][$_POST['workID']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the gene information entry!', 'success');

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
        // Load connected diseases.
        $_POST['created_date'] = substr($_POST['created_date'], 0, 10);
    }

    $_T->printHeader();
    $_T->printTitle();

    if (!lovd_error()) {
        print('      To edit this gene database, please complete the form below and press "Edit" at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit gene information entry'),
                      ));
    lovd_viewForm($aForm);

    print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;

}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && ACTION == 'delete') {
    // URL: /genes/DMD?delete
    // Drop specific entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Delete gene information entry ' . $sID);
    define('LOG_EVENT', 'GeneDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->loadEntry($sID);
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
            // This also deletes the entries in gen2dis and transcripts.
            $_DATA->deleteEntry($sID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene information entry ' . $sID . ' - ' . $zData['id'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the gene information entry!', 'success');

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
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting gene information entry', '', 'print', $zData['id'] . ' (' . $zData['name'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete gene information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && !ACTION) {
    // URL: /genes/DMD/columns
    // View enabled columns for this gene.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'View enabled custom data columns for gene ' . $sID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sID);
    $n = $_DATA->viewList('Columns');

    if ($n) {
        lovd_showJGNavigation(array('javascript:lovd_openWindow(\'' . CURRENT_PATH . '?order&amp;in_window\', \'ColumnSort' . $sID . '\', 800, 350);' => array('', 'Change order of columns', 1)), 'Columns');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && ACTION == 'order') {
    // URL: /genes/DMD/columns?order
    // Change order of enabled columns for this gene.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Change order of enabled custom data columns for gene ' . $sID);
    define('LOG_EVENT', 'ColumnOrder');
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    $sUnit = 'gene';
    $sCategory = 'VariantOnTranscript';

    if (POST) {
        $_DB->beginTransaction();
        foreach ($_POST['columns'] as $nOrder => $sColID) {
            $nOrder ++; // Since 0 is the first key in the array.
            $_DB->query('UPDATE ' . TABLE_SHARED_COLS . ' SET col_order = ? WHERE ' . $sUnit . 'id = ? AND colid = ?', array($nOrder, $sID, $sCategory . '/' . $sColID));
        }
        $_DB->commit();

        // Write to log...
        lovd_writeLog('Event', LOG_EVENT, 'Updated the column order for ' . $sUnit . ' ' . $sID);

        // Thank the user...
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Successfully updated the column order for ' . $sUnit . ' ' . $sID . '!', 'success');

        if (isset($_GET['in_window'])) {
            // We're in a new window, refresh opener en close window.
            print('      <SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
        } else {
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . $_PE[0] . '/' . $sID . '\\\';\', 1000);</SCRIPT>' . "\n\n");
        }

        $_T->printFooter();
        exit;
    }

    $_T->printHeader();
    $_T->printTitle();

    // Retrieve column IDs in current order.
    $aColumns = $_DB->query('SELECT SUBSTRING(colid, LOCATE("/", colid)+1) FROM ' . TABLE_SHARED_COLS . ' WHERE ' . $sUnit . 'id = ? ORDER BY col_order ASC', array($sID))->fetchAllColumn();

    if (!count($aColumns)) {
        lovd_showInfoTable('No columns found!', 'stop');
        $_T->printFooter();
        exit;
    }

    lovd_showInfoTable('Below is a sorting list of all active columns. By clicking &amp; dragging the arrow next to the column up and down you can rearrange the columns. Re-ordering them will affect listings, detailed views and data entry forms in the same way.', 'information');

    // Form & table.
    print('      <TABLE cellpadding="0" cellspacing="0" class="sortable_head" style="width : 302px;"><TR><TH width="20">&nbsp;</TH><TH>Column ID</TH></TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n" .
          '        <UL id="column_list" class="sortable" style="width : 300px; margin-top : 0px;">' . "\n");

    // Now loop the items in the order given.
    foreach ($aColumns as $sID) {
        print('        <LI><INPUT type="hidden" name="columns[]" value="' . $sID . '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' . $sID . '</TD></TR></TABLE></LI>' . "\n");
    }

    print('        </UL>' . "\n" .
          '        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="' . (isset($_GET['in_window'])? 'self.close(); return false;' : 'document.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1] . '\'; return false;') . '" style="border : 1px solid #FF4422;">' . "\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('lib/jQuery/jquery-ui.sortable.min.js');

?>
      <SCRIPT type='text/javascript'>
        $(function() {
          $('#column_list').sortable({
            containment: 'parent',
            tolerance: 'pointer',
            handle: 'TD.handle',
          });
          $('#column_list').disableSelection();
        });
      </SCRIPT>
<?php

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[1])) && in_array(ACTION, array('authorize', 'sortCurators'))) {
    // URL: /genes/DMD?authorize or /genes/DMD?sortCurators
    // Authorize users to be curators or collaborators for this gene, and/or define the order in which they're shown.

    $sID = rawurldecode($_PE[1]);

    // Load appropiate user level for this gene.
    lovd_isAuthorized('gene', $sID);

    if (ACTION == 'authorize' && $_AUTH['level'] < LEVEL_MANAGER) {
        header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . '?sortCurators');
        exit;
    }

    if (ACTION == 'authorize') {
        define('PAGE_TITLE', 'Authorize curators for the ' . $sID . ' gene');
        define('LOG_EVENT', 'CuratorAuthorize');

        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);
    } else {
        define('PAGE_TITLE', 'Sort curators for the ' . $sID . ' gene');
        define('LOG_EVENT', 'CuratorSort');

        // Require manager clearance.
        lovd_requireAUTH(LEVEL_CURATOR);
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_POST['curators'] stores the IDs of the users that are supposed to go in TABLE_CURATES.
        if (empty($_POST['curators']) || !is_array($_POST['curators'])) {
            $_POST['curators'] = array();
        }
        // $_POST['allow_edit'] stores the IDs of the users that are allowed to edit variants in this gene (the curators).
        if (empty($_POST['allow_edit']) || !is_array($_POST['allow_edit'])) {
            $_POST['allow_edit'] = array();
        }
        // $_POST['shown'] stores whether or not the curator is shown on the screen.
        if (empty($_POST['shown']) || !is_array($_POST['shown'])) {
            $_POST['shown'] = array();
        }

        if (ACTION == 'authorize') {
            // MUST select at least one curator!
            if (empty($_POST['curators']) || empty($_POST['allow_edit']) || empty($_POST['shown'])) {
                lovd_errorAdd('', 'Please select at least one curator that is allowed to edit <I>and</I> is shown on the gene home page!');
            } else {
                // Of the selected persons, at least one should be shown AND able to edit!
                $bCurator = false;
                foreach($_POST['curators'] as $nUserID) {
                    if (in_array($nUserID, $_POST['allow_edit']) && in_array($nUserID, $_POST['shown'])) {
                        $bCurator = true;
                        break;
                    }
                }
                if (!$bCurator) {
                    lovd_errorAdd('', 'Please select at least one curator that is allowed to edit <I>and</I> is shown on the gene home page!');
                }
            }

            // Mandatory fields.
            if (empty($_POST['password'])) {
                lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
            } elseif ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
                // User had to enter his/her password for authorization.
                lovd_errorAdd('password', 'Please enter your correct password for authorization.');
            }

        } else {
            // MUST select at least one visible curator!
            if (empty($_POST['curators']) || empty($_POST['shown'])) {
                lovd_errorAdd('', 'Please select at least one curator to be shown on the gene home page!');
            }
        }



        if (!lovd_error()) {
            // What's by far the most efficient code-wise is just insert/update all we've got and delete everything else.
            $_DB->beginTransaction();

            foreach ($_POST['curators'] as $nOrder => $nUserID) {
                $nOrder ++; // Since 0 is the first key in the array.
                // FIXME; Managers are authorized to add other managers or higher as curators, but should not be able to restrict other manager's editing rights, or hide these users as curators.
                //   Implementing this check on this level means we need to query the database to get all user levels again, defeating this optimalisation below.
                //   Taking away the editing rights/visibility of managers or the admin by a manager is restricted in the interface, so it's not critical to solve now.
                //   I'm being lazy, I'm not implementing the check here now. However, it *is* a bug and should be fixed later.
                if (ACTION == 'authorize') {
                    // FIXME; Is using REPLACE not a lot easier?
                    $_DB->query('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE allow_edit = VALUES(allow_edit), show_order = VALUES(show_order)', array($nUserID, $sID, (int) in_array($nUserID, $_POST['allow_edit']), (in_array($nUserID, $_POST['shown'])? $nOrder : 0)));
                    // FIXME; Without detailed user info we can't include elaborate logging. Would we want that anyway?
                    //   We could rapport things here more specifically because mysql_affected_rows() tells us if there has been an update (2) or an insert (1) or nothing changed (0).
                } else {
                    // Just sort and update visibility!
                    $_DB->query('UPDATE ' . TABLE_CURATES . ' SET show_order = ? WHERE userid = ?', array((in_array($nUserID, $_POST['shown'])? $nOrder : 0), $nUserID));
                }
            }

            if (ACTION == 'authorize') {
                // Now everybody should be updated. Remove whoever should no longer be in there.
                $_DB->query('DELETE FROM c USING ' . TABLE_CURATES . ' AS c, ' . TABLE_USERS . ' AS u WHERE c.userid = u.id AND c.geneid = ? AND c.userid NOT IN (?' . str_repeat(', ?', count($_POST['curators']) - 1) . ') AND (u.level < ? OR u.id = ?)', array_merge(array($sID), $_POST['curators'], array($_AUTH['level'], $_AUTH['id'])));
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            if (ACTION == 'authorize') {
                $sMessage = 'Updated curator list for the ' . $sID . ' gene';
            } else {
                $sMessage = 'Resorted curator list for the ' . $sID . ' gene';
            }
            lovd_writeLog('Event', LOG_EVENT, $sMessage);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully updated the curator list!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    // Now, build $aCurators, which contains info about the curators currently selected (from DB or, if available, POST!).
    $aCurators = array();
    if (!empty($_POST['curators'])) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // Retrieve data for selected curators and collaborators.
        $qCurators = $_DB->query('SELECT u.id, u.name, level FROM ' . TABLE_USERS . ' AS u WHERE u.id IN (?' . str_repeat(', ?', count($_POST['curators'])-1) . ')', $_POST['curators']);
        $zCurators = array();
        while ($z = $qCurators->fetchAssoc()) {
            // FIXME; Do we need to change all IDs to integers because of possibly loosing the prepended zero's? Cross-browser check to verify?
            $zCurators[$z['id']] = $z;
        }
        // Get the order right and add more information.
        foreach ($_POST['curators'] as $nID) {
            $aCurators[$nID] =
                     array(
                            'name' => $zCurators[$nID]['name'],
                            'level' => $zCurators[$nID]['level'],
                            'allow_edit' => (int) in_array($nID, $_POST['allow_edit']),
                            'shown' => (int) in_array($nID, $_POST['shown']));
        }

    } else {
        // First time on form. Use current database contents.

        // Retrieve current curators and collaborators, order by current order.
        // Special ORDER BY statement makes sure show_order value of 0 is sent to the bottom of the list.
        $qCurators = $_DB->query('SELECT u.id, u.name, c.allow_edit, (c.show_order > 0) AS shown, u.level FROM ' . TABLE_CURATES . ' AS c INNER JOIN ' . TABLE_USERS . ' AS u ON (c.userid = u.id) WHERE c.geneid = ? ORDER BY (c.show_order > 0) DESC, c.show_order, u.level DESC, u.name', array($sID));
        while ($z = $qCurators->fetchAssoc()) {
            $aCurators[$z['id']] = $z;
        }
    }



    if (ACTION == 'authorize') {
        lovd_errorPrint();

        // Show viewList() of users that are NO curator or collaborator at this moment.
        require ROOT_PATH . 'class/object_users.php';
        $_DATA = new LOVD_User();
        lovd_showInfoTable('The following users are currently not a curator for this gene. Click on a user to select him as Curator or Collaborator.', 'information');
        if ($aCurators) {
            // Create search string that hides the users currently selected to be curator or collaborator.
            $_GET['search_id'] = '!' . implode(' !', array_keys($aCurators));
        } else {
            // We must have something non-empty here, otherwise the JS fails when selecting users.
            $_GET['search_id'] = '!0';
        }
        $_GET['page_size'] = 10;
        $_DATA->setRowLink('Genes_AuthorizeUser', 'javascript:lovd_authorizeUser(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_name}}\', \'{{zData_level}}\'); return false;');
        $_DATA->viewList('Genes_AuthorizeUser', array('id', 'status_', 'last_login_', 'created_date_'), true); // Create known viewListID for lovd_unauthorizeUser().



        // Show curators, to sort and to select whether or not they can edit.
        print('      <BR><BR>' . "\n\n");

        lovd_showInfoTable('All users below have access to all data (public and non-public) of the ' . $sID . ' gene database. If you don\'t want to give the user access to <I>edit</I> any of the data that is not their own, deselect the "Allow edit" checkbox. Please note that users with level Manager or higher, cannot be restricted in their right to edit all information in the database.<BR>Users without edit rights are called Collaborators. Users having edit rights are called Curators; they receive email notifications of new submission and are shown on the gene\'s home page by default. You can disable that below by deselecting the "Shown" checkbox next to their name. To sort the list of curators for this gene, click and drag the <IMG src="gfx/drag_vertical.png" alt="" width="5" height="13"> icon up or down the list. Release the mouse button in the preferred location.', 'information');
    } else {
        lovd_showInfoTable('To sort the list of curators for this gene, click and drag the <IMG src="gfx/drag_vertical.png" alt="" width="5" height="13"> icon up or down the list. Release the mouse button in the preferred location. If you do not want a user to be shown on the list of curators on the gene homepage and on the top of the screen, deselect the checkbox on the right side of his name.', 'information');
    }

    // Form & table.
    print('      <TABLE class="sortable_head" style="width : 552px;"><TR><TH width="15">&nbsp;</TH><TH>Name</TH>');
    if (ACTION == 'authorize') {
        print('<TH width="100" style="text-align:right;">Allow edit</TH><TH width="75" style="text-align:right;">Shown</TH><TH width="30">&nbsp;</TH>');
    } else {
        print('<TH width="75" style="text-align:right;">Shown</TH>');
    }
    print('</TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
          '        <UL id="curator_list" class="sortable" style="margin-top : 0px; width : 550px;">' . "\n");
    // Now loop the items in the order given.
    foreach ($aCurators as $nID => $aVal) {
        print('          <LI id="li_' . $nID . '"><INPUT type="hidden" name="curators[]" value="' . $nID . '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' . $aVal['name'] . '</TD>');
        if (ACTION == 'authorize') {
            print('<TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' . $nID . '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else if (' . $aVal['level'] . ' >= ' . LEVEL_MANAGER . ') { this.checked = true; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }"' . ($aVal['allow_edit'] || $aVal['level'] >= LEVEL_MANAGER? ' checked' : '') . '></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' . $nID . '"' . ($aVal['allow_edit']? ($aVal['shown']? ' checked' : '') : ' disabled') . '></TD><TD width="30" align="right">' . ($aVal['level'] >= $_AUTH['level'] && $nID != $_AUTH['id']? '&nbsp;' : '<A href="#" onclick="lovd_unauthorizeUser(\'Genes_AuthorizeUser\', \'' . $nID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A>') . '</TD>');
        } else {
            print('<TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' . $nID . '"' . ($aVal['allow_edit']? ($aVal['shown']? ' checked' : '') : ' disabled') . '></TD>');
        }
        print('</TR></TABLE></LI>' . "\n");
    }
    print('        </UL>' . "\n");

    if (ACTION == 'authorize') {
        // Array which will make up the form table.
        $aForm = array(
                        array('POST', '', '', '', '0%', '0', '100%'),
                        array('', '', 'print', 'Enter your password for authorization'),
                        array('', '', 'password', 'password', 20),
                        array('', '', 'print', '<INPUT type="submit" value="Save curator list">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="document.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '\'; return false;" style="border : 1px solid #FF4422;">'),
                      );
        lovd_viewForm($aForm);
    } else {
        print('        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="document.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '\'; return false;" style="border : 1px solid #FF4422;">' . "\n");
    }
    print("\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('lib/jQuery/jquery-ui.sortable.min.js');
    // FIXME; disable JS functions authorize and unauthorize if not authorizing?
?>
      <SCRIPT type='text/javascript'>
        $(function() {
                $( '#curator_list' ).sortable({
                        containment: 'parent',
                        tolerance: 'pointer',
                        handle: 'TD.handle',
                });
                $( '#curator_list' ).disableSelection();
        });


<?php
    if (ACTION == 'authorize') {
?>
        function lovd_authorizeUser (sViewListID, nID, sName, nLevel)
        {
            // Moves the user to the Authorized Users block and removes the row from the viewList.
            objViewListF = document.getElementById('viewlistForm_' + sViewListID);
            objElement = document.getElementById(nID);
            objElement.style.cursor = 'progress';

            objUsers = document.getElementById('curator_list');
            oLI = document.createElement('LI');
            oLI.id = 'li_' + nID;
            oLI.innerHTML = '<INPUT type="hidden" name="curators[]" value="' + nID + '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' + sName + '</TD><TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' + nID + '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else if (' + nLevel + ' >= <?php echo LEVEL_MANAGER; ?>) { this.checked = true; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }" checked></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' + nID + '" checked></TD><TD width="30" align="right"><A href="#" onclick="lovd_unauthorizeUser(\'Genes_AuthorizeUser\', \'' + nID + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE>';
            objUsers.appendChild(oLI);

            // Then, remove this row from the table.
            objElement.style.cursor = '';
            lovd_AJAX_viewListHideRow(sViewListID, nID);
            objViewListF.total.value --;
            lovd_AJAX_viewListUpdateEntriesString(sViewListID);
// FIXME; disable for IE or try to fix?
            // This one doesn't really work in IE 7 and IE 8. Other versions not known.
            lovd_AJAX_viewListAddNextRow(sViewListID);

            // Also change the search terms in the viewList such that submitting it will not reshow this item.
            objViewListF.search_id.value += ' !' + nID;
            // Does an ltrim, too. But trim() doesn't work in IE < 9.
            objViewListF.search_id.value = objViewListF.search_id.value.replace(/^\s*/, '');
            return true;
        }


        function lovd_unauthorizeUser (sViewListID, nID)
        {
            // Removes the user from the Authorized Users block and reloads the viewList with the user back in there.
            objViewListF = document.getElementById('viewlistForm_' + sViewListID);
            objLI = document.getElementById('li_' + nID);

            // First remove from block, simply done (no fancy animation).
            objLI.parentNode.removeChild(objLI);

            // Reset the viewList.
            // Does an ltrim, too. But trim() doesn't work in IE < 9.
            objViewListF.search_id.value = objViewListF.search_id.value.replace('!' + nID, '').replace('  ', ' ').replace(/^\s*/, '');
            lovd_AJAX_viewListSubmit(sViewListID);

            return true;
        }
<?php
    }

    print('      </SCRIPT>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
