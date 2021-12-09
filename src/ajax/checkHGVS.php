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

define('ROOT_PATH', './../');
require ROOT_PATH . 'inc-lib-variants.php';
require ROOT_PATH . 'inc-lib-init.php';
header('Content-type: text/javascript; charset=UTF-8');

// Resetting the check/cross sign next to the written variant.
print('$("#checkResult").attr("src", "gfx/trans.png");');
print('$("#response").html("");');


if ($_REQUEST['var'] == '') {
    exit;
}

// Initialise the response.
$sResponse = '';




// Run when a single variant was given.
if ($_REQUEST['method'] == 'single') {
    $sVariant = $_REQUEST['var'];

    // First check to see if the variant is HGVS.
    $bIsHGVS = lovd_getVariantInfo($sVariant, false, true);

    $sResponse .= 'The given variant is ' . ($bIsHGVS ? '' : 'not') . ' HGVS.<br><br>';

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

        } else {
            $sFixedVariantPlusLink = '<a href=\"\" onclick=\"$(\'#variant\').val(\'' . $sFixedVariant . '\'); $(\'#checkButton\').click() ; return false;\">' . $sFixedVariant . '</a>';

            if (lovd_getVariantInfo($sFixedVariant, false, true)) {
                $sResponse .= 'Did you mean ' . $sFixedVariantPlusLink . ' ?<br>';
            } else {
                $sResponse .= 'We could not (safely) turn your variant into an HGVS description, but this suggestion might be an improvement: ' . $sFixedVariantPlusLink . '.<br>';
            }
        }
    }
}





// Run when a list of variants was given.
if ($_REQUEST['method'] == 'list') {
    $sVariants = urldecode($_REQUEST['var']);


    $bAllIsHGVS = true;

    $sTable = '<HTML><TABLE border=\"0\" cellpadding=\"10\" cellspacing=\"1\" class=\"data\">' .
              '<TR><TH style=\"background : #90E090;\">Variant</TH><TH style=\"background : #90E090;\">Is HGVS? (T/F)</TH><TH style=\"background : #90E090;\">Fixed variant</TH><TH style=\"background : #90E090;\">Warnings and errors</TH></TR>';

    foreach (explode("\n", $sVariants) as $sVariant) {
        if ($sVariant == '') {
            $sTable .= '<TR></TR>';

        } else {
            $sVariant = rtrim($sVariant); // Removing floating whitespaces.
            $bIsHGVS = lovd_getVariantInfo($sVariant, false, true);
            $sColour = 'green';

            if (!$bIsHGVS) {
                $bAllIsHGVS = false;
                $aVariantInfo = lovd_getVariantInfo($sVariant, false);
                $sFixedVariant = lovd_fixHGVS($sVariant);
                $bFixedIsHGVS = lovd_getVariantInfo($sFixedVariant, false, true);

                // Colour = red if the variant could not be improved, green
                // if it was fully fixed and orange if it was partially fixed.
                $sColour = ($sVariant == $sFixedVariant || !$bFixedIsHGVS? 'red' : 'orange');
            }

            $sTable .= '<TR valign=\"top\" class=\"col' . ucfirst($sColour) .'\">' .
                '<TD>' . htmlspecialchars($sVariant) . '</TD>' .
                '<TD>' . ($bIsHGVS? 'T' : 'F') . '</TD>' .
                '<TD >' . htmlspecialchars(($bIsHGVS || !$bFixedIsHGVS? $sVariant : $sFixedVariant)) . '</TD>';

            if (!$bIsHGVS) {
                $sTable .= '<TD>' . (empty($aVariantInfo['errors'])? '' : '<B>Errors: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['errors'])))) .
                                    (!$aVariantInfo['warnings'] || !$aVariantInfo['errors']? '' : '<BR>') .
                                    (empty($aVariantInfo['warnings'])? '' : '<B>Warnings: - </B>' . htmlspecialchars(implode(' - ', array_values($aVariantInfo['warnings'])))) . '</TD></TR>';
            } else {
                $sTable .= '<TD></TD>';
            }
        }
    }

    $sTable .= '</TABLE></HTML>';

    $sResponse .= 'The variants are ' . ($bAllIsHGVS ? '' : 'not') . ' all clean HGVS description.<br><br>';

    if (!$bAllIsHGVS) {
        $sResponse .= $sTable;
    }
}




// Print the response.
print('$("#response").html("' . $sResponse . '");');

?>
