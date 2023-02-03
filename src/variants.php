<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2023-02-03
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
 *               Zuotian Tatum <Z.Tatum@LUMC.nl>
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





function lovd_getMaxVOTEffects ($sType, $zData = array())
{
    // Loops $zData (typically $_POST) to find the max VOT effect.

    if (!in_array($sType, array('reported', 'concluded'))) {
        return false;
    }
    if (!is_array($zData)) {
        return false;
    }

    $aEffects = array();
    foreach (array_keys($zData) as $sKey) {
        if (preg_match('/^\d+_effect_' . $sType . '$/', $sKey)) {
            $aEffects[] = $zData[$sKey];
        }
    }

    if (!$aEffects) {
        return false;
    }

    $nMax = max($aEffects);
    // We cannot return "(Probably) does not affect function"
    //  if one value is also "Not classified".
    if ($nMax < 5 && in_array('0', $aEffects)) {
        $nMax = 0;
    }

    return $nMax;
}





if (!ACTION && !empty($_GET['select_db'])) {
    // Old way of linking to LOVD2s.
    if (!empty($_GET['trackid']) && substr_count($_GET['trackid'], ':')) {
        // URL: /variants.php?select_db=IVD&action=search_all&trackid=IVD%3Ac.465%2B1G>A
        // Old LOVD2-style way of linking from the genome browsers back to LOVD.
        $aTrackID = explode(':', $_GET['trackid'], 2);
        $sDNA = $aTrackID[1];
        // Using this style of linking (not using variants/GENE) we allow all transcripts to show up. We of course don't know which transcript the variant is mapped to.
        //   Downside is of course too much focus on the genomic side of the variant, and perhaps too many columns.
        header('Location: ' . lovd_getInstallURL() . 'variants/in_gene?search_geneid=%3D%22' . $_GET['select_db'] . '%22&search_VariantOnTranscript/DNA=%3D%22' . rawurlencode($sDNA) . '%22');
    } elseif (!empty($_GET['action']) && $_GET['action'] == 'search_all' && !empty($_GET['search_Variant/DBID'])) {
        // URL: /variants.php?select_db=IVD&action=search_all&search_Variant%2FDBID=IVD_000010
        // Old LOVD2-style way of linking from the LOVD world-wide search interface. That interface doesn't actually know what is LOVD2 and what is LOVD3...
        // Using this style of linking (not using variants/GENE) we allow all transcripts to show up. We of course don't know which transcript the variant is mapped to.
        //   Downside is of course too much focus on the genomic side of the variant, and perhaps too many columns.
        header('Location: ' . lovd_getInstallURL() . 'variants/in_gene?search_geneid=%3D%22' . $_GET['select_db'] . '%22&search_VariantOnGenome/DBID=%3D%22' . rawurlencode($_GET['search_Variant/DBID']) . '%22');
    }
    exit;
}





if (!ACTION && (empty($_PE[1])
        || preg_match('/^(chr[0-9A-Z]{1,2})(?::([0-9]+)-([0-9]+))?$/', $_PE[1], $aRegionArgs))) {
    // URL: /variants
    // URL: /variants/chrX
    // URL: /variants/chr3:20-200000
    // View all variant entries on the genome level, optionally restricted by chromosome or genomic range.

    // Managers are allowed to download this list...
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    require_once ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $aColsToHide = array('allele_');
    $sTitle = 'All variants';

    // Show page with variant VL.
    define('PAGE_TITLE', $sTitle);
    $_T->printHeader();
    $_T->printTitle();

    // Set conditions on VL if a region is specified (e.g. chr3:20-200000).
    if (isset($aRegionArgs)) {
        list($sRegion, $sChr, $sPositionStart, $sPositionEnd) = array_pad($aRegionArgs, 4, null);

        // Set search condition for chromosome.
        $_GET['search_chromosome'] = '="' . substr($sChr, 3) . '"';
        $aColsToHide[] = 'chromosome';

        if (!is_null($sPositionStart) && !is_null($sPositionEnd)) {
            // Set search conditions for start and end of region.
            $_GET['search_position_g_start'] = '>=' . $sPositionStart;
            $_GET['search_position_g_end'] = '<=' . $sPositionEnd;
            $sTitle .= ' in region ' . $sRegion;
        } else {
            $sTitle .= ' on chromosome ' . substr($sChr, 3);
        }

    } elseif ($_SETT['customization_settings']['variants_VL_per_chromosome_only']) {
        // Optimize for speed; show a list of chromosomes with variant counts
        //  instead of the Variant VL for the whole genome.
        print('Please select a chromosome to view the variant listing.<BR><BR>' . "\n");
        $aChromosomes = $_DB->q('
            SELECT c.name, COUNT(vog.id)
            FROM ' . TABLE_CHROMOSOMES . ' AS c
              LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (c.name = vog.chromosome)' .
            ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' :
                ' WHERE vog.statusid >= ' . STATUS_MARKED) . '
            GROUP BY c.name
            ORDER BY c.sort_id')->fetchAllCombine();
        print('
      <TABLE border="0" cellpadding="0" cellspacing="1" class="data">
        <TR>
          <TH valign="top" class="ordered">Chromosome</TH>
          <TH valign="top">Variants</TH></TR>');
        foreach ($aChromosomes as $sChr => $nVariants) {
            print('
        <TR class="data" valign="top" style="cursor : pointer;" onclick="window.location.href = \'' . $_PE[0] . '/chr' . $sChr . '\';">
          <TD class="ordered"><A href="' . $_PE[0] . '/chr' . $sChr . '" class="hide">' . $sChr . '</A></TD>
          <TD>' . $nVariants . '</TD></TR>');
        }
        print('</TABLE>' . "\n\n");
        $_T->printFooter();
        exit;
    }

    $aVLOptions = array(
        'cols_to_skip' => $aColsToHide,
        'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER),
        'find_and_replace' => true,
        'curate_set' => true,
    );
    $_DATA->viewList('VOG', $aVLOptions);
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && $_PE[1] == 'in_gene' && !ACTION
    && (!(LOVD_plus || LOVD_light) || (!empty($_GET['search_geneid']) && !empty($_GET['search_VariantOnTranscript/DNA'])))) {
    // URL: /variants/in_gene
    // View all entries effecting a transcript.

    // Managers are allowed to download this list...
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'All variants affecting transcripts');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_custom_viewlists.php';
    $_DATA = new LOVD_CustomViewList(array('Transcript', 'VariantOnTranscript', 'VariantOnGenome'));
    $aVLOptions = array(
        'cols_to_skip' => array('name', 'id_protein_ncbi'),
        'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER),
        'curate_set' => true,
    );
    $_DATA->viewList('CustomVL_IN_GENE', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'upload' && ctype_digit($_PE[2]) && !ACTION) {
    // URL: /variants/upload/123451234567890
    // View all variant entries on the genome level that were submitted in the given upload.

    $nID = sprintf('%015d', $_PE[2]);
    define('PAGE_TITLE', 'All variants from upload #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $_GET['search_created_by'] = substr($nID, 0, 5);
    $_GET['search_created_date'] = date('Y-m-d H:i:s', substr($nID, 5, 10));
    $aVLOptions = array(
        'cols_to_skip' => array('allele_'),
        'show_options' => ($_AUTH['level'] >= LEVEL_MANAGER),
        'find_and_replace' => true,
    );
    $_DATA->viewList('VOG_uploads', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (!ACTION && !empty($_PE[1]) && !ctype_digit($_PE[1])) {
    // URL: /variants/DMD
    // URL: /variants/DMD/unique
    // URL: /variants/DMD/NM_004006.2
    // URL: /variants/DMD/NM_004006.2/unique
    // View all entries in a specific gene, affecting a specific transcript.

    $bUnique = false;
    if ((isset($_PE[2]) && $_PE[2] == 'unique') || (isset($_PE[3]) && $_PE[3] == 'unique')) {
        $bUnique = true;
    }

    $qGene = $_DB->q('
        SELECT g.id, COUNT(t.id)
        FROM ' . TABLE_GENES . ' AS g
          LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON g.id = t.geneid
        WHERE g.id = ?
        GROUP BY g.id', array($_PE[1]));
    list($sGene, $nTranscripts) = $qGene->fetchRow();

    if ($sGene) {
        lovd_isAuthorized('gene', $sGene); // To show non public entries.

        // Curators are allowed to download this list...
        if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
            define('FORMAT_ALLOW_TEXTPLAIN', true);
        }

        // Overview is given per transcript. If there is only one, it will be mentioned.
        // If there are more, you will be able to select which one you'd like to see.
        $aTranscriptsWithVariants = $_DB->q(
            'SELECT t.id, t.id_ncbi
             FROM ' . TABLE_TRANSCRIPTS . ' AS t
               INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)
             WHERE t.geneid = ?
             GROUP BY t.id
             ORDER BY COUNT(vot.id) DESC, t.id ASC', array($sGene))->fetchAllCombine();
        $nTranscriptsWithVariants = count($aTranscriptsWithVariants);

        // If NM is mentioned, check if exists for this gene. If not, reload page without NM. Otherwise, restrict $aTranscriptsWithVariants.
        if (!empty($_PE[2]) && $_PE[2] != 'unique') {
            $nTranscript = array_search($_PE[2], $aTranscriptsWithVariants);
            if ($nTranscript === false) {
                // NM does not exist, or has no variants. Throw error or just simply redirect?
                header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1] . (!$bUnique? '' : '/unique'));
                exit;
            } else {
                $aTranscriptsWithVariants = array($nTranscript => $aTranscriptsWithVariants[$nTranscript]);
                $nTranscriptsWithVariants = 1;
            }
        }
    } else {
        // Command or gene not understood.
        // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
        //   test if browsers show that output or their own error page. Also, then, use the same method at
        //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
        exit;
    }

    if ($bUnique) {
        define('PAGE_TITLE', 'Unique variants in the ' . $sGene . ' gene');
        $sViewListID = 'CustomVL_VOTunique_VOG_' . $sGene;
    } else {
        define('PAGE_TITLE', 'All variants in the ' . $sGene . ' gene');
        $sViewListID = 'CustomVL_VOT_VOG_' . $sGene;
    }
    $_T->printHeader();
    $_T->printTitle();
    lovd_printGeneHeader();


    // If this gene has only one NM, show that one. Otherwise have people pick one.
    $nTranscriptID = key($aTranscriptsWithVariants);
    $sTranscript = current($aTranscriptsWithVariants);
    if (!$nTranscripts) {
        $sMessage = 'No transcripts found for this gene.';
    } elseif (!$nTranscriptsWithVariants) {
        $sMessage = 'No variants found for this gene.';
    } elseif ($nTranscriptsWithVariants == 1) {
        $_GET['search_transcriptid'] = $nTranscriptID;
        $sMessage = 'The variants shown are described using the ' . $sTranscript . ' transcript reference sequence.';
    } else {
        // Create select box.
        // We would like to be able to link to this list, focusing on a certain transcript but without restricting the viewer, by sending a (numeric) get_transcriptid search term.
        if (!isset($_GET['search_transcriptid']) || !isset($aTranscriptsWithVariants[$_GET['search_transcriptid']])) {
            $_GET['search_transcriptid'] = $nTranscriptID;
        }
        $sSelect = '<SELECT id="change_transcript" onchange="$(\'input[name=\\\'search_transcriptid\\\']\').val($(this).val()); lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');">';
        foreach ($aTranscriptsWithVariants as $nTranscriptID => $sTranscript) {
            $sSelect .= '<OPTION value="' . $nTranscriptID . '"' . ($_GET['search_transcriptid'] != $nTranscriptID? '' : ' selected') . '>' . $sTranscript . '</OPTION>';
        }
        $sSelect .= '</SELECT>';
        $sMessage = 'The variants shown are described using the ' . $sSelect . ' transcript reference sequence.';
    }
    if (FORMAT == 'text/html') {
        lovd_showInfoTable($sMessage);
    }

    if ($nTranscriptsWithVariants > 0) {
        require ROOT_PATH . 'class/object_custom_viewlists.php';
        if ($bUnique) {
            // When this ViewListID is changed, also change the prepareData in object_custom_viewluists.php
            $_DATA = new LOVD_CustomViewList(array('VariantOnTranscriptUnique', 'VariantOnGenome'), $sGene); // Restrict view to gene (correct custom column set, correct order).
            $_DATA->setRowLink($sViewListID, str_replace('/unique', '', CURRENT_PATH) . '?search_position_c_start={{position_c_start}}&search_position_c_start_intron={{position_c_start_intron}}&search_position_c_end={{position_c_end}}&search_position_c_end_intron={{position_c_end_intron}}&search_vot_clean_dna_change=%3D%22{{vot_clean_dna_change}}%22&search_transcriptid={{transcriptid}}');
        } else {
            $_DATA = new LOVD_CustomViewList(array('VariantOnTranscript', 'VariantOnGenome'), $sGene); // Restrict view to gene (correct custom column set, correct order).
        }

        $_DATA->sSortDefault = 'VariantOnTranscript/DNA';
        $aVLOptions = array(
            'cols_to_skip' => array('chromosome', 'allele_'), // Enforced for unique view in the object.
            'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
            'find_and_replace' => !$bUnique,
            'multi_value_filter' => $bUnique,
            'curate_set' => !$bUnique,
        );
        $_DATA->viewList($sViewListID, $aVLOptions);

        // Notes for the variant listings...
        if (!empty($_SETT['currdb']['note_listing'])) {
            print($_SETT['currdb']['note_listing'] . '<BR><BR>' . "\n\n");
        }
    }

    lovd_printGeneFooter();
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /variants/0000000001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();


?>
      <SCRIPT type="text/javascript">
        $(function ()
            {
                $('#mapOnRequest').prepend('&nbsp;&nbsp;<IMG style="display:none;">&nbsp;&nbsp;');
            }
        );

        function lovd_mapOnRequest ()
        {
            // Show the loading image.
            $('#mapOnRequest').children("img").first().attr({
                src: '<?php echo ROOT_PATH; ?>gfx/lovd_loading.gif',
                width: '12px',
                height: '12px',
                alt: 'Loading...',
                title: 'Loading...'
            }).show();

            // Call the script.
            $.get('<?php echo ROOT_PATH . 'ajax/map_variants.php?variantid=' . $nID; ?>', function ()
                {
                    // Reload the page on success.
                    window.location.reload();
                }
            ).fail(function ()
                {
                    // Show the error image.
                    $('#mapOnRequest').children("img").first().attr({
                        src: '<?php echo ROOT_PATH; ?>gfx/cross.png',
                        alt: 'Error',
                        title: 'An error occurred, please try again'
                    });
                }
            );
            return false;
        }
      </SCRIPT>
<?php

    lovd_isAuthorized('variant', $nID);
    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->viewEntry($nID);

    $bAuthorized = ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER);
    // However, for LOVD+, depending on the status of the screening, we might not have the rights to edit the variant.
    if (LOVD_plus && $bAuthorized) {
        $zScreenings = $_DB->q('SELECT s.* FROM ' . TABLE_SCREENINGS . ' AS s INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid = ? GROUP BY s.id', array($nID))->fetchAllAssoc();
        if ($zScreenings &&
            !($_AUTH['level'] >= LEVEL_OWNER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) &&
            !($_AUTH['level'] >= LEVEL_MANAGER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION) &&
            !($_AUTH['level'] >= LEVEL_ADMIN && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_CONFIRMED)) {
            $bAuthorized = false;
        }
    }

    $aNavigation = array();
    if ($bAuthorized) {
        // Authorized user is logged in. Provide tools.
        if (!LOVD_plus) {
            $aNavigation[CURRENT_PATH . '?edit']       = array('menu_edit.png', 'Edit variant entry', 1);
            if ($zData['statusid'] < STATUS_OK && $_AUTH['level'] >= LEVEL_CURATOR) {
                $aNavigation[CURRENT_PATH . '?publish'] = array('check.png', ($zData['statusid'] == STATUS_MARKED? 'Remove mark from' : 'Publish (curate)') . ' variant entry', 1);
            }
        } else {
            if (!lovd_verifyInstance('leiden')) {
                $aNavigation[CURRENT_PATH . '?curate' . (isset($_GET['in_window'])? '&amp;in_window' : '')] = array('menu_edit.png', 'Curate this variant', 1);
            }
            $aNavigation['javascript:lovd_openWindow(\'' . lovd_getInstallURL() . CURRENT_PATH . '?curation_log&in_window\', \'curation_log\', 1050, 450);'] = array('menu_clock.png', 'Show curation history', 1);
            // Menu items for setting the curation status.
            foreach ($_SETT['curation_status'] as $nCurationStatusID => $sCurationStatus) {
                $aCurationStatusMenu['javascript:' .
                    '$.get(\'ajax/set_curation_status.php?' . $nCurationStatusID . '&id=' . $nID . '\', ' .
                        'function(sResponse){' .
                            'if(sResponse.substring(0,1) == \'1\'){' .
                                'alert(\'Successfully set curation status of this variant to \\\'' . $sCurationStatus . '\\\'.\');window.location.reload();' .
                                (!isset($_GET['in_window'])? '' : 'window.opener.lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');window.opener.lovd_AJAX_viewEntryLoad();') .
                            '}else if(sResponse.substring(0,1) == \'9\'){' .
                                'alert(\'Error: \' + sResponse.substring(2));}})' .
                    '.fail(function(){alert(\'Error while setting curation status.\');});'] = array('menu_edit.png', $sCurationStatus);
            }
            $aCurationStatusMenu['javascript:' .
                'if(window.confirm(\'Are you sure you want to clear this variants curation status?\')){' .
                    '$.get(\'ajax/set_curation_status.php?clear&id=' . $nID . '\', ' .
                        'function(sResponse){' .
                            'if(sResponse.substring(0,1) == \'1\'){' .
                                'alert(\'Successfully cleared the curation status of this variant.\');window.location.reload();' .
                                (!isset($_GET['in_window'])? '' : 'window.opener.lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');window.opener.lovd_AJAX_viewEntryLoad();') .
                            '}else if(sResponse.substring(0,1) == \'9\'){' .
                                'alert(\'Error: \' + sResponse.substring(2));}})' .
                    '.fail(function(){alert(\'Error while setting curation status.\');});' .
                '}else{' .
                    'alert(\'This variants curation status has not been changed.\');}'] = array('cross.png', 'Clear curation status');
            $aNavigation['curation_status'] = array('menu_edit.png', 'Set curation status', 1, 'sub_menu' => $aCurationStatusMenu);
            // Menu items for setting the confirmation status.
            foreach ($_SETT['confirmation_status'] as $nConfirmationStatusID => $sConfirmationStatus) {
                $aConfirmationStatusMenu['javascript:' .
                    '$.get(\'ajax/set_confirmation_status.php?' . $nConfirmationStatusID . '&id=' . $nID . '\', ' .
                        'function(sResponse){' .
                            'if(sResponse.substring(0,1) == \'1\'){' .
                                'alert(\'Successfully set confirmation status of this variant to \\\'' . $sConfirmationStatus . '\\\'.\');window.location.reload();' .
                                (!isset($_GET['in_window'])? '' : 'window.opener.lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');window.opener.lovd_AJAX_viewEntryLoad();') .
                            '}else if(sResponse.substring(0,1) == \'9\'){' .
                                'alert(\'Error: \' + sResponse.substring(2));}})' .
                    '.fail(function(){alert(\'Error while setting confirmation status.\');});'] = array('menu_edit.png', $sConfirmationStatus);
            }
            $aNavigation['confirmation_status'] = array('menu_edit.png', 'Set confirmation status', 1, 'sub_menu' => $aConfirmationStatusMenu);
            if (lovd_verifyInstance('leiden')) {
                $aNavigation[CURRENT_PATH . '?edit_remarks' . (isset($_GET['in_window'])? '&amp;in_window' : '')] = array('menu_edit.png', 'Edit remarks', 1);
            }
        }
        $aNavigation[CURRENT_PATH . '?map']        = array('menu_transcripts.png', 'Manage transcripts for this variant', 1);
        if (LOVD_plus && $_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?delete_non-preferred_transcripts'] = array('menu_transcripts.png', 'Delete non-preferred transcripts', 1);
        }
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['delete_variant']) {
            $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete variant entry', 1);
        }
    }
    if (!empty($zData['position_g_start']) && $_CONF['refseq_build'] != '----') {
        if ($bAuthorized) {
            $aNavigation['javascript:lovd_openWindow(\'' . lovd_getInstallURL() . CURRENT_PATH . '?search_global\', \'global_search\', 900, 450);'] = array('menu_magnifying_glass.png', 'Search public LOVDs', 1);
        }
        $lVariant = abs($zData['position_g_end'] - $zData['position_g_start']);
        $lMargin = ($lVariant > 20? 5 : round((30 - $lVariant)/2));
        // FIXME; Once this navigation menu supports multi-level menu's, add this in a sub level.
        // FIXME: Add the BED file here?
        $aNavigation['javascript:lovd_openWindow(\'http://genome.ucsc.edu/cgi-bin/hgTracks?clade=mammal&amp;org=Human&amp;db=' . $_CONF['refseq_build'] . '&amp;position=chr' . $zData['chromosome'] . ':' . ($zData['position_g_start'] - $lMargin) . '-' . ($zData['position_g_end'] + $lMargin) . '&amp;width=800&amp;ruler=full&amp;ccdsGene=full\', \'variant_UCSC\', 1000, 500);'] = array('menu_magnifying_glass.png', 'View location in UCSC genome browser', 1);
        $sURLEnsembl = 'http://' . ($_CONF['refseq_build'] == 'hg18'? 'may2009.archive' : ($_CONF['refseq_build'] == 'hg19'? 'grch37' : 'www')) . '.ensembl.org/Homo_sapiens/Location/View?r=' . $zData['chromosome'] . ':' . ($zData['position_g_start'] - $lMargin) . '-' . ($zData['position_g_end'] + $lMargin);
        $aNavigation['javascript:lovd_openWindow(\'' . $sURLEnsembl . '\', \'variant_Ensembl\', 1000, 500);'] = array('menu_magnifying_glass.png', 'View location in Ensembl genome browser', 1);
        // Link to MobiDetails, but only if this variant has a gene. We'll know that only a few lines later when we load the VOT object.
        // To prevent yet another query, handled this with JS further below.
        $aNavigation['javascript:$.post(\'ajax/mobidetails.php/' . $nID . '?check\').fail(function(){alert(\'Error while preparing to check MobiDetails.\');});'] = array('menu_mobidetails.png', 'View variant in MobiDetails', 1);
    }
    lovd_showJGNavigation($aNavigation, 'Variants');

    print('      <BR><BR>' . "\n\n" .
          '      <DIV id="viewentryDiv">' . "\n" .
          '      </DIV>' . "\n\n");

    $_GET['search_id'] = $nID;
    print('      <BR><BR>' . "\n\n");
    $_T->printTitle('Variant on transcripts', 'H4');
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = new LOVD_TranscriptVariant('', $nID);
    $sViewListID = 'VOT_for_VOG_VE';
    $_DATA->setRowID($sViewListID, 'VOT_{{transcriptid}}');
    $_DATA->setRowLink($sViewListID, 'javascript:window.location.hash = \'{{transcriptid}}\'; return false');
    $aVLOptions = array(
        'cols_to_skip' => array('id_', 'transcriptid', 'status'),
        'track_history' => false,
        'show_navigation' => false,
    );
    if (LOVD_plus) {
        // LOVD+ adds a check whether the transcript is a preferred transcript in any gene panel.
        $_DATA->appendRowClass(function($zData) {
            if (!empty($zData['genepanelid'])) {
                return 'preferred-transcript';
            }
            return '';
        });
    }
    $_DATA->viewList($sViewListID, $aVLOptions);
    unset($_GET['search_id']);
?>

      <SCRIPT type="text/javascript">
        var prevHash = '';
        $( function () {
            lovd_AJAX_viewEntryLoad();
            setInterval(lovd_AJAX_viewEntryLoad, 250);

            // If there is only one row of VOT, then trigger click on the first row so that the details of that transcript is displayed.
            if ($('#viewlistTable_<?php echo $sViewListID; ?> tr').length === 2) { // Table heading + first row.
                $('#viewlistTable_<?php echo $sViewListID; ?> tr')[1].click();
            }

            // Disable link to MD when there is no VOT.
            if (<?php echo count($_DATA->aTranscripts); ?> < 1) {
                // Add disabled class.
                sLink = $('#viewentryMenu_Variants').children(':contains("MobiDetails")').children().html();
                $('#viewentryMenu_Variants').children(':contains("MobiDetails")').addClass('disabled');
                $('#viewentryMenu_Variants').children(':contains("MobiDetails")').html(sLink);
            }
        });





        function lovd_AJAX_viewEntryLoad () {
            var hash = window.location.hash.substring(1);
            if (hash) {
                if (hash != prevHash) {
                    // Hash changed, (re)load viewEntry.
                    // Set the correct status for the TRs in the viewList (highlight the active TR).
                    $( '#VOT_' + prevHash ).removeClass('bold');
                    $( '#VOT_' + hash ).addClass('bold');

                    if (!navigator.userAgent.match(/msie/i)) {
                        $( '#viewentryDiv' ).stop().css('opacity','0'); // Stop execution of actions, set opacity = 0 (hidden, but not taken out of the flow).
                    }
                    $.get('ajax/viewentry.php', { object: 'Transcript_Variant', id: '<?php echo $nID; ?>,' + hash },
                        function(sData) {
                            if (sData.length > 2) {
                                $( '#viewentryDiv' ).html('\n' + sData);
                                if (!navigator.userAgent.match(/msie/i)) {
                                    $( '#viewentryDiv' ).fadeTo(1000, 1);
                                }
                            }
                        });
                    prevHash = hash;
                } else {
                    // The viewList could have been resubmitted now, so reset this value (not very efficient).
                    $( '#VOT_' + hash ).addClass('bold');
                }
            }
        }
      </SCRIPT>
<?php

    if (!LOVD_plus && !empty($zData['screeningids'])) {
        $_GET['search_screeningid'] = $zData['screeningids'];
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Screenings', 'H4');
        require ROOT_PATH . 'class/object_screenings.php';
        $_DATA = new LOVD_Screening();
        $aVLOptions = array(
            'cols_to_skip' => array('individualid', 'created_date', 'edited_date'),
            'track_history' => false,
            'show_navigation' => false,
        );
        $_DATA->viewList('Screenings_for_VOG_VE', $aVLOptions);
    }

    $_T->printFooter();
    exit;
}





