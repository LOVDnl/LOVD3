<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-15
 * Modified    : 2012-05-07
 * For LOVD    : 3.0-beta-05
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
 *               Ivar Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
require ROOT_PATH . 'inc-lib-form.php';
require ROOT_PATH . 'class/REST2SOAP.php';
$_MutalyzerWS = new REST2SOAP($_CONF['mutalyzer_soap_url']);
define('LOG_EVENT', 'AutomaticMapping');

// We want to finish the mapping, even if the connection with the browser is lost while working.
ignore_user_abort(true);

// Process only a limited number of variants at once.
$nRange = 100000;
$nMaxVariants = 25;


// Log errors only once every 5 minutes.
$tLogInterval = 300;





// PHP may lose the current working directory once it starts calling shutdown functions.
// Let's remember it.
define('WORKING_DIRECTORY', getcwd());
$aVariantUpdates = array(); // Array which will hold the variantIDs as keys that need to have the MAPPING_IN_PROGRESS removed when the script terminates.
function lovd_updateVariantsOnExit ()
{
    // This function will unset the MAPPING_IN_PROGRESS flag for all variants in $aVariantUpdates.
    // It is registered as a shutdown function to be sure the variants that were flagged IN_PROGRESS are returned to normal.
    global $aVariantUpdates, $_DB;

    // Restore the working directory.
    chdir(WORKING_DIRECTORY);
    
    if (!empty($aVariantUpdates)) {
        $_DB->query('UPDATE '. TABLE_VARIANTS . ' SET mapping_flags = mapping_flags & ~' . MAPPING_IN_PROGRESS . ' WHERE id IN(?' . str_repeat(', ?', count($aVariantUpdates) - 1) . ')', array_keys($aVariantUpdates));
    }
}
register_shutdown_function('lovd_updateVariantsOnExit');





function lovd_mapVariantToTranscripts (&$aVariant, $aTranscripts)
{
    // This function prepares a variant's mapping onto the given transcripts.
    // $aVariant is an array with the variant's data: required keys are 'id' and 'VariantOnGenome/DNA'.
    // $aTranscripts is a two-dimensional array; each transcript is an array with keys 'id' (optional) and 'id_ncbi' (mandatory).
    // Returns an associative array of the same length as $aTranscripts with the 'id_ncbi' values as keys, or FALSE on failure.
    // On success, each element contains an array with a ?-filled SQL query and an array of values. If the transcript ID is not given, the value array's element [1] contains NULL.
    // The value array's element [7], for the VariantOnTranscript/Protein column, contains an empty string and is to be overwritten by runMutalyzer output.
    // On failure of a single transcript, the corresponding element contains FALSE.
    global $_MutalyzerWS, $_CONF, $_SETT;
    static $aVariantsOnTranscripts = array();

    
    if (!isset($aVariant['id']) || !isset($aVariant['VariantOnGenome/DNA']) || !is_array($aTranscripts)) {
        // Invalid arguments.
        return false;
    }
    
    
    $aReturn = array();
    if (!empty($aTranscripts)) {
        
        // Get the variant descriptions in c. notation.
        $sVariant = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA'];
        if (!isset($aVariantsOnTranscripts[$sVariant])) {
            // We haven't run numberConversion for this variant before.
            $aVariantsOnTranscripts[$sVariant] = $_MutalyzerWS->moduleCall('numberConversion', array('build' => $_CONF['refseq_build'], 'variant' => $sVariant));
            if (!empty($aVariantsOnTranscripts[$sVariant]['string'])) {
                $aVariantsOnTranscripts[$sVariant] = $aVariantsOnTranscripts[$sVariant]['string'];
            } // If moduleCall fails (bad variant description?) it will return an empty string, so no 'else' needed here.
        }
        
        if (empty($aVariantsOnTranscripts[$sVariant]) || !is_array($aVariantsOnTranscripts[$sVariant])) {
            return false;
        }

        // Loop the transcripts and map the variant to them.
        foreach ($aTranscripts as $aTranscript) {
            if (empty($aTranscript['id_ncbi'])) {
                // A transcript without accession number is encountered. Invalid agruments, return false.
                return false;
            }
            if (!empty($aVariant['alreadyMappedTranscripts']) && in_array($aTranscript['id_ncbi'], $aVariant['alreadyMappedTranscripts'])) {
                // Skip transcripts to which the variant is already mapped.
                $aReturn[$aTranscript['id_ncbi']] = false;
                continue;
            }
            $aMappingInfo = lovd_getAllValuesFromArray('', $_MutalyzerWS->moduleCall('mappingInfo', array('LOVD_ver' => $_SETT['system']['version'], 'build' => $_CONF['refseq_build'], 'accNo' => $aTranscript['id_ncbi'], 'variant' => $aVariant['VariantOnGenome/DNA'])));
            if (!isset($aMappingInfo['startmain']) || $aMappingInfo['startmain'] === '') {
                if ($aMappingInfo['errorcode']) {
                    // Got an error from Mutalyzer.
                    $aVariant['errorDetected'] = true;
                }
                $aReturn[$aTranscript['id_ncbi']] = false;
                continue;
            }
            foreach ($aVariantsOnTranscripts[$sVariant] as $aVariantOnTranscript) {
                $sVariantOnTranscript = lovd_getValueFromElement('', $aVariantOnTranscript);
                if (substr($sVariantOnTranscript, 0, strlen($aTranscript['id_ncbi'])) == $aTranscript['id_ncbi']) {
                    // Got the variant description relative to this transcript.
                    $aReturn[$aTranscript['id_ncbi']] =
                         array(
                                'INSERT INTO ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' (id, transcriptid, effectid, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, `VariantOnTranscript/DNA`, `VariantOnTranscript/Protein`) VALUES (?, ?, 55, ?, ?, ?, ?, ?, ?)',
                                array($aVariant['id'], isset($aTranscript['id'])? $aTranscript['id'] : NULL, $aMappingInfo['startmain'], $aMappingInfo['startoffset'], $aMappingInfo['endmain'], $aMappingInfo['endoffset'], substr($sVariantOnTranscript, strpos($sVariantOnTranscript, ':c.') + 1), '')
                              );
                    continue 2;
                }
            }
            $aReturn[$aTranscript['id_ncbi']] = false;
        }
    }
    return $aReturn;
}





