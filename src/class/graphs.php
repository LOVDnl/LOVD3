<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-11
 * Modified    : 2015-05-18
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               David Baux <david.baux@inserm.fr>
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





class LOVD_Graphs {
    // This class provides different methods for graphs implemented using Flot, a JS graph library with jQuery handlers.





    function getHoverFunction ($sDIV, $nTotal)
    {
        // Prints hover function for pie charts, that are needed to display the correct hover text.
        // It would be nice if we can rewrite this function somehow to know from where we're called,
        // so we can update the correct DIV for each graph.
        // As long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.

        return '        function ' . $sDIV . '_hover (event, pos, obj)' . "\n" .
               '        {' . "\n" .
               '            // Handles the hover label generation and fade.' . "\n" .
               '            if (!obj) {' . "\n" .
               '                // Although obj seems to be NULL also half of the time while hovering the pie, this if() is only activated when you\'re moving outside of the pie.' . "\n" .
               '                $("#' . $sDIV . '_hover").fadeOut(1000);' . "\n" .
               '                return;' . "\n" .
               '            }' . "\n" .
               '            sMessage = obj.series.datapoints.points[1] + "/' . $nTotal . ' (" + parseFloat(obj.series.percent).toFixed(1) + "%)";' . "\n" .
               '            $("#' . $sDIV . '_hover").stop(true, true); // Completes possible fading animation immediately and empties queue.' . "\n" .
               '            $("#' . $sDIV . '_hover").show(); // Shows the div, that may have been hidden.' . "\n" .
               '            $("#' . $sDIV . '_hover").html("<B>" + obj.series.label + ": " + sMessage + "</B>");' . "\n" .
               '        }' . "\n";
    }





    function getPieGraph ()
    {
        // Prints standard pie graph settings.

        return '                pie: {' . "\n" .
               '                    show: true,' . "\n" .
               '                    radius: .9, // A little smaller than full size' . "\n" .
               '                    innerRadius: .4, // The donut effect' . "\n" .
               '                    label: {' . "\n" .
               '                        show: true,' . "\n" .
               '                        radius: 3/4,' . "\n" .
               '                        formatter: function(label, series) {' . "\n" .
               '                            return \'<DIV class="S09" style="text-align:center; padding : 2px; color : #FFF;">\' + label + "<BR>" + Math.round(series.percent)+\'%</DIV>\';' . "\n" .
               '                        },' . "\n" .
               '                        background: {opacity: 0.5, color: "#000"},' . "\n" .
               '                        threshold: 0.05 // 5%' . "\n" .
               '                    },' . "\n" .
               '                    highlight: {opacity : 0.25} // Less highlighting than the default.' . "\n" .
               '                }' . "\n";
    }





    function genesLinkedDiseases ($sDIV, $Data = array())
    {
        // Shows a nice pie chart about the number of diseases per gene in a certain data set.
        // $Data can be either a * (all genes), or an array of gene symbols.
        global $_DB;

        if (empty($sDIV)) {
            return false;
        }

        print('      <SCRIPT type="text/javascript">' . "\n");

        if (empty($Data)) {
            print('        $("#' . $sDIV . '").html("Error: LOVD_Graphs::genesLinkedDiseases()<BR>No data received to create graph.");' . "\n" .
                  '      </SCRIPT>' . "\n\n");
            return false;
        }

        // Keys need to be renamed.
        $aTypes =
             array(
                    '0'  => array('None', '#000'),
                    '1'  => array('1 disease', '#800'),
                    '2'  => array('2 diseases', '#A30'),
                    '3'  => array('3 diseases', '#D60'),
                    '4'  => array('4 diseases', '#FC0'),
                    '5'  => array('5 diseases', '#FF0'),
                    '>5' => array('More than 5', '#CF0'),
                  );

        // Retricting to a certain set of genes, or full database ($Data == '*', although we actually don't check the value of $Data).
        if (!is_array($Data)) {
            $qData = $_DB->query('SELECT g.id, COUNT(g2d.diseaseid) FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) GROUP BY geneid');
        } elseif (count($Data)) {
            // Using list of gene IDs.
            $qData = $_DB->query('SELECT g.id, COUNT(g2d.diseaseid) FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) WHERE geneid IN (?' . str_repeat(',?', count($Data)-1) . ') GROUP BY geneid', array($Data));
        }

        // Fetch and group data.
        $aData = array_combine(array_keys($aTypes), array_fill(0, count($aTypes), 0));
        while (list($sGene, $nCount) = $qData->fetchRow()) {
            if ($nCount < 5) {
                $sType = (string) $nCount;
            } else {
                $sType = '>5';
            }
            $aData[$sType] ++;
        }

        // Format $aData.
        print('        var data = [');
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sType => $nValue) {
            if (isset($aTypes[$sType])) {
                $sLabel = $aTypes[$sType][0];
            } else {
                $sLabel = $sType;
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . (!isset($aTypes[$sType][1])? '' : ', color: "' . $aTypes[$sType][1] . '"') . '}');
            $nTotal += $nValue;
        }
        if (!$aData) {
            // There was no data... give "fake" data such that the graph can still be generated.
            print('{label: "No data to show", data: 1, color: "#000"}');
            $nTotal = 1;
        }
        print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              $this->getPieGraph() .
              '            },' . "\n" .
              '            grid: {hoverable: true}' . "\n" .

/*
        combine: {
            threshold: 0-1 for the percentage value at which to combine slices (if they're too small)
            color: any hexidecimal color value (other formats may or may not work, so best to stick with something like '#CCC'), if null, the plugin will automatically use the color of the first slice to be combined
            label: any text value of what the combined slice should be labeled
        }
*/
              '        });' . "\n" .
              '        $("#' . $sDIV . '").bind("plothover", ' . $sDIV . '_hover);' . "\n\n" .

