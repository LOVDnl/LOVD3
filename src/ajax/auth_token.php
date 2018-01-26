<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-17
 * Modified    : 2017-12-11
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
if (PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('create', 'revoke', 'view'))) {
    die('alert("Error while sending data.");');
}

// Require manager clearance.
if (!$_AUTH || !lovd_isAuthorized('user', $_PE[2])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Let's download the user's data.
$nID = sprintf('%0' . $_SETT['objectid_length']['users'] . 'd', $_PE[2]);
$zUser = $_DB->query('SELECT id, username, auth_token, auth_token_expires FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();

if (!$zUser) {
    // FIXME: Should we log this?
    die('alert("Data not found.");');
}

// If we get there, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#auth_token_dialog").length) {
    $("body").append("<DIV id=\'auth_token_dialog\' title=\'API authorization token\'></DIV>");
}
if (!$("#auth_token_dialog").hasClass("ui-dialog-content") || !$("#auth_token_dialog").dialog("isOpen")) {
    $("#auth_token_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
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

$sFormCreate    = '<FORM id=\'auth_token_create_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Please select the validity of the token.<BR><SELECT name=\'auth_token_expires\'><OPTION value=\'\'>forever</OPTION><OPTION value=\'604800\'>1 week</OPTION><OPTION value=\'2592000\'>1 month</OPTION><OPTION value=\'7776000\'>3 months</OPTION><OPTION value=\'31536000\'>1 year</OPTION></SELECT></FORM>';
$sFormRevoke    = '<FORM id=\'auth_token_revoke_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to revoke your current API key?</FORM>';
$sMessageLSDBID = ($_AUTH['level'] != LEVEL_ADMIN? '' : '<B>This LOVD\'s ID: ' . md5($_STAT['signature']) . '</B><BR><BR>');
$sMessageIntro  = 'Since LOVD 3.0-18, LOVD contains an API that allows for the direct submission of data into the database. To use this API, you\'ll need an API token that serves to authorize you instead of using your username and password in the data file.';
$sMessageCreate = 'You can create a new token by clicking &quot;Create new token&quot; below. This will revoke any existing tokens, if any. This also allows you to set an expiration to your token; after the expiration date, you will no longer be able to use this token and you will need to renew it.';
$sMessageRevoke = 'You can also revoke your token completely, without creating a new one, blocking access of this token to the API completely. You can do this by clicking &quot;Revoke token&quot; below.';
$bToken = !empty($zUser['auth_token']);
$bTokenExpired = (!empty($zUser['auth_token_expires']) && strtotime($zUser['auth_token_expires']) <= time());

// Set JS variables and objects.
print('
var bToken = ' . (int) $bToken . ';
var bTokenExpired = ' . (int) $bTokenExpired . ';
var oButtonCreate = {"Create new token":function () { if (bToken && !bTokenExpired) { if (!window.confirm("Are you sure you want to create a new token, invalidating the current token?")) { return false; }} $.get("' . CURRENT_PATH . '?create"); }};
var oButtonRevoke = {"Revoke token":function () { $.get("' . CURRENT_PATH . '?revoke"); }};
var oButtonBack   = {"Back":function () { $.get("' . CURRENT_PATH . '?view"); }};
var oButtonCancel = {"Cancel":function () { $.get("' . CURRENT_PATH . '?view"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
var oButtonFormCreate = {"Create new token":function () { $.post("' . CURRENT_PATH . '?create", $("#auth_token_create_form").serialize()); }};
var oButtonFormRevoke = {"Yes, revoke token":function () { $.post("' . CURRENT_PATH . '?revoke", $("#auth_token_revoke_form").serialize()); }};


');





if (ACTION == 'create' && GET) {
    // Show create form.
    // We do this in two steps, not only because we need to know the expiration of the token, but also to prevent CSRF.

    $_SESSION['csrf_tokens']['auth_token_create'] = md5(uniqid());
    $sFormCreate = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['auth_token_create'], $sFormCreate);

    // Display the form, and put the right buttons in place.
    print('
    $("#auth_token_dialog").html("' . $sFormCreate . '<BR>");

    // Select the right buttons.
    $("#auth_token_dialog").dialog({buttons: $.extend({}, oButtonFormCreate, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'create' && POST) {
    // Process create form.
    // We do this in two steps, not only because we need to know the expiration of the token, but also to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['auth_token_create']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    if (!isset($_POST['auth_token_expires']) || !($_POST['auth_token_expires'] === '' || ctype_digit($_POST['auth_token_expires']))) {
        die('alert("Error while sending data, please select a valid expiration time.");');
    }

    // Define expiration date.
    if ($_POST['auth_token_expires']) {
        $sAuthTokenExpires = date('Y-m-d H:i:s', time() + $_POST['auth_token_expires']);
    } else {
        $sAuthTokenExpires = NULL;
    }

    // Generate new token.
    $sToken = md5($zUser['username'] . microtime(true) . bin2hex(openssl_random_pseudo_bytes(10)));

    // Update!
    if (!$_DB->query('UPDATE ' . TABLE_USERS . ' SET auth_token = ?, auth_token_expires = ? WHERE id = ?', array($sToken, $sAuthTokenExpires, $nID), false)) {
        die('alert("Failed to create new token.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the token has been created and stored successfully!
    lovd_writeLog('Event', 'AuthTokenCreate', 'Successfully created new API token, expires ' . $sAuthTokenExpires);

    // Display the form, and put the right buttons in place.
    print('
    $("#auth_token_dialog").html("Token created successfully!");
    lovd_reloadUserVE();
    
    // Select the right buttons.
    $("#auth_token_dialog").dialog({buttons: oButtonBack}); 
    ');
    exit;
}





if (ACTION == 'revoke' && GET) {
    // Show revoke form.
    // We do this in two steps, to prevent CSRF.

    $_SESSION['csrf_tokens']['auth_token_revoke'] = md5(uniqid());
    $sFormRevoke = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['auth_token_revoke'], $sFormRevoke);

    // Display the form, and put the right buttons in place.
    print('
    $("#auth_token_dialog").html("' . $sFormRevoke . '<BR>");

    // Select the right buttons.
    $("#auth_token_dialog").dialog({buttons: $.extend({}, oButtonFormRevoke, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'revoke' && POST) {
    // Process revoke form.
    // We do this in two steps, to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['auth_token_revoke']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    // Update!
    if (!$_DB->query('UPDATE ' . TABLE_USERS . ' SET auth_token = NULL, auth_token_expires = NULL WHERE id = ?', array($nID), false)) {
        die('alert("Failed to revoke token.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }
    // If we get here, the token has been revoked successfully!
    lovd_writeLog('Event', 'AuthTokenRevoke', 'Successfully revoked current API token');

    // Display the form, and put the right buttons in place.
    print('
    $("#auth_token_dialog").html("Token revoked successfully!");
    lovd_reloadUserVE();
    
    // Select the right buttons.
    $("#auth_token_dialog").dialog({buttons: oButtonBack}); 
    ');
    exit;
}





if (ACTION == 'view') {
    // View current token and status.
    print('
    $("#auth_token_dialog").html("' . $sMessageLSDBID . $sMessageIntro . '<BR>");
    $("#auth_token_dialog").append("' . $sMessageCreate . '<BR>");
    if (bToken) {
        $("#auth_token_dialog").append("' . $sMessageRevoke . '<BR>");
    }
    $("#auth_token_dialog").append("<BR>");
    
    // If we have a token, show it.
    if (bToken) {
        if (bTokenExpired) {
            $("#auth_token_dialog").append("Your current token has expired.<BR>");
        } else {
            $("#auth_token_dialog").append("Your current token:<BR><PRE>' . $zUser['auth_token'] . '</PRE>");
        }
    }
    
    // Select the right buttons.
    var oButtons = $.extend({}, oButtonCreate);
    if (bToken) {
        $.extend(oButtons, oButtonRevoke);
    }
    $.extend(oButtons, oButtonClose);
    $("#auth_token_dialog").dialog({buttons: oButtons}); 
    ');
    exit;
}
?>
