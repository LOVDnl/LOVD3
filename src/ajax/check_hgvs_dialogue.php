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


// Preparing necessary functions and variables.
// Retrieving the variant and the transcripts and genome builds to map to.
$sVariant     = htmlspecialchars($_REQUEST['var']);
$aTranscripts = explode('|', htmlspecialchars($_REQUEST['transcripts']));
$aActiveGBs   = $_DB->query('SELECT column_suffix, id FROM ' . TABLE_GENOME_BUILDS)->fetchAllCombine();

// Retrieving the name of the input field.
$sFieldName   = htmlspecialchars($_REQUEST['fieldName']);

// Preparing the steps.
$sStepInitialChecks = 'statusChecks';
$sStepMapping       = 'statusMapping';

// Preparing the images.
$sImageNeutral = 'gfx/trans.png';
$sImagePassed  = 'gfx/check.png';
$sImageFailed  = 'gfx/cross.png';
$sImageLoading = 'gfx/lovd_loading.gif';

// Preparing the buttons.
$sButtonYes            = 'oButtonYes';
$sButtonNo             = 'oButtonNo';
$sButtonOKValid        = 'oButtonOKValid';
$sButtonOKInvalid      = 'oButtonOKInvalid';
$sButtonOKCouldBeValid = 'oButtonOKCouldBeValid';