        // Pretty annoying having to define this function for every pie chart on the page, but as long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.
        $this->getHoverFunction($sDIV, $nTotal) .
              '      </SCRIPT>' . "\n\n");

        flush();
        return true;
    }





    function genesNumberOfVariants ($sDIV, $Data = array(), $bNonPublic = false)
    {
        // Shows a nice pie chart about the number of variants per gene in a certain data set.
        // $Data can be either a * (all genes), or an array of gene symbols.
        // $bNonPublic indicates whether or not only the public variants should be used.
        global $_DB;

        if (empty($sDIV)) {
            return false;
        }

        print('      <SCRIPT type="text/javascript">' . "\n");

        if (empty($Data)) {
            print('        $("#' . $sDIV . '").html("Error: LOVD_Graphs::genesNumberOfVariants()<BR>No data received to create graph.");' . "\n" .
                  '      </SCRIPT>' . "\n\n");
            return false;
        }

        // Keys need to be renamed.
        $aTypes =
             array(
                    '0'      => array('None', '#000'),
                    '<=10'   => array('1 - 10 variants', '#800'),
                    '<=50'   => array('11 - 50 variants', '#A30'),
                    '<=100'  => array('51 - 100 variants', '#D60'),
                    '<=500'  => array('101 - 500 variants', '#FA0'),
                    '<=1000' => array('501 - 1000 variants', '#FD0'),
                    '>1000'  => array('More than 1000', '#FF0'),
                  );

        // Retricting to a certain set of genes, or full database ($Data == '*', although we actually don't check the value of $Data).
        if (!is_array($Data)) {
            $qData = $_DB->query('SELECT t.geneid, COUNT(DISTINCT vot.id) FROM ' .
                (!$bNonPublic? TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) ' : TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ') .
                'INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)' .
                ($bNonPublic? '' : ' WHERE statusid >= ' . STATUS_MARKED) .
                ' GROUP BY t.geneid');
        } elseif (count($Data)) {
            // Using list of gene IDs.
            $qData = $_DB->query('SELECT t.geneid, COUNT(DISTINCT vot.id) FROM ' .
                ($bNonPublic? '' : TABLE_VARIANTS . ' AS vog INNER JOIN ') .
                TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid IN (?' . str_repeat(',?', count($Data)-1) . ')' .
                ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) .
                ' GROUP BY t.geneid', array($Data));
        }

        // Fetch and group data.
        $aData = array_combine(array_keys($aTypes), array_fill(0, count($aTypes), 0));
        while (list($sGene, $nCount) = $qData->fetchRow()) {
            if (!$nCount) {
                $sType = '0';
            } elseif ($nCount <= 10) {
                $sType = '<=10';
            } elseif ($nCount <= 50) {
                $sType = '<=50';
            } elseif ($nCount <= 100) {
                $sType = '<=100';
            } elseif ($nCount <= 500) {
                $sType = '<=500';
            } elseif ($nCount <= 1000) {
                $sType = '<=1000';
            } else {
                $sType = '>1000';
            }
            $aData[$sType] ++;
        }

        // Format $aData.
        print('        var data = [');
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sType => $nValue) {
            if (isset($aTypes[$sType])) {
                $sLabel = $aTypes[$sType][0];
            } else {
                $sLabel = $sType;
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . (!isset($aTypes[$sType][1])? '' : ', color: "' . $aTypes[$sType][1] . '"') . '}');
            $nTotal += $nValue;
        }
        if (!$aData) {
            // There was no data... give "fake" data such that the graph can still be generated.
            print('{label: "No data to show", data: 1, color: "#000"}');
            $nTotal = 1;
        }
        print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              $this->getPieGraph() .
              '            },' . "\n" .
              '            grid: {hoverable: true}' . "\n" .
              '        });' . "\n" .
              '        $("#' . $sDIV . '").bind("plothover", ' . $sDIV . '_hover);' . "\n\n" .

        // Pretty annoying having to define this function for every pie chart on the page, but as long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.
        $this->getHoverFunction($sDIV, $nTotal) .
              '      </SCRIPT>' . "\n\n");

        flush();
        return true;
    }
