<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-10-01
 * Modified    : 2024-05-07
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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
if (!POST || PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('check', 'confirm'))) {
    // Note that we require POST to prevent search engines from using this script.
    die('alert("Error while sending data.");');
}

// Let's download the variant's data.
$nID = sprintf('%0' . $_SETT['objectid_length']['variants'] . 'd', $_PE[2]);

// Currently, we only submit to MobiDetails when we have a transcript.
// Get VOG description and VOT description on the most used transcript.
// We have to take the status into account, so that we won't disclose
//  information when people try random IDs!
// lovd_isAuthorized() can produce false, 0 or 1. Accept 0 or 1.
$bIsAuthorized = (lovd_isAuthorized('variant', $nID, false) !== false);
list($sVOG, $nHGNCID) =
    $_DB->q('
        SELECT CONCAT(c.`' . $_CONF['refseq_build'] . '_id_ncbi`, ":", vog.`VariantOnGenome/DNA`) AS VOG_DNA,
            g.id_hgnc AS HGNC
        FROM ' . TABLE_VARIANTS . ' AS vog
          INNER JOIN ' . TABLE_CHROMOSOMES . ' AS c ON (vog.chromosome = c.name)
          INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id)
          INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
          INNER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id)
          INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot_count ON (t.id = vot_count.transcriptid)
        WHERE vog.id = ? AND (? = 1 OR vog.statusid >= ?)
        GROUP BY vog.id, vot.transcriptid
        ORDER BY (vot.`VariantOnTranscript/DNA` REGEXP "[*+-]") ASC,
        LEAST(
          IF(vot.position_c_start < 0, -vot.position_c_start,
            IF(vot.position_c_start > t.position_c_cds_end, vot.position_c_start - t.position_c_cds_end, 0)),
          IF(vot.position_c_end < 0, -vot.position_c_end,
            IF(vot.position_c_end > t.position_c_cds_end, vot.position_c_end - t.position_c_cds_end, 0))) ASC,
        LEAST(
          IFNULL(ABS(vot.position_c_start_intron), 0),
          IFNULL(ABS(vot.position_c_end_intron), 0)) ASC,
        COUNT(vot_count.id) DESC, t.id ASC',
        array($nID, $bIsAuthorized, STATUS_MARKED))->fetchRow();
if (!$sVOG) {
    // Variant doesn't exist, isn't public, or has no VOT.
    die('alert("Variant not found, you can\'t view this variant, or variant has no mapping to a transcript.");');
}

