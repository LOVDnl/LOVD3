<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-09
 * Modified    : 2020-03-12
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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



class LOVD_VV
{
    // This class defines the LOVD VV object, handling all Variant Validator calls.

    public $sURL = 'https://rest.variantvalidator.org/'; // The URL of the VV endpoint.
    public $aResponse = array( // The standard response body.
        'data' => array(),
        'warnings' => array(),
        'errors' => array(),
    );





    function __construct ($sURL = '')
    {
        // Initiates the VV object. Nothing much to do except for filling in the URL.

        if ($sURL) {
            // We don't test given URLs, that would take too much time.
            $this->sURL = rtrim($sURL, '/') . '/';
        }
        // __construct() should return void.
    }





    private function callVV ($sMethod, $aArgs = array())
    {
        // Wrapper function to call VV's JSON webservice.
        // Because we have a wrapper, we can implement CURL, which is much faster on repeated calls.
        global $_CONF, $_SETT;

        // Build URL, regardless of how we'll connect to it.
        $sURL = $this->sURL . $sMethod . '/' . implode('/', $aArgs) . '?content-type=application%2Fjson';
        $sJSONResponse = '';

        if (function_exists('curl_init')) {
            // Initialize curl connection.
            static $hCurl;

            if (!$hCurl) {
                $hCurl = curl_init();
                curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true); // Return the result as a string.
                if (!empty($_SETT['system']['version'])) {
                    curl_setopt($hCurl, CURLOPT_USERAGENT, 'LOVDv.' . $_SETT['system']['version']); // Return the result as a string.
                }

                // Set proxy.
                if (!empty($_CONF['proxy_host'])) {
                    curl_setopt($hCurl, CURLOPT_PROXY, $_CONF['proxy_host'] . ':' . $_CONF['proxy_port']);
                    if (!empty($_CONF['proxy_username']) || !empty($_CONF['proxy_password'])) {
                        curl_setopt($hCurl, CURLOPT_PROXYUSERPWD, $_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
                    }
                }
            }

            curl_setopt($hCurl, CURLOPT_URL, $sURL);
            $sJSONResponse = curl_exec($hCurl);

        } elseif (function_exists('lovd_php_file')) {
            // Backup method, no curl installed. We'll try LOVD's file() implementation, which also handles proxies.
            $aJSONResponse = lovd_php_file($sURL);
            if ($aJSONResponse !== false) {
                $sJSONResponse = implode("\n", $aJSONResponse);
            }

        } else {
            // Last fallback. Requires fopen wrappers.
            $aJSONResponse = file($sURL);
            if ($aJSONResponse !== false) {
                $sJSONResponse = implode("\n", $aJSONResponse);
            }
        }



        if ($sJSONResponse) {
            $aJSONResponse = @json_decode($sJSONResponse, true);
            if ($aJSONResponse !== false) {
                return $aJSONResponse;
            }
        }
        // Something went wrong...
        return false;
    }





