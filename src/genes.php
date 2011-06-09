<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2011-06-09
 * For LOVD    : 3.0-alpha-01
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// Require manager clearance.
//lovd_requireAUTH(LEVEL_MANAGER);




if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /genes
    // View all entries.

    define('PAGE_TITLE', 'View genes');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $_DATA->viewList(false, 'geneid');

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





// FIXME; \w does not match all allowed characters in a gene symbol. This should change.
// NOTE that if this is loosened, you may need to clean $_PATH_ELEMENTS[1] before printing it on the screen!!!
// FIXME; tmp fix.
if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[\w-]+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /genes/DMD
    // View specific entry.

    $sID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'View gene ' . $sID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->viewEntry($sID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="genes/' . $sID . '?edit">Edit gene information</A>';
        $sNavigation .= ' | <A href="transcripts/' . $sID . '?create">Add transcript(s) to gene</A>';
        $sNavigation .= ' | <A href="genes/' . $sID . '?delete">Delete gene entry</A>' .
            ' | <A href="genes/' . $sID . '?authorize">Add/remove curators/collaborators</A>';
        // FIXME add: | Sort/hide curator names
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }
    
    $_GET['search_geneid'] = $sID;
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Transcripts', 'H4');
    print('<BR>' . "\n");
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->setSortDefault('id');
    $_DATA->viewList(false, 'geneid', true, true);

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // URL: /genes?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new gene information entry');
    define('LOG_EVENT', 'GeneCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    $_DATA = new LOVD_Gene();
    
    if (GET) {
        $_POST['workID'] = lovd_generateRandomID();
        // Temporary fix for mem leak.
        $_SESSION['work'] = array();
        $_SESSION['work'][$_POST['workID']] = array(
                                                    'action' => '/genes?create',
                                                    'step' => '1',
                                                   );
    }
    
    if ($_SESSION['work'][$_POST['workID']]['step'] == '1') {

        if (POST) {

            lovd_errorClean();

            if ($_POST['hgnc_id'] == '') {
                lovd_errorAdd('hgnc_id', 'No HGNC ID or Gene symbol was specified');
            } else {
                // Gene Symbol must be unique
                // Enforced in the table, but we want to handle this gracefully.
                $sSQL = 'SELECT id FROM ' . TABLE_GENES . ' WHERE id = ? OR id_hgnc = ?';
                $aSQL = array($_POST['hgnc_id'], $_POST['hgnc_id']);
                
                if (mysql_num_rows(lovd_queryDB($sSQL, $aSQL))) {
                    lovd_errorAdd('hgnc_id', 'This gene entry is already present in this LOVD installation. Please choose another one.');
                } else {
                    if (ctype_digit($_POST['hgnc_id'])) {
                        $sWhere = 'gd_hgnc_id%3D' . $_POST['hgnc_id'];
                    } else {
                        $sWhere = 'gd_app_sym%3D%22' . $_POST['hgnc_id'] . '%22';
                    }
                    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?col=gd_hgnc_id&col=gd_app_sym&col=gd_app_name&col=gd_pub_chrom_map&col=gd_pub_eg_id&col=md_mim_id&status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit', false, false);
                    
                    if (isset($aHgncFile['1'])) {
                        list($sHgncID, $sSymbol, $sGeneName, $sChromLocation, $sEntrez, $sOmim) = explode("\t", $aHgncFile['1']);
                        // FIXME; why array_values()???
                        list($sEntrez, $sOmim) = array_values(array_map('trim', array($sEntrez, $sOmim)));
                        if ($sGeneName == 'entry withdrawn') {
                            lovd_errorAdd('hgnc_id', 'Entry ' . $_POST['hgnc_id'] . ' no longer exists in the HGNC database.');
                        } elseif (preg_match('/^symbol withdrawn, see (.+)$/', $sGeneName, $aRegs)) {
                            lovd_errorAdd('hgnc_id', 'Entry ' . $_POST['hgnc_id'] . ' is deprecated, please use ' . $aRegs[1]);
                        }
                    } else {
                        lovd_errorAdd('hgnc_id', 'Entry was not found in the HGNC database.');
                    }
                }
            }
            
            if (!lovd_error()) {
                require ROOT_PATH . 'inc-top.php';
                require ROOT_PATH . 'class/progress_bar.php';
                require ROOT_PATH . 'inc-lib-genes.php';
                
                $sFormNextPage = '<FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" id="createGene" method="post">' . "\n" .
                                 '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                                 '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                                 '        </FORM>';
                
                $_BAR = new ProgressBar('', 'Collecting gene information...', $sFormNextPage);

                define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
                require ROOT_PATH . 'inc-bot.php';

                // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
                flush();

                $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
                
                // Get LRG if it exists
                $aRefseqGenomic = array();
                $_BAR->setMessage('Checking for LRG...');
                if ($sLRG = getLrgByGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sLRG;
                }

                // Get NG if it exists
                $_BAR->setMessage('Checking for NG...');
                $_BAR->setProgress(16);
                if ($sNG = getNgByGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sNG;
                }

                // Get NC from LOVD
                $_BAR->setMessage('Checking for NC...');
                $_BAR->setProgress(33);
                preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches);
                $sChromosome = $aMatches[1];
                $sChromBand = $aMatches[2];
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
                            $aTranscriptsPositions[$aTranscriptValues['id']] = array('gTransStart' => $aTranscriptValues['gTransStart'], 'gTransEnd' => $aTranscriptValues['gTransEnd'], 'cTransStart' => $aTranscriptValues['cTransStart'], 'cTransEnd' => $aTranscriptValues['sortableTransEnd'], 'cCDSStop' => $aTranscriptValues['cCDSStop']);
                            $aTranscriptsProtein[$aTranscriptValues['id']] = lovd_getElementFromArray('proteinTranscript/id', $aTranscriptInfo, 'v');
                            $_BAR->setProgress(66 + $nProgress);
                        }
                    }
                }
                $_BAR->setProgress(100);
                $_BAR->setMessage('Information collected, now building form...');
                $_BAR->setMessageVisibility('done', true);
                $_SESSION['work'][$_POST['workID']]['step'] = '2';
                $_SESSION['work'][$_POST['workID']]['values'] = array(
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
                      '</SCRIPT>' . "\n\n");

                lovd_checkXSS();

                print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
                exit;
            }
        }

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        if (GET) {
            print('      Please fill in the HGNC ID or Gene Symbol for the gene database you wish to create.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n" .
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

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }




    if ($_SESSION['work'][$_POST['workID']]['step'] == '2') {
        $zData = $_SESSION['work'][$_POST['workID']]['values'];
        if (count($_POST) > 1) {
            lovd_errorClean();

            $_DATA->checkFields($_POST);

            if (!lovd_error()) {
                // Fields to be used.
                $aFields = array(
                                'id', 'name', 'chromosome', 'chrom_band', 'refseq_genomic', 'refseq_UD', 'reference', 'url_homepage',
                                'url_external', 'allow_download', 'allow_index_wiki', 'id_hgnc', 'id_entrez', 'id_omim', 'show_hgmd',
                                'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'refseq', 'refseq_url', 'disclaimer', 
                                'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align', 'created_by', 'created_date', 
                                );

                // Prepare values.
                $_POST['created_by'] = $_AUTH['id'];
                if (empty($_POST['created_date'])) {
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

                // FIXME; put this block and the next in a function.
                $qAddedCustomCols = lovd_queryDB('DESCRIBE ' . TABLE_VARIANTS_ON_TRANSCRIPTS);
                while ($aCol = mysql_fetch_assoc($qAddedCustomCols)) {
                    $aAdded[] = $aCol['Field'];
                }
                
                $qStandardCustomCols = lovd_queryDB('SELECT * FROM ' . TABLE_COLS . ' WHERE id LIKE "VariantOnTranscript/%" AND (standard = 1 OR hgvs = 1)');
                while ($aStandard = mysql_fetch_assoc($qStandardCustomCols)) {
                    if (!in_array($aStandard['id'], $aAdded)) {
                        $q = lovd_queryDB('ALTER TABLE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' ADD COLUMN `' . $aStandard['id'] . '` ' . stripslashes($aStandard['mysql_type']), array());
                        $q = lovd_queryDB('INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES(?, ?, NOW())', array($aStandard['id'], $_AUTH['id']));
                    }
                    $q = lovd_queryDB('INSERT INTO ' . TABLE_SHARED_COLS . ' VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL, NULL)', array($_POST['id'], $aStandard['id'], $aStandard['col_order'], $aStandard['width'], $aStandard['mandatory'], $aStandard['description_form'], $aStandard['description_legend_short'], $aStandard['description_legend_full'], $aStandard['select_options'], $aStandard['public_view'], $aStandard['public_add'], $_AUTH['id']));
                }

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $_POST['id'] . ' (' . $_POST['name'] . ')');

                // Make current user curator of this gene.
                lovd_queryDB('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?)', array($_AUTH['id'], $_POST['id'], 1, 1), true);

                // Add diseases.
                $aSuccessDiseases = array();
                if (isset($_POST['active_diseases'])) {
                    // FIXME; een if in een if kun je samen trekken.
                    // FIXME; probeer van deze "None" af te komen.
                    // FIXME; zou er nog gecontroleerd moeten worden of 't een array is?
                    if (!in_array('None', $_POST['active_diseases'])) {
                        // FIXME; dit is $nDisease.
                        foreach ($_POST['active_diseases'] as $sDisease) {
                            // Add disease to gene.
                            $q = lovd_queryDB('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($_POST['id'], $sDisease));
                            if (!$q) {
                                // Silent error.
                                lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $sDisease . ' - could not be added to gene ' . $_POST['id']);
                            } else {
                                $aSuccessDiseases[] = $sDisease;
                            }
                        }
                    }
                }
                
                // Add transcripts.
                // FIXME; deze code is voor mij niet goed interpreteerbaar - ik zie niet goed in detail wat
                //   er gebeurt dus kan 't niet nakijken; misschien dat meer commentaar zou helpen.
                $aSuccessTranscripts = array();
                if (isset($_POST['active_transcripts'])) {
                    // FIXME; probeer van deze "None" af te komen.
                    if (!in_array('None', $_POST['active_transcripts'])) {
                        foreach($_POST['active_transcripts'] as $sTranscript) {
                            // Add transcript to gene
                            $sTranscriptProtein = $zData['transcriptsProtein'][$sTranscript];
                            $sTranscriptName = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)];
                            $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                            $q = lovd_queryDB('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)', array($_POST['id'], $sTranscriptName, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['gTransStart'], $aTranscriptPositions['gTransEnd'], $_POST['created_by']));
                            if (!$q) {
                                // Silent error.
                                lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - ' . ' - could not be added to gene ' . $_POST['id']);
                            } else {
                                $aSuccessTranscripts[] = $sTranscript;
                            }
                        }
                        if (count($aSuccessDiseases) && count($aSuccessTranscripts)) {
                            lovd_writeLog('Event', LOG_EVENT, 'Disease and transcript information entries successfully added to gene ' . $_POST['id'] . ' - ' . $_POST['name']);
                        }
                    }
                } else {
                    if (count($aSuccessDiseases)) {
                        lovd_writeLog('Event', LOG_EVENT, 'Disease entries successfully added to gene ' . $_POST['id'] . ' - ' . $_POST['name']);
                    }
                }

                // Add current user as the curator. This should also be in the transaction.
                @lovd_queryDB('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?)', array($_AUTH['id'], $_POST['id'], 1, 1));

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $_POST['id'] . '?authorize');

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('Successfully created the gene information entry!', 'success');

                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
        } else {
            // Default values.
            $_DATA->setDefaultValues();
        }

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        if (!lovd_error()) {
            print('      To create a new gene database, please complete the form below and press "Create" at the bottom of the form..<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Tooltip JS code.
        lovd_includeJS('inc-js-tooltip.php');
        // FIXME; ik suggereer 'm inc-js-custom_links.php te noemen.
        lovd_includeJS('inc-js-insert-custom-links.php');

        // Table.
        print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array_merge(
                     $_DATA->getForm(),
                     array(
                            array('', '', 'submit', 'Create gene information entry'),
                          ));
        lovd_viewForm($aForm);

        print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
        print('</FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[\w-]+$/', $_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /genes/DMD?edit
    // Edit an entry.

    $sID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Edit gene information entry');
    define('LOG_EVENT', 'GeneEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->loadEntry($sID);

    if (GET) {
        require ROOT_PATH . 'inc-lib-genes.php';

        $aRefseqGenomic = array();
        // Get LRG if it exists
        if ($sLRG = getLrgByGeneSymbol($sID)) {
            $aRefseqGenomic[] = $sLRG;
        }
        // Get NG if it exists
        if ($sNG = getNgByGeneSymbol($sID)) {
            $aRefseqGenomic[] = $sNG;
        }
        // Get NC from LOVD
        $aRefseqGenomic[] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$zData['chromosome']];

        $_POST['workID'] = lovd_generateRandomID();
        $_SESSION['work'][$_POST['workID']] =
                 array(
                        'action' => '/genes/' . $sID . '?edit',
                        'values' =>
                                 array(
                                        'genomic_references' => $aRefseqGenomic,
                                      ),
                      );
    }
    $zData['genomic_references'] = $_SESSION['work'][$_POST['workID']]['values']['genomic_references'];

    if (count($_POST) > 1) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array(
                            'name', 'chrom_band', 'refseq_genomic', 'reference', 'url_homepage', 'url_external', 'allow_download',
                            'allow_index_wiki', 'show_hgmd', 'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'refseq',
                            'refseq_url', 'disclaimer', 'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align', 'created_date',
                            'edited_by', 'edited_date', 
                            );

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
            // FIXME; we moeten afspraken op papier zetten over de naamgeving van velden, ik zou hier namelijk geen _ achter plaatsen.
            //   Een idee zou namelijk zijn om loadEntry automatisch velden te laten exploden afhankelijk van hun naam. Is dat wat?
            $aDiseases = explode(';', $zData['active_diseases_']);

            // Remove diseases.
            $aToRemove = array();
            foreach ($aDiseases as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }
            if ($aToRemove) {
                $q = lovd_queryDB('DELETE FROM ' . TABLE_GEN2DIS . ' WHERE geneid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove));
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
                // FIXME; probeer van deze "None" af te komen.
                if (!in_array($nDisease, $aDiseases) && $nDisease != 'None') {
                    // Add disease to gene.
                    $q = lovd_queryDB('INSERT IGNORE INTO ' . TABLE_GEN2DIS . ' VALUES (?, ?)', array($sID, $nDisease));
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
            }
            if ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene ' . $sID);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $sID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the gene information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;
        }

    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
        // Load connected diseases.
        $_POST['active_diseases'] = explode(';', $_POST['active_diseases_']);
        $_POST['created_date'] = substr($_POST['created_date'], 0, 10);
    }

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (!lovd_error()) {
        print('      To edit this gene database, please complete the form below and press "Edit" at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    // FIXME; ik suggereer 'm inc-js-custom_links.php te noemen.
    lovd_includeJS('inc-js-insert-custom-links.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $sID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit gene information entry'),
                      ));
    lovd_viewForm($aForm);

    print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;

}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[\w-]+$/', $_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /genes/DMD?delete
    // Drop specific entry.

    $sID = $_PATH_ELEMENTS[1];
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
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // This also deletes the entries in gen2dis and transcripts.
            $_DATA->deleteEntry($sID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene information entry ' . $sID . ' - ' . $zData['id'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'genes');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the gene information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $sID . '?' . ACTION . '" method="post">' . "\n");

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

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^[\w-]+$/', $_PATH_ELEMENTS[1]) && (ACTION == 'authorize' || ACTION == 'sortCurators')) {
    // URL: /genes/DMD?authorize or /genes/DMD?sortCurators
    // Authorize users to be curators or collaborators for this gene, and/or define the order in which they're shown.

    $sID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Authorize curators for the ' . $sID . ' gene');
    define('LOG_EVENT', 'GeneAuthorize');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

//    require ROOT_PATH . 'class/object_genes.php';
//    $_DATA = new LOVD_Gene();
//    $zData = $_DATA->loadEntry($sID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // MUST select at least one curator!
        if (empty($_POST['curators']) || !is_array($_POST['curators']) || empty($_POST['allow_edit']) || !is_array($_POST['allow_edit']) || empty($_POST['shown']) || !is_array($_POST['shown'])) {
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

        // FIXME; should require password!!!
//        // Mandatory fields.
//        if (empty($_POST['password'])) {
//            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
//        }

//        // User had to enter his/her password for authorization.
//        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
//            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
//        }

        if (!lovd_error()) {
            /*
            // FIXME; Now we've got three places in this piece of code in which we query the DB for user info... Must be made more efficient.
            $zCurator = array();
            $qCurators = lovd_queryDB('SELECT u.id, u.name, c.allow_edit, (c.show_order > 0) AS shown FROM ' . TABLE_CURATES . ' AS c INNER JOIN ' . TABLE_USERS . ' AS u ON (c.userid = u.id) WHERE c.geneid = ? ORDER BY (c.show_order > 0) DESC, c.show_order, u.level DESC, u.name', array($sID));
            while ($z = mysql_fetch_assoc($qCurators)) {
                $zCurators[$z['id']] = $z;
            }
            */

            // What's by far the most efficient code-wise is just insert/update all we've got and delete everything else.
            lovd_queryDB('START TRANSACTION', array(), true);

            foreach ($_POST['curators'] as $nOrder => $nUserID) {
                $nOrder ++; // Since 0 is the first key in the array.
                // Kind of stupid that just putting ON DUPLICATE KEY UPDATE doesn't do the trick.
                lovd_queryDB('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE allow_edit = ?, show_order = ?', array($nUserID, $sID, (int) in_array($nUserID, $_POST['allow_edit']), (in_array($nUserID, $_POST['shown'])? $nOrder : 0), (int) in_array($nUserID, $_POST['allow_edit']), (in_array($nUserID, $_POST['shown'])? $nOrder : 0)), true);
                // FIXME; Without detailed user info we can't include elaborate logging. Would we want that anyway?
                //   We could rapport things here more specifically because mysql_affected_rows() tells us if there has been an update (2) or an insert (1) or nothing changed (0).
            }

            // Now everybody should be updated. Remove whoever should no longer be in there.
            lovd_queryDB('DELETE FROM ' . TABLE_CURATES . ' WHERE geneid = ? AND userid NOT IN (?' . str_repeat(', ?', count($_POST['curators']) - 1) . ')', array_merge(array($sID), $_POST['curators']), true);

            // If we get here, it all succeeded.
            lovd_queryDB('COMMIT', array(), true);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated curator list for the ' . $sID . ' gene');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'genes/' . $sID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully updated the curator list!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Now, build $aCurators, which will represent the state that the user *wants* the curators to be.
    $zCurators = array();
    $aCurators = array();
    if (POST) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // $_POST['curators'] stores the IDs of the users that are supposed to go in TABLE_CURATES.
        // FIXME; this can also be done above in the code for checking the fields.
        if (empty($_POST['curators']) || !is_array($_POST['curators'])) {
            $_POST['curators'] = array();

        } else {
            // Retrieve data for selected curators and collaborators.
            $qCurators = lovd_queryDB('SELECT u.id, u.name FROM ' . TABLE_USERS . ' AS u WHERE u.id IN (?' . str_repeat(', ?', count($_POST['curators'])-1) . ')', $_POST['curators']);
            while ($z = mysql_fetch_assoc($qCurators)) {
                $zCurators[$z['id']] = $z;
            }
        }

        // $_POST['allow_edit'] stores the IDs of the users that are allowed to edit variants in this gene (the curators).
        // FIXME; this can also be done above in the code for checking the fields.
        if (empty($_POST['allow_edit']) || !is_array($_POST['allow_edit'])) {
            $_POST['allow_edit'] = array();
        }
        // $_POST['show_order'] stores whether or not the curator is shown on the screen.
        // FIXME; this can also be done above in the code for checking the fields.
        if (empty($_POST['shown']) || !is_array($_POST['shown'])) {
            $_POST['shown'] = array();
        }
        // Build $aCurators.
        // FIXME; Do we need to change all IDs to integers because of possibly loosing the prepended zero's? Cross-browser check to verify?
        foreach ($_POST['curators'] as $nID) {
            $aCurators[$nID] = array((int) in_array($nID, $_POST['allow_edit']), (int) in_array($nID, $_POST['shown']));
        }

    } else {
        // First time on form. Use current database contents.

        // Retrieve current curators and collaborators.
        // Special ORDER BY statement makes sure show_order value of 0 is sent to the bottom of the list.
        $qCurators = lovd_queryDB('SELECT u.id, u.name, c.allow_edit, (c.show_order > 0) AS shown FROM ' . TABLE_CURATES . ' AS c INNER JOIN ' . TABLE_USERS . ' AS u ON (c.userid = u.id) WHERE c.geneid = ? ORDER BY (c.show_order > 0) DESC, c.show_order, u.level DESC, u.name', array($sID));
        while ($z = mysql_fetch_assoc($qCurators)) {
            $zCurators[$z['id']] = $z;
        }

        // Build $aCurators from $zCurators.
        foreach ($zCurators as $nID => $z) {
            $aCurators[$nID] = array((int) $z['allow_edit'], (int) $z['shown']);
        }
    }



    // Show viewList() of users that are NO curator or collaborator at this moment.
    require ROOT_PATH . 'class/object_users.php';
    $_DATA = new LOVD_User();
    lovd_showInfoTable('The following users are currently not a curator for this gene. Click on a user to select him as curator or collaborator.', 'information');
    if ($aCurators) {
        // Create search string that hides the users currently selected to be curator or collaborator.
        $_GET['search_id'] = '!' . implode(' !', array_keys($aCurators));
    } else {
        // We must have something non-empty here, otherwise the JS fails when selecting users.
        $_GET['search_id'] = '!0';
    }
    $_DATA->sRowLink = 'javascript:lovd_authorizeUser(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_name}}\');';
    // FIXME; if all users have been selected, you get the message "No entries found for this gene!" which is a bit weird, but also I can't reload the viewList because I don't have a DIV.
    $_DATA->viewList('LOVDGeneAuthorizeUser', array('status_', 'last_login', 'created_date'), true); // Create known viewListID for lovd_unauthorizeUser().



    // Show curators, to sort and to select whether or not they can edit.
    print('      <BR><BR>' . "\n\n");

    lovd_showInfoTable('All users below have access to all data (public and non-public) of the ' . $sID . ' gene database. If you don\'t want to give the user access to <I>edit</I> any of the data that is not their own, deselect the "Allow edit" checkbox.<BR>Users having edit rights are called Curators and are shown on the gene\'s home page by default, but you can disable this below. To sort the list of curators for this gene, click and drag the <IMG src="gfx/drag_vertical.png" alt="" width="5" height="13"> icon up or down the list. Release the mouse button in the preferred location. If you do not want a curator to be shown on the list of curators on the gene homepage and on the top of the screen, deselect the checkbox under "Shown".', 'information');

    lovd_errorPrint();

    // Form & table.
    // FIXME; needs password protection if not just resorting!!!
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $_PATH_ELEMENTS[1] . '?' . ACTION . '" method="post">' . "\n" .
          '        <UL id="curator_list" class="sortable">' . "\n" .
          '          <TABLE width="100%" class="head"><TR><TH width="10">&nbsp;</TH><TH>Name</TH><TH width="100" align="right">Allow edit</TH><TH width="75" align="right">Shown</TH><TH width="30" align="right">&nbsp;</TH></TR></TABLE>' . "\n");
    // Now loop the items in the order given.
    foreach ($aCurators as $nID => $aVal) {
        list($bAllowEdit, $bShown) = $aVal;
        print('          <LI id="li_' . $nID . '"><INPUT type="hidden" name="curators[]" value="' . $nID . '"><TABLE width="100%"><TR><TD width="10"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13" class="handle"></TD><TD>' . $zCurators[$nID]['name'] . '</TD><TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' . $nID . '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }"' . ($bAllowEdit? ' checked' : '') . '></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' . $nID . '"' . ($bAllowEdit? ($bShown? ' checked' : '') : ' disabled') . '></TD><TD width="30" align="right"><A href="#" onclick="lovd_unauthorizeUser(\'LOVDGeneAuthorizeUser\', \'' . $nID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE></LI>' . "\n");
    }
    print('        </UL>' . "\n" .
          '        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="document.location.href=\'' . lovd_getInstallURL() . 'genes/' . $sID . '\'; return false;" style="border : 1px solid #FF4422;">' . "\n" .
          '      </FORM>' . "\n\n");

      // FIXME; Can't we merge these files into one? We only use them together anyway...
?>
      <!-- Tim Taylor's ToolMan DHTML Library, see http://tool-man.org/examples/ -->
      <SCRIPT type="text/javascript" src="lib/tool-man/core.js"></SCRIPT>
      <SCRIPT type="text/javascript" src="lib/tool-man/events.js"></SCRIPT>
      <SCRIPT type="text/javascript" src="lib/tool-man/css.js"></SCRIPT>
      <SCRIPT type="text/javascript" src="lib/tool-man/coordinates.js"></SCRIPT>
      <SCRIPT type="text/javascript" src="lib/tool-man/drag.js"></SCRIPT>
      <SCRIPT type="text/javascript" src="lib/tool-man/dragsort.js"></SCRIPT>
      <SCRIPT type="text/javascript">
        <!--
        var dragsort = ToolMan.dragsort()
        dragsort.makeListSortable(document.getElementById("curator_list"), setHandle)

        function setHandle(item) {
            item.toolManDragGroup.setHandle(findHandle(item))
        }

        function findHandle(item) {
            var children = item.getElementsByTagName("img")
            for (var i = 0; i < children.length; i++) {
                var child = children[i]

                if (child.getAttribute("class") == null) continue

                if (child.getAttribute("class").indexOf("handle") >= 0)
                    return child
            }
            return item
        }


        function lovd_authorizeUser (sViewListID, nID, sName)
        {
            // Moves the user to the Authorized Users block and removes the row from the viewList.
            objViewListT = document.getElementById('viewlistTable_' + sViewListID);
            objElement = document.getElementById(nID);
            objElement.style.cursor = 'progress';

            objUsers = document.getElementById('curator_list');
            oLI = document.createElement('LI');
            oLI.id = 'li_' + nID;
            // FIXME; check if this works in IE; DeleteLog says: // The following line doesn't work in IE 7. Don't know why. It says "Unknown runtime error". Other versions unknown.
            oLI.innerHTML = '<INPUT type="hidden" name="curators[]" value="' + nID + '"><TABLE width="100%"><TR><TD width="10"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13" class="handle"></TD><TD>' + sName + '</TD><TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' + nID + '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }" checked></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' + nID + '" checked></TD><TD width="30" align="right"><A href="#" onclick="lovd_unauthorizeUser(\'LOVDGeneAuthorizeUser\', \'' + nID + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE>';
            objUsers.appendChild(oLI);
            // Make new entry sortable also.
            dragsort.makeListSortable(document.getElementById("curator_list"), setHandle);

            // Then, remove this row from the table.
            objElement.style.cursor = '';
            lovd_AJAX_viewListHideRow(sViewListID, nID);
            document.forms['viewlistForm_' + sViewListID].total.value --;
            lovd_AJAX_viewListUpdateEntriesString(sViewListID);
// FIXME; disable for IE or try to fix?
            // This one doesn't really work in IE 7. Other versions not known.
            lovd_AJAX_viewListAddNextRow(sViewListID);

            // Also change the search terms in the viewList such that submitting it will not reshow this item.
            document.getElementById('viewlistForm_' + sViewListID).search_id.value += ' !' + nID;
            return true;
        }


        function lovd_unauthorizeUser (sViewListID, nID)
        {
            // Removes the user to from the Authorized Users block and reloads the viewList with the user back in there.
            objViewListF = document.getElementById('viewlistForm_' + sViewListID);
            objLI = document.getElementById('li_' + nID);

            // First remove from block, simply done (no fancy animation).
            objLI.parentNode.removeChild(objLI);

            // Reset the viewList.
            objViewListF.search_id.value = objViewListF.search_id.value.replace('!' + nID, '');
            lovd_AJAX_viewListSubmit(sViewListID);

            return true;
        }
        //-->
      </SCRIPT>
<?php


//       De entries in de twee lijsten (curators en collaborators) moeten ook weer teruggeplaatst kunnen worden in de viewList.
/*//////////////////////////////////////////////////////////////////////////////
// LOVD 3.0 code voor deleten van een gen.
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
*///////////////////////////////////////////////////////////////////////////////
/*//////////////////////////////////////////////////////////////////////////////
// LOVD 2.0 code voor toewijzen curatoren
    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        // (...)

        if (!lovd_error()) {
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_curators_manage', 'LOVD Setup - Manage gene curators');

    lovd_showInfoTable('Users selected as curators will receive notification emails after new submissions to this gene. Also, they will be mentioned on the gene homepage. If their user level is also "Curator", they will get write access to the gene\'s data - higher user levels already have write access regardless of whether or not they are configured as gene curators. Changes you make to other users with the same or a higher level than you, will be ignored.<BR>Whether or not a user\'s name appears on the gene homepage and how the names of multiple curators are sorted will be configured in the next screen.', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;edit=' . $_GET['edit'] . '&amp;sent=true" method="post">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('', 'print', '<B>Gene curators</B>'),
                    array('Select which users are this gene\'s curators', 'select', 'curators', $nUsersSize, $aUsers, false, true, true),
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'print', '<INPUT type="submit" value="Edit curator list">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Skip &raquo;" onclick="window.location.href=\'' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=sort&amp;sort=' . $_GET['edit'] . lovd_showSID(true, true) . '\'; return false;">'),
                  );
    lovd_viewForm($aForm);
*///////////////////////////////////////////////////////////////////////////////

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





/*
LOVD 2.0 code from setup_genes.php. Remove only if SURE that all functionality is included in LOVD 3.0 as well.

} elseif ($_GET['action'] == 'view' && isset($_GET['view'])) {
    // View specific gene.

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_genes_manage', 'LOVD Setup - Manage configured genes');

    // GROUP BY only necessary because of the COUNT(*) in the query.
    $zData = @mysql_fetch_assoc(mysql_query('SELECT d.*, COUNT(p2v.variantid) AS variants, u_c.name AS created_by, u_e.name AS edited_by, u_u.name AS updated_by FROM ' . TABLE_DBS . ' AS d LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (symbol) LEFT JOIN ' . TABLE_USERS . ' AS u_c ON (d.created_by = u_c.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS u_e ON (d.edited_by = u_e.id) LEFT OUTER JOIN ' . TABLE_USERS . ' AS u_u ON (d.updated_by = u_u.id) WHERE d.symbol = "' . $_GET['view'] . '" GROUP BY d.symbol'));
    if (!$zData) {
        // Wrong ID, apparently.
        print('      No such ID!<BR>' . "\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Array which will make up the data table.
    $aTable =
             array(
                    'symbol' => 'Gene symbol',
                    'gene' => 'Gene name',
                    'chrom_location' => 'Chromosome location',
                    'genbank' => 'Has a GenBank file',
                    'refseq_genomic' => 'Genomic accession number',
                    'refseq_mrna' => 'Transcript accession number',
                    'Gene/Reference' => 'Reference',
                    'url_homepage' => 'Homepage URL',
                    'allow_download' => 'Allow public to download all variant entries',
                    'allow_index_wiki' => 'Allow data to be indexed by WikiProfessional',
                    'header' => 'Page header (aligned to the ' . $_SETT['notes_align'][$zData['header_align']] . ')',
                    'footer' => 'Page footer (aligned to the ' . $_SETT['notes_align'][$zData['footer_align']] . ')',
                    'note_index' => 'Notes for the LOVD gene homepage',
                    'note_listing' => 'Notes for the variant listings',
                    'disclaimer' => 'Has a disclaimer',
                    'refseq' => 'Has a human-readable reference sequence',
                    'variants' => 'Total number of variants',
                    'curators_' => 'Curators',
                    'created_by' => 'Created by',
                    'created_date' => 'Date created',
                    'edited_by' => 'Last edited by',
                    'edited_date' => 'Date last edited',
                    'updated_by' => 'Last updated by',
                    'updated_date' => 'Date last updated',
                  );

    // Remove unnecessary columns.
    if ($zData['edited_by'] == NULL) {
        // Never been edited.
        unset($aTable['edited_by'], $aTable['edited_date']);
    }

    // Table.
    print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="600" class="data">');

    $zData['genbank']        = '<IMG src="gfx/mark_' . ($zData['genbank']? 1 : 0) . '.png" alt="' . $zData['genbank'] . '" width="11" height="11">';
    $zData['Gene/Reference'] = $zData['reference'];
    if ($zData['url_homepage']) {
        $zData['url_homepage']   = '<A href="' . $zData['url_homepage'] . '" target="_blank">' . $zData['url_homepage'] . '</A>';
    }
    $zData['allow_download']   = '<IMG src="gfx/mark_' . $zData['allow_download'] . '.png" alt="' . $zData['allow_download'] . '" width="11" height="11">';
    $zData['allow_index_wiki'] = '<IMG src="gfx/mark_' . $zData['allow_index_wiki'] . '.png" alt="' . $zData['allow_index_wiki'] . '" width="11" height="11">';
    $zData['disclaimer']       = '<IMG src="gfx/mark_' . ($zData['disclaimer']? 1 : 0) . '.png" alt="' . $zData['disclaimer'] . '" width="11" height="11">';
    $zData['refseq']           = '<IMG src="gfx/mark_' . ($zData['refseq'] != ''? 1 : 0) . '.png" alt="' . ($zData['refseq'] != ''? 1 : 0) . '" width="11" height="11">';
    list($nUnique) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(DISTINCT `Variant/DNA`) FROM ' . TABLEPREFIX . '_' . $zData['symbol'] . '_variants'));
    $zData['variants'] .= ' (' . $nUnique . ' unique entr' . ($nUnique == 1? 'y' : 'ies') . ')';
    $zData['curators_'] = '';
    $qCurators = mysql_query('SELECT u.name, u2g.show_order FROM ' . TABLE_USERS . ' AS u LEFT JOIN ' . TABLE_CURATES . ' AS u2g USING (userid) WHERE u2g.symbol = "' . $zData['symbol'] . '" ORDER BY (u2g.show_order > 0) DESC, u2g.show_order, u.level DESC, u.name');
    $nCurators = mysql_num_rows($qCurators);
    $i = 0;
    while ($r = mysql_fetch_row($qCurators)) {
        $i ++;
        $zData['curators_'] .= ($i == 1? '' : ($i == $nCurators? ' and ' : ', ')) . ($r[1]? '<B>' . $r[0] . '</B>' : '<I>' . $r[0] . ' (hidden)</I>');
    }
    $aTable['curators_'] .= ' (' . $nCurators . ')';

    // Parse and build Custom Links.
    // Just for Gene/Reference.
    lovd_buildLinks($zData);

    foreach ($aTable as $sField => $sHeader) {
        print("\n" .
              '        <TR>' . "\n" .
              '          <TH valign="top">' . str_replace(' ', '&nbsp;', $sHeader) . '</TH>' . "\n" .
              '          <TD>' . (!$zData[$sField]? '-' : $zData[$sField]) . '</TD></TR>');
    }
    print('</TABLE>' . "\n\n");

    $sNavigation = '';
    // 2010-06-23; 2.0-27; Security code here was useless, as this page cannot be accessed by curators but only by managers and higher.
    $sNavigation = '<A href="' . ROOT_PATH . 'config_genes.php?action=edit&amp;edit=' . $zData['symbol'] . '">Edit gene</A>  | <A href="' . $_SERVER['PHP_SELF'] . '?action=drop&amp;drop=' . $zData['symbol'] . '">Delete gene</A>';
    $sNavigation .= ' | <A href="setup_curators.php?action=edit&amp;edit=' . $zData['symbol'] . '">Add/remove curators</A> | <A href="setup_curators.php?action=sort&amp;sort=' . $zData['symbol'] . '">Sort/hide curator names</A>';

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_viewNavigation($sNavigation);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;





} elseif ($_GET['action'] == 'create') {
    // Create new gene.

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // 2010-01-13; 2.0-24; Added proper check on chromosome information because we need this to map to the genome!
        if (!empty($_POST['chrom_location']) && !empty($_POST['refseq_build']) && substr($_POST['refseq_build'], 0, 2) == 'hg' && !preg_match('/^([0-9]{1,2}|[XY])([pqtercen0-9.-]+)?$/', $_POST['chrom_location'])) {
            lovd_errorAdd('Incorrect chromosome location. Chromosome locations have to start with the chromosome number or name (X,Y), possibly followed by the chromosome band location.');
        }

        // Date of creation.
        if ($_POST['created_date'] && !lovd_matchDate($_POST['created_date'])) {
            lovd_errorAdd('The \'Date of creation\' field does not seem to contain a correct date format. Allowed formats: YYYY-MM-DD, YYYY.MM.DD, YYYY/MM/DD or YYYY\MM\DD.');
        }

        // GenBank file or ID.
        if ($_POST['genbank']) {
            if (empty($_POST['genbank_uri'])) {
                lovd_errorAdd('If you wish to use a GenBank file, please fill in the "GenBank file name or ID" field. Otherwise, clear the "Has a GenBank file" field.');
            } else {
                if (basename($_POST['genbank_uri']) != $_POST['genbank_uri']) {
                    // 2008-03-06; 2.0-05; Disallow Directory Traversal Attack.
                    lovd_errorAdd('Illegal GenBank file name or ID.');
                } elseif ($_POST['genbank'] == 1 && !is_readable(ROOT_PATH . 'genbank/' . $_POST['genbank_uri'])) {
                    // 2008-09-18; 2.0-12; If a proper GenBank filename has been selected, check if it's there.
                    lovd_errorAdd('Could not find the given GenBank file in the genbank directory. Are you sure it\'s in the right location?');
                }
            }

            // 2009-11-11; 2.0-23; The c. -> g. mapping fields are mandatory if the database is using a GenBank file.
            // FIXME; maybe before these checks, check if the field is empty but $_POST['genbank_uri'] contains useful information... then copy, instead of complain.
            if (empty($_POST['refseq_genomic'])) {
                lovd_errorAdd('Please fill in the \'NCBI accession number for the genomic reference sequence\' field.');
            } elseif (!preg_match('/^N(G|C)_[0-9]{6,9}\.[0-9]{1,2}$/', $_POST['refseq_genomic'])) {
                lovd_errorAdd('Please fill in a proper NG or NC accession number in the \'NCBI accession number for the genomic reference sequence\' field, like \'NG_012232.1\'.');
            }
            if (empty($_POST['refseq_mrna'])) {
                lovd_errorAdd('Please fill in the \'NCBI accession number for the transcript reference sequence\' field.');
            } elseif (!preg_match('/^N[MR]_[0-9]{6,9}\.[0-9]{1,2}$/', $_POST['refseq_mrna'])) {
                lovd_errorAdd('Please fill in a proper NM/NR accession number in the \'NCBI accession number for the transcript reference sequence\' field, like \'NM_004006.2\'.');
            }
            if (empty($_POST['refseq_build']) || !isset($_SETT['human_builds'][$_POST['refseq_build']])) {
                lovd_errorAdd('Please fill in the \'Human Build to map to (UCSC/NCBI)\' field.');
            }
        }

        // 2010-01-13; 2.0-24; When refseq is filled in, we need an URL!
        if (!empty($_POST['refseq']) && empty($_POST['refseq_url'])) {
            lovd_errorAdd('You have selected that there is a human-readable reference sequence. Please fill in the "Human-readable reference sequence location" field. Otherwise, select \'No\' for the "This gene has a human-readable reference sequence" field.');
        }

        // Disclaimer text.
        if ($_POST['disclaimer'] == 2 && empty($_POST['disclaimer_text'])) {
            lovd_errorAdd('If you wish to use an own disclaimer, please fill in the "Text for own disclaimer" field. Otherwise, select \'No\' for the "Include disclaimer" field.');
        }

        // URLs.
        $aCheck =
                 array(
                        'url_homepage' => 'Homepage URL',
                        'refseq_url' => 'Human-readable reference sequence location',
                      );

        foreach ($aCheck as $key => $val) {
            if ($_POST[$key] && !lovd_matchURL($_POST[$key])) {
                lovd_errorAdd('The \'' . $val . '\' field does not seem to contain a correct URL.');
            }
        }

        // List of external links.
        if ($_POST['url_external']) {
            $aExternalLinks = explode("\r\n", trim($_POST['url_external']));
            foreach ($aExternalLinks as $n => $sLink) {
                if (!lovd_matchURL($sLink) && (!preg_match('/^[^<>]+ <?([^< >]+)>?$/', $sLink, $aRegs) || !lovd_matchURL($aRegs[1]))) {
                    lovd_errorAdd('External link #' . ($n + 1) . ' (' . htmlspecialchars($sLink) . ') not understood.');
                }
            }
        }

        if (!lovd_error()) {
            require ROOT_PATH . 'class/currdb.php';
            $_CURRDB = new CurrDB(false);

            // Query text.
            $sQ = 'INSERT INTO ' . TABLE_DBS . ' VALUES (';

            $_POST['reference'] = $_POST['Gene/Reference'];

            // Standard fields to be used.
            // 2009-08-18; 2.0-21; added by Gerard: id_uniprot, show_genecards.
            $aQ = array('symbol', 'gene', 'chrom_location', 'refseq_genomic', 'refseq_mrna', 'refseq_build', 'c_position_mrna_start', 'c_position_mrna_end', 'c_position_cds_end', 'g_position_mrna_start', 'g_position_mrna_end', 'reference', 'url_homepage', 'url_external', 'allow_download', 'allow_index_wiki', 'id_hgnc', 'id_entrez', 'id_omim_gene', 'id_omim_disease', 'id_uniprot', 'show_hgmd', 'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'genbank', 'genbank_uri', 'refseq', 'refseq_url', 'disclaimer', 'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align');

            foreach ($aQ as $key => $val) {
                $sQ .= ($key? ', ' : '') . '"' . $_POST[$val] . '"';
            }

            if ($_POST['created_date']) {
                $_POST['created_date'] = '"' . $_POST['created_date'] . '"';
            } else {
                $_POST['created_date'] = 'NOW()';
            }

            $sQ .= ', "' . $_AUTH['id'] . '", ' . $_POST['created_date'] . ', NULL, NULL, "' . $_AUTH['id'] . '", NOW())';

            // If using transactional tables; begin transaction.
            if ($_INI['database']['engine'] == 'InnoDB') {
                // FIXME; It's better to use 'START TRANSACTION', but that's only available from 4.0.11.
                //   This works from the introduction of InnoDB in 3.23
                @lovd_queryDB('BEGIN WORK');
            }

            // Run query to create entry in DBS table.
            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                lovd_dbFout('GeneCreate_A', $sQ, $sError);
            }

            // Make current user curator of this gene.
            $sQ = 'INSERT INTO ' . TABLE_CURATES . ' VALUES ("' . $_AUTH['id'] . '", "' . $_POST['symbol'] . '", 1)';
            $q = @mysql_query($sQ);
            if (!$q) {
                // Save the mysql_error before it disappears.
                $sError = mysql_error();

                if ($_INI['database']['engine'] == 'InnoDB') {
                    @lovd_queryDB('ROLLBACK');
                } else {
                    @mysql_query('DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                }
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                lovd_dbFout('GeneCreate_B', $sQ, $sError);
            }

            // Commit, since a CREATE TABLE will commit either way (MySQL 5.0, too?).
            if ($_INI['database']['engine'] == 'InnoDB') {
                @lovd_queryDB('COMMIT');
            }



            // Create table for column information, based on the patient_columns table.
            require ROOT_PATH . 'install/inc-sql-tables.php';
            $sQ = str_replace(TABLE_PATIENTS_COLS, TABLEPREFIX . '_' . $_POST['symbol'] . '_columns', $aTableSQL['TABLE_PATIENTS_COLS']);
            $q = @mysql_query($sQ);
            if (!$q) {
                // Save the mysql_error before it disappears.
                $sError = mysql_error();

                // Rollback;
                @mysql_query('DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                @mysql_query('DELETE FROM ' . TABLE_CURATES . ' WHERE symbol = "' . $_POST['symbol'] . '"');

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                lovd_dbFout('GeneCreate_C', $sQ, $sError);
            }



            // Gather info on standard custom variant columns.
            $aColsToCopy = array('colid', 'col_order', 'width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public', 'public_form', 'created_by', 'created_date');
            $qCols = mysql_query('SELECT * FROM ' . TABLE_COLS . ' WHERE (hgvs = 1 OR standard = 1) AND colid LIKE "Variant/%"');

            while ($z = mysql_fetch_assoc($qCols)) {
                // $z comes from the database, and is therefore not quoted.
                lovd_magicQuote($z);

                // Calculate the standard width of the column based on the maximum number of characters.
                $nHeadLength = strlen($z['head_column']);
                $nColLength = $_CURRDB->getFieldLength($z['colid']) / 2;
                $nColLength = ($nColLength < $nHeadLength? $nHeadLength : $nColLength);
                // Compensate for small/large fields.
                $nColLength = ($nColLength < 5? 5 : ($nColLength > 35? 35 : $nColLength));
                if ($nColLength < 10) {
                    $z['width'] = 10*$nColLength;
                } else {
                    $z['width'] = 8*$nColLength;
                }
                $z['width'] = ($z['width'] > 200? 200 : $z['width']);

                // Created_* columns...
                $z['created_by'] = 0; // 'LOVD'
                $z['created_date'] = date('Y-m-d H:i:s');

                $sQ = 'INSERT INTO ' . TABLEPREFIX . '_' . $_POST['symbol'] . '_columns (';
                $aCol = array();
                foreach ($aColsToCopy as $sCol) {
                    if (isset($z[$sCol])) {
                        $sQ .= (substr($sQ, -1) == '('? '' : ', ') . $sCol;
                        $aCol[] = $z[$sCol];
                    }
                }
                $sQ .= ') VALUES (';

                foreach ($aCol as $key => $val) {
                    $sQ .= ($key? ', ' : '') . '"' . $aCol[$key] . '"';
                }
                $sQ .= ')';

                // Insert default LOVD custom column.
                $q = @mysql_query($sQ);
                if (!$q) {
                    // Save the mysql_error before it disappears.
                    $sError = mysql_error();

                    // Rollback;
                    @mysql_query('DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                    @mysql_query('DELETE FROM ' . TABLE_CURATES . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                    @mysql_query('DROP TABLE ' . TABLEPREFIX . '_' . $_POST['symbol'] . '_columns');

                    require ROOT_PATH . 'inc-top.php';
                    lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                    lovd_dbFout('GeneCreate_D', $sQ, $sError);
                }
            }



            // Create variant table.
            // 2009-06-11; 2.0-19; Added 5 chars to sort column length because of new codes.
            // 2009-07-09; 2.0-19 update; Added another 16 chars.
            // 2009-11-11; 2.0-23; Add columns for c and g position calculation and variant type.
            $sQ = 'CREATE TABLE ' . TABLEPREFIX . '_' . $_POST['symbol'] . '_variants (
                    variantid MEDIUMINT(7) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    c_position_start MEDIUMINT,
                    c_position_start_intron MEDIUMINT,
                    c_position_end MEDIUMINT,
                    c_position_end_intron MEDIUMINT,
                    g_position_start INT UNSIGNED,
                    g_position_end INT UNSIGNED,
                    type VARCHAR(10),
                    sort VARCHAR(52) NOT NULL';

            // Load standard columns.
            $q = mysql_query('SELECT t1.colid, t2.mysql_type FROM ' . TABLEPREFIX . '_' . $_POST['symbol'] . '_columns AS t1 LEFT JOIN ' . TABLE_COLS . ' AS t2 USING (colid)');
            while ($z = mysql_fetch_assoc($q)) {
                // Fetch cols for this gene and insert them into the query.
                $sQ .= ',' . "\n" . 
                       '`' . $z['colid'] . '` ' . $z['mysql_type'] . ' NOT NULL';
            }

            // 2009-11-11; 2.0-23; Add indexes on the columns for c and g position calculation.
            $sQ .= ',' . "\n" .
                   'INDEX (c_position_start, c_position_end),' . "\n" .
                   'INDEX (c_position_start, c_position_start_intron, c_position_end, c_position_end_intron),' . "\n" .
                   'INDEX (g_position_start, g_position_end)';

            $sQ .= ') TYPE=' . $_INI['database']['engine'];

            $q = @mysql_query($sQ);
            if (!$q) {
                // Save the mysql_error before it disappears.
                $sError = mysql_error();

                // Rollback;
                @mysql_query('DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                @mysql_query('DELETE FROM ' . TABLE_CURATES . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                @mysql_query('DROP TABLE ' . TABLEPREFIX . '_' . $_POST['symbol'] . '_columns');

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                lovd_dbFout('GeneCreate_E', $sQ, $sError);
            }



            // Gene successfully created!
            // Write to log...
            lovd_writeLog('MySQL:Event', 'GeneCreate', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully created gene ' . $_POST['symbol'] . ' (' . $_POST['gene'] . ')');

            // 2008-09-19; 2.0-12; Reload Mutalyzer module, if present, if GenBank file has been added.
            if ($_POST['genbank'] == 1 && $_POST['genbank_uri'] && $_MODULES->isLoaded('mutalyzer')) {
                $_MODULES->disable('mutalyzer');
                $_MODULES->enable('mutalyzer');
            }

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/config.php?select_db=' . $_POST['symbol'] . lovd_showSID(true));

            // Set currdb.
            @mysql_query('UPDATE ' . TABLE_USERS . ' SET current_db = "' . $_POST['symbol'] . '" WHERE id = "' . $_AUTH['id'] . '"');
            $_SESSION['currdb'] = $_POST['symbol'];
            // These just to have inc-top.php what it needs.
            $_SETT['currdb'] = array(
                    'gene' => $_POST['gene'],
                    'symbol' => $_POST['symbol']);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
            print('      Successfully created the ' . $_POST['symbol'] . ' gene!<BR>' . "\n" .
                  '      <BR>' . "\n\n");
            print('      <BUTTON onclick="window.location.href=\'' . ROOT_PATH . 'config.php?select_db=' . $_POST['symbol'] . lovd_showSID(true, true) . '\';" style="font-weight : bold; font-size : 11px;">Continue &gt;&gt;</BUTTON>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, thus we must return to the form.
            lovd_magicUnquoteAll();
        }

    } else {
        // Default values.
        $_POST['c_position_mrna_start'] = '';
        $_POST['c_position_mrna_end'] = '';
        $_POST['c_position_cds_end'] = '';
        $_POST['g_position_mrna_start'] = '';
        $_POST['g_position_mrna_end'] = '';
        $_POST['genbank'] = 2;
        $_POST['refseq_build'] = 'hg19';
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');

    if (!isset($_GET['sent'])) {
        print('      To create a new gene database, please complete the form below and press \'Create\' at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // 2009-11-11; 2.0-23; We want to try and force people to use a proper reference sequence.
    lovd_includeJS('inc-js-submit_geneform.php');

    // Table.
    // 2009-11-11; 2.0-23; Added the JS; we want to try and force people to use a proper reference sequence.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;sent" method="post" onsubmit="return lovd_checkSubmittedForm();">' . "\n" .
          '        <INPUT type="hidden" name="c_position_mrna_start" value="' . $_POST['c_position_mrna_start'] . '">' . "\n" .
          '        <INPUT type="hidden" name="c_position_mrna_end" value="' . $_POST['c_position_mrna_end'] . '">' . "\n" .
          '        <INPUT type="hidden" name="c_position_cds_end" value="' . $_POST['c_position_cds_end'] . '">' . "\n" .
          '        <INPUT type="hidden" name="g_position_mrna_start" value="' . $_POST['g_position_mrna_start'] . '">' . "\n" .
          '        <INPUT type="hidden" name="g_position_mrna_end" value="' . $_POST['g_position_mrna_end'] . '">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

    $aHumanBuilds = array();
    foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
        $aHumanBuilds[$sCode] = $sCode . ' / ' . $aBuild['ncbi_name'];
    }

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '40%', '60%'),
                    array('', 'print', '<B>General information</B>'),
                    'hr',
                    array('Full gene name', 'text', 'gene', 40),
                    'hr',
                    array('Official gene symbol', 'text', 'symbol', 10),
                    array('', 'print', '<SPAN class="form_note">The gene symbol is used by LOVD to reference to this gene and can\'t be changed later on. To create multiple databases for one gene, append \'_\' and an indentifier, i.e. \'DMD_point\' and \'DMD_deldup\' for the DMD gene.</SPAN>'),
                    'hr',
                    array('Chromosome location', 'text', 'chrom_location', 15),
                    array('', 'print', '<SPAN class="form_note">Example: Xp21.2</SPAN>'),
                    'hr',
                    array('Date of creation (optional)', 'text', 'created_date', 10),
                    array('', 'print', '<SPAN class="form_note">Format: YYYY-MM-DD. If left empty, today\'s date will be used.</SPAN>'),
                    'hr',
                    'skip',
                    'skip',
                    array('', 'print', '<B>Reference sequences</B>'),
                    array('', 'print', '<SPAN class="form_note">Collecting variants requires a proper reference sequence.</SPAN>'),
                    'hr',
                    array('This gene has a GenBank file', 'select', 'genbank', 1, array(1 => 'Uploaded own GenBank file', 2 => 'NCBI GenBank record', 3 => 'Mutalyzer UD identifier'), 'No', false, false),
                    array('', 'print', '<SPAN class="form_note">Without a (genomic) reference sequence the variants in this LOVD database cannot be interpreted properly. A valid genomic GenBank file can be used to map your variants to a genomic location, as well as creating a human-readable reference sequence format and linking to the mutation check Mutalyzer module. Select this option if you have a GenBank file uploaded to the genbank directory, if you want to use a GenBank record at the NCBI or if you have uploaded your GenBank file to Mutalyzer.</SPAN>'),
                    'hr',
                    array('GenBank file name or ID', 'text', 'genbank_uri', 30),
                    array('', 'print', '<SPAN class="form_note">If you have a GenBank file uploaded to the genbank directory, fill in the filename. If you wish to use a NCBI GenBank record, fill in the GenBank accession number. If you have uploaded your GenBank file to Mutalyzer and have received a Mutalyzer UD identifier, fill in this identifier.</SPAN>'),
                    'hr',
                    'skip',
                    array('', 'print', '<SPAN class="form_note"><B>The following three fields are for the mapping of the variants to the genomic reference sequence. They are mandatory if you have a GenBank file, and highly recommended otherwise.</B></SPAN>'),
                    'hr',
                    array('NCBI accession number for the genomic reference sequence', 'text', 'refseq_genomic', 15),
                    array('', 'print', '<SPAN class="form_note">Fill in the NCBI GenBank ID of the genomic reference sequence (NG or NC accession numbers), such as "NG_012232.1" or "NC_000023.10". If you have already provided an NG or NC accession number above, please copy that value to this field. Always include the version number as well!</SPAN>'),
                    'hr',
                    array('NCBI accession number for the transcript reference sequence', 'text', 'refseq_mrna', 15),
                    array('', 'print', '<SPAN class="form_note">Fill in the NCBI GenBank ID of the transcript reference sequence (NM/NR accession numbers), such as "NM_004006.2". If you have already provided an NM/NR accession number above, please copy that value to this field. Always include the version number as well!</SPAN>'),
                    'hr',
                    array('Human Build to map to (UCSC/NCBI)', 'select', 'refseq_build', 1, $aHumanBuilds, false, false, false),
                    array('', 'print', '<SPAN class="form_note">We need to know which version of the Human Build we need to map to.</SPAN>'),
                    'hr',
                    'skip',
                    'skip',
                    array('', 'print', '<B>Links to information sources (optional)</B>'),
                    array('', 'print', '<SPAN class="form_note">Here you can add links that will be displayed on the gene\'s LOVD gene homepage.</SPAN>'),
                    'hr',
                    array('Homepage URL', 'text', 'url_homepage', 40),
                    array('', 'print', '<SPAN class="form_note">If you have a separate homepage about this gene, you can specify the URL here.<BR>Format: complete URL, including &quot;http://&quot;.</SPAN>'),
                    'hr',
                    array('External links', 'textarea', 'url_external', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Here you can provide links to other resources on the internet that you would like to link to.<BR>One link per line, format: complete URLs or &quot;Description &lt;URL&gt;&quot;.</SPAN>'),
                    'hr',
                    array('HGNC ID', 'text', 'id_hgnc', 10),
                    'hr',
                    array('Entrez Gene (Locuslink) ID', 'text', 'id_entrez', 10),
                    'hr',
                    array('OMIM Gene ID', 'text', 'id_omim_gene', 10),
                    'hr',
                    array('OMIM Disease IDs', 'textarea', 'id_omim_disease', 40, 3),
                    array('', 'print', '<SPAN class="form_note">One line per OMIM ID, format : &quot;OMIM_ID Disease_name&quot;.<BR>Example : &quot;310200 Duchenne Muscular Dystrophy (DMD)&quot;.</SPAN>'),
                    'hr',
                    // 2009-08-17; 2.0-21; added link to UniProtKB/Swiss-Prot.
                    array('UniProt (SwissProt/TrEMBL) ID', 'text', 'id_uniprot', 10),
                    array('', 'print', '<SPAN class="form_note">This will add a link to the UniProtKB (SwissProt/TrEMBL) database from the gene\'s homepage.</SPAN>'),
                    'hr',
                    array('Provide link to HGMD', 'checkbox', 'show_hgmd', 1),
                    array('', 'print', '<SPAN class="form_note">Do you want a link to this gene\'s entry in the Human Gene Mutation Database added to the homepage?</SPAN>'),
                    'hr',
                    // 2009-08-17; 2.0-21; added link to GeneCards
                    array('Provide link to GeneCards', 'checkbox', 'show_genecards', 1),
                    array('', 'print', '<SPAN class="form_note">Do you want a link to this gene\'s entry in the GeneCards database added to the homepage?</SPAN>'),
                    'hr',
                    array('Provide link to GeneTests', 'checkbox', 'show_genetests', 1),
                    array('', 'print', '<SPAN class="form_note">Do you want a link to this gene\'s entry in the GeneTests database added to the homepage?</SPAN>'),
                    'hr',
                    array('This gene has a human-readable reference sequence', 'select', 'refseq', 1, array('c' => 'Coding DNA', 'g' => 'Genomic'), 'No', false, false),
                    array('', 'print', '<SPAN class="form_note">Although GenBank files are the official reference sequence, they are not very readable for humans. If you have a human-readable format of your reference sequence online, please select the type here.</SPAN>'),
                    'hr',
                    array('Human-readable reference sequence location', 'text', 'refseq_url', 40),
                    array('', 'print', '<SPAN class="form_note">If you used our Reference Sequence Parser to create a human-readable reference sequence, the result is located at<BR>&quot;' . (!empty($_CONF['location_url'])? $_CONF['location_url'] : PROTOCOL . $_SERVER['HTTP_HOST'] . lovd_cleanDirName(dirname($_SERVER['PHP_SELF']) . '/' . ROOT_PATH)) . 'refseq/GENESYMBOL_codingDNA.html&quot;.</SPAN>'),
                    'hr',
                    'skip',
                    'skip',
                    array('', 'print', '<B>Customizations (optional)</B>'),
                    array('', 'print', '<SPAN class="form_note">You can use the following fields to customize the gene\'s LOVD gene homepage.</SPAN>'),
                    'hr',
                    array('Citation reference(s)', 'textarea', 'Gene/Reference', 30, 3),
                    // FIXME; this is hard-coded... do this gracefully, add links if applicable and remove this if it's deactivated
                    array('', 'print', '<SPAN class="S11">(Active custom link : <A href="#" onclick="javascript:lovd_openWindow(\'' . ROOT_PATH . 'links.php?view=1&amp;col=Gene/Reference\', \'LinkView\', \'800\', \'200\'); return false;">PubMed</A>)</SPAN>'),
                    'hr',
                    array('Include disclaimer', 'select', 'disclaimer', 1, array(1 => 'Use standard LOVD disclaimer', 2 => 'Use own disclaimer (enter below)'), 'No', false, false),
                    array('', 'print', '<SPAN class="form_note">If you want a disclaimer added to the gene\'s LOVD gene homepage, select your preferred option here.</SPAN>'),
                    'hr',
                    array('Text for own disclaimer', 'textarea', 'disclaimer_text', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Only applicable if you choose to use your own disclaimer (see option above).</SPAN>'),
                    'hr',
                    array('Page header', 'textarea', 'header', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Text entered here will appear above all public gene-specific pages.</SPAN>'),
                    array('Header aligned to', 'select', 'header_align', 1, $_SETT['notes_align'], false, false, false),
                    'hr',
                    array('Page footer', 'textarea', 'footer', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Text entered here will appear below all public gene-specific pages.</SPAN>'),
                    array('Footer aligned to', 'select', 'footer_align', 1, $_SETT['notes_align'], false, false, false),
                    'hr',
                    array('Notes for the LOVD gene homepage', 'textarea', 'note_index', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Text entered here will appear in the General Information box on the gene\'s LOVD gene homepage.</SPAN>'),
                    'hr',
                    array('Notes for the variant listings', 'textarea', 'note_listing', 55, 3),
                    array('', 'print', '<SPAN class="form_note">Text entered here will appear below the gene\'s variant listings.</SPAN>'),
                    'hr',
                    'skip',
                    'skip',
                    array('', 'print', '<B>Security settings</B>'),
                    array('', 'print', '<SPAN class="form_note">Using the following settings you can control some security settings of LOVD.</SPAN>'),
                    'hr',
                    array('Allow public to download variant entries', 'checkbox', 'allow_download', 1),
                    'hr',
                    array('Allow my public variant and patient data to be indexed by WikiProfessional', 'checkbox', 'allow_index_wiki', 1),
                    'hr',
                    'skip',
                    array('', 'submit', 'Create'),
                  );
    $_MODULES->processForm('SetupGenesCreate', $aForm);
    lovd_viewForm($aForm);

    print('</TABLE></FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;





} elseif ($_GET['action'] == 'drop' && !empty($_GET['drop'])) {
    // Drop specific gene.

    $zData = @mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_GET['drop'] . '"'));
    if (!$zData) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_genes_manage', 'LOVD Setup - Manage configured genes');
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        if (!isset($_GET['confirm'])) {
            // Mandatory fields.
            $aCheck =
                     array(
                            'password' => 'Enter your password for authorization',
                          );

            foreach ($aCheck as $key => $val) {
                if (empty($_POST[$key])) {
                    lovd_errorAdd('Please fill in the \'' . $val . '\' field.');
                }
            }

            // User had to enter his/her password for authorization.
            if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
                lovd_errorAdd('Please enter your correct password for authorization.');
            }
        }

        if (!lovd_error()) {
            // Show second form, last confirmation.

            if (isset($_GET['confirm'])) {
                lovd_errorClean();

                // Mandatory fields.
                $aCheck =
                         array(
                                'password' => 'Enter your password for authorization',
                              );

                foreach ($aCheck as $key => $val) {
                    if (empty($_POST[$key])) {
                        lovd_errorAdd('Please fill in the \'' . $val . '\' field.');
                    }
                }

                // User had to enter his/her password for authorization.
                if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
                    lovd_errorAdd('Please enter your correct password for authorization.');
                }

                if (!lovd_error()) {
                    // It's useless to use transactions here. We have to drop tables, and these COMMIT on MySQL, as far as 5.1.
                    // Drop Columns & Variants, Remove from TABLE_DBS & TABLE_CURATES & current_db in TABLE_USERS, Remove orphaned patients from TABLE_PATIENTS.

                    require ROOT_PATH . 'inc-top.php';
                    lovd_printHeader('setup_genes_manage', 'LOVD Setup - Manage configured genes');

                    // Start out with the column information.
                    print('      Removing ' . $zData['symbol'] . ' gene from LOVD...<BR>' . "\n" .
                          '      Removing columns ... ');
                    flush();
                    $sQ = 'DROP TABLE IF EXISTS ' . TABLEPREFIX . '_' . $zData['symbol'] . '_columns';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropA', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");

                    // Variants.
                    print('      Removing variants ... ');
                    flush();
                    $sQ = 'DROP TABLE IF EXISTS ' . TABLEPREFIX . '_' . $zData['symbol'] . '_variants';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropB', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");

                    // Variants connections.
                    print('      Removing variants <-> patients links ... ');
                    flush();
                    $sQ = 'DELETE FROM ' . TABLE_PAT2VAR . ' WHERE symbol = "' . $zData['symbol'] . '"';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropC', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");

                    // DBS gene entry.
                    print('      Removing gene entry ... ');
                    flush();
                    $sQ = 'DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $zData['symbol'] . '"';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropD', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");

                    // CURATES entries.
                    print('      Removing curator permissions ... ');
                    flush();
                    $sQ = 'DELETE FROM ' . TABLE_CURATES . ' WHERE symbol = "' . $zData['symbol'] . '"';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropE', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");

                    // CURRDB settings.
                    print('      Updating user settings ... ');
                    flush();
                    $sQ = 'UPDATE ' . TABLE_USERS . ' SET current_db = "" WHERE current_db = "' . $zData['symbol'] . '"';
                    $q = mysql_query($sQ);
                    if (!$q) {
                        lovd_dbFout('GeneDropF', $sQ, mysql_error());
                    }
                    print('OK<BR>' . "\n");
                    // 2008-07-08; 2.0-09; Unset $_SESSION['currdb'] only if it's the currently selected gene.
                    if ($_SESSION['currdb'] == $zData['symbol']) {
                        $_SESSION['currdb'] = false;
                    }

                    // Orphaned patients.
                    print('      Removing obsolete patients ... ');
                    flush();
                    // Backwards compatible with MySQL 4.0 and earlier. These versions do not support subqueries, which would really come in handy now.
                    // First, determine the ID's of the orphaned patients. Then construct the DELETE query.
                    $aOrphaned = array();
                    $qOrphaned = lovd_queryDB('SELECT p.patientid FROM ' . TABLE_PATIENTS . ' AS p LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (patientid) WHERE p2v.symbol IS NULL');
                    while ($rOrphaned = mysql_fetch_row($qOrphaned)) {
                        $aOrphaned[] = $rOrphaned[0];
                    }
                    if (count($aOrphaned)) {
                        // Construct DELETE query.
                        $sQ = 'DELETE FROM ' . TABLE_PATIENTS . ' WHERE patientid IN (' . implode(', ', $aOrphaned) . ')';
                        $q = mysql_query($sQ);
                        if (!$q) {
                            lovd_dbFout('GeneDropG', $sQ, mysql_error());
                        }
                        print('OK<BR><BR>' . "\n\n");
                    } else {
                        print('N/A<BR><BR>' . "\n\n");
                    }

                    // Write to log...
                    lovd_writeLog('MySQL:Event', 'GeneDrop', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully deleted gene ' . $zData['symbol'] . ' (' . $zData['gene'] . ')');

                    // Thank the user...
                    print('      Successfully deleted gene ' . $zData['symbol'] . ' and all related information!<BR><BR>' . "\n\n");
                    if (GENE_COUNT == 1) {
                        print('      Do you wish to <A href="' . $_SERVER['PHP_SELF'] . '?action=find_hgnc">create a new database</A>?<BR><BR>' . "\n\n");
                    }

                    // Alternate refresh; since we can't send a HTTP header...
                    print('      <SCRIPT type="text/javascript">' . "\n" .
                          '      <!--' . "\n" .
                          '        setTimeout(\'window.location.href = "' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true, true) . '"\', 5000);' . "\n" .
                          '      // -->' . "\n" .
                          '      </SCRIPT>' . "\n\n");

                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }
            }

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_genes_manage', 'LOVD Setup - Manage configured genes');

            // Total number of variants found in this gene (incl. non curated).
            // 2008-07-08; 2.0-09; Check number of variants using $zData['symbol'], not $_SESSION['currdb'] of course...
            list($nTotalVariants) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_PAT2VAR . ' WHERE symbol = "' . $zData['symbol'] . '"'));

            lovd_showInfoTable('<B>FINAL WARNING! Removing the ' . $zData['symbol'] . ' gene will imply the loss of ' . $nTotalVariants . ' variant entr' . ($nTotalVariants == 1? 'y' : 'ies') . '. If you didn\'t download the varation data stored for the ' . $zData['symbol'] . ' gene in the LOVD system, everything will be lost.</B>', 'warning');

            if (!isset($_GET['confirm'])) {
                print('      Please note the message above and fill in your password one more time to remove the ' . $zData['symbol'] . ' gene.<BR>' . "\n" .
                      '      <BR>' . "\n\n");
            }

            lovd_errorPrint();

            // Table.
            print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;drop=' . $zData['symbol'] . '&amp;sent=true&amp;confirm=true" method="post">' . "\n" .
                  '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

            // Array which will make up the form table.
            $aForm = array(
                            array('POST', '', '', '50%', '50%'),
                            array('Deleting gene', 'print', $zData['symbol'] . ' (' . $zData['gene'] . ')'),
                            'skip',
                            array('Enter your password for authorization', 'password', 'password', 20),
                            array('', 'submit', 'Delete ' . $zData['symbol'] . ' gene'),
                          );
            $_MODULES->processForm('SetupGenesDeleteConfirm', $aForm);
            lovd_viewForm($aForm);

            print('</TABLE></FORM>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_genes_manage', 'LOVD Setup - Manage configured genes');

    lovd_showInfoTable('WARNING! If you did not download your variations, you will loose all of your data!', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;drop=' . $zData['symbol'] . '&amp;sent=true" method="post">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('Deleting gene', 'print', $zData['symbol'] . ' (' . $zData['gene'] . ')'),
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'submit', 'Delete ' . $zData['symbol'] . ' gene'),
                  );
    $_MODULES->processForm('SetupGenesDelete', $aForm);
    lovd_viewForm($aForm);

    print('</TABLE></FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;





} else {
    // Default action:
    header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true));
    exit;
}
*/
?>
