<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2011-11-16
 * For LOVD    : 3.0-alpha-06
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





if (!ACTION && (empty($_PATH_ELEMENTS[1]) || preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PATH_ELEMENTS[1])))) {
    // URL: /transcripts
    // URL: /transcripts/DMD
    // View all entries.

    if (empty($_PATH_ELEMENTS[1])) {
        $sGene = '';
    } else {
        $sGene = rawurldecode($_PATH_ELEMENTS[1]);
        $_GET['search_geneid'] = $sGene;
    }
    define('PAGE_TITLE', 'View transcripts' . ($sGene? ' of gene ' . $sGene : ''));
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->sSortDefault = ($sGene? 'variants' : 'geneid');
    $_DATA->viewList(false, ($sGene? 'geneid' : ''));

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /transcripts/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'View transcript #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Load appropiate user level for this transcript.
    lovd_isAuthorized('transcript', $nID); // This call will make database queries if necessary.

    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
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
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = new LOVD_TranscriptVariant($zData['geneid']);
    $_DATA->viewList(false, array('id', 'transcriptid'));

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (ACTION == 'create') {
    // URL: /transcripts?create
    // URL: /transcripts/DMD?create
    // Add a new transcript to a gene

    define('LOG_EVENT', 'TranscriptCreate');

    // If no gene given, ask for it and forward user.
    if (!isset($_PATH_ELEMENTS[1])) {
        define('PAGE_TITLE', 'Add transcript to a gene');

        // Is user authorized in any gene?
        lovd_isAuthorized('gene', $_AUTH['curates']);
        lovd_requireAUTH(LEVEL_CURATOR);

        require ROOT_PATH . 'inc-top.php';
        require ROOT_PATH . 'inc-lib-form.php';
        lovd_printHeader(PAGE_TITLE);

        print('      Please select the gene on which you wish to add a transcript.<BR>' . "\n" .
              '      <BR>' . "\n\n" .
              '      <FORM name="transcriptsCreate" method="post" onsubmit="window.location = \'' . $_PATH_ELEMENTS[0] . '/\' + this.geneSymbol.options[this.geneSymbol.selectedIndex].value + \'?' . ACTION . '\'; return false;">' . "\n" .
              '        <TABLE border="0" cellpadding="0" cellspacing="1" width="760">');

        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $sSQL = 'SELECT id, name FROM ' . TABLE_GENES . ' ORDER BY id';
            $aSQL = array();
        } else {
            $sSQL = 'SELECT g.id, g.name FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_CURATES . ' AS cu ON (cu.geneid = g.id) WHERE cu.userid = ? AND allow_edit = 1 ORDER BY g.id';
            $aSQL = array($_AUTH['id']);
        }

        $aSelectGene = array();
        $qGenes = lovd_queryDB_Old($sSQL, $aSQL);
        while ($zGene = mysql_fetch_assoc($qGenes)) {
            $aSelectGene[$zGene['id']] = $zGene['id'] . ' (' . $zGene['name'] . ')';
        }

        // Array which will make up the form table.
        $aFormData = array(
                            array('POST', '', '', '', '20%', '14', '80%'),
                            array('Gene', '', 'select', 'geneSymbol', 1, $aSelectGene, false, false, false),
                            array('', '', 'submit', 'Continue &raquo;'),
                          );
        lovd_viewForm($aFormData);
        print('</TABLE></FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }



    // Gene given, check validity.
    if (!in_array($_PATH_ELEMENTS[1], lovd_getGeneList())) {
        header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '?' . ACTION);

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_showInfoTable('Invalid gene symbol. Redirecting to gene selection...', 'warning');

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }



    // Is user authorized for the selected gene?
    lovd_isAuthorized('gene', $_PATH_ELEMENTS[1]);
    lovd_requireAUTH(LEVEL_CURATOR);





    // Form has not been submitted yet, build $_SESSION array with transcript data for this gene.
    if (!POST) {
        if (!isset($_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION])) {
            $_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION] = array();
        }

        while (count($_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION]) >= 5) {
            unset($_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION][min(array_keys($_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION]))]);
        }

        define('PAGE_TITLE', 'Add transcript to gene ' . $_PATH_ELEMENTS[1]);

        $zGene = mysql_fetch_assoc(lovd_queryDB_Old('SELECT id, name, refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PATH_ELEMENTS[1])));

        require ROOT_PATH . 'inc-top.php';
        require ROOT_PATH . 'class/progress_bar.php';
        require ROOT_PATH . 'inc-lib-genes.php';
        require ROOT_PATH . 'inc-lib-form.php';

        // Generate a unique workID, that is sortable.
        $nTime = gettimeofday();
        $_POST['workID'] = $nTime['sec'] . $nTime['usec'];

        $sFormNextPage = '<FORM action="' . CURRENT_PATH . '?' . ACTION . '" id="createTranscript" method="post">' . "\n" .
                         '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                         '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                         '        </FORM>';

        $_BAR = new ProgressBar('', 'Collecting transcript information...', $sFormNextPage);

        define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
        require ROOT_PATH . 'inc-bot.php';

        // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
        flush();

        require ROOT_PATH . 'class/REST2SOAP.php';
        $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
        $_BAR->setMessage('Collecting all available transcripts...');
        $_BAR->setProgress(0);

        $aOutput = $_MutalyzerWS->moduleCall('getTranscriptsAndInfo', array('genomicReference' => $zGene['refseq_UD'], 'geneName' => $zGene['id']));
        if (!is_array($aOutput)) {
            $_MutalyzerWS->soapError ('getTranscriptsAndInfo', array('genomicReference' => $zGene['refseq_UD'], 'geneName' => $zGene['id']), $aOutput);
        } else {
            if (empty($aOutput)) {
                $aTranscripts = array();
            } else {
                $aTranscriptsInfo = lovd_getElementFromArray('TranscriptInfo', $aOutput, '');
                $aTranscripts = array();
                $aTranscriptsName = array();
                $aTranscriptsPositions = array();
                $aTranscriptsProtein = array();
                list($aResultTranscriptsAdded) = mysql_fetch_row(lovd_queryDB_Old('SELECT GROUP_CONCAT(DISTINCT id_ncbi ORDER BY id_ncbi SEPARATOR ";") FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid="' . $zGene['id'] . '"'));
                $aTranscriptsAdded = explode(';', $aResultTranscriptsAdded);
                $nTranscripts = 0;
                foreach ($aTranscriptsInfo as $aTranscript) {
                    if (in_array($aTranscript['c']['id'][0]['v'], $aTranscriptsAdded)) {
                        $nTranscripts += 1;
                    }
                }
                $nProgress = 0.0;
                foreach($aTranscriptsInfo as $aTranscriptInfo) {
                    $aTranscriptInfo = $aTranscriptInfo['c'];
                    $aTranscriptValues = lovd_getAllValuesFromArray('', $aTranscriptInfo);
                    if (!in_array($aTranscriptValues['id'], $aTranscriptsAdded)) {
                        $nProgress = $nProgress + (100/$nTranscripts);
                        $_BAR->setMessage('Collecting ' . $aTranscriptValues['id'] . ' info...');
                        $aTranscripts[] = $aTranscriptValues['id'];
                        $aTranscriptsName[preg_replace('/\.\d+/', '', $aTranscriptValues['id'])] = str_replace($zGene['name'] . ', ', '', $aTranscriptValues['product']);
                        $aTranscriptsPositions[$aTranscriptValues['id']] = array('gTransStart' => $aTranscriptValues['gTransStart'], 'gTransEnd' => $aTranscriptValues['gTransEnd'], 'cTransStart' => $aTranscriptValues['cTransStart'], 'cTransEnd' => $aTranscriptValues['sortableTransEnd'], 'cCDSStop' => $aTranscriptValues['cCDSStop']);
                        $aTranscriptsProtein[$aTranscriptValues['id']] = lovd_getElementFromArray('proteinTranscript/id', $aTranscriptInfo, 'v');
                        $_BAR->setProgress(0 + $nProgress);
                    }
                }
            }
        }

        $_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION][$_POST['workID']]['values'] = array(
                                                          'gene' => $zGene, 
                                                          'transcripts' => $aTranscripts,
                                                          'transcriptsProtein' => $aTranscriptsProtein,
                                                          'transcriptNames' => $aTranscriptsName,
                                                          'transcriptPositions' => $aTranscriptsPositions,
                                                          'transcriptsAdded' => $aTranscriptsAdded
                                                        );
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
    if (!isset($_POST['workID']) || !array_key_exists($_POST['workID'], $_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION])) {
        exit;
    }





    require ROOT_PATH . 'inc-lib-form.php';
    // FIXME; $aData would have been a better name.
    $zData = $_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION][$_POST['workID']]['values'];
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
                    $sTranscriptName = $zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)];
                    $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                    $q = lovd_queryDB_Old('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)', array($zData['gene']['id'], $sTranscriptName, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['gTransStart'], $aTranscriptPositions['gTransEnd'], $_POST['created_by']));
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

            unset($_SESSION['work'][$_PATH_ELEMENTS[0] . '?' . ACTION][$_POST['workID']]);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'genes/' . rawurlencode($zData['gene']['id']));

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
        print('      To add the selected transcripts to the gene, please press "Add" at the bottom of the form.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // FIXME; perhaps just better, when there are no (more) transcripts available, to put a message there and not the entire form anyway?
    $atranscriptNames = array();
    $aTranscriptsForm = array();
    if (!empty($zData['transcripts'])) {
        foreach ($zData['transcripts'] as $sTranscript) {
            if (!isset($aTranscriptNames[preg_replace('/\.\d+/', '', $sTranscript)])) {
                $aTranscriptsForm[$sTranscript] = lovd_shortenString($zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)], 50);
                $aTranscriptsForm[$sTranscript] .= str_repeat(')', substr_count($aTranscriptsForm[$sTranscript], '(')) . ' (' . $sTranscript . ')';
            }
        }
        asort($aTranscriptsForm);
    } else {
        $aTranscriptsForm = array('' => 'No ' . (count($zData['transcriptsAdded'])? 'more ' : '') . 'transcripts available');
    }
    
    $nTranscriptsFormSize = (count($aTranscriptsForm) < 10? count($aTranscriptsForm) : 10);

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Transcriptomic reference sequence(s)', '', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, true),
                        array('', '', 'note', 'Select transcript references (NM accession numbers). Please note that transcripts already added to this gene database, are not shown. You can select multiple transcripts by holding "Ctrl" on a PC or "Command" on a Mac and clicking all wanted transcripts.'),
                        array('', '', 'submit', 'Add transcript(s) to gene'),
                      ));
    lovd_viewForm($aForm);

    print('<INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");
    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /transcripts/00001?edit
    // Edit a transcript

    $nID = sprintf('%05d', $_PATH_ELEMENTS[1]);
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
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the gene information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
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





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /transcripts/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PATH_ELEMENTS[1]);
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