// Update progress data.
$_SESSION['mapping']['todo'] = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE mapping_flags & ' . MAPPING_ALLOW . ' AND NOT mapping_flags & ' . (MAPPING_NOT_RECOGNIZED | MAPPING_DONE) . ' AND position_g_start IS NOT NULL')->fetchColumn();
$_SESSION['mapping']['time_complete'] = 0;

// Now we unlock the session. We'll update the unmappable, todo and time_complete values
// when exiting the script. We do it this way because the mapping script is called
// asynchronously, but the session data is locked by PHP to prevent race conditions.
// This forces it to be come completely synchronous. Without closing the session, the
// user will not be able to do anything in LOVD until this script finishes.
session_write_close();

$sChromosome = null;
$nVariants = 0;



// Single variant mapping.
if (!empty($_GET['variantid'])) {
    // Hook this one variant into $aVariants, which is normally used for a set of variants.
    $aVariants = $_DB->query('SELECT id, chromosome, position_g_start, position_g_end, statusid, mapping_flags, created_by, `VariantOnGenome/DNA`, `VariantOnGenome/DBID` ' .
                             'FROM ' . TABLE_VARIANTS . ' WHERE id = ?', array($_GET['variantid']))->fetchAllAssoc();

    if (count($aVariants)) {
        if (($aVariants[0]['mapping_flags'] & MAPPING_ALLOW) && !($aVariants[0]['mapping_flags'] & MAPPING_IN_PROGRESS)) {
            // We've found the variant, and we are actually allowed to map it! Let's go.

            if ($aVariants[0]['position_g_start'] === null || $aVariants[0]['position_g_end'] === null) {
                // This variant is not going to be mappable until it's got valid positions!
                $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = mapping_flags | ' . MAPPING_NOT_RECOGNIZED . ' WHERE id = ?', array($aVariants[0]['id']));
                $aVariants = array();
            }

            // Flag the variant as MAPPING_IN_PROGRESS, clear the MAPPING_DONE, MAPPING_ERROR and MAPPING_NOT_RECOGNIZED flags too.
            $aVariantUpdates[$aVariants[0]['id']] = true;
            $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = (mapping_flags | ' . MAPPING_IN_PROGRESS . ') & ~' . (MAPPING_NOT_RECOGNIZED | MAPPING_ERROR | MAPPING_DONE) . ' WHERE id = ?', array($aVariants[0]['id']));
            if (!$q->rowCount()) {
                // There seems to be a race condition. Forget the variant we had selected, we do NOT want to do anything with it!
                $aVariantUpdates = $aVariants = array();
            } else {
                // The MAPPING_NOT_RECOGNIZED, MAPPING_ERROR and MAPPING_DONE flags will be set accordingly in the end. We must unset them here, however, otherwise whatever is set now stays set afterwards.
                $aVariants[0]['mapping_flags'] &= ~(MAPPING_NOT_RECOGNIZED | MAPPING_ERROR | MAPPING_DONE);
            }
        } else {
            // We can't map this variant, forget about it.
            $aVariants = array();
        }
    }



// Automatic mapping of variants in the background (not a specifically requested variant).
} elseif ($_SESSION['mapping']['todo'] > 0) {
    // Randomly select some adjacent variants that await mapping.
    $aVariants = $_DB->query('SELECT id, vog.chromosome, vog.position_g_start, position_g_end, statusid, mapping_flags, created_by, `VariantOnGenome/DNA`, `VariantOnGenome/DBID` ' .
                             'FROM ' . TABLE_VARIANTS . ' AS vog, (' .
                                 'SELECT chromosome, position_g_start ' .
                                 'FROM ' . TABLE_VARIANTS . ' ' .
                                 'WHERE mapping_flags & ' . MAPPING_ALLOW . ' AND NOT mapping_flags & ' . (MAPPING_NOT_RECOGNIZED | MAPPING_DONE | MAPPING_IN_PROGRESS) . ' AND position_g_start IS NOT NULL ' .
                                 'ORDER BY RAND() ' .
                                 'LIMIT 1' .
                             ') AS first ' .
                             'WHERE vog.chromosome = first.chromosome AND vog.position_g_start BETWEEN first.position_g_start AND first.position_g_start + ' . $nRange . ' ' .
                                 'AND mapping_flags & ' . MAPPING_ALLOW . ' AND NOT mapping_flags & ' . (MAPPING_NOT_RECOGNIZED | MAPPING_DONE | MAPPING_IN_PROGRESS) . ' ' .
                             'ORDER BY position_g_start ' .
                             'LIMIT ' . $nMaxVariants
                             )->fetchAllAssoc();

    if (count($aVariants)) {
        // First flag the variants as MAPPING_IN_PROGRESS.
        $sIDs = '';
        foreach ($aVariants as $aVariant) {
            $sIDs .= $aVariant['id'] . ', ';
            $aVariantUpdates[$aVariant['id']] = true;
        }
        $_DB->beginTransaction();
        $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = mapping_flags | ' . MAPPING_IN_PROGRESS . ' WHERE id IN(' . substr($sIDs, 0, -2) . ')');
        if ($q->rowCount() != count($aVariants)) {
            // We've selected a different number of variants than we're setting the flag for. This is NOT GOOD! Could be a race condition. Abort at once.
            $_DB->rollBack();
            $aVariantUpdates = $aVariants = array(); // Forget the variants we had selected, we do NOT want to do anything with them! At least some were already IN_PROGRESS.
        } else {
            $_DB->commit();
        }
    }
}