    public function getTranscriptsByGene ($sSymbol)
    {
        // Returns the available transcripts for the given gene.
        global $_SETT;

        $aJSON = $this->callVV('VariantValidator/tools/gene2transcripts', array(
            'id' => $sSymbol,
        ));
        if ($aJSON !== false && $aJSON !== NULL && !empty($aJSON['transcripts'])) {
            $aData = $this->aResponse;
            foreach ($aJSON['transcripts'] as $aTranscript) {
                // Clean name.
                $sName = preg_replace(
                    array(
                        '/^Homo sapiens\s+/', // Remove species name.
                        '/^' . preg_quote($aJSON['current_name'], '/') . '\s+/', // The current gene name.
                        '/^\(' . preg_quote($aJSON['current_symbol'], '/') . '\),\s+/', // The current symbol.
                        '/, mRNA$/', // mRNA suffix.
                        '/, non-coding RNA$/', // non-coding RNA suffix, replaced to " (non-coding)".
                    ), array('', '', '', '', ' (non-coding)'), $aTranscript['description']);

                // Figure out the genomic positions, which are given to us using the NCs.
                $aGenomicPositions = array();
                foreach ($_SETT['human_builds'] as $sBuild => $aBuild) {
                    // See if one of the build's chromosomes match.
                    foreach (array_intersect($aBuild['ncbi_sequences'], array_keys($aTranscript['genomic_spans'])) as $sChromosome => $sRefSeq) {
                        if (!isset($aGenomicPositions[$sBuild])) {
                            $aGenomicPositions[$sBuild] = array();
                        }
                        $aGenomicPositions[$sBuild][$sChromosome] = array(
                            // FIXME: Can not know strand from these values. See https://github.com/openvar/variantValidator/issues/140.
                            'start' => $aTranscript['genomic_spans'][$sRefSeq]['start_position'],
                            'end' => $aTranscript['genomic_spans'][$sRefSeq]['end_position'],
                        );
                    }
                }

                $aData['data'][$aTranscript['reference']] = array(
                    'name' => $sName,
                    'id_ncbi_protein' => '', // FIXME: NP ID is currently not sent (yet). See https://github.com/openvar/variantValidator/issues/139.
                    'genomic_positions' => $aGenomicPositions,
                    'transcript_positions' => array(
                        'start' => ($aTranscript['coding_start'] == 'non-coding'? 1 : (2 - $aTranscript['coding_start'])), // FIXME: "non-coding" value hopefully will be fixed, and VV's start is off by 1, so I used 2. See https://github.com/openvar/variantValidator/issues/141.
                        'cds_length' => ($aTranscript['coding_end'] == 'non-coding'? NULL : ($aTranscript['coding_end'] - $aTranscript['coding_start'] + 2)), // FIXME: "non-coding" value hopefully will be fixed, and VV's start is off by 1, so I used 2. See https://github.com/openvar/variantValidator/issues/141.
                        'end' => NULL, // FIXME: Missing. See https://github.com/openvar/variantValidator/issues/141.
                    )
                );
            }
//                                         never used!           never used!      rename to CDS_length?     used to calculate sense and antisense!
// | id_ncbi     | id_protein_ncbi | position_c_mrna_start | position_c_mrna_end | position_c_cds_end | position_g_mrna_start | position_g_mrna_end |
// | NM_002225.3 | NP_002216.2     |                  -334 |                4331 |               1281 |              40697686 |            40713512 |
// | XR_243096.1 |                 |                     1 |                1958 |               1958 |              40697914 |            40711056 |
// VV: NM_002225.3 {"start":336;"end":1615}    NM_002225.4 {"start":35;"end":1305}
// NM_002225.3: GenBank says CDS 335..1615, meaning an upstream UTR of length 334, and a CDS of (1615-334=) 1281 (427 AAs); 1615 is the G of TAG. VV's start seems off by 1?
// NM_002225.4: GenBank says CDS  34..1305, meaning an upstream UTR of length  33, and a CDS of (1305- 33=) 1272 (424 AAs); 1305 is the G of TAG. VV's start seems off by 1?

            ksort($aData['data']);
            return $aData;
        } else {
            // Failure.
            return false;
        }
    }





    public function test ()
    {
        // Tests the VV endpoint.

        $aJSON = $this->callVV('hello');
        if ($aJSON !== false && $aJSON !== NULL) {
            if (isset($aJSON['status']) && $aJSON['status'] == 'hello_world') {
                // All good.
                return true;
            } else {
                // Something JSON, but perhaps another format?
                return 0;
            }
        } else {
            // Failure.
            return false;
        }
    }





