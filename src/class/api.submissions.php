<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-11-22
 * Modified    : 2019-08-08
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
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
        'genetic_origin' => array(
            'inherited' => 'Germline',
            'de novo' => 'De novo',
            'somatic' => 'Somatic',
        ),
        'genetic_source' => array(
            'paternal' => '10',
            'maternal' => '20',
        ),
        'genetic_evidence' => array(
            'inferred' => '0',
            'confirmed' => '1',
        ),
        'pathogenicity' => array(
            // 'Unclassified' => '00', // Not allowed for submission.
            'Non-pathogenic' => '10',
            'Probably Not Pathogenic' => '30',
            'Probably Pathogenic' => '70',
            'Pathogenic' => '90',
            'Not Known' => '50',
            'Causative' => '60',
            // 8 => '+*',  // Variant affects function but was not associated with this individual's disease phenotype
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
    // The length of the accessions are checked if source is provided. Should
    //  always be numeric.
    private $aAccessionLengths = array(
        'hpo' => 7,
        'omim' => 6,
    );
    private $aDNATypes = array(
        'DNA',
        'cDNA',
        'RNA',
        'AA'
    );

    // The sections/objects and their columns that we'll use.
    private $aObjects = array(
        'Columns' => array(),
        'Genes' => array(),
        'Transcripts' => array(),
        'Diseases' => array('id', 'symbol', 'name', 'id_omim', 'created_by'),
        'Genes_To_Diseases' => array(),
        'Individuals' => array('id', 'panel_size', 'owned_by', 'statusid', 'created_by', 'Individual/Lab_ID', 'Individual/Gender'),
        'Individuals_To_Diseases' => array('individualid', 'diseaseid'),
        'Phenotypes' => array('id', 'diseaseid', 'individualid', 'owned_by', 'statusid', 'created_by', 'Phenotype/Additional'),
        'Screenings' => array('id', 'individualid', 'variants_found', 'owned_by', 'created_by', 'Screening/Template', 'Screening/Technique'),
        'Screenings_To_Genes' => array(),
        'Variants_On_Genome' => array('id', 'allele', 'effectid', 'chromosome', 'position_g_start', 'position_g_end', 'owned_by', 'statusid', 'created_by', 'VariantOnGenome/DNA', 'VariantOnGenome/DBID', 'VariantOnGenome/Reference'),
        'Variants_On_Transcripts' => array('id', 'transcriptid', 'effectid', 'position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron', 'VariantOnTranscript/DNA', 'VariantOnTranscript/RNA', 'VariantOnTranscript/Protein'),
        'Screenings_To_Variants' => array('screeningid', 'variantid'),
    );

    // The mandatory custom colums in this LOVD, that we'll need to find defaults for.
    // This variable will be filled by addMandatoryColumns() and will have the structure:
    // [category][column ID] = default value;
    private $aMandatoryCustomColumns = array();





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }

        $this->API = $oAPI;
        return true;
    }





    private function addColumn ($sColID)
    {
        // Adds requested column to $this->aObjects, if they're active in LOVD, so that the API can populate the column.
        global $_DB;

        // Don't repeat yourself.
        static $aColumns = array();
        if (isset($aColumns[$sColID])) {
            // We ran before.
            return $aColumns[$sColID];
        }

        // Find column in the database.
        $sSQL = 'SELECT SUBSTRING_INDEX(ac.colid, "/", 1) AS category, ac.colid
                 FROM ' . TABLE_ACTIVE_COLS . ' AS ac
                 WHERE ac.colid = ?';
        if ($zColumn = $_DB->query($sSQL, array($sColID))->fetchAssoc()) {
            // Translate the category to that used in the file.
            // FIXME: Why is there no function for this?
            $sCategory = str_replace('Genomes', 'Genome', str_replace('On', 's_On_', $zColumn['category'] . 's'));

            // Store column in the objects array, so it will defined in the file and entry.
            $this->aObjects[$sCategory][] = $zColumn['colid'];
            $aColumns[$sColID] = true;
        } else {
            $aColumns[$sColID] = false;
        }

        return $aColumns[$sColID];
    }





    private function addMandatoryColumns ()
    {
        // Adds mandatory columns to $this->aObjects, so they'll at least be set.
        // Also try and predict the default value, and store.
        // Then, addMandatoryDefaultValues() should fill those in, per entry.
        global $_DB;

        if ($this->aMandatoryCustomColumns) {
            // We ran before.
            return false;
        }

        // Collect names of custom columns, per category, together with their form type and selection options, since we'll need those.
        // Note that for the shared columns we take c.select_options instead of from sc, because we won't know what all the genes may do.
        // FIXME: For shared columns, it would be more correct to take the select_options from each parent,
        //  and store whether or not the column is mandatory, per parent. This will take more resources (CPU and memory),
        //  but will prevent us from picking default values for columns that do not need default values.
        $sSQL = 'SELECT "global" AS type, SUBSTRING_INDEX(c.id, "/", 1) AS category, c.id, c.select_options
                 FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid)
                 WHERE c.id NOT LIKE "VariantOnTranscript/%" AND c.id NOT LIKE "Phenotype/%" AND c.mandatory = 1
                 UNION
                 SELECT "shared" AS type, SUBSTRING_INDEX(c.id, "/", 1) AS category, c.id, MIN(c.select_options) AS select_options
                 FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (c.id = sc.colid)
                 WHERE (c.id LIKE "VariantOnTranscript/%" OR c.id LIKE "Phenotype/%") AND sc.mandatory = 1
                 GROUP BY c.id';
        foreach ($_DB->query($sSQL)->fetchAllAssoc() as $zColumn) {
            // Translate the category to that used in the file.
            // FIXME: Why is there no function for this?
            $sCategory = str_replace('Genomes', 'Genome', str_replace('On', 's_On_', $zColumn['category'] . 's'));

            // Some values should not get defaults, because they are considered
            //  mandatory by this API, or handled in general by this API.
            // All those columns are defined in the $aObjects array.
            if (in_array($zColumn['id'], $this->aObjects[$sCategory])) {
                continue;
            } else {
                // Store column in the objects array, so it will defined in the file and entry.
                $this->aObjects[$sCategory][] = $zColumn['id'];
            }

            // Try and take the default value from the selection options, if available.
            $sDefaultValue = '';
            $aOptions = explode("\r\n", $zColumn['select_options']);
            foreach (array('Unknown', 'unknown', '?', '-') as $sValue) {
                if (in_array($sValue, $aOptions)) {
                    $sDefaultValue = $sValue;
                    break;
                }
            }

            // FIXME: If the field is not a selection list, we could just put a ?,
            //  but right now we're not handling the shared columns right, and I
            //  don't want default values to end up in non-mandatory fields.
            // For non-shared columns, that surely are a problem, put a '?'.
            if (!$sDefaultValue && $zColumn['type'] == 'global') {
                $sDefaultValue = '?';
            }

            // Store default value, in case we found any.
            $this->aMandatoryCustomColumns[$sCategory][$zColumn['id']] = $sDefaultValue;
        }

        return true;
    }





    private function addMandatoryDefaultValues ($sCategory, &$aData)
    {
        // This function applies default values for an entry, as definied in the
        //  $this->aMandatoryCustomColumns() array.

        if (!isset($this->aMandatoryCustomColumns[$sCategory])) {
            // No mandatory fields defined for this category.
            return false;
        }

        foreach ($this->aMandatoryCustomColumns[$sCategory] as $sField => $sDefaultValue) {
            $aData[$sField] = $sDefaultValue;
        }

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





    private function convertVarioMLToLOVD ($aInput)
    {
        // This function takes the input data and creates an LOVD data array, in
        //  the format as if taken directly from the database.
        // Calling this function assumes the input is properly verified VarioML
        //  format.
        global $_CONF, $_DB, $_SETT;

        // This array will contain all data that is to be returned.
        $aData = array_fill_keys(array_keys($this->aObjects), array());

        // This Disease ID is of the "unclassified" disease (which we will create if it doesn't exist and we need it).
        static $nDiseaseIDUnclassified = false;
        // Array of diseases we have seen already.
        static $aDiseases = array();
        // Array of transcripts we have already seen.
        static $aTranscripts = array();

        // To make sure we can actually import the result, we should check for columns that are mandatory, but not currently supported.
        // We should try and give those defaults. First, identify these columns.
        // This function will annotate $this->aObjects with mandatory columns, so we'll at least have them in the file.
        $this->addMandatoryColumns();

        // Loop VarioML data, fill in $aData array.
        foreach ($aInput['lsdb']['individual'] as $nIndividualKey => $aIndividual) {
            $nIndividualID = $nIndividualKey + 1;
            $aData['Individuals'][$nIndividualKey] = array_fill_keys($this->aObjects['Individuals'], ''); // Instantiate all columns.

            // Apply defaults, only for columns mandatory in this LOVD instance.
            // This function will try and get the default values from LOVD itself.
            $this->addMandatoryDefaultValues('Individuals', $aData['Individuals'][$nIndividualKey]);

            // Map the data.
            $aData['Individuals'][$nIndividualKey]['id'] = $nIndividualID;
            $aData['Individuals'][$nIndividualKey]['panel_size'] = 1; // Defaults to one individual.
            $aData['Individuals'][$nIndividualKey]['owned_by'] = $this->zAuth['id'];
            $aData['Individuals'][$nIndividualKey]['statusid'] = STATUS_PENDING;
            $aData['Individuals'][$nIndividualKey]['created_by'] = $this->zAuth['id'];
            $aData['Individuals'][$nIndividualKey]['Individual/Lab_ID'] = $aIndividual['@id'];
            $aData['Individuals'][$nIndividualKey]['Individual/Gender'] = (!isset($aIndividual['gender'])? '' : $this->aValueMappings['gender'][$aIndividual['gender']['@code']]);



            // Phenotypes; can be both HPO and OMIM entries.
            $nDiseaseIDForHPO = 0; // The disease ID to which HPO terms will be added.
            // Split based on source.
            $aPhenotypes = array('hpo' => array(), 'omim' => array());
            foreach ($aIndividual['phenotype'] as $aPhenotype) {
                $aPhenotypes[strtolower($aPhenotype['@source'])][$aPhenotype['@accession']] = $aPhenotype['@term'];
            }
            // If we have (HPO) phenotypes but no diseases, or if we have phenotypes
            //  and more than one disease, we need to add 'unclassified'.
            if ($aPhenotypes['hpo'] && count($aPhenotypes['omim']) != 1) {
                if (!$nDiseaseIDUnclassified) {
                    // We didn't look for it yet. Find it, and if it's not there, create it.
                    $nDiseaseIDUnclassified = $_DB->query('SELECT id FROM ' . TABLE_DISEASES . ' WHERE symbol = ? AND name LIKE ?', array('?', '%unclassified%'))->fetchColumn();
                    if ($nDiseaseIDUnclassified === false) {
                        // Have the "unclassified" disease created, then.
                        $nDiseaseIDUnclassified = count($aData['Diseases']) + 1;
                        $aData['Diseases'][] = array('id' => $nDiseaseIDUnclassified, 'symbol' => '?', 'name' => 'Unclassified', 'id_omim' => '', 'created_by' => $this->zAuth['id']);
                    }
                }
                $nDiseaseIDForHPO = $nDiseaseIDUnclassified;
            }

            // First handle the diseases.
            foreach ($aPhenotypes['omim'] as $nAccession => $sTerm) {
                // We focus on the OMIM ID and use the term
                //  only when we can't match on the OMIM ID.
                // We can just put it in the file and have LOVD match it, but
                //  LOVD will always issue a warning, and I want to prevent that.
                if (!isset($aDiseases[$nAccession])) {
                    $nDiseaseID = $_DB->query('SELECT id FROM ' . TABLE_DISEASES . ' WHERE id_omim = ? OR (id_omim IS NULL AND name = ?)', array($nAccession, $sTerm))->fetchColumn();
                    if (!$nDiseaseID) {
                        // Disease is not yet in the database. Have it created.
                        $nDiseaseID = count($aData['Diseases']) + 1;
                        // If the term looks like an abbreviation, use that, otherwise use "-".
                        $sSymbol = (preg_match('/^[A-Z0-9-]+$/', $sTerm)? $sTerm : '-');
                        $aData['Diseases'][] = array('id' => $nDiseaseID, 'symbol' => $sSymbol, 'name' => $sTerm, 'id_omim' => $nAccession, 'created_by' => $this->zAuth['id']);
                    }
                    $aDiseases[$nAccession] = $nDiseaseID;
                }
                // Link individual to the disease.
                $aData['Individuals_To_Diseases'][] = array('individualid' => $nIndividualID, 'diseaseid' => $aDiseases[$nAccession]);
                // Also, take individual's first disease, and select it for HPO terms to be added to.
                if (!$nDiseaseIDForHPO) {
                    $nDiseaseIDForHPO = $aDiseases[$nAccession];
                }
            }

            // Then, store phenotypes. Add to the first disease we have attached
            //  to this individual. If there were multiple diseases, we already
            //  handled that by added the "Unclassified" disease first.
            // All HPO phenotypes will be stored as one phenotype entry.
            if ($aPhenotypes['hpo']) {
                $sPhenotype = '';
                foreach ($aPhenotypes['hpo'] as $nAccession => $sTerm) {
                    $sPhenotype .= (!$sPhenotype ? '' : '; ') . $sTerm . ' (HP:' . $nAccession . ')';
                }
                // We're assuming here, that the Phenotype/Additional column is
                //  active. It's an LOVD-standard custom column added to new
                //  diseases by default.
                $aPhenotype = array_fill_keys($this->aObjects['Phenotypes'], ''); // Instantiate all columns.
                $nPhenotypeID = count($aData['Phenotypes']) + 1;

                // Apply defaults, only for columns mandatory in this LOVD instance.
                // This function will try and get the default values from LOVD itself.
                $this->addMandatoryDefaultValues('Phenotypes', $aPhenotype);

                $aData['Phenotypes'][] = array_merge(
                    $aPhenotype,
                    array(
                        'id' => $nPhenotypeID,
                        'diseaseid' => $nDiseaseIDForHPO,
                        'individualid' => $nIndividualID,
                        'owned_by' => $this->zAuth['id'],
                        'statusid' => STATUS_PENDING,
                        'created_by' => $this->zAuth['id'],
                        'Phenotype/Additional' => $sPhenotype,
                    )
                );
            }



            // Loop variants and store them, too.
            foreach ($aIndividual['variant'] as $nVariantKey => $aVariant) {
                // Prepare the VOG that we're building now.
                $aVOG = array_fill_keys($this->aObjects['Variants_On_Genome'], ''); // Instantiate all columns.
                $nVariantID = count($aData['Variants_On_Genome']) + 1;

                // Apply defaults, only for columns mandatory in this LOVD instance.
                // This function will try and get the default values from LOVD itself.
                $this->addMandatoryDefaultValues('Variants_On_Genome', $aVOG);

                // Map the data.
                $aVOG['id'] = $nVariantID;
                $aVOG['allele'] = $this->aValueMappings['@copy_count'][$aVariant['@copy_count']];
                $aVOG['effectid'] = $this->aValueMappings['pathogenicity'][$aVariant['pathogenicity'][0]['@term']];
                $aVOG['chromosome'] = array_search($aVariant['ref_seq']['@accession'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences']);
                $aVOG['owned_by'] = $this->zAuth['id'];
                $aVOG['statusid'] = STATUS_PENDING;
                $aVOG['created_by'] = $this->zAuth['id'];
                $aVOG['VariantOnGenome/DNA'] = $aVariant['name']['#text'];

                // Add genetic_origin, if present. This may also affect the 'allele' field.
                if (isset($aVariant['genetic_origin'])) {
                    $aVOG['VariantOnGenome/Genetic_origin'] = $this->aValueMappings['genetic_origin'][$aVariant['genetic_origin']['@term']];

                    // Find possible source and evidence codes. Evidence codes will be ignored unless there is a source.
                    // Source will be ignored if @copy_count = 2.
                    if (isset($aVariant['genetic_origin']['source']) && $aVariant['@copy_count'] == '1') {
                        $aVOG['allele'] = $this->aValueMappings['genetic_source'][$aVariant['genetic_origin']['source']['@term']];
                        if (isset($aVariant['genetic_origin']['evidence_code'])) {
                            $aVOG['allele'] += $this->aValueMappings['genetic_evidence'][$aVariant['genetic_origin']['evidence_code']['@term']];
                        }
                    }
                }

                // Check if the pathogenicity has a comment, that we need to process.
                if (isset($aVariant['pathogenicity'][0]['comment'])) {
                    foreach ($aVariant['pathogenicity'][0]['comment'] as $aEntries) {
                        if (!is_array($aEntries)) {
                            $aEntries = array($aEntries);
                        }
                        foreach ($aEntries as $aEntry) {
                            if (!is_array($aEntry)) {
                                $aEntry = array($aEntry);
                            }
                            foreach ($aEntry as $sEntry) {
                                // Try to link the Remarks column, if active.
                                if (!isset($aVOG['VariantOnGenome/Remarks']) && $this->addColumn('VariantOnGenome/Remarks')) {
                                    $aVOG['VariantOnGenome/Remarks'] = '';
                                }

                                // Use the Remarks column, but don't overwrite an existing value.
                                if (isset($aVOG['VariantOnGenome/Remarks'])) {
                                    $aVOG['VariantOnGenome/Remarks'] .= (!$aVOG['VariantOnGenome/Remarks']? '' : '\r\n') . $sEntry;
                                } else {
                                    // There is no fallback. I don't like throwing an error,
                                    //  but I have to if I don't want data to be lost.
                                    $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': Pathogenicity: Comment(s) found, but this LOVD doesn\'t have the Remarks column activated. ' .
                                        'Remove your comment or ask the admin to enable the variant\'s Remarks column: ' . $_SETT['admin']['address_formatted'] . '.';
                                    return false;
                                }
                            }
                        }
                    }
                }

                // Fill in the positions. If this fails, this is reason to reject the variant.
                $aVariantInfo = lovd_getVariantInfo($aVariant['name']['#text']);
                if (!$aVariantInfo) {
                    $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': Name not understood. ' .
                        'This does not seem to be correct HGVS nomenclature.';
                    return false;
                } elseif (isset($aVariantInfo['position_start_intron'])) {
                    // Shouldn't happen for genomic variants.
                    $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': Name does not seem to describe a genomic variant. ' .
                        'Variant must be genomic, indicated by \'g.\' or \'m.\'. ' .
                        'Variants of other types can only be specified as children of a genomic variant.';
                    return false;
                }
                $aVOG['position_g_start'] = $aVariantInfo['position_start'];
                $aVOG['position_g_end'] = $aVariantInfo['position_end'];

                // Check for db_xrefs.
                if (isset($aVariant['db_xref'])) {
                    foreach ($aVariant['db_xref'] as $aID) {
                        if (strtolower($aID['@source']) == 'dbsnp') {
                            // Try to link the dbSNP column, if active.
                            if (!isset($aVOG['VariantOnGenome/dbSNP']) && $this->addColumn('VariantOnGenome/dbSNP')) {
                                $aVOG['VariantOnGenome/dbSNP'] = '';
                            }

                            // Use the dbSNP column, but don't overwrite an existing value.
                            if (isset($aVOG['VariantOnGenome/dbSNP']) && (!$aVOG['VariantOnGenome/dbSNP'] || $aVOG['VariantOnGenome/dbSNP'] == $aID['@accession'])) {
                                $aVOG['VariantOnGenome/dbSNP'] = $aID['@accession'];
                            } else {
                                // Use the VariantOnGenome/Reference column, that is HGVS standard
                                //  and therefore should always be active.
                                $aVOG['VariantOnGenome/Reference'] .= (!$aVOG['VariantOnGenome/Reference']? '' : '; ') .
                                    '{dbSNP:' . $aID['@accession'] . '}';
                            }

                        } elseif (strtolower($aID['@source']) == 'pubmed') {
                            // @name can hold text, optional. accession can also be empty (Germany needs that).

                            if (!empty($aID['@accession'])) {
                                $sName = '';
                                if (empty($aID['@name'])) {
                                    // We don't have text to go with our PMID. Fetching that info should be fast.
                                    $sResponse = @join('', lovd_php_file('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=' . $aID['@accession']));
                                    if ($sResponse) {
                                        $aPubMedData = json_decode($sResponse, true);
                                        if (isset($aPubMedData['result'][$aID['@accession']]) && isset($aPubMedData['result'][$aID['@accession']]['authors'])) {
                                            $sName = preg_replace('/ [A-Z]+$/', '', $aPubMedData['result'][$aID['@accession']]['sortfirstauthor']) . ' et al (' .
                                                substr($aPubMedData['result'][$aID['@accession']]['pubdate'], 0, strpos($aPubMedData['result'][$aID['@accession']]['pubdate'] . ' ', ' ')) . ')';
                                        }
                                    }
                                    if (!$sName) {
                                        // It somehow failed. Default to name = "Pubmed".
                                        $sName = 'PubMed';
                                    }
                                } else {
                                    // Accession and name given.
                                    $sName = str_replace(':', ';', $aID['@name']);
                                }
                                $aVOG['VariantOnGenome/Reference'] .= (!$aVOG['VariantOnGenome/Reference']? '' : '; ') .
                                    '{PMID:' . $sName . ':' . $aID['@accession'] . '}';
                            } else {
                                // We don't have an ID...
                                if (!empty($aID['@name'])) {
                                    // We'll use this, then.
                                    $aVOG['VariantOnGenome/Reference'] .= (!$aVOG['VariantOnGenome/Reference']? '' : '; ') .
                                        $aID['@name'];
                                }
                            }
                        }
                    }
                }

                // Check for comments to process.
                if (isset($aVariant['comment'])) {
                    foreach ($aVariant['comment'] as $aEntries) {
                        if (!is_array($aEntries)) {
                            $aEntries = array($aEntries);
                        }
                        foreach ($aEntries as $aEntry) {
                            if (!is_array($aEntry)) {
                                $aEntry = array($aEntry);
                            }
                            foreach ($aEntry as $sEntry) {
                                // Try to link the Remarks column, if active.
                                if (!isset($aVOG['VariantOnGenome/Remarks']) && $this->addColumn('VariantOnGenome/Remarks')) {
                                    $aVOG['VariantOnGenome/Remarks'] = '';
                                }

                                // Use the Remarks column, but don't overwrite an existing value.
                                if (isset($aVOG['VariantOnGenome/Remarks'])) {
                                    $aVOG['VariantOnGenome/Remarks'] .= (!$aVOG['VariantOnGenome/Remarks']? '' : '\r\n') . $sEntry;
                                } else {
                                    // There is no fallback. I don't like throwing an error,
                                    //  but I have to if I don't want data to be lost.
                                    $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': Comment(s) found, but this LOVD doesn\'t have the Remarks column activated. ' .
                                        'Remove your comment or ask the admin to enable the variant\'s Remarks column: ' . $_SETT['admin']['address_formatted'] . '.';
                                    return false;
                                }
                            }
                        }
                    }
                }

                // Build the screening. There can be multiple. We choose to, instead of thinking of something real fancy, to just drop everything in one screening.
                $aTemplates = array();
                $aTechniques = array();
                foreach ($aVariant['variant_detection'] as $aScreening) {
                    $aTemplates[] = $aScreening['@template'];
                    $aTechniques = array_merge($aTechniques, explode(';', $aScreening['@technique']));
                }

                $aScreening = array_fill_keys($this->aObjects['Screenings'], ''); // Instantiate all columns.
                $nScreeningID = count($aData['Screenings']) + 1;

                // Apply defaults, only for columns mandatory in this LOVD instance.
                // This function will try and get the default values from LOVD itself.
                $this->addMandatoryDefaultValues('Screenings', $aScreening);

                // Before we add this screening to the list of screenings, let's see if we're not duplicating screenings.
                // When sending in multiple variants per individual, we'd be repeating the screening information for every variant.
                // Loop the list of screenings. If we find the same one, don't duplicate it.
                $aScreening = array_merge(
                    $aScreening,
                    array(
                        'id' => $nScreeningID,
                        'individualid' => $nIndividualID,
                        'variants_found' => 1,
                        'owned_by' => $this->zAuth['id'],
                        'created_by' => $this->zAuth['id'],
                        'Screening/Template' => implode(';', array_unique($aTemplates)),
                        'Screening/Technique' => implode(';', array_unique($aTechniques)),
                    )
                );
                $bScreeningIsNew = true;
                foreach ($aData['Screenings'] as $aProcessedScreening) {
                    foreach ($aProcessedScreening as $sKey => $sValue) {
                        // We could just do $a == $b, but the 'id' key will always be different.
                        // So, just compare key by key, ignoring the 'id' key.
                        if ($sKey != 'id' && $sValue != $aScreening[$sKey]) {
                            // Found a difference.
                            // Continue to the next screening.
                            continue 2;
                        }
                    }

                    // When we get here, no differences were found. Just one more thing to check.
                    if (array_keys($aProcessedScreening) == array_keys($aScreening)) {
                        // Yup, all fields match.
                        // The screening we see in the data is the same as this one that we previously saw.
                        $nScreeningID = $aProcessedScreening['id'];
                        $bScreeningIsNew = false;
                        break;
                    }
                }

                if ($bScreeningIsNew) {
                    // We didn't find a screening that was the same.
                    $aData['Screenings'][] = $aScreening;
                }

                $aData['Screenings_To_Variants'][] = array(
                    'screeningid' => $nScreeningID,
                    'variantid' => $nVariantID,
                );



                // Check for VOTs.
                if (isset($aVariant['seq_changes']) && isset($aVariant['seq_changes']['variant'])) {
                    // Loop through all VOTs. They've already been checked, so have to be cDNA, [RNA], [AA].
                    foreach ($aVariant['seq_changes']['variant'] as $nVariantLevel2 => $aVariantLevel2) {
                        $nVariantLevel2 ++;
                        $aVOT = array_fill_keys($this->aObjects['Variants_On_Transcripts'], ''); // Instantiate all columns.
                        $aVOT['id'] = $nVariantID; // The VOT that we're building now.

                        // Type must be cDNA, we already checked.
                        // We ignore the gene.

                        // Find the RefSeq. It should have already been checked.
                        if (!isset($aTranscripts[$aVariantLevel2['ref_seq']['@accession']])) {
                            $aTranscripts[$aVariantLevel2['ref_seq']['@accession']] = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($aVariantLevel2['ref_seq']['@accession']))->fetchColumn();
                        }
                        $aVOT['transcriptid'] = $aTranscripts[$aVariantLevel2['ref_seq']['@accession']];

                        // Apply defaults, only for columns mandatory in this LOVD instance.
                        // This function will try and get the default values from LOVD itself.
                        $this->addMandatoryDefaultValues('Variants_On_Transcripts', $aVOT);

                        // Map the data.
                        $aVOT['effectid'] = $aVOG['effectid'];
                        $aVOT['VariantOnTranscript/DNA'] = $aVariantLevel2['name']['#text'];

                        // Fill in the positions. If this fails, this is reason to reject the variant.
                        $aVariantInfo = lovd_getVariantInfo($aVariantLevel2['name']['#text'], $aVOT['transcriptid']);
                        if (!$aVariantInfo) {
                            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': SeqChange #' . $nVariantLevel2 . ': Name not understood. ' .
                                'This does not seem to be correct HGVS nomenclature.';
                            return false;
                        } elseif (!isset($aVariantInfo['position_start_intron'])) {
                            // Shouldn't happen for cDNA variants.
                            $this->API->nHTTPStatus = 422; // Send 422 Unprocessable Entity.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . ($nIndividualKey + 1) . ': Variant #' . ($nVariantKey + 1) . ': SeqChange #' . $nVariantLevel2 . ': Name does not seem to describe a transcriptomic variant. ' .
                                'Variant must be transcriptomic, indicated by \'c.\' or \'n.\'. ' .
                                'Genomic variants can not be children of other variants. ' .
                                'RNA and Protein variants can only be specified as children of a cDNA variant.';
                            return false;
                        }
                        $aVOT['position_c_start'] = $aVariantInfo['position_start'];
                        $aVOT['position_c_start_intron'] = $aVariantInfo['position_start_intron'];
                        $aVOT['position_c_end'] = $aVariantInfo['position_end'];
                        $aVOT['position_c_end_intron'] = $aVariantInfo['position_end_intron'];

                        // For RNA, we need to go to the next level (if it's there).
                        $aRNAs = array(); // We could find more than one!
                        $aProteins = array(); // We could find more than one!

                        if (isset($aVariantLevel2['seq_changes']) && isset($aVariantLevel2['seq_changes']['variant'])) {
                            foreach ($aVariantLevel2['seq_changes']['variant'] as $aVariantLevel3) {
                                if ($aVariantLevel3['@type'] == 'RNA') {
                                    $aRNAs[] = $aVariantLevel3['name']['#text'];
                                } elseif ($aVariantLevel3['@type'] == 'AA') {
                                    $aProteins[] = $aVariantLevel3['name']['#text'];
                                }

                                // We should only find AA still now.
                                if (isset($aVariantLevel3['seq_changes']) && isset($aVariantLevel3['seq_changes']['variant'])) {
                                    foreach ($aVariantLevel3['seq_changes']['variant'] as $aVariantLevel4) {
                                        if ($aVariantLevel4['@type'] == 'AA') {
                                            $aProteins[] = $aVariantLevel4['name']['#text'];
                                        }
                                    }
                                }
                            }
                        }

                        // Store RNA.
                        // Has RNA been checked?
                        $bRNAChecked = false;
                        foreach ($aData['Screenings'] as $aScreening) {
                            if (strpos($aScreening['Screening/Template'], 'RNA') !== false) {
                                $bRNAChecked = true;
                                break;
                            }
                        }
                        if (count($aRNAs) == 1) {
                            // When RNA has not been checked, the RNA field description requires parentheses.
                            $sRNA = (!$bRNAChecked && preg_match('/^r.[^(]/', $aRNAs[0])?
                                'r.(' . substr($aRNAs[0], 2) . ')' :
                                $aRNAs[0]);
                        } elseif (!$aRNAs) {
                            // Default value.
                            $sRNA = 'r.(?)';
                        } else {
                            // Multiple RNA changes...
                            $sRNA = 'r.[';
                            foreach ($aRNAs as $i => $sRNAVariant) {
                                if (substr($sRNAVariant, 0, 2) == 'r.') {
                                    $sRNAVariant = substr($sRNAVariant, 2);
                                }
                                $sRNA .= (!$i? '' : '; ') . $sRNAVariant;
                            }
                            $sRNA .= ']';
                        }
                        $aVOT['VariantOnTranscript/RNA'] = $sRNA;

                        // Store Protein.
                        if (count($aProteins) == 1) {
                            // When RNA has not been checked, the protein description requires parentheses.
                            $sProtein = (!$bRNAChecked && preg_match('/^p.[^(]/', $aProteins[0])?
                                'p.(' . substr($aProteins[0], 2) . ')' :
                                $aProteins[0]);
                        } elseif (!$aProteins) {
                            // Default value depends on transcript. '-' for non-coding transcripts, p.(?) otherwise.
                            if (in_array(substr($aVariantLevel2['ref_seq']['@accession'], 0, 2), array('NR', 'XR'))) {
                                $sProtein = '-';
                            } else {
                                $sProtein = 'p.(?)';
                            }
                        } else {
                            // Multiple Protein changes...
                            $sProtein = 'p.[';
                            foreach ($aProteins as $i => $sProteinVariant) {
                                if (substr($sProteinVariant, 0, 2) == 'p.') {
                                    $sProteinVariant = substr($sProteinVariant, 2);
                                }
                                $sProtein .= (!$i? '' : '; ') . $sProteinVariant;
                            }
                            $sProtein .= ']';
                        }
                        $aVOT['VariantOnTranscript/Protein'] = $sProtein;
                        $aData['Variants_On_Transcripts'][] = $aVOT;
                    }
                }

                $aData['Variants_On_Genome'][] = $aVOG;
            }
        }

        return $aData;
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
        global $_INI, $_SETT;

        // Check if we have data path to write the resulting file to.
        if (!$_INI['paths']['data_files']) {
            // There's no way we can do anything with the results, so fail.
            $this->API->aResponse['errors'][] = 'No data file path configured. Without this path, this LOVD API is not able to process files. ' .
                'Please contact the admin to configure the data file path: ' . $_SETT['admin']['address_formatted'] . '.';
            $this->API->nHTTPStatus = 500; // Send 500 Internal Server Error.
            return false;
        }

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
        $sInputClean = trim(preg_replace('/\/\*.+\*\//Us', '', $sInput));

        // Then, check the first character. Should be an '{'.
        if ($sInputClean{0} != '{') {
            // Can't be JSON...
            $this->API->aResponse['errors'][] = 'Unsupported media type. Expecting: application/json.';
            $this->API->nHTTPStatus = 415; // Send 415 Unsupported Media Type.
            return false;
        }

        // If it appears to be JSON, have PHP try and convert it into an array.
        $aInput = $this->jsonDecode($sInputClean);
        // If $aInput is false, we failed somewhere. Function should have set response and HTTP status.
        if ($aInput === false) {
            return false;
        }

        if (
            // Clean up the JSON, not forcing minimum data requirements yet.
            // This makes traversing the array easier.
            !$this->cleanVarioMLData($aInput) ||

            // Check for minimum data set, and check the data, specific for the VarioML format.
            // This function may alter the data, dropping transcripts that are not found in this LOVD.
            !$this->verifyVarioMLData($aInput)) {
            return false;
        }

        // Convert into an LOVD3 object.
        $aData = $this->convertVarioMLToLOVD($aInput);
        if (!$aData) {
            return false;
        }

        // Debugging:
        // $this->API->aResponse['data'] = $aData;
        // return true;

        // Write the LOVD3 output file (and optionally, the JSON data).
        return $this->writeImportFile($aData, $sInputClean);
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
                'request the LSDB ID from the admin: ' . $_SETT['admin']['address_formatted'] . '.';
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
        $this->zAuth = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ? AND auth_token = ? AND (auth_token_expires > NOW() OR auth_token_expires IS NULL)',
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

        // Fetch and store variant detection techniques.
        $sScreeningTechniques = $_DB->query('SELECT select_options FROM ' . TABLE_COLS . ' WHERE id = ?', array('Screening/Technique'))->fetchColumn();
        $aScreeningTechniques = explode("\r\n", $sScreeningTechniques);
        // Isolate only the option values.
        $aScreeningTechniques = preg_replace('/\s*(=.*)?$/', '', $aScreeningTechniques);


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
                        if (!in_array(strtolower($aPhenotype['@source']), array('hpo', 'omim'))) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Source not understood. ' .
                                'Currently supported: hpo, omim.';
                        } elseif (empty($aPhenotype['@accession'])) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Accession mandatory if source provided.';
                        } elseif (!ctype_digit($aPhenotype['@accession']) || strlen($aPhenotype['@accession']) != $this->aAccessionLengths[strtolower($aPhenotype['@source'])]) {
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Phenotype #' . $nPhenotype . ': Accession not understood. ' .
                                'Expecting ' . $this->aAccessionLengths[strtolower($aPhenotype['@source'])] . ' digits.';
                        }
                    }
                }
            } else {
                $aInput['lsdb']['individual'][$iIndividual]['phenotype'] = array();
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

                    // Check genetic_origin, if present.
                    if (isset($aVariant['genetic_origin'])) {
                        if (empty($aVariant['genetic_origin']['@term'])) {
                            // No term, no way.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Missing required @term element.';
                        } elseif (!isset($this->aValueMappings['genetic_origin'][$aVariant['genetic_origin']['@term']])) {
                            // Value not recognized.
                            $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Term code \'' . $aVariant['genetic_origin']['@term'] . '\' not recognized. ' .
                                'Options: ' . implode(', ', array_keys($this->aValueMappings['genetic_origin'])) . '.';
                        }

                        // Find possible source and evidence codes. Evidence codes will be ignored unless there is a source.
                        // FIXME: When @copy_count is 2, and the variant is homozygous, we ignore the source. Should we let the user know?
                        if (isset($aVariant['genetic_origin']['source'])) {
                            if (empty($aVariant['genetic_origin']['source']['@term'])) {
                                // No term, no way.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Source: Missing required @term element.';
                            } elseif (!isset($this->aValueMappings['genetic_source'][$aVariant['genetic_origin']['source']['@term']])) {
                                // Value not recognized.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Source: Term code \'' . $aVariant['genetic_origin']['source']['@term'] . '\' not recognized. ' .
                                    'Options: ' . implode(', ', array_keys($this->aValueMappings['genetic_source'])) . '.';
                            }

                            if (isset($aVariant['genetic_origin']['evidence_code'])) {
                                if (empty($aVariant['genetic_origin']['evidence_code']['@term'])) {
                                    // No term, no way.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Evidence Code: Missing required @term element.';
                                } elseif (!isset($this->aValueMappings['genetic_evidence'][$aVariant['genetic_origin']['evidence_code']['@term']])) {
                                    // Value not recognized.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Genetic Origin: Evidence Code: Term code \'' . $aVariant['genetic_origin']['evidence_code']['@term'] . '\' not recognized. ' .
                                        'Options: ' . implode(', ', array_keys($this->aValueMappings['genetic_evidence'])) . '.';
                                }
                            }
                        }
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
                    // FIXME: Currently ignoring "evidence_code", which seems to be able to contain "reported" and "concluded".
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
                                    'LOVD only supports: individual.'; // VarioML: individual | family | population.
                            } else {
                                if ($bPathogenicityIndividualScope) {
                                    // We already saw this scope, that's not possible.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': You cannot have more than one Pathogenicity element of the same scope.';
                                } else {
                                    $bPathogenicityIndividualScope = true;
                                }
                                if (!isset($this->aValueMappings['pathogenicity'][$aPathogenicity['@term']])) {
                                    // Value not recognized.
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': Pathogenicity #' . $nPathogenicity . ': Pathogenicity term \'' . $aPathogenicity['@term'] . '\' not recognized. ' .
                                        'Options: ' . implode(', ', array_keys($this->aValueMappings['pathogenicity'])) . '.';
                                }
                            }
                        }
                    }

                    // (temporarily) allow for alternative PubMed info, map to db_xref.
                    // This is undocumented and may be removed later, when our users will adhere to the standards for
                    //  providing PubMed (literature) links.
                    if (isset($aVariant['literature'])) {
                        foreach ($aVariant['literature'] as $aEntries) {
                            if (!is_array($aEntries)) {
                                $aEntries = array($aEntries);
                            }
                            foreach ($aEntries as $aEntry) {
                                if (!is_array($aEntry)) {
                                    $aEntry = array($aEntry);
                                }
                                foreach ($aEntry as $sEntry) {
                                    if (!isset($aVariant['db_xref'])) {
                                        $aVariant['db_xref'] = array();
                                    }
                                    $aVariant['db_xref'][] = array(
                                        '@source' => 'pubmed',
                                        '@name' => $sEntry,
                                    );
                                }
                            }
                        }
                        if (isset($aVariant['db_xref'])) {
                            // We don't measure if it's changed or not, we'll just overwrite.
                            $aInput['lsdb']['individual'][$iIndividual]['variant'][$iVariant]['db_xref'] = $aVariant['db_xref'];
                        }
                        unset($aVariant['literature'], $aInput['lsdb']['individual'][$iIndividual]['variant'][$iVariant]['literature']);
                    }

                    // Check db_xref, if present.
                    if (isset($aVariant['db_xref'])) {
                        foreach ($aVariant['db_xref'] as $iDBXRef => $aID) {
                            $nDBXRef = $iDBXRef + 1; // We start counting at 1, like most humans do.
                            if (!isset($aID['@source'])) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': DB XRef #' . $nDBXRef . ': Missing required @source element.';
                            } elseif (!in_array(strtolower($aID['@source']), array('dbsnp', 'pubmed'))) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': DB XRef #' . $nDBXRef . ': Source not understood. ' .
                                    'Currently supported: dbsnp, pubmed.';
                            } elseif (strtolower($aID['@source']) != 'pubmed' && !isset($aID['@accession'])) {
                                // PubMed is allowed to leave the accession out, as a common reference.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': DB XRef #' . $nDBXRef . ': Missing required @accession element.';
                            } elseif (strtolower($aID['@source']) == 'dbsnp' && !preg_match('/^rs\d+$/', $aID['@accession'])) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': DB XRef #' . $nDBXRef . ': Accession not understood. ' .
                                    'Expecting "rs", followed by one or more digits.';
                            } elseif (strtolower($aID['@source']) == 'pubmed' && !empty($aID['@accession']) && !ctype_digit($aID['@accession'])) {
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': DB XRef #' . $nDBXRef . ': Accession not understood. ' .
                                    'Expecting one or more digits.';
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
                            } else {
                                if (!isset($this->aValueMappings['@template'][$aScreening['@template']])) {
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': VariantDetection #' . $nScreening . ': VariantDetection template \'' . $aScreening['@template'] . '\' not understood. ' .
                                        'Options: ' . implode(', ', array_keys($this->aValueMappings['@template']));
                                }
                                // Compare all the techniques. Here, we'll allow semi-colon separated values, since LOVD stores it like that, too.
                                $aOptions = explode(';', $aScreening['@technique']);
                                foreach ($aOptions as $sOption) {
                                    $sOption = trim($sOption); // Trim whitespace to ensure match independent of whitespace.
                                    if ($sOption && !in_array($sOption, $aScreeningTechniques)) {
                                        $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': VariantDetection #' . $nScreening . ': VariantDetection technique \'' . $sOption . '\' not understood. ' .
                                            'Options: ' . implode(', ', $aScreeningTechniques);
                                    }
                                }
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
                                                list($sTranscriptAvailable, $sTranscriptGene) = $_DB->query('SELECT id_ncbi, geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC LIMIT 1',
                                                    array($sTranscriptNoVersion . '%', $aVariantLevel2['ref_seq']['@accession']))->fetchRow();
                                                if ($sTranscriptAvailable) {
                                                    $aTranscriptsExisting[$iVariantLevel2] = $sTranscriptAvailable;

                                                    // But also check gene. If we have a gene from the JSON file, and we have
                                                    //  that in the database, then it must match this transcript.
                                                    if (isset($aVariantLevel2['gene']['@accession'])
                                                        && $aVariantLevel2['gene']['@accession'] != $sTranscriptGene) {
                                                        $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': SeqChange #' . $nVariantLevel2 . ': ' .
                                                            'Gene source (' . $aVariantLevel2['gene']['@accession'] . ') mismatches with gene (' . $sTranscriptGene . ') attached to given RefSeq accession (' . $aVariantLevel2['ref_seq']['@accession'] . ').';
                                                    }
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
                                        'Please request the admin to create them: ' . $_SETT['admin']['address_formatted'] . '.';
                                } else {
                                    // Genes do exist. Mention which transcripts can then be used.
                                    $sTranscriptsAvailable = implode(', ', $_DB->query('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid IN (?' . str_repeat(', ?', count($aGenesExisting) - 1) . ') ORDER BY id_ncbi', array($aGenesExisting))->fetchAllColumn());
                                    $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': None of the given transcripts for this variant are configured in this LOVD. ' .
                                        'Options for the given genes: ' . $sTranscriptsAvailable . '.';
                                }
                            } else {
                                // No genes were ever given, focus on the transcripts.
                                $this->API->aResponse['errors'][] = 'VarioML error: Individual #' . $nIndividual . ': Variant #' . $nVariant . ': None of the given transcripts for this variant are configured in this LOVD. ' .
                                    'Please request the admin to create them: ' . $_SETT['admin']['address_formatted'] . '.';
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





    private function writeImportFile ($aData, $sJSON = '')
    {
        // This function takes the input file and writes an LOVD3 import file to
        //  the data file path as configured in $_INI['paths']['data_files'].
        // If $sJSON is given, it will write the contents
        //  of that string to a .json file.
        // Calling this function assumes the input is a properly verified LOVD
        //  database array.
        global $_INI, $_SETT;

        // Assuming this file is unique, since we're including the microseconds.
        $sFileName = 'LOVD_API_submission_' . $this->zAuth['id'] . '_' . date('Y-m-d_H:i:s') . substr(microtime(), 1, 7) . '.lovd';

        // Try opening the file.
        $f = @fopen($_INI['paths']['data_files'] . '/' . $sFileName, 'w');
        if (!$f) {
            $this->API->aResponse['errors'][] = 'Could not open file for writing: ' . $sFileName . '.';
            $this->API->nHTTPStatus = 500; // Send 500 Internal Server Error.
            return false;
        }

        // Begin with header.
        fputs($f, '### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
            '## File generated by LOVD API v' . $this->API->nVersion . '; filename=' . $sFileName . "\r\n" .
            '# charset = UTF-8' . "\r\n\r\n");

        // Loop objects, print header and data only if found.
        foreach ($this->aObjects as $sObject => $aColumns) {
            fputs($f, '## ' . $sObject . ' ## Do not remove or alter this header ##' . "\r\n");

            // If we have no columns or no data, continue.
            if (!$aColumns || empty($aData[$sObject])) {
                continue;
            }

            // Print headers.
            fputs($f, '"{{' . implode('}}"' . "\t" . '"{{', $aColumns) . '}}"' . "\r\n");

            // Loop through the data, and the columns, to make sure it all appears in the right order.
            foreach ($aData[$sObject] as $z) {
                // Quote data.
                $z = array_map('addslashes', $z);
                foreach ($aColumns as $nKey => $sCol) {
                    // Prevent notices here, when the columns are added later in the file.
                    if (!isset($z[$sCol])) {
                        $z[$sCol] = '';
                    }
                    // Replace line endings and tabs (they should not be there but oh well), so they don't cause problems with importing.
                    fputs($f, ($nKey? "\t" : '') . '"' . str_replace(array("\r\n", "\r", "\n", "\t"), array('\r\n', '\r', '\n', '\t'), $z[$sCol]) . '"');
                }
                fputs($f, "\r\n");
            }
            fputs($f, "\r\n\r\n");
        }
        fclose($f);

        // Store the JSON too, if we have the data.
        if ($sJSON) {
            $sFileNameJSON = preg_replace('/.lovd$/', '.json', $sFileName);
            if (defined('JSON_PRETTY_PRINT')) {
                // Make the JSON look pretty, if you can.
                // (if it was, it now isn't anymore).
                // JSON_PRETTY_PRINT is available from PHP 5.4.0.
                $sJSON = json_encode(json_decode($sJSON, true), JSON_PRETTY_PRINT);
            }
            @file_put_contents($_INI['paths']['data_files'] . '/' . $sFileNameJSON, $sJSON);
        }

        // Create log entry.
        $sMessage = '';
        foreach (array_keys($this->aObjects) as $sObject) {
            $n = count($aData[$sObject]);
            if ($n && !strpos($sObject, '_To_')) {
                $sMessage .= (!$sMessage ? '' : ', ') . $n . ' ' . $sObject;
            }
        }
        $sMessage = preg_replace('/, ([^,]+)/', " and $1", $sMessage);
        lovd_writeLog('Event', 'API:SubmissionCreate', 'Created LOVD import file ' . $sFileName . ' using LOVD API v' . $this->API->nVersion . ' (' . $sMessage . ')', $this->zAuth['id']);

        $nBytes = filesize($_INI['paths']['data_files'] . '/' . $sFileName);
        $this->API->aResponse['messages'][] = 'Data successfully scheduled for import. Data file name: ' . $sFileName . '. File size: ' . $nBytes . ' bytes.';
        $this->API->nHTTPStatus = 202; // Send 202 Accepted.

        return true;
    }
}
?>