// We've selected one or more variants (either explicitly or though the automated process).
if (!empty($aVariants)) {
    $sChromosome = $aVariants[0]['chromosome'];
    $nVariants = count($aVariants);

    // Record the covered range.
    $nStart = $aVariants[0]['position_g_start'];
    $nEnd = $aVariants[count($aVariants) - 1]['position_g_end'];
    
    // We'll need a list of transcripts in the database on this chromosome.
    $aTranscriptsInLOVD = array();
    $qTranscriptsInLOVD = $_DB->query('SELECT t.id, geneid, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t JOIN ' . TABLE_GENES . ' AS g ON(g.id = t.geneid) WHERE chromosome = ?', array($sChromosome));
    while ($aTranscriptInLOVD = $qTranscriptsInLOVD->fetchAssoc()) {
        $aTranscriptsInLOVD[$aTranscriptInLOVD['geneid']][$aTranscriptInLOVD['id']] = array('id' => $aTranscriptInLOVD['id'], 'id_ncbi' => $aTranscriptInLOVD['id_ncbi']);
    }

    // Ask Mutalyzer about the transcripts within this range.
    $aTranscriptData = array();
    $aTranscriptsWithinRange = $_MutalyzerWS->moduleCall('getTranscriptsMapping', array('build' => $_CONF['refseq_build'], 'chrom' => 'chr' . $sChromosome, 'pos1' => $nStart, 'pos2' => $nEnd, 'method' => 1));
    if ($aTranscriptsWithinRange === false) {
        // Call failed due to network problems. Don't run the mapping script now!
        define('MAPPING_NO_RESTART', true);
        if (!empty($_GET['variantid'])) {
            // We were trying to map a specific variant. Set the MAPPING_ERROR flag so the user understands we tried it.
            $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET mapping_flags = ' . ($aVariants[0]['mapping_flags'] | MAPPING_ERROR) . ' WHERE id = ?', array($aVariants[0]['id']));
            $aVariantUpdates = array();
        }
        
        if (empty($_SESSION['mapping']['time_error']) || time() - $_SESSION['mapping']['time_error'] > $tLogInterval) {
            lovd_writeLog('Error', LOG_EVENT, 'Could not connect to the Mutalyzer getTranscriptsMapping webservice.');
        }
        
        // Forget the variants we had selected. This will skip the loop below and take us straight to the update-and-exit part.
        $aVariants = array();
        $nVariants = 0;
    }

    if (!empty($aTranscriptsWithinRange['TranscriptMappingInfo']) && is_array($aTranscriptsWithinRange['TranscriptMappingInfo'])){
        // Of the detected transcripts, we want to know their GENE and POSITIONS.
        foreach ($aTranscriptsWithinRange['TranscriptMappingInfo'] as $aTranscript) {
            $aTranscript = $aTranscript['c'];
            
            // Record the transcript accession, gene symbol and start and end positions.
            $sTranscriptNM = lovd_getValueFromElement('name', $aTranscript);
            $nVersion = lovd_getValueFromElement('version', $aTranscript);
            if (empty($aTranscriptData[$sTranscriptNM]) || $aTranscriptData[$sTranscriptNM]['version'] < $nVersion) {
                // Be sure to remember only the latest version!
                $aTranscriptData[$sTranscriptNM]['version'] = $nVersion;
                $aTranscriptData[$sTranscriptNM]['gene'] = lovd_getValueFromElement('gene', $aTranscript);
                $aTranscriptData[$sTranscriptNM]['start'] = lovd_getValueFromElement('start', $aTranscript);
                $aTranscriptData[$sTranscriptNM]['end'] = lovd_getValueFromElement('stop', $aTranscript);
                
                // We want 'start' to be lower than 'end' no matter the direction of the transcript.
                if ($aTranscriptData[$sTranscriptNM]['start'] > $aTranscriptData[$sTranscriptNM]['end']) {
                    list($aTranscriptData[$sTranscriptNM]['start'], $aTranscriptData[$sTranscriptNM]['end']) = array($aTranscriptData[$sTranscriptNM]['end'], $aTranscriptData[$sTranscriptNM]['start']);
                }
            }
        }
    }

    // Let's process the variants one by one and see if we can map them.
    foreach ($aVariants as $aVariant) {
        $aGenes = array();
        $aMappedToGenesInLOVD = array();

        // Find out on which transcripts this variant has been mapped already.
        $aVariant['alreadyMappedTranscripts'] = $_DB->query('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' AS t JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON(t.id = vot.transcriptid AND vot.id = ' . $aVariant['id'] . ')')->fetchAllColumn();

        foreach ($aTranscriptData as $sTranscriptNM => $aTranscript) {
            if (!empty($aFailedGenes[$aTranscript['gene']])) {
                // Skip genes of which we know for sure mapping is not going to work.
                continue;
            }

            // Test if variant lies within this transcript.
            if (($aVariant['position_g_start'] >= $aTranscript['start'] && $aVariant['position_g_start'] <= $aTranscript['end']) ||
                ($aVariant['position_g_end']   >= $aTranscript['start'] && $aVariant['position_g_end']   <= $aTranscript['end']) ||
                ($aVariant['position_g_start'] <  $aTranscript['start'] && $aVariant['position_g_end']   >  $aTranscript['end'])) {

                if (isset($aTranscriptsInLOVD[$aTranscript['gene']])) {
                    // We've got at least one transcript for the corresponding gene in the database.
                    if(!isset($aMappedToGenesInLOVD[$aTranscript['gene']])) {
                        // But only try it once. (NOTE: don't merge these if's because that breaks the elseif below!)

                        $sRefseqUD = $_DB->query('SELECT refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ?', array($aTranscript['gene']))->fetchColumn();
                        $aVariantOnTranscriptSQL = lovd_mapVariantToTranscripts($aVariant, $aTranscriptsInLOVD[$aTranscript['gene']]);
                        if (!empty($aVariantOnTranscriptSQL)) {
                            foreach ($aVariantOnTranscriptSQL as $sTranscriptNM => $aSQL) {
                                if (empty($aSQL)) {
                                    continue;
                                }

                                // Get the p. description too.
                                $sTranscriptNum = $_DB->query('SELECT id_mutalyzer FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($sTranscriptNM))->fetchColumn();
                                $aOutputRunMutalyzer = $_MutalyzerWS->moduleCall('runMutalyzer', array('variant' => $sRefseqUD . '(' . $aTranscript['gene'] . '_v' . $sTranscriptNum . '):' . $aSQL[1][6]));
                                $aVariantsOnProtein = lovd_getAllValuesFromArray('proteinDescriptions', $aOutputRunMutalyzer);
                                $aVariantsOnProteinError = lovd_getAllValuesFromArray('messages/SoapMessage', $aOutputRunMutalyzer);

                                // FIXME; Temporary fix!!! Wait for mutalyzer SOAP to return errors ONLY from the requested transcript.
                                if ($aVariantsOnProteinError['errorcode'] == 'WSPLICE' && $aVariantsOnProteinError['message'] == 'Mutation on splice site in gene ' . $aTranscript['gene'] . ' transcript ' . $sTranscriptNum . '.') {
                                    $aSQL[1][7] = 'p.?';
                                } else {
                                    if (!empty($aVariantsOnProtein['string'])) {
                                        if (!is_array($aVariantsOnProtein['string'])) {
                                            $aVariantsOnProtein['string'] = array($aVariantsOnProtein['string']);
                                        }
                                        foreach ($aVariantsOnProtein['string'] as $sVariantOnProtein) {
                                            if (($nPos = strpos($sVariantOnProtein, '_i' . $sTranscriptNum . '):p.')) !== false) {
                                                $aSQL[1][7] = substr($sVariantOnProtein, $nPos + strlen('_i' . $sTranscriptNum . '):'));
                                                break;
                                            }
                                        }
                                    }
                                }
                                if ($_DB->query($aSQL[0], $aSQL[1], false)) {
                                    // If the insert succeeded, save some data in the variant array for lovd_fetchDBID().
                                    $aVariant['aTranscripts'][$aSQL[1][1]] = array($sTranscriptNM, $aTranscript['gene']);
                                    $aVariant[$aSQL[1][1] . '_VariantOnTranscript/DNA'] = $aSQL[1][6];
                                }
                            }
                        }
                        // Remember we've mapped this variant to this gene, so we don't try to map it again.
                        $aMappedToGenesInLOVD[$aTranscript['gene']] = 1;
                    }

                // We don't have the gene that this transcript belongs to in the database yet.
                // Count it for now, we may need to add it later on if the variant can't be mapped to something that already exists in LOVD.
                } elseif (empty($aGenes[$aTranscript['gene']])) {
                    $aGenes[$aTranscript['gene']] = 1;
                } else {
                    $aGenes[$aTranscript['gene']] ++;
                }
            }
        }

        if (($aVariant['mapping_flags'] & MAPPING_ALLOW_CREATE_GENES) && count($aGenes)) {
            // We may add extra genes to map this variant to.

            // Try the genes one by one.
            foreach (array_keys($aGenes) as $sGene) {

                // Get information from HGNC.
                $aGeneInfoFromHgnc = lovd_getGeneInfoFromHgnc($sGene, array('gd_hgnc_id', 'gd_app_sym', 'gd_app_name', 'gd_pub_chrom_map', 'gd_locus_type', 'gd_pub_eg_id', 'md_mim_id', 'gd_pub_refseq_ids', 'md_refseq_id'), true);
                if (empty($aGeneInfoFromHgnc)) {
                    // Couldn't find this gene. Try the next.
                    continue;
                }
                list($sHgncID, $sSymbol, $sGeneName, $sChromLocation, $sLocusType, $sEntrez, $sOmim, $sRefseq1, $sRefseq2) = array_values($aGeneInfoFromHgnc);

                list($sEntrez, $sOmim) = array_map('trim', array($sEntrez, $sOmim));
                $sEntrez = (empty($sEntrez)? null : $sEntrez);
                $sOmim = (empty($sOmim)? null : $sOmim);
                $aRefseqsTranscript = array();
                if ($sRefseq1 != $sRefseq2 && !empty($aTranscriptData[$sRefseq1])) {
                    $aRefseqsTranscript[] = array('id_ncbi' => $sRefseq1 . '.' . $aTranscriptData[$sRefseq1]['version']);
                }
                if (!empty($aTranscriptData[$sRefseq2])) {
                    $aRefseqsTranscript[] = array('id_ncbi' => $sRefseq2 . '.' . $aTranscriptData[$sRefseq2]['version']);
                }
                if (empty($aRefseqsTranscript)) {
                    // The HGNC does not have a transcript accession for this gene. Get one from LOVD.
                    // FIXME; don't use file_get_contents() but instead lovd_php_file().
                    $sGeneLink = @substr($sGeneLink = @file_get_contents('http://www.lovd.nl/' . $sSymbol . '?getURL'), 0, @strpos($sGeneLink, "\n"));
                    $aGeneInfo = @explode("\n", @file_get_contents($sGeneLink . 'api/rest.php/genes/' . $sSymbol));
                    if (!empty($aGeneInfo) && is_array($aGeneInfo)) {
                        foreach ($aGeneInfo as $sLine) {
                            preg_match('/refseq_mrna[\s]*:[\s]*([\S]+\.[\S]+)/', $sLine, $aMatches);
                            if (!empty($aMatches)) {
                                $aRefseqsTranscript[] = array('id_ncbi' => $aMatches[1]);
                                break;
                            }
                        }
                    }
                }
                if (empty($aRefseqsTranscript)) {
                    // Couldn't get a good transcript accession for this gene. Try the next.
                    // FIXME; we'd better have a fallback transcript here (one from Mutalyzer for example) before we discard the gene as a whole.
                    continue;
                }
                
                // Split chromosomal location in chromosome and band.
                if ($sChromLocation == 'mitochondria') {
                    $sChromBand = '';
                } else {
                    preg_match('/^(\d{1,2}|[XY])(.*)$/', $sChromLocation, $aMatches);
                    $sChromBand = $aMatches[2];
                }


                // Got transcript RefSeqs, run prepare queries on them now to see if it succeeds.
                $aVariantOnTranscriptSQL = lovd_mapVariantToTranscripts($aVariant, $aRefseqsTranscript);
                $bSuccess = false;
                if (!empty($aVariantOnTranscriptSQL)) {
                    foreach ($aVariantOnTranscriptSQL as $aSQL) {
                        if (!empty($aSQL)) {
                            // Got one!
                            $bSuccess = true;
                            break;
                        }
                    }
                }
                if (!$bSuccess) {
                    // Couldn't map this variant to the relevant transcripts.
                    continue;
                }

                // Get LRG if it exists.
                if (!$sRefseqGenomic = lovd_getLRGbyGeneSymbol($sSymbol)) {
                    // No LRG, get NG if it exists.
                    if (!$sRefseqGenomic = lovd_getNGbyGeneSymbol($sSymbol)) {
                        // Also no NG, use the NC instead.
                        $sRefseqGenomic = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$sChromosome];
                    }
                }
                
                // Get UD.
                $sRef = $sRefseqUD = $_MutalyzerWS->moduleCall('sliceChromosomeByGene', array('geneSymbol' => $sSymbol, 'organism' => 'Man', 'upStream' => '5000', 'downStream' => '2000'));
                if (!is_string($sRefseqUD) || substr($sRefseqUD, 0, 3) != 'UD_') {
                    $sRefseqUD = false;
                    $sRef = $sRefseqGenomic;
                }
                
                // Get transcripts and info.
                $aTranscripts = lovd_getElementFromArray('TranscriptInfo', $_MutalyzerWS->moduleCall('getTranscriptsAndInfo', array('genomicReference' => $sRef, 'geneName' => $sSymbol)));
                if (empty($aTranscripts)) {
                    // Mutalyzer has no transcripts for this gene. Try the next.
                    continue;
                }
                
                
                
                // Try to get a matching transcript from Mutalyzer.
                $aFieldsTranscript = array();
                foreach ($aVariantOnTranscriptSQL as $sTranscriptNM => $aQuery) {
                    if (empty($aQuery)) {
                        // The variant can't be mapped here, ignore...
                        continue;
                    }
                    foreach ($aTranscripts as $aTranscript) {
                        $aTranscriptValues = lovd_getAllValuesFromArray('', $aTranscript['c']);
                        if ($aTranscriptValues['id'] == $sTranscriptNM) {
                            // We'll be inserting the transcript we got from the HGNC. It's either the most studied or the longest for this gene.

                            $aFieldsTranscript = array('geneid' => $sSymbol,
                                                       'name' => str_replace($sGeneName . ', ', '', $aTranscriptValues['product']),
                                                       'id_mutalyzer' => str_replace($sSymbol . '_v', '', $aTranscriptValues['name']),
                                                       'id_ncbi' => $aTranscriptValues['id'],
                                                       'id_protein_ncbi' => lovd_getValueFromElement('proteinTranscript/id', $aTranscript['c']),
                                                       'position_c_mrna_start' => $aTranscriptValues['cTransStart'],
                                                       'position_c_mrna_end' => $aTranscriptValues['sortableTransEnd'],
                                                       'position_c_cds_end' => $aTranscriptValues['cCDSStop'],
                                                       'position_g_mrna_start' => $aTranscriptData[substr($sTranscriptNM, 0, strpos($sTranscriptNM, '.'))]['start'],
                                                       'position_g_mrna_end' => $aTranscriptData[substr($sTranscriptNM, 0, strpos($sTranscriptNM, '.'))]['end'],
                                                       'created_by' => 0,
                                                       'created_date' => date('Y-m-d H:i:s'));
                            break 2;
                        }
                    }
                }

                // If we've got a transcript, see if we can map the variant onto it.
                if (!empty($aFieldsTranscript)) {
                    // Mapping is going to succeed! Let's add this gene and transcript.
                    $aVariantOnTranscriptSQL = $aVariantOnTranscriptSQL[$aFieldsTranscript['id_ncbi']];

                    // But first check if the gene was already there without transcripts.
                    if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE id = ?', array($sSymbol))->fetchColumn()) {
                        $aFields = array('id' => $sSymbol,
                                         'name' => $sGeneName,
                                         'chromosome' => $sChromosome,
                                         'chrom_band' => $sChromBand,
                                         'refseq_genomic' => $sRefseqGenomic,
                                         'refseq_UD' => $sRefseqUD,
                                         'id_hgnc' => $sHgncID,
                                         'id_entrez' => $sEntrez,
                                         'id_omim' => $sOmim,
                                         'show_hgmd' => 1,
                                         'show_genecards' => 1,
                                         'show_genetests' => 1,
                                         'disclaimer' => 1,
                                         'created_by' => 0,
                                         'created_date' => date('Y-m-d H:i:s'));
                        $_DB->query('INSERT INTO ' . TABLE_GENES . ' (' . implode(', ', array_keys($aFields)) . ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')', array_values($aFields));
                    
                        // Only assign newly inserted genes to managers. If the creator of the variant is not a manager, make the database admin the curator for this gene.
                        if (empty($aManagerList)) {
                            // Building the list of managers only once.
                            $aManagerList = $_DB->query('SELECT id FROM ' . TABLE_USERS . ' WHERE level >= ' . LEVEL_MANAGER . ' ORDER BY level DESC')->fetchAllColumn();
                        }
                        $nCurator = (array_search($aVariant['created_by'], $aManagerList) !== false? $aVariant['created_by'] : $aManagerList[0]);
                        $_DB->query('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?)', array($nCurator, $sSymbol, 1, 1));
                        
                        // Also activate default custom columns for this gene.
                        lovd_addAllDefaultCustomColumnsForGene($sSymbol, false);
                    }
                    
                    // Now insert the transcript.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_TRANSCRIPTS . ' (' . implode(', ', array_keys($aFieldsTranscript)) . ') VALUES (?' . str_repeat(', ?', count($aFieldsTranscript) - 1) . ')', array_values($aFieldsTranscript));
                                
                    if ($q->rowCount()) {
                        // Get the ID of the newly inserted transcript.
                        $nID = $_DB->lastInsertId();
                    } else {
                        // This transcript was just added by a concurrent call to the mapping script. Get its ID and map on.
                        $nID = $_DB->query('SELECT id FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?', array($aFieldsTranscript['id_ncbi']))->fetchColumn();
                    }

                    // Get the p. description too.
                    $aVariantsOnProtein = lovd_getAllValuesFromArray('proteinDescriptions', $_MutalyzerWS->moduleCall('runMutalyzer', array('variant' => $sRefseqUD . '(' . $sSymbol . '_v' . $aFieldsTranscript['id_mutalyzer'] . '):' . $aVariantOnTranscriptSQL[1][6])));
                    if (!empty($aVariantsOnProtein['string'])) {
                        if (!is_array($aVariantsOnProtein['string'])) {
                            $aVariantsOnProtein['string'] = array($aVariantsOnProtein['string']);
                        }
                        foreach ($aVariantsOnProtein['string'] as $sVariantOnProtein) {
                            if (($nPos = strpos($sVariantOnProtein, '_i' . $aFieldsTranscript['id_mutalyzer'] . '):p.')) !== false) {
                                $aVariantOnTranscriptSQL[1][7] = substr($sVariantOnProtein, $nPos + strlen('_i' . $aFieldsTranscript['id_mutalyzer'] . '):'));
                            }
                        }
                    }

                    // Map the variant to the newly inserted transcript.
                    $aVariantOnTranscriptSQL[1][1] = $nID;
                    if ($_DB->query($aVariantOnTranscriptSQL[0], $aVariantOnTranscriptSQL[1], false)) {
                        $aVariant['aTranscripts'][$nID] = array($aFieldsTranscript['id_ncbi'], $sSymbol);
                        $aVariant[$nID . '_VariantOnTranscript/DNA'] = $aVariantOnTranscriptSQL[1][6];
                    }

                    // Also remember that we've got this gene and transcript now.
                    $aTranscriptsInLOVD[$sSymbol][$nID] = array('id' => $nID, 'id_ncbi' => $aFieldsTranscript['id_ncbi']);

                } else {
                    // Mutalyzer does not have the transcript we're looking for. Don't retry this gene!
                    $aFailedGenes[$sGene] = true;
                }
            }
        }
        
        // Try to get a DBID if the variant doesn't have one.
        if (empty($aVariant['VariantOnGenome/DBID'])) {
            // Also set the DBID if that's possible.
            $sDBID = lovd_fetchDBID($aVariant);
        } else {
            $sDBID = null;
        }
            
        // Now see if the above script actually mapped it and define the update query.
        if (!empty($aVariant['aTranscripts'])) {
            // It did.
            $sUpdateSQL = 'mapping_flags = ' . ($aVariant['mapping_flags'] | MAPPING_DONE) . ', edited_by = 0, edited_date = NOW()';
        } elseif (empty($aVariant['alreadyMappedTranscripts']) && !empty($aVariant['errorDetected'])) {
            // This variant cannot be mapped, probably because it's malformed.
            $sUpdateSQL = 'mapping_flags = ' . ($aVariant['mapping_flags'] | MAPPING_NOT_RECOGNIZED);
        } else {
            // This variant can't be mapped to anything (other than what it is mapped to already) right now.
            $sUpdateSQL = 'mapping_flags = ' . ($aVariant['mapping_flags'] | MAPPING_DONE);
        }
        
        // Update the variant.
        if (!empty($sDBID)) {
            $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET ' . $sUpdateSQL . ', `VariantOnGenome/DBID` = ? WHERE id = ?', array($sDBID, $aVariant['id']));
        } else {
            $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET ' . $sUpdateSQL . ' WHERE id = ?', array($aVariant['id']));
        }
        unset($aVariantUpdates[$aVariant['id']]);
    }
}



