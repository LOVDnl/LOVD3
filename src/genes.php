<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2016-10-14
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
 *               David Baux <david.baux@inserm.fr>
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



function lovd_prepareCuratorLogMessage($sGeneID, $aCurators, $aAllowEdit, $aShown)
{
    // Creates a log message showing main differences between current database
    // status and curator privileges given as parameters ($aCurators,
    // $aAllowEdit, $aShown).
    // Parameters:
    //     $sGeneID: Gene ID.
    //     $aCurators: array of curator IDs.
    //     $aAllowEdit: array of curator IDs with edit privileges.
    //     $aShown: array of curator IDs in order as shown on gene page.
    global $_DB;

    $sLogMessage = 'Updated curator list for the ' . $sGeneID . ' gene:' . "\n";

    // Generate SQL condition for curator ID. This condition is needed to select
    // users that are currently not associated with the gene.
    $sSQLUserWhereCondition = '';
    if (count($aCurators) > 0) {
        $sSQLUserWhereCondition = 'u.id IN (?' . str_repeat(', ?', count($aCurators) - 1) . ') OR';
    }

    // Get all curators (past and new) from database.
    $qUser = $_DB->query('SELECT u.id, u.name, u2g.allow_edit, u2g.show_order FROM ' .
        TABLE_USERS . ' AS u LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid ' .
        'AND u2g.geneid = ?) WHERE ' . $sSQLUserWhereCondition . ' u2g.geneid IS NOT NULL',
        array_merge(array($sGeneID), $aCurators));
    $aUserResult = $qUser->fetchAllAssoc();
    $zUsers = array();
    foreach ($aUserResult as $zResult) {
        $zUsers[$zResult['id']] = $zResult;
    }

    foreach ($zUsers as $nUserID => $zUser) {
        // Compare status of current privileges with those about to be submitted.

        if (!in_array($nUserID, $aCurators)) {
            $sLogMessage .= 'Removed user #' . $nUserID . ' (' . $zUser['name'] . ').' . "\n";
            continue;
        }

        if (is_null($zUser['allow_edit']) && is_null($zUser['show_order'])) {
            $sLogMessage .= 'Added user #' . $nUserID . ' (' . $zUser['name'] . ').' . "\n";
            continue;
        }

        if ($zUser['show_order'] == '0' && in_array($nUserID, $aShown)) {
            $sLogMessage .= 'Displayed user #' . $nUserID . ' (' . $zUser['name'] . ').' . "\n";

        } elseif ($zUser['show_order'] != '0' && !in_array($nUserID, $aShown)) {
            $sLogMessage .= 'Hid user #' . $nUserID . ' (' . $zUser['name'] . ').' . "\n";
        }

        if ($zUser['allow_edit'] == '0' && in_array($nUserID, $aAllowEdit)) {
            $sLogMessage .= 'Given edit privileges to user #' . $nUserID . ' (' .
                            $zUser['name'] . ').' . "\n";

        } elseif ($zUser['allow_edit'] == '1' && !in_array($nUserID, $aAllowEdit)) {
            $sLogMessage .= 'Retracted edit privileges from user #' . $nUserID . ' (' .
                            $zUser['name'] . ').' . "\n";
        }
    }

    // Format new order of curators with IDs and names.
    $sLogMessage .= 'Order is now: ';
    $aCuratorDisplaysShown = array();
    $aCuratorDisplaysHidden = array();

    foreach ($aCurators as $sCuratorID) {
        if (isset($zUsers[$sCuratorID])) {
            $sCuratorDisplay = 'user #' . $sCuratorID . ' (' . $zUsers[$sCuratorID]['name'] . ')';

            if (in_array($sCuratorID, $aShown)) {
                $aCuratorDisplaysShown[] = $sCuratorDisplay;
            } else {
                $aCuratorDisplaysHidden[] = $sCuratorDisplay;
            }
        }
    }

    $sLogMessage .= join(', ', $aCuratorDisplaysShown);
    if (count($aCuratorDisplaysHidden) > 0) {
        // Hidden curators are separate, their order may be off as it is implicit.
        $sLogMessage .= ', ' . join(', ', $aCuratorDisplaysHidden);
    }
    return $sLogMessage;
}