    public function verifyGenomic ($sVariant, $aOptions = array())
    {
        // Verify a genomic variant, and optionally get mappings and a protein prediction.
        global $_CONF;

        if (empty($aOptions) || !is_array($aOptions)) {
            $aOptions = array();
        }

        // Append defaults for any remaining options.
        $aOptions = array_replace(
            array(
                // NOTE: When adding options here, check the JSON call because we use a array_unique() trick there.
                'map_to_transcripts' => false, // Should we map the variant to transcripts?
                'predict_protein' => false, // Should we get protein predictions?
            ),
            $aOptions);

        // Some options require others.
        // We want to map to transcripts also if we want protein prediction.
        $aOptions['map_to_transcripts'] = ($aOptions['map_to_transcripts'] || $aOptions['predict_protein']);

        $aJSON = $this->callVV('LOVD/lovd', array(
            'genome_build' => $_CONF['refseq_build'],
            'variant_description' => $sVariant,
            'transcripts' => 'all',
            'select_transcripts' => 'all',
            'check_only' => (array_unique(array_values($aOptions)) == array(false)? 'True' : (!$aOptions['predict_protein']? 'tx' : 'False')),
            'lift_over' => 'False',
        ));
        if ($aJSON !== false && $aJSON !== NULL && !empty($aJSON[$sVariant])) {
            $aData = $this->aResponse;

            // Discard the meta data.
            $aJSON = $aJSON[$sVariant];

            // We'll copy the errors, but I've never seen them filled in, even with REF errors.
            $aData['errors'] = $aJSON['errors'];
            // Check the flag value.
            if ($aJSON['flag']) {
                // Flag is empty even when giving a delG on a REF:C and on roll forward errors.
                switch ($aJSON['flag']) {
                    // FIXME: Flag indicates warning, but genomic_variant_error suggests an error. The latter would be more useful.
                    case 'genomic_variant_warning':
                        // Seen with a REF error of a substitution.
                        if ($aJSON[$sVariant]['genomic_variant_error']) {
                            $aData['errors'][] = str_replace($sVariant . ': ', '', $aJSON[$sVariant]['genomic_variant_error']);
                            $aJSON[$sVariant]['genomic_variant_error'] = NULL;
                        }
                        break;
                    default:
                        // FIXME: I think I'd like to save these. Perhaps log them? Can I otherwise get them from the code?
                        break;
                }
            }
            // Discard the errors array and the flag value.
            $aJSON = $aJSON[$sVariant];

            // Copy the (corrected) DNA value.
            $aData['data']['DNA'] = $aJSON['g_hgvs'];
            // If description is different, then apparently there's been some kind of correction.
            if ($sVariant != $aJSON['g_hgvs']) {
                $aData['warnings'][] = 'Variant description has been corrected.';
            }

            // Any errors given?
            if ($aJSON['genomic_variant_error']) {
                // Not a previously seen error, handled through the flag value.
                // We'll assume a warning.
                // FIXME: Value may need cleaning!
                $aData['warnings'][] = $aJSON['genomic_variant_error'];
            }

            // Mappings?
            $aData['data']['transcript_mappings'] = array();
            if ($aJSON['hgvs_t_and_p']) {
                foreach ($aJSON['hgvs_t_and_p'] as $sTranscript => $aTranscript) {
                    if ($sTranscript != 'intergenic') {
                        $aMapping = array(
                            'DNA' => '',
                            'protein' => '',
                        );
                        // FIXME: What to do with gap_statement?
                        if ($aTranscript['gapped_alignment_warning']) {
                            // Store this in warnings.
                            $aData['warnings'][] = $aTranscript['gapped_alignment_warning'];
                        }
                        if ($aTranscript['t_hgvs']) {
                            $aMapping['DNA'] = substr(strstr($aTranscript['t_hgvs'], ':'), 1);
                        }
                        if ($aTranscript['p_hgvs_tlc']) {
                            $aMapping['protein'] = substr(strstr($aTranscript['p_hgvs_tlc'], ':'), 1);
                        }
                        // FIXME: What to do with transcript_variant_error?
                        $aData['data']['transcript_mappings'][$sTranscript] = $aMapping;
                    }
                }
            }
            return $aData;

        } else {
            // Failure.
            return false;
        }
    }





    public function verifyGenomicAndMap ($sVariant)
    {
        // Wrapper to map a variant to transcripts as well as verifying it.

        return $this->verifyGenomic($sVariant, array('map_to_transcripts' => true));
    }





    public function verifyGenomicAndPredictProtein ($sVariant)
    {
        // Wrapper to map a variant to transcripts as well as verifying it.

        return $this->verifyGenomic($sVariant, array('predict_protein' => true));
    }
}
?>
