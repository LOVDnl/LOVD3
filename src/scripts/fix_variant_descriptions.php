<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-04-09
 * Modified    : 2020-06-11
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

// FIXME: We still get timeouts with large variants. The LOVD endpoint skips
//  them, but VOTs fail; https://github.com/openvar/variantValidator/issues/151.
// FIXME: VV currently doesn't handle variants that fall outside of the
//  transcript; Mutalyzer used to support -5000 to +2000, so we have quite a few
//  of such variants; https://github.com/openvar/variantValidator/issues/173.
// FIXME: Uncertain DNA variants like c.(1234del) are currently not supported.
//  See: https://github.com/openvar/variantValidator/issues/194.
// FIXME: The RNA and protein handling part (EREF vs standard), have increased
//  to have quite some overlap; fix that?
// FIXME: Memory usage never gets high, so just remove that code and that bar?

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-form.php'; // For lovd_setUpdatedDate().
require ROOT_PATH . 'inc-lib-variants.php'; // For lovd_fixHGVS().

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
    protected $nVariantsDone = 0;       // Number of variants done in *this* run.
    protected $nVariantsUpdated = 0;    // Number of variants updated in *this* run.
    protected $aCache = array();        // Stores VV cache. Will be cleaned now and then when the memory usage is too high.

    // Numbers previously reported. If different from current values, we should update the stats.
    // All these numbers are two decimal floats (0-1), except for the position field.
    protected $nProgressTotalReported = 0;
    protected $nProgressReported = 0;
    protected $nMemoryUsageReported = 0;
    protected $nCurrentPositionReported = 0;

    protected $bDNA38 = false;          // Do we have the hg38 field active?
    protected $bRemarks = false;        // Do we have the VOG/Remarks field available?





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
            WHERE statusid > ?
            GROUP BY c.name
            ORDER BY c.sort_id', array(STATUS_PENDING))->fetchAllCombine();
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

        // Check for custom columns we need; hg38 annotation (GV shared has a
        //  custom column for that) and VOG/Remarks.
        list($this->bDNA38, $this->bRemarks) = $_DB->query('
            SELECT COUNT(*)
            FROM ' . TABLE_ACTIVE_COLS . '
            WHERE colid = ?
            UNION ALL
            SELECT COUNT(*)
            FROM ' . TABLE_ACTIVE_COLS . '
            WHERE colid = ?',
            array('VariantOnGenome/DNA/hg38', 'VariantOnGenome/Remarks'))->fetchAllColumn();

        // Get proper progress count - how much is behind us already for this chromosome?
        $this->nProgressCount = $_DB->query('
                SELECT COUNT(*)
                FROM ' . TABLE_VARIANTS . '
                WHERE chromosome = ? AND position_g_start < ? AND statusid > ?',
            array($this->sCurrentChromosome, $this->nCurrentPosition, STATUS_PENDING))->fetchColumn();

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
            'url' => '<A href="' . lovd_getInstallURL() . 'variants/' . $aVariant['id'] . '">' . $aVariant['id'] . '</A>',
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
        die('<PRE>' . $sDiff . '</PRE>
      <SCRIPT type="text/javascript">
        $("#tr_stats td img").remove();
      </SCRIPT>');
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
        // Connect with the testing endpoint, as long as production VV doesn't
        //  support the features yet that were built especially for LOVD.
        $_VV = new LOVD_VV('https://www35.lamp.le.ac.uk/'); // PROD: ''; TEST: https://www35.lamp.le.ac.uk/.
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
                WHERE chromosome = ? AND position_g_start >= ? AND statusid > ?',
                array($this->sCurrentChromosome, $this->nCurrentPosition, STATUS_PENDING))->fetchColumn();
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
                WHERE chromosome = ? AND position_g_start >= ? AND statusid > ?
                ORDER BY chromosome, position_g_start LIMIT 1',
                array($this->sCurrentChromosome, $this->nCurrentPosition, STATUS_PENDING))->fetchColumn();
            // Check if we got a position, for the small chance that our last database entry suddenly just got removed...
            if ($nNextPosition) {
                $this->nCurrentPosition = $nNextPosition;
            }

            // Fetch data for this position.
            $aVariants = $_DB->query('
                SELECT vog.id, vog.statusid, vog.`VariantOnGenome/DNA` AS DNA, ' .
                    (!$this->bDNA38? '' : 'vog.`VariantOnGenome/DNA/hg38` AS DNA38, ') .
                    (!$this->bRemarks? '' : 'vog.`VariantOnGenome/Remarks` AS remarks, ') .
                    'GROUP_CONCAT(vot.transcriptid, "||", t.id_ncbi, "||", IFNULL(vot.`VariantOnTranscript/DNA`, ""), "||", IFNULL(vot.`VariantOnTranscript/RNA`, ""), "||", IFNULL(vot.`VariantOnTranscript/Protein`, "") SEPARATOR ";;") AS __vots
                FROM ' . TABLE_VARIANTS . ' AS vog
                    LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)
                    LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                WHERE vog.chromosome = ? AND vog.position_g_start = ? AND statusid > ?
                GROUP BY vog.id',
                array($this->sCurrentChromosome, $this->nCurrentPosition, STATUS_PENDING))->fetchAllAssoc();
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
                $this->nVariantsDone ++; // We'll only see this number the next update anyway.
                usleep(250000); // FIXME: Remove later.

                // Call VV and get all information we need; mappings to
                //  transcripts, protein predictions and even mappings to hg38
                //  if we have that field.
                $sCurrentRefSeq = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$this->sCurrentChromosome];
                // Do a quick check with lovd_fixHGVS() on the variant's HGVS to prevent common errors.
                $sVariant = $sCurrentRefSeq . ':' . lovd_fixHGVS($aVariant['DNA']);
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
                    // VV failed. We already catch large variants that make VV
                    //  time out, so perhaps it's a temporary error?
                    // Just die() for now, keeping the stats visible.
                    die('<B style="color : #FF0000;">VV failed on ' . $sVariant . '...</B>');
                }

                // Do we have something to update?
                $aUpdate = array();



                if ($aVV['errors']) {
                    // Handle errors (ESYNTAX, EREF and the like).
                    if (isset($aVV['errors']['ESIZETOOLARGE'])) {
                        // Variant is too big to be handled.
                        $this->nProgressCount ++; // To show progress.
                        continue;
                    } elseif (isset($aVV['errors']['ESYNTAX'])
                        && preg_match('(\^|[?;]|con|ins\([0-9]+\)$|ins[0-9]+$|ins\[N[CGM]|\([0-9]+_[0-9]+\)|\[[0-9]+\]$)', $sVariant)) {
                        // We received an ESYNTAX, but the variant has a common
                        //  problem that we, nor VV, can handle.
                        // We can't do anything, so just skip them.
                        // (variant with uncertain position, allele notation,
                        //  insertion with only length mentioned)
                        $this->nProgressCount ++; // To show progress.
                        continue; // Then continue to the next variant.

                    } elseif ($this->bRemarks
                        && (isset($aVV['errors']['EINCONSISTENTLENGTH'])
                            || isset($aVV['errors']['ESYNTAX']))) {
                        // Other errors that we just report.
                        // Don't double-mark, so check if it's marked first.
                        $sErrorCode = (isset($aVV['errors']['ESYNTAX'])? 'ESYNTAX' : 'EINCONSISTENTLENGTH');
                        if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                            array($aVariant['id'], '%[' . $sErrorCode . ']%'))->fetchColumn()) {
                            // Add the error, set variant as marked when already public.
                            // Assuming here that $aVVVot['errors'] has named keys.
                            $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                    'Variant Error [' . $sErrorCode . ']: ' .
                                    'This genomic variant has an error (' . $aVV['errors'][$sErrorCode] . '). ' .
                                    'Please fix this entry and then remove this message.'),
                                'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                            ));
                            $this->nVariantsUpdated ++;
                        }
                        $this->nProgressCount ++; // To show progress.
                        continue; // Then continue to the next variant.

                    } elseif (isset($aVV['errors']['EREF'])) {
                        // EREF error; the genomic variant can not be correct.
                        // Loop the cDNA variants, if they are valid (all of them),
                        //  and the cDNA variant(s) are on the same chromosome,
                        //  then correct the genomic variant, it's probably wrong.
                        // It couldn't have been the source, since it's not valid.

                        // If we don't have VOTS, there's nothing we can do now.
                        if (empty($aVariant['vots']) && $this->bRemarks) {
                            // Don't double-mark, so check if it's marked first.
                            if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                                array($aVariant['id'], '%[EREF%'))->fetchColumn()) {
                                // Add the error, set variant as marked when already public.
                                $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                    'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                        'Variant Error [EREF]: ' .
                                        'This genomic variant does not match the reference sequence. ' .
                                         'Please fix this entry and then remove this message.'),
                                    'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                                ));
                                $this->nVariantsUpdated ++;
                            }
                            $this->nProgressCount ++;
                            continue;
                        }

                        // Collect alternative descriptions based on the VOTs.
                        $aMappedAlternatives = array();

                        foreach ($aVariant['vots'] as $sTranscript => $aVOT) {
                            // Because VV is in error, it didn't provide any mappings.
                            // Just reverse the mapping, check if the result is on
                            //  the same chromosome as the genomic input, and continue.
                            if (!isset($this->aCache[$sTranscript . ':' . $aVOT['DNA']])) {
                                $aVVVot = $_VV->verifyVariant($sCurrentRefSeq . '(' . $sTranscript . '):' . $aVOT['DNA']);
                                // This also stores failures, so we won't repeat these.
                                $this->aCache[$sTranscript . ':' . $aVOT['DNA']] = $aVVVot;
                            } else {
                                $aVVVot = $this->aCache[$sTranscript . ':' . $aVOT['DNA']];
                            }

                            // Check result.
                            if (!$aVVVot || $aVVVot['errors']) {
                                // VV failed. Either we have a really shitty variant, or VV broke.
                                // EREF *and* VOT fails. Log if we understand what happened, panic otherwise.
                                if ($this->bRemarks && isset($aVVVot['errors'])) {
                                    // Don't double-mark, so check if it's marked first.
                                    if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                                        array($aVariant['id'], '%[EREF/%'))->fetchColumn()) {
                                        // Add the error, set variant as marked when already public.
                                        // Assuming here that $aVVVot['errors'] has named keys.
                                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                            'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                                'Variant Error [EREF/' . key($aVVVot['errors']) . ']: ' .
                                                'This genomic variant does not match the reference sequence; ' .
                                                'the transcript variant ' . (isset($aVVVot['errors']['EREF'])? 'does not match the reference sequence either' : 'also has an error') . '. ' .
                                                'Please fix this entry and then remove this message.'),
                                            'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                                        ));
                                        $this->nVariantsUpdated ++;
                                    }
                                    $this->nProgressCount ++;
                                    continue 2; // On to the next variant. We ignore any other VOTs.

                                } else {
                                    // If we don't have the Remarks field active, or VV failed completely, panic anyway.
                                    $this->panic($aVariant, $aVV, 'While handling EREF error, VV failed on VOT' .
                                        (empty($aVVVot['errors'])? '' : (isset($aVVVot['errors']['EREF'])? ' with another EREF' : ' ("' . implode('";"', $aVVVot['errors']) . '")')) . '; this variant needs manual curation.');
                                }
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

                                // Compare the current RNA value with the new RNA prediction.
                                if ($aVOT['RNA'] != $aVVVot['data']['RNA']) {
                                    if (in_array($aVOT['RNA'], array('', '-', 'r.?', 'r.(?)', 'r.(=)'))
                                        || (strpos($aVOT['RNA'], 'spl') !== false && preg_match('/[0-9]+[+-][0-9]+/', $aVOT['DNA'])
                                            && !preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // Overwrite the RNA field if it's different and not so interesting,
                                        //  or when it mentioned splicing but the new description doesn't
                                        //  cover an intron anymore.
                                        $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                    } elseif ($aVOT['RNA'] == str_replace('?', '', $aVVVot['data']['RNA'])
                                        || (strpos($aVOT['RNA'], 'spl') !== false && preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // We ignore small differences, where maybe the RNA has been verified.
                                    } elseif (lovd_getVariantInfo(lovd_fixHGVS('c' . strstr($aVOT['RNA'], '.'), 'c'), '', true)
                                        || preg_match('/^r\.\[[0-9]+/', $aVOT['RNA'])) {
                                        // If the RNA variant looks like a full variant description,
                                        //  the current value must be better (or at least more
                                        //  specific) than what we have; keep it!
                                    } else {
                                        // We don't really know what to do here.
                                        // As a final resort, keep the RNA if it remotely looks
                                        //  like a verified RNA description, overwrite it otherwise.
                                        if (!preg_match('/^r\.[0-9]+/', $aVOT['RNA'])) {
                                            $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                        }
                                    }
                                }

                                // Compare the current protein value with the new protein prediction.
                                if (str_replace('*', 'Ter', $aVOT['protein']) != $aVVVot['data']['protein']) {
                                    if (in_array($aVOT['protein'], array('', 'p.?', 'p.fs', 'p.fs?', 'p.fs*', 'p.(fs)'))) {
                                        // Overwrite the protein field if it's different and not so interesting,
                                        //  we assume to have something better.
                                        $aUpdate['transcripts'][$sTranscript]['protein'] = $aVVVot['data']['protein'];
                                    } elseif (str_replace('*', 'Ter', $aVOT['protein']) == str_replace(array('(', ')'), '', $aVVVot['data']['protein'])
                                        || ($aVOT['protein'] == 'p.?' && preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // We ignore small differences, where maybe the RNA has been verified.
                                    } elseif ((preg_match('/^r\.[0-9]/', $aVOT['RNA']) && preg_match('/^p\.[A-Z]([a-z]{2})?[0-9]/', $aVOT['protein']))
                                        || ($aVVVot['data']['protein'] == 'p.?'
                                            && (preg_match('/^r\.[0-9]/', $aVOT['RNA']) || preg_match('/[A-Z]([a-z]{2})?[0-9]+/', $aVOT['protein'])))) {
                                        // RNA has been checked and we have a verified prediction;
                                        // OR VV doesn't dare to predict, and either the original RNA or protein field
                                        //    seems to contain some kind of prediction;
                                        // Keep the current protein value.
                                    } elseif (similar_text(str_replace('*', 'Ter', $aVOT['protein']), $aVVVot['data']['protein'], $n)
                                        && $n > 50) {
                                        // We have similar protein values. We already know the cDNA changed.
                                        // VV probably knows better because of the updated cDNA.
                                        $aUpdate['transcripts'][$sTranscript]['protein'] = $aVVVot['data']['protein'];
                                    } else {
                                        // We don't know what to do here.
                                        // Merge $aVV with $aVVVot's data, so we can see what VV's suggestion is for the DNA, RNA and protein.
                                        $this->panic($aVariant, array_merge($aVV,
                                            array('data' => array('transcript_mappings' => array($sTranscript => $aVVVot['data'])))),
                                            'While handling EREF error, found that also cDNA and protein are different; cDNA can be fixed, but I don\'t know what to do with the protein field.');
                                    }
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
                        $this->panic($aVariant, $aVV, 'Unhandled errors, I don\'t know how to handle this variant (' . implode(',', array_keys($aVV['errors'])) . '); [' . implode(';', $aVV['errors']) . '].');
                    }
                }

                // Also panic when we have a warning, to make sure we catch everything.
                unset($aVV['warnings']['WCORRECTED']);
                unset($aVV['warnings']['WROLLFORWARD']);
                if (isset($aVV['warnings']['WGAP'])) {
                    // Ignore WGAP warnings when the predicted cDNA is the same as the current cDNA.
                    $sTranscript = key($aVariant['vots']);
                    if ($aVariant['vots'][$sTranscript]['DNA']
                        == $aVV['data']['transcript_mappings'][$sTranscript]['DNA']) {
                        // Match.
                        unset($aVV['warnings']['WGAP']);
                    }
                }
                if ($aVV['warnings']) {
                    $this->panic($aVariant, $aVV, 'Warnings found: {' .
                        implode(';',
                            array_map(function ($sKey, $sVal) {
                                return $sKey . ':' . $sVal;
                            }, array_keys($aVV['warnings']), $aVV['warnings'])) . '}.');
                }



                // Clean genomic DNAs field, remove NC from it.
                $aVV['data']['DNA_clean'] = substr(strstr($aVV['data']['DNA'], ':'), 1);
                $aVV['data']['DNA38_clean'] = '';
                if ($this->bDNA38 && isset($aVV['data']['genomic_mappings']['hg38'])) {
                    if (count($aVV['data']['genomic_mappings']['hg38']) == 1) {
                        // We have a hg38 DNA column, and this variant has only one hg38 mapping.
                        $aVV['data']['DNA38_clean'] = substr(strstr($aVV['data']['genomic_mappings']['hg38'][0], ':'), 1);
                    } else {
                        $this->panic($aVariant, $aVV, 'Multiple hg38 mappings given for variant.');
                    }
                }

                // First, check NC description.
                if ($aVariant['DNA'] != $aVV['data']['DNA_clean']) {
                    // Genomic variant needs to be changed. That's OK, we know VV's
                    //  prediction is correct, since we used this variant as input.
                    $aUpdate['DNA'] = $aVV['data']['DNA_clean'];
                }

                // If we can, fill in or correct the hg38 prediction.
                if ($this->bDNA38 && $aVV['data']['DNA38_clean']) {
                    if (!$aVariant['DNA38']) {
                        // We didn't have a hg38 description yet. Just fill it in.
                        $aUpdate['DNA38'] = $aVV['data']['DNA38_clean'];
                    } elseif ($aVariant['DNA38'] != $aVV['data']['DNA38_clean']) {
                        // hg38 genomic variant is different.
                        // We can't assume here, that hg19 was the source.
                        // Throw in the hg38 variant that we have, and check if
                        //  it's mapping to the same variant that we have.
                        // If so, correct it.
                        $sVariantHG38 = $_SETT['human_builds']['hg38']['ncbi_sequences'][$this->sCurrentChromosome] . ':' . lovd_fixHGVS($aVariant['DNA38']);
                        if (!isset($this->aCache[$sVariantHG38 . ':checkonly'])) {
                            // The ":checkonly" suffix is because we're not running
                            //  everything including the mapping. We're usually on a
                            //  different NC so it should be OK, but if we're
                            //  running chrM, then we're actually on the same NC.
                            // Don't mix the full VV runs with the simple runs!
                            $aVVHG38 = $_VV->verifyGenomic($sVariantHG38);
                            // This also stores failures, so we won't repeat these.
                            $this->aCache[$sVariantHG38 . ':checkonly'] = $aVVHG38;
                        } else {
                            $aVVHG38 = $this->aCache[$sVariantHG38 . ':checkonly'];
                        }

                        // Check result.
                        if (!$aVVHG38) {
                            // VV failed. We already catch large variants that make VV
                            //  time out, so perhaps it's a temporary error?
                            // Just die() for now, keeping the stats visible.
                            die('<B style="color : #FF0000;">VV failed on hg38 verification of ' . $sVariant . '...</B>');

                        } elseif ($aVVHG38['errors']) {
                            // Handle EREF errors and the like.
                            // Ignoring ESYNTAX here because that should have
                            //  been handled for the original variant already.
                            if (isset($aVVHG38['errors']['ERANGE']) || isset($aVVHG38['errors']['EREF'])) {
                                // ERANGE or EREF error; the genomic variant
                                //  cannot be correct.
                                // If we get here, it means the hg19 variant
                                //  wasn't in error, or could have been corrected
                                //  using the cDNA variant. We cannot tell the
                                //  difference anymore at this point.
                                // Take the hg38 mapping from VV, it must be
                                //  better than what we currently have.
                                $aUpdate['DNA38'] = $aVV['data']['DNA38_clean'];

                                // Consider it handled.
                                unset($aVVHG38['errors']['ERANGE']);
                                unset($aVVHG38['errors']['EREF']);
                            }

                            if ($aVVHG38['errors']) {
                                // Unhandled errors. Reason to panic.
                                $this->panic($aVariant, $aVV, 'Unhandled errors for hg38 verification, I don\'t know how to handle this variant (' . implode(',', array_keys($aVVHG38['errors'])) . ')');
                            }

                        } else {
                            // If the resulting variant is the same as what our
                            //  hg19 already said the hg38 should be, correct it.

                            // Clean genomic DNAs field, remove NC from it.
                            $aVVHG38['data']['DNA_clean'] = substr(strstr($aVVHG38['data']['DNA'], ':'), 1);

                            if ($aVV['data']['DNA38_clean'] == $aVVHG38['data']['DNA_clean']) {
                                // It's actually the same variant! Just bad mapping.
                                $aUpdate['DNA38'] = $aVV['data']['DNA38_clean'];
                            } else {
                                // No good, we don't know whether to trust the hg19 or the hg38.
                                // This happens now and then. We don't know what to do,
                                //  so we'll just mark the variant and be done with it.
                                if ($this->bRemarks) {
                                    // Don't double-mark, so check if it's marked first.
                                    if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                                        array($aVariant['id'], '%[EBUILDMISMATCH]%'))->fetchColumn()) {
                                        // Add the error, set variant as marked when already public.
                                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                            'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                                'Variant Error [EBUILDMISMATCH]: This variant seems to mismatch; the genomic variants on hg19 and hg38 seem to not belong together. ' .
                                                'Please fix this entry and then remove this message.'),
                                            'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                                        ));
                                        $this->nVariantsUpdated ++;
                                    }
                                    $this->nProgressCount ++;
                                    continue; // On to the next variant.
                                } else {
                                    // If we don't have the Remarks field active, just panic anyway.
                                    $this->panic($aVariant, $aVV, 'hg38 variant does not match the hg19 variant, nor does it normalize to the predicted hg38 variant. Now what?');
                                }
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
                            $aVVVot = $_VV->verifyVariant($sCurrentRefSeq . '(' . $sTranscript . '):' . $aVOT['DNA']);
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

                        // VV doesn't like variants outside of the transcript.
                        // Mutalyzer used to give us mappings up to 5000 bases
                        //  upstream, and 2000 bases downstream of the transcript.
                        // We should only report missing hg19<->transcript mappings
                        //  when we're sure we have a variant here that should
                        //  work; ignore this otherwise.
                        // https://github.com/openvar/variantValidator/issues/173
                        if (!isset($aVVVot['errors']['ERANGE'])) {
                            // This transcript should be reported.
                            // Check if we have reported it before.
                            if (!$_DB->query('
                                SELECT COUNT(*)
                                FROM ' . TABLE_LOGS . '
                                WHERE name = ? AND event = ? AND log LIKE ?',
                                array('Error', 'VVMissingTranscript', '% ' . $sTranscript . '.'))->fetchColumn()) {
                                lovd_writeLog('Error', 'VVMissingTranscript', 'Missing transcript when operating VV:verifyGenome(' . $sVariant . '): ' . $sTranscript . '.');
                            }
                        }

                        // We won't be able to check this VOT, we'll silently leave it be.

                    } else {
                        // Check VOT.
                        // Check if the VOT's fields are perhaps empty; if so, replace them.
                        foreach (array('DNA', 'RNA', 'protein') as $sField) {
                            if (in_array($aVOT[$sField], array('', '-', 'c.?', 'r.?', 'p.?'))) {
                                // The current field is pretty much bogus, just overwrite it.
                                if (!isset($aUpdate['transcripts'])) {
                                    $aUpdate['transcripts'] = array();
                                }
                                $aVOT[$sField] = $aUpdate['transcripts'][$sTranscript][$sField] =
                                    $aVV['data']['transcript_mappings'][$sTranscript][$sField];
                            }
                        }
                        if (str_replace(array('(', ')'), '', $aVOT['DNA']) != $aVV['data']['transcript_mappings'][$sTranscript]['DNA']) {
                            // It's possible that this was just a bad Mutalyzer mapping.
                            // It can also be that perhaps somebody messed up.
                            // Map the given DNA field back to the genome, and
                            //  check if that perhaps match what we have.
                            // If so, we can safely replace this variant with VV's option.
                            if (!isset($this->aCache[$sTranscript . ':' . $aVOT['DNA']])) {
                                $aVVVot = $_VV->verifyVariant($sCurrentRefSeq . '(' . $sTranscript . '):' . $aVOT['DNA']);
                                // This also stores failures, so we won't repeat these.
                                $this->aCache[$sTranscript . ':' . $aVOT['DNA']] = $aVVVot;
                            } else {
                                $aVVVot = $this->aCache[$sTranscript . ':' . $aVOT['DNA']];
                            }

                            // Check result.
                            if (!$aVVVot || $aVVVot['errors']) {
                                // VV failed. Either we have a really shitty variant, or VV broke.
                                // We skip failed VV calls for genomic variants silently,
                                //  but it worked for this one, so the cDNA call should work as well.
                                // Log if we understand what happened, panic otherwise.
                                if ($this->bRemarks && isset($aVVVot['errors'])) {
                                    // Don't double-mark, so check if it's marked first.
                                    if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                                        array($aVariant['id'], '%[EMISMATCH/%'))->fetchColumn()) {
                                        // Add the error, set variant as marked when already public.
                                        // Assuming here that $aVVVot['errors'] has named keys.
                                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                            'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                                'Variant Error [EMISMATCH/' . key($aVVVot['errors']) . ']: ' .
                                                'This transcript variant ' . (isset($aVVVot['errors']['EREF'])? 'does not match the reference sequence' : 'has an error') . '. ' .
                                                'Please fix this entry and then remove this message.'),
                                            'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                                        ));
                                        $this->nVariantsUpdated ++;
                                    }
                                    $this->nProgressCount ++;
                                    continue 2; // On to the next variant. We ignore any other VOTs.

                                } else {
                                    // If we don't have the Remarks field active, or VV failed completely, panic anyway.
                                    $this->panic($aVariant, $aVV, 'While investigating a VOT DNA mismatch, VV failed on the VOT variant' .
                                        (empty($aVVVot['errors'])? '' :
                                            (isset($aVVVot['errors']['EREF']) || isset($aVVVot['errors']['ESYNTAX'])? ' with an ' . key($aVVVot['errors']) :
                                                ' ("' . implode('";"', $aVVVot['errors']) . '")')) . '; this variant needs manual curation.');
                                }
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

                                // Compare the current RNA value with the new RNA prediction.
                                if ($aVOT['RNA'] != $aVVVot['data']['RNA']) {
                                    if (in_array($aVOT['RNA'], array('', '-', 'r.?', 'r.(?)', 'r.(=)'))
                                        || (strpos($aVOT['RNA'], 'spl') !== false && preg_match('/[0-9]+[+-][0-9]+/', $aVOT['DNA'])
                                            && !preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // Overwrite the RNA field if it's different and not so interesting,
                                        //  or when it mentioned splicing but the new description doesn't
                                        //  cover an intron anymore.
                                        $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                    } elseif ($aVOT['RNA'] == str_replace('?', '', $aVVVot['data']['RNA'])
                                        || (strpos($aVOT['RNA'], 'spl') !== false && preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // We ignore small differences, where maybe the RNA has been verified.
                                    } elseif (lovd_getVariantInfo(lovd_fixHGVS('c' . strstr($aVOT['RNA'], '.'), 'c'), '', true)
                                        || preg_match('/^r\.\[[0-9]+/', $aVOT['RNA'])) {
                                        // If the RNA variant looks like a full variant description,
                                        //  the current value must be better (or at least more
                                        //  specific) than what we have; keep it!
                                    } else {
                                        // We don't really know what to do here.
                                        // As a final resort, keep the RNA if it remotely looks
                                        //  like a verified RNA description, overwrite it otherwise.
                                        if (!preg_match('/^r\.[0-9]+/', $aVOT['RNA'])) {
                                            $aUpdate['transcripts'][$sTranscript]['RNA'] = $aVVVot['data']['RNA'];
                                        }
                                    }
                                }

                                // Compare the current protein value with the new protein prediction.
                                if (str_replace('*', 'Ter', $aVOT['protein']) != $aVVVot['data']['protein']) {
                                    if (in_array($aVOT['protein'], array('', 'p.?', 'p.fs', 'p.fs?', 'p.fs*', 'p.(fs)'))) {
                                        // Overwrite the protein field if it's different and not so interesting,
                                        //  we assume to have something better.
                                        $aUpdate['transcripts'][$sTranscript]['protein'] = $aVVVot['data']['protein'];
                                    } elseif (str_replace('*', 'Ter', $aVOT['protein']) == str_replace(array('(', ')'), '', $aVVVot['data']['protein'])
                                        || ($aVOT['protein'] == 'p.?' && preg_match('/[0-9]+[+-][0-9]+/', $aVVVot['data']['DNA']))) {
                                        // We ignore small differences, where maybe the RNA has been verified.
                                    } elseif ((preg_match('/^r\.[0-9]/', $aVOT['RNA']) && preg_match('/^p\.[A-Z]([a-z]{2})?[0-9]/', $aVOT['protein']))
                                        || ($aVVVot['data']['protein'] == 'p.?'
                                            && (preg_match('/^r\.[0-9]/', $aVOT['RNA']) || preg_match('/[A-Z]([a-z]{2})?[0-9]+/', $aVOT['protein'])))) {
                                        // RNA has been checked and we have a verified prediction;
                                        // OR VV doesn't dare to predict, and either the original RNA or protein field
                                        //    seems to contain some kind of prediction;
                                        // Keep the current protein value.
                                    } elseif (similar_text(str_replace('*', 'Ter', $aVOT['protein']), $aVVVot['data']['protein'], $n)
                                        && $n > 50) {
                                        // We have similar protein values. We already know the cDNA changed.
                                        // VV probably knows better because of the updated cDNA.
                                        $aUpdate['transcripts'][$sTranscript]['protein'] = $aVVVot['data']['protein'];
                                    } else {
                                        // We don't know what to do here.
                                        // Merge $aVV with $aVVVot's data, so we can see what VV's suggestion is for the DNA, RNA and protein.
                                        $this->panic($aVariant, array_merge($aVV,
                                            array('data' => array('transcript_mappings' => array($sTranscript => $aVVVot['data'])))),
                                            'cDNA and protein are different; cDNA can be fixed, but I don\'t know what to do with the protein field.');
                                    }
                                }

                            } else {
                                // No good, we don't know whether to trust the gDNA or the cDNA.
                                // This happens now and then. We don't know what to do,
                                //  so we'll just mark the variant and be done with it.
                                if ($this->bRemarks) {
                                    // Don't double-mark, so check if it's marked first.
                                    if (!$_DB->query('
                                        SELECT COUNT(*)
                                        FROM ' . TABLE_VARIANTS . '
                                        WHERE id = ? AND `VariantOnGenome/Remarks` LIKE ?',
                                        array($aVariant['id'], '%[EMISMATCH]%'))->fetchColumn()) {
                                        // Add the error, set variant as marked when already public.
                                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                                            'VariantOnGenome/Remarks' => ltrim($aVariant['remarks'] . "\r\n" .
                                                'Variant Error [EMISMATCH]: This variant seems to mismatch; the genomic and the transcript variant seems to not belong together. ' .
                                                'Please fix this entry and then remove this message.'),
                                            'statusid' => min($aVariant['statusid'], STATUS_MARKED),
                                        ));
                                        $this->nVariantsUpdated ++;
                                    }
                                    $this->nProgressCount ++;
                                    continue 2; // On to the next variant. We ignore any other VOTs.

                                } else {
                                    // If we don't have the Remarks field active, just panic anyway.
                                    $this->panic($aVariant, $aVV, 'gDNA and cDNA don\'t belong together, I don\'t know what to do now.');
                                }
                            }
                        }

                        // We don't check RNA or protein if the DNA is the same. Or will we?
                    }
                }

                // Anything to update?
                if ($aUpdate) {
                    if (count($aUpdate) == 1 && key($aUpdate) == 'DNA38') {
                        // We only have to update the hg38 value. We can do that without any issues.
                        $_DATA['Genome']->updateEntry($aVariant['id'], array(
                            'VariantOnGenome/DNA/hg38' => $aUpdate['DNA38'],
                        ));

                    } else {
                        // Update the entry!

                        // Are genes involved as well?
                        if ($aVariant['vots']) {
                            // So, this will be annoying. I'd like to just have one object for all of this,
                            //  but VOT's updateAll() requires the variant's transcript set.
                            // This in turn depends on the gene(s) the variant is linked to, and this can be multiple.
                            // So, the object should be loaded for *every variant* that we update, not per gene.
                            // We might still hack our way around this, for instance by loading the object
                            //  once and then overwriting its transcript set with the list we have here.
                            $_DATA['Transcript'] = new LOVD_TranscriptVariant('', $aVariant['id']);
                        }

                        // Build the POST array.
                        $_POST = array();
                        $aFieldsGenome = array();
                        $aFieldsTranscripts = array();

                        foreach ($aUpdate as $sField => $sValue) {
                            switch ($sField) {
                                case 'DNA':
                                    // Genomic DNA updated.
                                    $aFieldsGenome[] = 'VariantOnGenome/DNA';
                                    $_POST['VariantOnGenome/DNA'] = $sValue;
                                    // Also fix position fields.
                                    $aResponse = lovd_getVariantInfo($sValue);
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
                                case 'transcripts':
                                    // Something changed in (one of the) transcript mapping(s).
                                    foreach ($sValue as $sTranscript => $aVOT) {
                                        // We need the transcript's numerical ID to process the updates.
                                        $nTranscriptID = $aVariant['vots'][$sTranscript]['transcriptid'];
                                        foreach ($aVOT as $sField => $sValue) {
                                            switch ($sField) {
                                                case 'DNA':
                                                    // VOT/DNA has been updated, also fix position fields.
                                                    $aFieldsTranscripts[] = 'VariantOnTranscript/DNA';
                                                    $_POST[$nTranscriptID . '_VariantOnTranscript/DNA'] = $sValue;
                                                    // Position fields, too!
                                                    $aResponse = lovd_getVariantInfo($sValue);
                                                    if ($aResponse) {
                                                        $aFieldsTranscripts = array_merge($aFieldsTranscripts,
                                                            array('position_c_start', 'position_c_start_intron', 'position_c_end', 'position_c_end_intron'));
                                                        $_POST[$nTranscriptID . '_position_c_start'] = $aResponse['position_start'];
                                                        $_POST[$nTranscriptID . '_position_c_start_intron'] = $aResponse['position_start_intron'];
                                                        $_POST[$nTranscriptID . '_position_c_end'] = $aResponse['position_end'];
                                                        $_POST[$nTranscriptID . '_position_c_end_intron'] = $aResponse['position_end_intron'];
                                                        // No fallback. What could happen?
                                                    }
                                                    break;
                                                case 'RNA':
                                                    // VOT/RNA has been updated.
                                                    $aFieldsTranscripts[] = 'VariantOnTranscript/RNA';
                                                    $_POST[$nTranscriptID . '_VariantOnTranscript/RNA'] = $sValue;
                                                    break;
                                                case 'protein':
                                                    // VOT/Protein has been updated.
                                                    $aFieldsTranscripts[] = 'VariantOnTranscript/Protein';
                                                    $_POST[$nTranscriptID . '_VariantOnTranscript/Protein'] = $sValue;
                                                    break;
                                                default:
                                                    // Unhandled field.
                                                    var_dump(array_merge(array('Stub!' => 'Something to update!'), $aUpdate));
                                                    $this->panic($aVariant, $aVV, 'While trying to update this variant, I realized I don\'t know how to handle the VOT/' . $sField . ' field.');
                                            }
                                        }
                                    }
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
                        $_DB->beginTransaction();
                        $_DATA['Genome']->updateEntry($aVariant['id'], $_POST, $aFieldsGenome);

                        if ($aFieldsTranscripts) {
                            // We also have to update the VOT(s).
                            $aFieldsTranscripts = array_unique($aFieldsTranscripts);

                            // The updateAll() function is normally used with a per-gene array of fields
                            //  that need to be edited. However, we don't have the gene symbol here.
                            // If we don't pass $aFieldsTranscripts at all, and only pass an $_POST array
                            //  with the fields that we need edited (with numeric transcript ID prefix),
                            //  the function builds it's own list of fields to update.
                            // As such, construct a new $_POST replacement.
                            $aData = array();
                            foreach ($aVariant['vots'] as $sTranscript => $aVOT) {
                                $nTranscriptID = $aVOT['transcriptid'];
                                foreach ($aFieldsTranscripts as $sField) {
                                    $sKey = $nTranscriptID . '_' . $sField;
                                    $aData[$sKey] = $_POST[$sKey];
                                }
                            }

                            $_DATA['Transcript']->updateAll($aVariant['id'], $aData);
                        }
                        $_DB->commit();
                        $this->nVariantsUpdated ++;
                    }

                    if ($aVariant['statusid'] >= STATUS_MARKED) {
                        // Get gene, and have it marked as updated.
                        $aGenes = $_DB->query('
                                SELECT DISTINCT t.geneid
                                FROM ' . TABLE_TRANSCRIPTS . ' AS t
                                    INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)
                                WHERE vot.id = ?',
                            array($aVariant['id']))->fetchAllColumn();
                        // Set second argument to false to let "LOVD" be marked
                        //  as the updated_by, not the current user.
                        lovd_setUpdatedDate($aGenes, false);
                    }
                }

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
        if ($this->nProgressTotal != $this->nProgressTotalReported
            || $this->nProgress != $this->nProgressReported
            || $this->nCurrentPosition != $this->nCurrentPositionReported) {
            // Update this status field whenever *something* changes.
            print('
      <SCRIPT type="text/javascript">
        $("#tr_stats td").html("' . $this->sCurrentChromosome . ':' . $this->nCurrentPosition .
                (!$this->nVariantsDone? '' : ' (checked ' . $this->nVariantsDone . ' variant' . ($this->nVariantsDone == 1? '' : 's') .
                    (!$this->nVariantsUpdated? '' : ', updated ' . $this->nVariantsUpdated . ' variant' . ($this->nVariantsUpdated == 1? '' : 's')) . ')') .
                (ACTION != 'run'? '' : ' <IMG src=\"gfx/lovd_loading.gif\" alt=\"\" width=\"13\" height=\"13\" style=\"float: right;\">') .
                '");
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
