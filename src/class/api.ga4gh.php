<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-22
 * Modified    : 2021-06-28
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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



class LOVD_API_GA4GH
{
    // This class defines the LOVD API object handling GA4GH Data Connect.

    private $API;                     // The API object.
    private $aURLElements = array();  // The current URL broken in parts.
    private $aTables = array(
        'variants' => array(
            'description' => 'Aggregated variant data, when available also containing information on individuals, their phenotypes, and their other variants.',
            'data_model' => 'https://github.com/VarioML/VarioML/blob/master/json/schemas/v.2.0/variants.json',
            'first_page' => 'data:{{refseq_build}}:chr1',
        ),
    );
    private $aValueMappings = array(
        'gender' => array(
            '' => '0',
            '?' => '0',
            'F' => '2',
            'M' => '1',
            'rF' => '2',
            'rM' => '1',
        ),
        'effect' => array(), // Defined in the constructor.
        'genetic_origin' => array(
            'de novo' => 'de novo',
            'germline' => 'inherited',
            'somatic' => 'somatic',
            'uniparental disomy, maternal allele' => 'inherited',
            'uniparental disomy, paternal allele' => 'inherited',
        ),
        'inheritance_long' => array(
            'unknown' => '',
            'familial' => 'familial',
            'familial autosomal dominant' => 'AD',
            'familial autosomal recessive' => 'AR',
            'familial x-linked dominant' => 'XLD',
            'familial x-linked dominant, male sparing' => 'XL',
            'familial x-linked recessive' => 'XLR',
            'paternal y-linked' => 'YL',
            'maternal mitochondrial' => 'Mi',
            'isolated (sporadic)' => 'IC',
            'complex' => 'Mu',
        ),
        'inheritance' => array(
            'AD' => array(
                'term' => 'autosomal dominant',
                'source' => 'HPO',
                'accession' => '0000006',
            ),
            'PI' => array(
                'term' => 'autosomal dominant with paternal imprinting',
                'source' => 'HPO',
                'accession' => '0012274',
            ),
            'MI' => array(
                'term' => 'autosomal dominant with maternal imprinting',
                'source' => 'HPO',
                'accession' => '0012275',
            ),
            'AR' => array(
                'term' => 'autosomal recessive',
                'source' => 'HPO',
                'accession' => '0000007',
            ),
            // FIXME: We have taken these two terms from OMIM,
            //  but OMIM doesn't have accession numbers or URIs for them.
            // HPO has "digenic" (0010984), which we currently don't have.
            'DD' => array(
                'term' => 'digenic dominant',
                'source' => 'OMIM',
            ),
            'DR' => array(
                'term' => 'digenic recessive',
                'source' => 'OMIM',
            ),
            'IC' => array(
                'term' => 'sporadic',
                'source' => 'HPO',
                'accession' => '0003745',
            ),
            'Mi' => array(
                'term' => 'mitochondrial',
                'source' => 'HPO',
                'accession' => '0001427',
            ),
            'Mu' => array(
                'term' => 'multifactorial',
                'source' => 'HPO',
                'accession' => '0001426',
            ),
            'SMo' => array(
                'term' => 'somatic mosaicism',
                'source' => 'HPO',
                'accession' => '0001442',
            ),
            'SMu' => array(
                'term' => 'somatic',
                'source' => 'HPO',
                'accession' => '0001428',
            ),
            'OG' => array(
                'term' => 'oligogenic',
                'source' => 'HPO',
                'accession' => '0010983',
            ),
            'PG' => array(
                'term' => 'polygenic',
                'source' => 'HPO',
                'accession' => '0010982',
            ),
            'XL' => array(
                'term' => 'X-linked',
                'source' => 'HPO',
                'accession' => '0001417',
            ),
            'XLD' => array(
                'term' => 'X-linked dominant',
                'source' => 'HPO',
                'accession' => '0001423',
            ),
            'XLR' => array(
                'term' => 'X-linked recessive',
                'source' => 'HPO',
                'accession' => '0001419',
            ),
            'YL' => array(
                'term' => 'Y-linked',
                'source' => 'HPO',
                'accession' => '0001450',
            ),
        ),
    );
    private $bAuthorized = false;
    private $bVarCache = false;





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.
        global $_SETT;

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }
        $this->API = $oAPI;

        // If we're being called by varcache.
        $aHeaders = getallheaders();
        $this->bVarCache = (isset($aHeaders['User-Agent'])
            && substr($aHeaders['User-Agent'], 0, 9) == 'varcache/');

        // To not duplicate code.
        $this->aValueMappings['effect'] = $_SETT['var_effect_api'];

        return true;
    }





    private function addComment ($aComments, $Text)
    {
        // Adds a comment to an existing array of comments.

        if (!is_array($Text)) {
            $Text = array(
                'value' => $Text
            );
        }

        $aComments[] = array(
            'texts' => array(
                $Text,
            ),
        );

        return $aComments;
    }





    private function convertContactToVML ($sRole, $sContact)
    {
        // Converts contact string into VarioML contact data.

        list($sORCID, $sName, $sEmail) = explode('##', $sContact);
        $aEmails = explode("\r\n", $sEmail);

        $aReturn = array(
            'role' => $sRole,
            'name' => $sName,
            'email' => (count($aEmails) == 1? $sEmail : $aEmails),
        );
        if ($sORCID) {
            $aReturn['db_xrefs'] = array(
                array(
                    'source' => 'orcid',
                    'accession' => $sORCID,
                )
            );
        }

        return $aReturn;
    }





    private function convertEffectsToVML ($sEffects)
    {
        // Converts variant effects into VarioML contact data.

        $aReturn = array();
        foreach (explode(';', $sEffects) as $sIDEffect) {
            if ($sIDEffect) {
                list($nID, $nEffectID) = explode(':', $sIDEffect);
                if ($nEffectID{0}) {
                    $aReturn[$nID] = array(
                        'scope' => 'individual', // Always the same for us.
                        'term' => $this->aValueMappings['effect'][(int) $nEffectID{0}],
                        'data_source' => array(
                            'name' => 'submitter',
                        ),
                    );
                }
                if ($nEffectID{1}) {
                    $aReturn[$nID] = array(
                        'scope' => 'individual', // Always the same for us.
                        'term' => $this->aValueMappings['effect'][(int) $nEffectID{1}],
                        'data_source' => array(
                            'name' => 'curator',
                        ),
                    );
                }
            }
        }

        return $aReturn;
    }





    private function convertLicenseToVML ($sLicense)
    {
        // Converts license string into VarioML license data.
        global $_SETT;
        $aReturn = array();

        list($sLicense, $bLOVDPermission) = explode(';', $sLicense);
        if (substr($sLicense, 0, 3) == 'cc_') {
            $sLicenseCode = substr($sLicense, 3, -4);
            $sLicenseVersion = substr($sLicense, -3);
            $aReturn['sharing_policy'] = array(
                'type' => ($sLicenseCode == 'by'? 'OpenAccess' : 'RestrictedAccess'),
                'use_permission' => array(
                    'term' => $_SETT['licenses'][$sLicense],
                    'source' => 'CC',
                    'accession' => $sLicense,
                    'uri' => 'https://creativecommons.org/licenses/' . $sLicenseCode . '/' . $sLicenseVersion,
                ),
            );

            if ($bLOVDPermission && $this->bVarCache) {
                // We need to indicate to varcache that they have access,
                // but only when varcache is calling us.
                $aReturn['sharing_policy']['comments'] = array(
                    array(
                        'texts' => array(
                            array(
                                'value' => 'Additional permissions for LOVD project.',
                            ),
                        ),
                    )
                );
            }
            return $aReturn;
        }

        return false;
    }





    private function convertReferenceToVML ($sReference, $aOptions = array())
    {
        // Converts reference string into VarioML DbXRef data.
        if (!$aOptions) {
            $aOptions = array('dbsnp', 'doi', 'pubmed');
        }
        $aReturn = array();

        foreach (explode(';', str_replace('}', '};', $sReference)) as $sRef) {
            $sRef = trim($sRef);
            if ($sRef) {
                if (preg_match('/^\{PMID:([^}]+):([0-9]+)\}$/', $sRef, $aRegs)
                    && in_array('pubmed', $aOptions)) {
                    $aReturn[] =
                        array(
                            'source' => 'pubmed',
                            'accession' => $aRegs[2],
                            'name' => $aRegs[1],
                        );
                } elseif (preg_match('/^\{DOI:([^}]+):([^:}]+)\}$/', $sRef, $aRegs)
                    && in_array('doi', $aOptions)) {
                    $aReturn[] =
                        array(
                            'source' => 'doi',
                            'accession' => $aRegs[2],
                            'name' => $aRegs[1],
                        );
                } elseif (preg_match('/^\{dbSNP:(rs[0-9]+)\}$/', $sRef, $aRegs)
                    && in_array('dbsnp', $aOptions)) {
                    $aReturn[] =
                        array(
                            'source' => 'dbsnp',
                            'accession' => $aRegs[1],
                        );
                }
            }
        }

        if ($aReturn) {
            return $aReturn;
        }

        return false;
    }





    public function processGET ($aURLElements)
    {
        // Handle GET requests for GA4GH Data Connect.
        global $_SETT, $_STAT;

        // We currently require authorization. This needs to be sent over an
        //  Authorization HTTP request header.
        $aHeaders = getallheaders();
        if (!isset($aHeaders['Authorization']) || substr($aHeaders['Authorization'], 0, 7) != 'Bearer ') {
            $this->API->nHTTPStatus = 401; // Send 401 Unauthorized.
            $this->API->aResponse = array('errors' => array(
                'title' => 'Access denied.',
                'detail' => 'Please provide authorization for this resource. To request access, contact the admin: ' . $_SETT['admin']['address_formatted'] . '.'));
            return false;

        } else {
            $sToken = substr($aHeaders['Authorization'], 7);
            if ($sToken != md5($_STAT['signature'])) {
                $this->API->nHTTPStatus = 401; // Send 401 Unauthorized.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Access denied.',
                    'detail' => 'The given token is not correct. To request access, contact the admin: ' . $_SETT['admin']['address_formatted'] . '.'));
                return false;
            }
        }
        $this->bAuthorized = true;
        $this->bVarCache = ($this->bVarCache && $this->bAuthorized);

        $this->aURLElements = array_pad($aURLElements, 3, '');

        // No further elements given, then forward to the table list.
        if (!implode('', $this->aURLElements)) {
            $this->API->nHTTPStatus = 302; // Send 302 Moved Temporarily (302 Found in HTTP 1.1).
            $this->API->aResponse['messages'][] = 'Location: ' . lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/tables';
            return true;
        }

        // Check URL structure.
        if (count($this->aURLElements) > 3
            || ($this->aURLElements[0] == 'tables' && $aURLElements[1])) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse = array('errors' => array('title' => 'Could not parse requested URL.'));
            return false;
        } elseif ($this->aURLElements[0] == 'table' && !isset($this->aTables[$this->aURLElements[1]])) {
            $this->API->nHTTPStatus = 404; // Send 404 Not Found.
            $this->API->aResponse = array('errors' => array(
                    'title' => 'Table name not recognized.',
                    'detail' => 'Table name not recognized. Choose from: \'' . implode("', '", array_keys($this->aTables)) . '\'.'));
            return false;
        }

        // If table is given but no choice is made between info or data, forward to data.
        if ($this->aURLElements[0] == 'table' && !$this->aURLElements[2]) {
            $this->API->nHTTPStatus = 302; // Send 302 Moved Temporarily (302 Found in HTTP 1.1).
            $this->API->aResponse['messages'][] = 'Location: ' . lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $this->aURLElements[1] . '/data';
            return true;
        }

        // Check URL structure some more.
        if ($this->aURLElements[0] == 'table'
            && !in_array($this->aURLElements[2], array('info', 'data'))
            && !preg_match('/^data:hg[0-9]{2}(:chr([XYM]|[0-9]{1,2})(:[0-9]+)?)?$/', $this->aURLElements[2])) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse = array('errors' => array('title' => 'Could not parse requested URL.'));
            return false;
        }

        // Now actually handle the request.
        if ($aURLElements[0] == 'tables') {
            return $this->showTables();
        } elseif ($aURLElements[0] == 'table' && $aURLElements[2] == 'info') {
            return $this->showTableInfo($aURLElements[1]);
        } elseif ($aURLElements[0] == 'table' && $aURLElements[2] == 'data') {
            return $this->showTableData($aURLElements[1]);
        } elseif ($aURLElements[0] == 'table' && preg_match('/^data:(hg[0-9]{2})(?::chr([XYM]|[0-9]{1,2})(?::([0-9]+))?)?$/', $aURLElements[2], $aRegs)) {
            return $this->showTableDataPage($aURLElements[1], $aRegs);
        }

        // If we end up here, we didn't handle the request well.
        return false;
    }





    private function showTableData ($sTableName)
    {
        // Shows table data view, first page only.
        // This doesn't actually list data yet, it's easier for us to implement
        //  it through pagination.
        global $_CONF;

        $aOutput = array(
            'data_model' => array(
                '$ref' => $this->aTables[$sTableName]['data_model'],
            ),
            'data' => array(),
            'pagination' => array(
                'next_page_url' => lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $sTableName . '/' .
                    rawurlencode(str_replace('{{refseq_build}}', $_CONF['refseq_build'], $this->aTables[$sTableName]['first_page'])),
            ),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showTableDataPage ($sTableName, $aPage)
    {
        // Shows table data page.
        global $_CONF, $_SETT;

        if ($sTableName == 'variants') {
            list(, $sBuild, $sChr, $nPosition) = array_pad($aPage, 4, '1');

            if ($sBuild != $_CONF['refseq_build']) {
                // We don't support this yet, because we can't use an index on a
                //  search on the other genome build. Wait for LOVD 3.1.
                $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
                if (!isset($_SETT['human_builds'][$sBuild])) {
                    $this->API->aResponse = array('errors' => array('title' => 'Unrecognized genome build.'));
                } else {
                    $this->API->aResponse = array('errors' => array('title' => 'Unsupported genome build.'));
                }
                $this->API->aResponse['errors']['detail'] = 'We can not use genome build ' . $sBuild . '. Please choose from: \'' . $_CONF['refseq_build'] . '\'.';
                return false;

            } elseif (!isset($_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr])) {
                // We don't know this chromosome.
                $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Unrecognized chromosome.',
                    'detail' => 'Unrecognized chromosome. Choose from: \'' . implode("', '", array_keys($_SETT['human_builds'][$sBuild]['ncbi_sequences'])) . '\'.'));
                return false;
            }

            return $this->showVariantDataPage($sBuild, $sChr, $nPosition);
        }

        return false;
    }





    private function showTableInfo ($sTableName)
    {
        // Shows table info.
        // Let's not make this too hard, the data model is on GitHub.

        $aOutput = array(
            'name' => $sTableName,
            'description' => $this->aTables[$sTableName]['description'],
            'data_model' => array(
                '$ref' => $this->aTables[$sTableName]['data_model'],
            ),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showTables ()
    {
        // Shows all tables in GA4GH Data Connect.

        $aOutput = array(
            'tables' => array(),
        );
        foreach ($this->aTables as $sTable => $aTable) {
            $aOutput['tables'][] = array(
                'name' => $sTable,
                'description' => $aTable['description'],
                'data_model' => array(
                    '$ref' => $aTable['data_model'],
                ),
            );
        }

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showVariantDataPage ($sBuild, $sChr, $nPosition)
    {
        // Shows variant data page.
        global $_DB;
        $sTableName = 'variants';
        $nLimit = 1000; // Get 1000 variants max in one go.

        // Check for required, and wanted columns.
        $aRequiredCols = array(
            'VariantOnGenome/DNA',
            'VariantOnTranscript/DNA',
            'VariantOnTranscript/RNA',
            'VariantOnTranscript/Protein',
        );
        $aColsToCheck = array_merge($aRequiredCols, array(
            'Individual/Gender',
            'Individual/Reference',
            'Individual/Remarks',
            'Phenotype/Additional',
            'Phenotype/Inheritance',
            'VariantOnGenome/DNA/hg38',
            'VariantOnGenome/ClinicalClassification',
            'VariantOnGenome/dbSNP',
            'VariantOnGenome/Genetic_origin',
            'VariantOnGenome/Reference',
            'VariantOnGenome/Remarks',
        ));
        // Select columns only if they're *globally* set to public.
        // Note that this means, for VOT and Phenotype columns, the gene- and
        //  disease-specific settings are ignored.
        $aCols = $_DB->query('
            SELECT colid
            FROM ' . TABLE_ACTIVE_COLS . ' AS ac
              INNER JOIN ' . TABLE_COLS . ' AS c ON (ac.colid = c.id)
            WHERE c.id IN (?' . str_repeat(', ?', count($aColsToCheck) - 1) . ')
              AND c.public_view = 1', $aColsToCheck)->fetchAllColumn();

        foreach ($aRequiredCols as $sCol) {
            if (!in_array($sCol, $aCols)) {
                $this->API->nHTTPStatus = 500; // Send 500 Internal Server Error.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Missing required columns.',
                    'detail' => 'Missing required columns; this LOVD instance is missing one or more columns required for this API to operate. Required fields: \'' . implode("', '", $aRequiredCols) . '\'.'));
                return false;
            }
        }
        $bIndGender = in_array('Individual/Gender', $aCols);
        $bIndReference = in_array('Individual/Reference', $aCols);
        $bIndRemarks = in_array('Individual/Remarks', $aCols);
        $bPhenotypeAdditional = in_array('Phenotype/Additional', $aCols);
        $bPhenotypeInheritance = in_array('Phenotype/Inheritance', $aCols);
        $bDNA38 = in_array('VariantOnGenome/DNA/hg38', $aCols);
        $bdbSNP = in_array('VariantOnGenome/dbSNP', $aCols);
        $bGeneticOrigin = in_array('VariantOnGenome/Genetic_origin', $aCols);
        $bVOGReference = in_array('VariantOnGenome/Reference', $aCols);
        $bVOGRemarks = in_array('VariantOnGenome/Remarks', $aCols);

        // Not all data can be shown in full. Only data licensed freely can have
        //  its details shared, all other data can only show summary data.
        $aLicenses = array(
            '' => 0, // Not having a license selected, summary data only.
            'cc_by_4.0;0' => 1,
            'cc_by_4.0;1' => 1,
            'cc_by-nc_4.0;0' => 1,
            'cc_by-nc_4.0;1' => 1,
            'cc_by-nc-nd_4.0;0' => 0, // We can not show details of ND licenses.
            'cc_by-nc-nd_4.0;1' => (int) ($this->bVarCache),
            'cc_by-nc-sa_4.0;0' => 1,
            'cc_by-nc-sa_4.0;1' => 1,
            'cc_by-nd_4.0;0' => 0, // We can not show details of ND licenses.
            'cc_by-nd_4.0;1' => (int) ($this->bVarCache),
            'cc_by-sa_4.0;0' => 1,
            'cc_by-sa_4.0;1' => 1,
        );
        // Now what licenses will only allow us to return summary data?
        $aLicensesSummaryData = array_keys(array_diff($aLicenses, array(1)));

        // Fetch data. We do this in two steps; first the basic variant
        //  information and after that the full submission data.
        $sQ = 'SELECT
                 vog.chromosome,
                 vog.position_g_start,
                 vog.position_g_end,
                 GROUP_CONCAT(vog.id SEPARATOR ";") AS ids,
                 vog.`VariantOnGenome/DNA` AS DNA' .
            (!$bDNA38? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/DNA/hg38`, "") ORDER BY vog.`VariantOnGenome/DNA/hg38` SEPARATOR ";") AS DNA38') .
            (!$bdbSNP? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/dbSNP`, "") ORDER BY vog.`VariantOnGenome/dbSNP` SEPARATOR ";") AS dbSNP') .
            (!$bVOGReference? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/Reference`, "") ORDER BY vog.`VariantOnGenome/Reference` SEPARATOR ";") AS refs') . ',
                 GROUP_CONCAT(DISTINCT t.geneid ORDER BY t.geneid SEPARATOR ";") AS genes,
                 GROUP_CONCAT(DISTINCT
                   IFNULL(i.id,
                     CONCAT(vog.id, "||", IFNULL(uc.default_license, ""), "||"' .
            (!$bDNA38? '' : ',
                       IFNULL(vog.`VariantOnGenome/DNA/hg38`, "")') . ', "||"' .
            (!$bdbSNP? '' : ',
                       IFNULL(vog.`VariantOnGenome/dbSNP`, "")') . ', "||"' .
            (!$bVOGReference? '' : ',
                       IFNULL(vog.`VariantOnGenome/Reference`, "")') . ', "||"' .
            (!$bVOGRemarks? '' : ',
                       IFNULL(vog.`VariantOnGenome/Remarks`, "")') . ', "||",
                       IFNULL(
                         (SELECT
                            GROUP_CONCAT(
                              CONCAT(
                                t.geneid, "##", t.id_ncbi, "##", vot.`VariantOnTranscript/DNA`, "##", vot.`VariantOnTranscript/RNA`, "##", vot.`VariantOnTranscript/Protein`)
                              SEPARATOR "$$")
                         FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot
                           INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                         WHERE vot.id = vog.id), ""), "||",
                       IFNULL(
                         CONCAT(
                           IFNULL(uc.orcid_id, ""), "##", uc.name, "##", uc.email
                         ), ""), "||",
                       IFNULL(
                         CONCAT(
                           IFNULL(uo.orcid_id, ""), "##", uo.name, "##", uo.email
                         ), "")
                     )
                   ) SEPARATOR ";;") AS variants
               FROM ' . TABLE_VARIANTS . ' AS vog
                 LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)
                 LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                 LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                 LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)
                 LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id AND i.statusid >= ?)
                 LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id)
                 LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id)
               WHERE vog.chromosome = ? AND vog.position_g_start >= ? AND vog.statusid >= ?';
        $aQ = array(
            STATUS_MARKED,
            (string) $sChr,
            (int) $nPosition,
            STATUS_MARKED
        );
        // FIXME: This is where searching will be implemented.
        $sQ .= '
               GROUP BY vog.chromosome, vog.position_g_start, vog.position_g_end, vog.`VariantOnGenome/DNA`
               ORDER BY vog.chromosome, vog.position_g_start, vog.position_g_end, vog.`VariantOnGenome/DNA`
               LIMIT ' . $nLimit;
        $zData = $_DB->query($sQ, $aQ)->fetchAllAssoc();
        $n = count($zData);



        // Make all transformations.
        $aData = array_map(function ($zData)
        use (
            $aLicenses, $aLicensesSummaryData, $sBuild, $sChr,
            $bdbSNP, $bDNA38, $bGeneticOrigin,
            $bIndGender, $bIndReference, $bIndRemarks,
            $bPhenotypeAdditional, $bPhenotypeInheritance,
            $bVOGReference, $bVOGRemarks)
        {
            global $_DB, $_SETT;

            // Format fields for VarioML-JSON payload.
            $aReturn = array(
                'type' => 'DNA',
                'genes' => array(),
                'ref_seq' => array(
                    'source' => 'genbank',
                    'accession' => $_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr],
                ),
                'name' => array(
                    'scheme' => 'HGVS',
                    'value' => $zData['DNA'],
                ),
                'aliases' => array(),
                'pathogenicities' => array(),
                'panel' => array(
                    'individuals' => array(),
                    'panels' => array(),
                    'variants' => array(),
                ),
            );

            if (!empty($zData['dbSNP'])) {
                $aReturn['db_xrefs'] = array_map(
                    function ($sRSID) {
                        return array(
                            'source' => 'dbsnp',
                            'accession' => $sRSID,
                        );
                    }, explode(';', $zData['dbSNP']));
            }

            if (!empty($zData['refs'])) {
                $aRefs = $this->convertReferenceToVML($zData['refs'], array('dbsnp'));
                if ($aRefs) {
                    if (!isset($aReturn['db_xrefs'])) {
                        $aReturn['db_xrefs'] = array();
                    }
                    // Merge, unique, and reset the array.
                    // We need to rebuild the keys to prevent
                    //  a JSON array from becoming a JSON object.
                    $aReturn['db_xrefs'] = array_values(
                        array_unique(
                            array_merge(
                                $aReturn['db_xrefs'],
                                $aRefs),
                            SORT_REGULAR)
                    );
                }
            }

            if (!empty($zData['genes'])) {
                $aReturn['genes'] = array_map(
                    function ($sSymbol) {
                        return array(
                            'source' => 'HGNC',
                            'accession' => $sSymbol,
                        );
                    }, explode(';', $zData['genes']));
            }

            if (!empty($zData['DNA38'])) {
                foreach (explode(';', $zData['DNA38']) as $sDNA38) {
                    $aReturn['aliases'][] = array(
                        'ref_seq' => array(
                            'source' => 'genbank',
                            'accession' => $_SETT['human_builds']['hg38']['ncbi_sequences'][$sChr],
                        ),
                        'name' => array(
                            'scheme' => 'HGVS',
                            'value' => $sDNA38,
                        ),
                    );
                }
            } else {
                unset($aReturn['aliases']);
            }

            // Further annotate the entries.
            $aSubmissions = array();
            foreach (explode(';;', $zData['variants']) as $sVariant) {
                if (ctype_digit($sVariant)) {
                    // An Individual ID. We don't know yet whether this is an
                    //  Individual or a Panel.
                    $aSubmissions[] = $sVariant;
                } else {
                    // Full variant data, which means there was no Individual.
                    list($nID, $sLicense, $sDNA38, $sRSID, $sRefs, $sRemarks, $sVOTs, $sCreator, $sOwner) = explode('||', $sVariant);

                    // Ignore the full variant entry when the license isn't
                    //  compatible; we're not allowed to show the details then.
                    if (empty($aLicenses[$sLicense])) {
                        // License isn't set (shouldn't happen) or is set to 0
                        //  (= don't share details).
                        continue;
                    }

                    $aVariant = array(
                        'id' => $nID,
                        'type' => 'DNA',
                        'ref_seq' => array(
                            'source' => 'genbank',
                            'accession' => $_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr],
                        ),
                        'name' => array(
                            'scheme' => 'HGVS',
                            'value' => $zData['DNA'],
                        ),
                        'aliases' => (!$sDNA38? array() : array(
                            array(
                                'ref_seq' => array(
                                    'source' => 'genbank',
                                    'accession' => $_SETT['human_builds']['hg38']['ncbi_sequences'][$sChr],
                                ),
                                'name' => array(
                                    'scheme' => 'HGVS',
                                    'value' => $sDNA38,
                                ),
                            ),
                        )),
                        'pathogenicities' => array(),
                    );

                    if (!$aVariant['aliases']) {
                        unset($aVariant['aliases']);
                    }

                    if ($sRemarks) {
                        $aVariant['comments'] = $this->addComment(array(), $sRemarks);
                    }

                    if ($sRSID) {
                        $aVariant['db_xrefs'] = array(
                            array(
                                'source' => 'dbsnp',
                                'accession' => $sRSID,
                            ),
                        );
                    }

                    if ($sRefs) {
                        $aRefs = $this->convertReferenceToVML($sRefs);
                        if ($aRefs) {
                            if (!isset($aVariant['db_xrefs'])) {
                                $aVariant['db_xrefs'] = array();
                            }
                            // Merge, unique, and reset the array.
                            // We need to rebuild the keys to prevent
                            //  a JSON array from becoming a JSON object.
                            $aVariant['db_xrefs'] = array_values(
                                array_unique(
                                    array_merge(
                                        $aVariant['db_xrefs'],
                                        $aRefs),
                                    SORT_REGULAR)
                            );
                        }
                    }

                    if ($sVOTs) {
                        $aVariant['seq_changes']['variants'] = array();
                        foreach (explode('$$', $sVOTs) as $sVOT) {
                            list($sGene, $sRefSeq, $sDNA, $sRNA, $sProtein) = explode('##', $sVOT);
                            $aVariant['seq_changes']['variants'][] = array(
                                'type' => 'cDNA',
                                'gene' => array(
                                    'source' => 'HGNC',
                                    'accession' => $sGene,
                                ),
                                'ref_seq' => array(
                                    'source' => 'genbank',
                                    'accession' => $sRefSeq,
                                ),
                                'name' => array(
                                    'scheme' => 'HGVS',
                                    'value' => $sDNA,
                                ),
                                'seq_changes' => array(
                                    'variants' => array(
                                        array(
                                            'type' => 'RNA',
                                            'name' => array(
                                                'scheme' => 'HGVS',
                                                'value' => $sRNA,
                                            ),
                                            'seq_changes' => array(
                                                'variants' => array(
                                                    array(
                                                        'type' => 'AA',
                                                        'name' => array(
                                                            'scheme' => 'HGVS',
                                                            'value' => $sProtein,
                                                        ),
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            );
                        }
                    }

                    // Data creator and owner.
                    foreach (
                        array(
                            array(
                                $sCreator,
                                'submitter',
                            ),
                            array(
                                $sOwner,
                                'owner',
                            )
                        ) as $aContact) {
                        list($sContact, $sRole) = $aContact;
                        if ($sContact) {
                            $aContact = $this->convertContactToVML($sRole, $sContact);

                            if (!isset($aVariant['data_source'])) {
                                $aVariant['data_source'] = array(
                                    'contacts' => array(),
                                );
                            }
                            $aVariant['data_source']['contacts'][] = $aContact;
                        }
                    }

                    // Data licensing, if known.
                    if ($sLicense) {
                        $aLicense = $this->convertLicenseToVML($sLicense);
                        if ($aLicense) {
                            $aVariant = array_merge($aVariant, $aLicense);
                        }
                    }
                    $aReturn['panel']['variants'][] = $aVariant;
                }
            }

            if ($aSubmissions) {
                $aSubmissions = $_DB->query('
                    SELECT i.id, i.panel_size' .
                    (!$bIndGender? '' : ',
                      i.`Individual/Gender` AS gender') . ',
                      GROUP_CONCAT(DISTINCT IFNULL(d.id_omim, ""), "||", IFNULL(d.inheritance, ""), "||", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, CONCAT(d.name, " (", d.symbol, ")")) ORDER BY d.id_omim, d.name SEPARATOR ";;") AS diseases' .
                    (!$bIndReference? '' : ',
                      i.`Individual/Reference` AS reference') .
                    (!$bIndRemarks? '' : ',
                      i.`Individual/Remarks` AS remarks') .
                    (!$bPhenotypeAdditional? '' : ',
                      GROUP_CONCAT(DISTINCT ' .
                        (!$bPhenotypeInheritance? '' : 'REPLACE(p.`Phenotype/Inheritance`, ",", ""), ') . '"||",
                        IFNULL(p.`Phenotype/Additional`, "") SEPARATOR ";;") AS phenotypes') . ',
                      CONCAT(
                        IFNULL(uc.orcid_id, ""), "##", uc.name, "##", uc.email
                      ) AS creator,
                      CONCAT(
                        IFNULL(uo.orcid_id, ""), "##", uo.name, "##", uo.email
                      ) AS owner,
                      IFNULL(i.license, uc.default_license) AS license,
                      GROUP_CONCAT(DISTINCT
                        CONCAT(
                          vog.id, "||",
                          vog.allele, "||",
                          vog.chromosome, "||",
                          vog.`VariantOnGenome/DNA`, "||"' .
                    (!$bDNA38? '' : ',
                          IFNULL(vog.`VariantOnGenome/DNA/hg38`, "")') . ', "||"' .
                    (!$bGeneticOrigin? '' : ',
                          IFNULL(LOWER(vog.`VariantOnGenome/Genetic_origin`), "")') . ', "||"' .
                    (!$bdbSNP? '' : ',
                          IFNULL(vog.`VariantOnGenome/dbSNP`, "")') . ', "||"' .
                    (!$bVOGReference? '' : ',
                          IFNULL(vog.`VariantOnGenome/Reference`, "")') . ', "||"' .
                    (!$bVOGRemarks? '' : ',
                          IFNULL(vog.`VariantOnGenome/Remarks`, "")') . ', "||",
                          IFNULL(s.`Screening/Template`, ""), "||",
                          IFNULL(s.`Screening/Technique`, ""), "||",
                          IFNULL(
                            (SELECT
                               GROUP_CONCAT(
                                 CONCAT(
                                   t.geneid, "##", t.id_ncbi, "##", vot.`VariantOnTranscript/DNA`, "##", vot.`VariantOnTranscript/RNA`, "##", vot.`VariantOnTranscript/Protein`)
                                 SEPARATOR "$$")
                             FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot
                               INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                             WHERE vot.id = vog.id), "")
                        )
                        SEPARATOR ";;") AS variants
                    FROM ' . TABLE_INDIVIDUALS . ' AS i
                      LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid)
                      LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid AND p.statusid >= ?)
                      LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id)
                      LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid)
                      LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid)
                      LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND vog.statusid >= ?)
                      LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id)
                      LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id)
                    WHERE i.id IN (?' . str_repeat(', ?', count($aSubmissions) - 1) . ')
                      AND i.statusid >= ?
                      AND IFNULL(i.license, IFNULL(uc.default_license, "")) NOT IN (?' . str_repeat(', ?', count($aLicensesSummaryData) - 1) . ')
                    GROUP BY i.id',
                    array_merge(
                        array(STATUS_MARKED, STATUS_MARKED),
                        $aSubmissions,
                        array(STATUS_MARKED),
                        $aLicensesSummaryData))->fetchAllAssoc();
            }

            foreach ($aSubmissions as $aSubmission) {
                // Loop through Individuals and Panels.
                $aIndividual = array(
                    'id' => $aSubmission['id'],
                );
                if ($aSubmission['panel_size'] > 1) {
                    $aIndividual['size'] = (int) $aSubmission['panel_size'];
                }
                if (isset($aSubmission['gender'])) {
                    $nCode = (!isset($this->aValueMappings['gender'][$aSubmission['gender']])?
                        $this->aValueMappings['gender'][''] :
                        $this->aValueMappings['gender'][$aSubmission['gender']]);
                    $aIndividual['gender'] = array(
                        'code' => $nCode,
                    );
                    // If we didn't find a match, put the original term in the description.
                    if (!isset($this->aValueMappings['gender'][$aSubmission['gender']])) {
                        $aIndividual['gender']['description'] = array(
                            'term' => $aSubmission['gender'],
                        );
                    }
                }

                $aIndividual['phenotypes'] = array();
                if ($aSubmission['diseases']) {
                    foreach (explode(';;', $aSubmission['diseases']) as $sDisease) {
                        list($nOMIMID, $sInheritance, $sName) = explode('||', $sDisease);
                        $aPhenotype = array(
                            'term' => $sName,
                        );
                        if ($nOMIMID) {
                            $aPhenotype['source'] = 'OMIM';
                            $aPhenotype['accession'] = $nOMIMID;
                        }
                        if ($sInheritance) {
                            if (isset($this->aValueMappings['inheritance'][$sInheritance])) {
                                $aPhenotype['inheritance_pattern'] = $this->aValueMappings['inheritance'][$sInheritance];
                            } else {
                                $aPhenotype['inheritance_pattern'] = array(
                                    'term' => $sInheritance,
                                );
                            }
                        }
                        $aIndividual['phenotypes'][] = $aPhenotype;
                    }
                }
                if ($aSubmission['phenotypes']) {
                    $sInheritance = '';
                    foreach (preg_split('/[;,\r\n]+/', $aSubmission['phenotypes']) as $sPhenotype) {
                        // Phenotype information is probably the most diverse
                        //  and unstructured data within LOVD. Trying to make
                        //  sense of it, returning it as structured data.
                        $sPhenotype = trim($sPhenotype, '( )');
                        if (strpos($sPhenotype, '||') !== false) {
                            // We've received an inheritance pattern.
                            list($sInheritance, $sPhenotype) = explode('||', $sPhenotype, 2);
                            if ($sInheritance) {
                                // Long terms to short terms.
                                if (isset($this->aValueMappings['inheritance_long'][strtolower($sInheritance)])) {
                                    $sInheritance = $this->aValueMappings['inheritance_long'][strtolower($sInheritance)];
                                }
                                // Convert into HPO term or just a single term without a source.
                                if (isset($this->aValueMappings['inheritance'][$sInheritance])) {
                                    $aInheritance = $this->aValueMappings['inheritance'][$sInheritance];
                                } else {
                                    $aInheritance = array(
                                        'term' => $sInheritance,
                                    );
                                }
                            }
                        }
                        if (preg_match('/^(.+)?\(([?-])?HP:([0-9]+)$/i', $sPhenotype, $aRegs)) {
                            // term (HP:0000000).
                            // term (-HP:0000000).
                            // term (?HP:0000000).
                            // So absent HPO terms (-HP:...) could be marked
                            //  with a modifier (Excluded (HP:0040285)), but
                            //  VarioML has no way of storing this.
                            // Unknown HPO terms (?HP:...) can't be marked with
                            //  a modifier because HPO has none.
                            // So both of these are ignored and *not* exported.
                            if (!$aRegs[2]) {
                                $aPhenotype = array(
                                    'term' => trim($aRegs[1]),
                                    'source' => 'HPO',
                                    'accession' => $aRegs[3],
                                );
                                if ($sInheritance) {
                                    $aPhenotype['inheritance_pattern'] = $aInheritance;
                                }
                            }
                        } elseif (preg_match('/^([?-])?HP:([0-9]+) *\((.+)?$/i', $sPhenotype, $aRegs)) {
                            // HP:0000000 (term).
                            // Old format coming from the submission API.
                            if (!$aRegs[1]) {
                                $aPhenotype = array(
                                    'term' => trim($aRegs[3]),
                                    'source' => 'HPO',
                                    'accession' => $aRegs[2],
                                );
                                if ($sInheritance) {
                                    $aPhenotype['inheritance_pattern'] = $aInheritance;
                                }
                            }
                        } else {
                            // Unrecognized. Just pass on.
                            $aPhenotype = array(
                                'term' => trim($sPhenotype),
                            );
                            if ($sInheritance) {
                                $aPhenotype['inheritance_pattern'] = $aInheritance;
                            }
                        }
                        $aIndividual['phenotypes'][] = $aPhenotype;
                    }
                }
                // Unique and reset the array. We need to rebuild the keys to
                //  prevent a JSON array from becoming a JSON object.
                $aIndividual['phenotypes'] = array_values(
                    array_unique(
                        $aIndividual['phenotypes'],
                        SORT_REGULAR)
                );

                if ($aSubmission['remarks']) {
                    $aIndividual['comments'] = $this->addComment(array(), $aSubmission['remarks']);
                }

                if ($aSubmission['reference']) {
                    $aRefs = $this->convertReferenceToVML($aSubmission['reference'], array('doi', 'pubmed'));
                    if ($aRefs) {
                        if (!isset($aIndividual['db_xrefs'])) {
                            $aIndividual['db_xrefs'] = array();
                        }
                        // Merge, unique, and reset the array.
                        // We need to rebuild the keys to prevent
                        //  a JSON array from becoming a JSON object.
                        $aIndividual['db_xrefs'] = array_values(
                            array_unique(
                                array_merge(
                                    $aIndividual['db_xrefs'],
                                    $aRefs),
                                SORT_REGULAR)
                        );
                    }
                }

                // Data creator and owner.
                foreach (
                    array(
                        array(
                            $aSubmission['creator'],
                            'submitter',
                        ),
                        array(
                            $aSubmission['owner'],
                            'owner',
                        )
                    ) as $aContact) {
                    list($sContact, $sRole) = $aContact;
                    if ($sContact) {
                        $aContact = $this->convertContactToVML($sRole, $sContact);

                        if (!isset($aIndividual['data_source'])) {
                            $aIndividual['data_source'] = array(
                                'contacts' => array(),
                            );
                        }
                        $aIndividual['data_source']['contacts'][] = $aContact;
                    }
                }

                // Data licensing, if known.
                if ($aSubmission['license']) {
                    $aLicense = $this->convertLicenseToVML($aSubmission['license']);
                    if ($aLicense) {
                        $aIndividual = array_merge($aIndividual, $aLicense);
                    }
                }

                $aIndividual['variants'] = array();

                // Then add variants. Note that variants can be repeated, when
                //  more than one screening has been created and linked to the
                //  same variant.
                foreach (explode(';;', $aSubmission['variants']) as $sVariant) {
                    list($nID, $nAllele, $sChr, $sDNA, $sDNA38, $sOrigin, $sRSID, $sRefs, $sRemarks, $sTemplate, $sTechnique, $sVOTs) = explode('||', $sVariant);
                    $aVariant = array(
                        'id' => $nID,
                        'copy_count' => ($nAllele == '3'? 2 : 1),
                        'type' => 'DNA',
                        'ref_seq' => array(
                            'source' => 'genbank',
                            'accession' => $_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr],
                        ),
                        'name' => array(
                            'scheme' => 'HGVS',
                            'value' => $sDNA,
                        ),
                        'aliases' => (!$sDNA38? array() : array(
                            array(
                                'ref_seq' => array(
                                    'source' => 'genbank',
                                    'accession' => $_SETT['human_builds']['hg38']['ncbi_sequences'][$sChr],
                                ),
                                'name' => array(
                                    'scheme' => 'HGVS',
                                    'value' => $sDNA38,
                                ),
                            ),
                        )),
                        'pathogenicities' => array(),
                    );

                    if (!$aVariant['aliases']) {
                        unset($aVariant['aliases']);
                    }

                    if ($sOrigin && isset($this->aValueMappings['genetic_origin'][$sOrigin])) {
                        $aVariant['genetic_origin'] = array(
                            'term' => $this->aValueMappings['genetic_origin'][$sOrigin],
                        );
                        if ($nAllele >= 10) {
                            $aVariant['genetic_origin']['genetic_source'] = array(
                                'term' => ($nAllele < 20? 'paternal' : 'maternal'),
                                'evidence_code' => array(
                                    'term' => (($nAllele % 10)? 'confirmed' : 'inferred'),
                                ),
                            );
                        }

                        // Special actions for UPD.
                        if (substr($sOrigin, 0, 18) == 'uniparental disomy') {
                            // Set copy count to 2, if it is 1.
                            if ($aVariant['copy_count'] == 1) {
                                $aVariant['copy_count'] = 2;
                            }

                            // Add a description to not lose this info.
                            $aVariant['genetic_origin']['description'] = $sOrigin;

                            // Set the parent, if needed.
                            list(,,$sAllele) = explode(' ', $sOrigin);
                            if (empty($aVariant['genetic_origin']['genetic_source'])) {
                                $aVariant['genetic_origin']['genetic_source'] = array(
                                    'term' => $sAllele,
                                );
                            } elseif ($aVariant['genetic_origin']['genetic_source']['term'] != $sAllele) {
                                // Strange, a conflict. Given allele value
                                //  doesn't match given genetic_origin value.
                                $aVariant['genetic_origin']['genetic_source']['comments'] = $this->addComment(
                                    array(),
                                    'Conflict in value for genetic_source: ' .
                                        $aVariant['genetic_origin']['genetic_source']['term'] .
                                        ' != ' . $sAllele . '.');
                            }
                        }
                    }

                    if ($sRemarks) {
                        $aVariant['comments'] = $this->addComment(array(), $sRemarks);
                    }

                    if ($sRSID) {
                        $aVariant['db_xrefs'] = array(
                            array(
                                'source' => 'dbsnp',
                                'accession' => $sRSID,
                            ),
                        );
                    }

                    if ($sRefs) {
                        $aRefs = $this->convertReferenceToVML($sRefs);
                        if ($aRefs) {
                            if (!isset($aVariant['db_xrefs'])) {
                                $aVariant['db_xrefs'] = array();
                            }
                            // Merge, unique, and reset the array.
                            // We need to rebuild the keys to prevent
                            //  a JSON array from becoming a JSON object.
                            $aVariant['db_xrefs'] = array_values(
                                array_unique(
                                    array_merge(
                                        $aVariant['db_xrefs'],
                                        $aRefs),
                                    SORT_REGULAR)
                            );
                        }
                    }

                    if ($sTemplate || $sTechnique) {
                        $aVariant['variant_detection'] = array();
                        // It's obviously best if data is stored like
                        //  (DNA, SEQ); (RNA, RT-PCR); but often it's not and
                        //  it's (DNA;RNA,SEQ;RT-PCR). We'll have to guess which
                        //  template belongs to which technique.
                        $aTemplates = explode(';', $sTemplate);
                        $nTemplates = count($aTemplates);
                        $aTechniques = explode(';', $sTechnique);
                        $nTechniques = count($aTechniques);
                        foreach ($aTemplates as $sTemplate) {
                            if ($nTechniques == 1 || $nTemplates == 1) {
                                // Single technique, store it once per template,
                                //  or one template only, put all techniques
                                //  together.
                                $aVariant['variant_detection'][] = array(
                                    'template' => $sTemplate,
                                    'technique' => $sTechnique,
                                );
                            } else {
                                // More than one template, more than one
                                //  technique. Try to figure out what belongs
                                //  to what.
                                if ($sTemplate == 'DNA') {
                                    // Exclude known RNA and protein techniques.
                                    $aVariant['variant_detection'][] = array(
                                        'template' => $sTemplate,
                                        'technique' => implode(';',
                                            array_diff(
                                                $aTechniques, array(
                                                    'expr', // Not standard LOVD.
                                                    'minigene', // Not standard LOVD.
                                                    'MS', // Not standard LOVD.
                                                    'Northern',
                                                    'PTT',
                                                    'RT-PCR',
                                                    'Western',
                                                ))),
                                    );
                                } elseif ($sTemplate == 'RNA') {
                                    // Exclude known DNA and protein techniques.
                                    $aVariant['variant_detection'][] = array(
                                        'template' => $sTemplate,
                                        'technique' => implode(';',
                                            array_diff(
                                                $aTechniques, array(
                                                'FISH', // Not standard LOVD.
                                                'FISHf', // Not standard LOVD.
                                                'MAPH',
                                                'MCA',
                                                'microscope', // Not standard LOVD.
                                                'MLPA',
                                                'MLPA-ms', // Not standard LOVD.
                                                'MS', // Not standard LOVD.
                                                'PTT',
                                                'SBE',
                                                'SEQ',
                                                'Southern',
                                                'Western',
                                            ))),
                                    );
                                } else {
                                    // Only select known protein techniques.
                                    $aVariant['variant_detection'][] = array(
                                        'template' => $sTemplate,
                                        'technique' => implode(';',
                                            array_intersect(
                                                $aTechniques, array(
                                                'MS', // Not standard LOVD.
                                                'PTT',
                                                'Western',
                                            ))),
                                    );
                                }
                            }
                        }
                    }

                    // Up and until this part, we didn't even check yet if we've
                    //  seen this variant before. That is possible when the
                    //  individual has multiple screenings that are linked to
                    //  the same variant. Check, possibly add the additional
                    //  screening information, then skip the rest.
                    if ($aIndividual['variants']) {
                        foreach ($aIndividual['variants'] as $nKey => $aVar) {
                            if ($aVar['id'] == $nID) {
                                // We've seen this variant before.
                                // The simplest is simply to take both arrays,
                                //  merge them, and make them unique.
                                // This may mean that templates are repeated,
                                //  but it indicates multiple screenings exist
                                //  in LOVD and varcache can merge it if needed.
                                $aIndividual['variants'][$nKey]['variant_detection'] =
                                    array_unique(
                                        array_merge(
                                            $aIndividual['variants'][$nKey]['variant_detection'],
                                            $aVariant['variant_detection']
                                        ),
                                        SORT_REGULAR
                                    );
                                continue 2;
                            }
                        }
                    }

                    if ($sVOTs) {
                        $aVariant['seq_changes']['variants'] = array();
                        foreach (explode('$$', $sVOTs) as $sVOT) {
                            list($sGene, $sRefSeq, $sDNA, $sRNA, $sProtein) = explode('##', $sVOT);
                            $aVariant['seq_changes']['variants'][] = array(
                                'type' => 'cDNA',
                                'gene' => array(
                                    'source' => 'HGNC',
                                    'accession' => $sGene,
                                ),
                                'ref_seq' => array(
                                    'source' => 'genbank',
                                    'accession' => $sRefSeq,
                                ),
                                'name' => array(
                                    'scheme' => 'HGVS',
                                    'value' => $sDNA,
                                ),
                                'seq_changes' => array(
                                    'variants' => array(
                                        array(
                                            'type' => 'RNA',
                                            'name' => array(
                                                'scheme' => 'HGVS',
                                                'value' => $sRNA,
                                            ),
                                            'seq_changes' => array(
                                                'variants' => array(
                                                    array(
                                                        'type' => 'AA',
                                                        'name' => array(
                                                            'scheme' => 'HGVS',
                                                            'value' => $sProtein,
                                                        ),
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            );
                        }
                    }

                    $aIndividual['variants'][] = $aVariant;
                }

                // Store in output.
                if ($aSubmission['panel_size'] > 1) {
                    $aReturn['panel']['panels'][] = $aIndividual;
                } else {
                    $aReturn['panel']['individuals'][] = $aIndividual;
                }
            }

            return $aReturn;
        }, $zData);



        // Set next seek window.
        $nNextPosition = $zData[$n-1]['position_g_start'] + 1;

        $aOutput = array(
            'data_model' => array(
                '$ref' => $this->aTables[$sTableName]['data_model'],
            ),
            'data' => $aData,
            'pagination' => array(
                'next_page_url' => lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $sTableName . '/data' . rawurlencode(':' . $sBuild . ':chr' . $sChr . ':' . $nNextPosition),
            ),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }
}
?>
