<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-01-22
 * Modified    : 2016-06-23
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
 *               Bsc. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

/**
 * Predict a protein description of a variant and given transcript using the
 * Mutalyzer webservice.
 * @param $sReference
 * @param string $sGene
 * @param $sNCBITranscriptID
 * @param string $sVariant
 * @return array $aMutalyzerData
 */
function lovd_getRNAProteinPrediction ($sReference, $sGene, $sNCBITranscriptID, $sVariant)
{
    global $_CONF;

    // Needs to be a require_once in case other code has already included this, and also for repeated calls to this function.
    require_once ROOT_PATH . 'class/soap_client.php';

    $aMutalyzerData = array();

    // Regex pattern to match a reference accession number in variant description.
    $sRefseqPattern = '(UD_\d{12}|N(?:C|G)_\d{6,}\.\d{1,2})';


    if (isset($sGene) && isset($_SETT['mito_genes_aliases'][$sGene])) {
        // This is a mitochondrial gene
        if (empty($sNCBITranscriptID) || empty($sVariant)) {
            $aMutalyzerData['mutalyzer_error'] = 'No valid transcript ID or variant specified.';
            return $aMutalyzerData;
        }
        // Gene is defined in the mito_genes_aliases in file inc-init.php: use the NCBI gene symbol.
        $sNCBITranscriptID = str_replace($sGene, $_SETT['mito_genes_aliases'][$sGene],
                                         $sNCBITranscriptID);

        // For mitochondrial genes, Mutalyzer specifies the NCBI transcript ID actually
        // as an NC_ accession number with NCBI gene alias (e.g. 'NC_012920.1(TRNF_v001)')
        // We can use that directly as a reference in the variant description.
        $sFullVariantDescription = $sNCBITranscriptID . ':' . $sVariant;
    } else {
        // Non-mitochondrial gene, use normal reference, transcript ID and variant.
        if (empty($sReference) || empty($sNCBITranscriptID) || empty($sVariant) ||
            !preg_match('/^' . $sRefseqPattern . '$/', $sReference)) {

            $aMutalyzerData['mutalyzer_error'] = 'No valid input given for reference, transcript id or variant.';
            return $aMutalyzerData;
        }

        $sFullVariantDescription = $sReference . '(' . $sNCBITranscriptID . '):' . $sVariant;
    }


    // Build URL for protein prediction to be shown in interface.
    $sURLComponents = parse_url($_CONF['mutalyzer_soap_url']);
    $sBaseMutalyzerURL = '';
    if (isset($sURLComponents['scheme'])) {
        $sBaseMutalyzerURL .= $sURLComponents['scheme'] . '://';
    }
    if (isset($sURLComponents['host'])) {
        $sBaseMutalyzerURL .= $sURLComponents['host'];
    }
    $aMutalyzerData['mutalyzer_url'] = $sBaseMutalyzerURL . '/check?name=' . urlencode($sFullVariantDescription) . '&standalone=1';

    // Make call to mutalyzer to check variant description.
    $_Mutalyzer = new LOVD_SoapClient();
    try {
        $oOutput = $_Mutalyzer->runMutalyzer(array('variant' => $sFullVariantDescription))->runMutalyzerResult;
    } catch (SoapFault $e) {
        $aMutalyzerData['mutalyzer_error'] = 'Unexpected response from Mutalyzer. Please try again later.';
        return $aMutalyzerData;
    }

    // When transcript is not found, attempt fallback to newer version of transcript
    foreach (getMutalyzerMessages($oOutput) as $oSoapMessage) {
        if ($oSoapMessage->errorcode === 'EINVALIDTRANSVAR') {
            // Invalid transcript variant

            if (isset($oOutput->legend) && !empty($oOutput->legend->LegendRecord)) {
                // Check if a newer version of the transcript is available from the legend.
                list($sAccession, $sVersion) = explode('.', $sNCBITranscriptID);

                foreach ($oOutput->legend->LegendRecord as $oRecord) {
                    $aRecordFields = explode('.', $oRecord->id);
                    if (count($aRecordFields) != 2) {
                        continue;
                    }
                    list($sAltAccession, $sAltVersion) = $aRecordFields;

                    if ($sAccession == $sAltAccession &&
                        intval($sAltVersion) > intval($sVersion)) {
                        // Found a newer version of the transcript. Try to do protein
                        // prediction using that record instead.
                        $aAltMutalyzerOutput = lovd_getRNAProteinPrediction($sReference, $sGene,
                            $oRecord->id, $sVariant);
                        if (!isset($aAltMutalyzerOutput['mutalyzer_error']) &&
                            !isset($aAltMutalyzerOutput['error']) &&
                            !empty($aAltMutalyzerOutput['predict'])) {
                            // Prediction with alternative transcript record went well, return it
                            // with an added warning.
                            $aAltMutalyzerOutput['warning']['DEPRECATED TRANSCRIPT'] =
                                'The provided transcript is outdated, the given prediction is ' .
                                'based on the latest version of the transcript: ' .
                                $sAltAccession . '.' . $sAltVersion;
                            return $aAltMutalyzerOutput;
                        }
                    }
                }

                // Could not find a newer version of the transcript.
                $aMutalyzerData['error'][$oSoapMessage->errorcode] = trim($oSoapMessage->message);
                return $aMutalyzerData;
            }
        }
    }

    // Find protein prediction in mutalyzer output.
    if (isset($oOutput->legend) && !empty($oOutput->legend->LegendRecord) &&
        !empty($oOutput->proteinDescriptions->string)) {
        $sMutProteinName = null;

        // Loop over legend records to find transcript name (v-number)
        foreach ($oOutput->legend->LegendRecord as $oRecord) {
            if ($oRecord->id == $sNCBITranscriptID && substr($oRecord->name, -4, 1) == 'v') {

                // Generate protein isoform name (i-number) from transcript name (v-number)
                $sMutProteinName = substr($oRecord->name, 0, strlen($oRecord->name) - 4) . 'i' .
                                   substr($oRecord->name, -3, 3);
                break;
            }
        }

        if (isset($sMutProteinName)) {
            // Select protein description based on protein isoform (i-number)
            $sProteinDescriptions = implode('|', $oOutput->proteinDescriptions->string);
            preg_match('/' . $sRefseqPattern . '\(' . preg_quote($sMutProteinName) .
                       '\):(p\..+?)(\||$)/', $sProteinDescriptions, $aProteinMatches);
            if (isset($aProteinMatches[2])) {
                $aMutalyzerData['predict']['protein'] = $aProteinMatches[2];
            }
        }
    }


    foreach (getMutalyzerMessages($oOutput) as $oSoapMessage) {
        if ($oSoapMessage->errorcode === 'ERANGE') {
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
        } elseif ($oSoapMessage->errorcode === 'WSPLICE') {
            // Mutalyzer now (2012-12-07) returns a WSPLICE for <= 5 nucleotides from the site,
            // even though there internally is a difference between variants in splice sites,
            // and variants close to splice sites.
            // Most likely, they will include two different types of errors in the future.
            $aMutalyzerData['predict']['protein'] = 'p.?';
            $aMutalyzerData['predict']['RNA'] = 'r.spl?';
        }

        if (isset($oSoapMessage->errorcode) && substr($oSoapMessage->errorcode, 0, 1) === 'E') {
            $aMutalyzerData['error'][trim($oSoapMessage->errorcode)] =  trim($oSoapMessage->message);
        } else if (isset($oSoapMessage->errorcode)) {
            $aMutalyzerData['warning'][trim($oSoapMessage->errorcode)] = trim($oSoapMessage->message);
        }
    }

    if ($oOutput->errors === 0 && empty($aMutalyzerData['predict']['RNA'])) {
        // RNA not filled in yet.
        if (!isset($aMutalyzerData['predict']['protein'])) {
            // Non-coding transcript, Mutalyzer does not return a protein field, but also no error.
            // FIXME: Check for intronic variants here, that do not span over an exon, and give them r.(=).
            $aMutalyzerData['predict']['RNA'] = 'r.(?)';
            $aMutalyzerData['predict']['protein'] = '-';
        } elseif ($aMutalyzerData['predict']['protein'] == 'p.?') {
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


function getMutalyzerMessages($oOutput) {
    // Return an array of messages from mutalyzer SOAP output. Only messages
    // related to the gene in the original request are returned.

    $aMessages = array();

    if (isset($oOutput->messages->SoapMessage)) {
        foreach ($oOutput->messages->SoapMessage as $oSoapMessage) {
            if (preg_match('/_OTHER$/', $oSoapMessage->errorcode) !== 0) {
                // Whatever error it is, it's not about this gene!
                continue;
            }
            $aMessages[] = $oSoapMessage;
        }
    }
    return $aMessages;
}
