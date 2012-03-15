<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2012-03-13
 * For LOVD    : 3.0-beta-03
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





if (!ACTION && (empty($_PATH_ELEMENTS[1]) || preg_match('/^chr[0-9A-Z]{1,2}$/', $_PATH_ELEMENTS[1]))) {
    // URL: /variants
    // URL: /variants/chrX
    // View all genomic variant entries, optionally restricted by chromosome.

    if (!empty($_PATH_ELEMENTS[1])) {
        $sChr = $_PATH_ELEMENTS[1];
    } else {
        $sChr = '';
    }

    define('PAGE_TITLE', 'View genomic variants' . (!$sChr? '' : ' on chromosome ' . substr($sChr, 3)));
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $aColsToHide = array('screeningids', 'allele_');
    if ($sChr) {
        $_GET['search_chromosome'] = '="' . substr($sChr, 3) . '"';
        $aColsToHide[] = 'chromosome';
    }
    $_DATA->viewList('VOG', $aColsToHide);

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!ACTION && !empty($_PATH_ELEMENTS[1]) && $_PATH_ELEMENTS[1] == 'in_gene') {
    // URL: /variants/in_gene
    // View all entries effecting a transcript.

    define('PAGE_TITLE', 'View transcript variants');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_custom_viewlists.php';
    $_DATA = new LOVD_CustomViewList(array('Transcript', 'VariantOnTranscript', 'VariantOnGenome'));
    $_DATA->viewList('CustomVL_IN_GENE', array('transcriptid'));

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!ACTION && !empty($_PATH_ELEMENTS[1]) && !ctype_digit($_PATH_ELEMENTS[1])) {
    // URL: /variants/DMD
    // URL: /variants/DMD/NM_004006.2
    // View all entries in a specific gene, affecting a specific trancript.

    if (in_array(rawurldecode($_PATH_ELEMENTS[1]), lovd_getGeneList())) {
        $sGene = rawurldecode($_PATH_ELEMENTS[1]);
        lovd_isAuthorized('gene', $sGene); // To show non public entries.

        // Overview is given per transcript. If there is only one, it will be mentioned. If there are more, you will be able to select which one you'd like to see.
        $aTranscripts = $_DB->query('SELECT t.id, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE t.geneid = ? AND vot.id IS NOT NULL', array($sGene))->fetchAllCombine();
        $nTranscripts = count($aTranscripts);

        // If NM is mentioned, check if exists for this gene. If not, reload page without NM. Otherwise, restrict $aTranscripts.
        if (!empty($_PATH_ELEMENTS[2])) {
            $nTranscript = array_search($_PATH_ELEMENTS[2], $aTranscripts);
            if ($nTranscript === false) {
                // NM does not exist. Throw error or just simply redirect?
                header('Location: ' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $_PATH_ELEMENTS[1]);
                exit;
            } else {
                $aTranscripts = array($nTranscript => $aTranscripts[$nTranscript]);
                $nTranscripts = 1;
            }
        }
    } else {
        // Command or gene not understood.
        // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
        //   test if browsers show that output or their own error page. Also, then, use the same method at
        //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
        exit;
    }

    define('PAGE_TITLE', 'View transcript variants in ' . $sGene);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    $sViewListID = 'CustomVL_VOT_VOG_' . $sGene;

    // If this gene has only one NM, show that one. Otherwise have people pick one.
    list($nTranscriptID, $sTranscript) = each($aTranscripts);
    if (!$nTranscripts) {
        $sMessage = 'No transcripts or variants found for this gene.';
    } elseif ($nTranscripts == 1) {
        $_GET['search_transcriptid'] = $nTranscriptID;
        $sMessage = 'The variants shown are described using the ' . $sTranscript . ' transcript reference sequence.';
    } else {
        // Create select box.
        // We would like to be able to link to this list, focussing on a certain transcript but without restricting the viewer, by sending a (numeric) get_transcriptid search term.
        if (!isset($_GET['search_transcriptid']) || !isset($aTranscripts[$_GET['search_transcriptid']])) {
            $_GET['search_transcriptid'] = $nTranscriptID;
        }
        $sSelect = '<SELECT id="change_transcript" onchange="$(\'input[name=\\\'search_transcriptid\\\']\').val($(this).val()); lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');">';
        foreach ($aTranscripts as $nTranscriptID => $sTranscript) {
            $sSelect .= '<OPTION value="' . $nTranscriptID . '"' . ($_GET['search_transcriptid'] != $nTranscriptID? '' : ' selected') . '>' . $sTranscript . '</OPTION>';
        }
        $sMessage = 'The variants shown are described using the ' . $sSelect . '</SELECT> transcript reference sequence.';
    }
    lovd_showInfoTable($sMessage);

    if ($nTranscripts > 0) {
        require ROOT_PATH . 'class/object_custom_viewlists.php';
        $_DATA = new LOVD_CustomViewList(array('VariantOnTranscript', 'VariantOnGenome'));
        $_DATA->sSortDefault = 'VariantOnTranscript/DNA';
        $_DATA->viewList($sViewListID, array('transcriptid', 'chromosome'));
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /variants/0000000001
    // View specific entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'View genomic variant #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->viewEntry($nID);
    lovd_isAuthorized('variant', $nID);

    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="variants/' . $nID . '?edit">Edit variant entry</A>';
        $sNavigation .= ' | <A href="variants/' . $nID . '?map">Add variant description to additional transcript</A>';
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $sNavigation .= ' | <A href="variants/' . $nID . '?delete">Delete variant entry</A>';
        }
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    print('      <BR><BR>' . "\n\n" .
          '      <DIV id="viewentryDiv">' . "\n" .
          '      </DIV>' . "\n\n");

    $_GET['search_id_'] = $nID;
    print('      <BR><BR>' . "\n\n");
    lovd_printHeader('Variant on transcripts', 'H4');
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = new LOVD_TranscriptVariant('', $nID);
    $_DATA->setRowID('VOT_for_VOG_VE', 'VOT_{{transcriptid}}');
    $_DATA->setRowLink('VOT_for_VOG_VE', 'javascript:window.location.hash = \'{{transcriptid}}\'; return false');
    $_DATA->viewList('VOT_for_VOG_VE', array('id_', 'transcriptid', 'status'), true, true);
    unset($_GET['search_id_']);