/*
        combine: {
            threshold: 0-1 for the percentage value at which to combine slices (if they're too small)
            color: any hexidecimal color value (other formats may or may not work, so best to stick with something like '#CCC'), if null, the plugin will automatically use the color of the first slice to be combined
            label: any text value of what the combined slice should be labeled
        }
*/





    function variantsLocations ($sDIV, $Data = array(), $bNonPublic = false, $bUnique = false, $bPathogenicOnly = false)
    {
        // Shows a nice pie chart about the variant locations on DNA level in a certain data set.
        // $Data can be either a * (whole database), a gene symbol or an array of variant IDs.
        // $bNonPublic indicates whether or not only the public variants should be used.
        // $bUnique indicates whether all variants or or the unique variants should be counted.
        // $bPathogenicOnly indicates whether the graph should show the results for (likely) pathogenic variants only (reported or concluded, VOG effectid only).
        global $_DB;

        if (empty($sDIV)) {
            return false;
        }

        print('      <SCRIPT type="text/javascript">' . "\n");

        if (empty($Data)) {
            print('        $("#' . $sDIV . '").html("Error: LOVD_Graphs::variantsLocations()<BR>No data received to create graph.");' . "\n" .
                  '      </SCRIPT>' . "\n\n");
            return false;
        }

        $nPathogenicThreshold = 7;

        // Keys need to be renamed.
        $aTypes =
            array(
                '5UTR'     => array('5\'UTR', '#F90'),        // Orange.
                'start'    => array('Start codon', '#600'),   // Dark dark red.
                'coding'   => array('Coding', '#00C'),        // Blue.
                'splice'   => array('Splice region', '#A00'), // Dark red.
                'intron'   => array('Intron', '#0AC'),        // Light blue.
                '3UTR'     => array('3\'UTR', '#090'),        // Green.
                'multiple' => array('Multiple', '#95F'),      // Purple.
                ''         => array('Unknown', '#000'),       // Black.
            );

        if (!is_array($Data)) {
            // Retricting to a certain gene, or full database ($Data == '*').
            // FIXME: Region "coding" doesn't make sense on non-coding transcripts, but in this case I won't handle for these situations.
            //   I guess this should be called "exonic", also removing both UTR regions, but I will leave this for some other time, if ever.
            if ($bUnique) {
                if ($Data == '*') {
                    $qPositions = $_DB->query('SELECT position_c_cds_end, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, 1 FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`');
                } else {
                    $qPositions = $_DB->query('SELECT position_c_cds_end, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, 1 FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE t.geneid = ?' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`', array($Data));
                }
            } else {
                if ($Data == '*') {
                    $qPositions = $_DB->query('SELECT position_c_cds_end, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`');
                } else {
                    $qPositions = $_DB->query('SELECT position_c_cds_end, position_c_start, position_c_start_intron, position_c_end, position_c_end_intron, COUNT(*) FROM ' . TABLE_TRANSCRIPTS . ' AS t INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id) WHERE t.geneid = ?' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`', array($Data));
                }
            }
        } else {
            // Using list of variant IDs.
        }

        $aData = array();
        while (list($nCDSend, $nPosStart, $nPosStartIntron, $nPosEnd, $nPosEndIntron, $nCount) = $qPositions->fetchRow()) {
            if (($nPosStart < 0 && $nPosEnd > 0) || ($nPosEnd > $nCDSend && $nPosStart < $nCDSend) || ($nPosStartIntron && !$nPosEndIntron) || ($nPosEndIntron && !$nPosStartIntron)) {
                $sType = 'multiple';
            } elseif ($nPosStartIntron && ($nPosEnd - $nPosStart) <= 1) {
                // Only intron if we're in the same intron...! Otherwise, we'll call it coding (whole exon deletion or duplication).
                if (abs($nPosStartIntron) <= 5 || abs($nPosEndIntron) <= 5) {
                    $sType = 'splice';
                } else {
                    $sType = 'intron';
                }
            } elseif ($nPosStart < 0) {
                $sType = '5UTR';
            } elseif (($nPosStart > 0 && $nPosStart <= 3) || ($nPosEnd > 0 && $nPosEnd <= 3)) {
                // Category 'start' is counted as well when just the start or end of the variant are located there, then 'multiple' is not selected.
                $sType = 'start';
            } elseif ($nPosStart < $nCDSend && !$nPosStartIntron) {
                $sType = 'coding';
            } elseif ($nPosStart >= $nCDSend) {
                $sType = '3UTR';
            } else {
                $sType = '';
            }

            if (!isset($aData[$sType])) {
                $aData[$sType] = 0;
            }
            $aData[$sType] += $nCount;
        }

        // Format $aData.
        print('        var data = [');
        ksort($aData); // May not work correctly, if keys are replaced...
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sType => $nValue) {
            if (isset($aTypes[$sType])) {
                $sLabel = $aTypes[$sType][0];
            } else {
                $sLabel = $sType;
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . (!isset($aTypes[$sType][1])? '' : ', color: "' . $aTypes[$sType][1] . '"') . '}');
            $nTotal += $nValue;
        }
        if (!$aData) {
            // There was no data... give "fake" data such that the graph can still be generated.
            print('{label: "No data to show", data: 1, color: "#000"}');
            $nTotal = 1;
        }
        print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              $this->getPieGraph() .
              '            },' . "\n" .
              '            grid: {hoverable: true}' . "\n" .
              '        });' . "\n" .
              '        $("#' . $sDIV . '").bind("plothover", ' . $sDIV . '_hover);' . "\n\n" .

              // Add the total number to the header above the graph.
              '        $("#' . $sDIV . '").parent().children(":first").append(" (' . $nTotal . ')");' . "\n\n" .

              // Pretty annoying having to define this function for every pie chart on the page, but as long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.
              $this->getHoverFunction($sDIV, $nTotal) .
              '      </SCRIPT>' . "\n\n");

        flush();
        return true;
    }





    function variantsTypeDNA ($sDIV, $Data = array(), $bNonPublic = false, $bUnique = false, $bPathogenicOnly = false)
    {
        // Shows a nice pie chart about the variant types on DNA level in a certain data set.
        // $Data can be either a * (whole database), a gene symbol or an array of variant IDs.
        // $bNonPublic indicates whether or not only the public variants should be used.
        // $bUnique indicates whether all variants or or the unique variants should be counted.
        // $bPathogenicOnly indicates whether the graph should show the results for (likely) pathogenic variants only (reported or concluded, VOG effectid only).
        global $_DB;

        if (empty($sDIV)) {
            return false;
        }

        print('      <SCRIPT type="text/javascript">' . "\n");

        if (empty($Data)) {
            print('        $("#' . $sDIV . '").html("Error: LOVD_Graphs::variantsTypeDNA()<BR>No data received to create graph.");' . "\n" .
                  '      </SCRIPT>' . "\n\n");
            return false;
        }

        $nPathogenicThreshold = 7;

        // Keys need to be renamed.
        $aTypes =
            array(
                ''       => array('Unknown', '#000'),
                'del'    => array('Deletions', '#A00'),
                'delins' => array('Indels', '#95F'),
                'dup'    => array('Duplications', '#F90'),
                'ins'    => array('Insertions', '#090'),
                'inv'    => array('Inversions', '#0AC'),
                'subst'  => array('Substitutions', '#00C'),
            );

        if (!is_array($Data)) {
            // Restricting to a certain gene, or full database ($Data == '*').
            if ($bUnique) {
                if ($Data == '*') {
                    $qData = $_DB->query('SELECT type, COUNT(DISTINCT type) FROM ' . TABLE_VARIANTS . ' AS vog WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`');
                } else {
                    $qData = $_DB->query('SELECT type, COUNT(DISTINCT type) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY `VariantOnGenome/DBID`', array($Data));
                }
                $aData = array();
                while (list($sType, $nCount) = $qData->fetchRow()) {
                    // If $nCount is greater than one, this DBID had more than one type. Probably a mistake, but we'll count it as complex.
                    if ($nCount > 1) {
                        $sType = 'complex';
                    }
                    if (!isset($aData[$sType])) {
                        $aData[$sType] = 0;
                    }
                    $aData[$sType] ++;
                }
            } else {
                if ($Data == '*') {
                    $aData = $_DB->query('SELECT type, COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY type')->fetchAllCombine();
                } else {
                    $aData = $_DB->query('SELECT type, COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog WHERE vog.id IN (SELECT DISTINCT vot.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?)' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vog.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY type', array($Data))->fetchAllCombine();
                }
            }
        } else {
            // Using list of variant IDs.
        }

        // Format $aData.
        print('        var data = [');
        ksort($aData); // May not work correctly, if keys are replaced...
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sType => $nValue) {
            if (isset($aTypes[$sType])) {
                $sLabel = $aTypes[$sType][0];
            } else {
                $sLabel = $sType;
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . (!isset($aTypes[$sType][1])? '' : ', color: "' . $aTypes[$sType][1] . '"') . '}');
            $nTotal += $nValue;
        }
        if (!$aData) {
            // There was no data... give "fake" data such that the graph can still be generated.
            print('{label: "No data to show", data: 1, color: "#000"}');
            $nTotal = 1;
        }
        print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              $this->getPieGraph() .
              '            },' . "\n" .
              '            grid: {hoverable: true}' . "\n" .
              '        });' . "\n" .
              '        $("#' . $sDIV . '").bind("plothover", ' . $sDIV . '_hover);' . "\n\n" .

        // Add the total number to the header above the graph.
              '        $("#' . $sDIV . '").parent().children(":first").append(" (' . $nTotal . ')");' . "\n\n" .

        // Pretty annoying having to define this function for every pie chart on the page, but as long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.
              $this->getHoverFunction($sDIV, $nTotal) .
              '      </SCRIPT>' . "\n\n");

        flush();
        return true;
    }





    function variantsTypeProtein ($sDIV, $Data = array(), $bNonPublic = false, $bUnique = false, $bPathogenicOnly = false)
    {
        // Shows a nice pie chart about the variant types on protein level in a certain data set.
        // $Data can be either a * (whole database), a gene symbol or an array of variant IDs.
        // $bNonPublic indicates whether or not only the public variants should be used.
        // $bUnique indicates whether all variants or or the unique variants should be counted.
        // $bPathogenicOnly indicates whether the graph should show the results for (likely) pathogenic variants only (reported or concluded, VOG effectid only).
        global $_DB;

        if (empty($sDIV)) {
            return false;
        }

        print('      <SCRIPT type="text/javascript">' . "\n");

        if (empty($Data)) {
            print('        $("#' . $sDIV . '").html("Error: LOVD_Graphs::variantsTypeProtein()<BR>No data received to create graph.");' . "\n" .
                  '      </SCRIPT>' . "\n\n");
            return false;
        }

        $nPathogenicThreshold = 7;

        // Keys need to be renamed.
        $aTypes =
            array(
                'frameshift'    => array('Frameshifts', '#FD6'),
                'inframedel'    => array('In frame deletions', '#A00'),
                'inframedelins' => array('In frame indels', '#95F'),
                'inframedup'    => array('In frame duplications', '#F90'),
                'inframeins'    => array('In frame insertions', '#090'),
                'missense'      => array('Missense changes', '#00C'),
                'no_protein'    => array('No protein produced', '#600'),
                'silent'        => array('Silent changes', '#0AC'),
                'stop'          => array('Stop changes', '#969'),
                ''              => array('Unknown', '#000'),
            );

        if (!is_array($Data)) {
            // Restricting to a certain gene, or full database ($Data == '*').
            if ($bUnique) {
                // FIXME: This is not really correct. When grouping on VOT/Protein, we might in theory be grouping variants over multiple transcripts that are not one variant on DNA level, but just happen to have the same description on protein level. Vice versa, we're counting one variant twice if it has different descriptions on different transcripts.
                if ($Data == '*') {
                    $qProteinDescriptions = $_DB->query('SELECT DISTINCT IFNULL(REPLACE(`VariantOnTranscript/Protein`, " ", ""), "") AS protein, 1 FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')'));
                } else {
                    $qProteinDescriptions = $_DB->query('SELECT DISTINCT IFNULL(REPLACE(`VariantOnTranscript/Protein`, " ", ""), "") AS protein, 1 FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')'), array($Data));
                }
            } else {
                if ($Data == '*') {
                    $qProteinDescriptions = $_DB->query('SELECT IFNULL(REPLACE(`VariantOnTranscript/Protein`, " ", ""), "") AS protein, COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) WHERE 1=1' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY protein');
                } else {
                    $qProteinDescriptions = $_DB->query('SELECT IFNULL(REPLACE(`VariantOnTranscript/Protein`, " ", ""), "") AS protein, COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . ($bNonPublic? '' : ' AND statusid >= ' . STATUS_MARKED) . (!$bPathogenicOnly? '' : ' AND (LEFT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ' OR RIGHT(vot.effectid, 1) >= ' . $nPathogenicThreshold . ')') . ' GROUP BY protein', array($Data));
                }
            }
        } else {
            // Using list of variant IDs.
        }

        $aData = array();
        while (list($sProteinDescription, $nCount) = $qProteinDescriptions->fetchRow()) {
            // Sort of ordered on average percentage used, to spare the number of comparisons needed.
            // However, make sure that the 'fs' check is always done before the missense and the stop-check, to prevent false positives.
            // Also, del, dup and ins checks must be done before missense checks.
            // The missense check is done a bit later, after more simper comparisons.
            if (strpos($sProteinDescription, '=') !== false) {
                $sType = 'silent';
            } elseif (!$sProteinDescription || $sProteinDescription == '-' || strpos($sProteinDescription, '?') !== false) {
                $sType = '';
            } elseif (strpos($sProteinDescription, 'fs') !== false) {
                $sType = 'frameshift';
            } elseif (preg_match('/[\*X]/', $sProteinDescription)) {
                $sType = 'stop';
            } elseif (strpos($sProteinDescription, 'del') !== false) {
                if (strpos($sProteinDescription, 'ins') !== false) {
                    $sType = 'inframedelins';
                } else {
                    $sType = 'inframedel';
                }
            } elseif (strpos($sProteinDescription, 'dup') !== false) {
                $sType = 'inframedup';
            } elseif (strpos($sProteinDescription, 'ins') !== false) {
                $sType = 'inframeins';
            } elseif (preg_match('/p\.\(?([A-Za-z]{1,3})\d+([A-Za-z]{1,3})?\)?/', $sProteinDescription, $aRegs)) {
                if (empty($aRegs[2]) || $aRegs[1] == $aRegs[2]) {
                    $sType = 'silent';
                } else {
                    $sType = 'missense';
                }
            } elseif (strpos($sProteinDescription, '.0') !== false || preg_match('/^p\.\(?del\)?$/', $sProteinDescription)) {
                $sType = 'no_protein';
            } elseif (strpos($sProteinDescription, 'del') !== false) {
                $sType = 'inframedel';
            } elseif (preg_match('/dup/', $sProteinDescription)) {
                $sType = 'inframedup';
            } else {
                $sType = '';
            }

            if (!isset($aData[$sType])) {
                $aData[$sType] = 0;
            }
            $aData[$sType] += $nCount;
        }

        // Format $aData.
        print('        var data = [');
        ksort($aData); // May not work correctly, if keys are replaced...
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sType => $nValue) {
            if (isset($aTypes[$sType])) {
                $sLabel = $aTypes[$sType][0];
            } else {
                $sLabel = $sType;
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . (!isset($aTypes[$sType][1])? '' : ', color: "' . $aTypes[$sType][1] . '"') . '}');
            $nTotal += $nValue;
        }
        if (!$aData) {
            // There was no data... give "fake" data such that the graph can still be generated.
            print('{label: "No data to show", data: 1, color: "#000"}');
            $nTotal = 1;
        }
        print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              $this->getPieGraph() .
              '            },' . "\n" .
              '            grid: {hoverable: true}' . "\n" .
              '        });' . "\n" .
              '        $("#' . $sDIV . '").bind("plothover", ' . $sDIV . '_hover);' . "\n\n" .

              // Add the total number to the header above the graph.
              '        $("#' . $sDIV . '").parent().children(":first").append(" (' . $nTotal . ')");' . "\n\n" .

              // Pretty annoying having to define this function for every pie chart on the page, but as long as we don't hack into the FLOT library itself to change the arguments to this function, there is no other way.
              $this->getHoverFunction($sDIV, $nTotal) .
              '      </SCRIPT>' . "\n\n");

        flush();
        return true;
    }
}



