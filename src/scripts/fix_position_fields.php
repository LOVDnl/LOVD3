<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-28
 * Modified    : 2017-02-06
 * For LOVD    : 3.0-19
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
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

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();

define('PAGE_TITLE', 'Fix variant position fields');
$_T->printHeader();
$_T->printTitle();
lovd_showInfoTable('This script is designed to verify and check all variant position fields in the database.
    The variant position fields may be wrong because of mistakes made in the import file when importing variants.
    Also, variants may not have been understood by LOVD before and therefore may be lacking positions.
    If you only manually added variants to LOVD, and adhered to the HGVS nomenclature standards, this script will not be useful.');

lovd_requireAUTH(LEVEL_MANAGER);





// Class for the Analyses.
class LOVD_VariantPositionAnalyses {
    protected $aAnalyses = array();
    protected $oBAR = NULL;





    public function __construct()
    {
        // Constructor that will load the progress bar and set up the analyses.
        global $_DB;

        // Set up the progress bar that we'll be reusing all the time.
        require_once ROOT_PATH . 'class/progress_bar.php';
        $this->oBAR = new ProgressBar();
        $this->oBAR->setMessageVisibility('done', true);

        // Define analyses.
        $this->aAnalyses = array(
/*
            'vog_total_variants' => // Total variants in the database.
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS,
                ),
            'vog_positions_swapped' => // The positions that have been swapped (end > start).
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE position_g_start > position_g_end',
                    'sql_fetch' => 'SELECT id, position_g_start, position_g_end FROM ' . TABLE_VARIANTS . ' WHERE position_g_start > position_g_end',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // We'll just simply swap the fields. That may not result in correct values, but this will be
                        //  checked later. For now, this simple change may do the trick.
                        return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET position_g_start = ?, position_g_end = ? WHERE id = ?', array($zRow['position_g_end'], $zRow['position_g_start'], $zRow['id']))->rowCount());
                    },
                ),
            'vog_positions_in_error' => // The positions that do not match the variant's description (analysis needed).
                array(
                    'sql_fetch_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NOT NULL AND position_g_start != 0 AND position_g_end IS NOT NULL AND position_g_end != 0',
                    'sql_fetch' => 'SELECT id, position_g_start, position_g_end, `VariantOnGenome/DNA` AS DNA FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NOT NULL AND position_g_start != 0 AND position_g_end IS NOT NULL AND position_g_end != 0',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // Verify every single variant, compare the calculated positions with the positions we have
                        //  stored. The calculated positions always win.
                        $aPositions = lovd_getVariantInfo($zRow['DNA']);
                        if ($aPositions) {
                            // The function recognized the variant.
                            if ($aPositions['position_start'] != $zRow['position_g_start'] || $aPositions['position_end'] != $zRow['position_g_end']) {
                                // Positions given by function do not match what is in the database. Fix!
                                return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET position_g_start = ?, position_g_end = ? WHERE id = ?', array($aPositions['position_start'], $aPositions['position_end'], $zRow['id']))->rowCount());
                            }
                        } else {
                            // Variant not recognized, but positions are stored. We're going to assume they are OK.
                        }
                        return array(0, 0);
                    },
                ),
            'vog_positions_missing' => // The variants that have no position fields.
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NULL OR position_g_start = 0 OR position_g_end IS NULL OR position_g_end = 0',
                    'sql_fetch' => 'SELECT id, `VariantOnGenome/DNA` AS DNA FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NULL OR position_g_start = 0 OR position_g_end IS NULL OR position_g_end = 0',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // Calculate positions for every variant. We ignore any position fields that
                        //  might be filled in, as we have determined we're missing at least one.
                        $aPositions = lovd_getVariantInfo($zRow['DNA']);
                        if ($aPositions) {
                            // The function recognized the variant.
                            return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET position_g_start = ?, position_g_end = ?, mapping_flags = mapping_flags &~ ' . MAPPING_NOT_RECOGNIZED . ' WHERE id = ?', array($aPositions['position_start'], $aPositions['position_end'], $zRow['id']))->rowCount());
                        } else {
                            // Variants not recognized by LOVD, will be handled by the next analysis.
                        }
                        return array(1, 0);
                    },
                ),
            'vog_variants_not_understood' => // The variants that have no position fields and can't be recognized by LOVD (analysis needed).
                array(
                    'sql_fetch_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NULL OR position_g_start = 0 OR position_g_end IS NULL OR position_g_end = 0',
                    'sql_fetch' => 'SELECT id, `VariantOnGenome/DNA` AS DNA FROM ' . TABLE_VARIANTS . ' WHERE position_g_start IS NULL OR position_g_start = 0 OR position_g_end IS NULL OR position_g_end = 0',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // Calculate positions for every variant. We ignore any position fields that
                        //  might be filled in, as we have determined we're missing at least one.
                        // We're assuming there's something wrong with this variant, otherwise the
                        //  previous analysis would have filled in the positions.

                        // Currently unsupported by lovd_getVariantInfo(): g.123= and g.123A=
                        if (preg_match('/^[cgmn]\.([0-9]+(_[0-9]+)?)[ACTG]?=$/', $zRow['DNA'], $aRegs)) {
                            // Fake the variant.
                            $zRow['DNA'] = 'g.' . $aRegs[1] . 'del';
                        // Positions but no variants in the DNA field.
                        } elseif (preg_match('/^[cgmn]\.([0-9]+(_[0-9]+)?)$/', $zRow['DNA'], $aRegs)) {
                            // Fake the variant.
                            $zRow['DNA'] .= 'del';
                        }

                        $aPositions = lovd_getVariantInfo($zRow['DNA']);
                        if ($aPositions) {
                            // The function recognized the variant.
                            return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET position_g_start = ?, position_g_end = ?, mapping_flags = mapping_flags &~ ' . MAPPING_NOT_RECOGNIZED . ' WHERE id = ?', array($aPositions['position_start'], $aPositions['position_end'], $zRow['id']))->rowCount());
                        }
                        return array(1, 0);
                    },
                ),
*/
            'vot_total_variants' => // Total VOT variants in the database.
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS,
                ),
            'vot_positions_swapped' => // The positions that have been swapped (end > start).
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start > position_c_end',
                    'sql_fetch' => 'SELECT id, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start > position_c_end',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // We'll just simply swap the fields. That may not result in correct values, but this will be
                        //  checked later. For now, this simple change may do the trick.
                        return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' SET position_c_start = ?, position_c_start_intron = ?, position_c_end = ?, position_c_end_intron = ? WHERE id = ?', array($zRow['position_c_end'], $zRow['position_c_end_intron'], $zRow['position_c_start'], $zRow['position_c_start_intron'], $zRow['id']))->rowCount());
                    },
                ),
            'vot_positions_in_error' => // The positions that do not match the variant's description (analysis needed).
                array(
                    'sql_fetch_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start IS NOT NULL AND position_c_start != 0 AND position_c_end IS NOT NULL AND position_c_end != 0',
                    'sql_fetch' => 'SELECT id, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, `VariantOnTranscript/DNA` AS DNA FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start IS NOT NULL AND position_c_start != 0 AND position_c_end IS NOT NULL AND position_c_end != 0',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // Verify every single variant, compare the calculated positions with the positions we have
                        //  stored. The calculated positions always win.
                        $aPositions = lovd_getVariantInfo($zRow['DNA']);
                        if ($aPositions) {
                            // The function recognized the variant.
                            if ($aPositions['position_start'] != $zRow['position_c_start'] ||
                                $aPositions['position_start_intron'] != $zRow['position_c_start_intron'] ||
                                $aPositions['position_end'] != $zRow['position_c_end'] ||
                                $aPositions['position_end_intron'] != $zRow['position_c_end_intron']) {
                                // Positions given by function do not match what is in the database. Fix!
                                return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' SET position_c_start = ?, position_c_start_intron = ?, position_c_end = ?, position_c_end_intron = ? WHERE id = ?', array($aPositions['position_start'], $aPositions['position_start_intron'], $aPositions['position_end'], $aPositions['position_end_intron'], $zRow['id']))->rowCount());
                            }
                        } else {
                            // Variant not recognized, but positions are stored. We're going to assume they are OK.
                        }
                        return array(0, 0);
                    },
                ),
            'vot_positions_missing' => // The variants that have no position fields.
                array(
                    'sql_count' => 'SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start IS NULL OR position_c_start = 0 OR position_c_end IS NULL OR position_c_end = 0',
                    'sql_fetch' => 'SELECT id, `VariantOnTranscript/DNA` AS DNA FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' WHERE position_c_start IS NULL OR position_c_start = 0 OR position_c_end IS NULL OR position_c_end = 0',
                    'fix' => function ($zRow) use ($_DB)
                    {
                        // Calculate positions for every variant. We ignore any position fields that
                        //  might be filled in, as we have determined we're missing at least one.
                        $aPositions = lovd_getVariantInfo($zRow['DNA']);
                        if ($aPositions) {
                            // The function recognized the variant.
                            return array(1, $_DB->query('UPDATE ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' SET position_c_start = ?, position_c_start_intron = ?, position_c_end = ?, position_c_end_intron = ? WHERE id = ?', array($aPositions['position_start'], $aPositions['position_start_intron'], $aPositions['position_end'], $aPositions['position_end_intron'], $zRow['id']))->rowCount());
                        } else {
                            // Variants not recognized by LOVD, will be handled by the next analysis.
                        }
                        return array(1, 0);
                    },
                ),
        );

        // Start counting already, because we always need these numbers.
        $this->oBAR->setMessage('Analysing...');
        $this->oBAR->setProgress(0);
        foreach ($this->aAnalyses as $sAnalysis => $aAnalysis) {
            if (isset($aAnalysis['sql_count'])) {
                $this->aAnalyses[$sAnalysis]['count'] = $_DB->query($aAnalysis['sql_count'])->fetchColumn();
            }
        }
        $this->oBAR->setMessage('<BR>');
    }





    public function runAnalyses ()
    {
        // Runs the analyses, updates the stats that should have been printed already, and runs the fixes.
        global $_DB;

        $this->oBAR->setProgress(0);
        foreach ($this->aAnalyses as $sAnalysis => $aAnalysis) {
            // Mark this row as being active.
            print('
      <SCRIPT type="text/javascript">$("#analyses_stats tr").removeClass("hover");$("#analyses_stats #tr_' . $sAnalysis . '").addClass("hover");</SCRIPT>');
            $this->oBAR->setMessage('Running analysis ' . $sAnalysis . ' ...');
            $this->oBAR->setProgress(0);

            if (!isset($aAnalysis['sql_fetch']) || !isset($aAnalysis['fix'])) {
                // We don't have a query to fetch the data, nor do we have a function to run over the entries.
                // We don't have anything to do here.
                $this->aAnalyses[$sAnalysis]['fixed'] = $aAnalysis['fixed'] = 0;

            } else {
                // We have something to analyse.

                // First, for the sake of being able to see how far we are, fetch the total number of entries
                //  we need to look at. If there is a separate query for that (if we don't know the actual
                //  count before), run that special query. Otherwise, fallback to the normal count. If that
                //  is also not available, fall back to the total_variants count.
                if (!isset($aAnalysis['sql_fetch_count'])) {
                    if (!isset($aAnalysis['count'])) {
                        $nData = $this->aAnalyses[substr($sAnalysis, 0, 3) . '_total_variants']['count'];
                    } else {
                        $nData = $aAnalysis['count'];
                    }
                } else {
                    // A special query was constructed to count the number of entries we need to look at.
                    // If we would be doing a fetchAll(), then we wouldn't need this.
                    $nData = $_DB->query($aAnalysis['sql_fetch_count'])->fetchColumn();
                }
                // We're optimizing for memory usage here, not speed. So we'll fetch the results line by line,
                //  and have the fix function called for every line. This does slow things down, and requires
                //  a separate count query, but I prefer that this script can handle any size of database.
                $qData = $_DB->query($aAnalysis['sql_fetch']);
                if (!isset($this->aAnalyses[$sAnalysis]['count'])) {
                    $this->aAnalyses[$sAnalysis]['count'] = 0;
                }
                if (!isset($this->aAnalyses[$sAnalysis]['fixed'])) {
                    $this->aAnalyses[$sAnalysis]['fixed'] = 0;
                }
                for ($i = 1; $zData = $qData->fetchAssoc(); $i ++) {
                    // The fix() function analyses the data row and updates the database if needed.
                    // It returns the number of entries it updated (0 or 1).
                    list($nMatched, $nUpdated) = $aAnalysis['fix']($zData);
                    if ($nMatched && !isset($aAnalysis['sql_count'])) {
                        // Fix function says this line matched, and we didn't have a count before.
                        $this->aAnalyses[$sAnalysis]['count'] += $nMatched;
                    }
                    $this->aAnalyses[$sAnalysis]['fixed'] += $nUpdated;
                    $this->aAnalyses[substr($sAnalysis, 0, 3) . '_total_variants']['fixed'] += $nUpdated;

                    // Update the progress.
                    if (!($i % 10) || $i == $nData) {
                        // Update the progress bar...
                        $this->oBAR->setProgress($i / $nData * 100);
                        // And show the updated counts (count/fixed, both can change).
                        $this->updateAnalysisRow($sAnalysis);
                    }
                }
                // If we had no data matching, update the row.
                if (!$nData) {
                    $this->updateAnalysisRow($sAnalysis);
                }
            }
            sleep(1);
        }
        print('
      <SCRIPT type="text/javascript">$("#analyses_stats tr").removeClass("hover");</SCRIPT>');
        $this->oBAR->setMessage('All done!');
        $this->oBAR->setProgress(100);
    }





    public function printStats ()
    {
        // Prints the stats in a table. Could be just pre-counts or already processed results.

        print('
      <TABLE class="data" id="analyses_stats">
        <TR>
          <TH>Analysis</TH>
          <TH style="text-align: right;">Count</TH>
          <TH>Fixed</TH>
        </TR>');
        foreach ($this->aAnalyses as $sAnalysis => $aAnalysis) {
            // If we don't have counts yet (analysis needed), then put a question mark.
            if (!isset($aAnalysis['count'])) {
                $aAnalysis['count'] = '?';
            }
            // If we don't have fix counts yet (fix needed), then put a hyphen.
            if (!isset($aAnalysis['fixed'])) {
                $aAnalysis['fixed'] = '-';
            }
            print('
        <TR id="tr_' . $sAnalysis . '">
          <TD>' . $sAnalysis . '</TD>
          <TD style="text-align: right;">' . $aAnalysis['count'] . '</TD>
          <TD style="text-align: right;">' . $aAnalysis['fixed'] . '</TD></TR>');
        }
        print('</TABLE>');
    }





    protected function updateAnalysisRow ($sAnalysis)
    {
        // Update the row in the table, in case the counts ("count" and "fixed" counts) changed.
        $sTotalVariants = substr($sAnalysis, 0, 3) . '_total_variants';
        print('
      <SCRIPT type="text/javascript">
        $("#analyses_stats #tr_' . $sAnalysis . ' td:eq(1)").html("' . $this->aAnalyses[$sAnalysis]['count'] . '");
        $("#analyses_stats #tr_' . $sAnalysis . ' td:eq(2)").html("' . $this->aAnalyses[$sAnalysis]['fixed'] . '");
        $("#analyses_stats #tr_' . $sTotalVariants . ' td:eq(2)").html("' . $this->aAnalyses[$sTotalVariants]['fixed'] . '");
      </SCRIPT>');
    }
}





// Instantiate class, get counts.
$_ANALYSES = new LOVD_VariantPositionAnalyses();

// Print current stats in a table.
$_ANALYSES->printStats();



// If we don't have an ACTION, just print the button to start the analysis.
if (!ACTION) {
    print('
      <BR>
      <BUTTON onclick="window.location.href = \'' . CURRENT_PATH . '?run\';">Start analyses / fixes &raquo;</BUTTON>');
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