?>

      <SCRIPT type="text/javascript">
        var prevHash = '';
        $( function () {
            lovd_AJAX_viewEntryLoad();
            setInterval(lovd_AJAX_viewEntryLoad, 250);
        });





        function lovd_AJAX_viewEntryLoad () {
            var hash = window.location.hash.substring(1);
            if (hash) {
                if (hash != prevHash) {
                    // Hash changed, (re)load viewEntry.
                    // Set the correct status for the TRs in the viewList (highlight the active TR).
                    $( '#VOT_' + prevHash ).attr('class', 'data');
                    $( '#VOT_' + hash ).attr('class', 'data bold');

                    if (!($.browser.msie && $.browser.version < 9.0)) {
                        $( '#viewentryDiv' ).stop().css('opacity','0'); // Stop execution of actions, set opacity = 0 (hidden, but not taken out of the flow).
                    }
                    $.get('ajax/viewentry.php', { object: 'Transcript_Variant', id: '<?php echo $nID; ?>,' + hash },
                        function(sData) {
                            if (sData.length > 2) {
                                $( '#viewentryDiv' ).html('\n' + sData);
                                if (!($.browser.msie && $.browser.version < 9.0)) {
                                    $( '#viewentryDiv' ).fadeTo(1000, 1);
                                }
                            }
                        });
                    prevHash = hash;
                } else {
                    // The viewList could have been resubmitted now, so reset this value (not very efficient).
                    $( '#VOT_' + hash ).attr('class', 'data bold');
                }
            }
        }
      </SCRIPT>