if ((empty($_PE[1]) || $_PE[1] == 'upload') && ACTION == 'create') {
    // URL: variants?create
    // URL: variants/upload?create
    // Detect whether a valid target screening is given. We do this here so we
    // don't have to duplicate this code for variants?create and variants/upload?create.

    lovd_requireAUTH(
        (!empty($_PE[1])? LEVEL_MANAGER :
            (empty($_GET['target']) && !($_AUTH && lovd_isAuthorized('gene', $_AUTH['curates'], false))? LEVEL_CURATOR :
                $_SETT['user_level_settings']['submit_new_data'])));

    $bSubmit = false;
    if (isset($_GET['target'])) {
        // On purpose not checking for numeric target. If it's not numeric, we'll automatically get to the error message below.
        $_GET['target'] = sprintf('%010d', $_GET['target']);
        $z = $_DB->q('SELECT id, variants_found FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target']))->fetchAssoc();
        $sMessage = '';
        if (!$z) {
            $sMessage = 'The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.';
        } elseif (!lovd_isAuthorized('screening', $_GET['target'], false)) {
            lovd_requireAUTH(LEVEL_OWNER);
        } elseif (!$z['variants_found']) {
            $sMessage = 'Cannot add variants to the given screening, because the value \'Have variants been found?\' is unchecked.';
        }
        if ($sMessage) {
            define('PAGE_TITLE', (empty($_PE[1])? lovd_getCurrentPageTitle() : 'Upload variant data'));
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable($sMessage, 'stop');
            $_T->printFooter();
            exit;
        } else {
            $_POST['screeningid'] = $_GET['target'];
            $_GET['search_id_'] = $_DB->q('SELECT GROUP_CONCAT(DISTINCT "=\"", geneid, "\"" SEPARATOR "|") FROM ' . TABLE_SCR2GENE . ' WHERE screeningid = ?', array($_POST['screeningid']))->fetchColumn();
        }

        if (isset($_POST['screeningid']) && isset($_AUTH['saved_work']['submissions']['screening'][$_POST['screeningid']])) {
            $bSubmit = true;
            $aSubmit = &$_AUTH['saved_work']['submissions']['screening'][$_POST['screeningid']];
        } elseif (isset($_POST['screeningid']) && isset($_AUTH['saved_work']['submissions']['individual'])) {
            foreach ($_AUTH['saved_work']['submissions']['individual'] as $nIndividualID => &$aSubmit) {
                if (isset($aSubmit['screenings']) && in_array($_POST['screeningid'], $aSubmit['screenings'])) {
                    $bSubmit = true;
                    break;
                }
            }
        }

    } else {
        $_GET['target'] = '';
    }
    // NO EXIT, so the rest of the code is in either one of the code blocks below.
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: variants?create
    // Create a new entry.

    // We already called lovd_requireAUTH().

    define('LOG_EVENT', 'VariantCreate');

    if (!isset($_GET['reference'])) {
        // URL: /variants?create
        // Select whether you want to create a variant on the genome or on a transcript.
        define('PAGE_TITLE', lovd_getCurrentPageTitle());
        $_T->printHeader();
        $_T->printTitle();

        require ROOT_PATH . 'inc-lib-form.php';

        if ($_GET['target']) {
            $nIndividual = $_DB->q('SELECT individualid FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target']))->fetchColumn();
            $nVariants = $_DB->q('SELECT COUNT(DISTINCT s2v.variantid) FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = ?', array($nIndividual))->fetchColumn();
            $nCurrentVariants = $_DB->q('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($_GET['target']))->fetchColumn();

            $aOptionsList = array('width' => 600);
            if (!$nVariants) {
                $aOptionsList['options'][0]['disabled'] = true;
                $aOptionsList['options'][0]['onclick'] = 'javascript:alert(\'You cannot confirm variants with this screening, because there aren\&#39;t any variants connected to this individual yet!\');';
            } elseif ($nCurrentVariants == $nVariants) {
                $aOptionsList['options'][0]['disabled'] = true;
                $aOptionsList['options'][0]['onclick'] = 'javascript:alert(\'You cannot confirm any more variants with this screening, because all this individual\&#39;s variants have already been found/confirmed by this screening!\');';
            } else {
                $aOptionsList['options'][0]['onclick'] = 'screenings/' . $_GET['target'] . '?confirmVariants';
            }
            $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to confirm variants found using this screening &raquo;&raquo;</B>';

            print('      Do you want to confirm already submitted variants with this screening?<BR><BR>' . "\n\n");
            print(lovd_buildOptionTable($aOptionsList));
        }

        $sViewListID = 'Genes_SubmitVOT' . ($_GET['target']? '_' . $_GET['target'] : '');

        $aOptionsList = array('width' => 600);
        $aOptionsList['options'][0]['onclick'] = 'javascript:$(\'#container\').toggle(); lovd_stretchInputs(\'' . $sViewListID . '\');';
        $aOptionsList['options'][0]['option_text'] = '<B>A variant that is located within a gene &raquo;&raquo;</B>';

        $aOptionsList['options'][1]['onclick'] = 'variants?create&amp;reference=Genome' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '');
        $aOptionsList['options'][1]['option_text'] = '<B>A variant that was only described on genomic level &raquo;&raquo;</B>';

        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aOptionsList['options'][2]['onclick'] = 'variants/upload?create' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '');
            $aOptionsList['options'][2]['option_text'] = '<B>I want to upload a file with genomic variant data &raquo;&raquo;</B>';
        }

        print('      What kind of variant would you like to submit?<BR><BR>' . "\n\n");
        print(lovd_buildOptionTable($aOptionsList));

        require ROOT_PATH . 'class/object_genes.php';
        $_GET['page_size'] = 10;
        $_DATA = new LOVD_Gene();
        $_DATA->setRowLink($sViewListID, CURRENT_PATH . '?' . ACTION . '&reference=Transcript&geneid=' . $_DATA->sRowID . ($_GET['target']? '&target=' . $_GET['target'] : ''));
        $_GET['search_transcripts'] = '>0';
        print('      <DIV id="container" style="display : none;">' . "\n"); // Extra div is to prevent "No entries in the database yet!" error to show up if there are no genes in the database yet.
        lovd_showInfoTable('Please find the gene for which you wish to submit this variant below, using the search fields if needed. <B>Click on the gene to proceed to the variant entry form</B>.<BR>If a gene is not shown in this display, but it does exist in this LOVD, then it does not have a transcript configured yet.', 'information', 600);
        $aVLOptions = array(
            'cols_to_skip' => array('transcripts', 'variants', 'diseases_', 'updated_date_'),
        );
        $_DATA->viewList($sViewListID, $aVLOptions);
        print('      </DIV>' . "\n" .
              (!$bSubmit? '' : '      <INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/screening/' . $_POST['screeningid'] . '\'; return false;" style="border : 1px solid #FF4422;">' . "\n"));

        $_T->printFooter();
        exit;





    } elseif (!in_array($_GET['reference'], array('Genome', 'Transcript')) || ($_GET['reference'] == 'Transcript' && empty($_GET['geneid']))) {
        exit;
    }

    // URL: /variants?create&reference=('Genome'|'Transcript')
    // Create a variant on the genome.

    if ($_GET['reference'] == 'Transcript') {
        // On purpose not checking for format of $_GET['geneid']. If it's not right, we'll automatically get to the error message below.
        $sGene = $_DB->q('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($_GET['geneid']))->fetchColumn();
        if (!$sGene) {
            define('PAGE_TITLE', lovd_getCurrentPageTitle());
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('The gene symbol given is not valid, please go to the create variant page and select the desired gene entry.', 'warning');
            $_T->printFooter();
            exit;
        } else {
            define('PAGE_TITLE', lovd_getCurrentPageTitle() . ' for gene ' . $sGene);
        }
    } else {
        define('PAGE_TITLE', lovd_getCurrentPageTitle());
    }

    if (isset($sGene)) {
        lovd_isAuthorized('gene', $sGene);
    }

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = array();
    $_DATA['Genome'] = new LOVD_GenomeVariant();
    if (isset($sGene)) {
        require ROOT_PATH . 'class/object_transcript_variants.php';
        $_DATA['Transcript'][$sGene] = new LOVD_TranscriptVariant($sGene);
        // This is done so that fetchDBID can have this information and can give a better prediction.
        // buildForm() also uses it to check some things.
        $_POST['aTranscripts'] = $_DATA['Transcript'][$sGene]->aTranscripts;
        $_POST['chromosome'] = $_DB->q('SELECT chromosome FROM ' . TABLE_GENES . ' WHERE id = ?', array($sGene))->fetchColumn();
    }
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        if (isset($sGene)) {
            foreach ($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                if (!empty($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) && strlen($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) >= 6) {
                    $aResponse = lovd_getVariantInfo($_POST[$nTranscriptID . '_VariantOnTranscript/DNA'], $aTranscript[0]);
                    if ($aResponse) {
                        $_POST[$nTranscriptID . '_position_c_start'] = $aResponse['position_start'];
                        $_POST[$nTranscriptID . '_position_c_start_intron'] = $aResponse['position_start_intron'];
                        $_POST[$nTranscriptID . '_position_c_end'] = $aResponse['position_end'];
                        $_POST[$nTranscriptID . '_position_c_end_intron'] = $aResponse['position_end_intron'];
                    }
                }
            }
            $_DATA['Transcript'][$sGene]->checkFields($_POST);

            // Set missing request values for variant effect.
            // FIXME: We're assuming there, that the genomic fields are not set, because we unset them.
            if (!isset($_POST['effect_reported'])) {
                $_POST['effect_reported'] = lovd_getMaxVOTEffects('reported', $_POST);
            }

            // FIXME: We're assuming there, that the genomic fields are not set, because we unset them.
            if (!isset($_POST['effect_concluded']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                $_POST['effect_concluded'] = lovd_getMaxVOTEffects('concluded', $_POST);
            }
        }

        // Prepare the position fields already, so they can be checked.
        $aResponse = lovd_getVariantInfo($_POST['VariantOnGenome/DNA']);
        if ($aResponse) {
            list($_POST['position_g_start'], $_POST['position_g_end'], $_POST['type']) =
                array($aResponse['position_start'], $aResponse['position_end'], $aResponse['type']);
        } else {
            $_POST['position_g_start'] = 0;
            $_POST['position_g_end'] = 0;
            $_POST['type'] = NULL;
        }
        $_DATA['Genome']->checkFields($_POST);

        if (!lovd_error()) {
            // Prepare the fields to be used for both genomic and transcript variant information.
            $aFieldsGenome = array_merge(
                                array('allele', 'effectid', 'chromosome', 'position_g_start', 'type', 'position_g_end', 'owned_by', 'statusid', 'created_by', 'created_date'),
                                $_DATA['Genome']->buildFields());

            // Prepare values.
            $_POST['effectid'] = $_POST['effect_reported'] . ($_AUTH['level'] >= $_SETT['user_level_settings']['set_concluded_effect']? $_POST['effect_concluded'] : substr($_SETT['var_effect_default'], -1));

            if (empty($_POST['position_g_start'])) {
                // Variant not recognized, or no DNA given.
                $_POST['position_g_start'] = 0;
                $_POST['position_g_end'] = 0;
                $_POST['type'] = NULL;
            }

            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_IN_PROGRESS);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $_DB->beginTransaction();
            $nID = $_DATA['Genome']->insertEntry($_POST, $aFieldsGenome);

            if (isset($sGene)) {
                $_POST['id'] = $nID;
                foreach ($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                    if (empty($_POST[$nTranscriptID . '_position_c_start'])) {
                        // Variant not recognized, or no DNA given.
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

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created variant entry #' . $nID);

            if (isset($_POST['screeningid'])) {
                // Add variant to screening.
                $q = $_DB->q('INSERT INTO ' . TABLE_SCR2VAR . ' VALUES (?, ?)', array($_POST['screeningid'], $nID), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Variant entry could not be added to screening #' . $_POST['screeningid']);
                }
            }

            if (isset($sGene) && $_POST['statusid'] >= STATUS_MARKED) {
                lovd_setUpdatedDate($sGene);
            }

            $_DB->commit();

            if ($bSubmit) {
                if (!isset($aSubmit['variants'])) {
                    $aSubmit['variants'] = array();
                }
                $aSubmit['variants'][] = $nID;

                lovd_saveWork();

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/screening/' . $_POST['screeningid']);

                $_T->printHeader();
                $_T->printTitle();

                lovd_showInfoTable('Successfully created the variant entry!', 'success');

                $_T->printFooter();

            } else {
                $_SESSION['work']['submits']['variant'][$nID] = $nID;
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



    $_T->printHeader();
    $_T->printTitle();

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
                        array('', '', 'print', '<INPUT type="submit" value="Create variant entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/screening/' . $_POST['screeningid'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('inc-js-variants.php?chromosome=' . $_POST['chromosome']);
?>
      <SCRIPT type="text/javascript">

        $( '.transcript' ).each(function () {
            $(this).parent().parent().children().first().html('<INPUT class="ignore" name="ignore_' + $(this).attr('transcriptid') + '" type="checkbox"> <B>Ignore this transcript</B>');
        });

        $( '.ignore' ).click(function () {
            var oBeginTranscript = $(this).parent().parent().next();
            var oNextElement = oBeginTranscript.next();
            while (oNextElement.children().length > 1) {
                // More than one TD, so it is an input field.
                if ($(this).prop('checked')) {
                    oNextElement.children().last().children().first().prop('disabled', true).siblings('button').first().hide();
                } else {
                    oNextElement.children().last().children().first().prop('disabled', false);
                }
                oNextElement = oNextElement.next();
            }
        });
<?php
    print('        var aUDrefseqs = {' . "\n");

    if (isset($sGene)) {
        echo '            \'' . $sGene . '\' : \'' . $_DB->q('SELECT refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($sGene))->fetchColumn() . '\'';
    }

    print("\n" .
          '        };' . "\n" .
          '        var aTranscripts = {'. "\n");

    if (isset($sGene)) {
        $i = 0;
        foreach ($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGeneSymbol, $sMutalyzerID) = $aTranscript;
            echo ($i? ',' . "\n" : '') . '            \'' . $nTranscriptID . '\' : [\'' . $sTranscriptNM . '\', \'' . $sGeneSymbol . '\', \'' . $sMutalyzerID . '\']';
            $i++;
        }
    }

    echo "\n" . '        };';

    foreach ($_POST as $key => $val) {
        if (substr($key, 0, 7) == 'ignore_') {
            // First uncheck the checkbox (just to be certain). Then trigger the click, which changes the checked state.
            echo '$( \'input[name="ignore_' . substr($key, 7, $_SETT['objectid_length']['transcripts']) . '"]\' ).prop(\'checked\', false).trigger(\'click\');' . "\n";
        }
    }

    print("\n" .
          '      </SCRIPT>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && $_PE[1] == 'upload' && ACTION == 'create') {
    // URL: /variants/upload?create
    // URL: /variants/upload?create&type=VCF
    // URL: /variants/upload?create&type=SeattleSeq
    // Import a VCF or SeattleSeq file.

    // We already called lovd_requireAUTH(LEVEL_MANAGER).

    if (!isset($_GET['type'])) {
        // URL: /variants/upload?create
        // Select whether you want to upload a VCF or SeattleSeq file.

        define('PAGE_TITLE', 'Upload variant data');
        $_T->printHeader();
        $_T->printTitle();
        require ROOT_PATH . 'inc-lib-form.php';

        print('      What kind of file would you like to upload?<BR><BR>' . "\n\n");
        $aOptionsList = array('width' => 600);

        $aOptionsList['options'][0]['onclick'] = 'variants/upload?create&amp;type=VCF' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '');
        $aOptionsList['options'][0]['option_text'] = '<B>I want to upload a Variant Call Format (VCF) file &raquo;&raquo;</B>';
        $aOptionsList['options'][1]['onclick'] = 'variants/upload?create&amp;type=SeattleSeq' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '');
        $aOptionsList['options'][1]['option_text'] = '<B>I want to upload a SeattleSeq Annotation file &raquo;&raquo;</B>';
        $aOptionsList['options'][2]['onclick'] = 'variants/?create' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '');
        $aOptionsList['options'][2]['type'] = 'l';
        $aOptionsList['options'][2]['option_text'] = '<B>Back</B>';

        print(lovd_buildOptionTable($aOptionsList));

        $_T->printFooter();
        exit;
    } elseif (!in_array($_GET['type'], array('VCF', 'SeattleSeq'))) {
        exit;
    }

    define('LOG_EVENT', 'VariantUpload' . $_GET['type']);

    define('PAGE_TITLE', 'Upload a ' . $_GET['type'] . ' file');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'inc-lib-form.php';

    // Maximum number of unsupported variants that will be printed to the user.
    $nMaxListedUnsupported = 100;



    // Calculate maximum uploadable file size.
    // VCF files are approximately 116 bytes per variant, so 50 MB should allow for half a million variants.
    // SeattleSeq variants may take up as much as 750 bytes per variant because they duplicate a lot of information in cases with several transcripts.
    // Either way, LOVD has a maximum file size limit of 100 MB.
    // Anyways, the server settings are probably much lower.
    $nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit.
    $nMaxSize = min(
        $nMaxSizeLOVD,
        lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
        lovd_convertIniValueToBytes(ini_get('post_max_size')));





    function lovd_getVCFLine ($fInput)
    {
        // This function reads and returns one line in $fInput.
        // It also updates the progress bar ($_BAR) and automatically skips empty lines. Returns false on EOF.
        global $_BAR, $_FILES;
        static $nParsedBytes;

        if (!isset($nParsedBytes)) {
            $nParsedBytes = 0;
        }

        // Automatically skip empty lines.
        do {
            $sLine = fgets($fInput);
            $nParsedBytes += strlen($sLine);
        } while ($sLine !== false && !trim($sLine));

        // Update the progress bar and return the line.
        $_BAR->setProgress(floor($nParsedBytes / $_FILES['variant_file']['size'] * 100));
        return rtrim($sLine, "\r\n");
    }





    // FIXME; with so many static functions, perhaps put this in a class?
    function lovd_getVariantFromSeattleSeq ($fInput)
    {
        // This function reads and returns one variant from $fInput. It is returned as an associative array;
        // the function automatically finds the SeattleSeq header line in the first call. It also updates the
        // progress bar ($_BAR) and automatically skips empty lines. Returns false on EOF or if no header line is found.
        global $_BAR, $_FILES;
        static $nParsedBytes, $sLine, $aHeaders;

        // Initiate static variables at the first call.
        if (!isset($nParsedBytes)) {
            $nParsedBytes = 0;
        }
        if (!isset($sLine)) {
            $sLine = '';
        }

        do {
            // Variants have a separate line for each transcript they hit. We read lines
            // until we've got all data for one genomic position and then exit of the loop.

            do {
                // Read a line into $sNextLine, automatically skip empty lines.
                // $sNextLine will be copied to $sLine at the end of the outer loop. $sLine is
                // declared static, which means it remains available in the next call to this function.
                $sNextLine = fgets($fInput);
                $nParsedBytes += strlen($sNextLine);
            } while ($sNextLine !== false && !trim($sNextLine));

            // If we don't have a header line yet, we keep reading lines until we've got one. Then we enter the if() below.
            if (empty($aHeaders) && $sNextLine && substr($sNextLine, 0, 2) != '# ') {
                // We moved past the header line with $sNextLine, so the header was the previous line. $sLine has it.
                if ($sLine == '') {
                    // We end up here if the first line in the file doesn't start with '# '.
                    // This can't be a SeattleSeq file; just return false.
                    return false;
                }
                $aHeaders = explode("\t", ltrim(rtrim($sLine, "\r\n"), '# '));
                $aHeaders = array_map('trim', $aHeaders, array_fill(0, count($aHeaders), '"'));
            }

            // If we do have a header line, we keep reading lines until we move to the next variant.
            // If we haven't moved past the last variant line on the previous call to this function, we end up in the if() below.
            if (!empty($aHeaders) && $sLine && substr($sLine, 0, 2) != '# ') {
                if (empty($aLine)) {
                    // $aLine is going to hold the actual variant data that we return.
                    // Its initial data comes from $sLine (which is the previously-read line; usually even from the previous call to this function).
                    // This is because we always read one line 'too much'; we only know $sNextLine is not part of the current variant once we've already read it.
                    $aLine = array_combine($aHeaders, explode("\t", rtrim($sLine, "\r\n")));

                    foreach (array('accession', 'functionGVS', 'functionDBSNP', 'aminoAcids', 'proteinPosition', 'cDNAPosition', 'polyPhen', 'granthamScore', 'proteinSequence', 'distanceToSplice') as $sKey) {
                        // Making arrays of some transcript-specific columns.

                        if (!isset($aLine[$sKey])) {
                            // cDNAPosition, polyPhen, granthamScore, proteinSequence and distanceToSplice are optional columns so we should check for their existence.
                            continue;
                        }
                        $aLine[$sKey] = array($aLine[$sKey]);
                    }
                }

                if (!$sNextLine || substr($sNextLine, 0, 2) == '# ') {
                    // We've moved past the last variant line with $sNextLine. Return what we have got in $aLine now.
                    $sLine = $sNextLine;
                    break;
                }

                // Compare the next line with the current variant data.
                // Prepare $aNextLine a little more carefully, since some files just plainly suck...
                $aNextLine = array_pad(explode("\t", rtrim($sNextLine, "\r\n")), count($aHeaders), '');
                $aNextLine = array_combine($aHeaders, $aNextLine);
                if (isset($aLine['chromosome']) && isset($aNextLine['chromosome']) && $aLine['chromosome'] == $aNextLine['chromosome'] && isset($aLine['position']) && isset($aNextLine['position']) && $aLine['position'] == $aNextLine['position']) {
                    // The variant in $aNextLine is the same as $aLine, but on another transcript. Add the transcript-specific values.
                    foreach ($aLine as $sKey => &$value) {
                        if (is_array($value)) {
                            $value[] = $aNextLine[$sKey];
                        }
                    }
                } else {
                    // The variant in $aNextLine is not the same as $aLine. Moving to a different variant, return what we have got in $aLine now.
                    $sLine = $sNextLine;
                    break;
                }
            }

            // Copy $sNextLine to $sLine and read another line if we haven't reached the end of the file yet.
            $sLine = $sNextLine;
        } while($sNextLine);

        // Update progress bar and return the variant (or false if we have none).
        $_BAR->setProgress(floor($nParsedBytes / $_FILES['variant_file']['size'] * 100));
        return (empty($aLine)? false : $aLine);
    }





    // FIXME: Seems a bit overkill. Can't the check be done when the file is being parsed?
    function lovd_reconstructSeattleSeqLine ($aVariant, $nTranscriptIndex = 0)
    {
        // Returns the given variant as a string, like it was in the SeattleSeq file.
        // This is used to be able to print a SeattleSeq line to the user in case a variant can't be imported.

        foreach (array('accession', 'functionGVS', 'functionDBSNP', 'aminoAcids', 'proteinPosition', 'cDNAPosition', 'polyPhen', 'granthamScore', 'proteinSequence', 'distanceToSplice') as $sKey) {
            // Getting the selected index from the transcript-dependent fields.

            if (!isset($aVariant[$sKey])) {
                // cDNAPosition, polyPhen, granthamScore, proteinSequence and distanceToSplice are optional columns so we should check for their existence.
                continue;
            }
            $aVariant[$sKey] = $aVariant[$sKey][$nTranscriptIndex];
        }

        return implode("\t", $aVariant);
    }





    function lovd_getVariantDescription (&$aVariantData, $sReference, $sAllele)
    {
        // Constructs a variant description from $sReference and $sAllele and adds it to $aVariantData in a new 'VariantOnGenome/DNA' key.
        // The 'position_g_start' and 'position_g_end' keys in $aVariantData are adjusted accordingly and a 'type' key is added too.
        // The numbering scheme is either g. or m. and depends on the 'chromosome' key in $aVariantData.

        // Use the right prefix for the numbering scheme.
        $sHGVSPrefix = 'g.';
        if ($aVariantData['chromosome'] == 'M') {
            $sHGVSPrefix = 'm.';
        }

        // 'Eat' letters from either end - first left, then right - to isolate the difference.
        $sAlleleOriginal = $sAllele;
        while (strlen($sReference) > 0 && strlen($sAllele) > 0 && $sReference[0] == $sAllele[0]) {
            $sReference = substr($sReference, 1);
            $sAllele = substr($sAllele, 1);
            $aVariantData['position_g_start'] ++;
        }
        while (strlen($sReference) > 0 && strlen($sAllele) > 0 && $sReference[strlen($sReference) - 1] == $sAllele[strlen($sAllele) - 1]) {
            $sReference = substr($sReference, 0, -1);
            $sAllele = substr($sAllele, 0, -1);
            $aVariantData['position_g_end'] --;
        }

        // Now find out the variant type.
        if (strlen($sReference) > 0 && strlen($sAllele) == 0) {
            // Deletion.
            $aVariantData['type'] = 'del';
            if ($aVariantData['position_g_start'] == $aVariantData['position_g_end']) {
                $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . 'del';
            } else {
                $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . '_' . $aVariantData['position_g_end'] . 'del';
            }
        } elseif (strlen($sAllele) > 0 && strlen($sReference) == 0) {
            // Something has been added... could be an insertion or a duplication.
            if (substr($sAlleleOriginal, strrpos($sAlleleOriginal, $sAllele) - strlen($sAllele), strlen($sAllele)) == $sAllele) {
                // Duplicaton.
                $aVariantData['type'] = 'dup';
                $aVariantData['position_g_start'] -= strlen($sAllele);
                if ($aVariantData['position_g_start'] == $aVariantData['position_g_end']) {
                    $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . 'dup';
                } else {
                    $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . '_' . $aVariantData['position_g_end'] . 'dup';
                }
            } else {
                // Insertion.
                $aVariantData['type'] = 'ins';

                // Exchange g_start and g_end; after the 'letter eating' we did, start is actually end + 1!
                $aVariantData['position_g_start'] --;
                $aVariantData['position_g_end'] ++;;
                $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . '_' . $aVariantData['position_g_end'] . 'ins' . $sAllele;
            }
        } elseif (strlen($sAllele) == 1 && strlen($sReference) == 1) {
            // Substitution.
            $aVariantData['type'] = 'subst';
            $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . $sReference . '>' . $sAllele;
        } elseif ($sReference == strrev(str_replace(array('a', 'c', 'g', 't'), array('T', 'G', 'C', 'A'), strtolower($sAllele)))) {
            // Inversion.
            $aVariantData['type'] = 'inv';
            $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . '_' . $aVariantData['position_g_end'] . 'inv';
        } else {
            // Deletion/insertion.
            $aVariantData['type'] = 'delins';
            if ($aVariantData['position_g_start'] == $aVariantData['position_g_end']) {
                $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . 'delins' . $sAllele;
            } else {
                $aVariantData['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariantData['position_g_start'] . '_' . $aVariantData['position_g_end'] . 'delins' . $sAllele;
            }
        }
    }





    // If dbSNP custom links are active, find out which columns in TABLE_VARIANTS accept them.
    $aDbSNPColumns = $_DB->q('SELECT ac.colid FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS2LINKS . ' AS c2l USING (colid) INNER JOIN ' . TABLE_LINKS . ' AS l ON (c2l.linkid = l.id) WHERE l.name = "DbSNP" AND ac.colid LIKE "VariantOnGenome/%" AND ac.colid NOT IN ("VariantOnGenome/DBID", "VariantOnGenome/DNA")')->fetchAllColumn();
    // FIXME: dbSNP will be included twice this way.
    if ($sDbSNPColumn = $_DB->q('SELECT colid FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = "VariantOnGenome/dbSNP"')->fetchColumn()) {
        // The dbSNP special column is active, allow to insert dbSNP links in there.
        array_unshift($aDbSNPColumns, $sDbSNPColumn);
    }
    array_unshift($aDbSNPColumns, 'Don\'t import dbSNP links');

    if (POST) {
        // The form has been submitted. Detect any errors in the file upload.
        if (empty($_FILES['variant_file']) || ($_FILES['variant_file']['error'] > 0 && $_FILES['variant_file']['error'] < 4)) {
            lovd_errorAdd('', 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

        } elseif ($_FILES['variant_file']['error'] == 4 || !$_FILES['variant_file']['size']) {
            lovd_errorAdd('', 'Please select a file to upload.');

        } elseif ($_FILES['variant_file']['size'] > $nMaxSize) {
            lovd_errorAdd('', 'The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

        } elseif ($_FILES['variant_file']['error']) {
            // Various errors available from 4.3.0 or later.
            lovd_errorAdd('', 'There was an unknown problem with receiving the file properly, possibly because of the current server settings. If the problem persists, contact the database administrator.');
        }

        if (!lovd_error()) {
            // No problems found. Start processing the file.

            // Initiate progress bar.
            require ROOT_PATH . 'class/progress_bar.php';
            $_BAR = new ProgressBar('', 'Loading variant data from the ' . $_GET['type'] . ' file...', '&nbsp;');
            $_T->printFooter(false);

            // Parse mapping options.
            $nMappingFlags = (!empty($_POST['allow_mapping'])? MAPPING_ALLOW : 0) | (!empty($_POST['allow_create_genes'])? MAPPING_ALLOW_CREATE_GENES : 0);

            // Create data array.
            $nUploadID = sprintf('%05d%010d', $_AUTH['id'], time());
            $aUploadData = array('upload_date' => date('Y-m-d H:i:s', substr($nUploadID, 5, 10)),
                                 'file_name' => $_FILES['variant_file']['name'],
                                 'file_type' => $_GET['type'],
                                 'num_variants' => 0,
                                 'num_variants_unsupported' => 0,
                                 'num_genes' => 0,
                                 'num_transcripts' => 0,
                                 'num_variants_on_transcripts' => 0,
                                 'owned_by' => $_POST['owned_by'],
                                 'statusid' => $_POST['statusid'],
                                 'mapping_flags' => $nMappingFlags);
            if (!empty($_POST['screeningid'])) {
                $aUploadData['screeningid'] = sprintf("%010d", $_POST['screeningid']);
            }

            // Let's go...
            $fInput = fopen($_FILES['variant_file']['tmp_name'], 'r');
            @set_time_limit(0);

            if (empty($_INI['test'])) {
                // This will take some time, allow the user to browse in other tabs.
                // FIXME; if the user finishes a screening submission in another tab while the upload
                // is still working, a seperate e-mail about the upload will be sent once it has finished.
                // So, other than that it results in two e-mails, it is working just fine actually.
                // Though maybe we should block submit/finish until we're done here?
                session_write_close();
            }

            $_DB->beginTransaction();





            if ($_GET['type'] == 'VCF') {
                // VCF specific.

                // Quickly skip the meta lines.
                do {
                    $sLine = lovd_getVCFLine($fInput);
                } while (substr($sLine, 0, 2) == '##');

                // Read out the header line.
                $aHeaders = explode("\t", $sLine);

                // Prepare database queries.
                if ($_POST['dbSNP_column'] > 0) {
                    $qInsertVariant = $_DB->prepare('INSERT INTO ' . TABLE_VARIANTS . ' (allele, effectid, chromosome, position_g_start, position_g_end, type, owned_by, statusid, mapping_flags, created_by, created_date, `VariantOnGenome/DBID`, `VariantOnGenome/DNA`, `' . $aDbSNPColumns[$_POST['dbSNP_column']] . '`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                } else {
                    $qInsertVariant = $_DB->prepare('INSERT INTO ' . TABLE_VARIANTS . ' (allele, effectid, chromosome, position_g_start, position_g_end, type, owned_by, statusid, mapping_flags, created_by, created_date, `VariantOnGenome/DBID`, `VariantOnGenome/DNA`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                }
                $qInsertScr2Var = $_DB->prepare('INSERT INTO ' . TABLE_SCR2VAR . ' (screeningid, variantid) VALUES (?, ?)');

                // Start parsing variants.
                $aUnsupportedLines = array();
                while ($sLine = lovd_getVCFLine($fInput)) {
                    // Read the next variant from the file.
                    $aLine = array_combine($aHeaders, explode("\t", $sLine));

                    // Check the chromosome number.
                    //preg_match('/.*?(\d{1,2}|[XYM]).*?/', $aLine['#CHROM'], $aChromosome); // Tries to extract any chromosome number (0-99, XYM)
                    // Check Chromosome field, allows '##', 'c##' and 'chr##' (where ## = 1-22 or XYM).
                    if (!isset($aLine['#CHROM']) || !preg_match('/^(?:c(?:hr)?)?([XYM]|[1-9]|1[0-9]|2[0-2])$/', $aLine['#CHROM'], $aChromosome) || !$aChromosome) {
                        // Chromosome not recognized, report & ignore this variant.
                        if (isset($aLine['ALT'])) {
                            $aUploadData['num_variants_unsupported'] += count(explode(',', $aLine['ALT']));
                        } else {
                            // Assuming we're dealing with a variant here. We've got no proof of it, but well...
                            $aUploadData['num_variants_unsupported'] ++;
                        }
                        if ($aUploadData['num_variants_unsupported'] < $nMaxListedUnsupported) {
                            $aUnsupportedLines[] = $sLine;
                        }
                        continue;
                    }

                    // Extract dbSNP references.
                    preg_match('/(?:^|;)(rs\d+)(?:$|;)/', $aLine['ID'], $aReference);

                    // Parse sample columns.
                    $aUsedAlleles = array();
                    for ($i = 9; $i < count($aHeaders); $i++) {
                        // Make it an associative array with the keys from the FORMAT column.
                        $aLine[$aHeaders[$i]] = array_combine(explode(':', $aLine['FORMAT']), explode(':', $aLine[$aHeaders[$i]]));

                        // Compute GT values if the user wants them to be computed from the PL values.
                        if (!empty($aLine[$aHeaders[$i]]['PL']) && $_POST['genotype_field'] == 'pl') {
                            $aLine[$aHeaders[$i]]['PL'] = explode(',', $aLine[$aHeaders[$i]]['PL']);
                            $n = array_search(min($aLine[$aHeaders[$i]]['PL']), $aLine[$aHeaders[$i]]['PL']);
                            $nFirst = 0;
                            $nSecond = 0;
                            while ($n -- > 0) {
                                if ($nFirst == $nSecond) {
                                    $nFirst = 0;
                                    $nSecond ++;
                                } else {
                                    $nFirst ++;
                                }
                            }
                            $aLine[$aHeaders[$i]]['GT'] = $nFirst . '/' . $nSecond;
                        }

                        // Find out which ALT alleles are present in the samples.
                        if (!empty($aLine[$aHeaders[$i]]['GT']) && preg_match('#^(\d+)[/|](\d+)$#', $aLine[$aHeaders[$i]]['GT'], $aMatches)) {
                            array_push($aUsedAlleles, $aMatches[1], $aMatches[2]);
                        } else {
                            // This line contains a sample that is missing a valid GT field, even though this is mandatory.
                            // This is by definition an invalid line in a VCF file.
                            if ($aUploadData['num_variants_unsupported'] ++ < $nMaxListedUnsupported) {
                                $aUnsupportedLines[] = $sLine;
                            }
                            continue;
                        }
                    }

                    // Read the alleles.
                    $aLine['REF'] = strtoupper($aLine['REF']);
                    $aAlleles = explode(',', strtoupper($aLine['ALT']));
                    foreach ($aAlleles as $nAlleleNumber => $sAllele) {
                        if (!empty($aUsedAlleles) && !in_array($nAlleleNumber + 1, $aUsedAlleles)) {
                            // This allele is not seen in a sample genotype, so don't import it!
                            continue;
                        }

                        // FIXME; Shouldn't we actually skip all variants we don't understand!??!!
                        if (strpos($sAllele, 'N') !== false || strpos($aLine['REF'], 'N') !== false) {
                            // Skip any variants with an 'N'.
                            if ($aUploadData['num_variants_unsupported'] ++ < $nMaxListedUnsupported) {
                                $aUnsupportedLines[] = $sLine;
                            }
                            continue;
                        }

                        // Initiate the variant data array with its chromosome number and, if present, the dbSNP reference.
                        $aVariantData = array('chromosome' => $aChromosome[1], 'reference' => null);
                        if (isset($aReference[1])) {
                            if ($aDbSNPColumns[$_POST['dbSNP_column']] == 'VariantOnGenome/dbSNP') {
                                $aVariantData['reference'] = $aReference[1];
                            } elseif ($_POST['dbSNP_column'] > 0){
                                $aVariantData['reference'] = '{dbSNP:' . $aReference[1] . '}';
                            }
                        }

                        // Define start and end positions on the reference.
                        $aVariantData['position_g_start'] = (int) $aLine['POS'];
                        $aVariantData['position_g_end'] = (int) $aLine['POS'] + strlen($aLine['REF']) - 1;

                        // Set the allele.
                        $aVariantData['allele'] = 0;
                        if (count($aHeaders) == 10 && (is_array($aLine[$aHeaders[9]]['GT']) || preg_match('#^(\d+)[/|](\d+)$#', $aLine[$aHeaders[9]]['GT'], $aLine[$aHeaders[9]]['GT']))) {
                            // Only if we have just a single sample.
                            // FIXME; is there any way we can make special use of phased data?
                            if ($aLine[$aHeaders[9]]['GT'][1] == $nAlleleNumber + 1) {
                                $aVariantData['allele'] += 1;
                            }
                            if ($aLine[$aHeaders[9]]['GT'][2] == $nAlleleNumber + 1) {
                                $aVariantData['allele'] += 2;
                            }
                        }

                        // Now find out the variant type and description.
                        lovd_getVariantDescription($aVariantData, $aLine['REF'], $sAllele);

                        // Enter a DB-ID if mapping is disabled.
                        $aVariantData['VariantOnGenome/DBID'] = null;
                        if (!($nMappingFlags & MAPPING_ALLOW)) {
                            $aVariantData['VariantOnGenome/DBID'] = lovd_fetchDBID($aVariantData);
                        }

                        // Analysis complete, now enter the variant into the database.
                        $aInsertValues = array($aVariantData['allele'], $_SETT['var_effect_default'], $aVariantData['chromosome'], $aVariantData['position_g_start'], $aVariantData['position_g_end'], $aVariantData['type'], $_POST['owned_by'], $_POST['statusid'], $nMappingFlags, $_AUTH['id'], $aUploadData['upload_date'], $aVariantData['VariantOnGenome/DBID'], $aVariantData['VariantOnGenome/DNA']);
                        if ($_POST['dbSNP_column']) {
                            $aInsertValues[] = $aVariantData['reference'];
                        }
                        $qInsertVariant->execute($aInsertValues);
                        $aUploadData['num_variants'] ++;

                        // Link this variant to the current screening if applicable.
                        if (isset($_POST['screeningid'])) {
                            $qInsertScr2Var->execute(array($_POST['screeningid'], $_DB->lastInsertId()));
                        }
                    }
                }





            } elseif ($_GET['type'] == 'SeattleSeq') {
                // SeattleSeq specific.
                $tStart = time();

                require ROOT_PATH . 'inc-lib-actions.php';
                require ROOT_PATH . 'inc-lib-genes.php';

                $aIupacTable = array(
                    'A' => array('A'),
                    'C' => array('C'),
                    'G' => array('G'),
                    'T' => array('T'),
                    'M' => array('A', 'C'),
                    'R' => array('A', 'G'),
                    'W' => array('A', 'T'),
                    'S' => array('C', 'G'),
                    'Y' => array('C', 'T'),
                    'K' => array('G', 'T'),
                    'V' => array('A', 'C', 'G'),
                    'H' => array('A', 'C', 'T'),
                    'D' => array('A', 'G', 'T'),
                    'B' => array('C', 'G', 'T'),
                    'N' => array('G', 'A', 'T', 'C'),
                    'X' => array('G', 'A', 'T', 'C')
                );

                // Check which VOG columns are available.
                $aVOGColumnsAvailable = $_DB->q('SELECT colid FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid LIKE "VariantOnGenome%"')->fetchAllColumn();

                // Define the list of VariantOnTranscript columns once and for all.
                $aVOTCols = array('VariantOnTranscript/Distance_to_splice_site',
                                  'VariantOnTranscript/GVS/Function',
                                  'VariantOnTranscript/PolyPhen',
                                  'VariantOnTranscript/Position');

                // We also need to get a list of standard VariantOnTranscript columns.
                $aColsStandard = $_DB->q('SELECT id FROM ' . TABLE_COLS . ' WHERE standard = 1 AND id IN("' . implode('", "', $aVOTCols) . '")')->fetchAllColumn();

                $aGenesChecked = array();       // Contains arrays with [refseq_UD], [name], [strand] and [columns] for each gene we'll encounter
                $aAccessionToSymbol = array();  // Contains the gene symbol for each SeattleSeq transcript accession
                $aAccessionMapping = array();   // Maps SeattleSeq transcript accession numbers to their LOVD and Mutalyzer-compatible versions

                // Prepare the arrays that will hold the data that can be inserted into the database.
                $aFieldsGene = array();         // [geneSymbol] = values
                $aFieldsTranscript = array();   // [geneSymbol][transcriptAccession] = values

                $nCount = 0;                    // Number of variants read from the file

                $qInsertScr2Var = $_DB->prepare('INSERT INTO ' . TABLE_SCR2VAR . ' (screeningid, variantid) VALUES (?, ?)');


                // When doing slow things, we can provide a more detailed status message in the 'done' message box.
                $_BAR->setMessageVisibility('done', true);


                // Start parsing variants.
                $aUnsupportedLines = array();
                while ($aVariant = lovd_getVariantFromSeattleSeq($fInput)) {
                    // Empty the arrays that will hold the variant data to be inserted into the database.
                    $aFieldsVariantOnGenome = array(); // [0] is the first variant, [1] is filled in case of compound heterozygosity.
                    $aFieldsVariantOnTranscript = array();

                    // lovd_fetchDBID wants to have some additional data in the variant's array which we need to store separately for now.
                    $aTranscriptDataForDBID = array();

                    // And we use this just to be able to cache the numberConversion calls.
                    $aNumberConversion = array();

                    if (($nCount % 10) == 0) {
                        $_BAR->setMessage('Processed ' . $nCount . ' variants<BR>' .
                                          'Time working: ' . gmdate('H:i:s', time() - $tStart));
                    }
                    $nCount ++;

                    // Prepare genomic variant.
                    $aFieldsVariantOnGenome[0] = array(
                        'effectid' => $_SETT['var_effect_default'],
                        'chromosome' => (!isset($aVariant['chromosome'])? '' : $aVariant['chromosome']),
                        'owned_by' => $_POST['owned_by'],
                        'statusid' => $_POST['statusid'],
                        'created_by' => $_AUTH['id'],
                        'created_date' => $aUploadData['upload_date'],
                        );

                    if (in_array('VariantOnGenome/Conservation_score/GERP', $aVOGColumnsAvailable) && !in_array($aVariant['consScoreGERP'], array('NA', 'unknown', 'none'))) {
                        $aFieldsVariantOnGenome[0]['VariantOnGenome/Conservation_score/GERP'] = $aVariant['consScoreGERP'];
                    }

                    if ($_POST['dbSNP_column'] > 0 && preg_match('/\d+/', $aVariant['rsID'], $aDbSNP) && $aDbSNP[0] != '0') {
                        // Include custom link to dbSNP if the user wants that and we have an rsID for this variant.
                        if ($aDbSNPColumns[$_POST['dbSNP_column']] == 'VariantOnGenome/dbSNP') {
                            $aFieldsVariantOnGenome[0][$aDbSNPColumns[$_POST['dbSNP_column']]] = 'rs' . $aDbSNP[0];
                        } else {
                            $aFieldsVariantOnGenome[0][$aDbSNPColumns[$_POST['dbSNP_column']]] = '{dbSNP:rs' . $aDbSNP[0] . '}';
                        }
                    }

                    // Make all bases uppercase.
                    $aVariant['referenceBase'] = strtoupper($aVariant['referenceBase']);
                    $aVariant['sampleGenotype'] = strtoupper($aVariant['sampleGenotype']);
                    if (!empty($aVariant['sampleAlleles'])) {
                        $aVariant['sampleAlleles'] = strtoupper($aVariant['sampleAlleles']);
                    }

                    // Use the right prefix for the numbering scheme.
                    $sHGVSPrefix = 'g.';
                    if ($aVariant['chromosome'] == 'M') {
                        $sHGVSPrefix = 'm.';
                    }

                    // Detect format type.
                    $bSkip = false;
                    if (strlen($aVariant['referenceBase']) == 1 && strlen($aVariant['sampleGenotype']) == 1) {
                        // SNPs, from any source.
                        $aFieldsVariantOnGenome[0]['type'] = 'subst';
                        $aFieldsVariantOnGenome[0]['position_g_start'] = $aFieldsVariantOnGenome[0]['position_g_end'] = $aVariant['position'];
                        $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position'] . $aVariant['referenceBase'] . '>';

                        if (strpos('ACGT', $aVariant['sampleGenotype']) !== false) {
                            // Both alleles have changed (homozygous).
                            $aFieldsVariantOnGenome[0]['allele'] = 3;
                            $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] .= $aVariant['sampleGenotype'];
                        } elseif (strpos('MRWSYK', $aVariant['sampleGenotype']) !== false) {
                            // Heterozygous.

                            // 'Explode' the sampleGenotype into an array of non-reference alleles.
                            // Array_diff returns the values that are in the first array but not in the second.
                            $aVariant['sampleGenotype'] = array_diff($aIupacTable[$aVariant['sampleGenotype']], $aIupacTable[$aVariant['referenceBase']]);

                            if (count($aVariant['sampleGenotype']) == 1) {
                                // One of the alleles is reference.
                                $aFieldsVariantOnGenome[0]['allele'] = 0; // 'Unknown'.
                                $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] .= array_pop($aVariant['sampleGenotype']);
                            } else {
                                // Compound heterozygous.
                                $aFieldsVariantOnGenome[1] = $aFieldsVariantOnGenome[0];
                                $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] .= array_pop($aVariant['sampleGenotype']);
                                $aFieldsVariantOnGenome[1]['VariantOnGenome/DNA'] .= array_pop($aVariant['sampleGenotype']);
                                $aFieldsVariantOnGenome[0]['allele'] = 1;
                                $aFieldsVariantOnGenome[1]['allele'] = 2;
                            }
                        } else {
                            // Heterozygous trisomy or something? At the very least this is something LOVD can't handle.
                            $bSkip = true;
                        }

                    } elseif (preg_match('/^I(\d+)$/', $aVariant['sampleGenotype'], $aMatches)) {
                        // Insertions, from BED.
                        $aFieldsVariantOnGenome[0]['allele'] = 0;
                        if (!empty($aVariant['sampleAlleles']) && $aMatches[1] == 1
                            && ($nPos = array_search(
                                substr($aVariant['sampleAlleles'], 1, 1),
                                array(
                                    substr($aVariant['referenceBase'], 0, 1),
                                    substr($aVariant['referenceBase'], 2, 0)
                                )
                            )) !== false) {
                            // It's a duplication.
                            $aFieldsVariantOnGenome[0]['type'] = 'dup';
                            $aFieldsVariantOnGenome[0]['position_g_start'] = $aFieldsVariantOnGenome[0]['position_g_end'] = $aVariant['position'] + $nPos;
                            $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] = $sHGVSPrefix . $aFieldsVariantOnGenome[0]['position_g_start'] . 'dup';
                        } else {
                            // Normal insertion.
                            $aFieldsVariantOnGenome[0]['type'] = 'ins';
                            $aFieldsVariantOnGenome[0]['position_g_start'] = $aVariant['position'];
                            $aFieldsVariantOnGenome[0]['position_g_end'] = $aVariant['position'] + 1;
                            $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] = $sHGVSPrefix . $aFieldsVariantOnGenome[0]['position_g_start'] . '_' . $aFieldsVariantOnGenome[0]['position_g_end'] . 'ins' . (!empty($aVariant['sampleAlleles'])? substr($aVariant['sampleAlleles'], 1, $aMatches[1]) : $aMatches[1]);
                        }

                    } elseif (preg_match('/^D(\d+)$/', $aVariant['sampleGenotype'], $aMatches)) {
                        // Deletions, from BED.
                        $aFieldsVariantOnGenome[0]['allele'] = 0;
                        $aFieldsVariantOnGenome[0]['type'] = 'del';
                        $aFieldsVariantOnGenome[0]['position_g_start'] = $aVariant['position'] + 1;
                        $aFieldsVariantOnGenome[0]['position_g_end'] = $aVariant['position'] + $aMatches[1];
                        $aFieldsVariantOnGenome[0]['VariantOnGenome/DNA'] = $sHGVSPrefix . $aFieldsVariantOnGenome[0]['position_g_start'] . (strlen($aVariant['referenceBase']) > 1? '_' . $aFieldsVariantOnGenome[0]['position_g_end'] : '') . 'del';

                    } elseif (preg_match('/^[ACGT]-[ACGT]$/', $aVariant['referenceBase'])) {
                        // Insertion, from VCF (SeattleSeq-like columns).
                        // Although there is a way to correctly detect and import simple insertions or substitutions from this format, all other variants will fail miserably.
                        // The output does not contain sufficient information to produce HGVS descriptions, so there is simply no way we can import this file correctly.
                        ob_start();
                        lovd_showInfoTable('This file seems to be a SeattleSeq file that was created from a VCF file ' .
                                           'with the option \'SeattleSeq Annotation original allele columns\' turned on.<BR>' . "\n" .
                                           'Such files contain ambiguous data, so LOVD is unable to construct HGVS variant descriptions from them. ' .
                                           'Please re-submit your VCF file to SeattleSeq and select \'VCF-like allele columns\' instead.', 'stop');
                        $_BAR->setMessage(ob_get_clean(), 'done');
                        exit('</BODY></HTML>');

                    } elseif (preg_match('#^[ACGT]+(?:/[ACGT]+)?$#', $aVariant['sampleGenotype'])) {
                        // All indels, from VCF (VCF-like columns only, though).
                        $aFieldsVariantOnGenome[0]['position_g_start'] = $aVariant['position'];
                        $aFieldsVariantOnGenome[0]['position_g_end'] = $aVariant['position'] + strlen($aVariant['referenceBase']) - 1;

                        // Detect the genotype and set the allele field.
                        $aVariant['sampleGenotype'] = array_unique(explode('/', $aVariant['sampleGenotype']));
                        if (count($aVariant['sampleGenotype']) == 1) {
                            // Homozygous.
                            if ($aVariant['sampleGenotype'][0] == $aVariant['referenceBase']) {
                                // Homozygous reference, this is not a variant.
                                $bSkip = true;
                            }
                            $aFieldsVariantOnGenome[0]['allele'] = 3;
                        } elseif (($n = array_search($aVariant['referenceBase'], $aVariant['sampleGenotype'])) !== false) {
                            // Heterozygous, one of the alleles is reference.
                            unset($aVariant['sampleGenotype'][$n]);
                            $aFieldsVariantOnGenome[0]['allele'] = 0; // 'Unknown'.
                        } else {
                            // Compound heterozygous.
                            $aFieldsVariantOnGenome[1] = $aFieldsVariantOnGenome[0];
                            $aFieldsVariantOnGenome[0]['allele'] = 1;
                            $aFieldsVariantOnGenome[1]['allele'] = 2;
                        }

                        // Now make the HGVS descriptions.
                        foreach ($aFieldsVariantOnGenome as &$aFieldsSub) {
                            lovd_getVariantDescription($aFieldsSub, $aVariant['referenceBase'], array_pop($aVariant['sampleGenotype']));
                        }

                    } else {
                        // We can't determine (or handle) the format.
                        $bSkip = true;
                    }

                    if ($bSkip) {
                        // The variant turns out to be unparsable or invalid. Skip this variant.
                        if ($aUploadData['num_variants_unsupported'] ++ < $nMaxListedUnsupported) {
                            $aUnsupportedLines[] = lovd_reconstructSeattleSeqLine($aVariant);
                        }
                        continue;
                    }



                    // Now up to the VariantOnTranscripts.
                    foreach ($aVariant['accession'] as $i => &$sAccession) {
                        // We'll process each transcript accession number for this variant.

                        if (!empty($aAccessionToSymbol[$sAccession])) {
                            // We've encountered this accession before, so we know its symbol already.
                            $sSymbol = $aAccessionToSymbol[$sAccession];
                        } elseif ($sAccession != 'none') {
                            // We still need to get a gene symbol for this accession.
                            // First try to get the gene symbol from the database (ignoring version number).
                            list($sSymbol, $sAccessionInDB) = $_DB->q('SELECT geneid, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ?', array(substr($sAccession, 0, strpos($sAccession . '.', '.')+1) . '%'))->fetchRow();
                            if ($sSymbol) {
                                // We've got it in the database already.
                                $aAccessionMapping[$sAccession] = $sAccessionInDB;
                            } elseif (strpos($_POST['autocreate'], 't') !== false) {
                                // ONLY attempt to fetch gene information in case we're allowed to create this (apparently new) transcript.
                                // Otherwise, why bother?
                                // FIXME: Isn't it easier to just check the geneList column? See if that contains just one gene, and use that?
                                $sSymbol = lovd_callMutalyzer('getGeneName', array('build' => $_CONF['refseq_build'], 'accno' => $sAccession));
                            }
                            if (empty($sSymbol)) {
                                $sSymbol = 'none';
                            }
                            $aAccessionToSymbol[$sAccession] = $sSymbol;
                        } else {
                            $sSymbol = 'none';
                        }



                        // Now that we've parsed the transcript accession number, let's see if we need to do anything with its gene.
                        if ($sSymbol != 'none' && empty($aGenesChecked[$sSymbol])) {
                            // We haven't seen this gene before in this upload.

                            // First try to get this gene from the database.
                            if ($aGene = $_DB->q('SELECT g.refseq_UD, g.name, IF(IFNULL(t.position_g_mrna_start, 0) = IFNULL(t.position_g_mrna_end, 0), NULL, IF(t.position_g_mrna_start < t.position_g_mrna_end, "+", "-")) AS strand
                                                      FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) WHERE g.id = ? ORDER BY t.id ASC LIMIT 1', array($sSymbol))->fetchAssoc()) {
                                // We've got it in the database. Check its columns.
                                $aGene['columns'] = $_DB->q('SELECT colid FROM ' . TABLE_SHARED_COLS . ' WHERE geneid = ? AND colid IN("' . implode('", "', $aVOTCols) . '")', array($sSymbol))->fetchAllColumn();
                                $aGenesChecked[$sSymbol] = $aGene;

                            } elseif (strpos($_POST['autocreate'], 'g') !== false) {
                                // We don't have this gene in the database yet. Try to add it instead.
                                $_BAR->setMessage('Loading gene information for ' . $sSymbol . '...', 'done');

                                if (empty($aGeneInfo)) {
                                    // Getting all gene information from the HGNC takes a few seconds.
                                    $_BAR->setMessage('Loading gene data...', 'done');
                                    $aGeneInfo = lovd_getGeneInfoFromHgncOld(true, array('gd_hgnc_id', 'gd_app_sym', 'gd_app_name', 'gd_pub_chrom_map', 'gd_locus_type', 'gd_pub_eg_id', 'md_mim_id'));

                                    if (empty($aGeneInfo)) {
                                        // We can't gene information from the HGNC, so we can't add them.
                                        // This is a major problem and we can't just continue; the user will have to give permission not to create new gene entries.
                                        ob_start();
                                        lovd_showInfoTable('Could not get any gene information from the HGNC database! If this problem persists, consider importing the file without creating new gene entries. ' .
                                                           'If this is not an option for you, please try again later.', 'stop');
                                        $_BAR->setMessage(ob_get_clean(), 'done');
                                        exit('</BODY></HTML>');
                                    }

                                    // Remove the Loading gene data...' message again.
                                    $_BAR->setMessage('Loading gene information for ' . $sSymbol . '...', 'done');
                                }

                                // Get HGNC data for this gene.
                                while(true) {
                                    if (empty($aGeneInfo[$sSymbol]) || $aGeneInfo[$sSymbol]['gd_app_name'] == 'entry withdrawn' || $aGeneInfo[$sSymbol]['gd_pub_chrom_map'] == 'reserved') {
                                        // Can't use this symbol.
                                        $sSymbol = $aAccessionToSymbol[$sAccession] = 'none';
                                    } elseif (preg_match('/^symbol withdrawn, see (.+)$/', $aGeneInfo[$sSymbol]['gd_app_name'], $aNewSym)) {
                                        // Symbol is deprecated, update the accession to symbol mapping and look up the new symbol.
                                        $sSymbol = $aAccessionToSymbol[$sAccession] = $aNewSym[1];
                                        continue;
                                    }
                                    break;
                                }

                                if (!empty($aGeneInfo[$sSymbol])) {
                                    // Got gene information, prepare to add the gene to the database.
                                    // Extract gene information.
                                    list($sHgncID, $sSymbol, $sGeneName, $sChromLocation, $sLocusType, $sEntrez, $sOmim) = array_values($aGeneInfo[$sSymbol]);
                                    list($sEntrez, $sOmim) = array_map(function($var) {
                                        return is_string($var)? trim($var) : $var;
                                    }, array($sEntrez, $sOmim));
                                    if ($sChromLocation == 'mitochondria') {
                                        $sChromosome = 'M';
                                        $sChromBand = '';
                                    } else {
                                        preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches);
                                        $sChromosome = $aMatches[1];
                                        $sChromBand = $aMatches[2];
                                    }

                                    // Get the complete LRG/NG list from LOVD.nl, all at once. Saves us a lot of queries because we need to look up quite some genes.
                                    if (empty($aNgMapping)) {
                                        $aNgMapping = array();
                                        $_BAR->setMessage('Loading genomic reference list...', 'done');

                                        // Get NG's first.
                                        // FIXME; Implement cache, so we don't request this file for every gene.
                                        $aLines = lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt');
                                        foreach ($aLines as $sLine) {
                                            if (preg_match('/(\w+)\s+(NG_\d+\.\d+)/', $sLine, $aMatches)) {
                                                $aNgMapping[$aMatches[1]] = $aMatches[2];
                                            }
                                        }

                                        // Overwrite with any existing LRG's.
                                        // FIXME; Implement cache, so we don't request this file for every gene.
                                        $aLines = lovd_php_file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt');
                                        foreach ($aLines as $sLine) {
                                            if (preg_match('/(LRG_\d+)\s+(\w+)/', $sLine, $aMatches)) {
                                                $aNgMapping[$aMatches[2]] = $aMatches[1];
                                            }
                                        }

                                        // Remove the 'Loading reference list...' message.
                                        $_BAR->setMessage('Loading gene information for ' . $sSymbol . '...', 'done');
                                    }

                                    // Set genomic reference sequence for gene.
                                    if (!empty($aNgMapping[$sSymbol])) {
                                        $sRefseqGenomic = $aNgMapping[$sSymbol];
                                    } else {
                                        $sRefseqGenomic = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$sChromosome];
                                    }

                                    // Get UDID from Mutalyzer.
                                    $sRefseqUD = lovd_getUDForGene($_CONF['refseq_build'], $sSymbol);

                                    // Not adding the gene just yet, but we remember its data...
                                    // FIXME: Need to define all fields here to prevent problems with strict mode on. Most of these fields however, can just allow for NULL values.
                                    $aFieldsGene[$sSymbol] = array(
                                        'id' => $sSymbol,
                                        'name' => $sGeneName,
                                        'chromosome' => $sChromosome,
                                        'chrom_band' => $sChromBand,
                                        'refseq_genomic' => $sRefseqGenomic,
                                        'refseq_UD' => $sRefseqUD,
                                        'reference' => '',
                                        'url_homepage' => '',
                                        'url_external' => '',
                                        'allow_download' => 0,
                                        'id_hgnc' => $sHgncID,
                                        'id_entrez' => $sEntrez,
                                        'id_omim' => $sOmim,
                                        'show_hgmd' => 1,
                                        'show_genecards' => 1,
                                        'show_genetests' => 1,
                                        'show_orphanet' => 1,
                                        'note_index' => '',
                                        'note_listing' => '',
                                        'refseq' => '',
                                        'refseq_url' => '',
                                        'disclaimer' => 1,
                                        'disclaimer_text' => '',
                                        'header' => '',
                                        'header_align' => -1,
                                        'footer' => '',
                                        'footer_align' => -1,
                                        'created_by' => 0,
                                        'created_date' => date('Y-m-d H:i:s'),
                                        'updated_by' => $_AUTH['id'],
                                        'updated_date' => date('Y-m-d H:i:s')); // Set updated date because we're importing variants in it, too.

                                    // Remember we've got this gene now.
                                    $aGenesChecked[$sSymbol] = array(
                                        'refseq_UD' => $sRefseqUD,
                                        'name' => $sGeneName,
                                        'strand' => '', // HGNC doesn't have this info, but to prevent notices we do create the field.
                                        'columns' => &$aColsStandard    // By reference, this saves memory 7-fold!!
                                    );
                                }

                                // Remove the 'Loading gene information...' message.
                                $_BAR->setMessage('&nbsp;', 'done');

                            } else {
                                // We don't have this gene in the database and the user requested we won't try to add it.
                                $sSymbol = $aAccessionToSymbol[$sAccession] = 'none';
                            }
                        }

                        if ($sSymbol != 'none') {
                            // We've got gene information available for this accession. Now we can further process the transcript.

                            if (!empty($aAccessionMapping[$sAccession])) {
                                // We've seen this transcript before. We have its proper accession number.
                                $sAccession = $aAccessionMapping[$sAccession];

                            } else {
                                // Haven't seen this transcript in this upload before, so we still need to get its proper accession number.
                                // We've got to be sure we have a valid accession without a version number for the next searches.
                                if (preg_match('/^.._\d+/', $sAccession, $aAccessionClean)) {
                                    $sAccessionClean = $aAccessionClean[0];
                                } else {
                                    // This accession number doesn't look like one.
                                    $sAccession = $aAccessionMapping[$sAccession] = 'none';
                                    continue;
                                }

                                // We'll try to get it from the database first. If we don't have it, we'll try Mutalyzer.
                                // We might have matched a different version before, now find the version we have, preferring the version given by SeattleSeq, otherwise the one added last.
                                if ($sAccessionDB = $_DB->q('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC', array($sAccessionClean . '.%', $sAccession))->fetchColumn()) {
                                    // We have this transcript in the database already.
                                    $sAccession = $aAccessionMapping[$sAccession] = $sAccessionDB;

                                } elseif (strpos($_POST['autocreate'], 't') !== false) {
                                    // We don't have it in the database, but we are allowed to add it.

                                    if (!isset($aFieldsTranscript[$sSymbol])) {
                                        // We still need to contact Mutalyzer to find information for the transcripts of this gene.

                                        $aFieldsTranscript[$sSymbol] = array();
                                        $_BAR->setMessage('Loading transcript information for ' . $sSymbol . '...', 'done');

                                        $aTranscripts = lovd_callMutalyzer('getTranscriptsAndInfo', array('genomicReference' => $aGenesChecked[$sSymbol]['refseq_UD'], 'geneName' => $sSymbol));
                                        if (!empty($aTranscripts) && empty($aTranscripts['faultcode'])) {
                                            foreach ($aTranscripts as $aTranscript) {
                                                // Remember the data for each of this gene's transcripts. We may insert them as needed.
                                                $aFieldsTranscript[$sSymbol][$aTranscript['id']] = array(
                                                    'geneid' => $sSymbol,
                                                    'name' => str_replace($aGenesChecked[$sSymbol]['name'] . ', ', '', $aTranscript['product']),
                                                    'id_mutalyzer' => str_replace($sSymbol . '_v', '', $aTranscript['name']),
                                                    'id_ncbi' => $aTranscript['id'],
                                                    'id_ensembl' => '',
                                                    'id_protein_ncbi' => (empty($aTranscript['proteinTranscript']['id'])? '' : $aTranscript['proteinTranscript']['id']),
                                                    'id_protein_ensembl' => '',
                                                    'id_protein_uniprot' => '',
                                                    'position_c_mrna_start' => $aTranscript['cTransStart'],
                                                    'position_c_mrna_end' => $aTranscript['sortableTransEnd'],
                                                    'position_c_cds_end' => $aTranscript['cCDSStop'],
                                                    'position_g_mrna_start' => $aTranscript['chromTransStart'],
                                                    'position_g_mrna_end' => $aTranscript['chromTransEnd'],
                                                    'created_by' => 0,
                                                    'created_date' => date('Y-m-d H:i:s'));
                                            }
                                        }

                                        // Remove the 'Loading transcript information...' message.
                                        $_BAR->setMessage('&nbsp;', 'done');
                                    }

                                    // Now we do have all of this gene's transcripts ready for insertion; let's see if its in there.
                                    $bSuccess = false;
                                    foreach ($aFieldsTranscript[$sSymbol] as $sAccessionKey => $aFields) {
                                        if (substr($sAccessionKey, 0, strlen($sAccessionClean)) == $sAccessionClean) {
                                            // Found it, we have its data.
                                            $sAccession = $aAccessionMapping[$sAccession] = $sAccessionKey;
                                            $bSuccess = true;
                                            break;
                                        }
                                    }

                                    if (!$bSuccess) {
                                        // We still didn't find it, so Mutalyzer didn't have this transcript either.
                                        // We ignore this accession from now on by mapping it to 'none'.
                                        $sAccession = $aAccessionMapping[$sAccession] = 'none';
                                    }

                                } else {
                                    // We don't have this transcript in the database and the user requested we won't try to add it.
                                    $sAccession = $aAccessionMapping[$sAccession] = 'none';
                                }
                            }



                            if ($sAccession != 'none') {
                                // We've got transcript information available, now we can further process the VariantOnTranscript.

                                // Find out the protein change.
                                $sProteinChange = 'p.?';
                                if (in_array($aVariant['aminoAcids'][$i], array('none', 'unknown')) && (in_array($aVariant['functionGVS'][$i], array('utr-5', 'utr-3', 'coding-synonymous')) || ($aVariant['functionGVS'][$i] == 'intron' && ctype_digit($aVariant['distanceToSplice'][$i]) && $aVariant['distanceToSplice'][$i] > 10))) {
                                    $sProteinChange = 'p.(=)';
                                } elseif (!in_array($aVariant['aminoAcids'][$i], array('none', 'unknown')) && count($aFieldsVariantOnGenome) == 1) {
                                    // Because of the way SeattleSeq reports amino acids, we can only reliably define
                                    // the protein change if we have just one alternate (non-reference) allele.

                                    $aAminoAcids = preg_split('/[,\/]/', $aVariant['aminoAcids'][$i]); // Old SeattleSeq uses comma, new uses slash.
                                    $aProteinPositions = explode('/', $aVariant['proteinPosition'][$i]);
                                    if ($aVariant['functionGVS'][$i] == 'missense') {
                                        if ($aAminoAcids[0] == 'MET' && $aProteinPositions[0] == 1) {
                                            $sProteinChange = 'p.Met1?';
                                        } else {
                                            $sProteinChange = 'p.(' . ucfirst(strtolower($aAminoAcids[0])) . $aProteinPositions[0] . ucfirst(strtolower($aAminoAcids[1])) . ')';
                                        }
                                    } elseif ($aVariant['functionGVS'][$i] == 'nonsense') {
                                        if ($aAminoAcids[0] == 'stop') {
                                            $sProteinChange = 'p.(*' . $aProteinPositions[0] . ucfirst(strtolower($aAminoAcids[1])) . ')';
                                        } elseif ($aAminoAcids[1] == 'stop') {
                                            $sProteinChange = 'p.(' . ucfirst(strtolower($aAminoAcids[0])) . $aProteinPositions[0] . '*)';
                                        }
                                    }
                                }



                                foreach ($aFieldsVariantOnGenome as $j => $aFieldsVOG) {
                                    // Get the c. notation for each of the variants we're about to insert.

                                    if (!isset($aNumberConversion[$j])) {
                                        // This way, we make just one call for each variant.
                                        // Of course, we'll unset $aNumberConversion when we load new variants from the file.
                                        $aNumberConversion[$j] = array();

                                        // 2014-02-24; In an attempt to prevent more Mutalyzer calls, try and reconstruct the VOT description ourselves.
                                        if ($aGenesChecked[$sSymbol]['strand'] && in_array($aFieldsVOG['type'], array('subst'))) {
                                            // We have strand information on the gene, and we understand the variant.
                                            foreach ($aVariant['accession'] as $key => $sNM) {
                                                // Create mapping per transcript.
                                                if (!$aVariant['cDNAPosition'][$key] || !ctype_digit($aVariant['cDNAPosition'][$key])) {
                                                    // We don't have a cDNA position, abort.
                                                    $aNumberConversion[$j] = array();
                                                    break;
                                                }
                                                $sDNAVOT = str_replace('g.' . $aFieldsVOG['position_g_start'], 'c.' . $aVariant['cDNAPosition'][$key], $aFieldsVOG['VariantOnGenome/DNA']);
                                                // If we're on the negative strand, also fix the bases.
                                                if ($aGenesChecked[$sSymbol]['strand'] == '-') {
                                                    $sDNAVOT = str_replace(array('a>', 'c>', 'g>', 't>'), array('T>', 'G>', 'C>', 'A>'), strtolower($sDNAVOT));
                                                    $sDNAVOT = str_replace(array('>a', '>c', '>g', '>t'), array('>T', '>G', '>C', '>A'), $sDNAVOT);
                                                }
                                                $aNumberConversion[$j][] = $sNM . ':' . $sDNAVOT;
                                            }
                                        }

                                        if (!$aNumberConversion[$j]) {
                                            $aResponse = lovd_callMutalyzer('numberConversion', array('build' => $_CONF['refseq_build'], 'variant' => $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aFieldsVOG['VariantOnGenome/DNA']));
                                            if (!empty($aResponse)) {
                                                $aNumberConversion[$j] = $aResponse;
                                            } else {
                                                $aNumberConversion[$j] = array();
                                            }
                                        }
                                    }

                                    // We've got the c. notations, now find the notation relative to this transcript.
                                    foreach ($aNumberConversion[$j] as $x => $sVariantOnTranscript) {

                                        if (substr($sVariantOnTranscript, 0, strlen($sAccession)) == $sAccession) {
                                            // Got the variant description relative to this transcript.

                                            // 2014-02-24; In an attempt to prevent more Mutalyzer calls, try and get the positions ourselves.
                                            if (preg_match('/:c\.(-?\d+)([+-]\d+)?([ACTG]>[ACTG]|del|dup)$/', $sVariantOnTranscript, $aRegs)) {
                                                $aMappingInfo = array(
                                                    'startmain' => $aRegs[1],
                                                    'startoffset' => (!isset($aRegs[2])? 0 : (int) $aRegs[2]),
                                                    'endmain' => $aRegs[1],
                                                    'endoffset' => (!isset($aRegs[2])? 0 : (int) $aRegs[2]),
                                                );
                                            } elseif (preg_match('/:c\.(-?\d+)([+-]\d+)?_(-?\d+)([+-]\d+)?(del|dup|ins([0-9]+|[ACTG]+)?)$/', $sVariantOnTranscript, $aRegs)) {
                                                $aMappingInfo = array(
                                                    'startmain' => $aRegs[1],
                                                    'startoffset' => (!isset($aRegs[2])? 0 : (int) $aRegs[2]),
                                                    'endmain' => $aRegs[3],
                                                    'endoffset' => (!isset($aRegs[4])? 0 : (int) $aRegs[4]),
                                                );
                                            } else {
                                                // Basically only variants in the 3'UTR should get here.
                                                $aMappingInfo = lovd_callMutalyzer('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $sAccession, 'variant' => $aFieldsVOG['VariantOnGenome/DNA']));
                                                if (isset($aMappingInfo['errorcode'])) {
                                                    $aMappingInfo = array();
                                                } else {
                                                    // 2014-02-25; 3.0-10; The mappingInfo module call does not sort the positions, and as such the "start" and "end" can be in the "wrong" order.
                                                    $bSense = ($aMappingInfo['startmain'] < $aMappingInfo['endmain'] || ($aMappingInfo['startmain'] == $aMappingInfo['endmain'] && ($aMappingInfo['startoffset'] < $aMappingInfo['endoffset'] || $aMappingInfo['startoffset'] == $aMappingInfo['endoffset'])));
                                                    if (!$bSense) {
                                                        list($aMappingInfo['startmain'], $aMappingInfo['endmain']) = array($aMappingInfo['endmain'], $aMappingInfo['startmain']);
                                                        list($aMappingInfo['startoffset'], $aMappingInfo['endoffset']) = array($aMappingInfo['endoffset'], $aMappingInfo['startoffset']);
                                                    }
                                                }
                                            }
                                            if (isset($aMappingInfo['startmain']) && $aMappingInfo['startmain'] !== '') {
                                                // Also got mapping information. Prepare the VariantOnTranscript data for insertion.

                                                $aFieldsVariantOnTranscript[$j][$sAccession] = array(
                                                    'effectid' => $_SETT['var_effect_default'],
                                                    'position_c_start' => $aMappingInfo['startmain'],
                                                    'position_c_start_intron' => $aMappingInfo['startoffset'],
                                                    'position_c_end' => $aMappingInfo['endmain'],
                                                    'position_c_end_intron' => $aMappingInfo['endoffset'],
                                                    'VariantOnTranscript/DNA' => substr($sVariantOnTranscript, strpos($sVariantOnTranscript, ':') + 1),
                                                    'VariantOnTranscript/Protein' => $sProteinChange);

                                                if (in_array('VariantOnTranscript/GVS/Function', $aGenesChecked[$sSymbol]['columns'])) {
                                                    $aFieldsVariantOnTranscript[$j][$sAccession]['VariantOnTranscript/GVS/Function'] = $aVariant['functionGVS'][$i];
                                                }

                                                // cDNAPosition, polyPhen and distanceToSplice are optional columns so we should check for their existance too.
                                                if (isset($aVariant['cDNAPosition']) && in_array('VariantOnTranscript/Position', $aGenesChecked[$sSymbol]['columns'])) {
                                                    $aFieldsVariantOnTranscript[$j][$sAccession]['VariantOnTranscript/Position'] = $aVariant['cDNAPosition'][$i];
                                                }
                                                if (isset($aVariant['polyPhen']) && in_array('VariantOnTranscript/PolyPhen', $aGenesChecked[$sSymbol]['columns'])) {
                                                    $aFieldsVariantOnTranscript[$j][$sAccession]['VariantOnTranscript/PolyPhen'] = $aVariant['polyPhen'][$i];
                                                }
                                                if (isset($aVariant['distanceToSplice']) && in_array('VariantOnTranscript/Distance_to_splice_site', $aGenesChecked[$sSymbol]['columns'])) {
                                                    $aFieldsVariantOnTranscript[$j][$sAccession]['VariantOnTranscript/Distance_to_splice_site'] = $aVariant['distanceToSplice'][$i];
                                                }

                                                // lovd_fetchDBID needs some VariantOnTranscript information too.
                                                $aTranscriptDataForDBID[$j]['aTranscripts'][$i] = array($sAccession, $sSymbol);
                                                $aTranscriptDataForDBID[$j][$i . '_VariantOnTranscript/DNA'] = substr($sVariantOnTranscript, strpos($sVariantOnTranscript, ':') + 1);
                                            }
                                            continue 2;
                                        } // End of "we've got the right c. notation"
                                    } // End of c. loop
                                } // End of VOG loop
                            } // End of "we've got transcript information"
                        } // End of "we've got a gene information"
                    } // End of accession loop



                    foreach ($aFieldsVariantOnGenome as $i => $aFieldsVOG) {
                        // Now that we know the VariantOnTranscript entries, we can
                        // fetch a DBID for the VariantOnGenome and insert everything.

                        if (empty($aTranscriptDataForDBID[$i])) {
                            $aTranscriptDataForDBID[$i] = array();
                        }
                        $aFieldsVOG['VariantOnGenome/DBID'] = lovd_fetchDBID(array_merge($aFieldsVOG, $aTranscriptDataForDBID[$i]));

                        // Now finally insert the VariantOnGenome!
                        $_DB->q('INSERT INTO ' . TABLE_VARIANTS . ' (`' . implode('`, `', array_keys($aFieldsVOG)) . '`) VALUES (?' . str_repeat(', ?', count($aFieldsVOG) - 1) . ')', array_values($aFieldsVOG));
                        $nVariantID = $_DB->lastInsertId();
                        $aUploadData['num_variants'] ++;

                        // Link this variant to the current screening if applicable.
                        if (isset($_POST['screeningid'])) {
                            $qInsertScr2Var->execute(array($_POST['screeningid'], $nVariantID));
                        }

                        if (!empty($aFieldsVariantOnTranscript[$i])) {
                            // Also got some VariantOnTranscripts.

                            foreach ($aFieldsVariantOnTranscript[$i] as $sAccession => $aFieldsVOT) {

                                if (($nTranscriptID = $_DB->q('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($sAccession))->fetchColumn()) === false) {
                                    // We don't have the transcript in the database, se we need to insert it first.

                                    foreach ($aFieldsTranscript as $sSymbol => &$aFieldsTranscriptsOfGene) {
                                        if (!empty($aFieldsTranscriptsOfGene[$sAccession])) {
                                            // Found the transcript insert data.

                                            if (!empty($aFieldsGene[$sSymbol])) {
                                                // We need to insert its gene first too.
                                                $_DB->q('INSERT INTO ' . TABLE_GENES . ' (`' . implode('`, `', array_keys($aFieldsGene[$sSymbol])) . '`) VALUES (?' . str_repeat(', ?', count($aFieldsGene[$sSymbol]) - 1) . ')', array_values($aFieldsGene[$sSymbol]));
                                                $_DB->q('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?)', array($_AUTH['id'], $sSymbol, 1, 1));
                                                lovd_addAllDefaultCustomColumns('gene', $sSymbol, 0);
                                                $aUploadData['num_genes'] ++;

                                                // We have it now, don't insert it again.
                                                unset($aFieldsGene[$sSymbol]);
                                            }

                                            // Now we're ready to insert the transcript
                                            $_DB->q('INSERT INTO ' . TABLE_TRANSCRIPTS . ' (`' . implode('`, `', array_keys($aFieldsTranscriptsOfGene[$sAccession])) . '`) VALUES (?' . str_repeat(', ?', count($aFieldsTranscriptsOfGene[$sAccession]) - 1) . ')', array_values($aFieldsTranscriptsOfGene[$sAccession]));
                                            $nTranscriptID = $_DB->lastInsertId();
                                            $aUploadData['num_transcripts'] ++;
                                            unset($aFieldsTranscriptsOfGene[$sAccession]);
                                            break;
                                        }
                                    }
                                }

                                // Ready to insert the VariantOnTranscript.
                                $aFieldsVOT['id'] = $nVariantID;
                                $aFieldsVOT['transcriptid'] = $nTranscriptID;
                                $_DB->q('INSERT INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' (`' . implode('`, `', array_keys($aFieldsVOT)) . '`) VALUES (?' . str_repeat(', ?', count($aFieldsVOT) - 1) . ')', array_values($aFieldsVOT));
                                $aUploadData['num_variants_on_transcripts'] ++;
                            }
                        }
                    }
                }

                if ($_POST['statusid'] >= STATUS_MARKED) {
                    $_BAR->setMessage('Setting last updated dates for affected genes...');
                    lovd_setUpdatedDate(array_keys($aGenesChecked));
                }
            } // End SeattleSeq specific code.





            $_BAR->setMessage('Committing changes to the database...');
            $_DB->commit();

            if (empty($_INI['test'])) {
                // Done! Reopen the session. Don't show warnings; session_start() is not going
                // to be able to send another cookie. But session data is written nonetheless.
                // First commit, then restart session, otherwise two browser windows may be waiting
                // for each other forever... one for the DB lock, the other for the session lock.
                @session_start();
            }

            // Turn on automatic mapping if it is enabled for the imported variants.
            if ($nMappingFlags & MAPPING_ALLOW) {
                $_SESSION['mapping']['time_complete'] = 0;
            }

            //   Then, DELETE data from $_SESSION.
            if ($bSubmit) {
                if (!isset($aSubmit['uploads'])) {
                    $aSubmit['uploads'] = array();
                }
                $aSubmit['uploads'][$nUploadID] = $aUploadData;

                if ($aUploadData['num_variants']) {
                    lovd_saveWork();
                }

            } else {
                $_SESSION['work']['submits']['upload'][$nUploadID] = $aUploadData;
            }



            // Processing finished.
            $_BAR->setProgress(100);
            if ($aUploadData['num_variants'] && !$aUploadData['num_variants_unsupported']) {
                // Variants were imported, none were ignored.
                $_BAR->setMessage('All ' . $aUploadData['num_variants'] . ' variants have been imported successfully!');
                if ($bSubmit) {
                    $_BAR->redirectTo(lovd_getInstallURL() . 'submit/screening/' . $_POST['screeningid']);
                } else {
                    $_BAR->redirectTo(lovd_getInstallURL() . 'submit/finish/upload/' . $nUploadID);
                }
            } else {
                $_BAR->setMessage($aUploadData['num_variants'] . ' variant' . ($aUploadData['num_variants'] == 1? '' : 's') . ' were imported' . (!$aUploadData['num_variants_unsupported']? '.' : ', ' . $aUploadData['num_variants_unsupported'] . ' variant' . ($aUploadData['num_variants_unsupported'] == 1? '' : 's') . ' could not be imported.') .
                // If we're in a submission and some variants couldn't be imported, show them the list and replace it with the continuation questions when they click the Continue button.
                       ($bSubmit? '<P>' .
                                  '  <INPUT type="button" value="Continue &raquo;" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/screening/' . $_POST['screeningid'] . '\';">' .
                                  '</P>' :
                // If we're not in a submission just use the Continue button to forward the user to submit/finish/upload/123.
                                  '<FORM action="' . ROOT_PATH . 'submit/finish/upload/' . $nUploadID . '" method="GET">' .
                                  '  <INPUT type="submit" value="Continue &raquo;">' .
                                  '</FORM>'));
                if ($aUploadData['num_variants_unsupported']) {
                    $_BAR->setMessage('Below is ' . ($aUploadData['num_variants_unsupported'] > 1? 'a list of ' : '') . 'the ' . ($aUploadData['num_variants_unsupported'] > $nMaxListedUnsupported? 'first ' . $nMaxListedUnsupported . ' of ' : '') . ($aUploadData['num_variants_unsupported'] == 1? 'variant' : $aUploadData['num_variants_unsupported'] . ' variants') . ' that could not be imported.' .
                                      '<DIV style="white-space: pre; font-family: monospace; border: 1px solid #224488; overflow: auto; max-height: 300px; max-width: 1000px">' .
                                          implode("\n", $aUnsupportedLines) .
                                      '</DIV>', 'done');
                    $_BAR->setMessageVisibility('done', true);
                }
            }


            // Log it!
            lovd_writeLog('Event', LOG_EVENT, 'Imported ' . $aUploadData['num_variants'] . ' variants from ' . $aUploadData['file_type'] . ' file ' . $aUploadData['file_name']);

            // End here, we don't want to show the upload form again after a successful import.
            exit('    </BODY>' . "\n" . '</HTML>');
        }

    } else {
        // Load default values.
        $_POST['owned_by'] = $_AUTH['id'];
        $_POST['statusid'] = STATUS_OK;
        if (($nReferenceColumn = array_search('VariantOnGenome/dbSNP', $aDbSNPColumns)) || ($nReferenceColumn = array_search('VariantOnGenome/Reference', $aDbSNPColumns)) || ($nReferenceColumn = (count($aDbSNPColumns) - 1)) == 1) {
            // Import dbSNP links in the dbSNP column by default, falling back on Reference.
            // If both are not active, and there is just one candidate column, use that as a default.
            $_POST['dbSNP_column'] = $nReferenceColumn;
        }
        $_POST['allow_mapping'] = 1;
        $_POST['autocreate'] = 'gt';
        $_POST['genotype_field'] = 'pl';
    }

    if ($_GET['type'] == 'SeattleSeq') {
        lovd_showInfoTable('<B>Warning</B>: Importing large SeattleSeq files may take several hours if genes need to be created. As a rule of thumb, it will take about 10 minutes to import 1500 variants when creating genes,' . "\n" .
                           'as opposed to one minute if no genes are created.<BR>', 'warning');
        print('<DIV id="column_check"></DIV>' . "\n" .
              '<SCRIPT type="text/javascript">' . "\n" .
              '    function lovd_updateColumnMessage (sMessage) {' . "\n" .
              '        if (sMessage == "' . AJAX_NO_AUTH . '") {' . "\n" .
              '            alert("Lost your session. Please log in again.");' . "\n" .
              '        } else if (sMessage != "' . AJAX_FALSE . '") {' . "\n" .
              '            $("#column_check").html(sMessage);' . "\n" .
              '        }' . "\n" .
              '    }' . "\n" .
              "\n" .
              '    function lovd_checkColumns () {' . "\n" .
              '        $.get("' . ROOT_PATH . 'ajax/check_seattleseq_columns.php", lovd_updateColumnMessage);' . "\n" .
              '    }' . "\n" .
              "\n" .
              '    function lovd_setStandardColumn (sColumn) {' . "\n" .
              '        $.post("' . ROOT_PATH . 'ajax/edit_column.php?set_standard", "colid=" + sColumn, lovd_checkColumns);' . "\n" .
              '    }' . "\n" .
              '    $(lovd_checkColumns);' . "\n" .
              '</SCRIPT>');
    }

    if ($_GET['type'] == 'VCF') {
        lovd_showInfoTable('Please note that LOVD does not support importing deletions or insertions using the VCF 3.3 format or lower! <B>Please use VCF 4.0 or higher.</B>', 'warning', 760);
        lovd_showInfoTable('To prevent long waiting times while mapping variants and in general prevent slowness in using LOVD, we suggest pre-filtering your variants somewhat (for instance on allele frequency in the <A href="http://www.1000genomes.org/" target="_blank">1000 genomes project</A>).', 'information', 760);
    }

    // Display any errors.
    lovd_errorPrint();

    // Prepare the upload form.
    $aSelectOwner = $_DB->q('SELECT id, name FROM ' . TABLE_USERS . ' ORDER BY name')->fetchAllCombine();
    $aSelectStatus = $_SETT['data_status'];
    unset($aSelectStatus[STATUS_PENDING], $aSelectStatus[STATUS_IN_PROGRESS]);

    // Display the upload form.
    lovd_includeJS('inc-js-tooltip.php');
    print('<FORM action="' . CURRENT_PATH . '?create&amp;type=' . $_GET['type'] . ($_GET['target']? '&amp;target=' . $_GET['target'] : '') . '" method="POST" enctype="multipart/form-data">');
    $aForm = array(array('POST', '', '', '', '60%', '14', '40%'),
                   array('', '', 'print', '<B>File selection</B>'),
                   'hr',
                   array('File type', '', 'print', ($_GET['type'] == 'VCF'? 'Variant Call Format (VCF) version >= 4.0' : 'SeattleSeq Annotation file')));
    if ($_GET['type'] == 'SeattleSeq') {
        array_push($aForm,
                   array('', '', 'note', 'Files with \'SeattleSeq Annotation original allele columns\' created from indel-only VCF files are <B>not supported</B>.'));
    }
    array_push($aForm,
                   array('Select the file to import', '', 'file', 'variant_file', 25),
                   array('', 'Current file size limits:<BR>LOVD: ' . ($nMaxSizeLOVD/(1024*1024)) . 'M<BR>PHP (upload_max_filesize): ' . ini_get('upload_max_filesize') . '<BR>PHP (post_max_size): ' . ini_get('post_max_size'), 'note', 'The maximum file size accepted is ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server. Move your mouse over the help icon on the left to see the server configuration. If you wish to have it increased, contact the server\'s system administrator') . '.'),
                   array('Imported variants are assumed to be relative to Human Genome build', '', 'select', 'hg_build', 1, array($_CONF['refseq_build']), false, false, false),
                   'hr',
                   'skip',
                   array('', '', 'print', '<B>Import options</B>'),
                   'hr',
                   array('Select where to import dbSNP links, if they are present in the file',
                         $_GET['type'] . ' files may contain references to dbSNP. Please choose the column in which you would like LOVD to include such links.<BR>' .
                         '<B>Note:</B> dbSNP custom links need to be active for at least one VariantOnGenome column to be able to store them.', 'select',
                         'dbSNP_column', 1, $aDbSNPColumns, false, false, false));
    if ($_GET['type'] == 'VCF') {
        array_push($aForm,
                   array('Compute genotype data from Phred-scaled likelihood data?',
                         'Samtools (at least up to v0.1.16) does not compute the genotype (GT) field correctly. If you want to import a VCF file that was created using Samtools and which ' .
                         'includes Phred-scaled genotype likelihoods (the PL field), please choose to use PL data to determine the genotype instead.', 'select', 'genotype_field', 1,
                         array('pl' => 'Use Phred-scaled genotype likelihoods (PL)', 'gt' => 'Use genotype field (GT)'), false, false, false),
                   array('', '', 'note',
                         'If the PL field is missing, LOVD will use the GT field instead.<BR>' . "\n" .
                         'If several samples are included in the file, LOVD will not import genotype data.'),
                   'hr',
                   'skip',
                   array('', '', 'print', '<B>Mapping variants to transcripts</B>'),
                   'hr',
                   array('Automatically map these variants to known genes and transcripts', '', 'checkbox', 'allow_mapping'),
                   array('Add new genes to LOVD if any variants can be mapped to them',
                         'When checked, LOVD will automatically add a gene and transcript entry in case a variant can be mapped on it.', 'checkbox', 'allow_create_genes'),
                   array('', '', 'note', 'If automatic mapping is disabled, it is still possible to map individual variants using the link on their detailed view.'));
    } else {
        array_push($aForm,
                   array('Do you want to create new gene and transcript entries automatically?', '', 'select', 'autocreate', 1,
                         array('' => 'Do not create genes or transcripts', 't' => 'Create transcripts only', 'gt' => 'Create genes and transcripts'), false, false, false));
    }
    array_push($aForm,
                   'hr',
                   'skip',
                   array('', '', 'print', '<B>General information</B>'),
                   'hr',
                   array('Owner of all imported variants', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false),
                   array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false),
                   'hr',
                   array('', '', 'print', '<INPUT type="submit" value="Upload ' . $_GET['type'] . ' file">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'variants/upload?create' . ($_GET['target']? '&amp;target=' . $_GET['target'] : '') . '\'; return false;" style="border : 1px solid #FF4422;">' : '')));

    lovd_viewform($aForm);
    print('</FORM>');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('edit', 'publish')) && !LOVD_plus) {
    // URL: /variants/0000000001?edit
    // URL: /variants/0000000001?publish
    // Edit an entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'VariantEdit');

    lovd_isAuthorized('variant', $nID);
    if (ACTION == 'publish') {
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_OWNER);
    }

    $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id = ?', array($nID))->fetchAllColumn();
    $bGene = (!empty($aGenes));

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = array();
    $_DATA['Genome'] = new LOVD_GenomeVariant();
    $zData = $_DATA['Genome']->loadEntry($nID);
    $_POST['id'] = $nID;
    $_POST['chromosome'] = $zData['chromosome'];
    if ($bGene) {
        require ROOT_PATH . 'class/object_transcript_variants.php';
        foreach ($aGenes as $sGene) {
            // If we ever want to restrict curators in editing only certain VOTs related to a VOG linked to one of their genes,
            // change VOT object to load only the transcripts that this user is authorized to edit.
            $_DATA['Transcript'][$sGene] = new LOVD_TranscriptVariant($sGene, $nID);
            $zData = array_merge($zData, $_DATA['Transcript'][$sGene]->loadAll($nID));
        }
        // This is done so that fetchDBID can have this information and can give a better prediction.
        // We don't care about which gene we pass, because the VOT object loads *ALL* transcripts linked to this variant.
        $_POST['aTranscripts'] = $_DATA['Transcript'][$sGene]->aTranscripts;
    }

    $bSubmit = false;
    if (isset($_GET['submission'])) {
        if (isset($_AUTH['saved_work']['submissions']['screening'][$_GET['submission']])) {
            $aSubmit = $_AUTH['saved_work']['submissions']['screening'][$_GET['submission']];
            if (!empty($aSubmit['variants']) && in_array($nID, $aSubmit['variants'])) {
                $bSubmit = true;
            } elseif (!empty($aSubmit['uploads'])) {
                $aUploadDates = array();
                foreach ($aSubmit['uploads'] as $aUploadInfo) {
                    $aUploadDates[] = $aUploadInfo['upload_date'];
                }
                $bSubmit = $_DB->q('SELECT TRUE FROM ' . TABLE_VARIANTS . ' WHERE id = ? AND created_date IN (?' . str_repeat(', ?', count($aUploadDates) - 1) . ')', array_merge(array($nID), $aUploadDates))->fetchColumn();
            }
        }

        if (!$bSubmit && isset($_AUTH['saved_work']['submissions']['individual'])) {
            foreach ($_AUTH['saved_work']['submissions']['individual'] as $nIndividualID => $aSubmit) {
                if (!empty($aSubmit['variants']) && in_array($nID, $aSubmit['variants'])) {
                    $bSubmit = true;
                    break;
                }
                if (!empty($aSubmit['uploads'])) {
                    $aUploadDates = array();
                    foreach ($aSubmit['uploads'] as $aUploadInfo) {
                        $aUploadDates[] = $aUploadInfo['upload_date'];
                    }
                    $bSubmit = $_DB->q('SELECT TRUE FROM ' . TABLE_VARIANTS . ' WHERE id = ? AND created_date IN (?' . str_repeat(', ?', count($aUploadDates) - 1) . ')', array_merge(array($nID), $aUploadDates))->fetchColumn();
                    if ($bSubmit) {
                        break;
                    }
                }
            }
        }
    }

    require ROOT_PATH . 'inc-lib-form.php';

    // If we're publishing... pretend the form has been sent with a different status.
    if (GET && ACTION == 'publish') {
        // 2013-09-10; 3.0-08; Don't just throw away $_POST, because it contains info we need (such as for DB-ID prediction).
        $_POST = array_replace($_POST, $zData);
        // Now loop through $_POST to find the effectid fields, that need to be split.
        foreach ($_POST as $sKey => $sVal) {
            if (preg_match('/^(\d+_)?effect(id)$/', $sKey, $aRegs)) { // (id) instead of id to make sure we have a $aRegs (so to prevent notices).
                $_POST[$aRegs[1] . 'effect_reported'] = $sVal[0];
                $_POST[$aRegs[1] . 'effect_concluded'] = $sVal[1];
            }
        }
        $_POST['statusid'] = STATUS_OK;
    }

    if (POST || ACTION == 'publish') {
        lovd_errorClean();

        if ($bGene) {
            foreach ($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                if (!empty($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) && strlen($_POST[$nTranscriptID . '_VariantOnTranscript/DNA']) >= 6) {
                    $aResponse = lovd_getVariantInfo($_POST[$nTranscriptID . '_VariantOnTranscript/DNA'], $aTranscript[0]);
                    if ($aResponse) {
                        $_POST[$nTranscriptID . '_position_c_start'] = $aResponse['position_start'];
                        $_POST[$nTranscriptID . '_position_c_start_intron'] = $aResponse['position_start_intron'];
                        $_POST[$nTranscriptID . '_position_c_end'] = $aResponse['position_end'];
                        $_POST[$nTranscriptID . '_position_c_end_intron'] = $aResponse['position_end_intron'];
                    }
                }
            }
            foreach ($aGenes as $sGene) {
                $_DATA['Transcript'][$sGene]->checkFields($_POST);
            }

            // Set missing request values for variant effect.
            // FIXME: We're assuming there, that the genomic fields are not set, because we unset them.
            if (!isset($_POST['effect_reported'])) {
                $_POST['effect_reported'] = lovd_getMaxVOTEffects('reported', $_POST);
            }

            // FIXME: We're assuming there, that the genomic fields are not set, because we unset them.
            if (!isset($_POST['effect_concluded']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                $_POST['effect_concluded'] = lovd_getMaxVOTEffects('concluded', $_POST);
            }
        }

        // Prepare the position fields already, so they can be checked.
        $aResponse = lovd_getVariantInfo($_POST['VariantOnGenome/DNA']);
        if ($aResponse) {
            list($_POST['position_g_start'], $_POST['position_g_end'], $_POST['type']) =
                array($aResponse['position_start'], $aResponse['position_end'], $aResponse['type']);
        } else {
            $_POST['position_g_start'] = 0;
            $_POST['position_g_end'] = 0;
            $_POST['type'] = NULL;
        }
        $_DATA['Genome']->checkFields($_POST);

        if (!lovd_error()) {
            // Prepare the fields to be used for both genomic and transcript variant information.
            $aFieldsGenome = array_merge(
                array('allele', 'effectid'),
                (!$bSubmit || !empty($zData['edited_by'])? array('edited_by', 'edited_date') : array()),
                $_DATA['Genome']->buildFields()
            );

            // Prepare values.
            $_POST['effectid'] = $_POST['effect_reported'] . ($_AUTH['level'] >= $_SETT['user_level_settings']['set_concluded_effect']? $_POST['effect_concluded'] : $zData['effectid'][1]);
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFieldsGenome[] = 'owned_by';
                $aFieldsGenome[] = 'statusid';
            } elseif ($zData['statusid'] > STATUS_MARKED) {
                $aFieldsGenome[] = 'statusid';
                $_POST['statusid'] = STATUS_MARKED;
            }

            if ($_POST['VariantOnGenome/DNA'] != $zData['VariantOnGenome/DNA'] || $zData['position_g_start'] == NULL) {
                $aFieldsGenome = array_merge($aFieldsGenome, array('position_g_start', 'position_g_end', 'type', 'mapping_flags'));
                if (empty($_POST['position_g_start'])) {
                    // Variant not recognized, or no DNA given.
                    $_POST['position_g_start'] = 0;
                    $_POST['position_g_end'] = 0;
                    $_POST['type'] = NULL;
                }

                // Remove the MAPPING_NOT_RECOGNIZED and MAPPING_DONE flags if the VariantOnGenome/DNA field changes.
                $_POST['mapping_flags'] = (int) $zData['mapping_flags'] & ~(MAPPING_NOT_RECOGNIZED | MAPPING_DONE);
                if (!$_POST['position_g_start']) {
                    // We couldn't get a position, mapping will fail.
                    $_POST['mapping_flags'] |= MAPPING_NOT_RECOGNIZED;
                }
            }

            // Only actually committed to the database if we're not in a submission, or when they are already filled in.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            if (!$bSubmit && !(GET && ACTION == 'publish')) {
                // Put $zData with the old values in $_SESSION for mailing.
                $zData['allele_'] = $_DB->q('SELECT name FROM ' . TABLE_ALLELES . ' WHERE id = ?', array($zData['allele']))->fetchColumn();
                if (!empty($_POST['aTranscripts'])) {
                    $zData['aTranscripts'] = array_keys($_POST['aTranscripts']);
                }
                $_SESSION['work']['edits']['variant'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DB->beginTransaction();
            $_DATA['Genome']->updateEntry($nID, $_POST, $aFieldsGenome);

            if ($bGene) {
                foreach ($_POST['aTranscripts'] as $nTranscriptID => $aTranscript) {
                    if (!empty($_POST[$nTranscriptID . '_VariantOnTranscript/DNA'])
                        && ($_POST[$nTranscriptID . '_VariantOnTranscript/DNA'] != $zData[$nTranscriptID . '_VariantOnTranscript/DNA']
                            || $zData[$nTranscriptID . '_position_c_start'] === NULL)) {
                        if (empty($_POST[$nTranscriptID . '_position_c_start'])) {
                            // Variant not recognized, or no DNA given.
                            $_POST[$nTranscriptID . '_position_c_start'] = 0;
                            $_POST[$nTranscriptID . '_position_c_start_intron'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end'] = 0;
                            $_POST[$nTranscriptID . '_position_c_end_intron'] = 0;
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
                    $aFieldsTranscripts[$sGene] = array_merge(
                        array('effectid', 'position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron'),
                        $_DATA['Transcript'][$sGene]->buildFields());
                }
                $aTranscriptID = $_DATA['Transcript'][$sGene]->updateAll($nID, $_POST, $aFieldsTranscripts);

                // Update gene timestamp, but submitters don't have a $_POST['statusid']...
                if (!isset($_POST['statusid'])) {
                    $_POST['statusid'] = $zData['statusid'];
                }
                if (max($_POST['statusid'], $zData['statusid']) >= STATUS_MARKED) {
                    lovd_setUpdatedDate($aGenes);
                }
            }
            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited variant entry ' . $nID);

            // Thank the user...
            if ($bSubmit) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/screening/' . $_GET['submission']);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the variant entry!', 'success');

                $_T->printFooter();
            } elseif (GET && ACTION == 'publish') {
                // We'll skip the mailing. But of course only if we're sure no other changes were sent (therefore check GET).
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/variant/' . $nID . '?edit');
            }

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
        $_POST['effect_reported'] = $zData['effectid'][0];
        $_POST['effect_concluded'] = $zData['effectid'][1];
        if ($zData['statusid'] < STATUS_HIDDEN) {
            $_POST['statusid'] = STATUS_OK;
        }
        if ($bGene) {
            foreach ($aGenes as $sGene) {
                foreach ($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
                    $_POST[$nTranscriptID . '_effect_reported'] = $zData[$nTranscriptID . '_effectid'][0];
                    $_POST[$nTranscriptID . '_effect_concluded'] = $zData[$nTranscriptID . '_effectid'][1];
                }
            }
        }
    }





    $_T->printHeader();
    $_T->printTitle();

    // If we're not the creator nor the owner, warn.
    if ($zData['created_by'] != $_AUTH['id'] && $zData['owned_by'] != $_AUTH['id']) {
        lovd_showInfoTable('Warning: You are editing data not created or owned by you. You are free to correct errors such as data inserted into the wrong field or typographical errors, but make sure that all other edits are made in consultation with the submitter. If you disagree with the submitter\'s findings, add a remark rather than removing or overwriting data. In particular, do not overwrite the submitter\'s reported variant effect if you disagree, rather add your own variant effect.', 'warning', 760);
    }

    if (GET) {
        print('      To edit a variant entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Hardcoded ACTION because when we're publishing, but we get the form on screen (i.e., something is wrong), we want this to be handled as a normal edit.
    print('      <FORM id="variantForm" action="' . CURRENT_PATH . '?edit' . (isset($_GET['submission'])? '&amp;submission=' . $_GET['submission'] : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA['Genome']->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Edit variant entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/screening/' . $_GET['submission'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('inc-js-variants.php?chromosome=' . $zData['chromosome']);

    print('      <SCRIPT type="text/javascript">' . "\n" .
          '        var aUDrefseqs = {' . "\n");
    if ($bGene) {
        $i=0;
        foreach ($aGenes as $sGene) {
            echo ($i? ',' . "\n" : '') . '            \'' . $sGene . '\' : \'' . $_DB->q('SELECT refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($sGene))->fetchColumn() . '\'';
            $i++;
        }
    }
    print("\n" . '        };' . "\n" .
          '        var aTranscripts = {' . "\n");
    $i = 0;
    if ($bGene) {
        foreach ($_DATA['Transcript'][$sGene]->aTranscripts as $nTranscriptID => $aTranscript) {
            list($sTranscriptNM, $sGeneSymbol, $sMutalyzerID) = $aTranscript;
            echo ($i? ',' . "\n" : '') . '            \'' . $nTranscriptID . '\' : [\'' . $sTranscriptNM . '\', \'' . $sGeneSymbol . '\', \'' . $sMutalyzerID . '\']';
            $i++;
        }
    }
    print("\n" . '        };' . "\n\n" .
          '        $( function () {' . "\n" .
          '          $(\'#variantForm\').attr("action", $(\'#variantForm\').attr("action") + window.location.hash)' . "\n" .
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
          '          for (i in aTranscripts) {' . "\n" .
          '            var oDNA = $(\'input[name="\' + i + \'_VariantOnTranscript/DNA"]\');' . "\n" .
          '            var oProtein = $(\'input[name="\' + i + \'_VariantOnTranscript/Protein"]\');' . "\n" .
          '            if ($(oDNA).val() && !$(oProtein).val()) {' . "\n" .
          '              $(oProtein).siblings(\'button\').first().show();' . "\n" .
          '            }' . "\n" .
          '          }' . "\n" .
          '        });' . "\n" .
          '      </SCRIPT>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /variants/0000000001?delete
    // Drop specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'VariantDelete');

    lovd_isAuthorized('variant', $nID);
    lovd_requireAUTH($_SETT['user_level_settings']['delete_variant']);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
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
            // We will need to update the timestamps of any gene affected by this deletion, if the variant's status is Marked or higher.
            if ($zData['statusid'] >= STATUS_MARKED) {
                $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ?', array($nID))->fetchAllColumn();
            }

            // This also deletes the entries in TABLE_VARIANTS_ON_TRANSCRIPTS && TABLE_SCR2VAR.
            $_DATA->deleteEntry($nID);

            if ($zData['statusid'] >= STATUS_MARKED && $aGenes) {
                // Change updated date for genes.
                lovd_setUpdatedDate($aGenes);
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted variant entry #' . $nID . ' (Owner: ' . $zData['owned_by_'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the variant entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
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
                        array('Deleting variant entry', '', 'print', $nID . ' (Owner: ' . htmlspecialchars($zData['owned_by_']) . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete variant entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'search_global') {
    // URL: /variants/0000000001?search_global
    // Search an entry in other public LOVDs.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'VariantGlobalSearch');
    $_T->printHeader(false);
    $_T->printTitle();

    lovd_isAuthorized('variant', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $zData = new LOVD_GenomeVariant();
    $zData = $zData->loadEntry($nID);

    // Request data.
    $sSearchPosition = 'chr' . $zData['chromosome'] . ':' . $zData['position_g_start'];
    if ($zData['position_g_end'] != $zData['position_g_start']) {
        $sSearchPosition .= '_' . $zData['position_g_end'];
    }
    $sSignature = $_DB->q('SELECT signature FROM ' . TABLE_STATUS)->fetchColumn();
    $aData = lovd_php_file($_SETT['upstream_URL'] . 'search.php?position=' . $sSearchPosition . '&build=' . $_CONF['refseq_build'] . '&ignore_signature=' . md5($sSignature));
    if (empty($aData)) {
        // No data found.
        lovd_showInfoTable('This variant was not found in any other public LOVDs.');
        $_T->printFooter();
        exit;
    }

    print('The variant has been found in the following public LOVDs. Click the entry for which you want to see more information.<BR><BR>' . "\n");

    print('<TABLE class="data" cellpadding="0" cellspacing="1" width="100%">' . "\n" .
          '  <TR>' . "\n" .
          '    <TH>Genome&nbsp;build</TH>' . "\n" .
          '    <TH>Gene</TH>' . "\n" .
          '    <TH>Transcript</TH>' . "\n" .
          '    <TH>Position</TH>' . "\n" .
          '    <TH>DNA&nbsp;change</TH>' . "\n" .
          '    <TH>LOVD&nbsp;location</TH>' . "\n" .
          '  </TR>' . "\n");
    $aHeaders = explode("\"\t\"", trim(array_shift($aData), '"'));

    foreach ($aData as $sHit) {
        $aHit = array_combine($aHeaders, explode("\"\t\"", trim($sHit, '"')));
        print('  <TR class="data" style="cursor : pointer;" onclick="window.open(\'' . htmlspecialchars($aHit['url']) . '\', \'_blank\');">' . "\n" .
              '    <TD>' . $aHit['hg_build'] . '</TD>' . "\n" .
              '    <TD>' . $aHit['gene_id'] . '</TD>' . "\n" .
              '    <TD>' . $aHit['nm_accession'] . '</TD>' . "\n" .
              '    <TD>' . $aHit['g_position'] . '</TD>' . "\n" .
              '    <TD>' . $aHit['DNA'] . '</TD>' . "\n" .
              '    <TD>' . substr($aHit['url'], 0, strpos($aHit['url'], '/variants.php')) . '</TD>' . "\n" .
              '  </TR>' . "\n");
    }
    print('</TABLE>');
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('delete_non-preferred_transcripts', 'map'))) {
    // URL: /variants/0000000001?delete_non-preferred_transcripts
    // URL: /variants/0000000001?map
    // Map a variant to additional transcripts, or remove transcripts from the variant.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'VariantMap');

    // Require manager clearance.
    lovd_isAuthorized('variant', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    if (LOVD_plus) {
        // However, depending on the status of the screening, we might not have the rights to edit the variant.
        $zScreenings = $_DB->q('SELECT s.* FROM ' . TABLE_SCREENINGS . ' AS s INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid = ? GROUP BY s.id', array($nID))->fetchAllAssoc();
        if ($zScreenings &&
            !($_AUTH['level'] >= LEVEL_OWNER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) &&
            !($_AUTH['level'] >= LEVEL_MANAGER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION)) {
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('This analysis has been closed. It\'s not possible to edit this variant.', 'stop');
            $_T->printFooter();
            exit;
        }
    }

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->loadEntry($nID);
    // Load all transcript ID's that are currently present in the database connected to this variant.
    $aCurrentTranscripts = $_DB->q('SELECT t.id, t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid', array($nID))->fetchAllCombine();
    if (ACTION == 'delete_non-preferred_transcripts') {
        if (!LOVD_plus) {
            // Only available for LOVD+!
            exit;
        }
        // Additionally, fetch which transcripts are *not* preferred transcripts.
        $aTranscriptsToRemove = $_DB->q('SELECT t.id, t.id_ncbi FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (t.id = gp2g.transcriptid) WHERE vot.id = ?  AND gp2g.transcriptid IS NULL', array($nID))->fetchAllCombine();

        // But, remove transcripts only if we'll have at least one left!
        if (count($aCurrentTranscripts) == count($aTranscriptsToRemove)) {
            // Send user back to the VE.
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('No preferred transcripts selected for this variant.', 'stop');
            $_T->printFooter();
            exit;

        } elseif (!$aTranscriptsToRemove) {
            // And, obviously, do nothing when we have nothing to do.
            header('Refresh: 5; url=' . lovd_getInstallURL() . CURRENT_PATH);
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('This variant does not have any non-preferred transcript.<BR>All transcripts of this variant has been set as preferred transcripts in at least one gene panel.');
            $_T->printFooter();
            exit;
        }
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_POST['transcripts'] stores the IDs of the transcripts that are supposed to go in TABLE_VARIANTS_ON_TRANSCRIPTS.
        if (empty($_POST['transcripts']) || !is_array($_POST['transcripts'])) {
            $_POST['transcripts'] = array();
        } else {
            // Verify all given IDs; they need to exist in the database, and they need to be on the same chromosome.
            $aTranscripts = $_DB->q('SELECT t.id, t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) WHERE g.chromosome = ? AND t.id IN (?' . str_repeat(', ?', count($_POST['transcripts']) - 1) . ')', array_merge(array($zData['chromosome']), $_POST['transcripts']))->fetchAllCombine();
            foreach ($_POST['transcripts'] as $nTranscript) {
                if (!isset($aTranscripts[$nTranscript])) {
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
            // User had to enter their password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DB->beginTransaction();

            $aNewTranscripts = array();
            $aToRemove = array();
            $aVariantDescriptions = array();
            $aGenesUpdated = array();

            foreach ($_POST['transcripts'] as $nTranscript) {
                if ($nTranscript && !isset($aCurrentTranscripts[$nTranscript])) {
                    // If the transcript is not already present in the database connected to this variant, we will add it now.
                    $aNewTranscripts[] = $nTranscript;
                    // Gather all necessary info from this transcript.
                    $zTranscript = $_DB->q('SELECT id, geneid, name, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($nTranscript))->fetchAssoc();
                    // Call the numberConversion module of mutalyzer to get the VariantOnTranscript/DNA value for this variant on this transcript.
                    // Check if we already have the converted positions for this gene, if so, we won't have to call mutalyzer again for this information.
                    if (!array_key_exists($zTranscript['geneid'], $aVariantDescriptions)) {
                        $aResponse = lovd_callMutalyzer('numberConversion', array('build' => $_CONF['refseq_build'], 'variant' => 'chr' . $zData['chromosome'] . ':' . $zData['VariantOnGenome/DNA'], 'gene' => $zTranscript['geneid']));
                        if (!empty($aResponse)) {
                            $aVariantDescriptions[$zTranscript['geneid']] = $aResponse;
                        } else {
                            $aVariantDescriptions[$zTranscript['geneid']] = array();
                        }
                    }

                    $bAdded = false;
                    if (count($aVariantDescriptions[$zTranscript['geneid']])) {
                        // Loop through the mutalyzer output for this gene, see if we can find this transcript.
                        foreach ($aVariantDescriptions[$zTranscript['geneid']] as $key => $sVariant) {
                            // Check if our transcript is in the variant description for each value returned by mutalyzer.
                            if (!empty($sVariant) && preg_match('/^' . preg_quote($zTranscript['id_ncbi']) . ':([cn]\..+)$/', $sVariant, $aMatches)) {
                                // Call the mappingInfo module of mutalyzer to get the start & stop positions of this variant on the transcript.
                                $aMapping = array();
                                // 2017-09-22; 3.0-20; Replacing the old API call to Mutalyzer with our new lovd_getVariantInfo() function.
                                // Don't bother with a fallback, this thing is more solid than Mutalyzer's service.
                                $aResponse = lovd_getVariantInfo($aMatches[1], $zTranscript['id_ncbi']);
                                if ($aResponse) {
                                    $aMapping = array(
                                        'position_c_start' => $aResponse['position_start'],
                                        'position_c_start_intron' => $aResponse['position_start_intron'],
                                        'position_c_end' => $aResponse['position_end'],
                                        'position_c_end_intron' => $aResponse['position_end_intron'],
                                    );
                                } else {
                                    $aMapping = array(
                                        'position_c_start' => 0,
                                        'position_c_start_intron' => 0,
                                        'position_c_end' => 0,
                                        'position_c_end_intron' => 0,
                                    );
                                }
                                // Insert all the gathered information about the variant description into the database.
                                $_DB->q('INSERT INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' (id, transcriptid, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, effectid, `VariantOnTranscript/DNA`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array($nID, $nTranscript, $aMapping['position_c_start'], $aMapping['position_c_start_intron'], $aMapping['position_c_end'], $aMapping['position_c_end_intron'], $zData['effectid'], $aMatches[1]));
                                $bAdded = true;
                                $aGenesUpdated[] = $aTranscripts[$nTranscript];
                                // Speed improvement: remove this value from the output from mutalyzer, so we will not check this one again with the next transcript that we will add.
                                unset($aVariantDescriptions[$zTranscript['geneid']][$key]);
                                break;
                            }
                        }
                    }
                    if (!$bAdded) {
                        // Requested transcript was not added to the database! Usually because mutalyzer does not understand the variant.
                        // Insert simpler version of mapping: no mapping fields, no DNA field predicted.
                        $_DB->q('INSERT INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' (id, transcriptid, effectid) VALUES (?, ?, ?)', array($nID, $nTranscript, $_SETT['var_effect_default']));
                    }
                }
            }

            foreach ($aCurrentTranscripts as $nTranscript => $sGene) {
                if (!in_array($nTranscript, $_POST['transcripts'])) {
                    // If one of the transcripts currently present in the database is not present in $_POST, we will want to remove it.
                    $aToRemove[] = $nTranscript;
                    $aGenesUpdated[] = $sGene;
                }
            }

            if (!empty($aToRemove)) {
                // Remove transcript mapping from variant...
                $_DB->q('DELETE FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE id = ? AND transcriptid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove));
            }

            if ($zData['statusid'] >= STATUS_MARKED) {
                lovd_setUpdatedDate($aGenesUpdated);
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            if (ACTION == 'map') {
                lovd_writeLog('Event', LOG_EVENT, 'Updated the transcript list for variant #' . $nID);
            } elseif (ACTION == 'delete_non-preferred_transcripts' && $aToRemove) {
                $sTranscriptsRemoved = '';
                foreach ($aToRemove as $nTranscriptID) {
                    $sTranscriptsRemoved .= (!$sTranscriptsRemoved? '' : ', ') . $aTranscriptsToRemove[$nTranscriptID];
                }
                lovd_writeLog('Event', LOG_EVENT, 'Deleted non-preferred transcript annotations for variant #' . $nID . ' : ' . $sTranscriptsRemoved);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . (!empty($aNewTranscripts)? '?edit#' . implode(',', $aNewTranscripts) : ''));

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully updated the transcript list!', 'success');

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

    if (POST) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // Retrieve data for selected transcripts.
        if (!empty($_POST['transcripts'])) {
            $aVOT = $_DB->q('SELECT t.id, t.geneid, t.name, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) WHERE g.chromosome = ? AND t.id IN (?' . str_repeat(', ?', count($_POST['transcripts']) - 1) . ')', array_merge(array($zData['chromosome']), $_POST['transcripts']))->fetchAllAssoc();
        } else {
            $aVOT = array();
        }
    } else {
        $aVOT = $_DB->q('SELECT t.id, t.geneid, t.name, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE vot.id = ? ORDER BY t.geneid, id_ncbi', array($nID))->fetchAllAssoc();
    }

    if (!(LOVD_plus && ACTION == 'delete_non-preferred_transcripts')) {
        // Normal mapping feature.
        lovd_showInfoTable('The variant entry is currently NOT mapped to the following transcripts. Click on a transcript to map the variant to it.', 'information');

        $_GET['page_size'] = 10;
        $_GET['search_tid'] = '';
        foreach ($aVOT as $aTranscript) {
            $_GET['search_tid'] .= '!' . $aTranscript['id'] . ' ';
        }
        $_GET['search_tid'] = (!empty($_GET['search_tid'])? rtrim($_GET['search_tid']) : '!0');
        $_GET['search_chromosome'] = '="' . $zData['chromosome'] . '"';
        require ROOT_PATH . 'class/object_custom_viewlists.php';
        $_DATA = new LOVD_CustomViewList(array('Gene', 'Transcript', 'DistanceToVar'), $zData['id']); // DistanceToVar needs the VariantID.
        $_DATA->setRowLink('VOT_map', 'javascript:lovd_addTranscript(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_geneid}}\', \'{{zData_name}}\', \'{{zData_id_ncbi}}\'); return false;');
        $_DATA->viewList('VOT_map', array('track_history' => false));
        print('      <BR><BR>' . "\n\n");

        lovd_showInfoTable('The variant entry is currently mapped to the following transcripts. Click on the cross at the right side of the transcript to remove the mapping.', 'information');

    } else {
        // Only deselecting transcripts, not adding anything.
        lovd_showInfoTable('The following transcript' . (count($aTranscriptsToRemove) == 1? '' : 's') . ' have been deselected from this variant: ' . implode(', ', $aTranscriptsToRemove) . '. If you wish, you can deselect more by clicking on the cross at the right side of the transcript.<BR>Please confirm removal by typing in your password below and click "Save transcript list".', 'information');
    }

    print('      <TABLE class="sortable_head" style="width : 652px;"><TR><TH width="100">Gene</TH>' .
          '<TH style="text-align : left;">Name</TH><TH width="123" style="text-align : left;">Transcript ID</TH><TH width="20">&nbsp;</TH>' .
          '</TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
          '        <UL id="transcript_list" class="sortable" style="margin-top : 0px; width : 650px;">' . "\n");
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
                    array('', '', 'print', '<INPUT type="submit" value="Save transcript list">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '\'; return false;" style="border : 1px solid #FF4422;">'),
                  );
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");
?>

      <SCRIPT type='text/javascript'>
        function lovd_addTranscript (sViewListID, nID, sGene, sName, sNM)
        {
            // Moves the transcript to the variant mapping block and removes the row from the viewList.
            var objViewListF = document.getElementById('viewlistForm_' + sViewListID);
            var objElement = document.getElementById(nID);
            objElement.style.cursor = 'progress';

            var objUsers = document.getElementById('transcript_list');
            var oLI = document.createElement('LI');
            oLI.id = 'li_' + nID;
            oLI.innerHTML = '<INPUT type="hidden" name="transcripts[]" value="' + nID + '"><TABLE width="100%"><TR><TD width="98">' + sGene + '</TD><TD align="left">' + sName + '</TD><TD width="120" align="left">' + sNM + '</TD><TD width="20" align="right"><A href="#" onclick="lovd_removeTranscript(\'VOT_map\', \'' + nID + '\', \'' + sNM + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR></TABLE>';
            objUsers.appendChild(oLI);

            // Then, remove this row from the table.
            objElement.style.cursor = '';
            lovd_AJAX_viewListHideRow(sViewListID, nID);
            objViewListF.total.value --;
            lovd_AJAX_viewListUpdateEntriesString(sViewListID);
            // 2013-09-26; 3.0-08; First do this, THEN add the next row, otherwise you're just duplicating the last visible row all the time.
            // Also change the search terms in the viewList such that submitting it will not reshow this item.
            objViewListF.search_tid.value += ' !' + nID;
            // Does an ltrim, too. But trim() doesn't work in IE < 9.
            objViewListF.search_tid.value = objViewListF.search_tid.value.replace(/^\s*/, '');

            lovd_AJAX_viewListAddNextRow(sViewListID);
            return true;
        }


        function lovd_removeTranscript (sViewListID, nID, sNM)
        {
            var aCurrentTranscripts = '<?php echo implode(';', array_keys($aCurrentTranscripts)); ?>'.split(";");
            if ($.inArray(nID, aCurrentTranscripts) == -1 || window.confirm('You are about to remove the variant description of transcript ' + sNM + ' from this variant.\n\nOk:\t\tRemove variant description of this transcript from the database.\nCancel:\tCancel the removal.')) {
                // Removes the mapping of the variant from this transcript and reloads the viewList with the transcript back in there.
                objViewListF = document.getElementById('viewlistForm_' + sViewListID);
                objLI = document.getElementById('li_' + nID);

                // First remove from block, simply done (no fancy animation).
                objLI.parentNode.removeChild(objLI);

                // Reset the viewList.
                // Does an ltrim, too. But trim() doesn't work in IE < 9.
                objViewListF.search_tid.value = objViewListF.search_tid.value.replace('!' + nID, '').replace('  ', ' ').replace(/^\s*/, '');
                // If the filter is now empty, it will be disabled by the submitting VL and it won't function anymore.
                if (!objViewListF.search_tid.value) {
                    objViewListF.search_tid.value = '!0';
                }
                lovd_AJAX_viewListSubmit(sViewListID);

                return true;
            } else {
                return false;
            }
        }
      </SCRIPT>
<?php

    if (LOVD_plus && ACTION == 'delete_non-preferred_transcripts') {
        // Trigger the removal of the non-preferred transcripts.
        print('
      <SCRIPT type="text/javascript">
        $.each(["' . implode('", "', array_keys($aTranscriptsToRemove)) . '"], function(index, value) {
            $("#li_" + value).remove();
        });
      </SCRIPT>' . "\n\n");
    }
    $_T->printFooter();
    exit;
}
?>
