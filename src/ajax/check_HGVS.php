<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-06
 * Modified    : 2022-08-30
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
        && count($aVariant['variant_info']['errors']) == 1
        && empty($aVariant['variant_info']['warnings'])) {
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
                'errors' => array('Failed to recognize a variant description in your input.'),
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
            } elseif (!empty($aVV['data']['DNA'])) {
                // Our VV library removed the refseq, put it back.
                $aVV['data']['DNA'] = lovd_getVariantRefSeq($sVariant) . ':' . $aVV['data']['DNA'];
                if ($sVariant != $aVV['data']['DNA']) {
                    // We don't check for WCORRECTED here, because the VV library accepts some changes
                    //  without setting WCORRECTED. We want to show every difference.
                    // This here may actually create WCORRECTED.
                    $aVV['warnings']['WCORRECTED'] = 'The variant description was automatically corrected to <B>' . $aVV['data']['DNA'] . '</B>.';
                    unset($aVV['warnings']['WROLLFORWARD']); // In case it exists.
                    $aVariant['fixed_variant'] = $aVV['data']['DNA'];
                    $aVariant['fixed_variant_is_hgvs'] = true;
                }
            }

            if (!$aVV['errors'] && !$aVV['warnings']) {
                $aVariant['VV'] = 'The variant description passed the validation by VariantValidator.';
            } else {
                $aVariant['VV'] = 'VariantValidator encountered one or more issues:<BR>' .
                    '- ' . implode('<BR>- ', array_merge($aVV['errors'], $aVV['warnings']));
            }
        }

    } elseif (!$aVariant['has_refseq']) {
        // No VV requested, but no refseq. Better still inform the user.
        // We'd throw a message if we'd have the option.
        $aVariant['variant_info']['warnings']['IREFSEQMISSING'] =
            'Please note that your variant description is missing a reference sequence. ' .
            'Although this is not necessary for our syntax check, a variant description does ' .
            'need a reference sequence to be fully informative and HGVS-compliant.';
    }

    // The variant's status color.
    // Green if it's HGVS and there's no improvement from VV.
    // Orange if it's ENOTSUPPORTED, or if we have a fix that's HGVS.
    // Red, otherwise. We don't get the variant at all, or we couldn't find an HGVS-compliant fix.
    $aVariant['color'] =
        ($aVariant['is_hgvs'] && !$aVariant['fixed_variant']? 'green' :
            ($aVariant['is_hgvs'] === null || $aVariant['fixed_variant_is_hgvs']? 'orange' :
                'red'));

    $aVariants[$sVariant] = $aVariant;
}





if ($_REQUEST['method'] == 'single') {
    // The form for one single variant was used.
    $sVariant = current(array_keys($aVariants));
    $aVariant = $aVariants[$sVariant];

    // First check to see if the variant is HGVS.
    $sResponse =
        '<B>' . htmlspecialchars($sVariant) . ' ' .
        ($aVariant['is_hgvs'] === null? 'contains syntax currently not supported by this service.' :
            ($aVariant['is_hgvs']? 'passed' : 'did not pass') . ' our syntax check.') .
        '</B><BR>';

    $aMessages = array_merge($aVariant['variant_info']['errors'], $aVariant['variant_info']['warnings']);
    if ($bVV) {
        $aMessages[] = $aVariant['VV'];
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
$("#checkResult").attr("src", "gfx/' . ($aVariant['is_hgvs'] === null? 'lovd_form_question' : ($aVariant['is_hgvs']? 'check' : 'cross')) . '.png");');





} elseif ($_REQUEST['method'] == 'list') {
    // The form for multiple variants was used.
    $bAllIsHGVS = true;

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
            $bAllIsHGVS &= $aVariant['is_hgvs'];

            $sTable .=
                '<TR valign=\"top\" class=\"col' . ucfirst($aVariant['color']) .'\">' .
                    '<TD>' . htmlspecialchars($sVariant) . '</TD>' .
                    '<TD><IMG src=\"gfx/' .
                        ($aVariant['is_hgvs']? 'mark_1.png\" alt=\"Valid syntax' :
                            ($aVariant['is_hgvs'] === null? 'lovd_form_question.png\" alt=\"Unsupported syntax' :
                                'mark_0.png\" alt=\"Invalid syntax')) . '\"></TD>' .
                    '<TD>' . (!$aVariant['fixed_variant_is_hgvs']? '-' : htmlspecialchars($aVariant['fixed_variant'])) . '</TD>' .
                    '<TD>' .
                        ($aVariant['is_hgvs']? '-' : '- ' .
                            implode('<BR>- ',
                                array_map('strip_tags',
                                    array_merge($aVariant['variant_info']['errors'], $aVariant['variant_info']['warnings'])))) . '</TD>';
            if ($bVV) {
                $sTable .= '<TD>' . $aVariant['VV'] . '</TD>';
            }
            $sTable .= '</TR>';
        }
    }

    $sTable .= '</TABLE>';


    // Create response.
    $sResponse = ($bAllIsHGVS? 'All of the variants passed our syntax check!' :
                               'Some of the variants did not pass our syntax check...') .

                  '<BR><BR>' .
                   $sTable .
                  '<BR>' .
                  '<BUTTON onclick=\"downloadResponse();\">Download result</BUTTON>';
}




// Print the response.
print('
$("#response").html("' . $sResponse . '");');
?>
