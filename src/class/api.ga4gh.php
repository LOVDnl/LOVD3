<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-22
 * Modified    : 2021-05-04
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





    function __construct (&$oAPI)
    {
        // Links the API to the private variable.

        if (!is_object($oAPI) || !is_a($oAPI, 'LOVD_API')) {
            return false;
        }

        $this->API = $oAPI;

        return true;
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
            'VariantOnGenome/DNA/hg38',
            'VariantOnGenome/ClinicalClassification',
            'VariantOnGenome/dbSNP',
            'VariantOnGenome/Genetic_origin',
            'VariantOnGenome/Reference',
        ));
        $aCols = $_DB->query('SELECT colid FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid IN (?' . str_repeat(', ?', count($aColsToCheck) - 1) . ')',
            $aColsToCheck)->fetchAllColumn();

        foreach ($aRequiredCols as $sCol) {
            if (!in_array($sCol, $aCols)) {
                $this->API->nHTTPStatus = 500; // Send 500 Internal Server Error.
                $this->API->aResponse = array('errors' => array(
                    'title' => 'Missing required columns.',
                    'detail' => 'Missing required columns; this LOVD instance is missing one or more columns required for this API to operate. Required fields: \'' . implode("', '", $aRequiredCols) . '\'.'));
                return false;
            }
        }
        $bDNA38 = in_array('VariantOnGenome/DNA/hg38', $aCols);
        $bdbSNP = in_array('VariantOnGenome/dbSNP', $aCols);
        $bVOGReference = in_array('VariantOnGenome/Reference', $aCols);

        // Fetch data. We do this in two steps; first the basic variant
        //  information and after that the full submission data.
        $sQ = 'SELECT
                 vog.chromosome,
                 vog.position_g_start,
                 vog.position_g_end,
                 GROUP_CONCAT(vog.id SEPARATOR ";") AS ids,
                 vog.`VariantOnGenome/DNA` AS DNA' .
            (!$bDNA38? '' : ',
                 GROUP_CONCAT(DISTINCT IFNULL(vog.`VariantOnGenome/DNA/hg38`, "") ORDER BY vog.`VariantOnGenome/DNA/hg38` SEPARATOR ";") AS DNA38') .
            (!$bdbSNP? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/dbSNP`, "") ORDER BY vog.`VariantOnGenome/dbSNP` SEPARATOR ";") AS dbSNP') .
            (!$bVOGReference? '' : ',
                 GROUP_CONCAT(DISTINCT NULLIF(vog.`VariantOnGenome/Reference`, "") ORDER BY vog.`VariantOnGenome/Reference` SEPARATOR ";") AS refs') . ',
                 GROUP_CONCAT(DISTINCT t.geneid ORDER BY t.geneid SEPARATOR ";") AS genes,
                 GROUP_CONCAT(DISTINCT
                   IFNULL(i.id,
                     CONCAT(vog.id, "||"' .
            (!$bDNA38? '' : ',
                       IFNULL(vog.`VariantOnGenome/DNA/hg38`, "")') . ', "||",
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
        $aData = array_map(function ($zData) use ($sBuild, $sChr)
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
                foreach (explode(';', str_replace('}', '};', $zData['refs'])) as $sRef) {
                    $sRef = trim($sRef);
                    if ($sRef) {
                        if (preg_match('/^\{dbSNP:(rs[0-9]+)\}$/', $sRef, $aRegs)) {
                            if (!isset($aReturn['db_xrefs'])) {
                                $aReturn['db_xrefs'] = array();
                            }
                            $aReturn['db_xrefs'][] =
                                array(
                                    'source' => 'dbsnp',
                                    'accession' => $aRegs[1],
                                );
                        }
                    }
                }
                if (isset($aReturn['db_xrefs'])) {
                    $aReturn['db_xrefs'] = array_unique($aReturn['db_xrefs'], SORT_REGULAR);
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
                    list($nID, $sDNA38, $sVOTs, $sOwner) = explode('||', $sVariant);
                    $aVariant = array(
                        'type' => 'DNA',
                        'ref_seq' => array(
                            'source' => 'genbank',
                            'accession' => $_SETT['human_builds'][$sBuild]['ncbi_sequences'][$sChr],
                        ),
                        'name' => array(
                            'scheme' => 'HGVS',
                            'value' => $zData['DNA'],
                        ),
                        'aliases' => (!$sDNA38? '' : array(
                            'ref_seq' => array(
                                'source' => 'genbank',
                                'accession' => $_SETT['human_builds']['hg38']['ncbi_sequences'][$sChr],
                            ),
                            'name' => array(
                                'scheme' => 'HGVS',
                                'value' => $sDNA38,
                            ),
                        )),
                        'pathogenicities' => array(),
                    );
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
                                        'type' => 'RNA',
                                        'name' => array(
                                            'scheme' => 'HGVS',
                                            'value' => $sRNA,
                                        ),
                                        'seq_changes' => array(
                                            'variants' => array(
                                                'type' => 'AA',
                                                'name' => array(
                                                    'scheme' => 'HGVS',
                                                    'value' => $sProtein,
                                                ),
                                            )
                                        )
                                    )
                                )
                            );
                        }
                    }
                    if ($sOwner) {
                        list($sORCID, $sName, $sEmail) = explode('##', $sOwner);
                        $aEmails = explode("\r\n", $sEmail);
                        $aContact = array(
                            'role' => 'owner',
                            'name' => $sName,
                            'email' => (count($aEmails) == 1? $sEmail : $aEmails),
                        );
                        if ($sORCID) {
                            $aContact['db_xrefs'] = array(
                                array(
                                    'source' => 'orcid',
                                    'accession' => $sORCID,
                                )
                            );
                        }

                        $aVariant['source'] = array(
                            'contacts' => array(
                                $aContact,
                            ),
                        );
                    }

                    $aReturn['panel']['variants'][] = $aVariant;
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
