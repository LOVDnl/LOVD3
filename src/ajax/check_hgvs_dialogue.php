<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-09-22
 * Modified    : 2021-02-25
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
require ROOT_PATH . 'inc-lib-variants.php';
header('Content-type: text/javascript; charset=UTF-8');

// TODO: All spots where $sTranscriptID is set to false -> check.
// TODO: Maak call naar ander script dat protein/RNA changes toevoegt.
// URL: /ajax/check_hgvs_dialogue.php/variant=g.1del


$sVariant = $_REQUEST['var'];
$sName = $_REQUEST['name'];


// If the variant is empty, we want to set the HGVS check to neutral.
if (!$_REQUEST['var']) {
    exit('
    var oInput = $(\'input[name$="' . $sName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/trans.png"}).show();
    ');
}



// If the variant is HGVS, we set the HGVS check to a 'check'.
if (lovd_getVariantInfo($sVariant, false, true)) {
    exit('
    var oInput = $(\'input[name$="' . $sName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/check.png"}).show();
    ');
}



// Retrieving information and fixes on the variant.
$aVariant = lovd_getVariantInfo($sVariant, false);
// To use after updating getVariantInfo:
// $aVariantIssues = ($aVariant === false? array() : array_merge($aVariant['errors'], $aVariant['warnings']));
$aVariant = ($aVariant === false? array() : $aVariant['warnings']);
$sFixedVariant = lovd_fixHGVS($sVariant);



// Preparing the buttons.
print('
// Preparing the buttons.
var oButtonYes = {"Yes":function () {
    var oInput = $(\'input[name$="' . $sName . '"]\');
    oInput.val("' . $sFixedVariant . '");
    oInput.siblings("img:first").attr({src: "gfx/check.png"}).show();
    $(this).dialog("close");
}};
var oButtonNo  = {"No, I will take a look myself":function () {
    var oInput = $(\'input[name$="' . $sName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png"}).show();
    $(this).dialog("close");
}};
var oButtonOK  = {"OK":function () { 
    var oInput = $(\'input[name$="' . $sName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png"}).show();
    $(this).dialog("close");
}};
');



// Preparing the dialogue's contents and buttons.
$sResponse = 'Your variant (\"' . $sVariant . '\") did not pass our HGVS check.<br><br>';

if (!empty($aVariantIssues)) {
    $sResponse .= 'We found the following problems:<br>- ';
    $sResponse .= implode('<br> -', $aVariantIssues) . '<br><br>';
}

if ($sFixedVariant !== $sVariant
    && lovd_getVariantInfo($sFixedVariant, false, true)) {
    $sResponse .= 'Did you mean \"' . $sFixedVariant . '\"?<br>';
    $sButtons = 'oButtonYes, oButtonNo';

} else {
    $sResponse .= 'Please check your variant for errors and try again.<br>';
    $sButtons = 'oButtonOK';
}




// Printing the dialogue.
print('
// Setting up the dialogue.
$("body").append("<DIV id=\'variantCheckDialogue\' title=\'Failed the HGVS check.\'></DIV>");
$("#variantCheckDialogue").dialog({
    draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:false,hide:"fade",modal:true,
    open: function(event, ui) { $(".ui-dialog-titlebar-close").hide(); }
});

// Placing the contents.
$("#variantCheckDialogue").html("' . $sResponse . '");

// Placing the buttons.
$("#variantCheckDialogue").dialog({buttons: $.extend({}, ' . $sButtons . ')});
');

?>