// If we get there, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#mobidetails_dialog").length) {
    $("body").append("<DIV id=\'mobidetails_dialog\' title=\'MobiDetails\'></DIV>");
}
if (!$("#mobidetails_dialog").hasClass("ui-dialog-content") || !$("#mobidetails_dialog").dialog("isOpen")) {
    $("#mobidetails_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}



');

$sFormConfirmation = '<FORM id=\'mobidetails_confirm_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'><A href=\'https://mobidetails.iurc.montp.inserm.fr/MD\' target=\'_blank\'>MobiDetails</A> is an annotation platform dedicated to the interpretation of DNA variants. MobiDetails has not seen this variant before and still needs to generate the annotation. This may take a while. Confirm you want this variant annotated by MobiDetails by clicking the button below.<BR><BR></FORM>';
$sFormConfirmationNoKey = '<A href=\'https://mobidetails.iurc.montp.inserm.fr/MD\' target=\'_blank\'>MobiDetails</A> is an annotation platform dedicated to the interpretation of DNA variants. MobiDetails has not seen this variant before and still needs to generate the annotation. However, this LOVD instance doesn\'t have an MobiDetails API key configured yet, so it can not send the variant to MobiDetails. Please contact the Curator and ask to have an MobiDetails API key registered in the LOVD System Settings.<BR><BR>';

// Set JS variables and objects.
print('
var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
var oButtonFormConfirm = {"Confirm annotation request":function () { $.post("' . CURRENT_PATH . '?confirm", $("#mobidetails_confirm_form").serialize()); $("#mobidetails_dialog").html("Please wait...<BR><IMG src=\'gfx/ajax_loading.gif\' alt=\'Please wait...\' width=\'100\' height=\'100\'>"); $("#mobidetails_dialog").dialog({buttons: $.extend({}, oButtonCancel)}); }};


');





if (ACTION == 'check') {
    // Check if variant is already known, and if not, ask the user for confirmation.
    // We do this in two steps to prevent CSRF.

    $_SESSION['csrf_tokens']['mobidetails_confirm'] = md5(uniqid());
    $sFormConfirmation = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['mobidetails_confirm'], $sFormConfirmation);

    print('
    $("#mobidetails_dialog").html("<IMG src=\'gfx/ajax_loading.gif\' alt=\'Please wait...\' width=\'100\' height=\'100\'>");
    ');
    ob_end_flush();
    flush();

    // Now check with MobiDetails.
    $aJSON = false;
    $aJSONResponse = lovd_php_file('https://mobidetails.iurc.montp.inserm.fr/MD/api/variant/exists/' . rawurlencode($sVOG));
    if ($aJSONResponse !== false) {
        $aJSON = json_decode(implode($aJSONResponse), true);
    }

    if (!empty($aJSON['mobidetails_id']) && !empty($aJSON['url'])) {
        // MD already knows this variant, so open a new window to it.
        print('
        // Close dialog.
        $("#mobidetails_dialog").dialog("close");
        // Open window.
        lovd_openWindow("' . $aJSON['url'] . '", "_blank");
        ');
        exit;
    }

    // If we're here, the variant doesn't exist yet.
    if ($_CONF['md_apikey']) {
        // Display the form, and put the right buttons in place.
        print('
        $("#mobidetails_dialog").html("' . $sFormConfirmation . '<BR>");

        // Select the right buttons.
        $("#mobidetails_dialog").dialog({buttons: $.extend({}, oButtonFormConfirm, oButtonCancel)});
        ');
    } else {
        // Ask the user to contact the LOVD team.
        print('
        $("#mobidetails_dialog").html("' . $sFormConfirmationNoKey . '<BR>");

        // Select the right buttons.
        $("#mobidetails_dialog").dialog({buttons: $.extend({}, oButtonClose)});
        ');
    }
    exit;
}





if (ACTION == 'confirm' && POST) {
    // Process confirmation form.
    // We do this in two steps to prevent CSRF.

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['mobidetails_confirm']) {
        die('alert("Error while sending data, possible security risk. Try reloading the page, and loading the form again.");');
    }

    // Send variant to MobiDetails. This can take a while.
    $aJSON = false;
    $aJSONResponse = lovd_php_file(
        'https://mobidetails.iurc.montp.inserm.fr/MD/api/variant/create_g',
        false,
        'caller=cli&variant_ghgvs=' . rawurlencode($sVOG) . '&gene_hgnc=' . $nHGNCID . '&api_key=' . $_CONF['md_apikey'],
        array(
            'Accept: application/json',
        ));
    if ($aJSONResponse !== false) {
        $aJSON = json_decode(implode($aJSONResponse), true);
    }

    if (!empty($aJSON['mobidetails_id']) && !empty($aJSON['url'])) {
        // Success! Open a new window to it.
        print('
        // Close dialog.
        $("#mobidetails_dialog").dialog("close");
        // Open window.
        lovd_openWindow("' . $aJSON['url'] . '", "_blank");
        ');
        exit;
    }

    // If we're here, something went wrong.
    // Display the error, with just a close button.
    print('
    $("#mobidetails_dialog").html("Error while requesting MobiDetails to annotate this variant.<BR>' .
        implode(array_map(function ($sKey, $sVal) {
            return $sKey . ': ' . $sVal . '<BR>';
        }, array_keys($aJSON), array_values($aJSON))) . '");
    
    // Select the right buttons.
    $("#mobidetails_dialog").dialog({buttons: oButtonClose}); 
    ');
    exit;
}
?>
