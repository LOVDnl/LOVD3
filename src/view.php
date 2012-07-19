<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-12-05
 * Modified    : 2012-07-19
 * For LOVD    : 3.0-beta-07
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





if (!ACTION && !empty($_PE[1]) && !ctype_digit($_PE[1])) {
    // URL: /view/DMD
    // URL: /view/DMD/NM_004006.2
    // View all entries in a specific gene, affecting a specific trancript, with all joinable data.

    if (in_array(rawurldecode($_PE[1]), lovd_getGeneList())) {
        $sGene = rawurldecode($_PE[1]);
        lovd_isAuthorized('gene', $sGene); // To show non public entries.

        // Curators are allowed to download this list...
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            define('FORMAT_ALLOW_TEXTPLAIN', true);
        }

        // Overview is given per transcript. If there is only one, it will be mentioned. If there are more, you will be able to select which one you'd like to see.
        $aTranscripts = $_DB->query('SELECT t.id, t.id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) WHERE t.geneid = ? AND vot.id IS NOT NULL', array($sGene))->fetchAllCombine();
        $nTranscripts = count($aTranscripts);

        // If NM is mentioned, check if exists for this gene. If not, reload page without NM. Otherwise, restrict $aTranscripts.
        if (!empty($_PE[2])) {
            $nTranscript = array_search($_PE[2], $aTranscripts);
            if ($nTranscript === false) {
                // NM does not exist. Throw error or just simply redirect?
                header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1]);
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

    define('PAGE_TITLE', 'Full data view for ' . $sGene);
    define('TAB_SELECTED', 'variants');
    $_T->printHeader();
    $_T->printTitle();

    $sViewListID = 'CustomVL_VIEW_' . $sGene;

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
        $sTranscript = $sSelect . '</SELECT>';
        $sMessage = 'The variants shown are described using the ' . $sTranscript . ' transcript reference sequence.';
    }
    if (FORMAT == 'text/html') {
        lovd_showInfoTable($sMessage);
    }

    if ($nTranscripts > 0) {
        require ROOT_PATH . 'class/object_custom_viewlists.php';
        $_DATA = new LOVD_CustomViewList(array('VariantOnTranscript', 'VariantOnGenome', 'Screening', 'Individual'), $sGene);
        $_DATA->viewList($sViewListID, array('transcriptid', 'chromosome'), false, false, (bool) ($_AUTH['level'] >= LEVEL_CURATOR));
    }

    $_T->printFooter();
    exit;
}
?>
