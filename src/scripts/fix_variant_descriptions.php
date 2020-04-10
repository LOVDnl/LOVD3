<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-04-09
 * Modified    : 2020-04-10
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





    public function runAnalyses ()
    {
        // Runs the analyses, updates the stats that should have been printed already, and runs the fixes.
        global $_DB;

        // Indicate we're working.
        print('
      <SCRIPT type="text/javascript">
        $("#tr_stats th").html("Working on ...");
      </SCRIPT>');

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
                $this->oBarTotal->redirectTo(CURRENT_PATH . '?run&chromosome=' . $sNextChromosome, 0);
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
                SELECT vog.id, vog.`VariantOnGenome/DNA`, ' .
                    (!$this->bDNA38? '' : 'vog.`VariantOnGenome/DNA/hg38`, ') .
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
                usleep(500000); // FIXME: Remove later.







//                exit;///////////////////////////////////////////////////////////////////////////
                /*
                If our genomic -> cDNA description is different than the existing cDNA description, we need to check if the given cDNA description perhaps normalizes to the correct cDNA description.
                I don't want to create yet another cache, so run based on genomic location and cache in memory - relatively easy to pick up where we left, but still at least some caching; Do include a way to skip IDs from before $X to make sure we don't need to rerun on every single variant in the future?
                Checks the VOG, and compares the generated VOG and all VOTs
                Implement a cache in memory so repeated variants don't cause more VV calls, but check memory usage and clean cache when needed (array_shift()).
                If any data is changed, fix the position fields as well.
                If VOT cannot be verified (VV doesn't know the transcript), but everything else seems OK, then assume it's OK? Log transcript so I can ask Pete to check why they don't have it? I guess this important for us.
                If VOTs are OK, but VOG should be changed, then just update it. We're talking about the same variant, so it's probably a mistake of the position converter.
                It should probably email when we change things, so users are aware of them, and we have some kind of log of what has been changed. The really simple changes (adding hg38 or changing delG to del or so) we can just skip.
                Also fill in the hg38 if missing.
                If any variant is fixed, or the hg38 is filled in, update the gene's timestamp.
                If the hg38 doesn't match, check if the filled in hg38 will generate the same hg19 that we have and it corrected to our hg38. If so, overwrite.
                Log problems nicely, or perhaps have this script send emails when problems are found?
                */

                // Increase progress count by one.
                $this->nProgressCount ++;
            }

            // Done with this set of variants. Just increase the position by one, we'll see if we'll actually find something there.
            $this->nCurrentPosition ++;
            usleep(500000); // Half a second. // FIXME: Check if we need to remove this later.
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
