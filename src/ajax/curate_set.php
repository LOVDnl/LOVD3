<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-04
 * Modified    : 2020-03-10
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
header('Content-type: text/javascript; charset=UTF-8');

// Check for basic format.
if (!ACTION || !in_array(ACTION, array('fromVL', 'process'))) {
    die('alert("Error while sending data.");');
}

// Require curator clearance (any gene).
if (!lovd_isAuthorized('gene', $_AUTH['curates'])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}



// If we get there, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#curate_set_dialog").length) {
    $("body").append("<DIV id=\'curate_set_dialog\' title=\'Curate (publish) entries\'></DIV>");
}
if (!$("#curate_set_dialog").hasClass("ui-dialog-content") || !$("#curate_set_dialog").dialog("isOpen")) {
    $("#curate_set_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');


// Set JS variables and objects.
print('
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};


');

// Allowed types.
$aObjectTypes = array(
    'individuals',
    'phenotypes',
    'variants',
);





function lovd_showCurationDialog ($aJob)
{
    // Receives a job description, shows the dialog, and calls the process.

    $sDialog = 'Checking and publishing entries, please wait...<BR><BR><TABLE>';
    foreach ($aJob['objects'] as $sObjectType => $aObjects) {
        $sDialog .= '<TR><TD valign=top rowspan=' . count($aObjects) . '><B>' . $sObjectType . '</B></TD>';
        foreach ($aObjects as $nKey => $nObjectID) {
            $sDialog .= (!$nKey? '' : '<TR>') .
                '<TD valign=top>#' . $nObjectID . '</TD>' .
                '<TD valign=top id=' . $sObjectType . '_' . $nObjectID . '_status></TD>' .
                '<TD id=' . $sObjectType . '_' . $nObjectID . '_errors></TD></TR>';
        }
    }
    $sDialog .= '</TABLE><BR><TABLE>' .
        '<TR><TD><IMG src=\"gfx/cross.png\"></TD><TD>Errors occurred, entry was not published.</TD></TR>' .
        '<TR><TD><IMG src=\"gfx/check_orange.png\"></TD><TD>Errors occurred, but entry was already public.</TD></TR>' .
        '<TR><TD><IMG src=\"gfx/check.png\"></TD><TD>No errors, entry was published or was already public.</TD></TR></TABLE><BR><BR><TABLE>';

    print('
    $("#curate_set_dialog").html("' . $sDialog . '<BR>");

    // Select the right buttons.
    $("#curate_set_dialog").dialog({buttons: $.extend({}, oButtonClose)});');

    // Store data in SESSION. I don't really want to POST it over.
    if (!isset($_SESSION['work'][CURRENT_PATH])) {
        $_SESSION['work'][CURRENT_PATH] = array();
    }

    // Clean up old work IDs...
    while (count($_SESSION['work'][CURRENT_PATH]) >= 5) {
        unset($_SESSION['work'][CURRENT_PATH][min(array_keys($_SESSION['work'][CURRENT_PATH]))]);
    }

    // Generate an unique workID that is sortable.
    $nWorkID = (string) microtime(true);
    $_SESSION['work'][CURRENT_PATH][$nWorkID]['job'] = $aJob;

    print('
    $.get("' . CURRENT_PATH . '?process&workid=' . $nWorkID . '").fail(function(){alert("Request failed. Please try again.");});');
    exit;
}





if (ACTION == 'fromVL' && GET) {
    // URL: /ajax/curate_set.php?fromVL&vlid=VOG
    // Fetch object types and object IDs, and call the curation process.

    if (!isset($_SESSION['viewlists'][$_GET['vlid']])) {
        die('$("#curate_set_dialog").html("Data listing not found. Please try to reload the page and try again.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['options']['curate_set'])) {
        die('$("#curate_set_dialog").html("Data listing does not allow curation of a set.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['checked'])) {
        die('$("#curate_set_dialog").html("No entries selected yet to curate.");');
    }

    // Determine type.
    $sObjectType = '';
    if (!empty($_SESSION['viewlists'][$_GET['vlid']]['row_link'])) {
        $sObjectType = substr($_SESSION['viewlists'][$_GET['vlid']]['row_link'], 0, strpos($_SESSION['viewlists'][$_GET['vlid']]['row_link'], '/'));
    }
    if (!in_array($sObjectType, $aObjectTypes)) {
        die('
        $("#curate_set_dialog").html("Did not recognize object type. This may be a bug in LOVD; please report.");');
    }

    $aJob = array(
        'objects' => array(
            $sObjectType => array_values($_SESSION['viewlists'][$_GET['vlid']]['checked']),
        ),
        'post_action' => array(
            'reload_VL' => $_GET['vlid'],
        ),
    );

    // Variants in a VOG/VOT view, sometimes have checked IDs that include the VOT's transcript ID. Fix that.
    if ($sObjectType == 'variants' && strpos($aJob['objects']['variants'][0], ':') !== false) {
        foreach ($aJob['objects']['variants'] as $nKey => $sID) {
            $aJob['objects']['variants'][$nKey] = str_pad(strstr($sID, ':', true), $_SETT['objectid_length']['variants'], '0', STR_PAD_LEFT);
        }
        // Values can be non-unique due to multiple transcripts.
        $aJob['objects']['variants'] = array_unique($aJob['objects']['variants']);
    }

    // Open dialog, and list the data types.
    lovd_showCurationDialog($aJob);
    exit;
}





if (ACTION == 'process' && !empty($_GET['workid']) && GET) {
    // URL: /ajax/curate_set.php?process&workid=1583341843.3402
    // Process work as stored in $_SESSION.

    if (!isset($_SESSION['work'][CURRENT_PATH][$_GET['workid']])) {
        die('alert("Work ID not found. This may be a bug in LOVD; please report.");');
    } elseif (empty($_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job'])
        || empty($_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job']['objects'])) {
        die('alert("Found nothing to do?");');
    }

    define('LOG_EVENT','QuickCurate');

    require ROOT_PATH . 'inc-lib-form.php';

    $aCheckFieldsOptions = array(
        'mandatory_password' => false,  // Password field is not mandatory.
        'trim_fields' => false,         // No trimming of whitespace.
    );

    $aJob = $_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job'];
    foreach ($aJob['objects'] as $sObjectType => $aObjects) {
        // Load necessary objects.

        switch ($sObjectType) {
            case 'individuals':
                require ROOT_PATH . 'class/object_individuals.php';
                $_DATA = new LOVD_Individual();
                break;
            case 'phenotypes':
                require ROOT_PATH . 'class/object_phenotypes.php';
                // We could reload LOVD_Phenotype() for each entry, but we're handling this for a VL, so load that ID.
                $nDiseaseID = NULL;
                if (empty($aJob['post_action']['reload_VL'])) {
                    die('alert("To curate phenotype entries, we need a disease ID. This may be a bug in LOVD; please report.");');
                } else {
                    // Get disease ID from the end of the VL ID.
                    $aVLID = explode('_', $aJob['post_action']['reload_VL']);
                    $nDiseaseID = array_pop($aVLID); // array_pop needs a var, can't combine these lines without @.
                }
                $_DATA = new LOVD_Phenotype($nDiseaseID);
                break;
            case 'variants':
                require ROOT_PATH . 'class/object_genome_variants.php';
                require ROOT_PATH . 'class/object_transcript_variants.php';

                $_DATA = array();
                $_DATA['Genome'] = new LOVD_GenomeVariant();
                $bDBID = in_array('VariantOnGenome/DBID', $_DATA['Genome']->buildFields());
                break;
            default:
                die('alert("Unhandled object type ' . $sObjectType . '.");');
        }



        foreach ($aObjects as $nKey => $nObjectID) {
            // Loop through the individual records, loading the data, running checkFields(), and running the update.

            // First check if we're authorized at all on this entry.
            if (!lovd_isAuthorized(rtrim($sObjectType, 's'), $nObjectID, false)) {
                // Oops, no, we're not. This should not really be possible, since the menu should be turned off if
                //  you're not authorized. So this suggests foul play. But either way, block this.
                print('
                $("#' . $sObjectType . '_' . $nObjectID . '_status").html("<IMG src=gfx/cross.png>");
                $("#' . $sObjectType . '_' . $nObjectID . '_errors").html("You are not authorized to curate this entry.");');
                continue;
            }

            $_POST = array(
                'statusid' => STATUS_OK,
                'edited_by' => $_AUTH['id'],
                'edited_date' => date('Y-m-d H:i:s'),
            );

            $_DB->beginTransaction();

            switch ($sObjectType) {
                case 'individuals':
                case 'phenotypes':
                    if ($sObjectType == 'individuals') {
                        $aGenes = $_DB->query('
                            SELECT DISTINCT t.geneid
                            FROM ' . TABLE_TRANSCRIPTS . ' AS t
                              LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id)
                              LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id)
                              LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                              LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)
                            WHERE s.individualid = ? AND vog.statusid >= ?', array($nObjectID, STATUS_MARKED))->fetchAllColumn();
                    } elseif ($sObjectType == 'phenotypes') {
                        $aGenes = $_DB->query('
                            SELECT DISTINCT t.geneid
                            FROM ' . TABLE_TRANSCRIPTS . ' AS t
                              LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id)
                              LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id)
                              LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                              LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)
                              LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i on (s.individualid = i.id)
                              LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid)
                            WHERE p.id = ? AND i.statusid >= ? AND vog.statusid >= ?', array($nObjectID, STATUS_MARKED, STATUS_MARKED))->fetchAllColumn();
                    }

                    $zData = $_DATA->loadEntry($nObjectID);
                    $_POST += $zData; // Won't overwrite existing key (statusid).
                    lovd_errorClean();
                    $_DATA->checkFields($_POST, $zData, $aCheckFieldsOptions);

                    if (!lovd_error() && $zData['statusid'] < STATUS_OK) {
                        // Prepare the fields to be used, but only update when entry isn't already public.
                        $aFields = array('statusid', 'edited_by', 'edited_date');
                        $_DATA->updateEntry($nObjectID, $_POST, $aFields);
                    }
                    break;

                case 'variants':
                    $zData = $_DATA['Genome']->loadEntry($nObjectID);
                    $_DATA['Transcript'] = array();

                    // FIXME: The following ~20 lines are just repeated from other code. It would be good to have a method for this in the VOG object.
                    // Load gene-related data.
                    $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vot.id = ?', array($nObjectID))->fetchAllColumn();

                    if ($aGenes) {
                        foreach ($aGenes as $sGene) {
                            $_DATA['Transcript'][$sGene] = new LOVD_TranscriptVariant($sGene, $nObjectID);
                            $zData = array_merge($zData, $_DATA['Transcript'][$sGene]->loadAll($nObjectID));
                        }

                        if ($bDBID) {
                            // This is done so that fetchDBID can have this information and can give a better prediction.
                            // We don't care about which gene we pass, because the VOT object loads *ALL* transcripts linked to this variant.
                            $_POST['aTranscripts'] = $_DATA['Transcript'][$sGene]->aTranscripts;
                        }
                    }

                    $_POST += $zData; // Won't overwrite existing key (statusid).
                    // Now loop through $_POST to find the effectid fields, that need to be split.
                    foreach ($_POST as $key => $val) {
                        if (preg_match('/^(\d+_)?effect(id)$/', $key, $aRegs)) { // (id) instead of id to make sure we have a $aRegs (so to prevent notices).
                            $_POST[$aRegs[1] . 'effect_reported'] = $val{0};
                            $_POST[$aRegs[1] . 'effect_concluded'] = $val{1};
                        }
                    }

                    lovd_errorClean();

                    foreach ($aGenes as $sGene) {
                        $_DATA['Transcript'][$sGene]->checkFields($_POST, $zData, $aCheckFieldsOptions);
                    }
                    $_DATA['Genome']->checkFields($_POST, $zData, $aCheckFieldsOptions);

                    if (!lovd_error() && $zData['statusid'] < STATUS_OK) {
                        // Prepare the fields to be used, but only update when entry isn't already public.
                        $aFields = array_merge(
                            array('statusid', 'edited_by', 'edited_date'),
                            (!$bDBID? array() : array('VariantOnGenome/DBID')));

                        $_DATA['Genome']->updateEntry($nObjectID, $_POST, $aFields);
                    }
                    break;

                default:
                    die('alert("Unhandled object type ' . $sObjectType . '.");');
            }

            if (!lovd_error()) {
                if ($zData['statusid'] < STATUS_OK) {
                    if ($aGenes) {
                        lovd_setUpdatedDate($aGenes);
                    }
                    $_DB->commit();

                    lovd_writeLog('Event', LOG_EVENT, 'Curated ' . rtrim($sObjectType, 's') . ' information entry ' . $nObjectID);

                } else {
                    // We have nothing to do.
                    $_DB->rollBack();
                }

                // Update the display.
                print('
                $("#' . $sObjectType . '_' . $nObjectID . '_status").html("<IMG src=gfx/check.png>");
                $("#' . $sObjectType . '_' . $nObjectID . '_errors").html("' . ($zData['statusid'] < STATUS_OK? 'Successfully published entry' : 'Entry was already public') . '");');

            } else {
                // Check failed, no update.
                $_DB->rollBack();

                print('
                $("#' . $sObjectType . '_' . $nObjectID . '_status").html("<IMG src=gfx/' . ($zData['statusid'] < STATUS_OK? 'cross' : 'check_orange') . '.png>");
                $("#' . $sObjectType . '_' . $nObjectID . '_errors").html("' . addslashes($_ERROR['messages'][1]) . (count($_ERROR['messages']) <= 2? '' : ' (' . (count($_ERROR['messages']) - 2) . ' more)') . '");');
            }
        }
    }

    // Anything more that we're supposed to do?
    if (!empty($aJob['post_action'])) {
        foreach ($aJob['post_action'] as $sAction => $sArg) {
            switch ($sAction) {
                case 'reload_VL':
                    // Reload the VL. But first, deselect all checkboxes.
                    print('
                    check_list["' . $sArg . '"] = "none";
                    lovd_AJAX_viewListSubmit("' . $sArg . '");');
                    break;
                default:
                    print('
                    $("#curate_set_dialog").append("<BR>Unknown post action ' . htmlspecialchars($sAction) . '");');
            }
        }
    }

    // Clear the data.
    unset($_SESSION['work'][CURRENT_PATH][$_GET['workid']]);
}
?>