if (PATH_COUNT == 1 && !ACTION) {
    // URL: /genes
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View all genes');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $_DATA->viewList('Genes', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && !ACTION) {
    // URL: /genes/DMD
    // View specific entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'View ' . $sID . ' gene homepage');
    $_T->printHeader();
    $_T->printTitle();
    lovd_printGeneHeader();

    // Load appropriate user level for this gene.
    lovd_isAuthorized('gene', $sID);

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->viewEntry($sID);
    // 2015-07-22; 3.0-14; Drop usage of CURRENT_PATH in favor of fixed $sID which may have a gene symbol with incorrect case.
    // Now fix possible issues with capitalization. inc-init.php does this for $_SESSION['currdb'], but we're using $sID.
    $sID = $zData['id'];

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[$_PE[0] . '/' . $sID . '?edit']             = array('menu_edit.png', 'Edit gene information', 1);
        $aNavigation['transcripts/' . $sID . '?create']  = array('menu_plus.png', 'Add transcript(s) to gene', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation[$_PE[0] . '/' . $sID . '?delete']       = array('cross.png', 'Delete gene entry', 1);
            $aNavigation[$_PE[0] . '/' . $sID . '?authorize']    = array('', 'Add/remove curators/collaborators', 1);
        } else {
            $aNavigation[$_PE[0] . '/' . $sID . '?sortCurators'] = array('', 'Sort/hide curator names', 1);
        }
        $aNavigation[$_PE[0] . '/' . $sID . '?empty']            = array('menu_empty.png', 'Empty this gene database', (bool) ($zData['variants']));
        $aNavigation[$_PE[0] . '/' . $sID . '/graphs']           = array('menu_graphs.png', 'View graphs about this gene database', 1);
        $aNavigation[$_PE[0] . '/' . $sID . '/columns']          = array('menu_columns.png', 'View enabled variant columns', 1);
        $aNavigation[$_PE[0] . '/' . $sID . '/columns?order']    = array('menu_columns.png', 'Re-order enabled variant columns', 1);
        $aNavigation['columns/VariantOnTranscript']      = array('menu_columns.png', 'View all available variant columns', 1);
        $aNavigation['download/all/gene/' . $sID]        = array('menu_save.png', 'Download all this gene\'s data', 1);
        $aNavigation['javascript:lovd_openWindow(\'' . lovd_getInstallURL() . 'scripts/refseq_parser.php?step=1&amp;symbol=' . $sID . '\', \'refseq_parser\', 900, 500);'] = array('menu_scripts.png', 'Create human-readable refseq file', ($zData['refseq_UD'] && count($zData['transcripts'])));
    }
    lovd_showJGNavigation($aNavigation, 'Genes');

    $_GET['search_geneid'] = '="' . $sID . '"';
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Active transcripts', 'H4');
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->setSortDefault('variants');
    $_DATA->viewList('Transcripts_for_G_VE', 'geneid', true, true);

    // Disclaimer.
    if ($zData['disclaimer']) {
        print('<BR>' . "\n\n" .
              '      <TABLE border="0" cellpadding="0" cellspacing="1" width="950" class="data">' . "\n" .
              '        <TR>' . "\n" .
              '          <TH class="S15">Copyright &amp; disclaimer</TH></TR>' . "\n" .
              '        <TR class="S11">' . "\n" .
              '          <TD>' . $zData['disclaimer_text_'] . '</TD></TR></TABLE><BR>' . "\n\n");
    }

    lovd_printGeneFooter();
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
    // FIXME: This is just to use two functions of the object that don't actually use the object. Better put them elsewhere.
    require ROOT_PATH . 'class/object_transcripts.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA['Genes'] = new LOVD_Gene();
    // FIXME: This is just to use two functions of the object that don't actually use the object. Better put them elsewhere.
    $_DATA['Transcript'] = new LOVD_transcript();

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
        require ROOT_PATH . 'inc-lib-genes.php';
        if (POST) {
            lovd_errorClean();

            if (empty($_POST['hgnc_id'])) {
                lovd_errorAdd('hgnc_id', 'No HGNC ID or Gene symbol was specified');

            } else {
                // Gene Symbol must be unique.
                // Enforced in the table, but we want to handle this gracefully.
                // When numeric, we search the id_hgnc field. When not, we search the id (gene symbol) field.
                $sSQL = 'SELECT id, id_hgnc FROM ' . TABLE_GENES . ' WHERE id' . (!ctype_digit($_POST['hgnc_id'])? '' : '_hgnc') . ' = ?';
                $aSQL = array($_POST['hgnc_id']);
                $result = $_DB->query($sSQL, $aSQL)->fetchObject();

                if ($result !== false) {
                    lovd_errorAdd('hgnc_id', sprintf('This gene entry (%s, HGNC-ID=%d) is already present in this LOVD installation.', $result->id, $result->id_hgnc));
                } else {
                    // This call already makes the needed lovd_errorAdd() calls.
                    $aGeneInfo = lovd_getGeneInfoFromHGNC($_POST['hgnc_id']);
                    if (!empty($aGeneInfo)) {
                        $sHgncID = $aGeneInfo['hgnc_id'];
                        $sSymbol = $aGeneInfo['symbol'];
                        $sGeneName = $aGeneInfo['name'];
                        $sChromLocation = $aGeneInfo['location'];
                        $sEntrez = $aGeneInfo['entrez_id'];
                        $nOmim = $aGeneInfo['omim_id'];
                    }
                }
            }

            if (!lovd_error()) {
                $_T->printHeader();
                $_T->printTitle();
                require ROOT_PATH . 'class/progress_bar.php';

                $sFormNextPage = '<FORM action="' . $sPath . '" id="createGene" method="post">' . "\n" .
                                 '          <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n" .
                                 '          <INPUT type="submit" value="Continue &raquo;">' . "\n" .
                                 '        </FORM>';

                $_BAR = new ProgressBar('', 'Collecting gene information...', $sFormNextPage);
                $nProgress = 0.0;

                $_T->printFooter(false);

                // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
                flush();

                require ROOT_PATH . 'class/soap_client.php';
                $_Mutalyzer = new LOVD_SoapClient();

                // Get LRG if it exists
                $aRefseqGenomic = array();
                $_BAR->setMessage('Checking for LRG...');
                if ($sLRG = lovd_getLRGbyGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sLRG;
                }

                // Get NG if it exists
                $_BAR->setMessage('Checking for NG...');
                $_BAR->setProgress($nProgress += 16);
                if ($sNG = lovd_getNGbyGeneSymbol($sSymbol)) {
                    $aRefseqGenomic[] = $sNG;
                }

                // Get NC from LOVD
                $_BAR->setMessage('Checking for NC...');
                $_BAR->setProgress($nProgress += 17);

                if ($sChromLocation == 'mitochondria') {
                    $sChromosome = 'M';
                    $sChromBand = '';
                } else {
                    preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches);
                    $sChromosome = $aMatches[1];
                    $sChromBand = $aMatches[2];
                }
                $aRefseqGenomic[] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$sChromosome];

                $_BAR->setMessage('Making a gene slice of the NC...');
                $_BAR->setProgress($nProgress += 16);
                // 2014-05-23; 3.0-11; Don't bother trying to get an UD for a mitochondrial gene, the NCBI uses different names and you will never get it...
                if ($sChromosome == 'M') {
                    // Instead of the UD, we just use the NC, it's small enough.
                    $sRefseqUD = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$sChromosome];
                } else {
                    // Get UD from mutalyzer.
                    try {
                        $sRefseqUD = lovd_getUDForGene($_CONF['refseq_build'], $sSymbol);
                        if ($sRefseqUD === '') {
                            // Function may return an empty string. This is not a SOAP error, but still an error. For instance a type of gene we don't support.
                            // To prevent further problems (getting transcripts, let's handle this nicely, shall we?
                            $_BAR->setMessage('Failed to retrieve gene reference sequence. This could be a temporary error, but it is likely that this gene is not supported by LOVD.', 'done');
                            $_BAR->setMessageVisibility('done', true);
                            die('</BODY>' . "\n" .
                                '</HTML>' . "\n");
                        }
                    } catch (SoapFault $e) {
                        lovd_soapError($e);
                    }
                }

                // Get all transcripts and info.
                // FIXME: When changing code here, check in transcripts?create if you need to make changes there, too.
                $_BAR->setMessage('Collecting all available transcripts...');
                $_BAR->setProgress($nProgress += 17);

                $aTranscripts = $_DATA['Transcript']->getTranscriptPositions($sRefseqUD, $sSymbol, $sGeneName, $nProgress);

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
                                                                  'id_omim' => $nOmim,
                                                                  'genomic_references' => $aRefseqGenomic,
                                                                  'refseq_UD' => $sRefseqUD,
                                                                );
                if (!empty($aTranscripts)) {
                    $_SESSION['work'][$sPath][$_POST['workID']]['values'] = array_merge($_SESSION['work'][$sPath][$_POST['workID']]['values'], array(
                                                                  'transcripts' => $aTranscripts['id'],
                                                                  'transcriptMutalyzer' => $aTranscripts['mutalyzer'],
                                                                  'transcriptsProtein' => $aTranscripts['protein'],
                                                                  'transcriptNames' => $aTranscripts['name'],
                                                                  'transcriptPositions' => $aTranscripts['positions'],
                                                                ));
                }

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

        print('      <FORM action="' . $sPath . '" method="post">' . "\n" .
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
        require ROOT_PATH . 'inc-lib-actions.php';
        $zData = $_SESSION['work'][$sPath][$_POST['workID']]['values'];
        if (count($_POST) > 1) {
            lovd_errorClean();

            $_DATA['Genes']->checkFields($_POST, $zData);

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

                $_DATA['Genes']->insertEntry($_POST, $aFields);

                // Add the default custom columns to this gene.
                lovd_addAllDefaultCustomColumns('gene', $_POST['id']);

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
                        // 2014-06-11; 3.0-11; Add check on $sTranscript to make sure a selected "No transcripts found" doesn't cause a lot of errors here.
                        if (!$sTranscript) {
                            continue;
                        }
                        // FIXME; If else statement is temporary. Now we only use the transcript object to save the transcripts for mitochondrial genes.
                        // Later all transcripts will be saved like this. For the sake of clarity this will be done in a separate commit.
                        if ($zData['chromosome'] == 'M') {
                            $zDataTranscript = array(
                                'geneid' => $_POST['id'],
                                'name' => $zData['transcriptNames'][$sTranscript],
                                'id_mutalyzer' => $zData['transcriptMutalyzer'][$sTranscript],
                                'id_ncbi' => $sTranscript,
                                'id_ensembl' => '',
                                'id_protein_ncbi' => $zData['transcriptsProtein'][$sTranscript],
                                'id_protein_ensembl' => '',
                                'id_protein_uniprot' => '',
                                'position_c_mrna_start' => $zData['transcriptPositions'][$sTranscript]['cTransStart'],
                                'position_c_mrna_end' => $zData['transcriptPositions'][$sTranscript]['cTransEnd'],
                                'position_c_cds_end' => $zData['transcriptPositions'][$sTranscript]['cCDSStop'],
                                'position_g_mrna_start' => $zData['transcriptPositions'][$sTranscript]['chromTransStart'],
                                'position_g_mrna_end' => $zData['transcriptPositions'][$sTranscript]['chromTransEnd'],
                                'created_date' => date('Y-m-d H:i:s'),
                                'created_by' => $_POST['created_by'],
                            );

                            if (!$_DATA['Transcript']->insertEntry($zDataTranscript, array_keys($zDataTranscript))) {
                                // Silent error.
                                lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - ' . ' - could not be added to gene ' . $_POST['id']);
                                continue;
                            }

                            $aSuccessTranscripts[] = $sTranscript;
                            $_DATA['Transcript']->turnOffMappingDone($_POST['chromosome'], $zData['transcriptPositions'][$sTranscript]);

                        } else {
                            // Gather transcript information from session.
                            // Until revision 679 the transcript version was not used in the index.
                            // Can not figure out why version is not included. Therefore, for now we will do without.
                            $nMutalyzerID = $zData['transcriptMutalyzer'][$sTranscript];
                            $sTranscriptProtein = $zData['transcriptsProtein'][$sTranscript];
                            $sTranscriptName = $zData['transcriptNames'][$sTranscript];
                            $aTranscriptPositions = $zData['transcriptPositions'][$sTranscript];
                            // Add transcript to gene.
                            $q = $_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '(id, geneid, name, id_mutalyzer, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) ' .
                                             'VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                                             array($_POST['id'], $sTranscriptName, $nMutalyzerID, $sTranscript, '', $sTranscriptProtein, '', '', $aTranscriptPositions['cTransStart'], $aTranscriptPositions['cTransEnd'], $aTranscriptPositions['cCDSStop'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $_POST['created_by']));
                            if (!$q) {
                                // Silent error.
                                lovd_writeLog('Error', LOG_EVENT, 'Transcript information entry ' . $sTranscript . ' - ' . ' - could not be added to gene ' . $_POST['id']);
                            } else {
                                $aSuccessTranscripts[] = $sTranscript;

                                // Turn off the MAPPING_DONE flags for variants within range of this transcript, so that automatic mapping will pick them up again.
                                $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = mapping_flags & ~' . MAPPING_DONE . ' WHERE chromosome = ? AND (' .
                                                 '(position_g_start BETWEEN ? AND ?) OR ' .
                                                 '(position_g_end   BETWEEN ? AND ?) OR ' .
                                                 '(position_g_start < ? AND position_g_end > ?))',
                                                 array($_POST['chromosome'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd'], $aTranscriptPositions['chromTransStart'], $aTranscriptPositions['chromTransEnd']));
                                if ($q->rowCount()) {
                                    // If we have changed variants, turn on mapping immediately.
                                    $_SESSION['mapping']['time_complete'] = 0;
                                }
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
                // If there is only one user, don't forward to the Add curators page.
                if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE id > 0')->fetchColumn() > 1) {
                    header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $_POST['id'] . '?authorize');
                } else {
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
            $_DATA['Genes']->setDefaultValues();
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
        print('      <FORM action="' . $sPath . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array_merge(
                     $_DATA['Genes']->getForm(),
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





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && ACTION == 'edit') {
    // URL: /genes/DMD?edit
    // Edit an entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Edit gene information entry');
    define('LOG_EVENT', 'GeneEdit');

    // Load appropriate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->loadEntry($sID);
    // 2015-07-22; 3.0-14; Drop usage of CURRENT_PATH in favor of fixed $sID which may have a gene symbol with incorrect case.
    // Now fix possible issues with capitalization. inc-init.php does this for $_SESSION['currdb'], but we're using $sID.
    $sID = $zData['id'];

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

    // This passes on the new list of genomic reference sequences to getForm(), which globals this variable.
    $zData['genomic_references'] = $_SESSION['work'][$sPath][$_POST['workID']]['values']['genomic_references'];
    if (count($_POST) > 1) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array(
                            'name', 'chrom_band', 'imprinting', 'refseq_genomic', 'reference', 'url_homepage', 'url_external', 'allow_download',
                            'allow_index_wiki', 'show_hgmd', 'show_genecards', 'show_genetests', 'note_index', 'note_listing', 'refseq',
                            'refseq_url', 'disclaimer', 'disclaimer_text', 'header', 'header_align', 'footer', 'footer_align', 'created_date',
                            'edited_by', 'edited_date',
                            );

            if (empty($zData['refseq_UD'])) {
                require ROOT_PATH . 'class/soap_client.php';
                $_Mutalyzer = new LOVD_SoapClient();
                try {
                    $sRefseqUD = lovd_getUDForGene($_CONF['refseq_build'], $sID);
                    $_POST['refseq_UD'] = $sRefseqUD;
                    $aFields[] = 'refseq_UD';
                } catch (SoapFault $e) {} // Silent error.
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
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $sID);

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
    print('      <FORM action="' . $_PE[0] . '/' . $sID . '?' . ACTION . '" method="post">' . "\n");

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





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && ACTION == 'empty') {
    // URL: /genes/DMD?empty
    // Empty the gene database (delete all variants and associated data).

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Empty ' . $sID . ' gene database');
    define('LOG_EVENT', 'GeneEmpty');
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    // If there are no variants, why continue?
    $nVariants = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?', array($sID))->fetchColumn();
    if (!$nVariants) {
        lovd_showInfoTable('There are already no variants in this gene database!', 'stop');
        $_T->printFooter();
        exit;
    }

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
            // Throwing away full submissions in one go is unfortunately not an option due to the many-to-many connection between screening and variant.
            // Deleting an individual does not delete its variants. Therefore, we might as well approach this the LOVD2 way: delete the variants, then delete the empty screenings, then the empty individuals.
            // Of course we MUST make sure those are at least entries related to the removed variants in question, otherwise we'll be deleting too much data (LOVD2 never allowed orphaned data like we do now).
            require ROOT_PATH . 'class/progress_bar.php';
            $_BAR = new ProgressBar('', 'Gathering data...');
            $aDone = array();
            $nDone = 0;

            $_DB->beginTransaction();
            // Determine which transcripts need their data deleted...
            // We must have transcripts and variants, otherwise we cannot get to this point.
            $aTranscripts = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ?', array($sID))->fetchAllColumn();
            // Then determine which VOGs need to be deleted, because they will point to nothing else...
            $aVOGs = $_DB->query('SELECT DISTINCT vog.id FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 ON (vog.id = vot1.id AND vot1.transcriptid IN (?' . str_repeat(', ?', count($aTranscripts) - 1) . ')) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 ON (vog.id = vot2.id AND vot2.transcriptid NOT IN (?' . str_repeat(', ?', count($aTranscripts) - 1) . ')) WHERE vot2.transcriptid IS NULL', array_merge($aTranscripts, $aTranscripts), true)->fetchAllColumn();
            $_BAR->setProgress(10);
            $_BAR->setMessage('Deleting variants...');

            // Delete the VOTs!
            $q = $_DB->query('DELETE FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE transcriptid IN (?' . str_repeat(', ?', count($aTranscripts) - 1) . ')', $aTranscripts);
            $aDone['Variants_On_Transcripts'] = $q->rowCount();
            $nDone ++;
            unset($aTranscripts); // Save some memory.
            $_BAR->setProgress(25);

            // Determine which screenings need to go, based on the VOGs...
            $aScreenings = $_DB->query('SELECT DISTINCT s2v1.screeningid FROM ' . TABLE_SCR2VAR . ' AS s2v1 LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v2 ON (s2v1.screeningid = s2v2.screeningid AND s2v2.variantid NOT IN (?' . str_repeat(', ?', count($aVOGs) - 1) . ')) WHERE s2v1.variantid IN (?' . str_repeat(', ?', count($aVOGs) - 1) . ') AND s2v2.variantid IS NULL', array_merge($aVOGs, $aVOGs), true)->fetchAllColumn();

            // Delete the VOGs!
            $q = $_DB->query('DELETE FROM ' . TABLE_VARIANTS . ' WHERE id IN (?' . str_repeat(', ?', count($aVOGs) - 1) . ')', $aVOGs);
            $aDone['Variants_On_Genome'] = $q->rowCount();
            $nDone ++;
            unset($aVOGs); // Save some memory.
            $_BAR->setProgress(40);

            if ($aScreenings) {
                // We could have had a gene with only variants and no individuals...
                $_BAR->setMessage('Deleting screenings...');

                // Determine which individuals need to go, based on the Screenings...
                $aIndividuals = $_DB->query('SELECT DISTINCT s1.individualid FROM ' . TABLE_SCREENINGS . ' AS s1 LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s2 ON (s1.individualid = s2.individualid AND s2.id NOT IN (?' . str_repeat(', ?', count($aScreenings) - 1) . ')) WHERE s1.id IN (?' . str_repeat(', ?', count($aScreenings) - 1) . ') AND s2.id IS NULL', array_merge($aScreenings, $aScreenings), true)->fetchAllColumn();

                // Delete the Screenings! (NOTE: I could now just drop the individuals and everything will cascade, but I want the statistics...)
                $q = $_DB->query('DELETE FROM ' . TABLE_SCREENINGS . ' WHERE id IN (?' . str_repeat(', ?', count($aScreenings) - 1) . ')', $aScreenings);
                $aDone['Screenings'] = $q->rowCount();
                $nDone ++;
                unset($aScreenings); // Save some memory.
                $_BAR->setProgress(60);
                $_BAR->setMessage('Deleting phenotypes...');

                if ($aIndividuals) {
                    // Delete the Phenotypes! (NOTE: Again, just because I want the statistics...)
                    $q = $_DB->query('DELETE FROM ' . TABLE_PHENOTYPES . ' WHERE individualid IN (?' . str_repeat(', ?', count($aIndividuals) - 1) . ')', $aIndividuals);
                    $aDone['Phenotypes'] = $q->rowCount();
                    $nDone ++;
                    $_BAR->setProgress(80);
                    $_BAR->setMessage('Deleting individuals...');

                    // And finally, delete the Individuals!
                    $q = $_DB->query('DELETE FROM ' . TABLE_INDIVIDUALS . ' WHERE id IN (?' . str_repeat(', ?', count($aIndividuals) - 1) . ')', $aIndividuals);
                    $aDone['Individuals'] = $q->rowCount();
                    $nDone ++;
                    unset($aIndividuals); // Save some memory.
                }
            }

            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');

            // All successful, now we can commit.
            $_DB->commit();
            $sMessage = '';
            if (count($aDone)) {
                foreach ($aDone as $sSection => $n) {
                    $sMessage .= (!$sMessage ? '' : ', ') . $n . ' ' . $sSection;
                }
                $sMessage = 'deleted ' . preg_replace('/, ([^,]+)/', " and $1", $sMessage);
            } else {
                $sMessage = 'no data to delete';
            }
            lovd_writeLog('Event', LOG_EVENT, 'Emptied gene database ' . $sID . '; ' . $sMessage . '; ran ' . $nDone . ' queries.');
            lovd_setUpdatedDate($sID); // FIXME; regardless of variant status... oh, well...

            // Thank the user...
            lovd_showInfoTable('Successfully emptied the ' . $sID . ' gene database!', 'success');
            $_BAR->redirectTo(lovd_getInstallURL() . 'configuration', 3);

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    lovd_errorPrint();

    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Emptying ' . $sID . ' gene database', 'All data associated to ' . $sID . ' will be deleted, as long as it\'s not associated with another gene. For instance, a variant that is mapped to ' . $sID . ' as well as another gene, will only lose the mapping to ' . $sID . '. A variant that is only described on ' . $sID . ' however, will be deleted. Also, an individual with a variant in ' . $sID . ', but also in another gene, will not be deleted, but the ' . $sID . ' variant data <I>will</I> be deleted. An individual that only has variants reported in ' . $sID . ' will be removed from the system.',
                            'print', 'Deleting ' . $nVariants . ' variant' . ($nVariants == 1? '' : 's') . ' and all associated data (screenings, individuals, phenotypes).<BR><B>All data (variants, screenings, individuals and phenotypes) only linked to ' . $sID . ' and not linked to any other gene will be deleted!</B>'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Empty gene database'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && ACTION == 'delete') {
    // URL: /genes/DMD?delete
    // Drop specific entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Delete gene information entry ' . $sID);
    define('LOG_EVENT', 'GeneDelete');

    // Require manager clearance.
    lovd_requireAUTH((LOVD_plus? LEVEL_ADMIN : LEVEL_MANAGER));

    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    $zData = $_DATA->loadEntry($sID);
    // 2015-07-22; 3.0-14; Drop usage of CURRENT_PATH in favor of fixed $sID which may have a gene symbol with incorrect case.
    // Now fix possible issues with capitalization. inc-init.php does this for $_SESSION['currdb'], but we're using $sID.
    $sID = $zData['id'];
    require ROOT_PATH . 'inc-lib-form.php';

    // Check whether user has submitted and confirmed the form/action.
    $bValidPassword = false;
    $bConfirmation = !empty($_GET['confirm']);
    if (POST) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');

        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');

        } else {
            $bValidPassword = true;
        }

        // Remove password from default values shown in confirmation form.
        unset($_POST['password']);
    }

    if ($bValidPassword && $bConfirmation) {
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
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('This will delete the ' . $zData['id'] . ' gene, all transcripts of this gene, and all annotations on variants specific for ' . $zData['id'] . '. The genomic variants and all individual-related information, including screenings, phenotypes and diseases, will not be deleted, so these might be left without a curator able to manage the data.<BR>
                        <B>If you also wish to remove all information on individuals with variants in ' . $zData['id'] . ', first <A href="' . $_PE[0] . '/' . $sID . '?empty">empty</A> the gene database.</B>', 'warning');

    if ($bValidPassword) {
        $zCounts = $_DB->query('SELECT count(DISTINCT t.id) AS tcount, count(DISTINCT vot.id) AS votcount
                                FROM ' . TABLE_TRANSCRIPTS . ' AS t
                                 LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)
                                WHERE t.geneid = ?', array($sID))->fetchAssoc();
        if ($zCounts['tcount'] || $zCounts['votcount']) {
            lovd_showInfoTable('<B>You are about to delete ' . $zCounts['tcount'] .
                ' transcript(s) and related information on ' . $zCounts['votcount'] .
                ' variant(s) on those transcripts. Please fill in your password one more time ' .
                'to confirm the removal of gene ' . $sID . '.</B>', 'warning');
        } else {
            lovd_showInfoTable('<B>Please note the message above and fill in your password one ' .
                'more time to confirm the removal of gene ' . $sID . '</B>', 'warning');
        }
    }

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PE[0] . '/' . $sID . '?' . ACTION . (!$bValidPassword? '' : '&confirm=true') . '" method="post">' . "\n");

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





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && !ACTION) {
    // URL: /genes/DMD/columns
    // View enabled columns for this gene.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'View enabled custom data columns for gene ' . $sID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this gene.
    lovd_isAuthorized('gene', $sID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sID);
    $n = $_DATA->viewList('Columns');

    if ($n) {
        lovd_showJGNavigation(array('javascript:lovd_openWindow(\'' . lovd_getInstallURL() . CURRENT_PATH . '?order&amp;in_window\', \'ColumnSort' . $sID . '\', 800, 350);' =>
            array('', 'Change order of columns', 1)), 'Columns');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 3 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && !ACTION) {
    // URL: /genes/DMD/columns/DNA
    // URL: /genes/DMD/columns/GVS/Function
    // View specific enabled column for this gene.

    $sUnit = 'gene';
    $sCategory = 'VariantOnTranscript';

    $sParentID = rawurldecode($_PE[1]);
    $aCol = $_PE;
    unset($aCol[0], $aCol[1], $aCol[2]); // 'genes/DMD/columns';
    $sColumnID = implode('/', $aCol);
    define('PAGE_TITLE', 'View settings for custom data column ' . $sColumnID . ' for ' . $sUnit . ' ' . $sParentID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this gene.
    lovd_isAuthorized($sUnit, $sParentID);
    lovd_requireAUTH(LEVEL_CURATOR); // Will also stop user if gene given is fake.

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sParentID, $sCategory . '/' . $sColumnID);
    $zData = $_DATA->viewEntry($sCategory . '/' . $sColumnID);

    $aNavigation =
         array(
                CURRENT_PATH . '?edit' => array('menu_edit.png', 'Edit settings for this ' . $sUnit . ' only', 1),
                // FIXME; Can we redirect inmediately to the correct page? And in a new window!
                'columns/' . $sCategory . '/' . $sColumnID . '?remove&amp;target=' . $sParentID => array('cross.png', 'Remove column from this ' . $sUnit, (!$zData['hgvs'])),
              );
    lovd_showJGNavigation($aNavigation, 'ColumnEdit');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 3 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && ACTION == 'edit') {
    // URL: /genes/DMD/columns/DNA?edit
    // URL: /genes/DMD/columns/GVS/Function?edit
    // Edit specific enabled column for this gene.

    $sUnit = 'gene';
    $sCategory = 'VariantOnTranscript';

    $sParentID = rawurldecode($_PE[1]);
    $aCol = $_PE;
    unset($aCol[0], $aCol[1], $aCol[2]); // 'genes/DMD/columns';
    $sColumnID = implode('/', $aCol);
    define('PAGE_TITLE', 'Edit settings for custom data column ' . $sColumnID . ' for ' . $sUnit . ' ' . $sParentID);
    define('LOG_EVENT', 'SharedColEdit');

    // Load appropriate user level for this gene.
    lovd_isAuthorized($sUnit, $sParentID);
    lovd_requireAUTH(LEVEL_CURATOR); // Will also stop user if gene given is fake.

    require ROOT_PATH . 'class/object_shared_columns.php';
    $_DATA = new LOVD_SharedColumn($sParentID, $sCategory . '/' . $sColumnID);
    $zData = $_DATA->loadEntry($sCategory . '/' . $sColumnID);
    // Remove columns based on form type?
    $aFormType = explode('|', $zData['form_type']);

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'public_view', 'public_add', 'edited_by', 'edited_date');
            if ($aFormType[2] == 'select') {
                $aFields[] = 'select_options';
            }

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // Update entry.
            $_DATA->updateEntry($sCategory . '/' . $sColumnID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited column ' . $sColumnID . ' for ' . $sUnit . ' ' . $sParentID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited column "' . $sColumnID . '" for ' . $sUnit . ' ' . $sParentID . '!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-columns.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit custom data column'),
                      ));
    if ($aFormType[2] != 'select') {
        unset($aForm['options'], $aForm['options_note']);
    }
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && $_PE[2] == 'columns' && ACTION == 'order') {
    // URL: /genes/DMD/columns?order
    // Change order of enabled columns for this gene.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Change order of enabled custom data columns for gene ' . $sID);
    define('LOG_EVENT', 'ColumnOrder');
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this gene.
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
        lovd_showInfoTable('No active columns found!', 'stop');
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
          '        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="' . (isset($_GET['in_window'])? 'self.close(); return false;' : 'window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1] . '\'; return false;') . '" style="border : 1px solid #FF4422;">' . "\n" .
          '      </FORM>' . "\n\n");

?>
      <SCRIPT type='text/javascript'>
        $(function() {
          $('#column_list').sortable({
            containment: 'parent',
            tolerance: 'pointer',
            handle: 'TD.handle'
          });
          $('#column_list').disableSelection();
        });
      </SCRIPT>
<?php

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && $_PE[2] == 'graphs' && !ACTION) {
    // URL: /genes/DMD/graphs
    // Show different graphs about this gene; variant type (DNA, RNA & Protein level), ...

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'Graphs &amp; statistics on gene ' . $sID);
    $_T->printHeader();
    $_T->printTitle();

    // Load authorization, collaborators and up see statistics about all variants, not just the public ones.
    lovd_isAuthorized('gene', $sID);

    // Check if there are variants at all.
    $nVariants = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : ' AND statusid >= ' . STATUS_MARKED), array($sID))->fetchColumn();
    if (!$nVariants) {
        lovd_showInfoTable('There are currently no ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants in this gene.', 'stop');
        $_T->printFooter();
        exit;
    }



    require ROOT_PATH . 'class/graphs.php';
    $_G = new LOVD_Graphs();
    lovd_includeJS('lib/flot/jquery.flot.min.js');
    lovd_includeJS('lib/flot/jquery.flot.pie.min.js');
    print('      <!--[if lte IE 8]><SCRIPT type="text/javascript" src="lib/flot/excanvas.min.js"></SCRIPT><![endif]-->' . "\n\n");

    // FIXME; Implement:
    // Check what's left here.
    // - Variant types (RNA).
    // - Locations of variants (exon and intron numbers)?
    // - Variants in this gene reported in individuals with which diseases?
    //   * Too bad we don't know if these variants cause this disease. Search for pathogenicity only? YES

    // We need to create the DIV containers, the Graph object will fill it in.
    // To save ourselves a lot of code, we'll build the DIV containers as templates.
    $aGraphs = array(
        // Variant types (DNA level).
        'Variant type (DNA level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants)' =>
        array(
            'variantsTypeDNA_all' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
            'variantsTypeDNA_unique' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
        ),
        // Variant types (DNA level) ((likely) pathogenic only).
        'Variant type (DNA level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants)' =>
        array(
            'variantsTypeDNA_all_pathogenic' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
            'variantsTypeDNA_unique_pathogenic' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
        ),
        // Variant types (protein level).
        'Variant type (Protein level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants) (note: numbers are sums for all transcripts of this gene)' =>
        array(
            'variantsTypeProtein_all' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
            'variantsTypeProtein_unique' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
        ),
        // Variant types (protein level) ((likely) pathogenic only).
        'Variant type (Protein level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants) (note: numbers are sums for all transcripts of this gene)' =>
        array(
            'variantsTypeProtein_all_pathogenic' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
            'variantsTypeProtein_unique_pathogenic' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
        ),
        // Variant locations (DNA level).
        'Variant location (DNA level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants) (note: numbers are sums for all transcripts of this gene)' =>
        array(
            'variantsLocations_all' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
            'variantsLocations_unique' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'variants',
        ),
        // Variant locations (DNA level) ((likely) pathogenic only).
        'Variant type (DNA level, all ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants) (note: numbers are sums for all transcripts of this gene)' =>
        array(
            'variantsLocations_all_pathogenic' => 'All ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
            'variantsLocations_unique_pathogenic' => 'Unique ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'public ') . 'pathogenic variants',
        ),
    );

    foreach ($aGraphs as $sCategory => $aCategory) {
        print('      <H5>' . $sCategory . '</H5>' . "\n" .
              '      <TABLE border="0" cellpadding="2" cellspacing="0" width="900" style="height : 320px;">' . "\n" .
              '        <TR valign="top">');
        foreach ($aCategory as $sGraphID => $sTitle) {
            print("\n" .
                  '          <TD width="50%">' . "\n" .
                  '            <B>' . $sTitle . '</B><BR>' . "\n" .
                  '            <DIV id="' . $sGraphID . '" style="width : 325px; height : 250px;"><IMG src="gfx/lovd_loading.gif" alt="Loading..."></DIV><BR><DIV id="' . $sGraphID . '_hover">&nbsp;</DIV></TD>');
        }
        print('</TR></TABLE>' . "\n\n");
    }

    flush();
    $_T->printFooter(false);
    $_G->variantsTypeDNA('variantsTypeDNA_all', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false);
    $_G->variantsTypeDNA('variantsTypeDNA_unique', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true);
    $_G->variantsTypeDNA('variantsTypeDNA_all_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false, true);
    $_G->variantsTypeDNA('variantsTypeDNA_unique_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true, true);
    $_G->variantsTypeProtein('variantsTypeProtein_all', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false, false);
    $_G->variantsTypeProtein('variantsTypeProtein_unique', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true, false);
    $_G->variantsTypeProtein('variantsTypeProtein_all_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false, true);
    $_G->variantsTypeProtein('variantsTypeProtein_unique_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true, true);
    $_G->variantsLocations('variantsLocations_all', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false);
    $_G->variantsLocations('variantsLocations_unique', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true);
    $_G->variantsLocations('variantsLocations_all_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), false, true);
    $_G->variantsLocations('variantsLocations_unique_pathogenic', $sID, ($_AUTH['level'] >= LEVEL_COLLABORATOR), true, true);

    print('</BODY>' . "\n" .
          '</HTML>' . "\n");
    exit;
}





if (PATH_COUNT == 2 && preg_match('/^[a-z][a-z0-9#@-]*$/i', rawurldecode($_PE[1])) && in_array(ACTION, array('authorize', 'sortCurators'))) {
    // URL: /genes/DMD?authorize
    // URL: /genes/DMD?sortCurators
    // Authorize users to be curators or collaborators for this gene, and/or define the order in which they're shown.

    $sID = rawurldecode($_PE[1]);

    // 2015-07-22; 3.0-14; Drop usage of CURRENT_PATH in favor of fixed $sID which may have a gene symbol with incorrect case.
    // Now fix possible issues with capitalization. inc-init.php does this for $_SESSION['currdb'], but we're using $sID.
    $sVerifiedID = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($sID))->fetchColumn();
    if (!$sVerifiedID) {
        define('PAGE_TITLE', 'Manage curators for the ' . $sID . ' gene');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }
    $sID = $sVerifiedID;

    // Load appropriate user level for this gene.
    lovd_isAuthorized('gene', $sID);

    if (ACTION == 'authorize' && $_AUTH['level'] < LEVEL_MANAGER) {
        header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $sID . '?sortCurators');
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

            // Prepare log for changes.
            // (depends on current database status, so we create the log message before
            // the changes are committed, but log the actual message afterwards).
            $sLogMessage = lovd_prepareCuratorLogMessage($sID, $_POST['curators'],
                                                         $_POST['allow_edit'], $_POST['shown']);

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
                    //   We could rapport things here more specifically because MySQL can tell us if there has been an update (2) or an insert (1) or nothing changed (0).
                } else {
                    // Just sort and update visibility!
                    $_DB->query('UPDATE ' . TABLE_CURATES . ' SET show_order = ? WHERE geneid = ? AND userid = ?', array((in_array($nUserID, $_POST['shown'])? $nOrder : 0), $sID, $nUserID));
                }
            }

            if (ACTION == 'authorize') {
                // Now everybody should be updated. Remove whoever should no longer be in there.
                $_DB->query('DELETE FROM c USING ' . TABLE_CURATES . ' AS c, ' . TABLE_USERS . ' AS u WHERE c.userid = u.id AND c.geneid = ? AND c.userid NOT IN (?' . str_repeat(', ?', count($_POST['curators']) - 1) . ') AND (u.level < ? OR u.id = ?)', array_merge(array($sID), $_POST['curators'], array($_AUTH['level'], $_AUTH['id'])));
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, $sLogMessage);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $sID);

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
        $qCurators = $_DB->query('SELECT u.id, u.name, c.allow_edit, (c.show_order > 0) AS shown, u.level FROM ' . TABLE_CURATES . ' AS c INNER JOIN ' . TABLE_USERS . ' AS u ON (c.userid = u.id) WHERE c.geneid = ? ' . (ACTION == 'authorize'? '' : 'AND c.allow_edit = 1 ') . 'ORDER BY (c.show_order > 0) DESC, c.show_order, u.level DESC, u.name', array($sID));
        while ($z = $qCurators->fetchAssoc()) {
            $aCurators[$z['id']] = $z;
        }
    }



    lovd_errorPrint();

    if (ACTION == 'authorize') {
        // Show viewList() of users that are NO curator or collaborator at this moment.
        require ROOT_PATH . 'class/object_users.php';
        $_DATA = new LOVD_User();
        lovd_showInfoTable('The following users are currently not a curator for this gene. Click on a user to select him/her as Curator or Collaborator.', 'information');
        if ($aCurators) {
            // Create search string that hides the users currently selected to be curator or collaborator.
            $_GET['search_id'] = '!' . implode(' !', array_keys($aCurators));
        } else {
            // We must have something non-empty here, otherwise the JS fails when selecting users.
            $_GET['search_id'] = '!0';
        }
        $_GET['page_size'] = 10;
        $_DATA->setRowLink('Genes_AuthorizeUser', 'javascript:lovd_passAndRemoveViewListRow("{{ViewListID}}", "{{ID}}", {id: "{{ID}}", name: "{{zData_name}}", level: "{{zData_level}}"}, lovd_authorizeUser); return false;');
        $_DATA->viewList('Genes_AuthorizeUser', array('id', 'status_', 'last_login_', 'created_date_'), true); // Create known viewListID for lovd_unauthorizeUser().



        // Show curators, to sort and to select whether or not they can edit.
        print('      <BR><BR>' . "\n\n");

        lovd_showInfoTable('All users below have access to all data (public and non-public) of the ' . $sID . ' gene database. If you don\'t want to give the user access to <I>edit</I> any of the data that is not their own, deselect the "Allow edit" checkbox. Please note that users with level Manager or higher, cannot be restricted in their right to edit all information in the database.<BR>Users without edit rights are called Collaborators. Users having edit rights are called Curators; they receive email notifications of new submission and are shown on the gene\'s home page by default. You can disable that below by deselecting the "Shown" checkbox next to their name. To sort the list of curators for this gene, click and drag the <IMG src="gfx/drag_vertical.png" alt="" width="5" height="13"> icon up or down the list. Release the mouse button in the preferred location.', 'information');
    } else {
        lovd_showInfoTable('To sort the list of curators for this gene, click and drag the <IMG src="gfx/drag_vertical.png" alt="" width="5" height="13"> icon up or down the list. Release the mouse button in the preferred location. If you do not want a user to be shown on the list of curators on the gene homepage and on the top of the screen, deselect the checkbox on the right side of his/her name.', 'information');
    }

    // Form & table.
    print('      <TABLE class="sortable_head" style="width : 552px;"><TR><TH width="15">&nbsp;</TH><TH>Name</TH>');
    if (ACTION == 'authorize') {
        print('<TH width="100" style="text-align:right;">Allow edit</TH><TH width="75" style="text-align:right;">Shown</TH><TH width="30">&nbsp;</TH>');
    } else {
        print('<TH width="75" style="text-align:right;">Shown</TH>');
    }
    print('</TR></TABLE>' . "\n" .
          '      <FORM action="' . $_PE[0] . '/' . $sID . '?' . ACTION . '" method="post">' . "\n" .
          '        <UL id="curator_list" class="sortable" style="margin-top : 0px; width : 550px;">' . "\n");
    // Now loop the items in the order given.
    foreach ($aCurators as $nID => $aVal) {
        print('          <LI id="li_' . $nID . '"><INPUT type="hidden" name="curators[]" value="' . $nID . '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' . $aVal['name'] . ' (#' . $nID . ')</TD>');
        if (ACTION == 'authorize') {
            print('<TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' . $nID . '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else if (' . $aVal['level'] . ' >= ' . LEVEL_MANAGER . ') { this.checked = true; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }"' . ($aVal['allow_edit'] || $aVal['level'] >= LEVEL_MANAGER? ' checked' : '') . '></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' . $nID . '"' . ($aVal['allow_edit']? ($aVal['shown']? ' checked' : '') : ' disabled') . '></TD><TD width="30" align="right">' . ($aVal['level'] >= $_AUTH['level'] && $nID != $_AUTH['id']? '&nbsp;' : '<A href="#" onclick="lovd_unauthorizeUser(\'Genes_AuthorizeUser\', \'' . $nID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A>') . '</TD>');
        } else {
            print('<TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' . $nID . '"' . ($aVal['shown']? ' checked' : '') . '></TD>');
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
                        array('', '', 'print', '<INPUT type="submit" value="Save curator list">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $sID . '\'; return false;" style="border : 1px solid #FF4422;">'),
                      );
        lovd_viewForm($aForm);
    } else {
        print('        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $sID . '\'; return false;" style="border : 1px solid #FF4422;">' . "\n");
    }
    print("\n" .
          '      </FORM>' . "\n\n");

    // FIXME; disable JS functions authorize and unauthorize if not authorizing?
?>
      <SCRIPT type='text/javascript'>
        $(function() {
          $('#curator_list').sortable({
            containment: 'parent',
            tolerance: 'pointer',
            handle: 'TD.handle'
          });
          $('#curator_list').disableSelection();
        });


<?php
    if (ACTION == 'authorize') {
?>
        function lovd_authorizeUser (aData)
        {
            // Creates the user to the Authorized Users block.

            objUsers = document.getElementById('curator_list');
            oLI = document.createElement('LI');
            oLI.id = 'li_' + aData.id;
            oLI.innerHTML = '<INPUT type="hidden" name="curators[]" value="' + aData.id + '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' + aData.name + '</TD><TD width="100" align="right"><INPUT type="checkbox" name="allow_edit[]" value="' + aData.id + '" onchange="if (this.checked == true) { this.parentNode.nextSibling.children[0].disabled = false; } else if (' + aData.level + ' >= <?php echo LEVEL_MANAGER; ?>) { this.checked = true; } else { this.parentNode.nextSibling.children[0].checked = false; this.parentNode.nextSibling.children[0].disabled = true; }" checked></TD><TD width="75" align="right"><INPUT type="checkbox" name="shown[]" value="' + aData.id + '" checked></TD><TD width="30" align="right"><A href="#" onclick="lovd_unauthorizeUser(\'Genes_AuthorizeUser\', \'' + aData.id + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE>';
            objUsers.appendChild(oLI);

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
