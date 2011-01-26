<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2011-01-26
 * For LOVD    : 3.0-pre-16
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

    define('PAGE_TITLE', 'LOVD Setup - Manage configured genes');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new Gene();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^(\w)+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /genes/DMD
    // View specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'View gene ' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new Gene();
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="genes/' . $nID . '?edit">Edit gene information</A>';
        $sNavigation .= ' | <A href="genes/' . $nID . '?delete">Delete gene entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }
    
    $_GET['search_geneid'] = $nID;
    print('<BR><BR><H2 class="LOVD">Transcripts for gene ' . $nID . '</H2>');
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new Transcript();
    $zData = $_DATA->viewList();
    
    
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
    $_DATA = new Gene();
    
    if (GET) {
        $_POST['workID'] = lovd_generateRandomID();
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
                $sSQL = 'SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?';
                $aSQL = array($_POST['hgnc_id']);
                
                if (mysql_num_rows(lovd_queryDB($sSQL, $aSQL))) {
                    lovd_errorAdd('hgnc_id', 'This gene entry is already present in this LOVD installation. Please choose another one.');
                } else {
                    if (preg_match("/^\d+$/", $_POST['hgnc_id'])) {
                        $sWhere = 'gd_hgnc_id%3D' . $_POST['hgnc_id'];
                    } else {
                        $sWhere = 'gd_app_sym%3D%22' . $_POST['hgnc_id'] . '%22';
                    }
                    $aHgncFile = lovd_php_file('http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?col=gd_hgnc_id&col=gd_app_sym&col=gd_app_name&col=gd_pub_chrom_map&col=gd_pub_eg_id&col=md_mim_id&status_opt=2&where=' . $sWhere . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit', false, false);
                    
                    if (isset($aHgncFile['1'])) {
                        list($sHgncID, $sSymbol, $sName, $sChromLocation, $sEntrez, $sOmim) = explode("\t", $aHgncFile['1']);
                        list($sEntrez, $sOmim) = array_values(array_map('trim', array($sEntrez, $sOmim)));
                        if ($sName == 'entry withdrawn') {
                            lovd_errorAdd('hgnc_id', 'Entry ' . $_POST['hgnc_id'] . ' no longer exists in the HGNC database.');
                        } else if (preg_match('/^symbol withdrawn, see (.+)$/', $sName, $aRegs)) {
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

                $_MutalyzerWS = new REST2SOAP();
                $_MutalyzerWS->sSoapURL = 'http://10.160.8.105/mutalyzer2/services';
                //$_MutalyzerWS->sSoapURL = 'http://www.mutalyzer.nl/2.0/services';
                //$_MutalyzerWS->sSoapURL = 'http://mutalyzer.martijn/services';
                
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
                $aUDID = $_MutalyzerWS->moduleCall('sliceChromosomeByGene', array('geneSymbol' => $sSymbol, 'organism' => 'Man', 'upStream' => '5000', 'downStream' => '2000'));
                if (!is_array($aUDID)) {
                    lovd_displayError('SOAP-ERROR', $aUDID);
                } else {
                    $sRefseqUD = $aUDID[0];
                }

                // Get transcripts for the hgnc entry from mutalyzer
                $_BAR->setMessage('Collecting all available transcripts...');
                $_BAR->setProgress(66);
                $aTranscripts = $_MutalyzerWS->moduleCall("getTranscriptsByGeneName", array("build" => $_CONF['refseq_build'], "name" => $sSymbol));

                if (!is_array($aTranscripts)) {
                    if (!preg_match('/^Empty/', $aTranscripts)) {
                        lovd_displayError('SOAP-ERROR', $aTranscripts);
                    }
                    $aTranscripts = array();
                    $aTranscriptNames = array();
                    $_BAR->setMessage('No transcripts to collect info from, continueing...');
                    $_BAR->setProgress(83);
                } else {
                    // Get transcript info from mutalyzer
                    $_BAR->setMessage('Collecting transcript info...');
                    $_BAR->setProgress(83);
                    foreach ($aTranscripts as $sTranscript) {
                        $aTranscriptInfo = $_MutalyzerWS->moduleCall("getGeneAndTranscript", array("genomicReference" => $sRefseqUD, "transcriptReference" => $sTranscript));
                        $aTranscriptNames[$sTranscript] = $aTranscriptInfo[1]; 
                    }
                    if (!is_array($aTranscriptNames)) {
                        $aTranscriptNames = array();
                    }
                }

                $_BAR->setProgress(100);
                $_BAR->setMessage('Information collected, now building form...');
                $_BAR->setMessageVisibility('done', true);
                $_SESSION['work'][$_POST['workID']]['step'] = '2';
                $_SESSION['work'][$_POST['workID']]['values'] = array(
                                                                  'id' => $sSymbol,
                                                                  'name' => $sName,
                                                                  'chromosome' => $sChromosome,
                                                                  'chrom_band' => $sChromBand,
                                                                  'id_hgnc' => $sHgncID,
                                                                  'id_entrez' => $sEntrez,
                                                                  'id_omim' => $sOmim,
                                                                  'transcripts' => $aTranscripts,
                                                                  'transcriptNames' => $aTranscriptNames,
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
                $_POST['refseq_UD'] = $zData['refseq_UD'];
                $_POST['chromosome'] = $zData['chromosome'];
                $_POST['id_hgnc'] = $zData['id_hgnc'];
                $_POST['id_entrez'] = ($zData['id_entrez']? $zData['id_entrez'] : '');
                $_POST['id_omim'] = ($zData['id_omim']? $zData['id_omim'] : '');

                $nID = $_DATA->insertEntry($_POST, $aFields);

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $_POST['id'] . ' (' . $_POST['name'] . ')');

                // Add diseases.
                $aSuccessDiseases = array();
                $aSuccessTranscripts = array();
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
                
                if (empty($_POST['active_transcripts']) || $_POST['active_transcripts'][0] != 'None') {
                    foreach($_POST['active_transcripts'] as $sTranscript) {
                        // Add transcript to gene
                        $_MutalyzerWS = new REST2SOAP();
                        $_MutalyzerWS->sSoapURL = 'http://10.160.8.105/mutalyzer2/services';
                        $aOutput = $_MutalyzerWS->moduleCall("transcriptInfo", array("LOVD_ver" => '3', "build" => $_CONF['refseq_build'], "accNo" => $sTranscript));
                        if (!is_array($aOutput)) {
                            $aTranscriptPositions = array(1, 1, 1);
                            $aTranscriptGenomePositions = array(1, 1);
                        } else {
                            $aTranscriptPositions = $aOutput;
                            $aOutput = $_MutalyzerWS->moduleCall("numberConversion", array("build" => $_CONF['refseq_build'], "variant" => $sTranscript . ':c.' . $aTranscriptPositions[0] . '_' . $aTranscriptPositions[1] . 'del'));
                            if (!is_array($aOutput)) {
                                $aTranscriptGenomePositions = array(1, 1);
                            } else {
                                preg_match('/^.+:g.(.+)_(.+)del$/', $aOutput[0], $aMatches);
                                $aTranscriptGenomePositions = array($aMatches[1], $aMatches[2]);
                            }
                        }
                        $sTranscriptName = str_replace('(' . $sTranscript . ')', '', $zData['transcriptNames'][$sTranscript]);
                        $q = lovd_queryDB('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($_POST['id'], $sTranscriptName, $sTranscript, 'NULL', 'NULL', 'NULL', 'NULL', $aTranscriptPositions[0], $aTranscriptPositions[1], $aTranscriptPositions[2], $aTranscriptGenomePositions[0], $aTranscriptGenomePositions[1], date('Y-m-d'), $_POST['created_by']));
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

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $_POST['id']);

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





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\w+$/', $_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /genes/DMD?edit
    // Edit an entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Edit gene information entry');
    define('LOG_EVENT', 'GeneEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    $_DATA = new Gene();
    $zData = $_DATA->loadEntry($nID);
    
    if (GET) {
        require ROOT_PATH . 'inc-lib-genes.php';
        
        $aRefseqGenomic = array();
        // Get LRG if it exists
        if ($sLRG = getLrgByGeneSymbol($nID)) {
            $aRefseqGenomic[] = $sLRG;
        }
        // Get NG if it exists
        if ($sNG = getNgByGeneSymbol($nID)) {
            $aRefseqGenomic[] = $sNG;
        }
        // Get NC from LOVD
        $aRefseqGenomic[] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$zData['chromosome']];
        
        $_POST['workID'] = lovd_generateRandomID();
        $_SESSION['work'][$_POST['workID']] = array(
                                                    'action' => '/genes/' . $nID . '?edit',
                                                    'values' => array(
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
            
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited gene information entry ' . $nID . ' (' . $_POST['name'] . ')');

            // Add diseases.
            $aSuccessDiseases = array();
            foreach ($_POST['active_diseases'] as $sDisease) {
                // Add disease to gene.
                lovd_queryDB('DELETE FROM ' . TABLE_GEN2DIS . ' WHERE diseaseid=?', array($sDisease));
                $q = lovd_queryDB('INSERT INTO ' . TABLE_GEN2DIS . ' VALUES (?,?)', array($nID, $sDisease));
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $sDisease . ' - could not be edited for gene ' . $nID);
                } else {
                    $aSuccessDiseases[] = $sDisease;
                }
            }
            if (count($aSuccessDiseases)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entries successfully edited for gene ' . $nID . ' - ' . $_POST['name']);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

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
    }

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (!lovd_error()) {
        print('      To edit this gene database, please complete the form below and press "Edit" at the bottom of the form..<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }
    
    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');   
    
    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

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





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\w+$/', $_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /genes/DMD?delete
    // Drop specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Delete gene information entry ' . $nID);
    define('LOG_EVENT', 'GeneDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new Gene();
    $zData = $_DATA->loadEntry($nID);
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
            // Query text.
            // This also deletes the entries in gen2dis and transcripts.
            // FIXME; implement deleteEntry()
            $sSQL = 'DELETE FROM ' . TABLE_GENES . ' WHERE id = ?';
            $aSQL = array($zData['id']);
            $q = lovd_queryDB($sSQL, $aSQL);
            if (!$q) {
                lovd_queryError(LOG_EVENT, $sSQL, mysql_error());
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene information entry ' . $nID . ' - ' . $zData['id'] . ' (' . $zData['name'] . ')');

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
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

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
/*
    // Standard query, will be extended later on.
    $sQ = 'SELECT d.*, COUNT(p2v.variantid) AS variants FROM ' . TABLE_DBS . ' AS d LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (symbol)';

    // SORT: Current settings.
    // 2008-08-07; 2.0-10; Implement XSS check on order variable.
    if (isset($_GET['order']) && $_GET['order'] && $_GET['order'] == strip_tags($_GET['order'])) {
        $aOrder = explode(',', $_GET['order']);
    } else {
        $aOrder = array('', '');
    }

    // SORT: Column data.
    $aOrderList = array('symbol' => array('symbol', 'ASC'), 'gene' => array('gene', 'ASC'), 'variants' => array('variants', 'ASC'), 'created_date' => array('created_date', 'ASC'), 'updated_date' => array('updated_date', 'DESC'));
    if (!array_key_exists($aOrder[0], $aOrderList)) {
        $aOrder[0] = 'symbol';
    }
    if ($aOrder[1] != 'ASC' && $aOrder[1] != 'DESC') {
        $aOrder[1] = $aOrderList[$aOrder[0]][1];
    }

    // GROUP BY only necessary because of the COUNT(*) in the query.
    $sQ .= ' GROUP BY d.symbol';

    $sQueryLimit = lovd_pagesplitInit($nTotal, 25);
    $sQ .= ' ORDER BY ' . $aOrderList[$aOrder[0]][0] . ' ' . $aOrder[1] . ', symbol ASC ' . $sQueryLimit;



    // Show form; required for sorting and searching.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '" method="get" style="margin : 0px;">' . "\n" .
          '        <INPUT type="hidden" name="action" value="' . $_GET['action'] . '">' . "\n" .
          '        <INPUT type="hidden" name="order" value="' . implode(',', $aOrder) . '">');
    print('</FORM>' . "\n\n");

    $q = mysql_query($sQ);
    if (!$q) {
        lovd_dbFout('Genes' . ucfirst($_GET['action']), $sQ, mysql_error());
    }

    $n = (isset($nResults)? $nResults : $nTotal);
    print('      <SPAN class="S11">' . $n . ' entr' . ($n == 1? 'y' : 'ies') . '</SPAN><BR>' . "\n");

    // Array which will make up the table (header and data).
    $aTable =
             array(
                    'symbol' => array('Symbol', '70'),
                    'gene' => array('Gene', '*'),
                    'chrom_location' => array('Chrom.', '70'),
                    'allow_download' => array('Download?', '70', 'align="center"'),
                    'refseq' => array('RefSeq?', '55', 'align="center"'),
                    'variants' => array('Variants', '70', 'align="right"'),
                    'created_date' => array('Created', '130'),
                    'updated_date' => array('Last updated', '130'),
                  );

    // Table.
    print('      <TABLE border="0" cellpadding="0" cellspacing="1" width="950" class="data">' . "\n" .
          '        <TR>');

    foreach ($aTable as $sField => $aCol) {
        print("\n" . '          <TH' . (!empty($aCol[2])? ' ' . $aCol[2] : '') . (is_numeric($aCol[1])? ' width="' . $aCol[1] . '"' : '') . (array_key_exists($sField, $aOrderList)? ' class="order' . ($aOrder[0] == $sField? 'ed' : '') . '" onclick="document.forms[0].order.value=\'' . $sField . ',' . ($aOrder[0] == $sField? ($aOrder[1] == 'ASC'? 'DESC' : 'ASC') : $aOrderList[$sField][1]) . '\';document.forms[0].submit();"' : '') . '>' .
              (array_key_exists($sField, $aOrderList)? "\n" .
                                                           '            <TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="S11">' . "\n" .
                                                           '              <TR>' . "\n" .
                                                           '                <TH>' . str_replace(' ', '&nbsp;', $aCol[0]) . '</TH>' . "\n" .
                                                           '                <TD align="right"><IMG src="gfx/order_arrow_desc' . ($aOrder[0] == $sField && $aOrder[1] == 'DESC'? '_sel' : '') . '.png" alt="Descending" title="Descending" width="13" height="6"><BR><IMG src="gfx/order_arrow_asc' . ($aOrder[0] == $sField && $aOrder[1] == 'ASC'? '_sel' : '') . '.png" alt="Ascending" title="Ascending" width="13" height="6"></TD></TR></TABLE>' : $aCol[0]) . '</TH>');
    }
    print('</TR>');

    while ($zData = mysql_fetch_assoc($q)) {
        print("\n" .
              '        <TR style="cursor : pointer; cursor : hand;" onmouseover="this.className = \'hover\';" onmouseout="this.className = \'\';" onclick="window.location.href = \'' . $_SERVER['PHP_SELF'] . '?action=view&amp;view=' . $zData['symbol'] . lovd_showSID(true, true) . '\';">');

        $zData['symbol']         = '<A href="' . $_SERVER['PHP_SELF'] . '?action=view&amp;view=' . $zData['symbol'] . '" class="data">' . $zData['symbol'] . '</A>';
        $zData['allow_download'] = '<IMG src="' . ROOT_PATH . 'gfx/mark_' . $zData['allow_download'] . '.png" alt="' . $zData['allow_download'] . '" width="11" height="11">';
        $zData['refseq']         = '<IMG src="' . ROOT_PATH . 'gfx/mark_' . ($zData['genbank']? 1 : 0) . '.png" alt="Has ' . ($zData['genbank']? 'a' : 'no') . ' GenBank Reference sequence" title="Has ' . ($zData['genbank']? 'a' : 'no') . ' GenBank Reference sequence" width="11" height="11">&nbsp;&nbsp;&nbsp;' .
                                   '<IMG src="' . ROOT_PATH . 'gfx/mark_' . ($zData['refseq'] != ''? 1 : 0) . '.png" alt="Has ' . ($zData['refseq'] != ''? 'a' : 'no') . ' human-readable reference sequence" title="Has ' . ($zData['refseq'] != ''? 'a' : 'no') . ' human-readable reference sequence" width="11" height="11">';

        foreach ($aTable as $sField => $aCol) {
            print("\n" . '          <TD' . (!empty($aCol[2])? ' ' . $aCol[2] : '') . (is_numeric($aCol[1])? ' width="' . $aCol[1] . '"' : '') . ($aOrder[0] == $sField? ' class="ordered"' : '') . '>' . (!$zData[$sField]? '-' : $zData[$sField]) . '</TD>');
        }
        print('</TR>');
    }
    print('</TABLE>' . "\n\n");

    // URL pagelink.
    $sPageLink = 'order=' . rawurlencode(implode(',', $aOrder));

    lovd_pagesplitShowNav($sPageLink);

    require ROOT_PATH . 'inc-bot.php';
    exit;





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
    list($nUnique) = mysql_fetch_row(mysql_query('SELECT COUNT(DISTINCT `Variant/DNA`) FROM ' . TABLEPREFIX . '_' . $zData['symbol'] . '_variants'));
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





} elseif ($_GET['action'] == 'find_hgnc') {
    // 2010-02-23; 2.0-25; Option added to use preset values taken from the HUGO Gene Nomenclature Committee (HGNC) website (www.genenames.org).
    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        if (empty($_POST['id_hgnc'])) {
            // user did not fill in a HGNC ID.
            header('Location: ' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=create' . lovd_showSID(true));
            exit;
        }

        if (!preg_match('/^\d+$/', $_POST['id_hgnc'])) {
            // User did not fill in a proper HGNC ID.
            lovd_errorAdd('Please fill in a valid value (digits only), or leave the textbox blank.');
        }

        if (!lovd_error()) {      
            // Search for values from the HGNC site to use as preset values.
            $sUrl = 'http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?col=gd_app_sym&col=gd_app_name&col=gd_pub_chrom_map&col=gd_pub_eg_id&col=md_mim_id&col=md_prot_id&status_opt=2&where=gd_hgnc_id%3D' . $_POST['id_hgnc'] . '&order_by=gd_app_sym_sort&limit=&format=text&submit=submit';
            $aHGNCValues = lovd_php_file($sUrl); // Headers are stored in the first element of the array, values in the second element of the array.

            if (empty($aHGNCValues[1]) || substr_count($aHGNCValues[1], 'symbol withdrawn')) {
                lovd_errorAdd('This number does not seem to be a valid HGNC ID!');
            } else {
                $aHGNCValues = explode("\t", $aHGNCValues[1]); // Separate the returned values.
                require ROOT_PATH . 'inc-top.php';
                print('      Successfully retrieved data from the HGNC website.<BR>' . "\n" .
                      '      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=create" method="post">' . "\n" .
                      '        <INPUT type="hidden" name="symbol" value="' . $aHGNCValues[0] . '">' . "\n" .
                      '        <INPUT type="hidden" name="gene" value="' . $aHGNCValues[1] . '">' . "\n" .
                      '        <INPUT type="hidden" name="chrom_location" value="' . $aHGNCValues[2] . '">' . "\n" .
                      '        <INPUT type="hidden" name="id_hgnc" value="' . $_POST['id_hgnc'] . '">' . "\n" .
                      '        <INPUT type="hidden" name="id_entrez" value="' . $aHGNCValues[3] . '">' . "\n" .
                      '        <INPUT type="hidden" name="id_omim_gene" value="' . $aHGNCValues[4] . '">' . "\n" .
                      '        <INPUT type="hidden" name="id_uniprot" value="' . $aHGNCValues[5] . '">' . "\n" .
                      '        <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                      '      </FORM>' . "\n\n" .
                      '      <SCRIPT type="text/javascript">' . "\n" .
                      '        <!--' . "\n" .
                      '        document.forms[0].submit();' . "\n" .
                      '        // -->' . "\n" .
                      '      </SCRIPT>' . "\n\n");
    
                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');

    if (!isset($_GET['sent'])) {
        print('      Please fill in the HGNC ID for the gene you wish to create. If you don\'t know the HGNC ID, leave the field blank.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;sent" method="post">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

    $_POST['id_hgnc'] = '';

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '100', ''),
                    array('HGNC ID', 'text', 'id_hgnc', 10),
                    array('', 'submit', 'Continue &raquo;'),
                  );
    $_MODULES->processForm('SetupGenesFindHGNC', $aForm);
    lovd_viewForm($aForm);

    print('</TABLE></FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;





} elseif ($_GET['action'] == 'create') {
    // Create new gene.

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheck =
                 array(
                        'gene' => 'Full gene name',
                        'symbol' => 'Official gene symbol',
                        'chrom_location' => 'Chromosome location',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd('Please fill in the \'' . $val . '\' field.');
            }
        }

        // 2010-06-28; 2.0-27; Updated gene symbol format check; allowed gene symbols were rejected. Relaxed the rules a bit.
        // This regexp is based on the list of guidelines taken from http://www.genenames.org/guidelines.html#2.%20Gene%20symbols
        // Exception: we are not allowing gene names with hyphens.
        // FIXME; LOVD 2 CANNOT WORK WITH HYPHENS IN TABLE NAMES...!!! I SHOULD QUOTE ALL QUERIES TO THOSE TABLES!
//        // Exception: we are not specifically checking which genes were allowed to contain hyphens, a difficult pattern, little advantage.
//        if ($_POST['symbol'] && (!preg_match('/^(C([XY0-9]|[0-9]{2})orf[0-9]+|[A-Z][A-Z0-9-]*)(_[A-Za-z0-9_-]+)?$/', $_POST['symbol']) || strlen($_POST['symbol']) > 12)) {
        if ($_POST['symbol'] && (!preg_match('/^(C([XY0-9]|[0-9]{2})orf[0-9]+|[A-Z][A-Z0-9]*)(_[A-Za-z0-9_]+)?$/', $_POST['symbol']) || strlen($_POST['symbol']) > 12)) {
            // Error in genesymbol.
            lovd_errorAdd('Incorrect gene symbol. This field can contain up to 12 characters. The offical gene symbol can only contain uppercase letters and numbers, it may be appended with an underscore followed by letters, numbers, hyphens and underscores.');
        }

        if (!lovd_error()) {
            if (in_array($_POST['symbol'], lovd_getGeneList())) {
                lovd_errorAdd('There is already a gene in place with symbol ' . $_POST['symbol'] . '.');
            }
        }

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
            // FIXME; maybe before these checks, check if the field is empty but $_POST['genbank_uri'] contains useful information... then copy, in stead of complain.
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

        // Numeric fields.
        $aCheck =
                 array(
                        'id_hgnc' => 'HGNC ID',
                        'id_entrez' => 'Entrez Gene (Locuslink) ID',
                        'id_omim_gene' => 'OMIM Gene ID',
                        'header_align' => 'Header aligned to',
                        'footer_align' => 'Footer aligned to',
                      );

        foreach ($aCheck as $key => $val) {
            if ($_POST[$key] && !is_numeric($_POST[$key])) {
                lovd_errorAdd('The \'' . $val . '\' field has to contain a numeric value.');
            }
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

        // OMIM disease ID list.
        if ($_POST['id_omim_disease']) {
            // OMIM Disease ID's.
            $aOMIM = explode("\r\n", $_POST['id_omim_disease']);
            foreach ($aOMIM as $n => $sOMIM) {
                if (!preg_match('/^[0-9]{1,6} [^<>]+$/', $sOMIM)) {
                    lovd_errorAdd('OMIM Disease ID #' . ($n + 1) . ' (' . htmlspecialchars($sOMIM) . ') not understood.');
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
                @mysql_query('BEGIN WORK');
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
                    @mysql_query('ROLLBACK');
                } else {
                    @mysql_query('DELETE FROM ' . TABLE_DBS . ' WHERE symbol = "' . $_POST['symbol'] . '"');
                }
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_genes_create', 'LOVD Setup - Create new gene');
                lovd_dbFout('GeneCreate_B', $sQ, $sError);
            }

            // Commit, since a CREATE TABLE will commit either way (MySQL 5.0, too?).
            if ($_INI['database']['engine'] == 'InnoDB') {
                @mysql_query('COMMIT');
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
    lovd_includeJS(ROOT_PATH . 'inc-js-submit_geneform.php');

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
                    $qOrphaned = mysql_query('SELECT p.patientid FROM ' . TABLE_PATIENTS . ' AS p LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (patientid) WHERE p2v.symbol IS NULL');
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
