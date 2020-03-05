<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-04
 * Modified    : 2020-03-05
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
    'variants' => array(),
);





function lovd_showCurationDialog ($aJob)
{
    // Receives a job description, shows the dialog, and calls the process.

    $sDialog = 'Checking and publishing entries, please wait...<BR><BR><TABLE>';
    foreach ($aJob['objects'] as $sObjectType => $aObjects) {
        $sDialog .= '<TR><TD valign=top rowspan=' . count($aObjects) . '><B>' . $sObjectType . '</B></TD>';
        foreach ($aObjects as $nKey => $nObjectID) {
            $sDialog .= (!$nKey? '' : '<TR>') . '<TD>#' . $nObjectID . '</TD><TD id=' . $sObjectType . '_' . $nObjectID . '_status></TD></TR>';
        }
    }
    $sDialog .= '</TABLE>';

    print('
    $("#curate_set_dialog").html("' . $sDialog . '<BR>");

    // Select the right buttons.
    $("#curate_set_dialog").dialog({buttons: $.extend({}, oButtonClose)});
    ');

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
    $.get("' . CURRENT_PATH . '?process&workid=' . $nWorkID . '").fail(function(){alert("Request failed. Please try again.");});
    
    ');
    exit;
}





if (ACTION == 'fromVL' && GET) {
    // URL: /ajax/curate_set.php?fromVL&vlid=VOG
    // Fetch object types and object IDs, and call the curation process.

    if (!isset($_SESSION['viewlists'][$_GET['vlid']])) {
        die('alert("Data listing not found. Please try to reload the page and try again.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['options']['curate_set'])) {
        die('alert("Data listing does not allow curation of a set.");');
    } elseif (empty($_SESSION['viewlists'][$_GET['vlid']]['checked'])) {
        die('alert("No entries selected yet to curate.");');
    }

    // Determine type.
    $sObjectType = '';
    if (!empty($_SESSION['viewlists'][$_GET['vlid']]['row_link'])) {
        $sObjectType = substr($_SESSION['viewlists'][$_GET['vlid']]['row_link'], 0, strpos($_SESSION['viewlists'][$_GET['vlid']]['row_link'], '/'));
    }
    if (!isset($aObjectTypes[$sObjectType])) {
        // FIXME: Try the ViewListID?
        die('alert("Did not recognize object type. This may be a bug in LOVD; please report.");');
    }

    $aJob = array(
        'objects' => array(
            $sObjectType => $_SESSION['viewlists'][$_GET['vlid']]['checked'],
        ),
        'post_action' => array(
            'reload_VL' => $_GET['vlid'],
        ),
    );

    // Open dialog, and list the data types.
    lovd_showCurationDialog($aJob);
    exit;
}
?>
