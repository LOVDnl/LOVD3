<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-04-09
 * Modified    : 2020-04-17
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-form.php'; // For lovd_setUpdatedDate().

// We don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();

define('PAGE_TITLE', 'Fix variant descriptions');
$_T->printHeader();
$_T->printTitle();
lovd_showInfoTable('This script is designed to verify and check all variant descriptions in the database,
     both genomic and transcript-based, using the new Variant Validator API object.
    Variant Validator does a better job at mapping genomic to transcript-based variants than
     the Mutalyzer service, which is currently still the LOVD default.
    Also, not all submitters use the variant mapping or prediction buttons and might submit incorrect variants.');

lovd_requireAUTH(LEVEL_MANAGER);





// Class for the Analyses.
class LOVD_VVAnalyses {
    protected $oBarTotal = NULL;        // Progress bar for total progress.
    protected $oBarMemory = NULL;       // Progress bar for memory usage.
    protected $oBarChromosome = NULL;   // Progress bar for current chromosome's progress.

    protected $nProgressTotal = 0;      // Percentage of total progress (0-1).
    protected $nProgressCount = 0;      // Number of variants done for this chromosome.
    protected $nProgress = 0;           // Percentage of progress on this chromosome (0-1).
    protected $nMemoryUsage = 0;        // Percentage of allowed memory that we're using (0-1).
    protected $nMaxMemory = 0;          // The maximum allowed memory (slightly lowered for safety).
    protected $aChromosomes = array();  // List of chromosomes that have data, and their counts.
    protected $nVariantsTotal = 0;      // Sum of $aChromosomes, total number of variants in the DB.
    protected $sCurrentChromosome = ''; // Which chromosome are we working on now?
    protected $nCurrentPosition = 0;    // What position are we working on now?
    protected $aCache = array();        // Stores VV cache. Will be cleaned now and then when the memory usage is too high.

    // Numbers previously reported. If different from current values, we should update the stats.
    // All these numbers are two decimal floats (0-1), except for the position field.
    protected $nProgressTotalReported = 0;
    protected $nProgressReported = 0;
    protected $nMemoryUsageReported = 0;
    protected $nCurrentPositionReported = 0;

    protected $bDNA38 = false;          // Do we have the hg38 field active?





    public function __construct ($sCurrentChromosome = '', $nCurrentPosition = 0)
    {
        // Constructor that will print the tables on the page and load the progress bars.
        global $_DB;

        // Check the arguments we have received.
        // Query takes 0.9 seconds on shared - acceptable.
        $this->aChromosomes = $_DB->query('
            SELECT c.name, COUNT(*)
            FROM ' . TABLE_CHROMOSOMES . ' AS c
                INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (c.name = vog.chromosome)
            GROUP BY c.name
            ORDER BY c.sort_id')->fetchAllCombine();
        // Take given chromosome if we have it in our list, or the first one otherwise.
        $this->sCurrentChromosome = (!isset($this->aChromosomes[$sCurrentChromosome])?
            key($this->aChromosomes) : $sCurrentChromosome);
        // Check if the given position is numeric, default to 1.
        // Starting at one has the advantage of skipping all of these non-HGVS variants with position = 0.
        $this->nCurrentPosition = (!$nCurrentPosition
            || (!is_int($nCurrentPosition) && !ctype_digit($nCurrentPosition))?
            1 : (int) $nCurrentPosition);
        $this->nVariantsTotal = array_sum($this->aChromosomes);

        // Setting up the progress bars will print them already,
        //  so I need to print the page's structure around it.
        require_once ROOT_PATH . 'class/progress_bar.php';
        print('
      <TABLE class="data S13" id="VV_analyses">
        <TR>
          <TH>Total progress</TH>
          <TD>' . "\n");
        $this->oBarTotal = new ProgressBar('total');

        print('
          </TD></TR>
        <TR>
          <TH>Memory usage</TH>
          <TD>' . "\n");
        $this->oBarMemory = new ProgressBar('memory');

        print('
          </TD></TR>
        <TR>
          <TH>Progress on chromosome</TH>
          <TD>' . "\n");
        $this->oBarChromosome = new ProgressBar('chromosome');

        print('
          </TD></TR>
        <TR id="tr_stats">
          <TH>Next to analyze</TH>
          <TD>chr' . $sCurrentChromosome . ':' . $nCurrentPosition . '</TD></TR></TABLE>
          
      <SCRIPT type="text/javascript">
        // Remove space below the progress bars.
        $("#VV_analyses").find("br").remove();
      </SCRIPT>' . "\n\n");

        // Store if we have hg38 annotation or not (GV shared had a custom column for that).
        $this->bDNA38 = (bool) $_DB->query('
            SELECT COUNT(*)
            FROM ' . TABLE_ACTIVE_COLS . '
            WHERE colid = ?',
            array('VariantOnGenome/DNA/hg38'))->fetchColumn();

        // Get proper progress count - how much is behind us already for this chromosome?
        $this->nProgressCount = $_DB->query('
                SELECT COUNT(*)
                FROM ' . TABLE_VARIANTS . '
                WHERE chromosome = ? AND position_g_start < ?',
            array($this->sCurrentChromosome, $this->nCurrentPosition))->fetchColumn();

        // We'll be sending a lot of updates, so stop all buffering.
        flush();
        @ob_end_flush(); // Can generate errors on the screen if no buffer found.

        // Update stats as a start.
        $this->updateStats();
    }





    protected function panic ($aVariant, $aVV, $sPanic)
    {
        // Bail out; we don't know how to handle this variant.
        // Just dump the error to the screen and quit.

        // List the issues we found.
        $aDiff = array(
            'panic' => $sPanic,
            $aVariant['DNA'] => (!isset($aVV['data']['DNA_clean'])? '' : $aVV['data']['DNA_clean']),
            'transcripts' => array(),
        );
        if ($this->bDNA38) {
            $aDiff[(!$aVariant['DNA38']? '(DNA38)' : $aVariant['DNA38'])] =
                (!isset($aVV['data']['genome_mappings']['hg38']['DNA'])? '' : $aVV['data']['genome_mappings']['hg38']['DNA']);
        }
        foreach ($aVariant['vots'] as $sTranscript => $aVOT) {
            $aDiff['transcripts'][$sTranscript] = array(
                (!$aVOT['DNA']? '(DNA)' : $aVOT['DNA']) => (!isset($aVV['data']['transcript_mappings'][$sTranscript])? '' : $aVV['data']['transcript_mappings'][$sTranscript]['DNA']),
                (!$aVOT['RNA']? '(RNA)' : $aVOT['RNA']) => (!isset($aVV['data']['transcript_mappings'][$sTranscript])? '' : $aVV['data']['transcript_mappings'][$sTranscript]['RNA']),
                (!$aVOT['protein']? '(protein)' : $aVOT['protein']) => (!isset($aVV['data']['transcript_mappings'][$sTranscript])? '' : $aVV['data']['transcript_mappings'][$sTranscript]['protein']),
            );
        }
        $sDiff = print_r($aDiff, true);
        die('<PRE>' . $sDiff . '</PRE>');
    }





    public function runAnalyses ()
    {
        // Runs the analyses, updates the stats that should have been printed already, and runs the fixes.
        global $_CONF, $_DB, $_SETT;

        // Indicate we're working.
        print('
      <SCRIPT type="text/javascript">
        $("#tr_stats th").html("Working on ...");
      </SCRIPT>');

        // Load VV.
        require ROOT_PATH . 'class/variant_validator.php';
        $_VV = new LOVD_VV();
        if (!$_VV->test()) {
            print('
      <SCRIPT type="text/javascript">
        $("#tr_stats td").html("<B style=\"color : #FF0000;\">Failure testing VV API.</B>");
      </SCRIPT>');
            exit;
        }

        // For running updates.
        require ROOT_PATH . 'class/object_genome_variants.php';
        require ROOT_PATH . 'class/object_transcript_variants.php';
        $_DATA = array(
            'Genome' => new LOVD_GenomeVariant(),
            'Transcript' => NULL, // Will be reloaded for each variant we need to edit.
        );

        // I'm not too happy making a eternal loop here, but I also don't want
        //  to retrieve all positions from the database.
        // As long as I make sure the loop will quit, I should be fine.
        while (true) {
            // Count how much there is left to do.
            $nLeft = $_DB->query('
                SELECT COUNT(*)
                FROM ' . TABLE_VARIANTS . '
                WHERE chromosome = ? AND position_g_start >= ?',
                array($this->sCurrentChromosome, $this->nCurrentPosition))->fetchColumn();
            if (!$nLeft) {
                // We're done with this chromosome, move on to the next.
                // We do so automatically, by redirecting. We don't care that the
                //  cache will be emptied, we won't need it anymore anyway and it's
                //  a fast way to clean it up.
                $nNextKey = array_search($this->sCurrentChromosome, array_keys($this->aChromosomes)) + 1;
                if ($nNextKey >= count($this->aChromosomes)) {
                    // Actually, we're done.
                    $this->updateStats(true);
                    exit;
                }

                $sNextChromosome = key(array_slice($this->aChromosomes, $nNextKey, 1, true));
                // Redirect the page. Since we've had output already, use the progress bar to do this.
                $this->oBarTotal->redirectTo(lovd_getInstallURL() . CURRENT_PATH . '?run&chromosome=' . $sNextChromosome, 0);
                exit;
            }

            // Get next position to work on.
            $nNextPosition = $_DB->query('
                SELECT position_g_start
                FROM ' . TABLE_VARIANTS . '
                WHERE chromosome = ? AND position_g_start >= ?
                ORDER BY chromosome, position_g_start LIMIT 1',
                array($this->sCurrentChromosome, $this->nCurrentPosition))->fetchColumn();
            // Check if we got a position, for the small chance that our last database entry suddenly just got removed...
            if ($nNextPosition) {
                $this->nCurrentPosition = $nNextPosition;
            }

            // Fetch data for this position.
            $aVariants = $_DB->query('
                SELECT vog.id, vog.statusid, vog.`VariantOnGenome/DNA` AS DNA, ' .
                    (!$this->bDNA38? '' : 'vog.`VariantOnGenome/DNA/hg38` AS DNA38, ') .
                    'GROUP_CONCAT(vot.transcriptid, "||", t.id_ncbi, "||", IFNULL(vot.`VariantOnTranscript/DNA`, ""), "||", IFNULL(vot.`VariantOnTranscript/RNA`, ""), "||", IFNULL(vot.`VariantOnTranscript/Protein`, "") SEPARATOR ";;") AS __vots
                FROM ' . TABLE_VARIANTS . ' AS vog
                    LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)
                    LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                WHERE vog.chromosome = ? AND vog.position_g_start = ? GROUP BY vog.id',
                array($this->sCurrentChromosome, $this->nCurrentPosition))->fetchAllAssoc();
            // Explode vots, and explode transcriptid, DNA, RNA, protein.
            $aVariants = array_map(
                function ($aVariant)
                {
                    // Explode vots.
                    $aVariant['vots'] = array();
                    foreach (array_map(
                        function ($sVOT)
                        {
                            // Explode VOT.
                            $aVOT = array();
                            if ($sVOT) {
                                list($aVOT['transcriptid'], $aVOT['id_ncbi'], $aVOT['DNA'], $aVOT['RNA'], $aVOT['protein']) = explode('||', $sVOT);
                            }
                            return $aVOT;
                        }, explode(';;', $aVariant['__vots'])) as $aVOT) {
                        if ($aVOT) {
                            $sTranscript = $aVOT['id_ncbi'];
                            unset($aVOT['id_ncbi']);
                            $aVariant['vots'][$sTranscript] = $aVOT;
                        }
                    }
                    unset($aVariant['__vots']);
                    return $aVariant;
                }, $aVariants);



            // Loop variants, apply fixes when possible.
            foreach ($aVariants as $aVariant) {
                // Update stats.
                $this->updateStats();
                usleep(250000); // FIXME: Remove later.

                // Call VV and get all information we need; mappings to
                //  transcripts, protein predictions and even mappings to hg38
                //  if we have that field.
                $sCurrentRefSeq = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$this->sCurrentChromosome];
                $sVariant = $sCurrentRefSeq . ':' . $aVariant['DNA'];
                if (!isset($this->aCache[$sVariant])) {
                    $aVV = $_VV->verifyGenomic($sVariant,
                        array(
                            'map_to_transcripts' => true,
                            'predict_protein' => true,
                            'select_transcripts' => array_keys($aVariant['vots']), // Restrict transcripts to speed up liftover.
                            'lift_over' => ($_CONF['refseq_build'] == 'hg19' && $this->bDNA38),
                        ));
                    // This also stores failures, so we won't repeat these.
                    $this->aCache[$sVariant] = $aVV;
                } else {
                    $aVV = $this->aCache[$sVariant];
                }

                // Check result.
                if (!$aVV) {
                    // VV failed. Either we have a really shitty variant, or VV broke.
                    // Nonetheless, it's easier to just continue.
                    // Just give a notice about it, and continue.
                    print('
      <SCRIPT type="text/javascript">
        $("#tr_stats td").html("<B style=\"color : #FF0000;\">VV failed on ' . $sVariant . '. Ignoring...</B>");
      </SCRIPT>');
                    flush();
                    sleep(5); // To make it visible for a while.
                    $this->nProgressCount ++; // To show progress.
                    continue; // Then continue to the next variant.
                }

                // Do we have something to update?
                $aUpdate = array();



                if ($aVV['errors']) {
                    // Handle EREF errors and the like.
                    if (isset($aVV['errors']['EREF'])) {
                        // EREF error; the genomic variant can not be correct.
                        // Loop the cDNA variants, if they are valid (all of them),
                        //  and the cDNA variant(s) are on the same chromosome,
                        //  then correct the genomic variant, it's probably wrong.
                        // It couldn't have been the source, since it's not valid.
                        // Anyway people will get an email when we change things,
                        //  so it can always be reversed when it's wrong.

                        // Collect alternative descriptions based on the VOTs.
                        $aMappedAlternatives = array();

                        foreach ($aVariant['vots'] as $sTranscript => $aVOT) {
                            // Because VV is in error, it didn't provide any mappings.
                            // Just reverse the mapping, check if the result is on
                            //  the same chromosome as the genomic input, and continue.
                            if (!isset($this->aCache[$sTranscript . ':' . $aVOT['DNA']])) {
                                $aVVVot = $_VV->verifyVariant($sTranscript . ':' . $aVOT['DNA']);
                                // This also stores failures, so we won't repeat these.
                                $this->aCache[$sTranscript . ':' . $aVOT['DNA']] = $aVVVot;
                            } else {
                                $aVVVot = $this->aCache[$sTranscript . ':' . $aVOT['DNA']];
                            }

                            // Check result.
                            if (!$aVVVot || $aVVVot['errors']) {
                                // VV failed. Either we have a really shitty variant, or VV broke.
                                // EREF *and* VOT fails. Just panic here.
                                $this->panic($aVariant, $aVV, 'While handling EREF error, VV failed on VOT; this variant needs manual curation.');
                            }

                            // All we ask is that the transcript is found on the same chromosome.
                            // If not, this is probably an import error where the wrong
                            //  transcript ID was selected. Yes, even if the chromosome is the same,
                            //  this might be the case. But we have to draw the line somewhere.
                            $sMappedRefSeq = strstr($aVVVot['data']['genomic_mappings'][$_CONF['refseq_build']], ':', true);
                            if ($sCurrentRefSeq != $sMappedRefSeq) {
                                $this->panic($aVariant, $aVV, 'While handling EREF error, found that LOVD\'s mapping is on a transcript on a different chromosome (' . $sCurrentRefSeq . ' => ' . $sMappedRefSeq . ').');
                            }

                            // Store this mapping, so we can see if all VOTs agree.
                            $aMappedAlternatives[] = substr(strstr($aVVVot['data']['genomic_mappings'][$_CONF['refseq_build']], ':'), 1);

                            // Also check the VOT itself. Perhaps its DNA should be different.
                            if ($aVOT['DNA'] != $aVVVot['data']['DNA']) {
                                // Handle this update the same way you normally would, if there would have been no EREF.
                                if (!isset($aUpdate['transcripts'])) {
                                    $aUpdate['transcripts'] = array();
                                }
                                if (!isset($aUpdate['transcripts'][$sTranscript])) {
                                    $aUpdate['transcripts'][$sTranscript] = array();
                                }
                                $aUpdate['transcripts'][$sTranscript]['DNA'] = $aVVVot['data']['DNA'];

                                // Overwrite the RNA field if it's different and not so interesting.
                                if ($aVOT['RNA'] != $aVVVot['data']['RNA']) {
                                    if (in_array($aVOT['RNA'], array('', 'r.(?)'))) {
                                        $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                    } else {
                                        // We don't know what to do here.
                                        $this->panic($aVariant, $aVV, 'While handling EREF error, found that also cDNA and RNA are different; cDNA can be fixed, but I don\'t know what to do with the RNA field.');
                                    }
                                }

                                // Right now, we don't overwrite the protein field. We just check if it's different, and panic if needed.
                                if (str_replace('*', 'Ter', $aVOT['protein']) != $aVV['data']['protein']) {
                                    // We don't know what to do here.
                                    $this->panic($aVariant, $aVV, 'While handling EREF error, found that also cDNA and protein are different; cDNA can be fixed, but I don\'t know what to do with the protein field.');
                                }
                            }

                            // We don't check RNA or protein if the DNA is the same. Or will we?

                            // Store mapping for further processing.
                            if (!isset($aVV['data']['genomic_mappings'])) {
                                $aVV['data']['genomic_mappings'] = array(
                                    'hg38' => array(),
                                );
                            }
                            $aVV['data']['genomic_mappings']['hg38'][] = $aVVVot['data']['genomic_mappings']['hg38'];
                            if (!isset($aVV['data']['transcript_mappings'])) {
                                $aVV['data']['transcript_mappings'] = array();
                            }
                            $aVV['data']['transcript_mappings'][$sTranscript] =
                                array_intersect_key($aVVVot['data'], array_flip(array('DNA', 'RNA', 'protein')));
                        }

                        // Check given alternatives; if it's one unique variant, we'll take that one.
                        if (count(array_unique($aMappedAlternatives)) == 1) {
                            // Only one genomic variant maps to the given VOTs.
                            // We also checked if it's on the same chromosome, just accept it.
                            $aUpdate['DNA'] = $aMappedAlternatives[0];
                        } else {
                            // No good, we don't know what to trust now.
                            $this->panic($aVariant, $aVV, 'While handling an EREF error, found that LOVD\'s VOTs returned multiple options for the gDNA, I don\'t know what to do now.');
                        }

                        // Consider it handled.
                        unset($aVV['errors']['EREF']);
                        $aVV['data']['DNA'] = $sCurrentRefSeq . ':' . $aUpdate['DNA'];
                    }

                    if ($aVV['errors']) {
                        // Unhandled errors. Reason to panic.
                        $this->panic($aVariant, $aVV, 'Unhandled errors, I don\'t know how to handle this variant (' . implode(',', array_keys($aVV['errors'])) . ')');
                    }
                }



                // Clean genomic DNAs field, remove NC from it.
                $aVV['data']['DNA_clean'] = substr(strstr($aVV['data']['DNA'], ':'), 1);
                if ($this->bDNA38) {
                    if (count($aVV['data']['genomic_mappings']['hg38']) == 1) {
                        // We have a hg38 DNA column, and this variant has only one hg38 mapping.
                        $aVV['data']['DNA38_clean'] = substr(strstr($aVV['data']['genomic_mappings']['hg38'][0], ':'), 1);
                    } else {
                        $this->panic($aVariant, $aVV, 'None or multiple hg38 mappings given for variant.');
                    }
                }

                // FIXME: We want to send emails to the users when we edit
                //  something, right? I know the emailing code is horrible and
                //  almost impossible to understand, but it would be good to see
                //  how we can get that code to work for us.

                // First, check NC description.
                if ($aVariant['DNA'] != $aVV['data']['DNA_clean']) {
                    // Genomic variant needs to be changed. That's OK, we know VV's
                    //  prediction is correct, since we used this variant as input.
                    $aUpdate['DNA'] = $aVV['data']['DNA_clean'];
                }

                // If we can, fill in or correct the hg38 prediction.
                if ($this->bDNA38) {
                    if (!$aVariant['DNA38']) {
                        // We didn't have a hg38 description yet. Just fill it in.
                        $aUpdate['DNA38'] = $aVV['data']['DNA38_clean'];
                    } elseif ($aVariant['DNA38'] != $aVV['data']['DNA38_clean']) {
                        // HG38 genomic variant is different.
                        // We can't assume here, that hg19 was the source.
                        // Throw in the hg38 variant that we have, and check if
                        //  it's mapping to the same variant that we have.
                        // If so, correct it.
                        $sVariantHG38 = $_SETT['human_builds']['hg38']['ncbi_sequences'][$this->sCurrentChromosome] . ':' . $aVariant['DNA38'];
                        if (!isset($this->aCache[$sVariantHG38 . ':checkonly'])) {
                            // The ":checkonly" suffix is because we're not running
                            //  everything including the mapping. We're usually on a
                            //  different chromosome so it should be OK, but if we're
                            //  running chrM, then we're actually on the same chromosome.
                            // Don't mix the full VV runs with the simple runs!
                            $aVVHG38 = $_VV->verifyGenomic($sVariantHG38);
                            // This also stores failures, so we won't repeat these.
                            $this->aCache[$sVariantHG38 . ':checkonly'] = $aVVHG38;
                        } else {
                            $aVVHG38 = $this->aCache[$sVariantHG38 . ':checkonly'];
                        }

                        // Check result.
                        if (!$aVVHG38) {
                            // VV failed. Either we have a really shitty variant, or VV broke.
                            // Nonetheless, it's easier to just continue.
                            // Just give a notice about it, and continue.
                            print('
      <SCRIPT type="text/javascript">
        $("#tr_stats td").html("<B style=\"color : #FF0000;\">VV failed on HG38 verification of ' . $sVariant . '. Ignoring...</B>");
      </SCRIPT>');
                            flush();
                            sleep(5); // To make it visible for a while.
                            // Silently ignore this. We ignore VV errors for variants, so why not for hg38 mappings?

                        } else {
                            // If the resulting variant is the same as what our
                            //  hg19 already said the hg38 should be, correct it.

                            // Clean genomic DNAs field, remove NC from it.
                            $aVVHG38['data']['DNA_clean'] = substr(strstr($aVVHG38['data']['DNA'], ':'), 1);

                            if ($aVV['data']['DNA38_clean'] == $aVVHG38['data']['DNA_clean']) {
                                // It's actually the same variant! Just bad mapping.
                                $aUpdate['DNA38'] = $aVV['data']['DNA38_clean'];
                            } else {
                                // We don't know what's going on, panic for now.
                                $this->panic($aVariant, $aVV, 'hg38 variant does not match the hg19 variant, nor does it normalize to the predicted hg38 variant. Now what?');
                            }
                        }
                    }
                }

                // Check transcript mappings.
                foreach ($aVariant['vots'] as $sTranscript => $aVOT) {
                    // Did VV give us this transcript? If not, we need to notify VV.
                    // If they're missing a transcript from their database, they want to know.
                    if (!isset($aVV['data']['transcript_mappings'][$sTranscript])) {
                        // VV didn't return this transcript.
                        // This can also happen when our variant is just
                        //  completely wrong and not even close to this transcript.
                        // Therefore, test this transcript quickly.

                        if (!isset($this->aCache[$sTranscript . ':' . $aVOT['DNA']])) {
                            $aVVVot = $_VV->verifyVariant($sTranscript . ':' . $aVOT['DNA']);
                            // This also stores failures, so we won't repeat these.
                            $this->aCache[$sTranscript . ':' . $aVOT['DNA']] = $aVVVot;
                        } else {
                            $aVVVot = $this->aCache[$sTranscript . ':' . $aVOT['DNA']];
                        }

                        // If VV succeeded, check if the transcript is found on the same chromosome.
                        // If not, this is probably an import error where the wrong
                        //  transcript ID was selected. Yes, even if the chromosome is the same,
                        //  this might be the case. But we have to draw the line somewhere.
                        if ($aVVVot && !$aVVVot['errors']) {
                            $sMappedRefSeq = strstr($aVVVot['data']['genomic_mappings'][$_CONF['refseq_build']], ':', true);
                            if ($sCurrentRefSeq != $sMappedRefSeq) {
                                $this->panic($aVariant, $aVV, 'While handling missing transcript annotation, found that LOVD\'s mapping is on a transcript on a different chromosome (' . $sCurrentRefSeq . ' => ' . $sMappedRefSeq . ').');
                            }
                        }

                        // We don't care if VV failed or not. This transcript should be reported.
                        // Check if we have reported it before.
                        if (!$_DB->query('
                                SELECT COUNT(*)
                                FROM ' . TABLE_LOGS . '
                                WHERE name = ? AND event = ? AND log LIKE ?',
                                array('Error', 'VVMissingTranscript', '% ' . $sTranscript . '.'))->fetchColumn()) {
                            lovd_writeLog('Error', 'VVMissingTranscript', 'Missing transcript when operating VV:verifyGenome(' . $sVariant . '): ' . $sTranscript . '.');
                        }

                        // We won't be able to check this VOT, we'll silently leave it be.

                    } else {
                        // Check VOT.
                        if (str_replace(array('(', ')'), '', $aVOT['DNA']) != $aVV['data']['transcript_mappings'][$sTranscript]['DNA']) {
                            // It's possible that this was just a bad Mutalyzer mapping.
                            // It can also be that perhaps somebody messed up.
                            // Map the given DNA field back to the genome, and
                            //  check if that perhaps match what we have.
                            // If so, we can safely replace this variant with VV's option.
                            if (!isset($this->aCache[$sTranscript . ':' . $aVOT['DNA']])) {
                                $aVVVot = $_VV->verifyVariant($sTranscript . ':' . $aVOT['DNA']);
                                // This also stores failures, so we won't repeat these.
                                $this->aCache[$sTranscript . ':' . $aVOT['DNA']] = $aVVVot;
                            } else {
                                $aVVVot = $this->aCache[$sTranscript . ':' . $aVOT['DNA']];
                            }

                            // Check result.
                            if (!$aVVVot) {
                                // VV failed. Either we have a really shitty variant, or VV broke.
                                // We skip failed VV calls for genomic variants silently,
                                //  but it worked for this one, so the cDNA call should work as well.
                                $this->panic($aVariant, $aVV, 'While investigating a VOT DNA mismatch, VV failed on the VOT variant; this variant needs manual curation.');
                            }

                            if ($aVVVot['data']['genomic_mappings'][$_CONF['refseq_build']] == $aVV['data']['DNA']) {
                                // OK, the genomic variants match, so it was just bad mapping.
                                if (!isset($aUpdate['transcripts'])) {
                                    $aUpdate['transcripts'] = array();
                                }
                                if (!isset($aUpdate['transcripts'][$sTranscript])) {
                                    $aUpdate['transcripts'][$sTranscript] = array();
                                }
                                $aUpdate['transcripts'][$sTranscript]['DNA'] = $aVVVot['data']['DNA'];

                                // Overwrite the RNA field if it's different and not so interesting.
                                if ($aVOT['RNA'] != $aVVVot['data']['RNA']) {
                                    if (in_array($aVOT['RNA'], array('', 'r.(?)'))) {
                                        $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                    } else {
                                        // We don't know what to do here.
                                        $this->panic($aVariant, $aVV, 'cDNA and RNA are different; cDNA can be fixed, but I don\'t know what to do with the RNA field.');
                                    }
                                }

                                // Right now, we don't overwrite the protein field. We just check if it's different, and panic if needed.
                                if (str_replace('*', 'Ter', $aVOT['protein']) != $aVV['data']['protein']) {
                                    // We don't know what to do here.
                                    $this->panic($aVariant, $aVV, 'cDNA and protein are different; cDNA can be fixed, but I don\'t know what to do with the protein field.');
                                }

                            } else {
                                // No good, we don't know whether to trust the gDNA or the cDNA.
                                $this->panic($aVariant, $aVV, 'gDNA and cDNA don\'t belong together, I don\'t know what to do now.');
                            }
                        }

                        // We don't check RNA or protein if the DNA is the same. Or will we?
                    }
                }

                // Anything to update?
                if ($aUpdate) {
                    if (count($aUpdate) == 1 && key($aUpdate) == 'DNA38') {
                        // We only have to update the hg38 value. We can do that without any issues and without sending any email.
                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                            'VariantOnGenome/DNA/hg38' => $aUpdate['DNA38'],
                        ), array('VariantOnGenome/DNA/hg38'));

                    } else {
                        // Update the entry!
                        // FIXME: For now, panic if we need to update both the VOG's
                        //  DNA and VOT data, I want to check this first.
                        if (!empty($aUpdate['DNA']) && !empty($aUpdate['transcripts'])) {
                            $this->panic($aVariant, $aVV, 'FIXME: Update required for both VOG and VOT, please check. If this is OK, just disable this panic call.');
                        }

                        // We'll be emailing, so make sure we load the data as is for the email.
                        $zData = $_DATA['Genome']->loadEntry($aVariant['id']);

                        // Are genes involved as well?
                        if ($aVariant['vots']) {
                            // So, this will be annoying. I'd like to just have one object for all of this,
                            //  but currently, VOT's loadAll() requires the variant's column set.
                            // This in turn depends on the gene(s) the variant is linked to, and this can be multiple.
                            // So, the object should be loaded for *every variant* that we update, not per gene.
                            // We might still hack our way around this, for instance by loading the object
                            //  once and then overwriting its column set to a basic default list.
                            // This will however cause problems with the email script, which will then show empty fields.
                            // To hell with it, I'll just load the object...
                            $_DATA['Transcript'] = new LOVD_TranscriptVariant('', $aVariant['id']);
                            $zData = array_merge($zData, $_DATA['Transcript']->loadAll($aVariant['id']));
                        }

                        // Build the POST array.
                        $_POST = $zData;
                        $aFieldsGenome = array();
                        $aFieldsTranscripts = array();

                        foreach ($aUpdate as $sField => $sValue) {
                            switch ($sField) {
                                case 'DNA':
                                    // Genomic DNA updated.
                                    $aFieldsGenome[] = 'VariantOnGenome/DNA';
                                    $_POST['VariantOnGenome/DNA'] = $sValue;
                                    // Also fix position fields.
                                    $aResponse = lovd_getVariantInfo($_POST['VariantOnGenome/DNA']);
                                    if ($aResponse) {
                                        $aFieldsGenome = array_merge($aFieldsGenome, array('position_g_start', 'position_g_end', 'type'));
                                        list($_POST['position_g_start'], $_POST['position_g_end'], $_POST['type']) =
                                            array($aResponse['position_start'], $aResponse['position_end'], $aResponse['type']);
                                        // No fallback. What could happen?
                                    }
                                    break;
                                case 'DNA38':
                                    // Genomic DNA on hg38 updated.
                                    $aFieldsGenome[] = 'VariantOnGenome/DNA/hg38';
                                    $_POST['VariantOnGenome/DNA/hg38'] = $sValue;
                                    break;
                                default:
                                    // Unhandled field.
                                    var_dump(array_merge(array('Stub!' => 'Something to update!'), $aUpdate));
                                    $this->panic($aVariant, $aVV, 'While trying to update this variant, I realized I don\'t know how to handle the ' . $sField . ' field.');
                            }
                        }

                        // Add the edited_* fields.
                        $aFieldsGenome[] = 'edited_by';
                        $aFieldsGenome[] = 'edited_date';
                        $_POST['edited_by'] = 0;
                        $_POST['edited_date'] = date('Y-m-d H:i:s');

                        // Run the update.
                        $_DATA['Genome']->updateEntry($aVariant['id'], $_POST, $aFieldsGenome);
                    }

                    if ($aVariant['statusid'] >= STATUS_MARKED) {
                        // Get gene, and have it marked as updated.
                        $aGenes = $_DB->query('
                                SELECT DISTINCT t.geneid
                                FROM ' . TABLE_TRANSCRIPTS . ' AS t
                                    INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)
                                WHERE vot.id = ?',
                            array($aVariant['id']))->fetchAllColumn();
                        lovd_setUpdatedDate($aGenes);
                    }
                }



//                exit;///////////////////////////////////////////////////////////////////////////
                /*
                Checks the VOG, and compares the generated VOG and all VOTs
                Check memory usage and clean cache when needed (array_shift()).
                If any data is changed, fix the position fields as well.
                If VOT cannot be verified (VV doesn't know the transcript), but everything else seems OK, then assume it's OK?
                If VOTs are OK, but VOG should be changed, then just update it. We're talking about the same variant, so it's probably a mistake of the position converter.
                It should probably email when we change things, so users are aware of them, and we have some kind of log of what has been changed. The really simple changes (adding hg38 or changing delG to del or so) we can just skip.
                Log problems nicely, or perhaps have this script send emails when problems are found?
                */

                // Increase progress count by one.
                $this->nProgressCount ++;
            }

            // Done with this set of variants. Just increase the position by one, we'll see if we'll actually find something there.
            $this->nCurrentPosition ++;
        }

        // We'll never get here, this function just exit()s whenever it wants.
        return true;
    }





    public function getCurrentChromosome ()
    {
        // Simple getter to return the current chromosome.
        return $this->sCurrentChromosome;
    }





    public function getCurrentPosition ()
    {
        // Simple getter to return the current position.
        return $this->nCurrentPosition;
    }





    public function updateStats ($bDone = false)
    {
        // Update the table using the stored progress.

        // If we're told we're done, we won't doubt it.
        if ($bDone) {
            $this->oBarTotal->setProgress(100);
            $this->oBarChromosome->setProgress(100);
            print('
      <SCRIPT type="text/javascript">
        $("#tr_stats th").html("Done!");
        $("#tr_stats td").html("");
      </SCRIPT>');
            return true;
        }

        // If this is the first time we are running, calculate the maximum allowed memory size.
        if (!$this->nMaxMemory) {
            // Maximum memory usage?
            $nMaxMemory = lovd_convertIniValueToBytes(ini_get('memory_limit'));
            // Set to 1GB max.
            if ($nMaxMemory < 0 || $nMaxMemory > 1073741824) {
                $nMaxMemory = 1073741824;
            }
            // Now take 95% for safety, so we can simply check for 'usage > $nMaxMemory'.
            $this->nMaxMemory = 0.95*$nMaxMemory;
        }

        // Calculate current memory usage.
        $this->nMemoryUsage = round(memory_get_usage(true)/$this->nMaxMemory, 2);

        // Calculate total progress.
        // Progress is defined as the number of entries completed on this chromosome.
        $nVariantsDone = 0;
        foreach ($this->aChromosomes as $sChr => $nCount) {
            if ($sChr == $this->sCurrentChromosome) {
                // This is where we are.
                $nVariantsDone += $this->nProgressCount;
                break;
            } else {
                // We have done these chromosomes already.
                $nVariantsDone += $nCount;
            }
        }
        $this->nProgressTotal = round($nVariantsDone/$this->nVariantsTotal, 2);
        $this->nProgress = round($this->nProgressCount/$this->aChromosomes[$this->sCurrentChromosome], 2);



        // Update stats, if different from what we reported before.
        if ($this->nProgressTotal != $this->nProgressTotalReported) {
            $this->oBarTotal->setProgress($this->nProgressTotal*100);
            $this->nProgressTotalReported = $this->nProgressTotal;
        }
        // FIXME: Can we color this one red or so? Or make the color depend on the length, where 100% is red? Shouldn't be so hard?
        if ($this->nMemoryUsage != $this->nMemoryUsageReported) {
            $this->oBarMemory->setProgress($this->nMemoryUsage*100);
            $this->nMemoryUsageReported = $this->nMemoryUsage;
        }
        if ($this->nProgress != $this->nProgressReported) {
            $this->oBarChromosome->setProgress($this->nProgress*100);
            $this->nProgressReported = $this->nProgress;
        }
        if ($this->nCurrentPosition != $this->nCurrentPositionReported) {
            // We have no function for this, do it yourself.
            print('
      <SCRIPT type="text/javascript">
        $("#tr_stats td").html("' . $this->sCurrentChromosome . ':' . $this->nCurrentPosition . '");
      </SCRIPT>');
            $this->nCurrentPositionReported = $this->nCurrentPosition;
        }
        flush();
        return true;
    }
}





// Where will we start? The object itself will clean these values.
$sChrToStart = (empty($_GET['chromosome'])? '1' : $_GET['chromosome']);
$nPositionToStart = (empty($_GET['position'])? 1 : (int) $_GET['position']);

// Instantiate class.
$_ANALYSES = new LOVD_VVAnalyses($sChrToStart, $nPositionToStart);





// If we don't have an ACTION, just print the button to start the analysis.
if (!ACTION) {
    print('
      <BR>
      <BUTTON onclick="window.location.href = \'' . CURRENT_PATH . '?run&chromosome=' . $_ANALYSES->getCurrentChromosome() . '&position=' . $_ANALYSES->getCurrentPosition() . '\';">Start analysis &raquo;</BUTTON>');
    $_T->printFooter();
    exit;
}





if (ACTION == 'run') {
    // Actually run the analyses, update the stats table with the latest information while we run.
    @set_time_limit(0);

    $_T->printFooter(false); // 'false' keeps the BODY and the HTML open, but closes the tables.

    $_ANALYSES->runAnalyses();

    print('
</BODY>
</HTML>');
    exit;
}
?>
