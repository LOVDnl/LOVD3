<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-07-28
 * Modified    : 2020-07-29
 * For LOVD    : 3.0-25
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
if (!$("#merge_set_dialog").length) {
    $("body").append("<DIV id=\'merge_set_dialog\' title=\'Merge entries\'></DIV>");
}
if (!$("#merge_set_dialog").hasClass("ui-dialog-content") || !$("#merge_set_dialog").dialog("isOpen")) {
    $("#merge_set_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');


// Set JS variables and objects.
print('
var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};


');

// Allowed types.
$aObjectTypes = array(
    'individuals',
    'screenings',
);





function lovd_showMergeDialog ($aJob)
{
    // Receives the job, shows the dialog, creates the form to call the process.
    // If we would only use GET here without confirmation, CSRF would be
    //  possible. Also, GET shouldn't be used for data manipulation.

    if (!isset($aJob['objects'])
        || count($aJob['objects']) > 1 ||
        count(current($aJob['objects'])) <= 1) {
        // Something's wrong with this job.
        die('
        $("#merge_set_dialog").html("Did not recognize the job. This may be a bug in LOVD; please report.").dialog({buttons: oButtonClose});');
    }

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

    $_SESSION['csrf_tokens']['merge_entries'] = md5(uniqid());
    $sDialog = str_replace(
        '{{CSRF_TOKEN}}',
        $_SESSION['csrf_tokens']['merge_entries'],
        '<FORM id=\'merge_entries_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>' .
        'Please confirm merging the following ' . count(current($aJob['objects'])) . ' entries.</FORM><BR><TABLE>');

    foreach ($aJob['objects'] as $sObjectType => $aObjects) {
        $sDialog .= '<TR><TD valign=top rowspan=' . count($aObjects) . '><B>' . $sObjectType . '</B></TD>';
        foreach ($aObjects as $nKey => $nObjectID) {
            $sDialog .= (!$nKey? '' : '<TR>') .
                '<TD valign=top>#' . $nObjectID . '</TD></TR>';
        }
    }
    $sDialog .= '</TABLE><BR><BR>';

    // Display the form, and put the right buttons in place.
    print('
    $("#merge_set_dialog").html("' . $sDialog . '");

    // Select the right buttons.
    var oButtonMerge = {"Merge entries":function () { $.post("' . CURRENT_PATH . '?process&workid=' . $nWorkID . '", $("#merge_entries_form").serialize()); }};
    $("#merge_set_dialog").dialog({buttons: $.extend({}, oButtonMerge, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'fromVL' && GET && !empty($_GET['vlid'])) {
    // URL: /ajax/merge_entries.php?fromVL&vlid=Individuals
    // Fetch object IDs, and call the merging process.

    if (!isset($_SESSION['viewlists'][$_GET['vlid']])) {
        die('$("#merge_set_dialog").html("Data listing not found. Please try to reload the page and try again.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['options']['merge_set'])) {
        die('$("#merge_set_dialog").html("Data listing does not allow merging entries.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['checked'])) {
        die('$("#merge_set_dialog").html("No entries selected yet to merge.");');
    }

    // Determine type.
    $sObjectType = '';
    if (!empty($_SESSION['viewlists'][$_GET['vlid']]['row_link'])) {
        $sObjectType = substr($_SESSION['viewlists'][$_GET['vlid']]['row_link'], 0, strpos($_SESSION['viewlists'][$_GET['vlid']]['row_link'], '/'));
    }
    if (!in_array($sObjectType, $aObjectTypes)) {
        die('
        $("#merge_set_dialog").html("Did not recognize object type. This may be a bug in LOVD; please report.");');
    }

    $aValues = array_values($_SESSION['viewlists'][$_GET['vlid']]['checked']);
    sort($aValues);
    $aJob = array(
        'objects' => array(
            $sObjectType => $aValues,
        ),
        'post_action' => array(
            'uncheck_VL' => $_GET['vlid'],
            'reload_VL' => $_GET['vlid'],
            'go_to' => lovd_getInstallURL() . $sObjectType . '/' . $aValues[0],
        ),
    );

    // Open dialog, and list the data types.
    lovd_showMergeDialog($aJob);
    exit;
}





if (ACTION == 'process' && !empty($_GET['workid']) && POST) {
    // URL: /ajax/merge_entries.php?process&workid=1583341843.3402
    // Process work as stored in $_SESSION.
    // We do this in two steps to get confirmation and to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['merge_entries']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    if (!isset($_SESSION['work'][CURRENT_PATH][$_GET['workid']])) {
        die('alert("Work ID not found. This may be a bug in LOVD; please report.");');
    } elseif (empty($_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job'])
        || empty($_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job']['objects'])) {
        die('alert("Found nothing to do?");');
    }

    define('LOG_EVENT', 'MergeEntries');

    // For lovd_setUpdatedDate().
    require ROOT_PATH . 'inc-lib-form.php';

    $aJob = $_SESSION['work'][CURRENT_PATH][$_GET['workid']]['job'];

    // To prevent race condition, first start
    //  a transaction before we check the entries.
    $_DB->beginTransaction();

    foreach ($aJob['objects'] as $sObjectType => $aObjects) {
        $nMergedID = current($aObjects);
        $aMergedData = array();
        $aUpdatedFields = array();
        $aConflictingFields = array();

        // Object IDs have already been sorted.
        foreach ($aObjects as $nKey => $nObjectID) {
            // Loop through the records and compare them. There should be no
            //  conflicts, if we want to merge them.

            // First check if we're authorized at all on this entry.
            if (!lovd_isAuthorized(rtrim($sObjectType, 's'), $nObjectID, false)) {
                // Oops, no, we're not. This should not really be possible, since the menu should be turned off if
                //  you're not authorized. So this suggests foul play. But either way, block this.
                print('
                $("#merge_set_dialog").html("You are not authorized to merge these entries.").dialog({buttons: oButtonClose});');
                exit;
            }

            switch ($sObjectType) {
                case 'individuals':
                    $zData = $_DB->query('
                        SELECT i.*, GROUP_CONCAT(i2d.diseaseid ORDER BY i2d.diseaseid SEPARATOR ";") AS diseaseids
                        FROM ' . TABLE_INDIVIDUALS . ' AS i LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid)
                        WHERE i.id = ?', array($nObjectID))->fetchAssoc();
                    if ($zData['statusid'] >= STATUS_MARKED) {
                        // Collect genes that will be affected.
                        $aGenes = $_DB->query('
                            SELECT DISTINCT t.geneid
                            FROM ' . TABLE_TRANSCRIPTS . ' AS t
                              LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)
                              LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id)
                              LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                              LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)
                            WHERE s.individualid = ? AND vog.statusid >= ?', array($nObjectID, STATUS_MARKED))->fetchAllColumn();
                    }
                    break;

                default:
                    die('alert("Unhandled object type ' . $sObjectType . '.");');
            }

            if (!$nKey) {
                $aMergedData = $zData;

            } else {
                // Loop $zData looking for conflicts, reasons to not merge.
                foreach ($zData as $sCol => $sValue) {
                    switch ($sCol) {
                        case 'id':
                            // Of course this is different. Ignore.
                            break;
                        case 'created_by':
                            if ($zData['created_date'] < $aMergedData['created_date']) {
                                $aMergedData[$sCol] = $zData[$sCol];
                                $aUpdatedFields[] = $sCol;
                            }
                            break;
                        case 'created_date':
                            if ($zData[$sCol] < $aMergedData[$sCol]) {
                                $aMergedData[$sCol] = $zData[$sCol];
                                $aUpdatedFields[] = $sCol;
                            }
                            break;
                        case 'edited_by':
                            if ($aMergedData[$sCol] != $_AUTH['id']) {
                                $aMergedData[$sCol] = $_AUTH['id'];
                                $aUpdatedFields[] = $sCol;
                            }
                            break;
                        case 'edited_date':
                            $aMergedData[$sCol] = date('Y-m-d H:i:s');
                            $aUpdatedFields[] = $sCol;
                            break;
                        case 'diseaseids':
                            // We won't just update this; any difference
                            //  is regarded a conflict.
                            if ($aMergedData[$sCol] !== $zData[$sCol]) {
                                $aConflictingFields[] = $sCol;
                            }
                            break;
                        case 'statusid':
                            if ($aMergedData[$sCol] < $zData[$sCol]) {
                                $aMergedData[$sCol] = $zData[$sCol];
                                $aUpdatedFields[] = $sCol;
                            }
                            break;
                        default:
                            if ($zData[$sCol] && !$aMergedData[$sCol]) {
                                $aMergedData[$sCol] = $zData[$sCol];
                                $aUpdatedFields[] = $sCol;
                            } elseif ($zData[$sCol] && $aMergedData[$sCol] != $zData[$sCol]) {
                                // Data conflict.
                                $aConflictingFields[] = $sCol;
                            } // Else, data is the same or $zData[$sCol] is empty; do nothing.
                    }
                }
            }
        }

        if (!$aConflictingFields) {
            // Update data, including foreign keys.
            foreach ($aObjects as $nKey => $nObjectID) {
                if (!$nKey) {
                    // The first entry is just being updated.
                    $aUpdatedFields = array_unique($aUpdatedFields);
                    if ($aUpdatedFields) {
                        $sColumns = implode(', ', array_map(function ($sColumn) {
                            return '`' . $sColumn . '` = ?';
                        }, $aUpdatedFields));
                        $aValues = array();
                        foreach ($aUpdatedFields as $sColumn) {
                            $aValues[] = $aMergedData[$sColumn];
                        }
                        $aValues[] = $nMergedID;
                        $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET ' . $sColumns . ' WHERE id = ?', $aValues);
                    }

                } else {
                    switch ($sObjectType) {
                        case 'individuals':
                            // Move over phenotype entries and screenings.
                            $_DB->query('UPDATE ' . TABLE_PHENOTYPES . ' SET individualid = ? WHERE individualid = ?', array($nMergedID, $nObjectID));
                            $_DB->query('UPDATE ' . TABLE_SCREENINGS . ' SET individualid = ? WHERE individualid = ?', array($nMergedID, $nObjectID));
                            // Delete IND2DIS entries, we already compared them.
                            $_DB->query('DELETE FROM ' . TABLE_IND2DIS . ' WHERE individualid = ?', array($nObjectID));
                            // Move parent or panel ID references.
                            $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET fatherid = ? WHERE fatherid = ?', array($nMergedID, $nObjectID));
                            $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET motherid = ? WHERE motherid = ?', array($nMergedID, $nObjectID));
                            $_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET panelid = ? WHERE panelid = ?', array($nMergedID, $nObjectID));
                            $_DB->query('DELETE FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($nObjectID));
                            lovd_writeLog('Event', LOG_EVENT, 'Merged ' . rtrim($sObjectType, 's') . ' entry #' . $nObjectID . ' into entry #' . $nMergedID);
                            break;
                    }
                }
            }

            if ($aMergedData['statusid'] >= STATUS_MARKED && $aGenes) {
                lovd_setUpdatedDate($aGenes);
            }
            $_DB->commit();

            // Update the display.
            print('
            $("#merge_set_dialog").html("Successfully merged entries!").dialog({buttons: oButtonClose});');

            // Anything more that we're supposed to do?
            if (!empty($aJob['post_action'])) {
                foreach ($aJob['post_action'] as $sAction => $sArg) {
                    switch ($sAction) {
                        case 'go_to':
                            // Redirect to another page.
                            print('
                            $("#merge_set_dialog").on("dialogclose", function(event, ui) { window.location.href = "' . $sArg . '"; });');
                            break;
                        case 'reload_page':
                            // Reload the entire page.
                            print('
                            $("#merge_set_dialog").on("dialogclose", function(event, ui) { window.location.href = window.location; });');
                            break;
                        case 'reload_VL':
                            // Reload the VL.
                            print('
                            lovd_AJAX_viewListSubmit("' . $sArg . '");');
                            break;
                        case 'uncheck_VL':
                            // Deselect all checkboxes.
                            print('
                            check_list["' . $sArg . '"] = "none";');
                            break;
                        default:
                            print('
                            $("#merge_set_dialog").append("<BR>Unknown post action ' . htmlspecialchars($sAction) . '");');
                    }
                }
            }

        } else {
            // Conflicts, can't merge entries.
            $_DB->rollBack();

            $aConflictingFields = array_unique($aConflictingFields);
            print('
            $("#merge_set_dialog").html("Entries can not be merged because of conflicting values in the following field(s):<BR>' .
                '- ' . implode('<BR>- ', $aConflictingFields) . '<BR><BR>Resolve these conflicting values first, then try again.").dialog({buttons: oButtonClose});');
        }
    }

    // Clear the data.
    unset($_SESSION['work'][CURRENT_PATH][$_GET['workid']]);
    exit;
}
?>