<?php

    if (!empty($zData['screeningids'])) {
        $_GET['search_screeningid'] = $zData['screeningids'];
        print('<BR><BR>' . "\n\n");
        lovd_printHeader('Screenings', 'H4');
        require ROOT_PATH . 'class/object_screenings.php';
        $_DATA = new LOVD_Screening();
        $_DATA->viewList('Screenings_for_VOG_VE', array('screeningid', 'individualid', 'created_date', 'edited_date'), true, true);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // Create a new entry.

    lovd_requireAUTH(LEVEL_SUBMITTER);

    define('LOG_EVENT', 'VariantCreate');

    if (isset($_GET['target'])) {
        // On purpose not checking for numeric target. If it's not numeric, we'll automatically get to the error message below.
        $_GET['target'] = sprintf('%010d', $_GET['target']);
        $z = $_DB->query('SELECT id, variants_found FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target']))->fetchAssoc();
        $sMessage = '';
        if (!$z) {
            $sMessage = 'The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.';
        } elseif (!lovd_isAuthorized('screenings', $_GET['target'])) {
            lovd_requireAUTH(LEVEL_OWNER);
        } elseif (!$z['variants_found']) {
            $sMessage = 'Cannot add variant to the given screening, because the value \'Have variants been found?\' is unchecked.';
        }
        if ($sMessage) {
            define('PAGE_TITLE', 'Create a new variant entry');
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable($sMessage, 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        } else {
            $_POST['screeningid'] = $_GET['target'];
            $_GET['search_id_'] = $_DB->query('SELECT GROUP_CONCAT(DISTINCT geneid SEPARATOR "|") FROM ' . TABLE_SCR2GENE . ' WHERE screeningid = ?', array($_POST['screeningid']))->fetchColumn(); 
        }

    } else {
        $_GET['target'] = '';
    }

    if (!isset($_GET['reference'])) {
        // URL: /variants?create
        // Select wether the you want to create a variant on the genome or on a transcript.
        define('PAGE_TITLE', 'Create a new variant entry');
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        require ROOT_PATH . 'inc-lib-form.php';

        $nIndividual = $_DB->query('SELECT individualid FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target']))->fetchColumn();
        $aVariants = $_DB->query('SELECT s2v.variantid FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = ?', array($nIndividual))->fetchAllColumn();

        if ($_GET['target']) {
            $aOptionsList = array('width' => 600);
            if (!count($aVariants)) {
                $aOptionsList['options'][0]['disabled'] = true;
                $aOptionsList['options'][0]['onclick'] = 'alert(\'You cannot confirm variants with this screening, because there aren\&#39;t any variants connected to this individual yet!\');';
            } else {
                $aOptionsList['options'][0]['onclick'] = 'window.location.href=\'screenings/' . $_GET['target'] . '?confirm\'';
            }
            $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to confirm variants found using this screening &raquo;&raquo;</B>';

            print('      Do you want to add already submitted variants with this screening?<BR><BR>' . "\n\n");
            print(lovd_buildOptionTable($aOptionsList));
        }

        $aOptionsList = array('width' => 600);
        $aOptionsList['options'][0]['onclick'] = 'window.location.href=\'variants?create&amp;reference=Genome' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '') . '\'';
        $aOptionsList['options'][0]['option_text'] = '<B>I want to create a variant on genomic level &raquo;&raquo;</B>';
        $aOptionsList['options'][1]['onclick'] = '$(\'#container\').toggle();';
        $aOptionsList['options'][1]['option_text'] = '<B>I want to create a variant on genomic & transcript level &raquo;&raquo;</B>';

        print('      What kind of variant would you like to submit?<BR><BR>' . "\n\n");
        print(lovd_buildOptionTable($aOptionsList));

        $sViewListID = 'Genes_SubmitVOT' . ($_GET['target']? '_' . $_GET['target'] : '');

        require ROOT_PATH . 'class/object_genes.php';
        $_GET['page_size'] = 10;
        $_DATA = new LOVD_Gene();
        $_DATA->setRowLink($sViewListID, 'variants?create&reference=Transcript&geneid=' . $_DATA->sRowID . ($_GET['target']? '&target=' . $_GET['target'] : ''));
        $_GET['search_transcripts'] = '>0';
        print('      <DIV id="container">' . "\n"); // Extra div is to prevent "No entries in the database yet!" error to show up if there are no genes in the database yet.
        $_DATA->viewList($sViewListID, array('geneid', 'transcripts', 'variants', 'diseases_', 'updated_date_'));
        print('      </DIV>' . "\n" .
              '      <SCRIPT type="text/javascript">' . "\n" .
              '        $("#container").hide();' . "\n" .
              '      </SCRIPT>' . "\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;





    } elseif (!in_array($_GET['reference'], array('Genome', 'Transcript')) || ($_GET['reference'] == 'Transcript' && empty($_GET['geneid']))) {
        exit;
    }

    // URL: /variants?create&reference=('Genome'|'Transcript')
    // Create a variant on the genome.

    if ($_GET['reference'] == 'Transcript') {
        // On purpose not checking for format of $_GET['geneid']. If it's not right, we'll automatically get to the error message below.
        $sGene = $_GET['geneid'];
        if (!in_array($sGene, lovd_getGeneList())) {
            define('PAGE_TITLE', 'Create a new variant entry');
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('The gene symbol given is not valid, please go to the create variant page and select the desired gene entry.', 'warning');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        } else {
            define('PAGE_TITLE', 'Create a new variant entry for gene ' . $_GET['geneid']);
        }
    } else {
        define('PAGE_TITLE', 'Create a new variant entry');
    }

    lovd_isAuthorized('gene', (isset($sGene)? $sGene : $_AUTH['curates']));

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = array();
    $_DATA['Genome'] = new LOVD_GenomeVariant();
    if (isset($sGene)) {
        require ROOT_PATH . 'class/object_transcript_variants.php';
        $_DATA['Transcript'][$sGene] = new LOVD_TranscriptVariant($sGene);
        $_POST['aTranscripts'] = $_DATA['Transcript'][$sGene]->aTranscripts;
        $_POST['chromosome'] = $_DB->query('SELECT chromosome FROM ' . TABLE_GENES . ' WHERE id = ?', array($sGene))->fetchColumn();
    }
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA['Genome']->checkFields($_POST);

        if (isset($sGene)) {
            $_DATA['Transcript'][$sGene]->checkFields($_POST);
        }

        if (!lovd_error()) {
            // Prepare the fields to be used for both genomic and transcript variant information.
            $aFieldsGenome = array_merge(
                                array('allele', 'effectid', 'chromosome', 'position_g_start', 'type', 'position_g_end', 'owned_by', 'statusid', 'created_by', 'created_date'),
                                $_DATA['Genome']->buildFields());

            // Prepare values.
            $_POST['effectid'] = $_POST['effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['effect_concluded'] : '5');

            require ROOT_PATH . 'class/REST2SOAP.php';
            $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
            $aOutput = $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => 'NM_001100.3', 'variant' => $_POST['VariantOnGenome/DNA']));
            if (!empty($aOutput) && !$aOutput['errorcode'][0]['v']) {
                $_POST['position_g_start'] = $aOutput['start_g'][0]['v'];
                $_POST['position_g_end'] = $aOutput['end_g'][0]['v'];
                $_POST['type'] = $aOutput['mutationType'][0]['v'];
            }

            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_IN_PROGRESS);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            lovd_queryDB_Old('BEGIN TRANSACTION');
            $nID = $_DATA['Genome']->insertEntry($_POST, $aFieldsGenome);

            if (isset($sGene)) {
                $_POST['id'] = $nID;
                foreach($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                    if (!empty($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) && strlen($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) >= 6) {
                        $aOutput = $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $aTranscript[0], 'variant' => $_POST[$nTranscriptID . '_VariantOnTranscript/DNA']));
                        if (!is_array($aOutput) && !empty($aOutput)) {
                            $_MutalyzerWS->soapError('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $aTranscript[0], 'variant' => $_POST[$nTranscriptID . '_VariantOnTranscript/DNA']), $aOutput);
                        } elseif (!empty($aOutput) && !$aOutput['errorcode'][0]['v']) {
                            $_POST[$nTranscriptID . '_position_c_start'] = $aOutput['startmain'][0]['v'];
                            $_POST[$nTranscriptID . '_position_c_start_intron'] = $aOutput['startoffset'][0]['v'];
                            $_POST[$nTranscriptID . '_position_c_end'] = $aOutput['endmain'][0]['v'];
                            $_POST[$nTranscriptID . '_position_c_end_intron'] = $aOutput['endoffset'][0]['v'];
                        } else {
                            $_POST[$nTranscriptID . '_position_c_start'] = 0;
                            $_POST[$nTranscriptID . '_position_c_start_intron'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end_intron'] = 0;
                        }
                    } else {
                        $_POST[$nTranscriptID . '_position_c_start'] = 0;
                        $_POST[$nTranscriptID . '_position_c_start_intron'] = 0;
                        $_POST[$nTranscriptID . '_position_c_end'] = 0;
                        $_POST[$nTranscriptID . '_position_c_end_intron'] = 0;
                    }
                }
                $aFieldsTranscript = array_merge(
                                        array('id', 'transcriptid', 'effectid', 'position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron'),
                                        $_DATA['Transcript'][$sGene]->buildFields());
                $aTranscriptID = $_DATA['Transcript'][$sGene]->insertAll($_POST, $aFieldsTranscript);
            }
            lovd_queryDB_Old('COMMIT');

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created variant entry ' . $nID);

            if (isset($_POST['screeningid'])) {
                // Add variant to screening.
                $q = lovd_queryDB_Old('INSERT INTO ' . TABLE_SCR2VAR . ' VALUES (?, ?)', array($_POST['screeningid'], $nID));
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Variant entry could not be added to screening #' . $_POST['screeningid']);
                }
            }

            $bSubmit = false;
            $sSubmitType = '';
            if (isset($_POST['screeningid']) && isset($_SESSION['work']['submits']['screening'][$_POST['screeningid']])) {
                $bSubmit = true;
                $aSubmit = &$_SESSION['work']['submits']['screening'][$_POST['screeningid']];
                $sSubmitType = 'screening';
            } elseif (isset($_POST['screeningid']) && isset($_SESSION['work']['submits']['individual'])) {
                foreach($_SESSION['work']['submits']['individual'] as $nIndividualID => &$aSubmit) {
                    if (isset($aSubmit['screenings']) && in_array($_POST['screeningid'], $aSubmit['screenings'])) {
                        $bSubmit = true;
                        $sSubmitType = 'individual';
                        $_POST['individualid'] = $nIndividualID;
                        break;
                    }
                }
            }

            if ($bSubmit) {
                if (!isset($aSubmit['variants'])) {
                    $aSubmit['variants'] = array();
                }
                $aSubmit['variants'][] = $nID;
            } else {
                $_SESSION['work']['submits']['variant'][$nID] = $nID;
            }

            if ($bSubmit) {
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                print('      Were there more variants found with this mutation screening?<BR><BR>' . "\n\n");

                $aOptionsList = array();
                $aOptionsList['options'][0]['onclick']     = 'window.location.href=\'' . lovd_getInstallURL() . 'variants?create&amp;target=' . $_POST['screeningid'] . '\'';
                $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to submit more variants found by this mutation screening</B>';
                if ($sSubmitType == 'individual') {
                    $aOptionsList['options'][1]['onclick']     = 'window.location.href=\'' . lovd_getInstallURL() . 'screenings?create&amp;target=' . $_POST['individualid'] . '\'';
                    $aOptionsList['options'][1]['option_text'] = '<B>No, I want to submit another screening instead</B>';
                }
                $aOptionsList['options'][2]['onclick']     = 'window.location.href=\'' . lovd_getInstallURL() . 'submit/finish/' . $sSubmitType . '/' . $_POST[$sSubmitType . 'id'] . '\'';
                $aOptionsList['options'][2]['option_text'] = '<B>No, I have finished my submission</B>';

                print(lovd_buildOptionTable($aOptionsList));

                require ROOT_PATH . 'inc-bot.php';
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/variant/' . $nID);
            }
            exit;
        }

    } else {
        // Default values.
        $_DATA['Genome']->setDefaultValues();
        if (isset($sGene)) {
            $_DATA['Transcript'][$sGene]->setAllDefaultValues();
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To create a new variant entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM id="variantForm" action="' . CURRENT_PATH . '?create&amp;reference=' . $_GET['reference'] . (isset($sGene)? '&amp;geneid=' . rawurlencode($sGene) : '') . (isset($_POST['screeningid'])? '&amp;target=' . $_GET['target'] : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA['Genome']->getForm((isset($sGene)? $_DATA['Transcript'][$sGene]->getForm() : array())),
                 array(
                        array('', '', 'submit', 'Create variant entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('inc-js-variants.php?chromosome=' . $_POST['chromosome']);
?>
      <SCRIPT type="text/javascript">

        $( '.transcript' ).each(function () {
            $(this).parent().parent().find(">:first-child").html('<INPUT class="ignore" name="ignore_' + $(this).attr('transcriptid') + '" type="checkbox"> <B>Ignore this transcript</B>');
        });

        $( '.ignore' ).click(function () {
            var oBeginTranscript = $(this).parent().parent().next();
            var oNextElement = oBeginTranscript.next();
            while (oNextElement.children().size() > 1) {
                // More than one TD, so it is an input field.
                if ($(this).attr('checked')) {
                    oNextElement.find(">:last-child").find(">:first-child").attr('disabled', true);
                } else {
                    oNextElement.find(">:last-child").find(">:first-child").removeAttr('disabled');
                }
                oNextElement = oNextElement.next();
            }
        });
        var aTranscripts = {
<?php
    if (isset($sGene)) {
        $i = 0;
        foreach($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGeneSymbol) = $aTranscript;
            echo ($i? ',' . "\n" : '') . '            \'' . $nTranscriptID . '\' : [\'' . $sTranscriptNM . '\', \'' . $sGeneSymbol . '\']';
            $i++;
        }
    }

    echo "\n" . '        };';

    foreach ($_POST as $key => $val) {
        if (substr($key, 0, 7) == 'ignore_') {
            // First check the checkbox. Then the event first triggers the click and THEN changes the checked state. Recheck the checkbox.
            echo '$( \'input[name="ignore_' . substr($key, 7, 5) . '"]\' ).attr(\'checked\', true).trigger(\'click\').attr(\'checked\', true);' . "\n";
        }
    }
?>

</SCRIPT>


<?php
    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /variants/0000000001?edit
    // Edit an entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Edit variant entry #' . $nID);
    define('LOG_EVENT', 'VariantEdit');

    // Require manager clearance.
    lovd_isAuthorized('variant', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    $bGene = false;
    $sGenes = $_DB->query('SELECT GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS geneids FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id = ? GROUP BY vot.id', array($nID))->fetchColumn();
    if (!empty($sGenes)) {
        $aGenes = explode(';', $sGenes);
        $bGene = true;
    }

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = array();
    $_DATA['Genome'] = new LOVD_GenomeVariant();
    $zData = $_DATA['Genome']->loadEntry($nID);
    $_POST['id'] = $nID;
    $_POST['chromosome'] = $zData['chromosome'];
    if ($bGene) {
        require ROOT_PATH . 'class/object_transcript_variants.php';
        foreach ($aGenes as $sGene) {
            if (lovd_isAuthorized('gene', $sGene)) {
                $_DATA['Transcript'][$sGene] = new LOVD_TranscriptVariant($sGene, $nID);
                $zData = array_merge($zData, $_DATA['Transcript'][$sGene]->loadAll($nID));
            }
        }
        $_POST['aTranscripts'] = $_DATA['Transcript'][$sGene]->aTranscripts;
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA['Genome']->checkFields($_POST);

        if ($bGene) {
            foreach ($aGenes as $sGene) {
                $_DATA['Transcript'][$sGene]->checkFields($_POST);
            }
        }

        if (!lovd_error()) {
            // Prepare the fields to be used for both genomic and transcript variant information.
            $aFieldsGenome = array_merge(
                                array('allele', 'effectid', 'edited_by', 'edited_date'),
                                $_DATA['Genome']->buildFields());

            // Prepare values.
            $_POST['effectid'] = $_POST['effect_reported'] . ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['effect_concluded'] : $zData['effectid']{1});
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFieldsGenome[] = 'owned_by';
                $aFieldsGenome[] = 'statusid';
            } elseif ($zData['statusid'] >= STATUS_MARKED) {
                $aFieldsGenome[] = 'statusid';
                $_POST['statusid'] = STATUS_MARKED;
            }

            require ROOT_PATH . 'class/REST2SOAP.php';
            $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
            if ($_POST['VariantOnGenome/DNA'] != $zData['VariantOnGenome/DNA'] || $zData['position_g_start'] == NULL) {
                $aFieldsGenome = array_merge($aFieldsGenome, array('position_g_start', 'position_g_end', 'type'));
                $aOutput = $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => 'NM_001100.3', 'variant' => $_POST['VariantOnGenome/DNA']));
                if (!empty($aOutput) && !$aOutput['errorcode'][0]['v']) {
                    $_POST['position_g_start'] = $aOutput['start_g'][0]['v'];
                    $_POST['position_g_end'] = $aOutput['end_g'][0]['v'];
                    $_POST['type'] = $aOutput['mutationType'][0]['v'];
                }
            }

            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // FIXME: implement versioning in updateEntry!
            lovd_queryDB_Old('BEGIN TRANSACTION');
            $_DATA['Genome']->updateEntry($nID, $_POST, $aFieldsGenome);

            if ($bGene) {
                foreach($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                    if (!empty($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) && ($_POST[$nTranscriptID . '_VariantOnTranscript/DNA'] != $zData[$nTranscriptID . '_VariantOnTranscript/DNA'] || $zData[$nTranscriptID . '_position_c_start'] === NULL)) {
                        if (strlen($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) < 6) {
                            $_POST[$nTranscriptID . '_position_c_start'] = 0;
                            $_POST[$nTranscriptID . '_position_c_start_intron'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end_intron'] = 0;
                        } else {
                            $aOutput = $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $aTranscript[0], 'variant' => $_POST[$nTranscriptID . '_VariantOnTranscript/DNA']));
                            if (!is_array($aOutput) && !empty($aOutput)) {
                                $_MutalyzerWS->soapError('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $aTranscript[0], 'variant' => $_POST[$nTranscriptID . '_VariantOnTranscript/DNA']), $aOutput);
                            } elseif (!empty($aOutput) && !$aOutput['errorcode'][0]['v']) {
                                $_POST[$nTranscriptID . '_position_c_start'] = $aOutput['startmain'][0]['v'];
                                $_POST[$nTranscriptID . '_position_c_start_intron'] = $aOutput['startoffset'][0]['v'];
                                $_POST[$nTranscriptID . '_position_c_end'] = $aOutput['endmain'][0]['v'];
                                $_POST[$nTranscriptID . '_position_c_end_intron'] = $aOutput['endoffset'][0]['v'];
                            } else {
                                $_POST[$nTranscriptID . '_position_c_start'] = 0;
                                $_POST[$nTranscriptID . '_position_c_start_intron'] = 0;
                                $_POST[$nTranscriptID . '_position_c_end'] = 0;
                                $_POST[$nTranscriptID . '_position_c_end_intron'] = 0;
                            }
                        }
                    } else {
                        $_POST[$nTranscriptID . '_position_c_start'] = $zData[$nTranscriptID . '_position_c_start'];
                        $_POST[$nTranscriptID . '_position_c_start_intron'] = $zData[$nTranscriptID . '_position_c_start_intron'];
                        $_POST[$nTranscriptID . '_position_c_end'] = $zData[$nTranscriptID . '_position_c_end'];
                        $_POST[$nTranscriptID . '_position_c_end_intron'] = $zData[$nTranscriptID . '_position_c_end_intron'];
                    }
                }
                $aFieldsTranscripts = array();
                foreach ($aGenes as $sGene) {
                    $aFieldsTranscripts[$sGene] = array_merge(array('effectid', 'position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron'), $_DATA['Transcript'][$sGene]->buildFields());
                }
                $aTranscriptID = $_DATA['Transcript'][$sGene]->updateAll($nID, $_POST, $aFieldsTranscripts);
            }
            lovd_queryDB_Old('COMMIT');

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited variant entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the variant entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
        $_POST['effect_reported'] = $zData['effectid']{0};
        $_POST['effect_concluded'] = $zData['effectid']{1};
        if ($bGene) {
            foreach ($aGenes as $sGene) {
                foreach($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
                    $_POST[$nTranscriptID . '_effect_reported'] = $zData[$nTranscriptID . '_effectid']{0};
                    $_POST[$nTranscriptID . '_effect_concluded'] = $zData[$nTranscriptID . '_effectid']{1};
                }
            }
        }
    }



    

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To edit a variant entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM id="variantForm" action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA['Genome']->getForm(),
                 array(
                        array('', '', 'submit', 'Edit variant entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('inc-js-variants.php?chromosome=' . $zData['chromosome']);

    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        var aTranscripts = ');

    if ($bGene) {
        print('{' . "\n");
        $i = 0;
        foreach($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGeneSymbol) = $aTranscript;
            echo ($i? ',' . "\n" : '') . '            \'' . $nTranscriptID . '\' : [\'' . $sTranscriptNM . '\', \'' . $sGeneSymbol . '\']';
            $i++;
        }
        print("\n" . '        }');
    } else {
        print('false');
    }
    print(';' . "\n\n" .
          '        $( function () {' . "\n" .
          '          var aNewTranscripts = window.location.hash.substring(1).split(",");' . "\n" .
          '          for (i in aNewTranscripts) {' . "\n" .
          '            var oNewTranscript = $(\'.transcript[transcriptid="\' + aNewTranscripts[i] + \'"]\');' . "\n" .
          '            $(oNewTranscript).html($(oNewTranscript).html() + \'<BR>(<I>Newly added transcript</I>)\');' . "\n" .
          '            $(oNewTranscript).attr("style", "color:red");' . "\n" .
          '          }' . "\n" .
          '          for (i in aTranscripts) {' . "\n" .
          '            if ($.inArray(i, aNewTranscripts) != -1) {' . "\n" .
          '              var oNewTranscript = $(\'.transcript[transcriptid="\' + i + \'"]\');' . "\n" .
          '              newPosition = $(oNewTranscript).offset();' . "\n" .
          '              window.scrollTo(newPosition.left, newPosition.top);' . "\n" .
          '              break;' . "\n" .
          '            }' . "\n" .
          '          }' . "\n" .
          '        });' . "\n" .
          '      </SCRIPT>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /variants/0000000001?delete
    // Drop specific entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Delete variant entry #' . $nID);
    define('LOG_EVENT', 'VariantDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
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
            // This also deletes the entries in variants_on_transcripts.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted variant entry #' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the variant entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
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
                        array('Deleting variant entry', '', 'print', $nID),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete variant entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'map') {
    // URL: /variants/0000000001?map
    // Map a variant to additional transcript.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Map variant entry #' . $nID);
    define('LOG_EVENT', 'VariantMap');

    // Require manager clearance.
    lovd_isAuthorized('variant', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->loadEntry($nID);
    // Load all transcript ID's that are currently present in the database connected to this variant.
    $aCurrentTranscripts = $_DB->query('SELECT t.id FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid', array($nID))->fetchAllColumn();

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_POST['transcripts'] stores the IDs of the transcripts that are supposed to go in TABLE_VARIANTS_ON_TRANSCRIPTS.
        if (empty($_POST['transcripts']) || !is_array($_POST['transcripts'])) {
            $_POST['transcripts'] = array();
        } else {
            $aTranscripts = $_DB->query('SELECT t.id FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) WHERE g.chromosome = ? AND t.id IN (?' . str_repeat(', ?', count($_POST['transcripts']) - 1) . ')', array_merge(array($zData['chromosome']), $_POST['transcripts']))->fetchAllColumn();
            foreach ($_POST['transcripts'] as $nTranscript) {
                if (!in_array($nTranscript, $aTranscripts)) {
                    // The user tried to fake a $_POST by inserting an ID that did not come from our code.
                    lovd_errorAdd('', 'Invalid transcript, please select one from the top viewlist!');
                    break;
                }
            }
        }

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DB->beginTransaction();

            $aNewTranscripts = array();
            $aToRemove = array();
            $aVariantDescriptions = array();
            require ROOT_PATH . 'class/REST2SOAP.php';
            $_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);

            foreach ($_POST['transcripts'] as $nTranscript) {
                if ($nTranscript && !in_array($nTranscript, $aCurrentTranscripts)) {
                    // If the transcript is not already present in the database connected to this variant, we will add it now.
                    $aNewTranscripts[] = $nTranscript;
                    // Gather all necessary info from this transcript.
                    $zTranscript = $_DB->query('SELECT id, geneid, name, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($nTranscript))->fetchAssoc();
                    // Call the numberConversion module of mutalyzer to get the VariantOnTranscript/DNA value for this variant on this transcript.
                    // Check if we already have the converted positions for this gene, if so, we won't have to call mutalyzer again for this information.
                    if (!array_key_exists($zTranscript['geneid'], $aVariantDescriptions)) {
                        $aVariantDescriptions[$zTranscript['geneid']] = $_MutalyzerWS->moduleCall('numberConversion', array('build' => $_CONF['refseq_build'], 'variant' => 'chr' . $zData['chromosome'] . ':' . $zData['VariantOnGenome/DNA'], 'gene' => $zTranscript['geneid']));
                    }

                    if (isset($aVariantDescriptions[$zTranscript['geneid']]['string']) && is_array($aVariantDescriptions[$zTranscript['geneid']]['string'])) {
                        // Loop through the mutalyzer output for this gene.
                        foreach($aVariantDescriptions[$zTranscript['geneid']]['string'] as $key => $aVariant) {
                            // Check if our transcript is in the variant description for each value returned by mutalyzer.
                            if (!empty($aVariant['v']) && preg_match('/^' . preg_quote($zTranscript['id_ncbi']) . ':(c\..+)$/', $aVariant['v'], $aMatches)) {
                                // Call the mappingInfo module of mutalyzer to get the start & stop positions of this variant on the transcript.
                                $aMapping = $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $zTranscript['id_ncbi'], 'variant' => $aMatches[1]));
                                if (!empty($aMapping) && !$aMapping['errorcode'][0]['v']) {
                                    $aMapping['position_c_start'] = $aMapping['startmain'][0]['v'];
                                    $aMapping['position_c_start_intron'] = $aMapping['startoffset'][0]['v'];
                                    $aMapping['position_c_end'] = $aMapping['endmain'][0]['v'];
                                    $aMapping['position_c_end_intron'] = $aMapping['endoffset'][0]['v'];
                                } else {
                                    $aMapping['position_c_start'] = 0;
                                    $aMapping['position_c_start_intron'] = 0;
                                    $aMapping['position_c_end'] = 0;
                                    $aMapping['position_c_end_intron'] = 0;
                                }
                                // Insert all the gathered information about the variant description into the database.
                                $_DB->query('INSERT INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . '(id, transcriptid, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, effectid, `VariantOnTranscript/DNA`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array($nID, $nTranscript, $aMapping['position_c_start'], $aMapping['position_c_start_intron'], $aMapping['position_c_end'], $aMapping['position_c_end_intron'], '55', $aMatches[1]));
                                // Remove this value from the output from mutalyzer, so we will not check this one again with the next transcript that we will add.
                                unset($aVariantDescriptions[$zTranscript['geneid']]['string'][$key]);
                                break;
                            }
                        }
                    }
                }
            }

            foreach ($aCurrentTranscripts as $nTranscript) {
                if (!in_array($nTranscript, $_POST['transcripts'])) {
                    // If one of the transcripts currently present in the database is not present in $_POST, we will want to remove it.
                    $aToRemove[] = $nTranscript;
                }
            }

            if (!empty($aToRemove)) {
                // Remove transcript mapping from variant...
                $_DB->query('DELETE FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE id = ? AND transcriptid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove));
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated the transcript list for variant #' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nID . (!empty($aNewTranscripts)? '?edit#' . implode(',', $aNewTranscripts) : ''));

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully updated the transcript list!', 'success');

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
    lovd_showInfoTable('The variant entry is currently NOT mapped to the following transcripts. Click on a transcript to map the variant to it.', 'information');

    if (POST) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // Retrieve data for selected transcripts.
        if (!empty($_POST['transcripts'])) {
            $aVOT = $_DB->query('SELECT t.id, t.geneid, t.name, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) WHERE g.chromosome = ? AND t.id IN (?' . str_repeat(', ?', count($_POST['transcripts']) - 1) . ')', array_merge(array($zData['chromosome']), $_POST['transcripts']))->fetchAllAssoc();
        } else {
            $aVOT = array();
        }
    } else {
        $aVOT = $_DB->query('SELECT t.id, t.geneid, t.name, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid, id_ncbi', array($nID))->fetchAllAssoc();
    }

    $_GET['page_size'] = 10;
    $_GET['search_id_'] = '';
    foreach ($aVOT as $aTranscript) {
        $_GET['search_id_'] .= '!' . $aTranscript['id'] . ' ';
    }
    // FIXME; maybe also check if the variant is close to the transcripts?
    $_GET['search_id_'] = (!empty($_GET['search_id_'])? rtrim($_GET['search_id_']) : '!0');
    $_GET['search_chromosome'] = '="' . $zData['chromosome'] . '"';
    require ROOT_PATH . 'class/object_transcripts.php';
    $_DATA = new LOVD_Transcript();
    $_DATA->setRowLink('VOT_map', 'javascript:lovd_addTranscript(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_geneid}}\', \'{{zData_name}}\', \'{{zData_id_ncbi}}\'); return false;');
    $_DATA->viewList('VOT_map', array('id_', 'chromosome'), true);
    print('      <BR><BR>' . "\n\n");

    lovd_showInfoTable('The variant entry is currently mapped to the following transcripts. Click on the cross at the right side of the transcript to remove the mapping.', 'information');

    print('      <TABLE class="sortable_head" style="width : 552px;"><TR><TH width="100">Gene</TH>' .
          '<TH style="text-align : left;">Name</TH><TH width="123" style="text-align : left;">Transcript ID</TH><TH width="20">&nbsp;</TH>' .
          '</TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
          '        <UL id="transcript_list" class="sortable" style="margin-top : 0px; width : 550px;">' . "\n");
    // Now loop the items in the order given.
    foreach ($aVOT as $aTranscript) {
        print('          <LI id="li_' . $aTranscript['id'] . '"><INPUT type="hidden" name="transcripts[]" value="' . $aTranscript['id'] . '"><TABLE width="100%"><TR><TD width="98">' . $aTranscript['geneid'] . '</TD>' .
              '<TD align="left">' . $aTranscript['name'] . '</TD><TD width="120" align="left">' . $aTranscript['id_ncbi'] . '</TD><TD width="20" align="right"><A href="#" onclick="lovd_removeTranscript(\'VOT_map\', \'' . $aTranscript['id'] . '\', \'' . $aTranscript['id_ncbi'] . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD>' .
              '</TR></TABLE></LI>' . "\n");
    }
    print('        </UL>' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '0%', '0', '100%'),
                    array('', '', 'print', 'Enter your password for authorization'),
                    array('', '', 'password', 'password', 20),
                    array('', '', 'print', '<INPUT type="submit" value="Save transcript list">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="document.location.href=\'' . lovd_getInstallURL() . 'variants/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">'),
                  );
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");
?>
      <SCRIPT type='text/javascript'>
        function lovd_addTranscript (sViewListID, nID, sGene, sName, sNM)
        {
            // Moves the transcript to the variant mapping block and removes the row from the viewList.
            objViewListF = document.getElementById('viewlistForm_' + sViewListID);
            objElement = document.getElementById(nID);
            objElement.style.cursor = 'progress';

            objUsers = document.getElementById('transcript_list');
            oLI = document.createElement('LI');
            oLI.id = 'li_' + nID;
            oLI.innerHTML = '<INPUT type="hidden" name="transcripts[]" value="' + nID + '"><TABLE width="100%"><TR><TD width="98">' + sGene + '</TD><TD align="left">' + sName + '</TD><TD width="120" align="left">' + sNM + '</TD><TD width="20" align="right"><A href="#" onclick="lovd_removeTranscript(\'VOT_map\', \'' + nID + '\', \'' + sNM + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE>';
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
            objViewListF.search_id_.value += ' !' + nID;
            // Does an ltrim, too. But trim() doesn't work in IE < 9.
            objViewListF.search_id_.value = objViewListF.search_id_.value.replace(/^\s*/, '');
            return true;
        }


        function lovd_removeTranscript (sViewListID, nID, sNM)
        {
            var aCurrentTranscripts = '<?php echo implode(';', $aCurrentTranscripts) ?>'.split(";");
            if ($.inArray(nID, aCurrentTranscripts) == -1 || window.confirm('You are about to remove the variant description of transcript ' + sNM + ' from this variant.\n\nOk:\t\tRemove variant description of this transcript from the database.\nCancel:\tCancel the removal.')) {
                // Removes the mapping of the variant from this transcript and reloads the viewList with the transcript back in there.
                objViewListF = document.getElementById('viewlistForm_' + sViewListID);
                objLI = document.getElementById('li_' + nID);

                // First remove from block, simply done (no fancy animation).
                objLI.parentNode.removeChild(objLI);

                // Reset the viewList.
                // Does an ltrim, too. But trim() doesn't work in IE < 9.
                objViewListF.search_id_.value = objViewListF.search_id_.value.replace('!' + nID, '').replace('  ', ' ').replace(/^\s*/, '');
                lovd_AJAX_viewListSubmit(sViewListID);

                return true;
            } else {
                return false;
            }
        }
      </SCRIPT>
<?php
    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