// Finally, we have the script finish with a little status update so the browser
// knows whether it should call the script again.


// Get the newest data from the session files and update that.
session_start();

// Update todo counter.
$_SESSION['mapping']['todo'] -= $nVariants;

// Compute progress percentage - but only percentages for which we have an image (+6.25% each) are possible.
$nTotalVariants = (int) $_DB->query('SELECT COUNT(id) FROM ' . TABLE_VARIANTS . ' WHERE mapping_flags & ' . MAPPING_ALLOW)->fetchColumn();
$nMappedVariants = $nTotalVariants - $_SESSION['mapping']['todo'];
if ($nTotalVariants == 0) {
    $nPercentage = 99;
} else {
    $nPercentage = round(floor($nMappedVariants / $nTotalVariants * 100 / 6.25) * 6.25);
    $nPercentage = $nPercentage == 100? 99 : $nPercentage;
}

// Output the current progress.
if ($nMappedVariants >= $nTotalVariants || (!isset($_GET['variantid']) && !defined('MAPPING_NO_RESTART') && $nVariants == 0)) {
    // Mapped all variants, or the last ones were IN_PROGRESS in a different instance of the script.
    // To prevent a flood of AJAX requests in the latter case, we'll just report them as finished.

    // Remembering the completion time to prevent any automatic calls within a day.
    $_SESSION['mapping']['time_complete'] = time();
    if ($nTotalVariants == 0) {
        exit(AJAX_FALSE . "\t99\tThere are no variants to map in the database");
    }
    exit(AJAX_FALSE . "\t99\tSuccessfully mapped " . $nTotalVariants . ' variant' . ($nTotalVariants == 1? '' : 's'));
} elseif (defined('MAPPING_NO_RESTART')) {
    // There were network problems during this request.
    $_SESSION['mapping']['time_error'] = time();
    exit(AJAX_FALSE . "\t" . sprintf('%02d', $nPercentage) . "\tMapped " . round($nMappedVariants / $nTotalVariants * 100) . '% of ' . $nTotalVariants . ' variant' . ($nTotalVariants == 1? '' : 's') . '; ' .
         'temporarily suspended because of network problems on the last attempt. Click to retry.');
}
exit(AJAX_TRUE . "\t" . sprintf('%02d', $nPercentage) . "\tMapped " . round($nMappedVariants / $nTotalVariants * 100) . '% of ' . $nTotalVariants . ' variant' . ($nTotalVariants == 1? '' : 's'));
?>
