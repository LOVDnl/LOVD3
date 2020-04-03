<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-09
 * Modified    : 2020-04-03
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

// NOTE: Getting g. mapping requires asking for c. mapping as well. Examples:
// https://www35.lamp.le.ac.uk/LOVD/lovd/hg19/NC_000017.10%3Ag.48275363C%3EA/refseq/all/True/primary?content-type=application%2Fjson
// vs intergenic:
// https://www35.lamp.le.ac.uk/LOVD/lovd/hg19/NC_000017.10%3Ag.14445090C%3EG/refseq/all/True/primary?content-type=application%2Fjson

// Internal server error:
// https://www35.lamp.le.ac.uk/LOVD/lovd/hg19/NC_000017.10%3Ag.1069645_1279669dup/refseq/all/True/primary?content-type=application%2Fjson

        // Allow calling for any build, not just the one we are configured to use.
        // We always need to receive an NC anyway, so we can deduce the build (except for chrM).
        // We can pull this out of the datebase, but I prefer to rely on an array rather
        //  than a database, in case this object will ever be pulled out of LOVD.
        $sVariantNC = substr($sVariant, 0, strpos($sVariant, ':'));
        $sBuild = '';
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            if (isset($aBuild['ncbi_sequences'])) {
                if (in_array($sVariantNC, $aBuild['ncbi_sequences'])) {
                    // We pick the NCBI name here, because for chrM we actually
                    //  use GRCh37's NC_012920.1 instead of hg19's NC_001807.4.
                    $sBuild = $aBuild['ncbi_name'];
                }
            }
        }

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
                // Flag is empty even when giving a delG on a REF:C and on roll forward errors.
                switch ($aJSON['flag']) {
                    // FIXME: Flag indicates warning, but genomic_variant_error suggests an error. The latter would be more useful.
                    case 'genomic_variant_warning':
                        // Seen with a REF error of a substitution.
                        if ($aJSON[$sVariant]['genomic_variant_error']) {
                            // Clean off variant description.
                            $sError = str_replace($sVariant . ': ', '', $aJSON[$sVariant]['genomic_variant_error']);
                            // VV has declared their error messages are stable.
                            // This means we can parse them and rely on them not to change.
                            // Add error code if possible, so we won't have to parse the error message again somewhere.
                            if (strpos($aJSON[$sVariant]['genomic_variant_error'], 'does not agree with reference sequence') !== false) {
                                // EREF error.
                                $aData['errors']['EREF'] = $sError;
                            } elseif (strpos($aJSON[$sVariant]['genomic_variant_error'], 'is not associated with genome build') !== false) {
                                // EBUILD error.
                                $aData['errors']['EREFSEQ'] = $sError;
                            } else {
                                // Unrecognized error.
                                $aData['errors'][] = $sError;
                            }
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
            // If description is given but different, then apparently there's been some kind of correction.
            if ($aJSON['g_hgvs'] && $sVariant != $aJSON['g_hgvs']) {
                // FIXME: You can actually compare the two values to see if this is a WROLLFORWARD or maybe delG to del or so. chrM is currently corrected to g. as well.
                $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
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
                    if ($sTranscript != 'intergenic') {
                        $aMapping = array(
                            'DNA' => '',
                            'RNA' => 'r.(?)',
                            'protein' => '',
                        );
                        // FIXME: Handle gap_statement and gapped_alignment_warning differently?
                        //  I think they're either both provided, or both not provided?
                        //  (requires mapping to be requested) Concatenating them for now.
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
                        // Check values, perhaps we can do better.
                        if (substr($aMapping['DNA'], -1) == '=') {
                            // DNA actually didn't change. Protein will indicate the same.
                            $aMapping['RNA'] = 'r.(=)';
                            // FIXME: VV returns p.(Ala86=) rather than p.(=); perhaps return r.(257=) instead of r.(=).
                            //  If you instead would like to make VV return p.(=), here is where you change this.
                            //  If you do, don't forget to check that you're on a coding transcript.
                        } elseif (in_array($aMapping['protein'], array('', 'p.?', 'p.(=)'))) {
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
                                    $aVariant['RNA'] = 'r.spl?';
                                    $aVariant['protein'] = 'p.?';

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
                        // FIXME: What to do with transcript_variant_error?
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
}
?>
