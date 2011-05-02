<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2011-04-29
 * For LOVD    : 3.0-pre-20
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// Require manager clearance.
//lovd_requireAUTH(LEVEL_MANAGER);




if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /transcripts
    // View all entries.

    define('PAGE_TITLE', 'View transcripts');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\d+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /transcripts/00001
    // View specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, "0", STR_PAD_LEFT);
    define('PAGE_TITLE', 'View transcript #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="transcripts/' . $nID . '?edit">Edit transcript information</A>';
        $sNavigation .= ' | <A href="transcripts/' . $nID . '?delete">Delete transcript entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }
    $_GET['search_transcriptid'] = $nID;
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Variants', 'H4');
    require ROOT_PATH . 'class/object_variants.php';
    $_DATA = new LOVD_Variant();
    $_DATA->viewList(false, 'transcriptid');

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (ACTION == 'create') {
    // URL: /transcripts?create
    // Add a new transcript to a gene

    define('LOG_EVENT', 'TranscriptCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'class/REST2SOAP.php';
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();


    if (GET) {
        $qGenes = 'SELECT id, name, refseq_UD FROM ' . TABLE_GENES;
        $zGenes = lovd_queryDB($qGenes);
        while ($aGene = mysql_fetch_row($zGenes)) {
            $aGenes[$aGene[0]] = array('id' => $aGene[0], 'name' => $aGene[1], 'refseq_UD' => $aGene[2]);
        }
        $_POST['workID'] = lovd_generateRandomID();
        $_SESSION['work'][$_POST['workID']] = array(
                                                    'action' => '/transcripts' . (isset($_PATH_ELEMENTS[1])? '/' . $_PATH_ELEMENTS[1] : '') . '?create',
                                                    'step' => '1',
                                                    'values' => array(
                                                                        'genes' => $aGenes,
                                                                      ),
                                                   );
    }
    define('PAGE_TITLE', 'Add transcript to ' . (isset($_SESSION['work'][$_POST['workID']]['values']['gene'])? 'gene ' . $_SESSION['work'][$_POST['workID']]['values']['gene']['id'] : 'a gene'));
    
    
    
    
    
    if ($_SESSION['work'][$_POST['workID']]['step'] == '1') {
        $zData = $_SESSION['work'][$_POST['workID']]['values'];
        if (POST) {

            lovd_errorClean();

            if (!isset($zData['genes'][$_POST['geneSymbol']])) {
                lovd_errorAdd('geneSymbol', 'Please select a Gene out of the list below!');
            }
            
            if (!lovd_error()) {
                require ROOT_PATH . 'inc-top.php';
                require ROOT_PATH . 'class/progress_bar.php';
                require ROOT_PATH . 'inc-lib-genes.php';
                
                $sFormNextPage = '<FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" id="createTranscript" method="post">' . "\n" .
                                 '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                                 '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                                 '        </FORM>';
                
                $_BAR = new ProgressBar('', 'Collecting transcript information...', $sFormNextPage);

                define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
                require ROOT_PATH . 'inc-bot.php';

                // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
                flush();
                
                $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
                $_BAR->setMessage('Collecting all available transcripts...');
                $_BAR->setProgress(0);
                
                $aOutput = $_MutalyzerWS->moduleCall('getTranscriptsAndInfo', array('genomicReference' => $zData['genes'][$_POST['geneSymbol']]['refseq_UD'], 'geneName' => $_POST['geneSymbol']));
                if (!is_array($aOutput)) {
                    $_MutalyzerWS->soapError ('getTranscriptsAndInfo', array('genomicReference' => $zData['genes'][$_POST['geneSymbol']]['refseq_UD'], 'geneName' => $_POST['geneSymbol']), $aOutput);
                } else {
                    if (empty($aOutput)) {
                        $aTranscripts = array();
                    } else {
                        $aTranscriptsInfo = lovd_getElementFromArray('TranscriptInfo', $aOutput, '');
                        $aTranscripts = array();
                        $aTranscriptsName = array();
                        $aTranscriptsPositions = array();
                        $aTranscriptsProtein = array();
                        $qTranscriptsAdded = lovd_queryDB('SELECT GROUP_CONCAT(DISTINCT id_ncbi ORDER BY id_ncbi SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid="' . $_POST['geneSymbol'] . '"');
                        $aResultTranscriptsAdded = mysql_fetch_row($qTranscriptsAdded);
                        $aTranscriptsAdded = explode(';', $aResultTranscriptsAdded[0]);
                        $nTranscripts = count($aTranscriptsInfo) - count($aTranscriptsAdded);
                        $nProgress = 0.0;
                        foreach($aTranscriptsInfo as $aTranscriptInfo) {
                            $aTranscriptInfo = $aTranscriptInfo['c'];
                            $aTranscriptValues = lovd_getAllValuesFromArray('', $aTranscriptInfo);
                            if (!in_array($aTranscriptValues['id'], $aTranscriptsAdded)) {
                                $nProgress = $nProgress + (100/$nTranscripts);
                                $_BAR->setMessage('Collecting ' . $aTranscriptValues['id'] . ' info...');
                                $aTranscripts[] = $aTranscriptValues['id'];
                                $aTranscriptsName[preg_replace('/\.\d+/', '', $aTranscriptValues['id'])] = str_replace($zData['genes'][$_POST['geneSymbol']]['name'] . ', ', '', $aTranscriptValues['product']);
                                $aTranscriptsPositions[$aTranscriptValues['id']] = array('gTransStart' => $aTranscriptValues['gTransStart'], 'gTransEnd' => $aTranscriptValues['gTransEnd'], 'cTransStart' => $aTranscriptValues['cTransStart'], 'cTransEnd' => $aTranscriptValues['sortableTransEnd'], 'cCDSStop' => $aTranscriptValues['cCDSStop']);
                                $aTranscriptsProtein[$aTranscriptValues['id']] = lovd_getElementFromArray('proteinTranscript/id', $aTranscriptInfo, 'v');
                                $_BAR->setProgress(0 + $nProgress);
                            }
                        }
                    }
                }
                $_SESSION['work'][$_POST['workID']]['step'] = '2';
                $_SESSION['work'][$_POST['workID']]['values'] = array(
                                                                  'gene' => $zData['genes'][$_POST['geneSymbol']], 
                                                                  'transcripts' => $aTranscripts,
                                                                  'transcriptsProtein' => $aTranscriptsProtein,
                                                                  'transcriptNames' => $aTranscriptsName,
                                                                  'transcriptPositions' => $aTranscriptsPositions,
                                                                  'transcriptsAdded' => $aTranscriptsAdded
                                                                );
                unset($_SESSION['work'][$_POST['workID']]['values']['genes']);
                $_BAR->setProgress(100);
                $_BAR->setMessage('Information collected, now building form...');
                $_BAR->setMessageVisibility('done', true);

                print('<SCRIPT type="text/javascript">' . "\n" .
                      '  document.forms[\'createTranscript\'].submit();' . "\n" .
                      '</SCRIPT>' . "\n\n");
            
                lovd_checkXSS();
            
                print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
                exit;
            }
        }
        
        
        
        if (isset($_PATH_ELEMENTS[1]) && preg_match('/^\w+$/', $_PATH_ELEMENTS[1])) {
            // URL: /transcripts/DMD?create
            // Add a new transcript to the specified gene symbol

            require ROOT_PATH . 'inc-top.php';
            $sGeneID = $_PATH_ELEMENTS[1];

            lovd_printHeader(PAGE_TITLE);

            print('<FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" id="selectedGene" method="post">' . "\n" .
                  '    <TABLE border="0" cellpadding="0" cellspacing="1" width="760">'. "\n" .
                  '        <INPUT type="hidden" name="geneSymbol" value="' . $sGeneID . '">' . "\n" .
                  '        <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                  '    </TABLE>' . "\n" .
                  '</FORM>' . "\n\n" .
                  '<SCRIPT type="text/javascript">' . "\n" .
                  '  document.forms[\'selectedGene\'].submit();' . "\n" .
                  '</SCRIPT>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;
        }

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        if (GET) {
            print('      Please select the gene on which you wish to add a transcript.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

        $aSelectGene = array();
        $aGenes = $_SESSION['work'][$_POST['workID']]['values']['genes'];
        foreach ($aGenes as $key => $val) {
            $aSelectGene[$key] = $val['name'] . ' (' . $val['id'] . ')';
        }
        // Array which will make up the form table.
        $aFormData = array(
                            array('POST', '', '', '', '30%', '14', '70%'),
                            array('Gene', '', 'select', 'geneSymbol', 1, $aSelectGene, false, false, false),
                            array('', '', 'submit', 'Continue &raquo;'),
                          );
        lovd_viewForm($aFormData);
        print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
        print('</TABLE></FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }
    
    
    
    
    
    if ($_SESSION['work'][$_POST['workID']]['step'] == '2') {
        $zData = $_SESSION['work'][$_POST['workID']]['values'];
        if (count($_POST) > 1) {
            lovd_errorClean();
            
            $_DATA->checkFields($_POST);
            
            if (!lovd_error()) {
                
                $_POST['created_by'] = $_AUTH['id'];
                $_POST['created_date'] = date('Y-m-d H:i:s');
                
                if (!empty($_POST['active_transcripts']) && $_POST['active_transcripts'][0] != 'None') {
                    foreach($_POST['active_transcripts'] as $sTranscript) {
                        // Add transcript to gene
                        $sTranscriptProtein = (isset($zData['transcriptsProtein'][$sTranscript])? $zData['transcriptsProtein'][$sTranscript] : '');
                        $sTranscriptName = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)];
                        $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                        $q = lovd_queryDB('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)', array($zData['gene']['id'], $sTranscriptName, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['gTransStart'], $aTranscriptPositions['gTransEnd'], $_POST['created_by']));
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - could not be added to gene ' . $zData['gene']['id']);
                        } else {
                            $aSuccessTranscripts[] = $sTranscript;
                        }
                    }
                    if (count($aSuccessTranscripts)) {
                        lovd_writeLog('Event', LOG_EVENT, 'Transcript information entries successfully added to gene ' . $zData['gene']['id'] . ' - ' . $zData['gene']['name']);
                    }
                }

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'genes/' . $zData['gene']['id']);

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('Successfully added the transcript(s) to gene ' . $zData['gene']['id'], 'success');

                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
        }

        require ROOT_PATH . 'inc-top.php';

        lovd_printHeader(PAGE_TITLE);

        if (!lovd_error()) {
            print('      To add the selected transcripts to the gene, please press "Add" at the bottom of the form..<BR>' . "\n" .
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
                            array('', '', 'submit', 'Add transcript(s) to gene'),
                          ));
        lovd_viewForm($aForm);

        print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
        print('</FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\d+$/', $_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /transcripts/00001?edit
    // Edit a transcript
    
    $nID = str_pad($_PATH_ELEMENTS[1], 5, "0", STR_PAD_LEFT);
    define('PAGE_TITLE', 'Edit transcript #' . $nID);
    define('LOG_EVENT', 'TranscriptEdit');
    
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
            
            //var_dump($nID);
            //var_dump($_POST);
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited transcript information entry #' . $nID . ' (' . $zData['geneid'] . ')');

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
    }

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (!lovd_error()) {
        print('      To edit this transcript, please complete the form below and press "Edit" at the bottom of the form..<BR>' . "\n" .
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
                        array('', '', 'submit', 'Edit transcript information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\d+$/', $_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /transcripts/00001?delete
    // Drop specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 5, "0", STR_PAD_LEFT);
    define('PAGE_TITLE', 'Delete transcript information entry #' . $nID);
    define('LOG_EVENT', 'TranscriptDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

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
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in variants.
            // FIXME; implement deleteEntry()
            $sSQL = 'DELETE FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?';
            $aSQL = array($nID);
            $q = lovd_queryDB($sSQL, $aSQL, true);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted transcript information entry ' . $nID . ' - ' . $zData['geneid'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'transcripts');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the transcript information entry!', 'success');

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
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Deleting transcript information entry', '', 'print', $nID . ' - ' . $zData['name'] . ' (' . $zData['geneid'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete transcript information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
