<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-01-22
 * Modified    : 2016-02-05
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
 *               Bsc. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
 * along with LOVD. If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
    require_once ROOT_PATH . 'class/soap_client.php';
/**
 * This method can predict a protein description of a variant based on a
 * transcript DNA field.
 * @param string $sVariant
 * @param string $sGene
 * @return array $aMutalyzerData
 **/
function lovd_getRNAProteinPrediction ($sVariant, $sGene)
{
    $aMutalyzerData = array();

    // Check if variant is an UD, NC or NG and described as a c or n variant.
    if (!preg_match('/^((UD_\d{12}|N(?:C|G)_\d{6,}\.\d{1,2})\(' . $sGene . '_v\d{3}\)):[cn]\..+$/', $sVariant, $aVariantMatches)) {
        $aMutalyzerData['mutalyzer_error'] = 'Not a valid variant, invalid reference sequence (not an UD, NC or NG) or variant doesn\'t use c. or n. notation.';
        return $aMutalyzerData;
    }

    $sProteinPrefix = str_replace('_v', '_i', $aVariantMatches[1]);

    $_Mutalyzer = new LOVD_SoapClient();
    try {
        $oOutput = $_Mutalyzer->runMutalyzer(array('variant' => $sVariant))->runMutalyzerResult;
    } catch (SoapFault $e) {
        $aMutalyzerData['mutalyzer_error'] = 'Unexpected response from Mutalyzer. Please try again later.';
        return $aMutalyzerData;
    }

    if (!empty($oOutput->proteinDescriptions->string)) {
        $sProteinDescriptions = implode('|', $oOutput->proteinDescriptions->string);
        preg_match('/' . preg_quote($sProteinPrefix) . ':(p\..+?)(\||$)/', $sProteinDescriptions, $aProteinMatches);
        if (isset($aProteinMatches[1])) {
            $aMutalyzerData['predict']['protein'] = $aProteinMatches[1];
        }
    }

    if(isset($oOutput->messages->SoapMessage)){
        foreach ($oOutput->messages->SoapMessage as $aSoapMessage) {
            if (preg_match('/_OTHER$/', $aSoapMessage->errorcode) !== 0) {
                // Whatever error it is, it's not about this gene!
                continue;
            }
            if ($aSoapMessage->errorcode === 'ERANGE') {
                // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
                $sDNAChange = substr($sVariant, strpos($sVariant, ':') + 1);
                $aVariantRange = explode('_', $sDNAChange);
                // Check what the variant looks like and act accordingly.
                if (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/-\d+/', $aVariantRange[1])) {
                    // Variant has 2 positions. Variant has both the start and end positions upstream of the transcript, we can assume that the product will not be affected.
                    $sPredictR = 'r.(=)';
                    $sPredictP = 'p.(=)';
                } elseif (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                    // Variant has 2 positions. Variant has an upstream start position and a downstream end position, we can assume that the product will not be expressed.
                    $sPredictR = 'r.0?';
                    $sPredictP = 'p.0?';
                } elseif (count($aVariantRange) == 2 && preg_match('/\*\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                    // Variant has 2 positions. Variant has both the start and end positions downstream of the transcript, we can assume that the product will not be affected.
                    $sPredictR = 'r.(=)';
                    $sPredictP = 'p.(=)';
                } elseif (count($aVariantRange) == 1 && preg_match('/-\d+/', $aVariantRange[0]) || preg_match('/\*\d+/', $aVariantRange[0])) {
                    // Variant has 1 position and is either upstream or downstream from the transcript, we can assume that the product will not be affected.
                    $sPredictR = 'r.(=)';
                    $sPredictP = 'p.(=)';
                } else {
                    // One of the positions of the variant falls within the transcript, so we can not make any assumptions based on that.
                    $sPredictR = 'r.?';
                    $sPredictP = 'p.?';
                }
                // Fill in our assumption in aData to forge that this information came from Mutalyzer.
                $aMutalyzerData['predict']['protein'] = $sPredictP;
                $aMutalyzerData['predict']['RNA'] = $sPredictR;
                continue;
            } elseif ($aSoapMessage->errorcode === 'WSPLICE') {
                // Mutalyzer now (2012-12-07) returns a WSPLICE for <= 5 nucleotides from the site,
                // even though there internally is a difference between variants in splice sites,
                // and variants close to splice sites.
                // Most likely, they will include two different types of errors in the future.
                $aMutalyzerData['predict']['protein'] = 'p.?';
                $aMutalyzerData['predict']['RNA'] = 'r.spl?';
            }

            if (isset($aSoapMessage->errorcode) && substr($aSoapMessage->errorcode, 0, 1) === 'E') {
                $aMutalyzerData['error'][trim($aSoapMessage->errorcode)] =  trim($aSoapMessage->message);
            } else if (isset($aSoapMessage->errorcode)) {
                $aMutalyzerData['warning'][trim($aSoapMessage->errorcode)] = trim($aSoapMessage->message);
            }
        }
    }

    if ($oOutput->errors === 0) {
        // RNA not filled in yet.
        if ($aMutalyzerData['predict']['protein'] == 'p.?') {
            $aMutalyzerData['predict']['RNA'] = 'r.?';
        } elseif ($aMutalyzerData['predict']['protein'] == 'p.(=)') {
            // FIXME: Not correct in case of substitutions e.g. in the third position of the codon, not leading to a protein change.
            $aMutalyzerData['predict']['RNA'] = 'r.(=)';
        } else {
            // RNA will default to r.(?).
            $aMutalyzerData['predict']['RNA'] = 'r.(?)';
        }
    }

    return $aMutalyzerData;
}
