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

// Fixme; Add to buttonOKValid and buttonOKCouldBeValid: mp5 translation of all input.
// Getting all variables from the URL.
$sVariant     = htmlspecialchars($_REQUEST['var']);
$sFieldName   = htmlspecialchars($_REQUEST['fieldName']);
$sRefSeqInfo  = htmlspecialchars($_REQUEST['refSeqInfo']);
$aTranscripts = explode('|', htmlspecialchars($_REQUEST['transcripts']));
$aActiveGBs   = $_DB->query('SELECT column_suffix, id FROM ' . TABLE_GENOME_BUILDS)->fetchAllCombine();



// If the variant is empty, we want to reset all results of this script.
if (!$sVariant) {
    print('
    // Resetting all values.
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/trans.png"}).show();
    '); // TODO: Remove the mp5 translated variant from the HTML.

    // Returning the mapping for transcript, RNA and protein variants.
    foreach($aTranscripts as $sTranscript) {
        print('        
        var oTranscriptField = $("input").filter(function() {
            return $(this).data("id_ncbi") == "' . $sTranscript . '" 
        });
        oTranscriptField.val("");
        var sBaseOfFieldName = oTranscriptField.attr("name").substring(0, oTranscriptField.attr("name").indexOf("DNA"));
        $(\'#variantForm input[name$="\' + sBaseOfFieldName + "RNA" + \'"]\').val("");
        $(\'#variantForm input[name$="\' + sBaseOfFieldName + "Protein" + \'"]\').val("");
        ');
    }

    // Returning the mapping for genomic variants.
    foreach($aActiveGBs as $sGBSuffix => $sGBID) {
        print('
        var oGenomicVariant = $(\'#variantForm input[name$="VariantOnGenome/DNA' . (!$sGBSuffix? '' : '/' . $sGBSuffix) . '"]\');
        oGenomicVariant.val("");
        ');
    }

    // Closing the script.
    exit();
}





// Create a PHP function to easily update the dialogue.
function update_dialogue($sText, $aButtons = array())
{
    // This function fills the variantCheckDialogue with the given
    //  text, adds the right buttons and sends it to the user.
    print('
    // Updating the contents.
    $("#variantCheckDialogue").html("' . $sText . '");
    ');

    print(($aButtons? '
    // Placing the buttons.
    $("#variantCheckDialogue").dialog({buttons: $.extend({}, ' . implode(', ', $aButtons) . ')});
    ' : '
    // Removing buttons.
    $("#variantCheckDialogue").dialog({buttons: {}});
    '));

    // Sending the contents directly to the user.
    flush();
}


// And a function to easily append to the dialogue.
function append_to_dialogue($sText)
{
    // This function fills the variantCheckDialogue with the given
    //  text, adds the right buttons and sends it to the user.
    print('
    // Updating the contents.
    $("#variantCheckDialogue").append("' . $sText . '");
    ');

    // Sending the contents directly to the user.
    flush();
}





// Preparing the buttons.
$sButtonYes            = 'oButtonYes';
$sButtonNo             = 'oButtonNo';
$sButtonOKValid        = 'oButtonOKValid';
$sButtonOKInvalid      = 'oButtonOKInvalid';
$sButtonOKCouldBeValid = 'oButtonOKCouldBeValid';





// Opening the dialogue.
print('
// Setting up the dialogue.
$("body").append("<DIV id=\'variantCheckDialogue\' title=\'Mapping and validating your variant.\'></DIV>");
$("#variantCheckDialogue").dialog({
    draggable:true,resizable:false,minWidth:600,show:"fade",closeOnEscape:false,hide:"fade",modal:true,
    open: function(event, ui) { $(".ui-dialog-titlebar-close").hide(); }
});

// Notifying user of what is happening.
$("#variantCheckDialogue").html("Performing initial checks...");
');

// Ending the output buffering and sending the dialogue directly to the user.
@ob_end_flush();
flush();

print('
// Preparing the buttons.
var ' . $sButtonYes . ' = {"Yes":function () {
    // The user accepts the given fixed variant.
    // We will fill in this fixed variant, close the dialogue,
    //  and perform a new call to this script by activating
    //  the onChange.
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.val("' . lovd_fixHGVS($sVariant) . '");
    $(this).dialog("close");
    oInput.change();
}};
var ' . $sButtonNo . '  = {"No, I will take a look myself":function () {
    // The user does not accept the given fixed variant.
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png"}).show();
    $(this).dialog("close");
}};
var ' . $sButtonOKValid . '  = {"OK":function () {
    // The variant was mapped and looks just great!
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/check.png"}).show();
    $(this).dialog("close");
}};
var ' . $sButtonOKInvalid . '  = {"OK":function () {
    // The user agrees to change its invalid input manually. 
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png"}).show();
    $(this).dialog("close");
}};
var ' . $sButtonOKCouldBeValid . '  = {"OK":function () {
    // We could not validate this variant, but the problem lies with us.
    //  We will accept this variant and the uncertainty that comes with it.
    var oInput = $(\'input[name$="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/check_orange.png"}).show();
    $(this).dialog("close");
}};
');





// Check whether this variant is supported by LOVD.
$aVariant = lovd_getVariantInfo($sVariant, false);
$bIsSupportedByLOVD = !isset($aVariant['errors']['ENOTSUPPORTED']);

if (!$bIsSupportedByLOVD) {
    // If the variant is not supported by LOVD, we cannot perform an HGVS check nor the mapping.
    // We will notify the user and end the script here.

    update_dialogue(
        'Your variant contains syntax which our HGVS check cannot recognise. ' .
        'Therefore, we cannot validate your variant nor map it to other reference sequences. ' .
        'Please thoroughly validate your variant by hand.',
        array($sButtonOKCouldBeValid));
    exit();
}





// Perform our HGVS check.
if (!lovd_isHGVS($sVariant)) {
    // If the variant is not HGVS, we cannot send the variant to
    //  VariantValidator yet. We will try to see if our fixHGVS
    //  function knows what to do, but if not, we need to exit
    //  this script and wait for the user to return with a variant
    //  which we can interpret.

    // Let the user know that the given variant did not pass our HGVS check.
    $sResponse = 'Your variant (\"' . $sVariant . '\") did not pass our HGVS check.<br><br>';


    // Show the user the warnings and errors we found through getVariantInfo.
    $aVariantIssues = ($aVariant === false? array() : array_merge(array_values($aVariant['errors']), array_values($aVariant['warnings'])));

    if (!empty($aVariantIssues)) {
        $sResponse .= 'We found the following problems:<br>- ';
        $sResponse .= implode('<br> -', $aVariantIssues) . '<br><br>';
    }


    // Show the fixed variant if fixHGVS was successful.
    $sFixedVariant = lovd_fixHGVS($sVariant);

    if ($sFixedVariant !== $sVariant && lovd_isHGVS($sFixedVariant)) {
        // Good, we can propose a fix. If the user agrees with the fix,
        //  we can continue to the mapping.
        update_dialogue($sResponse . 'Did you mean \"' . $sFixedVariant . '\"?<br>',
            array($sButtonYes, $sButtonNo));

        // Our 'Yes' button sets the steps in motion which change the user's
        //  input into the fixed variant, and reactivates the dialogue.
        // It is then started from the top. We can thus exit the script here.
        exit();

    } else {
        // We could not propose a fix. We will end the script.
        update_dialogue($sResponse . 'Please check your variant for errors and try again.<br>',
            array($sButtonOKInvalid));
        exit();
    }
}

// Passed the HGVS check. Inform the user.
update_dialogue('Your variant passed our HGVS syntax check.');





// Check whether VariantValidator supports the syntax of the variant.
$bIsSupportedByVV =
    !(isset($aVariant['warnings']['WNOTSUPPORTED'])
        || isset($aVariant['messages']['IUNCERTAINPOSITIONS'])
        || isset($aVariant['messages']['IPOSTIONRANGE']));

if (!$bIsSupportedByVV) {
    // If syntax was found which VariantValidator does not support, we
    //  cannot send the variant in for mapping. We will notify the
    //  user of this and exit this script.
    update_dialogue('Your variant contains syntax which VariantValidator cannot recognise. ' .
                    'Therefore, we cannot map your variant nor validate the positions.',
                    array($sButtonOKCouldBeValid));
    exit();
}





// Prepare the call to VariantValidator to validate the variant.
update_dialogue('Preparing the mapping...');

// Retrieve the reference sequence from the info given through the URL.
if (strpos($sRefSeqInfo, '-') === false) {
    // The hashtag serves as our little communication tool; it tells
    //  us that the given input was a GB suffix. When no hashtag was
    //  found, we know that the input was the reference sequence of
    //  a transcript.
    $sType = 'VOT';
    $sReferenceSequence = $sRefSeqInfo;

} else {
    // We know we got information on a GB. This is given through
    //  JS in the format of <GB suffix>#<chromosome>.
    $sType = 'VOG';
    list($sCurrentGBSuffix, $sChromosome) = explode('-', $sRefSeqInfo);

    if (!$_SETT['human_builds'][$aActiveGBs[$sCurrentGBSuffix]]['supported_by_VV']) {
        // If the given genome build is not supported by VV, we cannot fully validate
        //  the variants... We will have to accept these variants into the database
        //  anyway, since this issue lies with us. Fixme; Is this true though? Do we want to accept these variants?
        update_dialogue(
            'This genome build is not supported for mapping or validation.' .
            ' Please start the mapping with a transcript ' . (count($aActiveGBs) > 1 ? '' : 'or a different genome build') . '.',
            array($sButtonOKValid));
        exit();
    }

    if (!isset($_SETT['human_builds'][$aActiveGBs[$sCurrentGBSuffix]]['ncbi_sequences'][$sChromosome])) {
        // The combination of chromosome and build is not known by LOVD.
        // Something probably went wrong on the user's end. We will inform
        //  the user and exit the script.
        update_dialogue(
            'An unknown combination of genome build and chromosome was given.' .
            ' This means we cannot perform the mapping. Please try again later.',
            array($sButtonOKInvalid));
        exit();
    }

    $sReferenceSequence = $_SETT['human_builds'][$aActiveGBs[$sCurrentGBSuffix]]['ncbi_sequences'][$sChromosome];
}


// Check if the description itself holds a reference sequence.
// if (lovd_holdsRefSeq($sVariant)) { Fixme; Update line below with this line once the necessary code has been pulled in -> Committed in feat/checkHGVSTool on January 14th 2022; ID 7abf9d70dc3094f5f9cfa0dfc43039c49b4217ff.
if (preg_match('/.*:[a-z]\./', $sVariant)) {
    // The given variant description holds a reference sequence.
    $sRefSeqInDescription = substr($sVariant, 0, strpos($sVariant, ':'));

    if ($sRefSeqInDescription == $sReferenceSequence) {
        // Perfect, no issues found; the user redundantly gave
        //  the reference sequence in the variant description,
        //  but that is no problem at all, since the given
        //  refSeq matches our expectations.
        $sFullVariant = $sVariant;

    } else {
        // The user gave a refSeq within the variant description
        //  input which does not match our expectations. The variant
        //  is then likely to be wrong. We cannot accept it.
        update_dialogue(
            'The reference sequence given in the input description, does not equal the' .
            ' reference sequence matched to the variant automatically by LOVD. Please have' .
            ' another look and perhaps try again from a different input field.',
            array($sButtonOKInvalid));
        exit();
    }

} else {
    // The given variant does not hold a reference sequence.
    $sFullVariant = $sReferenceSequence . ':' . $sVariant;
}





// Call VariantValidator.
append_to_dialogue('<br>' . $sFullVariant . ' is ready.<br><br>Mapping the variant...');

require ROOT_PATH . 'class/variant_validator.php';
$_VV = new LOVD_VV();
$aMappedVariant = (
    $sType == 'VOG'?
    $_VV->verifyGenomic($sFullVariant, array(
        'map_to_transcripts' => true,            // Should we map the variant to transcripts?
        'predict_protein' => true,               // Should we get protein predictions?
        'lift_over' => (count($aActiveGBs) > 1), // Should we get other genomic mappings of this variant?
        'select_transcripts' => $aTranscripts,   // Should we limit our output to only a certain set of transcripts?
    )) :
    $_VV->verifyVariant($sFullVariant, array('select_transcripts' => $aTranscripts))
);





// Check if VariantValidator bumped into any issues.
if (!empty($aMappedVariant['errors'])) {
    // The variant holds a fatal issue. We will exit the script and not
    //  accept this variant into the database.

    update_dialogue(
        'We could not valide nor map your variant because of the following problem(s):<br>- ' .
        implode('<br> -', $aMappedVariant['errors']) . '<br><br>' .
        'Please take another look at your variant and try again.',
        array($sButtonOKInvalid));

    exit();
}

// Check for warnings.
if (!empty($aMappedVariant['warnings'])) {
    // One or more warnings were found. Perhaps the variant was corrected?

    if (isset($aMappedVariant['warnings']['WROLLBACK'])
        || isset($aMappedVariant['warnings']['WCORRECTED'])) {
        // The variant was corrected.
        append_to_dialogue('Your variant was corrected to ' . $aMappedVariant['data']['DNA'] .
            ' to fully match HGVS guidelines.');
        $bImprovedByVV = true;
    }

    if (isset($aMappedVariant['warnings']['WFLAG'])) {
        // This type of warning tells us that VariantValidator had a problem
        //  which is an issue with them, not us nor our user. We can only get
        //  the mapping on all genome builds, not on (other) transcripts.
        // We will notify the user.
        append_to_dialogue('Your variant could not be fully validated due to unknown issues.');
        // Fixme; Either find a fix within VV, or Call Mutalyzer.
    }

// Check whether the mapping was successful.
} elseif (!isset($aMappedVariant['data']['DNA'])
    || empty($aMappedVariant['data']['DNA'])) {
    // Although we did not receive any warnings or errors, the DNA field
    //  is left empty. This means we have no information on the mapping
    //  and there is not much we can do... We will inform the user that
    //  an unknown error occurred and that they should try again later.
    update_dialogue(
        'An unknown error occurred while trying to validate and map your variant.' .
        ' We are sorry for the inconvenience. Please try again later.',
        array($sButtonOKInvalid));

    exit();
}





// When sending in a variant on transcript, VariantValidator only
//  returns the variant as mapped on that one transcript. If we are
//  on a VOT creation form, and there are multiple transcripts open,
//  we want each transcript to get a mapping. To get this, we then
//  need to call VariantValidator a second time using one of the
//  genomic variant as were returned using our first call.
if ($sType == 'VOT' && count($aTranscripts) > 1) {

    $aMappedViaGB = (
        !isset($aMappedVariant['data']['genomic_mappings']['hg38'])? // Yes=We have a genomic reference from our first call; No=We don't have a genomic reference.
            array() :
            $_VV->verifyGenomic($aMappedVariant['data']['genomic_mappings']['hg38'], array(
                'map_to_transcripts' => true, // Should we map the variant to transcripts?
                'predict_protein' => true,    // Should we get protein predictions?
                'lift_over' => true,          // Should we get other genomic mappings of this variant?
                'select_transcripts' => $aTranscripts, // Should we limit our output to only a certain set of transcripts?
            ))
    );

    if (!isset($aMappedViaGB['data']['transcript_mappings'])) {
        // If for any reason no genomic mappings were given, we cannot perform this
        //  extra step, and will thus miss some information. We will inform the user.
        append_to_dialogue('Your variant could not be mapped to all transcripts due to unknown issues.');
    }

    $aMappedVariant['data']['transcript_mappings'] = $aMappedViaGB['data']['transcript_mappings'];

    unset($aMappedViaGB); // We don't need the rest of this information.
}





// Add mapping information to the right fields.

// Returning the mapping for transcript, RNA and protein variants.
foreach($aTranscripts as $sTranscript) {
    $aTranscriptData = ($sTranscript == $sReferenceSequence?
        $aMappedVariant['data'] :
        (isset($aMappedVariant['data']['transcript_mappings'][$sTranscript])?
            $aMappedVariant['data']['transcript_mappings'][$sTranscript] :
            array('DNA' => 'not_mapped', 'RNA' => 'not_mapped', 'protein' => 'not_mapped')
        )
    );
    print('        
    var oTranscriptField = $("input").filter(function() { 
        return $(this).data("id_ncbi") == "' . $sTranscript . '" 
    });
    oTranscriptField.val("' . $aTranscriptData['DNA'] . '");
    var sBaseOfFieldName = oTranscriptField.attr("name").substring(0, oTranscriptField.attr("name").indexOf("DNA"));
    $(\'#variantForm input[name$="\' + sBaseOfFieldName + "RNA" + \'"]\').val("' . $aTranscriptData['RNA'] . '");
    $(\'#variantForm input[name$="\' + sBaseOfFieldName + "Protein" + \'"]\').val("' . $aTranscriptData['protein'] . '");
    ');
}

// Returning the mapping for genomic variants.
foreach($aActiveGBs as $sGBSuffix => $sGBID) {
    if (!$_SETT['human_builds'][$sGBID]['supported_by_VV']) {
        // If a genome build is active which is not supported by VV, we won't have
        //  received mapping information on it. We will send a 'not_mapped' message.
        $sMappedGenomicVariant = 'not_mapped';

    } else {
        // The genome build is supported by VV and we are thus good to go.

        if (isset($sCurrentGBSuffix) && $sGBSuffix == $sCurrentGBSuffix) {
            // The current GB is the GB which is the direct reference of our input variant.
            // When this is the case, our output is already perfectly formatted as a string.
            $sMappedGenomicVariant = $aMappedVariant['data']['DNA'];

        } elseif ($sType == 'VOT') {
            // When VV was called using a variant on transcript, we always
            //  get only one possible mapping. It is thus formatted as a string.
            $sMappedGenomicVariant = $aMappedVariant['data']['genomic_mappings'][$sGBID];

        } else {
            // Our output is formatted as an array.
            $aMappedGenomicVariant = $aMappedVariant['data']['genomic_mappings'][$sGBID];

            if (count($aMappedGenomicVariant) <= 1) {
                // Only one variant was found. Great! We don't have to do anything.
                $sMappedGenomicVariant = $aMappedGenomicVariant[0];

            } else {
                // Multiple possible genomic variants were found. We now need to
                //  concatenate them cleanly. We will do this as follows:
                // NC_1233456.1:g.1del + NC_123456.1:g.2_3del + NC_123456.1:g.4del = NC_123456.1:g.1del^2_3del^4del.
                // We'll also give the user a heads-up.
                append_to_dialogue('There were multiple genomic variant predictions for build ' . $sGBID . '.');

                $sMappedGenomicVariant =
                    preg_replace('/(.*:[a-z]\.).*/', '', $aMappedGenomicVariant[0]) .
                    implode('^',
                        array_map(function ($sFullVariant) {
                            return preg_replace('/.*:[a-z]\./', '', $sFullVariant);
                        }, $aMappedGenomicVariant)
                    );
            }
        }
    }

    print('
        var oGenomicVariant = $(\'#variantForm input[name$="VariantOnGenome/DNA' . (!$sGBSuffix? '' : '/' . $sGBSuffix) . '"]\');
        oGenomicVariant.val("' . $sMappedGenomicVariant . '");
    ');
}





// Send final message to the user.
update_dialogue(
    'Your variant was successfully mapped' . (!isset($bImprovedByVV)? '' : ', improved') .
    ' and validated by VariantValidator. Thank you for your patience!',
    array($sButtonOKValid));
?>
