<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-12-03
 * Modified    : 2021-12-09
 * For LOVD    : 3.5-pre-02
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

if ($_REQUEST['var'] == '') {
    exit;
}

define('ROOT_PATH', './../');
require ROOT_PATH . 'inc-lib-variants.php';
require ROOT_PATH . 'inc-init.php';

if ($_REQUEST['nameCheck'] == 'true') {
    require ROOT_PATH . 'class/variant_validator.php';
}

header('Content-type: text/javascript; charset=UTF-8');

// Resetting the check/cross sign next to the written variant.
print('$("#checkResult").attr("src", "gfx/trans.png");');
print('$("#response").html("");');



// Initialise the response.
$sResponse = '';




// Run when a single variant was given.
if ($_REQUEST['method'] == 'single') {
    $sVariant = $_REQUEST['var'];

    // First check to see if the variant is HGVS.
    $bIsHGVS = lovd_getVariantInfo($sVariant, false, true);

    $sResponse .= 'The given variant is ' . ($bIsHGVS ? '' : 'not ') . 'HGVS.<br><br>';

    // Show whether the variant was correct through a check or a cross.
    print('$("#checkResult").attr("src", "gfx/' . ($bIsHGVS ? 'check' : 'cross') . '.png"); ');


    if (!$bIsHGVS) {
        // Call lovd_getVariantInfo to get the warnings and errors.
        $aVariantInfo = lovd_getVariantInfo($sVariant, false);

        // Add the warning and error messages.
        $aCleanedMessages = array(
            'warnings' => htmlspecialchars(implode("\n - ", array_values($aVariantInfo['warnings']))),
            'errors' => htmlspecialchars(implode("\n - ", array_values($aVariantInfo['errors']))),
        );

        $sResponse .= ($aVariantInfo['warnings'] ? '<b>Warnings:</b><br> - ' . str_replace("\n", '<br>', $aCleanedMessages['warnings']) . '<br>' : '') .
            ($aVariantInfo['warnings'] && $aVariantInfo['errors'] ? '<br>' : '') .
            ($aVariantInfo['errors'] ? '<b>Errors:</b><br> - ' . str_replace("\n", '<br>', ($aCleanedMessages['errors'])) . '<br>' : '') . '<br>' .
            '<b>Automatically fixed variant:</b><br>';


        // Return the fixed variant (if it was actually fixed).
        $sFixedVariant = lovd_fixHGVS($sVariant);

        if ($sVariant == $sFixedVariant) {
            $sResponse .= 'Sadly, we could not (safely) fix your variant...<br><br>';
            unset($sFixedVariant); // If no changes were made, we don't need this variable.

        } else {
            $sFixedVariantPlusLink = '<a href=\"\" onclick=\"$(\'#variant\').val(\'' . $sFixedVariant . '\'); $(\'#checkButton\').click() ; return false;\">' . $sFixedVariant . '</a>';

            if (lovd_getVariantInfo($sFixedVariant, false, true)) {
                $sResponse .= 'Did you mean ' . $sFixedVariantPlusLink . ' ?<br>';
            } else {
                $sResponse .= 'We could not (safely) turn your variant into an HGVS description, but this suggestion might be an improvement: ' . $sFixedVariantPlusLink . '.<br>';
            }
        }
    }


    // Performing the nameCheck using VariantValidator.
    if ($_REQUEST['nameCheck'] == 'true') {
        $sResponse .= '<br><b>Result of the name check (calling VariantValidator):</b><br>';

        // We only want to run a name check on HGVS variants.
        if (!$bIsHGVS) {
            $sResponse .=
                'A name check can only be run using HGVS descriptions. Please take another look at your variant' .
                (isset($sFixedVariant)? '' : ' and our recommended fixes') . ', and try again.';

        } else {
            // We also only want to run a name check if a
            //  reference sequence was provided.
            if (!lovd_findReferenceSequence($sVariant)) {
                $sResponse .= 'Please provide a reference sequence to check the contents of your variant.';

            } else {
                $sVariant = (!isset($sFixedVariant) || !lovd_getVariantInfo($sFixedVariant, false, true) ?
                    $sVariant : $sFixedVariant);

                // Call VariantValidator.
                $_VV = new LOVD_VV();
                $aValidatedVariant = ($sVariant[strpos($sVariant, ':') + 1] == 'g'?
                    $_VV->verifyGenomic($sVariant, array()) :
                    $_VV->verifyVariant($sVariant, array()));


                // Returning the results.
                if (!$aValidatedVariant['errors'] && !$aValidatedVariant['warnings']) {
                    $sResponse .= 'The variant passed the name check.';

                } else {
                    $sResponse .=
                        // Fixme; where is the fixed variant?
                        (!$aValidatedVariant['warnings'] ? '' :
                            '<b>Warnings:<br> - ' . implode('<br> - ', $aValidatedVariant['warnings'])) . '<br>' .
                        (!$aValidatedVariant['errors'] ? '' :
                            '<b>Errors:</b><br> - ' . implode('<br> - ', $aValidatedVariant['errors']));
                }
            }
        }
    }
}





