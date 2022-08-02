<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-06
 * Modified    : 2022-08-02
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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
// Stop the session from sending any cache or no-cache headers. Alternative: ini_set() session.cache_limiter.
session_cache_limiter('public');
require ROOT_PATH . 'inc-init.php';
// HGVS syntax check result expires in a day.
header('Expires: ' . date('r', time() + (24 * 60 * 60)));

session_write_close();

if (!empty($_GET['variant'])) {
    // HGVS check from the submission form, previously done using Mutalyzer and coded in 2011.

    if (!preg_match('/^(c:[cn]|g:[mg])\./', $_GET['variant'])) {
        die(AJAX_DATA_ERROR);
    }

    // Take the c. or g. off.
    $_GET['variant'] = substr($_GET['variant'], 2);

    // Requires at least LEVEL_SUBMITTER.
    if (!$_AUTH) {
        die(AJAX_NO_AUTH);
    }

    if (lovd_getVariantInfo($_GET['variant'], false, true)) {
        // Variant is HGVS-compliant.
        die(AJAX_TRUE);
    } else {
        die(AJAX_FALSE);
    }
}





if (empty($_REQUEST['var'])) {
    die(AJAX_DATA_ERROR);
}

// HGVS check from the special HGVS syntax validation form.

require ROOT_PATH . 'inc-lib-variants.php';
if (!empty($_REQUEST['callVV']) && $_REQUEST['callVV'] == 'true') {
    require ROOT_PATH . 'class/variant_validator.php';
    $_VV = new LOVD_VV();
    $bVV = true;
} else {
    $bVV = false;
}

header('Content-type: text/javascript; charset=UTF-8');