/*************** OLD LOVD 2.0 CODE THAT NEEDS TO BE IMPLEMENTED ****************
// To check availability of the Variant/DNA, Variant/RNA, Variant/Protein and Patient/Times_reported columns, we need the CurrDB class.
require ROOT_PATH . 'class/currdb.php';
$_CURRDB = new CurrDB();
$sMutationCol = $_CURRDB->getMutationCol();

// 2009-06-24; 2.0-19; see if you can use exon lengths
$sFileName = ROOT_PATH . 'refseq/' . $_SESSION['currdb'] . '_table.txt';
if (file_exists($sFileName)) {
    // read each line of the file into an array
    $aExonTable = array();
    $i = 0;
    foreach (file($sFileName) as $line) {
        $aLine = explode("\t", $line);
        $aExonTable[$i][0] = $aLine[5];
        if (isset($aLine[6])) {
            $aExonTable[$i][1] = trim($aLine[6]);
        }
        $i++;
    }

    // 2010-02-22; 2.0-25; Remove the headers, to prevent exon numbers as '00' to cause errors.
    if ($aExonTable[0][0] == 'lengthExon' && $aExonTable[0][1] == 'lengthIntron') {
        unset($aExonTable[0]);
    }
}


// 2009-05-05; 2.0-19 added by Gerard: Count the number of variants per exon
if ($_CURRDB->colExists('Variant/Exon')) {
    // Initialize the counter array of DNA variants
    $aCounts = array();

    if ($_CURRDB->colExists('Patient/Times_Reported')) {
        // Use the Times_Reported column to count the number of patients.
        $sQ = 'SELECT v.`Variant/Exon`, SUM(p.`Patient/Times_Reported`) AS sum' .
              ' FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)' .
              ' LEFT JOIN ' . TABLE_PATIENTS . ' AS p USING (patientid)';
    } else {
        // No Times_Reported column found, consider every patient entry to be one case.
        $sQ = 'SELECT v.`Variant/Exon`, COUNT(p2v.patientid) AS sum' .
              ' FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)';
    }
    $sQ .= ' WHERE p2v.symbol = "' . $_SESSION['currdb'] . '"' . (IS_CURATOR? '' : ' AND p2v.status >= ' . STATUS_MARKED) . ' AND `' . $sMutationCol . '` NOT IN ("c.=", "c.0")' .
           ' GROUP BY v.`Variant/Exon`';
    $qExons = mysql_query($sQ);
    while ($r = mysql_fetch_row($qExons)) {
        // 2010-07-05; 2.0-27; take care of exon numbers with e and intron numbers with i
        if (preg_match('/^\d+i$/', $r[0])) {
            // Intronic numbering; 01i.
            $r[0] = preg_replace('/^0+/', '', $r[0]);
            if ($r[0] == 'i') {
                $r[0] = '0i';
            }
        } elseif (preg_match('/^\d+e?$/', $r[0])) {
            // Exonic numbering; 01e or 01.
            $r[0] = intval($r[0]);
        }
        @$aCounts[$r[0]] += $r[1];
    }

    // 2009-07-14; 2.0-20; store the number of variants per the number of nucleotides in an array
    $bStandExonNum = true;
    if (isset($aExonTable)) {
        foreach ($aCounts as $nExon => $nVariants) {
            // 2009-06-24; 2.0-19; compensate for exon or intron length
            if (preg_match('/^\d+i$/', $nExon) && !empty($aExonTable[intval($nExon)][1]) && is_numeric($aExonTable[intval($nExon)][1])) {
                // alternative intron numbering (e.g. 02i)
                $aFractionVariants[$nExon] = $nVariants/($aExonTable[intval($nExon)][1]);// number of variants per intron length
            } elseif (preg_match('/^\d+$/', $nExon) && array_key_exists($nExon, $aExonTable)) {
                // proper exon numbering, only digits allowed
                $aFractionVariants[$nExon] = $nVariants/($aExonTable[$nExon][0]);// number of variants per exon length
            } else {
                // non-standard exon numbering
                $aFractionVariants[$nExon] = 0;
                $bStandExonNum = false;
            }
        }
    }
    // After fetching and counting data, print it to the screen.
    // Print percentages in horizontal bars
    print('      <SPAN class="S15"><B>Variants per exon/intron</B></SPAN><BR>' . "\n" .
          '      <TABLE border cellpadding="2">' . "\n" .
          '        <TR>' . "\n" .
          '          <TH>exon</TH>' . "\n" .
          // 2009-07-14; 2.0-20; add column with number of nucleotides when exon lengths available
          (isset($aExonTable) ? '          <TH>variants/length</TH>' . "\n" : '          <TH># variants</TH>' . "\n") .
          '          <TH>' . (isset($aExonTable) ? '' : 'percentage of variants per exon') . '</TH></TR>' . "\n");


    // 2009-08-31; 2.0-21; added variable $nBarFraction to simplify the print the red bar statement
    $nBarFraction = '';
    // 2010-07-05; 2.0-27; Padding length depends on the maximum exon value for this gene.
    $lPadding = (max(array_keys($aCounts)) > 99 ? 3 : 2);
    foreach ($aCounts as $nExon => $nVariants) {
        print('        <TR>' . "\n" .
              '          <TD>' . str_pad($nExon, $lPadding + (preg_match('/^\d+i$/', $nExon)? 1 : 0), '0', STR_PAD_LEFT) . '</TD>' . "\n"); //column with exon numbers

        if (isset($aExonTable)) {
            $nBarFraction = $aFractionVariants[$nExon];
            if (preg_match('/^\d+i$/', $nExon) && !empty($aExonTable[intval($nExon)][1]) && is_numeric($aExonTable[intval($nExon)][1])) {
                // column with variants per number of nucleotides in intron
                print('          <TD align="right">' . $nVariants . ' / ' . $aExonTable[intval($nExon)][1] . ' bp</TD>' . "\n");
            // 2010-02-19; 2.0-25; Added intval() to make sure that exon numbers like "05" match also.
            } elseif (preg_match('/^\d+$/', $nExon) && array_key_exists(intval($nExon), $aExonTable)) {
                // column with variants per number of nucleotides in exon
                print('          <TD align="right">' . $nVariants . ' / ' . $aExonTable[intval($nExon)][0] . ' bp</TD>' . "\n");
            } else {
                print('          <TD align="right">' . $nVariants . '<sup>1</sup></TD>' . "\n");
            }
        } else {
            // 2009-02-12; 2.0-16 prevent division by zero
            $nBarFraction = round($nVariants/(array_sum($aCounts)+0.0000001)*100, 2) . '%';
            print('          <TD align="right">' . $nVariants . '</TD>' . "\n");
        }

        print('          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="' . $nBarFraction . '" title="' . $nBarFraction . '" width="' . (isset($aExonTable) ? $nBarFraction*$nBarWidth/max($aFractionVariants) : round($nVariants/(array_sum($aCounts)+0.0000001)*$nBarWidth)) . '" height="15"></TD></TR>' . "\n");
    }
    // Totals row
    if (isset($aExonTable)) {
        print('        </TABLE>' . "\n\n\n\n");
    } else {
        // 2009-06-24; 2.0-19; print only when no exon lengths availabe
        print('        <TR>' . "\n" .
              '          <TD>total</TD>' . "\n" .
              '          <TD align="right">' . array_sum($aCounts) . '</TD>' . "\n" .
              '          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="100%" title="100%" width="' . $nBarWidth . '" height="15"></TD></TR></TABLE>' . "\n");
    }
    if (!$bStandExonNum) {
        print('<sup>1</sup>When exon/intron lengths are not available, only the numbers of variants are given<BR><BR>' . "\n\n\n\n");
    } else {
        print('<BR>' . "\n\n\n\n");
    }
}





// 2009-08-17; 2.0-21  added notification
lovd_showInfoTable('Please note that numbers shown hereafter can deviate from the numbers when you click on a variant link. Reasons for these differences can be that a variant is reported more than once (see # Reported field) or a homozygous variant.', 'warning');

// 2009-09-01; 2.0-21 initialize an array in the session array for storage of variantid's to be used with the variant type links in the tables
$_SESSION['variant_statistics'][$_SESSION['currdb']] = array();





// Counting the RNA variants
if ($_CURRDB->colExists('Variant/RNA')) {
    $nTotalSum = 0;
    // Checking the RNA column.

    // Initialize the counter array
    $aCounts = array();
    $aCounts['sub']         = 0;    //substitutions
    $aCounts['del']         = 0;    //deletions
    $aCounts['dup']         = 0;    //duplications
    $aCounts['ins']         = 0;    //insertions
    $aCounts['delins']      = 0;    //insertions/deletions
    $aCounts['inv']         = 0;    //inversions
    $aCounts['spl']         = 0;    //splice variants
    $aCounts['twovars']     = 0;    //2 variants in 1 allel
    $aCounts['complex']     = 0;    //complex variants
    $aCounts['unknown']     = 0;    //unknown variants
    $aCounts['no effect']   = 0;    //no effect variants
    $aCounts['r.0']         = 0;    //no RNA produced


    if ($_CURRDB->colExists('Patient/Times_Reported')) {
        // Use the Times_Reported column to count the number of patients.
        $sQ = 'SELECT v.variantid, v.`Variant/RNA`, SUM(p.`Patient/Times_Reported`) AS sum FROM ' . TABLE_CURRDB_VARS .
              ' AS v LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid) LEFT JOIN ' . TABLE_PATIENTS . ' AS p USING (patientid)';
    } else {
        // No Times_Reported column found, consider every patient entry to be one case.
        $sQ = 'SELECT v.variantid, v.`Variant/RNA`, COUNT(p2v.patientid) AS sum FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)';
    }
    $sQ .= ' WHERE p2v.symbol = "' . $_SESSION['currdb'] . '"' . (IS_CURATOR? '' : ' AND p2v.status >= ' . STATUS_MARKED) . ' AND `' . $sMutationCol . '` NOT IN ("c.=", "c.0")' .
           ' GROUP BY v.variantid';
    $qRNA = @mysql_query($sQ);


    while (list($nVariantid, $sRNA, $nCount) = mysql_fetch_row($qRNA)) {
        if (preg_match($sUnknown, $sRNA) || !$sRNA) {
            // unknown variant
            $aCounts['unknown'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_unknown'][] = $nVariantid;
        } elseif (preg_match($sTwoVars, $sRNA)) {
            // two variants in one allele
            $aCounts['twovars'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_twovars'][] = $nVariantid;
        } elseif (preg_match($sSplice, $sRNA)) {
            // splice variant
            $aCounts['spl'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_spl'][] = $nVariantid;
        } elseif (preg_match($sComma, $sRNA)) {
            // complex variant
            $aCounts['complex'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_complex'][] = $nVariantid;
        } elseif (preg_match($sDelIns, $sRNA)) {
            // variant is an indel
            $aCounts['delins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_delins'][] = $nVariantid;
        } elseif (preg_match($sInv, $sRNA)) {
            // variant is an inversion
            $aCounts['inv'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_inv'][] = $nVariantid;
        } elseif (preg_match($sIns, $sRNA)) {
            // variant is an insertion
            $aCounts['ins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_ins'][] = $nVariantid;
        } elseif (preg_match($sDel, $sRNA)) {
            // variant is an deletion
            $aCounts['del'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_del'][] = $nVariantid;
        } elseif (preg_match($sDup, $sRNA)) {
            // variant is an duplication
            $aCounts['dup'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_dup'][] = $nVariantid;
        } elseif (preg_match($sSub, $sRNA)) {
            // variant is an substitution
            $aCounts['sub'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_sub'][] = $nVariantid;
        } elseif (preg_match($sSilent, $sRNA)) {
            // variant is an no effect RNA variant
            $aCounts['no effect'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_no effect'][] = $nVariantid;
        } elseif (preg_match($sNoRNA, $sRNA)) {
            // variant is an no RNA produced variant
            $aCounts['r.0'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_r.0'][] = $nVariantid;
        } else {
            // complex variant
            $aCounts['complex'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['RNA_complex'][] = $nVariantid;
        }
    }

    // After fetching and counting data, print it to the screen.
    // Print percentages in horizontal bars
    print('      <SPAN class="S15"><B>RNA variants</B></SPAN><BR>' . "\n" .
          '      <TABLE border="1">' . "\n" .
          '        <TR>' . "\n" .
          '          <TH>variant</TH>' . "\n" .
          '          <TH>number</TH>' . "\n" .
          '          <TH>percentages</TH></TR>' . "\n");

    $aAbsentVariants = array(); //2009-06-24; 2.0-19; keep track of non-observed variants
    foreach ($aCounts as $sVariant => $nVariants) {
        // 2009-02-12; 2.0-16 prevent division by zero
        $sPercentage = round($nVariants/(array_sum($aCounts)+0.0000001)*100, 2) . '%';
        if ($nVariants != 0) {
            // 2009-06-24; 2.0-19; print observed variants only
            print('        <TR>' . "\n" .
                '          <TD><A href="' .ROOT_PATH . 'variants.php?select_db=' . $sSymbol . '&action=view_all&view=RNA_' . $sVariant .'">' . $aVariants[$sVariant]['header'] . '</A></TD>' . "\n" .
                  '          <TD align="right">' . $nVariants . '</TD>' . "\n" .
                  '          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="' . $sPercentage . '" title="' . $sPercentage . '" width="' . (round($nVariants/(array_sum($aCounts)+0.0000001)*$nBarWidth)>1?round($nVariants/(array_sum($aCounts)+0.0000001)*$nBarWidth):1) . '" height="15"></TD></TR>' . "\n");
        } else {
            // 2009-06-24; 2.0-19; store non-observed variants
            $aAbsentVariants[] = $aVariants[$sVariant]['header'];
        }

    }

    // Totals row
    print('        <TR>' . "\n" .
          '          <TD>total</TD>' . "\n" .
          '          <TD align="right">' . array_sum($aCounts) . '</TD>' . "\n" .
          '          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="100%" title="100%" width="' . $nBarWidth . '" height="15"></TD></TR></TABLE>' . "\n\n\n\n");

    // 2009-06-24; 2.0-19; print non-observed variants
    if (!empty($aAbsentVariants)) {
        print('Variants not observed: ' . implode($aAbsentVariants, ', ') . '<BR><BR>');
    }

}

lovd_printGeneFooter();
require ROOT_PATH . 'inc-bot.php';
*////////////////// OLD LOVD 2.0 CODE THAT NEEDS TO BE IMPLEMENTED /////////////
?>