// Run when a list of variants was given.
if ($_REQUEST['method'] == 'list') {
    $sVariants = urldecode($_REQUEST['var']);


    $bAllIsHGVS = true;

    $sTable = '<HTML><TABLE border=\"0\" cellpadding=\"10\" cellspacing=\"1\" class=\"data\">' .
        '<TR>' .
           '<TH style=\"background : #90E090;\">Variant</TH>' .
           '<TH style=\"background : #90E090;\">Is HGVS? (T/F)</TH>' .
           '<TH style=\"background : #90E090;\">Fixed variant</TH>' .
           '<TH style=\"background : #90E090;\">Warnings and errors</TH>' .
           ($_REQUEST['nameCheck'] == 'false'? '' :
          '<TH style=\"background : #90E090;\">Result of the name check</TH>') .
        '</TR>';

    foreach (explode("\n", $sVariants) as $sVariant) {
        if ($sVariant == '') {
            $sTable .= '<TR></TR>';

        } else {
            $sVariant = rtrim($sVariant); // Removing floating whitespaces.
            $bIsHGVS = lovd_getVariantInfo($sVariant, false, true);
            $sColour = 'green';

            $sTable .= '<TR valign=\"top\" class=\"col' . ucfirst($sColour) .'\">' .
                '<TD>' . htmlspecialchars($sVariant) . '</TD>' .
                '<TD>' . ($bIsHGVS? 'T' : 'F') . '</TD>';

            if ($bIsHGVS) {
                $sTable .= '<TD></TD><TD></TD>';

                if ($_REQUEST['nameCheck'] == 'true') {
                    if (!lovd_findReferenceSequence($sVariant)) {
                        // We can only call VariantValidator if the variant
                        //  is HGVS and holds a reference sequence.
                        $sTable .= '<TD>could not perform name check: missing required reference sequence.</TD>';

                    } else {
                        $_VV = new LOVD_VV();
                        $aValidatedVariant = ($sVariant[strpos($sVariant, ':') + 1] == 'g' ?
                            $_VV->verifyGenomic($sVariant, array()) :
                            $_VV->verifyVariant($sVariant, array()));

                        if ($aValidatedVariant === false) {
                            $sTable .= '<TD>error performing name check.</TD>';

                        } else {
                        $sTable .= '<TD>' . (!$aValidatedVariant['errors'] && !$aValidatedVariant['warnings']?
                                'passed name check' :
                                'failed name check: - ' . implode('. - ',
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

                $sTable .= '<TD >' . htmlspecialchars((!$bFixedIsHGVS? $sVariant : $sFixedVariant)) . '</TD>';

                // Colour = red if the variant could not be improved, green
                // if it was HGVS and orange if it was fixed.
                $sColour = ($sVariant == $sFixedVariant || !$bFixedIsHGVS? 'red' : 'orange');

                $sTable .='<TD>' .
                    (empty($aVariantInfo['errors']) ? '' :
                        '<B>Errors: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['errors'])))) .
                    (!$aVariantInfo['warnings'] || !$aVariantInfo['errors'] ? '' : '<BR>') .
                    (empty($aVariantInfo['warnings']) ? '' :
                        '<B>Warnings: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['warnings'])))) .
                '</TD>';

                // Perform name check (call VariantValidator).
                if ($_REQUEST['nameCheck'] == 'true') {
                    $sTable .= '<TD>could not perform name check: variant is not HGVS.</TD>';
                }
            }
            $sTable .= '</TR>';
        }
    }

    $sTable .= '</TABLE></HTML>';

    $sResponse .= 'The variants are ' . ($bAllIsHGVS ? '' : 'not ') . 'all clean HGVS description.<br><br>';

    if (!$bAllIsHGVS) {
        $sResponse .= $sTable;
    }
}




// Print the response.
print('$("#response").html("' . $sResponse . '");');

?>
