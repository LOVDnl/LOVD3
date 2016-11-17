<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-17
 * Modified    : 2016-11-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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
if (PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('view'))) {
    die('alert("Error while sending data.");');
}

// Require manager clearance.
if (!$_AUTH || !lovd_isAuthorized('user', $_PE[2])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Let's download the user's data.
$nID = sprintf('%0' . $_SETT['objectid_length']['users'] . 'd', $_PE[2]);
$zUser = $_DB->query('SELECT id, auth_token, auth_token_expires FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchAssoc();

if (!$zUser) {
    // FIXME: Should we log this?
    die('alert("Data not found.");');
}

// If we get there, we want to show the dialog for sure.
print('
if (!$("#auth_token_dialog").hasClass("ui-dialog-content") || !$("#auth_token_dialog").dialog("isOpen")) {
    $("#auth_token_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');

$sMessageIntro  = 'Since LOVD 3.0-18, LOVD contains an API that allows for the direct submission of data into the database. This API is currently undocumented and stil in beta. To use this API, you\'ll need an API token that serves to authorize you instead of using your username and password in the data file.';
$sMessageCreate = 'You can create a new token by clicking &quot;Create new token&quot; below. This will revoke any existing tokens, if any. This also allows you to set an expiration to your token; after the expiration date, you will no longer be able to use this token and you will need to renew it.';
$sMessageRevoke = 'You can also revoke your token completely, without creating a new one, blocking access of this token to the API completely. You can do this by clicking &quote;Revoke token&quot; below.';
$bToken = !empty($zUser['auth_token']);
$bTokenExpired = (strtotime($zUser['auth_token_expires']) <= time());

// Set JS variables and objects.
print('
var bToken = ' . (int) $bToken . ';
var bTokenExpired = ' . (int) $bTokenExpired . ';
var oButtonCreate = {"Create new token":function () { $.get("' . CURRENT_PATH . '?create"); }};
var oButtonRevoke = {"Revoke token":function () { $.get("' . CURRENT_PATH . '?revoke"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};


');





if (ACTION == 'view') {
    // View current token and status.
    print('
    $("#auth_token_dialog").html("' . $sMessageIntro . '<BR>");
    $("#auth_token_dialog").append("' . $sMessageCreate . '<BR>");
    if (bToken) {
        $("#auth_token_dialog").append("' . $sMessageRevoke . '<BR>");
    }
    $("#auth_token_dialog").append("<BR>");
    
    // If we have a token, show it.
    if (bToken) {
        $("#auth_token_dialog").append("Your current token:<BR><PRE>' . $zUser['auth_token'] . '</PRE><BR>");
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
