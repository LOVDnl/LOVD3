<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-09
 * Modified    : 2020-06-02
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
        $sURL = $this->sURL . $sMethod . '/' . implode('/', array_map('rawurlencode', $aArgs)) . '?content-type=application%2Fjson';
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





    private function getRNAProteinPrediction (&$aMapping, $sTranscript = '')
    {
        // Function to predict the RNA change and to improve VV's protein prediction.
        // $aMapping will be extended with 'RNA' and 'protein' if they don't already exist.
        // $sTranscript is just used to check if this is a coding or non-coding transcript.

        if (!is_array($aMapping) || !isset($aMapping['DNA'])) {
            // Without DNA, we can do nothing.
            return false;
        }

        if (!isset($aMapping['RNA'])) {
            $aMapping['RNA'] = 'r.(?)';
        }
        if (!isset($aMapping['protein'])) {
            $aMapping['protein'] = '';
        }

        // Check values, perhaps we can do better.
        if (substr($aMapping['DNA'], -1) == '=') {
            // DNA actually didn't change. Protein will indicate the same.
            $aMapping['RNA'] = 'r.(=)';
            // FIXME: VV returns p.(Ala86=) rather than p.(=); perhaps return r.(257=) instead of r.(=).
            //  If you instead would like to make VV return p.(=), here is where you change this.
            //  If you do, don't forget to check whether you're on a coding transcript.
            // For UTRs or p.Met1, a c.= returns a p.? (safe choice). I prefer a p.(=).
            if ($aMapping['protein'] == 'p.?' || $aMapping['protein'] == 'p.(Met1?)') {
                $aMapping['protein'] = 'p.(=)';
            }

        } elseif (function_exists('lovd_getVariantInfo')
            && in_array($aMapping['protein'], array('', 'p.?', 'p.(=)'))) {
            // lovd_getVariantInfo() is generally fast, so we don't have to worry about slowdowns.
            // But we need to prevent the possible database query for 3' UTR variants,
            //  because we don't even know if we have the transcript.
            // Therefore, passing False as transcript ID.
            $aVariant = lovd_getVariantInfo($aMapping['DNA'], false);
            if ($aVariant) {
                // We'd want to check this.
                // Splicing.
                if (($aVariant['position_start_intron'] && abs($aVariant['position_start_intron']) <= 5)
                    || ($aVariant['position_end_intron'] && abs($aVariant['position_end_intron']) <= 5)
                    || ($aVariant['position_start_intron'] && !$aVariant['position_end_intron'])
                    || (!$aVariant['position_start_intron'] && $aVariant['position_end_intron'])) {
                    $aMapping['RNA'] = 'r.spl?';
                    $aMapping['protein'] = 'p.?';

                } elseif ($aVariant['position_start_intron'] && $aVariant['position_end_intron']
                    && abs($aVariant['position_start_intron']) > 5 && abs($aVariant['position_end_intron']) > 5
                    && ($aVariant['position_start'] == $aVariant['position_end'] || $aVariant['position_start'] == ($aVariant['position_end'] + 1))) {
                    // Deep intronic.
                    $aMapping['RNA'] = 'r.(=)';
                    $aMapping['protein'] = 'p.(=)';

                } else {
                    // No introns involved.
                    if ($aVariant['position_start'] < 0 && $aVariant['position_end'] < 0) {
                        // Variant is upstream.
                        $aMapping['RNA'] = 'r.(?)';
                        $aMapping['protein'] = 'p.(=)';

                    } elseif ($aVariant['position_start'] < 0 && strpos($aMapping['DNA'], '*') !== false) {
                        // Start is upstream, end is downstream.
                        if ($aMapping['type'] == 'del') {
                            $aMapping['RNA'] = 'r.0?';
                            $aMapping['protein'] = 'p.0?';
                        } else {
                            $aMapping['RNA'] = 'r.?';
                            $aMapping['protein'] = 'p.?';
                        }

                    } elseif (substr($aMapping['DNA'], 0, 3) == 'c.*' && ($aVariant['type'] == 'subst' || substr_count($aMapping['DNA'], '*') > 1)) {
                        // Variant is downstream.
                        $aMapping['RNA'] = 'r.(=)';
                        $aMapping['protein'] = 'p.(=)';

                    } elseif ($aVariant['type'] != 'subst' && $aMapping['protein'] != 'p.(=)') {
                        // Deletion/insertion partially in the transcript, not predicted to do nothing.
                        $aMapping['RNA'] = 'r.?';
                        $aMapping['protein'] = 'p.?';

                    } else {
                        // Substitution on wobble base or so.
                        $aMapping['RNA'] = 'r.(?)';
                    }
                }

                // But wait, did we just fill in a protein field for a non-coding transcript?
                if (substr($sTranscript, 1, 1) == 'R') {
                    $aMapping['protein'] = '';
                }
            }
        }

        return true;
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
                    if (!isset($aBuild['ncbi_sequences'])) {
                        continue;
                    }
                    // See if one of the build's chromosomes match.
                    foreach (array_intersect($aBuild['ncbi_sequences'], array_keys($aTranscript['genomic_spans'])) as $sChromosome => $sRefSeq) {
                        if (!isset($aGenomicPositions[$sBuild])) {
                            $aGenomicPositions[$sBuild] = array();
                        }
                        $aGenomicPositions[$sBuild][$sChromosome] = array(
                            'start' => ($aTranscript['orientation'] == 1?
                                $aTranscript['genomic_spans'][$sRefSeq]['start_position'] :
                                $aTranscript['genomic_spans'][$sRefSeq]['end_position']),
                            'end' => ($aTranscript['orientation'] == 1?
                                $aTranscript['genomic_spans'][$sRefSeq]['end_position'] :
                                $aTranscript['genomic_spans'][$sRefSeq]['start_position']),
                        );
                    }
                }

                $aData['data'][$aTranscript['reference']] = array(
                    'name' => $sName,
                    'id_ncbi_protein' => $aTranscript['translation'],
                    'genomic_positions' => $aGenomicPositions,
                    'transcript_positions' => array(
                        'cds_start' => $aTranscript['coding_start'],
                        'cds_length' => (!$aTranscript['coding_end']? NULL : ($aTranscript['coding_end'] - $aTranscript['coding_start'] + 1)),
                        'length' => $aTranscript['length'],
                    )
                );
            }

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
        global $_SETT;

        if (empty($aOptions) || !is_array($aOptions)) {
            $aOptions = array();
        }

        // Don't send variants that are too big; VV can't currently handle them.
        if (function_exists('lovd_getVariantInfo')) {
            $aPositions = lovd_getVariantInfo(substr(strstr($sVariant, ':'), 1));
            // These sizes are approximate and slightly on the safe side;
            //  simple measurements have shown a maximum duplication size of
            //  250KB, and a max deletion of 900KB, requiring a full minute.
            // See: https://github.com/openvar/variantValidator/issues/151
            if ($aPositions
                && (($aPositions['type'] == 'dup' && ($aPositions['position_end'] - $aPositions['position_start']) > 200000)
                    || (substr($aPositions['type'], 0, 3) == 'del' && ($aPositions['position_end'] - $aPositions['position_start']) > 800000))) {
                // Variant too big, return error.
                $aReturn = $this->aResponse;
                $aReturn['errors']['ESIZETOOLARGE'] = 'This variant is currently too big to process. It will likely time out after a minute of waiting, so we won\'t send it to VariantValidator.';
                return $aReturn;
            }
        }

        // Append defaults for any remaining options.
        $aOptions = array_replace(
            array(
                'map_to_transcripts' => false, // Should we map the variant to transcripts?
                'predict_protein' => false,    // Should we get protein predictions?
                'lift_over' => false,          // Should we get other genomic mappings of this variant?
                'select_transcripts' => 'all', // Should we limit our output to only a certain set of transcripts?
            ),
            $aOptions);

        // Some options require others.
        // We want to map to transcripts also if we're asking for a liftover, and if we want protein prediction.
        $aOptions['map_to_transcripts'] = ($aOptions['map_to_transcripts'] || $aOptions['lift_over'] || $aOptions['predict_protein']);

        // Allow calling for any build, not just the one we are configured to use.
        // We always need to receive an NC anyway, so we can deduce the build (except for chrM).
        // We can pull this out of the database, but I prefer to rely on an array rather
        //  than a database, in case this object will ever be pulled out of LOVD.
        $sVariantNC = strstr($sVariant, ':', true);
        $sBuild = '';
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            if (isset($aBuild['ncbi_sequences'])) {
                if (in_array($sVariantNC, $aBuild['ncbi_sequences'])) {
                    // We pick the NCBI name here, because for chrM we actually
                    //  use GRCh37's NC_012920.1 instead of hg19's NC_001807.4.
                    $sBuild = $aBuild['ncbi_name'];
                    break;
                }
            }
        }
        // If we didn't get the build right here, then the whole call will fail.

        // Transcript list should be a list, or 'all'.
        if (!$aOptions['select_transcripts']
            || (!is_array($aOptions['select_transcripts']) && $aOptions['select_transcripts'] != 'all')) {
            $aOptions['select_transcripts'] = 'all';
        }

        $aJSON = $this->callVV('LOVD/lovd', array(
            'genome_build' => $sBuild,
            'variant_description' => $sVariant,
            'transcripts' => 'all',
            'select_transcripts' => (!is_array($aOptions['select_transcripts'])?
                $aOptions['select_transcripts'] :
                implode('|', $aOptions['select_transcripts'])),
            'check_only' => ($aOptions['predict_protein']?
                'False' : ($aOptions['map_to_transcripts']? 'tx' : 'True')),
            'lift_over' => ($aOptions['lift_over']? 'primary' : 'False'),
        ));
        if ($aJSON !== false && $aJSON !== NULL && !empty($aJSON[$sVariant])) {
            $aData = $this->aResponse;

            // Discard the meta data.
            $aJSON = $aJSON[$sVariant];

            // We'll copy the errors, but I've never seen them filled in, even with REF errors.
            $aData['errors'] = $aJSON['errors'];
            // Check the flag value.
            if ($aJSON['flag']) {
                switch ($aJSON['flag']) {
                    case 'genomic_variant_warning':
                        if ($aJSON[$sVariant]['genomic_variant_error']) {
                            // Clean off variant description.
                            $sError = str_replace($sVariant . ': ', '', $aJSON[$sVariant]['genomic_variant_error']);
                            // VV has declared their error messages are stable.
                            // This means we can parse them and rely on them not to change.
                            // Add error code if possible, so we won't have to parse the error message again somewhere.
                            if ($sError == 'Length implied by coordinates must equal sequence deletion length') {
                                // EINCONSISTENTLENGTH error.
                                $aData['errors']['EINCONSISTENTLENGTH'] = $sError;
                            } elseif (strpos($sError, 'is outside the boundaries of reference sequence') !== false) {
                                // ERANGE error.
                                $aData['errors']['ERANGE'] = $sError;
                            } elseif (strpos($sError, 'does not agree with reference sequence') !== false) {
                                // EREF error.
                                $aData['errors']['EREF'] = $sError;
                            } elseif (strpos($sError, 'is not associated with genome build') !== false) {
                                // EREFSEQ error.
                                $aData['errors']['EREFSEQ'] = $sError;
                            } elseif (substr($sError, 0, 5) == 'char ' || $sError == 'insertion length must be 1') {
                                // ESYNTAX error.
                                $aData['errors']['ESYNTAX'] = $sError;
                            } else {
                                // Unrecognized error.
                                $aData['errors'][] = $sError;
                            }
                            // When we have errors, we don't need 'data' filled in. Just return what I have.
                            return $aData;
                        }
                        break;
                    default:
                        // Unhandled flag. I know "processing_error" can be thrown, in theory.
                        $aData['errors']['EFLAG'] = 'VV Flag not recognized: ' . $aJSON['flag'] . '. This indicates a feature is missing in LOVD.';
                        break;
                }
            }
            // Discard the errors array and the flag value.
            $aJSON = $aJSON[$sVariant];

            // Copy the (corrected) DNA value.
            $aData['data']['DNA'] = $aJSON['g_hgvs'];
            // If description is given but different, then apparently there's been some kind of correction.
            if ($aData['data']['DNA'] && $sVariant != $aData['data']['DNA']) {
                // Check type of correction; silent, WCORRECTED, or WROLLFORWARD.
                if (function_exists('lovd_getVariantInfo')) {
                    // Use LOVD's lovd_getVariantInfo() to parse positions and type.
                    $sDNAOri = substr($sVariant, strlen($sVariantNC) + 1);
                    $aVariantOri = lovd_getVariantInfo($sDNAOri);
                    $sDNACorrected = substr($aData['data']['DNA'], strlen($sVariantNC) + 1);
                    $aVariantCorrected = lovd_getVariantInfo($sDNACorrected);
                    // Check for g.1_1del to g.1del.
                    $bRangeChanged = (substr_count($sDNAOri, '_') > substr_count($sDNACorrected, '_'));

                    if ($aVariantOri == $aVariantCorrected && !$bRangeChanged) {
                        // Positions and type are the same, small corrections like delG to del.
                        // We let these pass silently.
                    } elseif ($aVariantOri['type'] != $aVariantCorrected['type'] || $bRangeChanged) {
                        // An insertion actually being a duplication.
                        // A deletion-insertion which is actually something else.
                        // A g.1_1del that should be g.1del.
                        $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                    } else {
                        // Positions are different, but type is the same.
                        // 3' forwarding of deletions, insertions, duplications
                        //  and deletion-insertion events.
                        $aData['warnings']['WROLLFORWARD'] = 'Variant position' .
                            (!substr_count($sDNAOri, '_')? ' has' : 's have') .
                            ' been corrected.';
                    }

                } else {
                    // Not running as an LOVD object, just complain here.
                    $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                }
            }

            // Any errors given?
            if ($aJSON['genomic_variant_error']) {
                // Not a previously seen error, handled through the flag value.
                // We'll assume a warning.
                // FIXME: Value may need cleaning!
                // FIXME: Does this ever happen? Or is this only filled in when we also have a flag?
                $aData['warnings'][] = $aJSON['genomic_variant_error'];
            }

            // Mappings?
            $aData['data']['genomic_mappings'] = array();
            $aData['data']['transcript_mappings'] = array();
            if ($aJSON['hgvs_t_and_p']) {
                foreach ($aJSON['hgvs_t_and_p'] as $sTranscript => $aTranscript) {
                    if ($sTranscript != 'intergenic' && empty($aTranscript['transcript_variant_error'])) {
                        // We silently ignore transcripts here that gave us an error, but not for the liftover feature.
                        $aMapping = array(
                            'DNA' => '',
                            'RNA' => (!$aOptions['predict_protein']? '' : 'r.(?)'),
                            'protein' => '',
                        );
                        if ($aTranscript['gap_statement'] || $aTranscript['gapped_alignment_warning']) {
                            // Store this in warnings.
                            $sWarning = '';
                            if ($aTranscript['gap_statement']) {
                                $sWarning = rtrim($aTranscript['gap_statement'], '.') . '.';
                            }
                            if ($aTranscript['gapped_alignment_warning']) {
                                $sWarning .= (!$sWarning? '' : ' ') . rtrim($aTranscript['gapped_alignment_warning'], '.') . '.';
                            }
                            $aData['warnings']['WGAP'] = $sWarning;
                        }
                        if ($aTranscript['t_hgvs']) {
                            $aMapping['DNA'] = substr(strstr($aTranscript['t_hgvs'], ':'), 1);
                        }
                        if ($aTranscript['p_hgvs_tlc']) {
                            $aMapping['protein'] = substr(strstr($aTranscript['p_hgvs_tlc'], ':'), 1);
                        }

                        if ($aOptions['predict_protein']) {
                            // Try to improve VV's predictions.
                            $this->getRNAProteinPrediction($aMapping, $sTranscript);
                        }
                        $aData['data']['transcript_mappings'][$sTranscript] = $aMapping;
                    }

                    // Genomic mappings, when requested, are given per transcript (or otherwise as "intergenic").
                    if (empty($aTranscript['primary_assembly_loci'])) {
                        $aTranscript['primary_assembly_loci'] = array();
                    }

                    foreach ($aTranscript['primary_assembly_loci'] as $sBuild => $aMappings) {
                        // We support only the builds we have...
                        if (!isset($_SETT['human_builds'][$sBuild])) {
                            continue;
                        }

                        // There can be more than one mapping per build in theory...
                        foreach ($aMappings as $sRefSeq => $aMapping) {
                            $aData['data']['genomic_mappings'][$sBuild][] = $aMapping['hgvs_genomic_description'];
                        }
                    }

                    // Clean up duplicates from multiple transcripts.
                    foreach ($aData['data']['genomic_mappings'] as $sBuild => $aMappings) {
                        $aData['data']['genomic_mappings'][$sBuild] = array_unique($aMappings);
                    }
                }
            }
            return $aData;

        } else {
            // Failure.
            return false;
        }
    }





    public function verifyGenomicAndMap ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant and map it to transcripts as well.

        return $this->verifyGenomic($sVariant,
            array(
                'map_to_transcripts' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyGenomicAndLiftOver ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant and lift it over to other genome builds
        //  (through transcript mapping if possible).

        return $this->verifyGenomic($sVariant,
            array(
                'lift_over' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyGenomicAndPredictProtein ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant, map it to transcripts, and get protein predictions as well.

        return $this->verifyGenomic($sVariant,
            array(
                'predict_protein' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyVariant ($sVariant, $aOptions = array())
    {
        // Verify a variant, get mappings and protein predictions.
        // Uses the VariantValidator API, in practice for both genomic and
        //  transcript variants. For genomic variants, we're much happier using
        //  the LOVD endpoint (verifyGenomic()), so just use this method only
        //  for transcript variants.
        // For getting reference base verification, you'll need to pass the NC
        //  as well, in the format NC_000001.10(NM_123456.1):c.100del.
        // We don't want to add code to fetch the NC, since we don't want to use
        //  the database backend here in case we're used as an external lib.
        global $_CONF, $_SETT;

        // Disallow NC variants. We should verifyGenomic() for these.
        // Supporting NCs using this function will just take a lot more code,
        //  which wouldn't be useful. Fail hard, to teach users to not do this,
        //  but don't fail on NC_000001.10(NM_123456.1):c. variants.
        if (preg_match('/^NC_[0-9]+\.[0-9]+:/', $sVariant)) {
            return false;
        }

        if (empty($aOptions) || !is_array($aOptions)) {
            $aOptions = array();
        }

        // Append defaults for any remaining options.
        // VV doesn't have as many options as the LOVD endpoint, and honestly,
        //  selecting transcripts is only useful when we're using NC's as input.
        $aOptions = array_replace(
            array(
                'select_transcripts' => 'all', // Should we limit our output to only a certain set of transcripts?
            ),
            $aOptions);

        // We only need a genome build to resolve intronic variants.
        // The VV endpoint only throws a warning when an invalid build has been
        //  passed, but does continue. Try $_CONF, but be OK with it not there.
        if (isset($_CONF['refseq_build'])) {
            $sBuild = $_CONF['refseq_build'];
        } else {
            $sBuild = 'hg38';
        }

        // We pick the NCBI name here, because for chrM we actually
        //  use GRCh37's NC_012920.1 instead of hg19's NC_001807.4.
        // We can pull this out of the database, but I prefer to rely on an array rather
        //  than a database, in case this object will ever be pulled out of LOVD.
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            if ($sCode == $sBuild && isset($aBuild['ncbi_sequences'])) {
                $sBuild = $aBuild['ncbi_name'];
                break;
            }
        }

        // Transcript list should be a list, or 'all'.
        if (!$aOptions['select_transcripts']
            || (!is_array($aOptions['select_transcripts']) && $aOptions['select_transcripts'] != 'all')) {
            $aOptions['select_transcripts'] = 'all';
        }

        $aJSON = $this->callVV('VariantValidator/variantvalidator', array(
            'genome_build' => $sBuild,
            'variant_description' => $sVariant,
            'select_transcripts' => (!is_array($aOptions['select_transcripts'])?
                $aOptions['select_transcripts'] :
                implode('|', $aOptions['select_transcripts'])),
        ));
        if ($aJSON !== false && $aJSON !== NULL && !empty($aJSON['flag'])) {
            $aData = $this->aResponse;

            // Discard the meta data.
            unset($aJSON['metadata']);

            // Check the flag value. In contrast to the LOVD endpoint, the VV flag is always filled in.
            switch ($aJSON['flag']) {
                case 'error':
                    // VV failed completely. Nothing to do here...
                    return false;
                case 'gene_variant':
                    // All good. We can still have validation errors, but at least it's not a big warning.
                    break;
                case 'intergenic':
                    // This can only happen when passing NC-based variants.
                    // N[MR]-based variants that are outside of the transcript's
                    //  bounds are returning a warning flag.
                    // We choose not to support this. We could, but returning
                    //  False here will teach us to use verifyGenomic() instead.
                    return false;
                case 'warning':
                    // Something's wrong. Parse given warning and quit.
                    if ($aJSON['validation_warning_1']['validation_warnings']) {
                        foreach ($aJSON['validation_warning_1']['validation_warnings'] as $sError) {
                            // Clean off variant description.
                            // If we'd allow NCs here, we'd have valiations
                            //  warnings of *all* affected transcripts, repeated
                            //  for *all* transcripts. Just a huge array of
                            //  repeated errors. We'd have to make sure the
                            //  errors here would be about the transcript we're
                            //  analyzing now, but since we don't support NCs,
                            //  we don't need to worry about that now.
                            $sError = str_replace(
                                array(
                                    $sVariant . ': ',
                                    str_replace(array(strstr($sVariant, '(', true), '(', ')'), '', $sVariant) . ': '), '', $sError);

                            // VV has declared their error messages are stable.
                            // This means we can parse them and rely on them not to change.
                            // Add error code if possible, so we won't have to parse the error message again somewhere.
                            if (strpos($sError, 'Invalid genome build has been specified') !== false) {
                                // EBUILD error.
                                $aData['errors']['EBUILD'] = $sError;
                            } elseif ($sError == 'Length implied by coordinates must equal sequence deletion length') {
                                // EINCONSISTENTLENGTH error.
                                $aData['errors']['EINCONSISTENTLENGTH'] = $sError;
                            } elseif (strpos($sError, 'coordinates do not agree with the intron/exon boundaries') !== false) {
                                // EINVALIDBOUNDARY error.
                                $aData['errors']['EINVALIDBOUNDARY'] = $sError;
                            } elseif (strpos($sError, ' variant position that lies outside of the reference sequence') !== false
                                || strpos($sError, 'Variant coordinate is out of the bound of CDS region') !== false
                                || strpos($sError, 'The given coordinate is outside the bounds of the reference sequence') !== false) {
                                // ERANGE error. VV throws a range of different messages, depending on using NC-notation or not,
                                //  sending variants 5' or 3' of the transcript, or sending a CDS position that should be in the 3' UTR.
                                // VV doesn't auto-correct CDS positions outside of CDS, we will need to subtract the CDS length ourselves.
                                $aData['errors']['ERANGE'] = $sError;
                            } elseif (strpos($sError, 'does not agree with reference sequence') !== false) {
                                // EREF error.
                                $aData['errors']['EREF'] = $sError;
                            } elseif (strpos($sError, 'No transcript definition for') !== false) {
                                // EREFSEQ error.
                                $aData['errors']['EREFSEQ'] = $sError;
                            } elseif (substr($sError, 0, 5) == 'char ' || $sError == 'insertion length must be 1') {
                                // ESYNTAX error.
                                $aData['errors']['ESYNTAX'] = $sError;
                            } elseif ($sError == 'Uncertain positions are not currently supported') {
                                // EUNCERTAIN error.
                                $aData['errors']['EUNCERTAIN'] = $sError;
                                // FIXME: Asked already for having this in the LOVD endpoint as well - see #92.
                                //  Currently throws an ESYNTAX there.
                            } else {
                                // Unrecognized error.
                                $aData['errors'][] = $sError;
                            }
                        }
                        // When we have errors, we don't need 'data' filled in. Just return what I have.
                        return $aData;
                    }
                    break;
                // Handled all possible flags, no default needed.
            }
            // Discard the flag value.
            unset($aJSON['flag']);
            // If we'd allow NCs for this function, we'd be ending up with a
            //  possible array of NM mappings. However, since we sent only one
            //  NM, we end up with only one NM here.
            $aJSON = current($aJSON);

            // Add a warning in case we submitted a intronic variant while not
            //  using an NC reference sequence.
            if (preg_match('/^N[MR]_.+[0-9]+[+-][0-9]+/', $sVariant)) {
                $aData['warnings']['WINTRONICWITHOUTNC'] = 'Without using a genomic reference sequence, intronic bases can not be verified.' .
                    (!isset($aJSON['genome_context_intronic_sequence']) || !isset($aJSON['submitted_variant'])? ''
                        : ' Please consider passing the variant as ' .
                        strstr($aJSON['genome_context_intronic_sequence'], ':', true) . strstr($aJSON['submitted_variant'], ':') . '.');
            }

            // Copy the (corrected) DNA value.
            $aData['data']['DNA'] = $aJSON['hgvs_transcript_variant'];
            // If description is given but different, then apparently there's been some kind of correction.
            if ($aData['data']['DNA'] && $sVariant != $aData['data']['DNA']) {
                // Check type of correction; silent, WCORRECTED, or WROLLFORWARD.
                if (function_exists('lovd_getVariantInfo')) {
                    // Use LOVD's lovd_getVariantInfo() to parse positions and type.
                    $sDNAOri = substr(strstr($sVariant, ':'), 1);
                    $aVariantOri = lovd_getVariantInfo($sDNAOri);
                    $sDNACorrected = substr(strstr($aData['data']['DNA'], ':'), 1);
                    $aVariantCorrected = lovd_getVariantInfo($sDNACorrected);
                    // Check for c.1_1del to c.1del.
                    $bRangeChanged = (substr_count($sDNAOri, '_') > substr_count($sDNACorrected, '_'));

                    if ($aVariantOri == $aVariantCorrected && !$bRangeChanged) {
                        // Positions and type are the same, small corrections like delG to del.
                        // We let these pass silently.
                    } elseif ($aVariantOri['type'] != $aVariantCorrected['type'] || $bRangeChanged) {
                        // An insertion actually being a duplication.
                        // A deletion-insertion which is actually something else.
                        // A g.1_1del that should be g.1del.
                        $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                    } else {
                        // Positions are different, but type is the same.
                        // 3' forwarding of deletions, insertions, duplications
                        //  and deletion-insertion events.
                        $aData['warnings']['WROLLFORWARD'] = 'Variant position' .
                            (!substr_count($sDNAOri, '_')? ' has' : 's have') .
                            ' been corrected.';
                    }

                } else {
                    // Not running as an LOVD object, just complain here.
                    $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                }

                // Although the LOVD endpoint doesn't do this, the VV endpoint
                //  sometimes throws warnings when variants are corrected.
                // If we threw a warning, we can remove the VV warning.
                if ($aData['warnings'] && $aJSON['validation_warnings']) {
                    // Selectively search for the validation warning to remove,
                    //  in case there are multiple warnings.
                    foreach ($aJSON['validation_warnings'] as $nKey => $sWarning) {
                        if (strpos($sWarning, 'automapped to') !== false) {
                            // Toss this error.
                            unset($aJSON['validation_warnings'][$nKey]);
                            break;
                        }
                    }
                }
            }

            // Any errors given?
            if ($aJSON['validation_warnings']) {
                // Not a previously seen error, handled through the flag value.
                // We'll assume a warning.

                // VV throws two warnings for del100 variants, because of the '100'.
                if (($nKey = array_search('Trailing digits are not permitted in HGVS variant descriptions',
                        $aJSON['validation_warnings'])) !== false) {
                    // We silently skip these warnings.
                    unset($aJSON['validation_warnings'][$nKey]);
                    // Also unset the next line, which contains the link to the docs.
                    unset($aJSON['validation_warnings'][$nKey + 1]);
                }

                $aData['warnings'][] = $aJSON['validation_warnings'];
            }

            if ($aData['data']['DNA']) {
                // We silently ignore transcripts here that gave us an error, but not for the liftover feature.
                $aMapping = array(
                    'DNA' => substr(strstr($aData['data']['DNA'], ':'), 1),
                    'RNA' => 'r.(?)',
                    'protein' => '',
                );
                if ($aJSON['hgvs_predicted_protein_consequence']['tlr']) {
                    $aMapping['protein'] = substr(strstr($aJSON['hgvs_predicted_protein_consequence']['tlr'], ':'), 1);
                }

                // Try to improve VV's predictions.
                $sTranscript = strstr($sVariant, ':', true);
                $this->getRNAProteinPrediction($aMapping, $sTranscript);
                $aData['data'] = $aMapping;
            }

            // Mappings?
            $aData['data']['genomic_mappings'] = array();

            // Since we're in fact using GRCh37 instead of hg19, but our internal codes say hg19...
            // (this won't really affect us unless we'll have MT DNA working, but still...)
            if (isset($aJSON['primary_assembly_loci']['grch37'])) {
                $aJSON['primary_assembly_loci']['hg19'] = $aJSON['primary_assembly_loci']['grch37'];
            }

            foreach ($aJSON['primary_assembly_loci'] as $sBuild => $aMapping) {
                // We support only the builds we have...
                if (!isset($_SETT['human_builds'][$sBuild])) {
                    continue;
                }

                // verifyGenomic() makes an array here because multiple values can be expected.
                // We never will have multiple values, so just simplify the output and store a string.
                $aData['data']['genomic_mappings'][$sBuild] = $aMapping['hgvs_genomic_description'];
            }
            return $aData;

        } else {
            // Failure.
            return false;
        }
    }
}
?>
