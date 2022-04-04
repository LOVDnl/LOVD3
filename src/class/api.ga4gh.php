<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-22
 * Modified    : 2022-04-01
 * For LOVD    : 3.5-pre-03
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class LOVD_API_GA4GH
{
    // This class defines the LOVD API object handling GA4GH Data Connect.

    private $API;                     // The API object.
    private $aURLElements = array();  // The current URL broken in parts.
    private $aFilters = array();      // Filters active on the data.
    private $sActiveGB;
    private $aActiveGBs;
    private $aTables = array(
        'variants' => array(
            'description' => 'Aggregated variant data, when available also containing information on individuals, their phenotypes, and their other variants.',
            'data_model' => 'https://raw.githubusercontent.com/VarioML/VarioML/master/json/schemas/v.2.0/variant.json',
            'first_page' => 'data:{{refseq_build}}:chr1',
        ),
    );
    private $aValueMappings = array(
        'classifications' => array(
            'ACMG' => array(
                'benign' => 'benign',
                'likely benign' => 'likely benign',
                'VUS' => 'uncertain significance',
                'likely pathogenic' => 'likely pathogenic',
                'pathogenic' => 'pathogenic',
            ),
            'ENIGMA' => array(
                'benign' => 'Class 1',
                'likely benign' => 'Class 2',
                'VUS' => 'Class 3',
                'likely pathogenic' => 'Class 4',
                'pathogenic' => 'Class 5',
            ),
        ),
        'effect' => array(), // Defined in the constructor.
        'gender' => array(
            '' => '0',
            '?' => '0',
            'F' => '2',
            'M' => '1',
            'rF' => '2',
            'rM' => '1',
        ),
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
    private $bLocal = false;
    private $bReturnBody = true;
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

        // Retrieve default genome build.
        global $_DB;
        $this->aActiveGBs = $_DB->query('SELECT id, column_suffix FROM ' . TABLE_GENOME_BUILDS)->fetchAllGroupAssoc();
        $this->sActiveGB = array_keys($this->aActiveGBs)[0];

        return true;
    }





    private function addComment ($aComments, $Text, $sTerm = '')
    {
        // Adds a comment to an existing array of comments.

        if (!is_array($Text)) {
            $Text = array(
                'value' => $Text
            );
        }

        $aReturn = array();
        if ($sTerm) {
            $aReturn['term'] = $sTerm;
        }
        $aReturn['texts'] = array(
            $Text,
        );
        $aComments[] = $aReturn;

        return $aComments;
    }





    private function convertAliasesToVML ($zData, $sBuild, $sChr)
    {
        // This function converts all aliases (variant descriptions that
        //  are not of the specified build) into the VarioML Alias format.

        return array_map(
            function($sGBID) use ($zData, $sChr) {
                // We will go through all genome builds and return the required
                //  info in the VarioML Alias format.
                global $_SETT;
                return array(
                    'ref_seq' => array(
                        'source' => 'genbank',
                        'accession' => $_SETT['human_builds'][$sGBID]['ncbi_sequences'][$sChr],
                    ),
                    'name' => array(
                        'scheme' => 'HGVS',
                        'value' => $zData['DNA' . $sGBID],
                    ),
                );
            },
            array_filter(array_keys($this->aActiveGBs)), function($sGBID) use ($sBuild) {
                // We only want to get ALIASES (ALTERNATIVE variant descriptions).
                // This means that we do not want to get the descriptions of the
                //  specified build, since that description is not alternative.
                return $sGBID != $sBuild;
            }
        );
    }





    private function convertClassificationToVML ($sClassifications)
    {
        // Converts classifications into VarioML pathogenicities.

        $aReturn = array();
        foreach (explode(';', $sClassifications) as $sIDClassificationMethod) {
            if ($sIDClassificationMethod) {
                list($nID, $sClassification, $sMethod) = explode(':', $sIDClassificationMethod);
                if ($sClassification && !in_array($sClassification, array('NA', 'unclassified'))) {
                    $aReturn[$nID] = array(
                        'scope' => 'individual', // Always the same for us.
                        'term' => trim(preg_replace('/\s\([a-z!]+\)$/i', '', $sClassification)),
                        'data_source' => array(
                            'name' => 'submitter',
                        ),
                    );
                    if ($sMethod && in_array($sMethod, array('ACMG', 'ENIGMA'))) {
                        $aReturn[$nID]['source'] = $sMethod;
                        // Unrecognized methods we'll simply not store.
                        // VarioML has a dictionary for this field and we don't
                        //  want unrecognized values in there.
                        if (isset($this->aValueMappings['classifications'][$sMethod])) {
                            // Some conversions required. Also, values not in
                            //  the list will be removed (like 'association').
                            if (!isset($this->aValueMappings['classifications'][$sMethod][$aReturn[$nID]['term']])) {
                                // Unset the whole thing.
                                unset($aReturn[$nID]);
                                continue;
                            } else {
                                $aReturn[$nID]['term'] = $this->aValueMappings['classifications'][$sMethod][$aReturn[$nID]['term']];
                            }
                        }
                    }
                    // Values like "pathogenic (!)" require a comment.
                    if (substr($sClassification, -3) == '(!)') {
                        $aReturn[$nID]['comments'] = $this->addComment(array(),
                            'This classification is marked as an exceptional case, see the full entry.',
                            'IEXCEPTION'
                        );
                    } elseif (substr($sClassification, -8) == 'aternal)') {
                        $aReturn[$nID]['comments'] = $this->addComment(array(),
                            'This classification is marked as ' . substr($sClassification, -9, -1) . ' genomic imprinting.',
                            'IIMPRINTING'
                        );
                    } elseif (in_array(substr($sClassification, -10), array('recessive)', '(dominant)'))) {
                        // VarioML states we need to store inheritance within a phenotype.
                        // But that's difficult to do in the code, and not all variants
                        //  have phenotypes (summary records, classification records).
                        $sInheritance = str_replace('(', '', substr($sClassification, -10, -1));
                        $aReturn[$nID]['comments'] = $this->addComment(array(),
                            'This classification is marked as ' . $sInheritance . ' inheritance.',
                            'I' . strtoupper($sInheritance)
                        );
                    }
                }
            }
        }

        return $aReturn;
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
        // Converts variant effects into VarioML pathogenicities.

        $aReturn = array();
        foreach (explode(';', $sEffects) as $sIDEffect) {
            if ($sIDEffect) {
                list($nID, $nEffectID) = explode(':', $sIDEffect);
                $aReturn[$nID] = array();
                if ($nEffectID[0]) {
                    $aReturn[$nID][] = array(
                        'scope' => 'individual', // Always the same for us.
                        'source' => 'LOVD',
                        'term' => $this->aValueMappings['effect'][(int) $nEffectID[0]],
                        'data_source' => array(
                            'name' => 'submitter',
                        ),
                    );
                }
                if ($nEffectID[1]) {
                    $aReturn[$nID][] = array(
                        'scope' => 'individual', // Always the same for us.
                        'source' => 'LOVD',
                        'term' => $this->aValueMappings['effect'][(int) $nEffectID[1]],
                        'data_source' => array(
                            'name' => 'curator',
                        ),
                    );
                }
            }
        }

        return $aReturn;
    }





    private function convertGeneToVML ($sSymbol)
    {
        // Converts gene symbol into VarioML.
        global $_DB;
        static $aGenes = array();

        if (!isset($aGenes[$sSymbol])) {
            $aGenes[$sSymbol] = $_DB->query('
                SELECT id_hgnc, id_omim FROM ' . TABLE_GENES . ' WHERE id = ?',
                array($sSymbol))->fetchAssoc();
        }

        $aGene = array(
            'source' => 'HGNC',
            'accession' => $aGenes[$sSymbol]['id_hgnc'],
            'db_xrefs' => array(
                array(
                    'source' => 'HGNC.symbol',
                    'accession' => $sSymbol,
                ),
            )
        );
        if (!empty($aGenes[$sSymbol]['id_omim'])) {
            $aGene['db_xrefs'][] = array(
                'source' => 'MIM',
                'accession' => $aGenes[$sSymbol]['id_omim'],
            );
        }
        return $aGene;
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
                $aReturn['sharing_policy']['comments'] = $this->addComment(array(),
                    'Additional permissions for LOVD project.', 'ILICENSE4LOVD');
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
            $sRef = trim($sRef, ', ');
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





    public function processGET ($aURLElements, $bReturnBody)
    {
        // Handle GET and HEAD requests for GA4GH Data Connect.
        // For HEAD requests, we won't print any output.
        // We could just check for the HEAD constant but this way the code will
        //  be more independent on the rest of the infrastructure.
        // Note that LOVD API's sendHeaders() function does check for HEAD and
        //  automatically won't print any contents if HEAD is used.
        global $_SETT, $_STAT;
        $this->bReturnBody = $bReturnBody;

        // We currently require authorization. This needs to be sent over an
        //  Authorization HTTP request header.
        $aHeaders = getallheaders();
        $this->bLocal = in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'));
        if (!isset($aHeaders['Authorization']) || substr($aHeaders['Authorization'], 0, 7) != 'Bearer ') {
            if (!$this->bLocal) {
                $this->API->nHTTPStatus = 401; // Send 401 Unauthorized.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Access denied.',
                    'detail' => 'Please provide authorization for this resource. To request access, contact the admin: ' . $_SETT['admin']['address_formatted'] . '.'));
                return false;
            }

        } else {
            $sToken = substr($aHeaders['Authorization'], 7);
            if ($sToken != md5('auth:' . $_STAT['signature'])) {
                $this->API->nHTTPStatus = 401; // Send 401 Unauthorized.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Access denied.',
                    'detail' => 'The given token is not correct. To request access, contact the admin: ' . $_SETT['admin']['address_formatted'] . '.'));
                return false;
            } else {
                $this->bAuthorized = true;
            }
        }
        $this->bVarCache = ($this->bVarCache && ($this->bAuthorized || $this->bLocal));

        $this->aURLElements = array_pad($aURLElements, 3, '');

        // No further elements given, then forward to the table list.
        if (!implode('', $this->aURLElements)) {
            $this->API->nHTTPStatus = 302; // Send 302 Moved Temporarily (302 Found in HTTP 1.1).
            $this->API->aResponse['messages'][] = 'Location: ' . lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/service-info';
            return true;
        }

        // Check URL structure.
        if (count($this->aURLElements) > 3
            || ($this->aURLElements[0] == 'service-info' && $aURLElements[1])
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
            && !preg_match('/^data:hg[0-9]{2}(:chr([XYM]|[0-9]{1,2})(:[0-9]+(-[0-9]+)?)?)?$/', $this->aURLElements[2])) {
            $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
            $this->API->aResponse = array('errors' => array('title' => 'Could not parse requested URL.'));
            return false;
        }

        // Process filters.
        // Supported: "If-Modified-Since" for selecting only data recently edited.
        // HTTP has a very strict definition of how this field should look,
        //  but we're happy with whatever strtotime() recognizes.
        if (isset($aHeaders['If-Modified-Since']) && $tIfModifiedSince = strtotime($aHeaders['If-Modified-Since'])) {
            // strtotime() took care of timezone differences.
            $this->aFilters['modified_since'] = date('Y-m-d H:i:s', $tIfModifiedSince);
            // Also implement the "Last-Modified" response header.
            // We shouldn't pick the last edited_date that we see, since we
            //  don't know if data has been deleted or edited "out" of this view
            //  since (i.e., its edit removed it from this view).
            $oDate = new DateTime('now', new DateTimeZone('GMT'));
            $this->API->aHTTPHeaders['Last-Modified'] = str_replace('+0000', 'GMT', $oDate->format('r'));
        }

        // Now actually handle the request.
        if ($aURLElements[0] == 'service-info') {
            return $this->showServiceInfo();
        } elseif ($aURLElements[0] == 'tables') {
            return $this->showTables();
        } elseif ($aURLElements[0] == 'table' && $aURLElements[2] == 'info') {
            return $this->showTableInfo($aURLElements[1]);
        } elseif ($aURLElements[0] == 'table' && $aURLElements[2] == 'data') {
            return $this->showTableData($aURLElements[1]);
        } elseif ($aURLElements[0] == 'table' && preg_match('/^data:(hg[0-9]{2})(?::chr([XYM]|[0-9]{1,2})(?::([0-9]+(?:-[0-9]+)?))?)?$/', $aURLElements[2], $aRegs)) {
            return $this->showTableDataPage($aURLElements[1], $aRegs);
        }

        // If we end up here, we didn't handle the request well.
        return false;
    }





    private function showServiceInfo ()
    {
        // Shows service info.
        global $_STAT;

        $aOutput = array(
            'id' => 'nl.lovd.ga4gh.' . md5($_STAT['signature']),
            'name' => 'GA4GH Data Connect API for LOVD instance ' . md5($_STAT['signature']),
            'type' => array(
                'group' => 'org.ga4gh',
                'artifact' => 'service-registry',
                'version' => '1.0.0'
            ),
            'description' => 'Implementation of the GA4GH Data Connect API on top of this LOVD instance. Supports export of aggregated variant records (table: "variants"). See /tables for more information.',
            'organization' => array(
                'name' => 'Leiden Open Variation Database (LOVD)',
                'url' => 'https://lovd.nl/',
            ),
            'version' => '1.0.0',
        );

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showTableData ($sTableName)
    {
        // Shows table data view, first page only.
        // This doesn't actually list data yet, it's easier for us to implement
        //  it through pagination.
        $aOutput = array(
            'data_model' => array(
                '$ref' => $this->aTables[$sTableName]['data_model'],
            ),
            'data' => array(),
            'pagination' => array(
                'next_page_url' => lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $sTableName . '/' .
                    rawurlencode(str_replace('{{refseq_build}}', $this->sActiveGB, $this->aTables[$sTableName]['first_page'])),
            ),
        );

        $this->API->aResponse = $aOutput;
        return true;
    }





    private function showTableDataPage ($sTableName, $aPage)
    {
        // Shows table data page.
        global $_SETT;

        if ($sTableName == 'variants') {
            list(, $sBuild, $sChr, $sPosition) = array_pad($aPage, 4, '1');

            if (!in_array($sBuild, array_keys($this->aActiveGBs))) {
                // We can only support searches on genome builds that are
                //  active in the database.
                $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
                $this->API->aResponse = array('errors' => array('title' =>
                    (isset($_SETT['human_builds'][$sBuild])? 'Unsupported' : 'Unrecognized')  . ' genome build.')
                );
                $this->API->aResponse['errors']['detail'] = 'We cannot use genome build ' . $sBuild . '. Please choose from: \'' . implode('|', array_keys($this->aActiveGBs)) . '\'.';
                return false;

            } elseif (!isset($_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr])) {
                // We don't know this chromosome.
                $this->API->nHTTPStatus = 400; // Send 400 Bad Request.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Unrecognized chromosome.',
                    'detail' => 'Unrecognized chromosome. Choose from: \'' . implode("', '", array_keys($_SETT['human_builds'][$sBuild]['ncbi_sequences'])) . '\'.'));
                return false;
            }

            if (!$this->bReturnBody) {
                return true;
            }
            return $this->showVariantDataPage($sBuild, $sChr, $sPosition);
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





    private function showVariantDataPage ($sBuild, $sChr, $sPosition)
    {
        // Shows variant data page.
        global $_DB, $_SETT;

        $sTableName = 'variants';
        $nTimeLimit = 15; // After 15 seconds, just send what you have.
        $nLimit = 100; // Get 100 variants max in one go.
        // Split position fields (append hyphen to prevent notice).
        list($nPositionStart, $nPositionEnd) = explode('-', $sPosition . '-');

        // Check for required, and wanted columns.
        $aRequiredCols = array(
            'VariantOnGenome/DNA' . (!$this->aActiveGBs[$sBuild]? '' : '/' . $this->aActiveGBs[$sBuild]),
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
            'VariantOnGenome/ClinicalClassification',
            'VariantOnGenome/ClinicalClassification/Method',
            'VariantOnGenome/dbSNP',
            'VariantOnGenome/Genetic_origin',
            'VariantOnGenome/Reference',
            'VariantOnGenome/Remarks',
        ));
        $sGenomeBuildQ = '';
        foreach ($this->aActiveGBs as $sGBID => $sGBSuffix) {
            // We will prepare to get the right data for each active
            //  genome build.
            $sPreparedSuffix = !$sGBSuffix ? '' : '/' . $sGBSuffix;

            if ($sGBID != $sBuild) {
                // We only need extra data on genome builds that are
                //  NOT the build as specified by the user, because
                //  that build has been processed already.
                $sGenomeBuildQ .= ', ' .
                    'GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/DNA' . $sPreparedSuffix . '`, "")' .
                    ' ORDER BY vog.`VariantOnGenome/DNA' . $sPreparedSuffix . '` SEPARATOR ";") AS DNA' . $sGBID;

                $aColsToCheck[] = 'VariantOnGenome/DNA' . $sPreparedSuffix;
            }
        }
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
        $bClassification = in_array('VariantOnGenome/ClinicalClassification', $aCols);
        $bClassificationMethod = in_array('VariantOnGenome/ClinicalClassification/Method', $aCols);
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

        // We'll need lots of space for GROUP_CONCAT().
        $_DB->query('SET group_concat_max_len = 1000000');

        // Fetch data. We do this in two steps; first the basic variant
        //  information and after that the full submission data.
        $sQ = 'SELECT
                 vog.chromosome,
                 vog.position_g_start' . (!$this->aActiveGBs[$sBuild]? '' : '_' . $this->aActiveGBs[$sBuild]) . ',
                 vog.position_g_end' . (!$this->aActiveGBs[$sBuild]? '' : '_' . $this->aActiveGBs[$sBuild]) . ',
                 GROUP_CONCAT(vog.id SEPARATOR ";") AS ids,
                 vog.`VariantOnGenome/DNA' . (!$this->aActiveGBs[$sBuild]? '' : '/' . $this->aActiveGBs[$sBuild]) . '` AS DNA' .
            $sGenomeBuildQ . ',
                 GROUP_CONCAT(DISTINCT CONCAT(vog.id, ":", vog.effectid) ORDER BY vog.id SEPARATOR ";") AS effectids' .
            (!$bClassification? '' : ',
                 GROUP_CONCAT(DISTINCT CONCAT(vog.id, ":", NULLIF(vog.`VariantOnGenome/ClinicalClassification`, ""), ":"' .
                (!$bClassificationMethod? '' : ', IFNULL(vog.`VariantOnGenome/ClinicalClassification/Method`, "")') .
                ') ORDER BY vog.id SEPARATOR ";") AS classifications') .
            (!$bdbSNP? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/dbSNP`, "") ORDER BY vog.`VariantOnGenome/dbSNP` SEPARATOR ";") AS dbSNP') .
            (!$bVOGReference? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/Reference`, "") ORDER BY vog.`VariantOnGenome/Reference` SEPARATOR ";") AS refs') . ',
                 GROUP_CONCAT(DISTINCT t.geneid ORDER BY t.geneid SEPARATOR ";") AS genes,
                 GROUP_CONCAT(DISTINCT
                   IFNULL(i.id,
                     CONCAT(vog.id, "||", IFNULL(uc.default_license, ""), "||", vog.effectid, "||",
                        vog.`VariantOnGenome/DNA' . (!$this->aActiveGBs[$sBuild]? '' : '/' . $this->aActiveGBs[$sBuild]) . '`, "||",' .

            (!$bClassification? '' : ',
                       IFNULL(vog.`VariantOnGenome/ClinicalClassification`, "")') . ', "||"' .
            (!$bClassificationMethod? '' : ',
                       IFNULL(vog.`VariantOnGenome/ClinicalClassification/Method`, "")') . ', "||"' .
            (!$bGeneticOrigin? '' : ',
                       IFNULL(LOWER(vog.`VariantOnGenome/Genetic_origin`), "")') . ', "||"' .
            (!$bdbSNP? '' : ',
                       IFNULL(vog.`VariantOnGenome/dbSNP`, "")') . ', "||"' .
            (!$bVOGReference? '' : ',
                       REPLACE(IFNULL(vog.`VariantOnGenome/Reference`, ""), ";", ",")') . ', "||"' .
            (!$bVOGRemarks? '' : ',
                       REPLACE(IFNULL(vog.`VariantOnGenome/Remarks`, ""), ";", ",")') . ', "||",
                       IFNULL(
                         (SELECT
                            GROUP_CONCAT(
                              CONCAT(
                                t.geneid, "##", t.id_ncbi, "##", vot.`VariantOnTranscript/DNA`, "##", vot.`VariantOnTranscript/RNA`, "##", t.id_protein_ncbi, "##", vot.`VariantOnTranscript/Protein`)
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
                   ) SEPARATOR ";;") AS variants,
                 MIN(vog.created_date) AS created_date,
                 MAX(IFNULL(vog.edited_date, vog.created_date)) AS edited_date
               FROM ' . TABLE_VARIANTS . ' AS vog
                 LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)
                 LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                 LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                 LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)
                 LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id AND i.statusid >= ?)
                 LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id)
                 LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id)
               WHERE vog.chromosome = ?
                 AND vog.position_g_start >= ?' . (!$nPositionEnd? '' : ' AND vog.position_g_end <= ?') . '
                 AND vog.statusid >= ?';
        $aQ = array(
            STATUS_MARKED,
            (string) $sChr,
            (int) $nPositionStart
        );
        if ($nPositionEnd) {
            $aQ[] = (int) $nPositionEnd;
        }
        $aQ[] = STATUS_MARKED;
        $sQ .= '
               GROUP BY vog.chromosome, vog.position_g_start, vog.position_g_end, vog.`VariantOnGenome/DNA`';
        // If-Modified-Since filter must be on HAVING as it must be done *after* grouping.
        if (isset($this->aFilters['modified_since'])) {
            $sQ .= '
               HAVING edited_date >= ?';
            $aQ[] = $this->aFilters['modified_since'];
        }
        $sQ .= '
               ORDER BY vog.chromosome, vog.position_g_start, vog.position_g_end, vog.`VariantOnGenome/DNA`
               LIMIT ' . $nLimit;
        $zData = $_DB->query($sQ, $aQ)->fetchAllAssoc();
        $n = count($zData);



        // Make all transformations.
        $tStart = microtime(true);
        $aData = array_map(function ($zData)
        use (
            $tStart, $nTimeLimit,
            $aLicenses, $aLicensesSummaryData, $sBuild, $sChr,
            $bdbSNP, $bClassification, $bClassificationMethod, $bGeneticOrigin,
            $bIndGender, $bIndReference, $bIndRemarks,
            $bPhenotypeAdditional, $bPhenotypeInheritance,
            $bVOGReference, $bVOGRemarks)
        {
            global $_DB, $_SETT;

            // If we've been busy for too long, stop working.
            if ((microtime(true) - $tStart) > $nTimeLimit) {
                // This will just continue to the next item, but will speed up the execution a lot.
                return false;
            }

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
                'aliases' => $this->convertAliasesToVML($zData, $sBuild, $sChr),
                'locations' => array(
                    array(
                        'chr' => $sChr,
                        'start' => (int) $zData['position_g_start'],
                        'end' => (int) $zData['position_g_end'],
                    ),
                ),
                'pathogenicities' => array(),
                'creation_date' => array(
                    'value' => date('c', strtotime($zData['created_date'])),
                ),
                'modification_date' => array(
                    'value' => date('c', strtotime($zData['edited_date'])),
                ),
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
                    array($this, 'convertGeneToVML'), explode(';', $zData['genes']));
            }

            if (!$aReturn['aliases']) {
                unset($aReturn['aliases']);
            }

            $aReturn['effectids'] = $this->convertEffectsToVML($zData['effectids']);
            if (!empty($zData['classifications'])) {
                $aReturn['classifications'] = $this->convertClassificationToVML($zData['classifications']);
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
                    list(
                        $nID,
                        $sLicense,
                        $sEffects,
                        $sClassification,
                        $sClassificationMethod,
                        $sOrigin,
                        $sRSID,
                        $sRefs,
                        $sRemarks,
                        $sVOTs,
                        $sCreator,
                        $sOwner
                    ) = explode('||', $sVariant);

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
                        'aliases' => $this->convertAliasesToVML($zData, $sBuild, $sChr),
                        'pathogenicities' => array(),
                    );

                    if (!$aVariant['aliases']) {
                        unset($aVariant['aliases']);
                    }

                    $aVariant['pathogenicities'] = array_merge(
                        current($this->convertEffectsToVML($nID . ':' . $sEffects)),
                        array_values($this->convertClassificationToVML($nID . ':' . $sClassification . ':' . $sClassificationMethod))
                    );

                    // For GV shared type "SUMMARY records", overwrite the data_source.
                    if ($sOrigin && $sOrigin == 'summary record') {
                        // These entries are made by curators.
                        $aVariant['pathogenicities'] = array_map(
                            function ($aPathogenicity) {
                                if (isset($aPathogenicity['data_source'])) {
                                    $aPathogenicity['data_source']['name'] = 'curator';
                                }
                                return $aPathogenicity;
                            }, $aVariant['pathogenicities']);

                        // Also overwrite the values collected for the
                        //  aggregated variant entry.
                        if (!empty($aReturn['effectids'][$nID])) {
                            $aReturn['effectids'][$nID] = array_map(
                                function ($aPathogenicity) {
                                    if (isset($aPathogenicity['data_source'])) {
                                        $aPathogenicity['data_source']['name'] = 'curator';
                                    }
                                    return $aPathogenicity;
                                }, $aReturn['effectids'][$nID]);
                        }
                        if (!empty($aReturn['classifications'][$nID]['data_source'])) {
                            $aReturn['classifications'][$nID]['data_source']['name'] = 'curator';
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

                    if ($sVOTs) {
                        $aVariant['seq_changes']['variants'] = array();
                        foreach (explode('$$', $sVOTs) as $sVOT) {
                            list($sGene, $sRefSeq, $sDNA, $sRNA, $sProtRefSeq, $sProtein) = explode('##', $sVOT);
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
                                                        'ref_seq' => array(
                                                            'source' => 'genbank',
                                                            'accession' => $sProtRefSeq,
                                                        ),
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

                            // Remove protein NCBI ID when not available.
                            if (!$sProtRefSeq) {
                                unset($aVariant['seq_changes']['variants'][count($aVariant['seq_changes']['variants'])-1]['seq_changes']['variants'][0]['seq_changes']['variants'][0]['ref_seq']);
                            }
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
                      IFNULL(NULLIF(i.license, ""), uc.default_license) AS license,
                      GROUP_CONCAT(DISTINCT
                        CONCAT(
                          vog.id, "||",
                          vog.allele, "||",
                          vog.chromosome, "||",
                          vog.`VariantOnGenome/DNA' . (!$this->aActiveGBs[$sBuild]? '' : '/' . $this->aActiveGBs[$sBuild]) . ', "||",
                       vog.effectid, "||"' .
                    (!$bClassification? '' : ',
                       IFNULL(vog.`VariantOnGenome/ClinicalClassification`, "")') . ', "||"' .
                    (!$bClassificationMethod? '' : ',
                       IFNULL(vog.`VariantOnGenome/ClinicalClassification/Method`, "")') . ', "||"' .
                    (!$bGeneticOrigin? '' : ',
                          IFNULL(LOWER(vog.`VariantOnGenome/Genetic_origin`), "")') . ', "||"' .
                    (!$bdbSNP? '' : ',
                          IFNULL(vog.`VariantOnGenome/dbSNP`, "")') . ', "||"' .
                    (!$bVOGReference? '' : ',
                          REPLACE(IFNULL(vog.`VariantOnGenome/Reference`, ""), ";", ",")') . ', "||"' .
                    (!$bVOGRemarks? '' : ',
                          REPLACE(IFNULL(vog.`VariantOnGenome/Remarks`, ""), ";", ",")') . ', "||",
                          IFNULL(s.`Screening/Template`, ""), "||",
                          IFNULL(s.`Screening/Technique`, ""), "||",
                          IFNULL(
                            (SELECT
                               GROUP_CONCAT(
                                 CONCAT(
                                   t.geneid, "##", t.id_ncbi, "##", vot.`VariantOnTranscript/DNA`, "##", vot.`VariantOnTranscript/RNA`, "##", t.id_protein_ncbi, "##", vot.`VariantOnTranscript/Protein`)
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
                      AND IFNULL(NULLIF(i.license, ""), IFNULL(uc.default_license, "")) NOT IN (?' . str_repeat(', ?', count($aLicensesSummaryData) - 1) . ')
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
                            $aPhenotype['source'] = 'MIM';
                            $aPhenotype['accession'] = $nOMIMID;
                        }
                        if ($sInheritance) {
                            // Inheritance can contain multiple values, but
                            //  VarioML allows for only one. Combined values
                            //  will therefore just be stored as a term.
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
                    list(
                        $nID,
                        $nAllele,
                        $sChr,
                        $sDNA,
                        $sEffects,
                        $sClassification,
                        $sClassificationMethod,
                        $sOrigin,
                        $sRSID,
                        $sRefs,
                        $sRemarks,
                        $sTemplate,
                        $sTechnique,
                        $sVOTs
                    ) = explode('||', $sVariant);
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
                        'aliases' => $this->convertAliasesToVML($zData, $sBuild, $sChr),
                        'pathogenicities' => array(),
                    );

                    // Copy the phenotypes to this variant's aggregate pathogenicities.
                    if (!empty($aIndividual['phenotypes'])) {
                        if (isset($aReturn['effectids'][$nID][0])) {
                            $aReturn['effectids'][$nID][0]['phenotypes'] = $aIndividual['phenotypes'];
                        }
                        if (isset($aReturn['effectids'][$nID][1])) {
                            $aReturn['effectids'][$nID][0]['phenotypes'] = $aIndividual['phenotypes'];
                        }
                        if (isset($aReturn['classifications'][$nID])) {
                            $aReturn['classifications'][$nID]['phenotypes'] = $aIndividual['phenotypes'];
                        }
                    }

                    if (!$aVariant['aliases']) {
                        unset($aVariant['aliases']);
                    }

                    $aVariant['pathogenicities'] = array_merge(
                        current($this->convertEffectsToVML($nID . ':' . $sEffects)),
                        array_values($this->convertClassificationToVML($nID . ':' . $sClassification . ':' . $sClassificationMethod))
                    );
                    if ($sChr == 'M') {
                        // The GV shared sometimes uses "pathogenic (maternal)"
                        //  for chrM variants, which makes no sense. These
                        //  values are meant to indicate imprinting,
                        //  so this should be removed here.
                        $aVariant['pathogenicities'] = array_map(function ($aPathogenicity) {
                            if (isset($aPathogenicity['comments'])) {
                                foreach ($aPathogenicity['comments'] as $nKey => $aComment) {
                                    if (isset($aComment['term']) && $aComment['term'] == 'IIMPRINTING') {
                                        // Delete this.
                                        unset($aPathogenicity['comments'][$nKey]);
                                    }
                                }
                                if (!count($aPathogenicity['comments'])) {
                                    unset($aPathogenicity['comments']);
                                }
                            }
                            return $aPathogenicity;
                        }, $aVariant['pathogenicities']);
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
                                        ' != ' . $sAllele . '.',
                                    'WCONFLICT'
                                );
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
                            list($sGene, $sRefSeq, $sDNA, $sRNA, $sProtRefSeq, $sProtein) = explode('##', $sVOT);
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
                                                        'ref_seq' => array(
                                                            'source' => 'genbank',
                                                            'accession' => $sProtRefSeq,
                                                        ),
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

                            // Remove protein NCBI ID when not available.
                            if (!$sProtRefSeq) {
                                unset($aVariant['seq_changes']['variants'][count($aVariant['seq_changes']['variants'])-1]['seq_changes']['variants'][0]['seq_changes']['variants'][0]['ref_seq']);
                            }
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

            // The aggregate pathogenicities aren't stored well yet.
            $aReturn['pathogenicities'] = call_user_func_array('array_merge', $aReturn['effectids']);
            if (!empty($aReturn['classifications'])) {
                $aReturn['pathogenicities'] = array_merge(
                    $aReturn['pathogenicities'],
                    array_values($aReturn['classifications'])
                );
            }
            unset($aReturn['effectids'], $aReturn['classifications']);

            // Clean "individuals", "panels" and "variants" when empty.
            // VarioML wants them gone when empty.
            foreach (array('individuals', 'panels', 'variants') as $sIndex) {
                if (!count($aReturn['panel'][$sIndex])) {
                    unset($aReturn['panel'][$sIndex]);
                }
            }
            if (!count($aReturn['panel'])) {
                // Nothing licensed to show.
                unset($aReturn['panel']);
            }

            return $aReturn;
        }, $zData);



        // If we've gone over time, we have 'false' values in the data.
        $bOverTime = false;
        foreach (array_keys($aData) as $nKey) {
            if (!$aData[$nKey]) {
                unset($aData[$nKey]);
                $bOverTime = true;
            }
        }

        // Set next seek window.
        // We're not sure if we're done with this last position, so start there.
        $nNextPosition = (!$zData? 0 : $zData[$n-1]['position_g_start']);
        if ($bOverTime) {
            if (!$aData) {
                // We have removed everything. Try again.
                $nNextPosition = $zData[0]['position_g_start'];
            } else {
                // Continue with the position of the last item.
                $nNextPosition = $zData[count($aData)-1]['position_g_start'];
            }

        } elseif ($nPositionEnd) {
            // We were looking in a closed range.
            if ($n < $nLimit) {
                // We're done; continue after window.
                $nNextPosition = $nPositionEnd + 1;
            } else {
                // We're not sure if we're done.
                // Continue in this window.
                $nNextPosition .= '-' . $nPositionEnd;
            }

        } elseif ($n < $nLimit) {
            // We didn't receive everything. This must be because we're at the
            //  end of the chromosome. Let's look at the next.
            // The easiest way to find the "next" chromosome is by our list.
            $aChrs = array_keys($_SETT['human_builds'][$this->sActiveGB]['ncbi_sequences']);
            $nIndex = array_search($sChr, $aChrs);
            $nIndex ++;
            if (isset($aChrs[$nIndex])) {
                $sChr = $aChrs[$nIndex];
                $nNextPosition = 1;
            } else {
                $sChr = false;
            }
        }

        // If we were filtering using If-Modified-Since but we didn't have any
        //  results, that means nothing had been modified and we should let the
        //  user know. Unfortunately, HEAD requests don't reach here.
        if (isset($this->aFilters['modified_since']) && !$zData) {
            $this->API->nHTTPStatus = 304; // Send 304 Not Modified.
        }

        $aOutput = array(
            'data_model' => array(
                '$ref' => $this->aTables[$sTableName]['data_model'],
            ),
            'data' => $aData,
            'pagination' => array(
                'next_page_url' => lovd_getInstallURL() . 'api/v' . $this->API->nVersion . '/ga4gh/table/' . $sTableName . '/data' . rawurlencode(':' . $sBuild . ':chr' . $sChr . ':' . $nNextPosition),
            ),
        );

        // If we're at the end, make sure we let them know.
        if (!$sChr) {
            unset($aOutput['pagination']['next_page_url']);
        }

        $this->API->aResponse = $aOutput;
        return true;
    }
}
?>
