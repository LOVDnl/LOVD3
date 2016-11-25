<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-22
 * Modified    : 2016-11-24
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class LOVD_API_Submissions {
    // This class defines the LOVD API object handling submissions.

    private $API;                     // The API object.
    private $nMaxPOSTSize = 1048576;  // The maximum POST size allowed (1MB).
    private $zAuth = array();         // User uploading the data.

    private $aRepeatableElements = array(
        'varioml' => array(
            'db_xref',
            'individual',
            'phenotype',
            'variant',
            'pathogenicity',
            'variant_detection',
        ),
    );

    private $aValueMappings = array(
        '@copy_count' => array(
            '1' => '0',
            '2' => '3',
        ),
        // Let's hope this one doesn't clash, otherwise we need to rebuild this array.
        '@term' => array(
            'Non-pathogenic' => '10',
            'Probably Not Pathogenic' => '30',
            'Probably Pathogenic' => '70',
            'Pathogenic' => '90',
            'Not Known' => '50',
        ),
        '@template' => array(
            'DNA' => 'DNA',
            'RNA' => 'RNA',
            'cDNA' => 'RNA',
            'AA' => 'Protein',
        ),
        'gender' => array(
            '0' => '?',
            '1' => 'M',
            '2' => 'F',
            '9' => '', // This assumes it's not a mandatory field.
        ),
    );
    private $aDNATypes = array(
        'DNA',
        'cDNA',
        'RNA',
        'AA'
    );





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }

        $this->API = $oAPI;
        return true;
    }





    private function cleanVarioMLData (&$aInput)
    {
        // Cleans the VarioML-formatted data by making sure all elements that
        //  can be repeated in the specs, are presented as an array.
        // Function will call itself, while traversing through the array.

        if (is_array($aInput)) {
            foreach ($aInput as $sKey => $Value) {
                // Attributes or text values can never be repeated, so check only possible arrays.
                if ($sKey{0} != '@' && $sKey{0} != '#') {
                    // Check if this key is listed as one that can be repeated.
                    if (in_array((string) $sKey, $this->aRepeatableElements['varioml'])) {
                        // This element can be repeated. Make sure it's a proper array of values.
                        if (!is_array($Value) || !isset($Value[0])) {
                            $aInput[$sKey] = $Value = array($Value);
                        }
                    }
                    if (is_array($Value)) {
                        $this->cleanVarioMLData($Value);
                        $aInput[$sKey] = $Value;
                    }
                }
            }
        }

        return true;
    }





    private function jsonDecode ($sInput)
    {
        // Attempts to decode the given JSON string, and handles any error.
        // Returns the array if successfully decoded, but throws any errors
        //  directly to the output.

        $aJSONErrors = array(
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
        );
        if (PHP_VERSION_ID >= 50303) {
            $aJSONErrors[JSON_ERROR_UTF8] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            if (PHP_VERSION_ID >= 50500) {
                $aJSONErrors[JSON_ERROR_RECURSION] = 'One or more recursive references in the value to be encoded';
                $aJSONErrors[JSON_ERROR_INF_OR_NAN] = 'One or more NAN or INF values in the value to be encoded';
                $aJSONErrors[JSON_ERROR_UNSUPPORTED_TYPE] = 'A value of a type that cannot be encoded was given';
            } else {
                // This makes sure they can be referenced, but can never occur.
                define('JSON_ERROR_RECURSION', 0);
                define('JSON_ERROR_INF_OR_NAN', 0);
                define('JSON_ERROR_UNSUPPORTED_TYPE', 0);
            }
        } else {
            // This makes sure they can be referenced, but can never occur.
            define('JSON_ERROR_UTF8', 0);
        }

        // Attempt to decode.
        $aInput = json_decode($sInput, true);

        // If not successful, try if a non-UTF8 string is the error.
        if ($aInput === NULL && json_last_error() == JSON_ERROR_UTF8) {
            // Encode to UTF8, and try again.
            $aInput = json_decode(utf8_encode($sInput), true);
        }

        if ($aInput === NULL) {
            // Handle errors.
            $this->API->aResponse['errors'][] = 'Error parsing JSON input. Error: ' . $aJSONErrors[json_last_error()] . '.';
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            return false;
        }

        // If we're still here, we have properly decoded data.
        return $aInput;
    }





    public function processPOST ()
    {
        // Handle POST requests for submissions.

        // Check if we're receiving data at all over POST.
        $sInput = file_get_contents('php://input');
        if (!$sInput) {
            // No data received.
            $this->API->aResponse['errors'][] = 'No data received.';
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            return false;
        }

        // Check the size of the data. Can not be larger than $this->nMaxPOSTSize.
        if (strlen($sInput) > $this->nMaxPOSTSize) {
            $this->API->aResponse['errors'][] = 'Payload too large. Maximum data size: ' . $this->nMaxPOSTSize . ' bytes.';
            $this->API->nHTTPStatus = 413; // Send 413 Payload Too Large.
            return false;
        }

        // If we have data, do a quick check if it could be JSON.
        // First, content type. Also accept an often made error (application/x-www-form-urlencoded data).
        if (isset($_SERVER['CONTENT_TYPE']) && !in_array($_SERVER['CONTENT_TYPE'], array('application/json', 'application/x-www-form-urlencoded'))) {
            $this->API->aResponse['errors'][] = 'Unsupported media type. Expecting: application/json.';
            $this->API->nHTTPStatus = 415; // Send 415 Unsupported Media Type.
            return false;
        }

        // Remove comments from JSON file, and trim.
        $sInput = trim(preg_replace('/\/\*.+\*\//Us', '', $sInput));

        // Then, check the first character. Should be an '{'.
        if ($sInput{0} != '{') {
            // Can't be JSON...
            $this->API->aResponse['errors'][] = 'Unsupported media type. Expecting: application/json.';
            $this->API->nHTTPStatus = 415; // Send 415 Unsupported Media Type.
            return false;
        }

        // If it appears to be JSON, have PHP try and convert it into an array.
        $aInput = $this->jsonDecode($sInput);
        // If $aInput is false, we failed somewhere. Function should have set response and HTTP status.
        if ($aInput === false) {
            return false;
        }

        return (
            // Clean up the JSON, not forcing minimum data requirements yet.
            // This makes traversing the array easier.
            $this->cleanVarioMLData($aInput) &&

            // Check for minimum data set.
            $this->verifyVarioMLData($aInput)

            // Do a quick check on the data, which is specific for the VarioML format.

            // Convert into the LOVD3 output file.
        );
    }





    private function verifyVarioMLData (&$aInput)
    {
        // Verifies if the VarioML data is complete; Is the source OK, and the
        //  authentication OK? Is there at least one individual with variants?
        // At least one screening present?
        global $_CONF, $_DB, $_SETT, $_STAT;

        // FIXME: If we'd have a proper VarioML JSON schema, like:
        // https://github.com/VarioML/VarioML/blob/master/json/examples/vreport.json-schema
        //  then we could use something like this library:
        // https://github.com/justinrainbow/json-schema
        //  to preprocess the VarioML, which saves us a lot of code. More info:
        // http://json-schema.org/

        // First, check if this file is actually meant for this LSDB.
        if (!isset($aInput['lsdb']) || !isset($aInput['lsdb']['@id'])) {
            // Without the proper LSDB info, we can't authorize this addition.
            $this->API->aResponse['errors'][] = 'VarioML error: LSDB root element not found, or has no ID.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }

        if ($aInput['lsdb']['@id'] != md5($_STAT['signature'])) {
            // Data file is not meant for this LSDB.
            $this->API->aResponse['errors'][] = 'VarioML error: LSDB ID in file does not match this LSDB. ' .
                'Submit your file to the correct LSDB, or if sure you want to submit here, ' .
                'request the LSDB ID from the admin: ' . $_SETT['admin']['name'] . ' <' . $_SETT['admin']['email'] . '>.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }



        // Then, check the source info and the authentication.
        if (!isset($aInput['lsdb']['source']) || !isset($aInput['lsdb']['source']['contact']) ||
            !isset($aInput['lsdb']['source']['contact']['name']) || !isset($aInput['lsdb']['source']['contact']['email'])) {
            $this->API->aResponse['errors'][] = 'VarioML error: Source element not found, contact element not found, or no contact information. ' .
                'You need to provide both a name and an email in the contact element.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }
        if (!isset($aInput['lsdb']['source']['contact']['db_xref'])) {
            $this->API->aResponse['errors'][] = 'VarioML error: Authentication IDs not found. ' .
                'You need to provide authentication IDs in db_xref elements in the contact element.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }
        // Loop the IDs for a valid one.
        $aAuth = array('id' => 0, 'auth_token' => '');
        foreach ($aInput['lsdb']['source']['contact']['db_xref'] as $aID) {
            if ($aID['@source'] == 'lovd') {
                $aAuth['id'] = $aID['@accession'];
            } elseif ($aID['@source'] == 'lovd_auth_token') {
                $aAuth['auth_token'] = $aID['@accession'];
            }
        }
        if (!$aAuth['id'] || !$aAuth['auth_token']) {
            // We don't have both an ID and the token, as required.
            $this->API->aResponse['errors'][] = 'VarioML error: Authentication IDs missing. ' .
                'You need both the lovd db_xref as the lovd_auth_token db_xref in your contact element.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }
        // Check the authentication.
        $this->zAuth = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ? AND auth_token = ? AND auth_token_expires > NOW()',
            array($aAuth['id'], $aAuth['auth_token']))->fetchAssoc();
        if (!$this->zAuth) {
            $this->API->aResponse['errors'][] = 'VarioML error: Authentication denied. ' .
                'Verify your lovd and lovd_auth_token values. Also check if your token has perhaps expired.';
            $this->API->nHTTPStatus = 401; // Send 401 Unauthorized.
            return false;
        }



        // Do we have data at all?
        if (!isset($aInput['lsdb']['individual']) || !$aInput['lsdb']['individual']) {
            $this->API->aResponse['errors'][] = 'VarioML error: Individual element not found, or no individuals. ' .
                'Your submission must include at least one individual data entry.';
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }



        // Loop through individual, checking minimal requirements.
        foreach ($aInput['lsdb']['individual'] as $iIndividual => $aIndividual) {
            // From now on, we won't return directly anymore if there are errors.
            // We let them accumulate, to make it easier for the user to test his file.
            $nIndividual = $iIndividual + 1; // We start counting at 1, like most humans do.

            // Required elements.
            foreach (array('@id', 'variant') as $sRequiredElement) {
                if (!isset($aIndividual[$sRequiredElement]) || !$aIndividual[$sRequiredElement]) {
                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Missing required ' . $sRequiredElement . ' element.';
                }
            }

            // Check gender, if present.
            if (isset($aIndividual['gender']) && isset($aIndividual['gender']['@code']) &&
                !isset($this->aValueMappings['gender'][$aIndividual['gender']['@code']])) {
                // Value not recognized.
                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Gender code \'' . $aIndividual['gender']['@code'] . '\' not recognized. ' .
                    'Options: ' . implode(', ', array_keys($this->aValueMappings['gender'])) . '.';
            }

            // Check phenotypes, if present.
            if (isset($aIndividual['phenotype'])) {
                foreach ($aIndividual['phenotype'] as $iPhenotype => $aPhenotype) {
                    $nPhenotype = $iPhenotype + 1; // We start counting at 1, like most humans do.
                    if (!isset($aPhenotype['@term'])) {
                        $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Missing required @term element.';
                    }
                    if (isset($aPhenotype['@source'])) {
                        if ($aPhenotype['@source'] != 'HPO') {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Source not understood. ' .
                                'Currently supported: HPO.';
                        } elseif (empty($aPhenotype['@accession'])) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Accession mandatory if source provided.';
                        } elseif (!ctype_digit($aPhenotype['@accession']) || strlen($aPhenotype['@accession']) != 7) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Accession not understood. ' .
                                'Expecting 7 digits.';
                        }
                    }
                }
            }



            // Check (genomic) variant, if present.
            if (isset($aIndividual['variant'])) {
                foreach ($aIndividual['variant'] as $iVariant => $aVariant) {
                    $nVariant = $iVariant + 1; // We start counting at 1, like most humans do.

                    // Required elements.
                    foreach (array('@copy_count', '@type', 'ref_seq', 'name', 'pathogenicity', 'variant_detection') as $sRequiredElement) {
                        if (!isset($aVariant[$sRequiredElement]) || !$aVariant[$sRequiredElement]) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Missing required ' . $sRequiredElement . ' element.';
                        }
                    }

                    // Check copy_count, if present.
                    if (isset($aVariant['@copy_count']) &&
                        !isset($this->aValueMappings['@copy_count'][$aVariant['@copy_count']])) {
                        // Value not recognized.
                        $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Copy count code \'' . $aVariant['@copy_count'] . '\' not recognized. ' .
                            'Options: ' . implode(', ', array_keys($this->aValueMappings['@copy_count'])) . '.';
                    }

                    // Check variant type, if present.
                    if (isset($aVariant['@type'])) {
                        // Check types.
                        if (!in_array($aVariant['@type'], $this->aDNATypes)) {
                            // Value not recognized.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Type code \'' . $aVariant['@type'] . '\' not recognized. ' .
                                'Options: ' . implode(', ', $this->aDNATypes) . '.';
                        } elseif ($aVariant['@type'] != 'DNA') {
                            // First variant must have type "DNA".
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Variant type must be genomic, indicated by type \'DNA\'. ' .
                                'Variants of other types can only be specified as children of a genomic variant.';
                        }
                    }

                    // Check ref_seq, if present.
                    if (isset($aVariant['ref_seq'])) {
                        if (empty($aVariant['ref_seq']['@source']) || empty($aVariant['ref_seq']['@accession'])) {
                            // No source or accession, no way.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Missing required RefSeq @source or @accession elements.';
                        } elseif ($aVariant['@type'] == 'DNA') {
                            // Check the ref_seq, but only if we're indeed using DNA.
                            if ($aVariant['ref_seq']['@source'] != 'genbank') {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': RefSeq source not understood. ' .
                                    'Currently supported: genbank.';
                            } elseif (!in_array($aVariant['ref_seq']['@accession'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'])) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': RefSeq accession ' . $aVariant['ref_seq']['@accession'] . ' not understood. ' .
                                    'Are you using the right genome build? This LOVD is configured for ' . $_CONF['refseq_build'] . '. ' .
                                    'Options: ' . implode(', ', $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']);
                            }
                        }
                    }

                    // Check name, if present.
                    if (isset($aVariant['name'])) {
                        if (empty($aVariant['name']['@scheme']) || empty($aVariant['name']['#text'])) {
                            // No scheme or text, no way.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Missing required name @scheme or #text elements.';
                        } elseif (strtolower($aVariant['name']['@scheme']) != 'hgvs') {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Name scheme not understood. ' .
                                'Currently supported: hgvs.';
                        }
                    }

                    // Check pathogenicity, if present.
                    if (isset($aVariant['pathogenicity'])) {
                        // We don't want to find conflicting info. Mark if we found pathogenicity of individual level.
                        $bPathogenicityIndividualScope = false;
                        foreach ($aVariant['pathogenicity'] as $iPathogenicity => $aPathogenicity) {
                            $nPathogenicity = $iPathogenicity + 1; // We start counting at 1, like most humans do.
                            if (empty($aPathogenicity['@scope']) || empty($aPathogenicity['@term'])) {
                                // No scope or term, no way.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': Missing required Pathogenicity @scope or @term elements.';
                            } elseif ($aPathogenicity['@scope'] != 'individual') {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': Pathogenicity scope \'' . $aPathogenicity['@scope'] . '\' not understood. ' .
                                    'LOVD only supports: individual.';
                            } else {
                                if ($bPathogenicityIndividualScope) {
                                    // We already saw this scope, that's not possible.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': You cannot have more than one Pathogenicity element of the same scope.';
                                } else {
                                    $bPathogenicityIndividualScope = true;
                                }
                                if (!isset($this->aValueMappings['@term'][$aPathogenicity['@term']])) {
                                    // Value not recognized.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': Pathogenicity term \'' . $aPathogenicity['@term'] . '\' not recognized. ' .
                                        'Options: ' . implode(', ', array_keys($this->aValueMappings['@term'])) . '.';
                                }
                            }
                        }
                    }

                    // Check variant_detection, if present.
                    if (isset($aVariant['variant_detection'])) {
                        foreach ($aVariant['variant_detection'] as $iScreening => $aScreening) {
                            $nScreening = $iScreening + 1; // We start counting at 1, like most humans do.
                            if (empty($aScreening['@template']) || empty($aScreening['@technique'])) {
                                // No template or technique, no way.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': VariantDetection #' . $nScreening . ': Missing required VariantDetection @template or @technique elements.';
                            } elseif (!isset($this->aValueMappings['@template'][$aScreening['@template']])) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': VariantDetection #' . $nScreening . ': VariantDetection template \'' . $aScreening['@template'] . '\' not understood. ' .
                                    'Options: ' . implode(', ', array_keys($this->aValueMappings['@template']));
                            }
                            // We currently don't parse the technique. We just accept anything.
                        }
                    }



                    // Check next level of variation (cDNA), if present.
                    if (isset($aVariant['seq_changes']) && isset($aVariant['seq_changes']['variant'])) {
                        // We collect the genes and transcripts annotated for this variant.
                        // If we cannot find *any* of the transcripts that are annotated for this variant, we throw an error.
                        // If we do have genes in the database that are mentioned, then we let the user know which transcripts we have that they are maybe interested in.
                        // If we also don't have any of the genes, we suggest the user to request them.
                        // If we do have at least one matching NM, we just issue warnings for all the ignored NMs.
                        // We also remove those from the array to save space and time when writing the file.
                        $aGenes = array();
                        $aGenesExisting = array();
                        $aTranscripts = array();
                        $aTranscriptsExisting = array();

                        // First loop through the variants: quick check not descending any further, and collect gene and transcript info.
                        foreach ($aVariant['seq_changes']['variant'] as $iVariantLevel2 => $aVariantLevel2) {
                            $nVariantLevel2 = $iVariantLevel2 + 1; // We start counting at 1, like most humans do.

                            // Required elements.
                            foreach (array('@type', 'ref_seq', 'name') as $sRequiredElement) {
                                if (!isset($aVariantLevel2[$sRequiredElement]) || !$aVariantLevel2[$sRequiredElement]) {
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Missing required ' . $sRequiredElement . ' element.';
                                }
                            }

                            // Check variant further, if we at least have a type.
                            if (isset($aVariantLevel2['@type'])) {
                                // Check types.
                                if (!in_array($aVariantLevel2['@type'], $this->aDNATypes)) {
                                    // Value not recognized.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Type code \'' . $aVariantLevel2['@type'] . '\' not recognized. ' .
                                        'Options: ' . implode(', ', $this->aDNATypes) . '.';

                                } elseif ($aVariantLevel2['@type'] != 'cDNA') {
                                    // Second level variant must have type "cDNA".
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Variant type must be cDNA, indicated by type \'cDNA\'. ' .
                                        ($aVariantLevel2['@type'] == 'DNA'?
                                            'Variants of the DNA type can not be children of other variants.' :
                                            'Variants of other types can only be specified as children of a cDNA variant.');

                                } else {
                                    // Collect gene...
                                    if (isset($aVariantLevel2['gene'])) {
                                        // VarioML specifies you can have multiple gene elements, but that makes no sense to me.
                                        // Let them add that to another variant on the cDNA level.
                                        if (empty($aVariantLevel2['gene']['@source']) || empty($aVariantLevel2['gene']['@accession'])) {
                                            // No source or accession, no way.
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Missing required Gene @source or @accession elements.';
                                        } elseif (strtolower($aVariantLevel2['gene']['@source']) != 'hgnc') {
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Gene source not understood. ' .
                                                'Currently supported: hgnc.';
                                        } else {
                                            // Store the gene, and check if it exists.
                                            $aGenes[$iVariantLevel2] = $aVariantLevel2['gene']['@accession'];
                                            if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE id = ?',
                                                array($aVariantLevel2['gene']['@accession']))->fetchColumn()) {
                                                // Gene exists.
                                                $aGenesExisting[$iVariantLevel2] = $aVariantLevel2['gene']['@accession'];
                                            }
                                        }
                                    }

                                    // Collect transcript...
                                    if (isset($aVariantLevel2['ref_seq'])) {
                                        if (empty($aVariantLevel2['ref_seq']['@source']) || empty($aVariantLevel2['ref_seq']['@accession'])) {
                                            // No source or accession, no way.
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Missing required RefSeq @source or @accession elements.';
                                        } else {
                                            // Check the ref_seq.
                                            if ($aVariantLevel2['ref_seq']['@source'] != 'genbank') {
                                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': RefSeq source not understood. ' .
                                                    'Currently supported: genbank.';
                                            } elseif (!preg_match('/^[NX][MR]_([0-9]{6}|[0-9]{9})(\.[0-9]{1,2})?$/', $aVariantLevel2['ref_seq']['@accession'])) {
                                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': RefSeq accession not understood. ' .
                                                    'Currently supported are NCBI RefSeq transcript reference sequences (NM, NR, XM, XR).';
                                            } else {
                                                // Store the transcript, and check if it exists (or an alternative can be used).
                                                $aTranscripts[$iVariantLevel2] = $aVariantLevel2['ref_seq']['@accession'];
                                                // We'll search flexibly, so get the transcript ID without the version.
                                                $sTranscriptNoVersion = substr($aVariantLevel2['ref_seq']['@accession'], 0, strpos($aVariantLevel2['ref_seq']['@accession'] . '.', '.') + 1);
                                                $sTranscriptAvailable = $_DB->query('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC LIMIT 1',
                                                    array($sTranscriptNoVersion . '%', $aVariantLevel2['ref_seq']['@accession']))->fetchColumn();
                                                if ($sTranscriptAvailable) {
                                                    $aTranscriptsExisting[$iVariantLevel2] = $sTranscriptAvailable;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // If we have transcripts, check which ones exists, and which ones don't.
                        // Otherwise, complain.
                        if (!$aTranscripts && $aVariant['seq_changes']['variant']) {
                            // We didn't receive any transcripts, but there were definitely variants defined.
                            // This is a problem.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChanges defined with variants, but none have a valid transcript defined.';
                            break; // Continue to the individual's next variant.
                        } elseif (!$aTranscriptsExisting) {
                            // We did have transcripts, but there are none in the database that match.
                            // If we had genes but now not anymore, that could explain it...
                            if ($aGenes) {
                                if (!$aGenesExisting) {
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': None of the given genes for this variant are configured in this LOVD. ' .
                                        'Please request the admin to create them: ' . $_SETT['admin']['name'] . ' <' . $_SETT['admin']['email'] . '>.';
                                } else {
                                    // Genes do exist. Mention which transcripts can then be used.
                                    $sTranscriptsAvailable = implode(', ', $_DB->query('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid IN (?' . str_repeat(', ?', count($aGenesExisting) - 1) . ') ORDER BY id_ncbi')->fetchAllColumn());
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': None of the given transcripts for this variant are configured in this LOVD. ' .
                                        'Options for the given genes: ' . $sTranscriptsAvailable . '.';
                                }
                            } else {
                                // No genes were ever given, focus on the transcripts.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': None of the given transcripts for this variant are configured in this LOVD. ' .
                                    'Please request the admin to create them: ' . $_SETT['admin']['name'] . ' <' . $_SETT['admin']['email'] . '>.';
                            }
                        } else {
                            // We have at least one matching transcript.
                            // Remove all the others, and issue warnings for those.
                            // Transcripts that we will use:
                            $sTranscriptsAvailable = implode(', ', $aTranscriptsExisting);
                            // Remove unusable transcripts.
                            foreach (array_diff(array_keys($aTranscripts), array_keys($aTranscriptsExisting)) as $iVariantLevel2) {
                                // Unset, issue warning.
                                unset($aVariant['seq_changes']['variant'][$iVariantLevel2]);
                                $this->API->aResponse['warnings'][] = 'Warning: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Ignoring transcript \'' . $aTranscripts[$iVariantLevel2] . '\', not configured in this LOVD, using ' . $sTranscriptsAvailable . '.';
                            }

                            // Replace RefSeqs with the ones we will use.
                            foreach ($aTranscriptsExisting as $iVariantLevel2 => $sTranscriptAvailable) {
                                $aVariant['seq_changes']['variant'][$iVariantLevel2]['ref_seq']['@accession'] = $sTranscriptAvailable;
                            }

                            // Also update the whole data array.
                            $aInput['lsdb']['individual'][$iIndividual]['variant'][$iVariant]['seq_changes']['variant'] = $aVariant['seq_changes']['variant'];
                        }



                        // Loop variants again. But, only the ones of type cDNA with verified, existing, RefSeqs.
                        // There might be more, with erroneous types or missing ref_seq fields or so.
                        foreach (array_keys($aTranscriptsExisting) as $iVariantLevel2) {
                            $nVariantLevel2 = $iVariantLevel2 + 1; // We start counting at 1, like most humans do.
                            $aVariantLevel2 = $aVariant['seq_changes']['variant'][$iVariantLevel2];

                            // Check name, if present.
                            if (isset($aVariantLevel2['name'])) {
                                if (empty($aVariantLevel2['name']['@scheme']) || empty($aVariantLevel2['name']['#text'])) {
                                    // No scheme or text, no way.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Missing required name @scheme or #text elements.';
                                } elseif (strtolower($aVariantLevel2['name']['@scheme']) != 'hgvs') {
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': Name scheme not understood. ' .
                                        'Currently supported: hgvs.';
                                }
                            }



                            // Check next level of variation (RNA or Protein), if present.
                            if (isset($aVariantLevel2['seq_changes']) && isset($aVariantLevel2['seq_changes']['variant'])) {
                                foreach ($aVariantLevel2['seq_changes']['variant'] as $iVariantLevel3 => $aVariantLevel3) {
                                    $nVariantLevel3 = $iVariantLevel3 + 1; // We start counting at 1, like most humans do.

                                    // Required elements.
                                    foreach (array('@type', 'name') as $sRequiredElement) {
                                        if (!isset($aVariantLevel3[$sRequiredElement]) || !$aVariantLevel3[$sRequiredElement]) {
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Missing required ' . $sRequiredElement . ' element.';
                                        }
                                    }

                                    // Check variant further, if we at least have a type.
                                    if (isset($aVariantLevel3['@type'])) {
                                        // Check types.
                                        if (!in_array($aVariantLevel3['@type'], $this->aDNATypes)) {
                                            // Value not recognized.
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Type code \'' . $aVariantLevel3['@type'] . '\' not recognized. ' .
                                                'Options: ' . implode(', ', $this->aDNATypes) . '.';

                                        } elseif ($aVariantLevel3['@type'] != 'RNA' && $aVariantLevel3['@type'] != 'AA') {
                                            // Third level variant must have type "RNA" or "AA".
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Variant type must be RNA or Protein, indicated by type \'RNA\' or \'AA\', respectively.';
                                        }
                                    }

                                    // Check name, if present.
                                    if (isset($aVariantLevel3['name'])) {
                                        if (empty($aVariantLevel3['name']['@scheme']) || empty($aVariantLevel3['name']['#text'])) {
                                            // No scheme or text, no way.
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Missing required name @scheme or #text elements.';
                                        } elseif (strtolower($aVariantLevel3['name']['@scheme']) != 'hgvs') {
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Name scheme not understood. ' .
                                                'Currently supported: hgvs.';
                                        }
                                    }



                                    // Check next level of variation (only AA allowed, only if this variant was RNA).
                                    if (isset($aVariantLevel3['seq_changes']) && isset($aVariantLevel3['seq_changes']['variant'])) {
                                        // If the previous level was already AA, we should not be seeing any children, actually.

                                        if (isset($aVariantLevel3['@type']) && $aVariantLevel3['@type'] == 'AA' && $aVariantLevel3['seq_changes']['variant']) {
                                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': Variant type is Protein, but child variants have been defined, which is not allowed.';
                                            break; // Continue to the individual's next variant in this level.
                                        }

                                        foreach ($aVariantLevel3['seq_changes']['variant'] as $iVariantLevel4 => $aVariantLevel4) {
                                            $nVariantLevel4 = $iVariantLevel4 + 1; // We start counting at 1, like most humans do.

                                            // Required elements.
                                            foreach (array('@type', 'name') as $sRequiredElement) {
                                                if (!isset($aVariantLevel4[$sRequiredElement]) || !$aVariantLevel4[$sRequiredElement]) {
                                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': SeqChange #' . $nVariantLevel4 . ': Missing required ' . $sRequiredElement . ' element.';
                                                }
                                            }

                                            // Check variant further, if we at least have a type.
                                            if (isset($aVariantLevel4['@type'])) {
                                                // Check types.
                                                if (!in_array($aVariantLevel4['@type'], $this->aDNATypes)) {
                                                    // Value not recognized.
                                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': SeqChange #' . $nVariantLevel4 . ': Type code \'' . $aVariantLevel4['@type'] . '\' not recognized. ' .
                                                        'Options: ' . implode(', ', $this->aDNATypes) . '.';

                                                } elseif ($aVariantLevel4['@type'] != 'AA') {
                                                    // Third level variant must have type "AA".
                                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': SeqChange #' . $nVariantLevel4 . ': Variant type must be Protein, indicated by type \'AA\'.';
                                                }
                                            }

                                            // Check name, if present.
                                            if (isset($aVariantLevel4['name'])) {
                                                if (empty($aVariantLevel4['name']['@scheme']) || empty($aVariantLevel4['name']['#text'])) {
                                                    // No scheme or text, no way.
                                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': SeqChange #' . $nVariantLevel4 . ': Missing required name @scheme or #text elements.';
                                                } elseif (strtolower($aVariantLevel4['name']['@scheme']) != 'hgvs') {
                                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': SeqChange #' . $nVariantLevel3 . ': SeqChange #' . $nVariantLevel4 . ': Name scheme not understood. ' .
                                                        'Currently supported: hgvs.';
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // If we have errors, return false here.
        if ($this->API->aResponse['errors']) {
            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
            return false;
        }

        return true;
    }
}
?>
