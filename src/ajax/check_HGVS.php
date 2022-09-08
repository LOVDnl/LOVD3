<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-09-06
 * Modified    : 2022-09-06
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

header('Content-type: application/json; charset=UTF-8');





// Handling both single submissions and list submissions using the same code.
// This removes duplication, or perhaps worse, differences in implementation.
// Put the variants in an array, assigning them as keys.
$aVariants = array_fill_keys(
    // This is not a normal form; JS combines the values without adding a \r.
    array_map('htmlspecialchars',
        array_map('trim', explode("\n", $_REQUEST['var']))),
    array()
);

foreach ($aVariants as $sVariant => $aVariant) {
    $sVariant = trim(html_entity_decode($sVariant));
    if (!$sVariant) {
        unset($aVariants[$sVariant]);
        continue;
    }

    $aVariant['fixed_variant'] = '';
    $aVariant['fixed_variant_confidence'] = false;
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
        $aVariant['fixed_variant_variant_info'] = lovd_getVariantInfo($aVariant['fixed_variant'], false);
        if (!$aVariant['variant_info']) {
            $aVariant['variant_info'] = array(
                'errors' => array(
                    'EFAIL' => 'Failed to recognize a variant description in your input.'
                ),
                'warnings' => array(),
            );
        }

        // We normally don't show non-HGVS compliant suggestions. Exception;
        // Treat the result as HGVS compliant (i.e., accept suggestion and show)
        //  when all we had was a WTOOMUCHUNKNOWN and now we get a ESUFFIXMISSING.
        // The issue is that WTOOMUCHUNKNOWN suggests a fix, so it's stupid to then not show it.
        // Also, add the error to the current list, so it's not lost.
        if ($aVariant['variant_info'] && $aVariant['fixed_variant_variant_info']
            && array_keys($aVariant['variant_info']['errors'] + $aVariant['variant_info']['warnings']) == array('WTOOMUCHUNKNOWN')
            && array_keys($aVariant['fixed_variant_variant_info']['errors'] + $aVariant['fixed_variant_variant_info']['warnings']) == array('ESUFFIXMISSING')) {
            $aVariant['variant_info']['errors'] += $aVariant['fixed_variant_variant_info']['errors']; // For the output.
            unset($aVariant['fixed_variant_variant_info']['errors']['ESUFFIXMISSING']);
        }

        // Then check if the fix is HGVS-compliant.
        $aVariant['fixed_variant_is_hgvs'] = (
            is_array($aVariant['fixed_variant_variant_info'])
            &&
            empty($aVariant['fixed_variant_variant_info']['errors'])
            &&
            (
                empty($aVariant['fixed_variant_variant_info']['warnings'])
                ||
                array_keys($aVariant['fixed_variant_variant_info']['warnings']) == array('WNOTSUPPORTED')
            )
        );

        // And add the confidence for us.
        $aVariant['fixed_variant_confidence'] = lovd_fixHGVSGetConfidence(
            $sVariant,
            $aVariant['fixed_variant'],
            $aVariant['variant_info'],
            $aVariant['fixed_variant_variant_info']
        );
    }

    $aVariant['VV'] = array();
    if ($bVV) {
        if (!empty($aVariant['variant_info'])
            && !empty($aVariant['variant_info']['errors']['ENOTSUPPORTED'])) {
            $aVariant['VV']['ENOTSUPPORTED'] = 'This variant description is not currently supported by VariantValidator.';
        } elseif (!empty($aVariant['variant_info'])
            && !empty($aVariant['variant_info']['warnings']['WNOTSUPPORTED'])) {
            $aVariant['VV']['WNOTSUPPORTED'] = 'This variant description is not currently supported by VariantValidator.';
        } elseif (!$aVariant['is_hgvs']) {
            $aVariant['VV']['EFAIL'] = 'Please first correct the variant description to run VariantValidator.';
        } elseif (!$aVariant['has_refseq']) {
            $aVariant['VV']['EREFSEQMISSING'] = 'Please provide a reference sequence to run VariantValidator.';

        } else {
            // Call VariantValidator. Use the outcome of lovd_getVariantInfo()
            //  to determine whether this is a genomic variant or not.
            $aVV = (!isset($aVariant['variant_info']['position_start_intron'])?
                    $_VV->verifyGenomic($sVariant) :
                    $_VV->verifyVariant($sVariant));

            if ($aVV === false) {
                $aVariant['VV']['EINTERNAL'] = 'An internal error within VariantValidator occurred when trying to validate your variant.';
            } elseif (!empty($aVV['data']['DNA'])) {
                // Our VV library removed the refseq, put it back.
                $aVV['data']['DNA'] = lovd_getVariantRefSeq($sVariant) . ':' . $aVV['data']['DNA'];
                if ($sVariant != $aVV['data']['DNA']) {
                    // We don't check for WCORRECTED here, because the VV library accepts some changes
                    //  without setting WCORRECTED. We want to show every difference.
                    $aVariant['VV']['WCORRECTED'] = 'VariantValidator automatically corrected the variant description to <B>' . $aVV['data']['DNA'] . '</B>.';
                    unset($aVV['warnings']['WCORRECTED']); // In case it exists.
                    unset($aVV['warnings']['WROLLFORWARD']); // In case it exists.
                    $aVariant['fixed_variant'] = $aVV['data']['DNA'];
                    $aVariant['fixed_variant_is_hgvs'] = true;
                }

                if (!$aVV['errors'] && !$aVV['warnings'] && !$aVariant['VV']) {
                    $aVariant['VV']['IOK'] = 'The variant description passed the validation by VariantValidator.';
                } else {
                    $aVariant['VV'] = array_merge(
                        $aVariant['VV'],
                        array_map(
                            function ($sValue)
                            {
                                return 'VariantValidator: ' . $sValue;
                            },
                            array_merge($aVV['errors'], $aVV['warnings'])
                        )
                    );
                }
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
    // Green if it's HGVS and there's no improvement from VV. (bootstrap: success)
    // Orange if it's ENOTSUPPORTED, or if we have a fix that's HGVS. (bootstrap: warning)
    // Red, otherwise. We don't get the variant at all, or we couldn't find an HGVS-compliant fix. (bootstrap: danger)
    $aVariant['color'] =
        ($aVariant['is_hgvs'] && !$aVariant['fixed_variant']? 'green' :
            ($aVariant['is_hgvs'] === null || $aVariant['fixed_variant_is_hgvs']? 'orange' :
                'red'));

    $aVariants[htmlspecialchars($sVariant)] = $aVariant;
}

// Prevent any XSS here, by simply escaping all errors, warnings, VV messages, suggested corrections, etc.
array_walk_recursive(
    $aVariants,
    function (&$sValue, $sKey)
    {
        // Only strings are sent through; we won't get array values here.
        $sValue = htmlspecialchars($sValue);
    }
);

echo json_encode($aVariants);
?>
