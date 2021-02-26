<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-02-25
 * Modified    : 2021-02-26
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

// URL: /ajax/licenses.php/individual/00000001
// URL: /ajax/licenses.php/user/00001

// Check for basic format.
if (PATH_COUNT != 4 || !in_array($_PE[2], array('individual', 'user'))
    || !ctype_digit($_PE[3]) || !in_array(ACTION, array('edit'))) {
    die('alert("Error while sending data.");');
}

// Require authorization on this user.
if (!$_AUTH || !lovd_isAuthorized($_PE[2], $_PE[3])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Let's download the user's data.
$nID = lovd_getCurrentID();
$sObject = $_PE[2];
if ($sObject == 'individual') {
    $rObject = $_DB->query('
        SELECT CONCAT("individual #", i.id), IFNULL(i.license, uc.default_license)
        FROM ' . TABLE_INDIVIDUALS . ' AS i
          INNER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id)
        WHERE i.id = ?', array($nID))->fetchRow();
} elseif ($sObject == 'user') {
    $rObject = $_DB->query('SELECT username, default_license FROM ' . TABLE_USERS . ' WHERE id = ?', array($nID))->fetchRow();
}
if (!$rObject) {
    // FIXME: Should we log this?
    die('alert("Data not found.");');
}
list($sName, $sLicense) = $rObject;

// If we get here, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#licenses_dialog").length) {
    $("body").append("<DIV id=\'licenses_dialog\' title=\'License settings for ' . $sName . '\'></DIV>");
}
if (!$("#licenses_dialog").hasClass("ui-dialog-content") || !$("#licenses_dialog").dialog("isOpen")) {
    $("#licenses_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}

function lovd_reloadVE (sObject)
{
    // Reloads the VE if we\'ve changed the token info.
    $.get("ajax/viewentry.php", { object: sObject, id: "' . $nID . '" },
        function (sData) {
            if (sData.length > 2) {
                $("#viewentryDiv").html("\n" + sData);
            }
        });
}

function lovd_showLicense ()
{
    // Checks the form\'s contents and displays the chosen license.
    var sLicense = "";
    $("#licenses_edit_form input[name=license]").val("");
    if ($("#licenses_edit_form input:radio:checked").length > 1) {
        // Both parts of the form filled in.
        sLicense = "cc_by";
        if ($("#licenses_edit_form input[name=commercial]:checked").val() == "no") {
            sLicense += "-nc";
        }
        if ($("#licenses_edit_form input[name=derivatives]:checked").val() == "yes-sa") {
            sLicense += "-sa";
        } else if ($("#licenses_edit_form input[name=derivatives]:checked").val() == "no") {
            sLicense += "-nd";
        }
        $("#licenses_edit_form input[name=license]").val(sLicense + "_4.0");
    }
    
    if (sLicense) {
        var sLicenseName = sLicense.replace("cc_by", "Creative Commons Attribution").replace("-nc", "-NonCommercial").replace("-nd", "-NoDerivatives").replace("-sa", "-ShareAlike");
        $("#selected_license").show();
        $("#selected_license_name").html("<A href=\'https://creativecommons.org/licenses/" + sLicense.replace("cc_", "") + "/4.0/\' target=\'_blank\'>" + sLicenseName + " 4.0 International</A>");
        var sIcons = "<IMG src=\'gfx/cc_icon.png\' alt=\'\' width=\'64\' style=\'margin: 5px;\'>";
        var aTypes = ["by", "nc", "nd", "sa"];
        aTypes.forEach(function (sVal) {
            if (sLicense.indexOf(sVal) > -1) {
                sIcons += "<IMG src=\'gfx/cc_icon_" + sVal + ".png\' alt=\'\' width=\'64\' style=\'margin: 5px;\'>";
            }
        });
        $("#selected_license_icons").html(sIcons);
    } else {
        $("#selected_license").hide();
    }
}


');

$aFields = array(
    'commercial' => array(
        'Do you want to allow others to use your work commercially?<BR><I>Selecting \'yes\' will allow LOVD to seek financial support by sharing your data.</I>',
        'yes' => '<B>Yes.</B> Others can use my work, even for commercial purposes.',
        'no' => '<B>No.</B> Others can not use my work for commercial purposes.',
    ),
    'derivatives' => array(
        'Do you want to allow adaptations of your work to be shared?<BR><I>Selecting \'no\' may prevent your data to be used in studies or shared with genome browsers.</I>',
        'yes' => '<B>Yes.</B> Others can adapt, or build upon my work and share this.',
        'yes-sa' => '<B>Yes.</B> Others can adapt, or build upon my work, as long as they share using the same CC license.',
        'no' => '<B>No.</B> Others may only share my work in unadapted form.',
    ),
);



$sFormEdit = '<FORM id=\'licenses_edit_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'><INPUT type=\'hidden\' name=\'license\' value=\'\'>' .
    'Please fill out the form below to select the license you wish to apply to your data.<BR><BR>' .
    '<B>Please note that in all cases, others using your data must give you attribution.</B><BR><BR>';
foreach ($aFields as $sField => $aItems) {
    $sQuestion = array_shift($aItems);
    $sFormEdit .= '<TABLE style=\'background: #DEEDF7;\' cellspacing=\'4\' width=\'100%\'><TR><TD colspan=\'2\'><B>' . $sQuestion . '</B></TD></TR>';
    foreach ($aItems as $sValue => $sText) {
        $sFormEdit .= '<TR valign=\'top\'><TD><INPUT type=\'radio\' name=\'' . $sField . '\' value=\'' . $sValue . '\'></TD>' .
            '<TD>' . $sText . '</TD></TR>';
    }
    $sFormEdit .= '</TABLE><BR>';
}
$sFormEdit .= '</TABLE><BR></FORM>';
// Add a license result div.
$sFormEdit .= '<DIV id=\'selected_license\' style=\'text-align: center; background: #DEEDF7; border: 1px solid #AED0EA; display: none;\'><H1>Selected license:</H1><BR><H3 id=\'selected_license_name\' style=\'width: 450px; margin: auto;\'></H3><BR><SPAN id=\'selected_license_icons\'></SPAN></DIV>';

// Set JS variables and objects.
print('
var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
var oButtonFormEdit = {"Save settings":function () { $.post("' . CURRENT_PATH . '?edit", $("#licenses_edit_form").serialize()); }};


');





if (ACTION == 'edit' && GET) {
    // Show edit form.
    // We do this in two steps to prevent CSRF.

    $_SESSION['csrf_tokens']['licenses_edit'] = md5(uniqid());
    $sFormEdit = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['licenses_edit'], $sFormEdit);

    // Display the form, and put the right buttons in place.
    print('
    $("#licenses_dialog").html("' . $sFormEdit . '<BR>");

    // Select the right buttons.
    $("#licenses_dialog").dialog({buttons: $.extend({}, oButtonFormEdit, oButtonCancel)});
    
    // Adapt form to trigger the lovd_showLicense() function
    //  whenever the values change.
    $("#licenses_edit_form input:radio").change(function () {
        lovd_showLicense();
    });

    // Fill in preselected settings.');
    if ($sLicense) {
        print('
    $("#licenses_edit_form input[name=commercial][value=' . (strpos($sLicense, '-nc')? 'no' : 'yes') . ']").click();
    $("#licenses_edit_form input[name=derivatives][value=' . (strpos($sLicense, '-nd')? 'no' : (strpos($sLicense, '-sa')? 'yes-sa' : 'yes')) . ']").click();');
    }
    exit;
}





if (ACTION == 'edit' && POST) {
    // Process edit form.
    // We do this in two steps to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['licenses_edit']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    if (empty($_POST['license']) || !isset($_SETT['licenses'][$_POST['license']])) {
        die('alert("Please answer both questions on the form to select the correct license.");');
    }

    // Update!
    if ($sObject == 'individual') {
        if (!$_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET license = ? WHERE id = ?',
            array($_POST['license'], $nID), false)) {
            die('alert("Failed to save settings.\n' . htmlspecialchars($_DB->formatError()) . '");');
        }
        // If we get here, the changes have been saved successfully!
        lovd_writeLog('Event', 'IndividualLicenseEdit', 'Successfully set license to ' . $_POST['license'] . ' for individual #' . $nID);
    } elseif ($sObject == 'user') {
        if (!$_DB->query('UPDATE ' . TABLE_USERS . ' SET default_license = ? WHERE id = ?',
            array($_POST['license'], $nID), false)) {
            die('alert("Failed to save settings.\n' . htmlspecialchars($_DB->formatError()) . '");');
        }
        // If we get here, the changes have been saved successfully!
        lovd_writeLog('Event', 'UserLicenseEdit', 'Successfully set default license to ' . $_POST['license'] . ' for user #' . $nID);
    }

    // Reload the dialog, and put the right buttons in place.
    print('
    $("#licenses_dialog").html("Settings saved successfully!");
    lovd_reloadVE("' . ucfirst($_PE[2]) . '");
    
    // Select the right buttons.
    $("#licenses_dialog").dialog({buttons: oButtonClose}); 
    
    setTimeout(function() { $("#licenses_dialog").dialog("close"); }, 1000);
    ');
    exit;
}
?>
