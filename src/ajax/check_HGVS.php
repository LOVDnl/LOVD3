<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-06
 * Modified    : 2022-07-29
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
    $bVV = true;
} else {
    $bVV = false;
}

header('Content-type: text/javascript; charset=UTF-8');

// Resetting the check/cross sign next to the written variant.
print('
$("#checkResult").attr("src", "gfx/trans.png");
$("#response").html("");');





if ($_REQUEST['method'] == 'single') {
    // The form for one single variant was used.
    $sVariant = $_REQUEST['var'];

    // First check to see if the variant is HGVS.
    $bIsHGVS = lovd_getVariantInfo($_REQUEST['var'], false, true);

    $sResponse = 'The given variant ' . ($bIsHGVS ? 'passed' : 'did not pass') . ' our syntax check.<BR><BR>';

    // Warn the user if a reference sequence is missing.
    if (!lovd_variantHasRefSeq($_REQUEST['var']) && !$bVV) {
        $sResponse .= '<I>' .
            'Please note that your variant is missing a reference sequence.<BR>' .
            'Although this is not necessary for our syntax check, a variant description does ' .
            'need a reference to be fully informative and HGVS compliant.</I><BR><BR>';
    }

    // Show whether the variant was correct through a check or a cross.
    print('
$("#checkResult").attr("src", "gfx/' . ($bIsHGVS ? 'check' : 'cross') . '.png");');

    if (!$bIsHGVS) {
        // Call lovd_getVariantInfo to get the warnings and errors.
        $aVariantInfo = lovd_getVariantInfo($_REQUEST['var'], false);

        // Add the warning and error messages.
        if ($aVariantInfo) {
            $aCleanedMessages = array(
                'warnings' => htmlspecialchars(implode("\n - ", array_values($aVariantInfo['warnings']))),
                'errors' => htmlspecialchars(implode("\n - ", array_values($aVariantInfo['errors']))),
            );
        } else {
            $aCleanedMessages = array(
                'warnings' => '',
                'errors' => 'This entry is not recognized as a variant.',
            );
        }

        $sResponse .= ($aCleanedMessages['warnings'] ? '<B>Warnings:</B><BR> - ' . str_replace("\n", '<BR>', $aCleanedMessages['warnings']) . '<BR>' : '') .
            ($aCleanedMessages['warnings'] && $aCleanedMessages['errors'] ? '<BR>' : '') .
            ($aCleanedMessages['errors'] ? '<B>Errors:</B><BR> - ' . str_replace("\n", '<BR>', ($aCleanedMessages['errors'])) . '<BR>' : '') . '<BR>' .
            '<B>Automatically fixed variant:</B><BR>';


        // Return the fixed variant (if it was actually fixed).
        $sFixedVariant = lovd_fixHGVS($_REQUEST['var']);

        if ($_REQUEST['var'] == $sFixedVariant) {
            $sResponse .= 'Sadly, we could not (safely) fix your variant...<BR><BR>';
            unset($sFixedVariant); // If no changes were made, we don't need this variable.

        } else {
            $sFixedVariantPlusLink = '<A href=\"\" onclick=\"$(\'#variant\').val(\'' . $sFixedVariant . '\'); $(\'#checkButton\').click() ; return false;\">' . $sFixedVariant . '</A>';

            if (lovd_getVariantInfo($sFixedVariant, false, true)) {
                $sResponse .= 'Did you mean ' . $sFixedVariantPlusLink . '?<BR>';
            } else {
                $sResponse .= 'We could not (safely) turn your variant into a syntax that passes our tests, but this suggestion might be an improvement: ' . $sFixedVariantPlusLink . '.<BR>';
            }
        }
    }


    // Running VariantValidator.
    if ($bVV) {
        $sResponse .= '<BR><B>Running VariantValidator:</B><BR>';

        // We only want to run VariantValidator on HGVS variants.
        if (!$bIsHGVS) {
            $sResponse .=
                'VariantValidator can only be run using HGVS descriptions. Please take another look at your variant' .
                (isset($sFixedVariant) ? '' : ' and our recommended fixes') . ', and try again.';

        } else {
            // We cannot run VariantValidator if no
            //  reference sequence was provided.
            if (!lovd_variantHasRefSeq($_REQUEST['var'])) {
                $sResponse .= 'Please provide a reference sequence to run VariantValidator.';

            } else {
                $_REQUEST['var'] = (!isset($sFixedVariant) || !lovd_getVariantInfo($sFixedVariant, false, true) ?
                    $_REQUEST['var'] : $sFixedVariant);

                // Call VariantValidator.
                $_VV = new LOVD_VV();
                $aValidatedVariant = ($_REQUEST['var'][strpos($_REQUEST['var'], ':') + 1] == 'g' ?
                    $_VV->verifyGenomic($_REQUEST['var'], array()) :
                    $_VV->verifyVariant($_REQUEST['var'], array()));


                // Returning the results.
                if ($aValidatedVariant === false) {
                    $sResponse .= 'Internal error occurred';

                } elseif (!$aValidatedVariant['errors'] && !$aValidatedVariant['warnings']) {
                    $sResponse .= 'The variant passed the validation.';

                } elseif (in_array('WCORRECTED', array_keys($aValidatedVariant['warnings']))) {
                    $sResponse .= 'Your variant was corrected to: ' . $aValidatedVariant['data']['DNA'] . '.';

                } else {
                    $sResponse .=
                        (!$aValidatedVariant['warnings'] ? '' :
                            '<B>Warnings:</B><BR> - ' . implode('<BR> - ', $aValidatedVariant['warnings'])) . '<BR>' .
                        (!$aValidatedVariant['errors'] ? '' :
                            '<B>Errors:</B><BR> - ' . implode('<BR> - ', $aValidatedVariant['errors']));
                }
            }
        }
    }





} elseif ($_REQUEST['method'] == 'list') {
    // The form for multiple variants was used.
    $bAllIsHGVS = true;
    $bAllHoldRefSeqs = true;

    $sTable = '<TABLE id=\"responseTable\" border=\"0\" cellpadding=\"10\" cellspacing=\"1\" class=\"data\">' .
        '<TR>' .
           '<TH style=\"background : #90E090;\">Variant</TH>' .
           '<TH style=\"background : #90E090;\">Is HGVS? (T/F)</TH>' .
           '<TH style=\"background : #90E090;\">Fixed variant</TH>' .
           '<TH style=\"background : #90E090;\">Warnings and errors</TH>' .
           (!$bVV? '' :
          '<TH style=\"background : #90E090;\">Result of VariantValidator</TH>') .
        '</TR>';

    foreach (explode("\n", $_REQUEST['var']) as $sVariant) {
        if ($sVariant == '') {
            $sTable .= '<TR></TR>';

        } else {
            $sVariant = rtrim($sVariant); // Removing floating whitespaces.

            // Storing info on whether we find any variants which are missing
            //  reference sequences.
            if ($bAllHoldRefSeqs && !lovd_variantHasRefSeq($sVariant)) {
                $bAllHoldRefSeqs = false;
            }

            $bIsHGVS = lovd_getVariantInfo($sVariant, false, true);
            $sColour = 'green';

            $sTable .= '<TR valign=\"top\" class=\"col' . ucfirst($sColour) .'\">' .
                '<TD>' . htmlspecialchars($sVariant) . '</TD>' .
                '<TD>' . ($bIsHGVS? 'T' : 'F') . '</TD>';

            if ($bIsHGVS) {
                $sTable .= '<TD></TD><TD></TD>';

                if ($bVV) {
                    if (!lovd_variantHasRefSeq($sVariant)) {
                        // We can only call VariantValidator if the variant
                        //  is HGVS and holds a reference sequence.
                        $sTable .= '<TD>could not run VariantValidator: missing required reference sequence.</TD>';

                    } else {
                        $_VV = new LOVD_VV();
                        $aValidatedVariant = ($sVariant[strpos($sVariant, ':') + 1] == 'g'?
                            $_VV->verifyGenomic($sVariant, array()) :
                            $_VV->verifyVariant($sVariant, array()));

                        if ($aValidatedVariant === false) {
                            $sTable .= '<TD>internal error occurred.</TD>';

                        } elseif (in_array('WCORRECTED', array_keys($aValidatedVariant['warnings']))) {
                                $sTable .= '<TD>variant was corrected to: ' . $aValidatedVariant['data']['DNA'] . '.</TD>';

                        } else {
                            $sTable .= '<TD>' . (!$aValidatedVariant['errors'] && !$aValidatedVariant['warnings']?
                            'passed variant validation' :
                            'failed validation: - ' . implode('. - ',
                            array_merge($aValidatedVariant['errors'], $aValidatedVariant['warnings']))) . '.</TD>';
                        }
                    }
                }

                $sTable .= '</TR>';


            } else {
                // The variant is not HGVS.
                $bAllIsHGVS = false;
                $aVariantInfo = lovd_getVariantInfo($sVariant, false);
                $sFixedVariant = lovd_fixHGVS($sVariant);
                $bFixedIsHGVS = lovd_getVariantInfo($sFixedVariant, false, true);

                $sTable .= '<TD>' . htmlspecialchars((!$bFixedIsHGVS? $sVariant : $sFixedVariant)) . '</TD>';

                // Colour = red if the variant could not be improved, green
                // if it was HGVS and orange if it was fixed.
                $sColour = ($sVariant == $sFixedVariant || !$bFixedIsHGVS? 'red' : 'orange');

                if (empty($aVariantInfo['warnings']) && empty($aVariantInfo['errors'])
                    && $sFixedVariant != $sVariant && $bFixedIsHGVS) {
                    $sTable .= '<TD>The variant description has been corrected.</TD>';

                } else {
                    $sTable .= '<TD>' .
                        (empty($aVariantInfo['errors'])? '' :
                            '<B>Errors: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['errors'])))) .
                        (!$aVariantInfo['warnings'] || !$aVariantInfo['errors']? '' : '<BR>') .
                        (empty($aVariantInfo['warnings'])? '' :
                            '<B>Warnings: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['warnings'])))) .
                        '</TD>';
                }

                // Call VariantValidator.
                if ($bVV) {
                    $sTable .= '<TD>could not run VariantValidator: description is not HGVS.</TD>';
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