// Reset the form (check/cross sign, validation output).
print('
$("#checkResult").attr("src", "gfx/trans.png");
$("#response").html("");');





// Handling both single submissions and list submissions using the same code.
// This removes duplication, or perhaps worse, differences in implementation.
$bList = (!empty($_REQUEST['method']) && $_REQUEST['method'] == 'list');
// Put the variants in an array, assigning them as keys.
$aVariants = array_fill_keys(
    // This is not a normal form; JS combines the values without adding a \r.
    array_map('trim', explode("\n", $_REQUEST['var'])),
    array()
);

foreach ($aVariants as $sVariant => $aVariant) {
    if (!trim($sVariant)) {
        unset($aVariants[$sVariant]);
        continue;
    }
    $sVariant = trim($sVariant);

    $aVariant['fixed_variant'] = '';
    $aVariant['fixed_variant_is_hgvs'] = false;
    $aVariant['variant_info'] = lovd_getVariantInfo($sVariant, false);
    if ($aVariant['variant_info']) {
        $aVariant['variant_info']['errors'] = array_map('htmlspecialchars', $aVariant['variant_info']['errors']);
        $aVariant['variant_info']['warnings'] = array_map('htmlspecialchars', $aVariant['variant_info']['warnings']);
    }

    $aVariant['has_refseq'] = lovd_variantHasRefSeq($sVariant);
    $aVariant['is_hgvs'] = (
        is_array($aVariant['variant_info'])
        &&
        empty($aVariant['variant_info']['errors'])
        &&
        (
            empty($aVariant['variant_info']['warnings'])
            ||
            array_keys($aVariant['variant_info']['warnings']) == array('WNOTSUPPORTED')
        )
    );
    // But compensate for ENOTSUPPORTED.
    if (!$aVariant['is_hgvs']
        && isset($aVariant['variant_info']['errors']['ENOTSUPPORTED'])
        && count($aVariant['variant_info']['errors']) == 1) {
        // We don't actually know if this is HGVS or not.
        $aVariant['is_hgvs'] = null;
        $aVariant['fixed_variant'] = $sVariant;
        $aVariant['fixed_variant_is_hgvs'] = null;
    }
    if ($aVariant['is_hgvs'] === false) {
        $aVariant['fixed_variant'] = lovd_fixHGVS($sVariant);
        $aVariant['fixed_variant_is_hgvs'] = lovd_getVariantInfo($aVariant['fixed_variant'], false, true);
        if (!$aVariant['variant_info']) {
            $aVariant['variant_info'] = array(
                'errors' => array('This entry is not recognized as a variant.'),
                'warnings' => array(),
            );
        }
        if ($aVariant['fixed_variant'] != $sVariant) {
            if ($aVariant['fixed_variant_is_hgvs']) {
                $aVariant['variant_info']['warnings'][] = 'Did you mean <A href=\"#\" onclick=\"$(\'#variant\').val(\'' . $aVariant['fixed_variant'] . '\'); $(\'#checkButton\').click(); return false;\">' . $aVariant['fixed_variant'] . '</A>?';
            } else {
                $aVariant['variant_info']['warnings'][] = 'We could not automatically correct your variant description, but this suggestion may be an improvement: ' . htmlspecialchars($aVariant['fixed_variant']) . '.<BR>';
            }
        }
    }

    $aVariant['VV'] = '';
    if ($bVV) {
        if (!empty($aVariant['variant_info'])
            && (!empty($aVariant['variant_info']['errors']['ENOTSUPPORTED'])
                || !empty($aVariant['variant_info']['warnings']['WNOTSUPPORTED']))) {
            $aVariant['VV'] = 'This variant description is not currently supported by VariantValidator.';
        } elseif (!$aVariant['is_hgvs']) {
            $aVariant['VV'] = 'Please first correct the variant description to run VariantValidator.';
        } elseif (!$aVariant['has_refseq']) {
            $aVariant['VV'] = 'Please provide a reference sequence to run VariantValidator.';

        } else {
            // Call VariantValidator. Use the outcome of lovd_getVariantInfo()
            //  to determine whether this is a genomic variant or not.
            $aVV = (!isset($aVariant['variant_info']['position_start_intron'])?
                    $_VV->verifyGenomic($sVariant) :
                    $_VV->verifyVariant($sVariant));

            if ($aVV === false) {
                $aVariant['VV'] = 'An internal error within VariantValidator occurred when trying to validate your variant.';
            } elseif (!empty($aVV['data']['DNA']) && $sVariant != $aVV['data']['DNA']) {
                // We don't check for WCORRECTED here, because the VV library accepts some changes
                //  without setting WCORRECTED. We want to show every difference.
                // This here may actually create WCORRECTED.
                $aVV['warnings']['WCORRECTED'] = 'The variant description was automatically corrected to <B>' . $aVV['data']['DNA'] . '</B>.';
                unset($aVV['warnings']['WROLLFORWARD']); // In case it exists.
                $aVariant['fixed_variant'] = $aVV['data']['DNA'];
                $aVariant['fixed_variant_is_hgvs'] = true;
            }

            if (!$aVV['errors'] && !$aVV['warnings']) {
                $aVariant['VV'] = 'The variant description passed the validation by VariantValidator.';
            } else {
                $aVariant['VV'] = 'VariantValidator encountered one or more issues:<BR>' .
                    '- ' . implode('<BR>- ', array_merge($aVV['errors'], $aVV['warnings']));
            }
        }
    }
    $aVariants[$sVariant] = $aVariant;
}





if ($_REQUEST['method'] == 'single') {
    // The form for one single variant was used.
    $sVariant = current(array_keys($aVariants));

    // First check to see if the variant is HGVS.
    $bIsHGVS = $aVariants[$sVariant]['is_hgvs'];
    $aVariantInfo = $aVariants[$sVariant]['variant_info'];

    $sResponse =
        '<B>' . htmlspecialchars($sVariant) . ' ' .
        ($bIsHGVS === null? 'contains syntax currently not supported by this service.' :
            ($bIsHGVS? 'passed' : 'did not pass') . ' our syntax check.') .
        '</B><BR>';

    // Warn the user if a reference sequence is missing.
    if (!$aVariants[$sVariant]['has_refseq'] && !$bVV) {
        $aVariantInfo['warnings'][] =
            'Please note that your variant description is missing a reference sequence. ' .
            'Although this is not necessary for our syntax check, a variant description does ' .
            'need a reference sequence to be fully informative and HGVS compliant.';
    }

    $aMessages = array_merge($aVariantInfo['errors'], $aVariantInfo['warnings']);
    if ($bVV) {
        $aMessages[] = $aVariants[$sVariant]['VV'];
    }
    if ($aMessages) {
        if (count($aMessages) == 1) {
            $sResponse .= current($aMessages) . '<BR><BR>';
        } else {
            $sResponse .= '<UL style=\"margin: 0px;\"><LI>' . implode('</LI><LI>', $aMessages) . '</LI></UL>';
        }
    }

    // Show whether the variant was correct through a check or a cross.
    print('
$("#checkResult").attr("src", "gfx/' . ($bIsHGVS === null? 'lovd_form_question' : ($bIsHGVS? 'check' : 'cross')) . '.png");');





} elseif ($_REQUEST['method'] == 'list') {
    // The form for multiple variants was used.
    $bAllIsHGVS = true;
    $bAllHoldRefSeqs = true;

    $sTable = '<TABLE id=\"responseTable\" border=\"0\" cellpadding=\"10\" cellspacing=\"1\" class=\"data\">' .
        '<TR>' .
           '<TH style=\"background : #90E090;\">Variant</TH>' .
           '<TH style=\"background : #90E090;\">Valid&nbsp;syntax?</TH>' .
           '<TH style=\"background : #90E090;\">Fixed&nbsp;variant</TH>' .
           '<TH style=\"background : #90E090;\">Warnings and errors</TH>' .
           (!$bVV? '' :
          '<TH style=\"background : #90E090;\">Result of VariantValidator</TH>') .
        '</TR>';

    foreach ($aVariants as $sVariant => $aVariant) {
        if (true) {
            // Storing info on whether we find any variants which are missing
            //  reference sequences.
            $bAllHoldRefSeqs &= $aVariant['has_refseq'];
            $bIsHGVS = $aVariant['is_hgvs'];
            // Color = red if the variant could not be improved, green
            // if it was HGVS and orange if it was fixed.
            $sFixedVariant = $aVariant['fixed_variant'];
            $bFixedIsHGVS = $aVariant['fixed_variant_is_hgvs'];
            $sColor = ($bIsHGVS? 'green' :
                ($bIsHGVS === null || $bFixedIsHGVS? 'orange' : 'red'));

            $sTable .= '<TR valign=\"top\" class=\"col' . ucfirst($sColor) .'\">' .
                '<TD>' . htmlspecialchars($sVariant) . '</TD>' .
                '<TD><IMG src=\"gfx/' .
                    ($bIsHGVS? 'mark_1.png\" alt=\"Valid syntax' :
                        ($bIsHGVS === null? 'lovd_form_question.png\" alt=\"Unsupported syntax' :
                            'mark_0.png\" alt=\"Invalid syntax')) . '\"></TD>';

            if ($bIsHGVS) {
                $sTable .= '<TD></TD><TD></TD>';

                if ($bVV) {
                    $sTable .= '<TD>' . $aVariant['VV'] . '</TD>';
                }

                $sTable .= '</TR>';


            } else {
                // The variant is not HGVS.
                $bAllIsHGVS = false;
                $aVariantInfo = $aVariant['variant_info'];

                $sTable .= '<TD>' . htmlspecialchars((!$bFixedIsHGVS? $sVariant : $sFixedVariant)) . '</TD>';

                if (empty($aVariantInfo['warnings']) && empty($aVariantInfo['errors'])
                    && $sFixedVariant != $sVariant && $bFixedIsHGVS) {
                    $sTable .= '<TD>The variant description has been corrected.</TD>';

                } else {
                    $sTable .= '<TD>' .
                        (empty($aVariantInfo['errors'])? '' :
                            '<B>Errors: - </B>' . implode(' - ', array_values($aVariantInfo['errors']))) .
                        (empty($aVariantInfo['warnings']) || empty($aVariantInfo['errors'])? '' : '<BR>') .
                        (empty($aVariantInfo['warnings'])? '' :
                            '<B>Warnings: - </B>' . implode(' - ', array_values($aVariantInfo['warnings']))) .
                        '</TD>';
                }

                // Call VariantValidator.
                if ($bVV) {
                    $sTable .= '<TD>' . $aVariant['VV'] . '</TD>';
                }
            }
            $sTable .= '</TR>';
        }
    }

    $sTable .= '</TABLE>';


    // Create response.
    $sResponse = ($bAllIsHGVS? 'All of the variants passed our syntax check!' :
                               'Some of the variants did not pass our syntax check...') .

                   ($bAllHoldRefSeqs || $bVV? '' : '<BR><BR><I>' .
                        'Please note that at least one of your variants is missing a reference sequence.<BR>' .
                        'Although this is not necessary for our syntax check, a variant description does ' .
                        'need a reference to be fully informative and HGVS compliant.</I><BR>') .

                  '<BR><BR>' .
                   $sTable .
                  '<BR>' .
                  '<BUTTON onclick=\"downloadResponse();\">Download result</BUTTON>';
}




// Print the response.
print('
$("#response").html("' . $sResponse . '");');
?>
