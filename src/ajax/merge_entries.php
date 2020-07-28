<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-07-28
 * Modified    : 2020-07-28
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
if (!ACTION || !in_array(ACTION, array('fromVL'))) {
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
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};


');

// Allowed types.
$aObjectTypes = array(
    'individuals',
);





if (ACTION == 'fromVL' && GET && !empty($_GET['vlid'])) {
    // URL: /ajax/merge_entries.php?fromVL&vlid=Individuals
    // Fetch object IDs, and call the curation process.

    if (!isset($_SESSION['viewlists'][$_GET['vlid']])) {
        die('$("#merge_set_dialog").html("Data listing not found. Please try to reload the page and try again.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['options']['merge_set'])) {
        die('$("#merge_set_dialog").html("Data listing does not allow curation of a set.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['checked'])) {
        die('$("#merge_set_dialog").html("No entries selected yet to curate.");');
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
            'go_to' => lovd_getInstallURL() . $sObjectType . '/' . $aValues[0],
        ),
    );

    // Open dialog, and list the data types.
    lovd_showMergeDialog($aJob);
    exit;
}
?>
