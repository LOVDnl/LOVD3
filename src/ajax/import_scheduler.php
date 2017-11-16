<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-11-16
 * Modified    : 2017-11-16
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
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
if (PATH_COUNT != 3 || !in_array(ACTION, array('reschedule', 'set_priority', 'unschedule', 'view'))) {
    die('alert("Error while sending data.");');
}

// Require manager clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Check info currently in the scheduler.
$sFile = $_PE[2];
$zFile = $_DB->query('SELECT * FROM ' . TABLE_SCHEDULED_IMPORTS . ' WHERE filename = ?', array($sFile))->fetchAssoc();

if (!$zFile) {
    // FIXME: Should we log this?
    die('alert("Data not found.");');
}

// If we get there, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#import_scheduler_dialog").length) {
    $("body").append("<DIV id=\'import_scheduler_dialog\' title=\'Import file scheduler\'></DIV>");
}
if (!$("#import_scheduler_dialog").hasClass("ui-dialog-content") || !$("#import_scheduler_dialog").dialog("isOpen")) {
    $("#import_scheduler_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');

$bFileLost  = !file_exists($_INI['paths']['data_files'] . '/' . $sFile);
$nPriority  = $zFile['priority'];
$bProcessed = (int) $zFile['in_progress'];
$bError     = !empty($zFile['process_errors']);

$sFormUnschedule     = '<FORM id=\'import_scheduler_unschedule_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to unschedule this file?</FORM>';
$sFormSetPriority    = '<FORM id=\'import_scheduler_set_priority_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Please select the priority with which the file will be processed.<BR><SELECT name=\'priority\'>' .
    implode('', array_map(
            function ($nID, $sValue) {
                global $nPriority;
                return '<OPTION value=\'' . $nID . '\'' . ($nID != $nPriority? '' : ' selected') . '>' . $sValue . '</OPTION>';
            },
            array_keys($_SETT['import_priorities']), $_SETT['import_priorities'])
    ) . '</SELECT></FORM>';
$sFormReschedule     = '<FORM id=\'import_scheduler_reschedule_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to clear all errors and reschedule this file?</FORM>';
$sMessageIntro       = 'Please choose from the actions below.<BR>';
$sMessageUnschedule  = 'You can unschedule this file, which will remove it from the list of files to import. You can do this by clicking &quot;Unschedule file&quot; below.<BR>';
$sMessageSetPriority = 'You can set the priority with which this file will be imported by clicking &quot;Set priority&quot; below.<BR>';
$sMessageReschedule  = 'You can also reschedule this file, resetting it and putting it back at the end of the queue, so LOVD will try to import it again. You can do this by clicking &quot;Reschedule file&quot; below.<BR>';

// Set JS variables and objects.
print('
var bFileLost              = ' . (int) $bFileLost . ';
var bError                 = ' . (int) $bError . ';
var oButtonUnschedule      = {"Unschedule file":function () { $.get("' . CURRENT_PATH . '?unschedule"); }};
var oButtonSetPriority     = {"Set priority":function () { $.get("' . CURRENT_PATH . '?set_priority"); }};
var oButtonReschedule      = {"Reschedule file":function () { $.get("' . CURRENT_PATH . '?reschedule"); }};
var oButtonBack            = {"Back":function () { $.get("' . CURRENT_PATH . '?view"); }};
var oButtonCancel          = {"Cancel":function () { $.get("' . CURRENT_PATH . '?view"); }};
var oButtonClose           = {"Close":function () { $(this).dialog("close"); }};
var oButtonFormUnschedule  = {"Yes, unschedule":function () { $.post("' . CURRENT_PATH . '?unschedule", $("#import_scheduler_unschedule_form").serialize()); }};
var oButtonFormSetPriority = {"Set priority":function () { $.post("' . CURRENT_PATH . '?set_priority", $("#import_scheduler_set_priority_form").serialize()); }};
var oButtonFormReschedule  = {"Yes, reschedule file":function () { $.post("' . CURRENT_PATH . '?reschedule", $("#import_scheduler_reschedule_form").serialize()); }};


');





if (ACTION == 'unschedule' && GET) {
    // Show form to unschedule a file.
    // We do this in two steps, to prevent CSRF.

    $_SESSION['csrf_tokens']['import_scheduler_unschedule'] = md5(uniqid());
    $sFormUnschedule = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['import_scheduler_unschedule'], $sFormUnschedule);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("' . $sFormUnschedule . '<BR>");

    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: $.extend({}, oButtonFormUnschedule, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'unschedule' && POST) {
    // Process form to unschedule a file.
    // We do this in two steps, to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['import_scheduler_unschedule']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    // Delete!
    if (!$_DB->query('DELETE FROM ' . TABLE_SCHEDULED_IMPORTS . ' WHERE filename = ?', array($sFile), false)) {
        die('alert("Failed to unschedule file.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the file has been successfully unscheduled!
    lovd_writeLog('Event', 'ImportUnschedule', 'Successfully unscheduled ' . $sFile);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("File successfully unscheduled!");
    setTimeout(\'window.location.href = window.location.href;\', 1000);
    
    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: oButtonBack}); 
    ');
    exit;
}





if (ACTION == 'set_priority' && GET) {
    // Show form for setting the priority.
    // We do this in two steps, not only because we need to know the priority level, but also to prevent CSRF.

    $_SESSION['csrf_tokens']['import_scheduler_set_priority'] = md5(uniqid());
    $sFormSetPriority = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['import_scheduler_set_priority'], $sFormSetPriority);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("' . $sFormSetPriority . '<BR>");

    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: $.extend({}, oButtonFormSetPriority, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'set_priority' && POST) {
    // Process form for setting the priority.
    // We do this in two steps, not only because we need to know the priority level, but also to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['import_scheduler_set_priority']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    if (!isset($_POST['priority']) || !isset($_SETT['import_priorities'][$_POST['priority']])) {
        die('alert("Error while sending data, please select a valid priority.");');
    }

    // Update!
    if (!$_DB->query('UPDATE ' . TABLE_SCHEDULED_IMPORTS . ' SET priority = ? WHERE filename = ?', array($_POST['priority'], $sFile), false)) {
        die('alert("Failed to set priority.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the token has been created and stored successfully!
    lovd_writeLog('Event', 'ImportSetPriority', 'Successfully set priority of ' . $sFile . ' to ' . $_SETT['import_priorities'][$_POST['priority']]);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("Successfully set the priority!");
    setTimeout(\'window.location.href = window.location.href;\', 1000);
    
    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: oButtonBack}); 
    ');
    exit;
}





if (ACTION == 'reschedule' && GET) {
    // Show form to reschedule a file.
    // We do this in two steps, to prevent CSRF.

    $_SESSION['csrf_tokens']['import_scheduler_reschedule'] = md5(uniqid());
    $sFormReschedule = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['import_scheduler_reschedule'], $sFormReschedule);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("' . $sFormReschedule . '<BR>");

    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: $.extend({}, oButtonFormReschedule, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'reschedule' && POST) {
    // Process form to reschedule a file.
    // We do this in two steps, to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['import_scheduler_reschedule']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    // Update!
    if (!$_DB->query('UPDATE ' . TABLE_SCHEDULED_IMPORTS . ' SET in_progress = 0, scheduled_by = ?, scheduled_date = NOW(), process_errors = NULL, processed_by = NULL, processed_date = NULL WHERE filename = ?', array($_AUTH['id'], $sFile), false)) {
        die('alert("Failed to reschedule file.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the file has been successfully rescheduled!
    lovd_writeLog('Event', 'ImportReschedule', 'Successfully rescheduled ' . $sFile);

    // Display the form, and put the right buttons in place.
    print('
    $("#import_scheduler_dialog").html("File successfully rescheduled!");
    setTimeout(\'window.location.href = window.location.href;\', 1000);
    
    // Select the right buttons.
    $("#import_scheduler_dialog").dialog({buttons: oButtonBack}); 
    ');
    exit;
}





if (ACTION == 'view') {
    // View current token and status.
    print('
    $("#import_scheduler_dialog").html("' . $sMessageIntro . '<BR>");
    $("#import_scheduler_dialog").append("' . $sMessageUnschedule . '<BR>");
    if (!bError && !bFileLost) {
        $("#import_scheduler_dialog").append("' . $sMessageSetPriority . '<BR>");
    }
    if (bError && !bFileLost) {
        $("#import_scheduler_dialog").append("' . $sMessageReschedule . '<BR>");
    }
    $("#import_scheduler_dialog").append("<BR>");
    
    // Select the right buttons.
    var oButtons = $.extend({}, oButtonUnschedule);
    if (!bError && !bFileLost) {
        $.extend(oButtons, oButtonSetPriority);
    }
    if (bError && !bFileLost) {
        $.extend(oButtons, oButtonReschedule);
    }
    $.extend(oButtons, oButtonClose);
    $("#import_scheduler_dialog").dialog({buttons: oButtons}); 
    ');
    exit;
}
?>
