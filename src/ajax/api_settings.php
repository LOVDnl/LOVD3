<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-09-22
 * Modified    : 2020-09-23
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
if (PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('edit'))) {
    die('alert("Error while sending data.");');
}

// Require manager clearance AND authorization on this user (manager or admin).
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER || !lovd_isAuthorized('user', $_PE[2])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Let's download the user's data.
$nID = sprintf('%0' . $_SETT['objectid_length']['users'] . 'd', $_PE[2]);
$zUser = $_DB->query('SELECT id, username, api_settings FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();
$zUser['api_settings'] = @json_decode($zUser['api_settings'], true);

if (!$zUser) {
    // FIXME: Should we log this?
    die('alert("Data not found.");');
}

// If we get there, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#api_settings_dialog").length) {
    $("body").append("<DIV id=\'api_settings_dialog\' title=\'API settings for ' . $zUser['username'] . '\'></DIV>");
}
if (!$("#api_settings_dialog").hasClass("ui-dialog-content") || !$("#api_settings_dialog").dialog("isOpen")) {
    $("#api_settings_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}

function lovd_reloadUserVE ()
{
    // Reloads the VE if we\'ve changed the token info.
    $.get("ajax/viewentry.php", { object: "User", id: "' . $nID . '" },
        function (sData) {
            if (sData.length > 2) {
                $("#viewentryDiv").html("\n" + sData);
            }
        });
}


');

$aFields = array(
    'auto-schedule_submissions' => array(
        'Auto-schedule API submissions?',
        'Note that you\'ll need to configure automatic import of scheduled files to actually automatically process these submissions.',
    ),
    'process_as_public' => array(
        'Process data directly as Public?',
        'Normally, new submissions are set to Pending, until a Curator publishes them. This setting will directly publish new API submissions from this user when they are processed.'
    ),
    'allow_variant-only_submissions' => array(
        'Allow variant-only submissions?',
        'For data sources that aggregate data and cannot submit full case-level data.',
    ),
);
$sFormEdit = '<FORM id=\'api_settings_edit_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Please select which options you would like to enable for this user.<BR><BR><TABLE>';
foreach ($aFields as $sField => $aText) {
    $sFormEdit .= '<TR valign=\'top\'><TD><INPUT type=\'checkbox\' name=\'' . $sField . '\' value=\'1\'' . (empty($zUser['api_settings'][$sField])? '' : ' checked') . '></TD>' .
        '<TD><B>' . $aText[0] . '</B><BR>' . $aText[1] . '</TD></TR>';
}
$sFormEdit .= '</TABLE><BR></FORM>';

// Set JS variables and objects.
print('
var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
var oButtonFormEdit = {"Edit settings":function () { $.post("' . CURRENT_PATH . '?edit", $("#api_settings_edit_form").serialize()); }};


');





if (ACTION == 'edit' && GET) {
    // Show edit form.
    // We do this in two steps to prevent CSRF.

    $_SESSION['csrf_tokens']['api_settings_edit'] = md5(uniqid());
    $sFormEdit = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['api_settings_edit'], $sFormEdit);

    // Display the form, and put the right buttons in place.
    print('
    $("#api_settings_dialog").html("' . $sFormEdit . '<BR>");

    // Select the right buttons.
    $("#api_settings_dialog").dialog({buttons: $.extend({}, oButtonFormEdit, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'edit' && POST) {
    // Process edit form.
    // We do this in two steps to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['api_settings_edit']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    // Generate settings array.
    $aSettings = array();
    foreach (array_keys($aFields) as $sCol) {
        if (!empty($_POST[$sCol])) {
            $aSettings[$sCol] = 1;
        }
    }
    if (!$aSettings) {
        // To prevent json_encode() storing '[]'.
        $sSettings = '{}';
    } else {
        $sSettings = json_encode($aSettings);
    }

    // Update!
    if (!$_DB->query('UPDATE ' . TABLE_USERS . ' SET api_settings = ? WHERE id = ?',
        array($sSettings, $nID), false)) {
        die('alert("Failed to edit settings.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the token has been edited and stored successfully!
    lovd_writeLog('Event', 'APISettingsEdit', 'Successfully edited API settings (' . implode(', ', array_keys($aSettings)) . ') for user #' . $nID);

    // Display the form, and put the right buttons in place.
    print('
    $("#api_settings_dialog").html("Settings edited successfully!");
    lovd_reloadUserVE();
    
    // Select the right buttons.
    $("#api_settings_dialog").dialog({buttons: oButtonClose}); 
    ');
    exit;
}
?>