// Preparing the JS for the buttons.
// Fixme; Add to buttonOKValid and buttonOKCouldBeValid: md5 translation of all input.
print('
// Preparing the buttons.
var ' . $sButtonYes . ' = {"Yes":function () {
    // The user accepts the given fixed variant.
    // We will fill in this fixed variant, close the dialogue,
    //  and perform a new call to this script by activating
    //  the onChange.
    var oInput = $(\'input[name="' . $sFieldName . '"]\');
    oInput.val("' . lovd_fixHGVS($sVariant) . '");
    $(this).dialog("close");
    oInput.change();
}};
var ' . $sButtonNo . '  = {"No, I will take a look myself":function () {
    // The user does not accept the given fixed variant.
    var oInput = $(\'input[name="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png", title: "Please check the HGVS syntax of your variant description before sending it into the database."}).show();
    $(this).dialog("close");
}};
var ' . $sButtonOKValid . '  = {"OK":function () {
    // The variant was mapped and looks just great!
    // All steps have already been taken; the only
    //  thing left to do is to close the dialogue.
    $(this).dialog("close");
}};
var ' . $sButtonOKInvalid . '  = {"OK":function () {
    // The user agrees to change their invalid input manually. 
    var oInput = $(\'input[name="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/cross.png", title: "Your variant could not be validated..."}).show();
    $(this).dialog("close");
}};
var ' . $sButtonOKCouldBeValid . '  = {"OK":function () {
    // We could not validate this variant, but the problem
    //  lies with us. We will accept this variant and the
    //  uncertainty that comes with it.
    var oInput = $(\'input[name="' . $sFieldName . '"]\');
    oInput.siblings("img:first").attr({src: "gfx/check_orange.png", title: "Your variant could not be (in)validated..."}).show();
    $(this).dialog("close");
}};
');


// Create a PHP function to easily update the dialogue.
function update_dialogue($sText, $sButtons = '', $bCleanSlate = false)
{
    // This function fills the variantCheckDialogue with the given
    //  text, adds the right buttons and sends it to the user.
    // If $bCleanSlate, it will remove the old text and start over;
    //  if !$bCleanSlate, it will append.
    print(($bCleanSlate ? '
    // Updating the contents.
    $("#variantCheckDialogue").html("' . $sText . '<br>");
    ' : '
    // Appending to the contents.
    $("#variantCheckDialogue").append("' . $sText . '<br>");
    '));

    print(($sButtons ? '
    // Placing the buttons.
    $("#variantCheckDialogue").dialog({buttons: $.extend({}, ' . $sButtons . ')});
    ' : '
    // Removing buttons.
    $("#variantCheckDialogue").dialog({buttons: {}});
    '));

    // Sending the contents directly to the user.
    flush();
}


// Create a PHP function to easily update the images to the left of each step.
function update_images_per_step($nStep, $sImage)
{
    // This function takes a step in the format of an integer, and
    //  replaces the image which was put next to this step by the
    //  given $sImage.
    print('$("#' . $nStep . '").attr({src: "' . $sImage . '"});');
}





// Performing initial checks.
if ($_REQUEST['action'] == 'check') {

    // If the variant is empty, we want to reset all results of this script.
    if (!$sVariant) {
        print('
        // Resetting all values.
        var oInput = $(\'input[name$="' . $sFieldName . '"]\');
        oInput.siblings("img:first").attr({src: "gfx/trans.png"}).show();
        '); // TODO: Remove the md5 translated variant from the HTML.

        // Returning the mapping for transcript, RNA and protein variants.
        foreach ($aTranscripts as $sTranscript) {
            print('        
            var oTranscriptField = $("input").filter(function() {
                return $(this).data("id_ncbi") == "' . $sTranscript . '" 
            });
            oTranscriptField.val("");
            oTranscriptField.prop("disabled", false);
            oTranscriptField.siblings("img:first").attr({src: "gfx/trans.png"}).show();
            var sBaseOfFieldName = oTranscriptField.attr("name").substring(0, oTranscriptField.attr("name").indexOf("DNA"));
            $(\'#variantForm input[name$="\' + sBaseOfFieldName + "RNA" + \'"]\').val("");
            $(\'#variantForm input[name$="\' + sBaseOfFieldName + "Protein" + \'"]\').val("");
            ');
        }

        // Returning the mapping for genomic variants.
        foreach ($aActiveGBs as $sGBSuffix => $sGBID) {
            print('
            var oGenomicVariant = $(\'#variantForm input[name$="VariantOnGenome/DNA' . (!$sGBSuffix ? '' : '/' . $sGBSuffix) . '"]\');
            oGenomicVariant.val("");
            oGenomicVariant.prop("disabled", false);
            oGenomicVariant.siblings("img:first").attr({src: "gfx/trans.png"}).show();
            ');
        }

        // Closing the script.
        exit();
    }

    // Retrieving information on the reference sequence from the URL.
    $sRefSeqInfo  = htmlspecialchars($_REQUEST['refSeqInfo']);

    // Opening the dialogue.
    print('
    // Setting up the dialogue.
    $("body").append("<DIV id=\'variantCheckDialogue\' title=\'Mapping and validating your variant using VariantValidator.\'></DIV>");
    $("#variantCheckDialogue").dialog({
        draggable:true,resizable:false,minWidth:600,show:"fade",closeOnEscape:false,hide:"fade",modal:true,
        open: function(event, ui) { $(".ui-dialog-titlebar-close").hide(); }
    });
    ');

    update_dialogue(
        '<IMG id=\"' . $sStepInitialChecks . '\" src=\"' . $sImageNeutral . '\" width=\"16\" height=\"16\"> Performing initial checks.',
        '',
        true
    );


    // Check whether this variant is supported by LOVD.
    $aVariant = lovd_getVariantInfo($sVariant, false);
    $bIsSupportedByLOVD = !isset($aVariant['errors']['ENOTSUPPORTED']);

    if (!$bIsSupportedByLOVD) {
        // If the variant is not supported by LOVD, we cannot perform an HGVS check nor the mapping.
        // We will notify the user and end the script here.

        update_images_per_step($sStepInitialChecks, $sImageFailed);
        update_dialogue(
            '<br>Your variant contains syntax which our HGVS check cannot recognise. ' .
            'Therefore, we cannot validate your variant nor map it to other reference sequences. ' .
            'Please thoroughly validate your variant by hand.',
            $sButtonOKCouldBeValid);
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
        $sResponse = '<br>Your variant (\"' . $sVariant . '\") did not pass our HGVS check.<br><br>';
        update_images_per_step($sStepInitialChecks, $sImageFailed);


        // Show the user the warnings and errors we found through getVariantInfo.
        $aVariantIssues = ($aVariant === false ? array() : array_merge(array_values($aVariant['errors']), array_values($aVariant['warnings'])));

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
                $sButtonYes . ', ' . $sButtonNo);

            // Our 'Yes' button sets the steps in motion which change the user's
            //  input into the fixed variant, and reactivates the dialogue.
            // It is then started from the top.

        } else {
            // We could not propose a fix.
            update_dialogue($sResponse . 'Please check your variant for errors and try again.<br>',
                $sButtonOKInvalid);
        }
        exit();
    }


    // Check whether VariantValidator supports the syntax of the variant.
    $bIsSupportedByVV =
        !(isset($aVariant['warnings']['WNOTSUPPORTED'])
            || isset($aVariant['messages']['IUNCERTAINPOSITIONS'])
            || isset($aVariant['messages']['IPOSTIONRANGE']));

    if (!$bIsSupportedByVV) {
        // If syntax was found which VariantValidator does not support, we
        //  cannot send the variant in for mapping. We will notify the
        //  user and exit this script.
        update_images_per_step($sStepInitialChecks, $sImageFailed);
        update_dialogue('<br>Your variant contains syntax which VariantValidator cannot recognise. ' .
            'Therefore, we cannot map your variant nor validate the positions.',
            $sButtonOKCouldBeValid);
        exit();
    }


    // Retrieve the reference sequence from the info given through the URL.
    if (strpos($sRefSeqInfo, '-') === false) {
        // The '-' serves as our little communication tool; it tells
        //  us that the given input was a GB suffix. When no '-' was
        //  found, we know that the input was the reference sequence
        //  of a transcript.
        $sType = 'VOT';
        $sReferenceSequence = $sRefSeqInfo;

    } else {
        // We know we got information on a GB. This is given through
        //  JS in the format of <GB suffix>-<chromosome>.
        $sType = 'VOG';
        list($sCurrentGBSuffix, $sChromosome) = explode('-', $sRefSeqInfo);

        if (!$_SETT['human_builds'][$aActiveGBs[$sCurrentGBSuffix]]['supported_by_VV']) {
            // If the given genome build is not supported by VV, we cannot fully validate
            //  the variants... We will have to accept these variants into the database
            //  anyway, since this issue lies with us.
            die('
        $("#variantCheckDialogue").dialog("close");
        var oInput = $(\'input[name="' . $sFieldName . '"]\');
        oInput.siblings("img:first").attr({src: "gfx/check_orange.png", title: "We validated the syntax, but could not validate the positions."}).show();
        ');
        }

        if (!isset($_SETT['human_builds'][$aActiveGBs[$sCurrentGBSuffix]]['ncbi_sequences'][$sChromosome])) {
            // The combination of chromosome and build is not known by LOVD.
            // Something probably went wrong on the user's end. We will inform
            //  the user and exit the script.
            update_images_per_step($sStepInitialChecks, $sImageFailed);
            update_dialogue(
                '<br>An unknown combination of genome build and chromosome was given.' .
                ' This means we cannot perform the mapping.',
                $sButtonOKInvalid);
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
            $sVariant = $sVariant;

        } else {
            // The user gave a refSeq within the variant description
            //  input which does not match our expectations. The variant
            //  is then likely to be wrong. We cannot accept it.
            update_images_per_step($sStepInitialChecks, $sImageFailed);
            update_dialogue(
                '<br>The reference sequence given in the input description, does not equal the' .
                ' reference sequence matched to the variant by LOVD automatically. Please have' .
                ' another look and perhaps try again from a different input field.',
                $sButtonOKInvalid);
            exit();
        }

    } else {
        // The given variant does not hold a reference sequence.
        $sVariant = $sReferenceSequence . ':' . $sVariant;
    }


    // All checks have passed; we are ready for the mapping.
    update_images_per_step($sStepInitialChecks, $sImagePassed);
    update_dialogue('<IMG id=\"' . $sStepMapping . '\" src=\"' . $sImageLoading . '\" width=\"16\" height=\"16\"> Mapping your variant.');

    print('
    $.get("ajax/check_hgvs_dialogue.php?"
            + "action=map"
            + "&var=' . $sVariant . '"
            + "&fieldName=' . $sFieldName . '"
            + "&type=' . $sType . '"
            + "&refSeq=' . $sReferenceSequence . '"
            + "&transcripts=' . implode('|', $aTranscripts) . '")
        .fail(function(){alert("Error while trying to map your variant, please try again later.");})
    ');
}





// Performing the mapping.
if ($_REQUEST['action'] == 'map') {

    // Retrieving necessary information from the URL.
    $sType              = urldecode($_REQUEST['type']);
    $sReferenceSequence = urldecode($_REQUEST['refSeq']);

    // Call VariantValidator.
    require ROOT_PATH . 'class/variant_validator.php';
    $_VV = new LOVD_VV();
    $aMappedVariant = (
    $sType == 'VOG' ?
        $_VV->verifyGenomic($sVariant, array(
            'map_to_transcripts' => empty($aTranscript),      // Should we map the variant to transcripts?
            'predict_protein' => empty($aTranscript),      // Should we get protein predictions?
            'lift_over' => (count($aActiveGBs) > 1), // Should we get other genomic mappings of this variant?
            'select_transcripts' => (empty($aTranscripts) ? array() : $aTranscripts),
        )) :
        $_VV->verifyVariant($sVariant, array('select_transcripts' => $aTranscripts))
    );

    // Check for issues for which the user cannot be blamed.
    if ($aMappedVariant === false
        || in_array(array_keys($aMappedVariant['errors']), array(array('EBUILD'), array('ESYNTAX')))) {
        // If our VV call returned false, or if we found an EBUILD or ESYNTAX
        //  error, this is an issue that lies with us, not the user.
        // We will have to allow these variants into the database.
        update_images_per_step($sStepMapping, $sImageFailed);
        update_dialogue(
            '<br>Something went wrong on our side, which means we could not map nor validate your variant.',
            $sButtonOKCouldBeValid
        );
        exit();
    }


    // Check if VariantValidator bumped into any issues.
    if (!empty($aMappedVariant['errors'])) {
        // The variant holds a fatal issue. We will exit the script and not
        //  accept this variant into the database.

        update_images_per_step($sStepMapping, $sImageFailed);
        update_dialogue(
            '<br>We could not validate nor map your variant because of the following problem(s):<br>- ' .
            implode('<br> -', $aMappedVariant['errors']) . '<br><br>' .
            'Please take another look at your variant and try again.',
            $sButtonOKInvalid);
        exit();
    }

    // Check for warnings.
    if (!empty($aMappedVariant['warnings'])) {
        // One or more warnings were found. Perhaps the variant was corrected?

        if (isset($aMappedVariant['warnings']['WROLLBACK'])
            || isset($aMappedVariant['warnings']['WCORRECTED'])) {
            // The variant was corrected.
            update_dialogue('<br>Your variant was corrected to ' . $aMappedVariant['data']['DNA'] .
                ' to fully match HGVS guidelines.');
            $bImprovedByVV = true;
        }

        if (isset($aMappedVariant['warnings']['WFLAG'])) {
            // This type of warning tells us that VariantValidator had a problem
            //  which is an issue with them, not us nor our user. We can only get
            //  the mapping on all genome builds, not on (other) transcripts.
            // We will notify the user.
            update_dialogue('<br>Your variant could not fully be validated due to unknown issues.');
            // Fixme; Either find a fix within VV, or Call Mutalyzer.
        }

    // Check whether the mapping was successful.
    } elseif (!isset($aMappedVariant['data']['DNA'])
        || empty($aMappedVariant['data']['DNA'])) {
        // Although we did not receive any warnings or errors, the DNA field
        //  is left empty. This means we have no information on the mapping
        //  and there is not much we can do... We will inform the user that
        //  an unknown error occurred and that they should try again later.
        update_images_per_step($sStepMapping, $sImageFailed);
        update_dialogue(
            '<br>An unknown error occurred while trying to validate and map your variant.' .
            ' We are sorry for the inconvenience. Please try again later.',
            $sButtonOKInvalid);
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
        !isset($aMappedVariant['data']['genomic_mappings']['hg38']) ? // Yes=We have a genomic reference from our first call; No=We don't have a genomic reference.
            array() :
            $_VV->verifyGenomic($aMappedVariant['data']['genomic_mappings']['hg38'], array(
                'map_to_transcripts' => true,          // Should we map the variant to transcripts?
                'predict_protein'    => true,          // Should we get protein predictions?
                'lift_over'          => false,         // Should we get other genomic mappings of this variant?
                'select_transcripts' => $aTranscripts, // Should we limit our output to only a certain set of transcripts?
            ))
        );

        if (!isset($aMappedViaGB['data']['transcript_mappings'])) {
            // If for any reason no genomic mappings were given, we cannot perform this
            //  extra step, and will thus miss some information. We will inform the user.
            update_dialogue('<br>Your variant could not be mapped to all transcripts due to unknown issues.');
        }

        $aMappedVariant['data']['transcript_mappings'] = $aMappedViaGB['data']['transcript_mappings'];

        unset($aMappedViaGB); // We don't need the rest of this information.
    }


    // Add mapping information to the right fields.

    // Returning the mapping for transcript, RNA and protein variants.
    foreach ($aTranscripts as $sTranscript) {
        if (!isset($aMappedVariant['data']['transcript_mappings'][$sTranscript])
            && $sTranscript != $sReferenceSequence) {
            // If no mapping was found for this transcript, we skip it.
            continue;
        }
        // Retrieving the info.
        $aTranscriptData = ($sTranscript == $sReferenceSequence ? // Yes=Variant of origin (stored directly in data); No=Other (stored in transcript_mappings)
            $aMappedVariant['data'] : $aMappedVariant['data']['transcript_mappings'][$sTranscript]);

        // Filling in the input fields.
        print('
    // Adding transcript info to the fields.        
    var oTranscriptField = $("input").filter(function() { 
        return $(this).data("id_ncbi") == "' . $sTranscript . '" 
    });
    
    if (!oTranscriptField.prop("disabled")) {
        oTranscriptField.val("' . $aTranscriptData['DNA'] . '");
        oTranscriptField.siblings("img:first").attr({src: "gfx/check.png", title: "Validated"}).show();
        oTranscriptField.prop("disabled", true);
        var sBaseOfFieldName = oTranscriptField.attr("name").substring(0, oTranscriptField.attr("name").indexOf("DNA"));
        $(\'#variantForm input[name$="\' + sBaseOfFieldName + "RNA" + \'"]\').val("' . $aTranscriptData['RNA'] . '");
        $(\'#variantForm input[name$="\' + sBaseOfFieldName + "Protein" + \'"]\').val("' . $aTranscriptData['protein'] . '");
    }');
    }

    // Returning the mapping for genomic variants.
    foreach ($aActiveGBs as $sGBSuffix => $sGBID) {
        if (!$_SETT['human_builds'][$sGBID]['supported_by_VV']
            || !isset($aMappedVariant['data']['genomic_mappings'][$sGBID])) {
            // If a genome build is active which is not supported by VV, we won't have
            //  received mapping information on it. We will skip this variant.
            continue;
        }
        // Retrieving the info.
        if ($sType == 'VOT') {
            // When VV was called using a variant on transcript, we always
            //  get only one possible mapping, which is formatted as a string.
            $sMappedGenomicVariant = $aMappedVariant['data']['genomic_mappings'][$sGBID];

        } elseif (isset($sCurrentGBSuffix) && $sGBSuffix == $sCurrentGBSuffix) {
            // The current build is the build which is the direct reference of
            //  our input variant. When this is the case, our output is already
            //  formatted as a string as well.
            $sMappedGenomicVariant = $aMappedVariant['data']['DNA'];

        } else {
            // Our output is formatted as an array, since multiple variants were possible.
            $aMappedGenomicVariant = $aMappedVariant['data']['genomic_mappings'][$sGBID];

            if (count($aMappedGenomicVariant) <= 1) {
                // Only one variant was found. Great! We can simply take the first element.
                $sMappedGenomicVariant = $aMappedGenomicVariant[0];

            } else {
                // Multiple possible variants were found. We will give the user a heads-up,
                //  and concatenate the variants cleanly as follows:
                // NC_1233456.1:g.1del + NC_123456.1:g.2_3del + NC_123456.1:g.4del =
                //  NC_123456.1:g.1del^2_3del^4del.
                update_dialogue('<br>There were multiple genomic variant predictions for build ' . $sGBID . '.');

                $sMappedGenomicVariant =
                    preg_replace('/(.*:[a-z]\.).*/', '', $aMappedGenomicVariant[0]) .
                    implode('^',
                        array_map(function ($sFullVariant) {
                            return preg_replace('/.*:[a-z]\./', '', $sFullVariant);
                        }, $aMappedGenomicVariant)
                    );
            }
        }

        // Filling in the input field.
        print('
        // Adding genomic info the fields.
        var oGenomicVariant = $(\'#variantForm input[name$="VariantOnGenome/DNA' . (!$sGBSuffix ? '' : '/' . $sGBSuffix) . '"]\');
        oGenomicVariant.val("' . preg_replace('/.*:/', '', $sMappedGenomicVariant) . '");
        oGenomicVariant.siblings("img:first").attr({src: "gfx/check.png", title: "Validated"}).show();
        oGenomicVariant.prop("disabled", true);
        '); // Fixme; Find a cleaner way of cutting off the reference sequence.
    }


    // Because we automatically filled all non-blocked positions,
    //  all open transcript fields have been blocked. We don't
    //  want the user to block the transcript input at this point
    //  so we disable the 'Ignore this transcript' option.
    print('
    // Disabling the "ignore this transcript" option.
    var oIgnoreOption = $(\'input[name^="ignore_"]\');
    oIgnoreOption.parent().html("");
    ');


    // Send final message to the user.
    update_images_per_step($sStepMapping, $sImagePassed);
    update_dialogue(
        '<br>Your variant was successfully mapped' . (!isset($bImprovedByVV) ? '' : ', improved') .
        ' and validated by VariantValidator. Thank you for your patience!',
        $sButtonOKValid);
}
?>
