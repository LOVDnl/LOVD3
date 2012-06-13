<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-11
 * Modified    : 2012-06-13
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

    function variantsTypeDNA ($sDIV, $Data = array(), $bPublicOnly = true, $bUnique = false)
    {
        // Shows a nice piechart about the variant types on DNA level in a certain data set.
        // $aData can be either a gene symbol or an array of variant IDs.
        // $bPublicOnly indicates whether or not only the public variants should be used.
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

        // Keys need to be renamed.
        $aRename =
             array(
                    ''       => 'Unknown',
                    'del'    => 'Deletions',
                    'delins' => 'Indels',
                    'dup'    => 'Duplications',
                    'ins'    => 'Insertions',
                    'inv'    => 'Inversions',
                    'subst'  => 'Substitutions',
                  );

        if (!is_array($Data)) {
            // Retricting to a certain gene.
            if ($bUnique) {
                // Not correct; fix.
                $qData = $_DB->query('SELECT type, COUNT(DISTINCT type) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . (!$bPublicOnly? '' : ' AND statusid >= ' . STATUS_MARKED) . ' GROUP BY `VariantOnGenome/DBID`', array($Data));
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
                $aData = $_DB->query('SELECT type, COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = ?' . (!$bPublicOnly? '' : ' AND statusid >= ' . STATUS_MARKED) . ' GROUP BY type', array($Data))->fetchAllCombine();
            }
        } else {
            // Using list of variant IDs.
        }

        // Format $a.
        print('        var data = [');
        ksort($aData); // May not work correctly, if keys are replaced...
        $i = 0;
        $nTotal = 0;
        foreach ($aData as $sLabel => $nValue) {
            if (isset($aRename[$sLabel])) {
                $sLabel = $aRename[$sLabel];
            }
            print(($i++? ',' : '') . "\n" .
                  '            {label: "' . $sLabel . '", data: ' . $nValue . '}');
            $nTotal += $nValue;
        }
		print('];' . "\n\n" .
              '        $.plot($("#' . $sDIV . '"), data,' . "\n" .
              '        {' . "\n" .
              '            series: {' . "\n" .
              '                pie: {' . "\n" .
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
              '                }' . "\n" .
              '           },' . "\n" .
              '           grid: {hoverable: true}' . "\n" .

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
              '        function ' . $sDIV . '_hover (event, pos, obj)' . "\n" .
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
              '        }' . "\n" .
              '      </SCRIPT>' . "\n\n");

        return true;
    }
}



/*************** OLD LOVD 2.0 CODE THAT NEEDS TO BE IMPLEMENTED ****************
// DMD_SPECIFIC
function lovd_determineLocation($sDNA) {
    // Function to determine the location of DNA variants (5'ATG, coding, intron, 3'stop)
    if (preg_match("/[0-9]+[\+\-][0-9]+/", $sDNA)) {
        //variant is located in an intron
        return 2;
    } elseif (preg_match("/\-[0-9]+/", $sDNA)) {
        //variant is located before the 5' ATG start codon
        return 0;
    } elseif (preg_match("/\*[0-9]+/", $sDNA)) {
        //variant is located after the 3' stop codon
        return 3;
    } else {
        //variant is located in the coding region
        return 1;
    }
}

$nBarWidth = 600;// Set the width for a 100% bar

// Array with row headers for the final output tables
$aVariants['sub']['header']             = 'substitutions';
$aVariants['del']['header']             = 'deletions';
$aVariants['dup']['header']             = 'duplications';
$aVariants['ins']['header']             = 'insertions';
$aVariants['delins']['header']          = 'insertion/deletions';
$aVariants['inv']['header']             = 'inversions';
$aVariants['twovars']['header']         = '2 variants in 1 allele';
$aVariants['spl']['header']             = 'splice variants';
$aVariants['fs']['header']              = 'frame shifts';
$aVariants['no protein']['header']      = 'no protein variants';
$aVariants['p.X']['header']             = 'nonstop variants';
$aVariants['X']['header']               = 'nonsense';
$aVariants['p.Met']['header']           = 'translation initiation variant';
$aVariants['=']['header']               = 'silent';
$aVariants['complex']['header']         = 'complex';
$aVariants['unknown']['header']         = 'unknown';
$aVariants['r.0']['header']             = 'no RNA produced';
$aVariants['no effect']['header']       = 'no effect';


// create regular expressions
$sFraShift      = "/fs/";                                                       // frame shift
$sPredFraShift  = "/\([a-zA-Z]{1,3}(\d)*[a-zA-Z]*fs/i";                         // frame shift (predicted)

$sNonStop       = "/extX/";                                                     // nonstop
$sPredNonStop   = "/\([a-zA-Z0-9_]*extX/";                                      // nonstop (predicted)

// 2010-07-23; 2.0-28; also include the alternative writing with "*"
$sNonsense      = "/X|\*//*";                                                     // nonsense
$sPredNonsense  = "/\([a-zA-Z]{1,3}(\d)*(X|\*)/";                               // nonsense (predicted)

$sNoProtein      = "/p\.0/";                                                    // no translation
$sPredNoProtein  = "/p\.\(0\)[^\?]/";                                           // no translation (predicted)

// 2011-01-25; 2.0-30; included position 1 when searching for translation inition variants
$sTransInit     = "/p\.Met1/";                                                  // translation initiation
$sPredTransInit = "/p\.\(Met1/";                                                // translation initiation (predicted)

$sSilent        = "/=/";                                                        // silent

$sUnknown       = "/\?|\(|^r\.$|^p\.$/";                                        // unknown

$sSplice        = "/spl/";                                                      // splice variant
$sPredSplice    = "/\(spl/";                                                    // splice variant (predicted)

$sDelIns        = "/del(\w)*ins/";                                              // insertion/deletion
$sPredDelIns    = "/\([a-zA-Z0-9_]*del(\w)*ins/";                               // insertion/deletion (predicted)

$sInv           = "/inv/";                                                      // inversion
$sPredInv       = "/\([a-zA-Z0-9_]*inv/";                                       // inversion

$sIns           = "/ins/";                                                      // insertion
$sPredIns       = "/\([a-zA-Z0-9_]*ins(\d)*//*";                                  // insertion

$sDup           = "/dup/";                                                      // duplication
$sPredDup       = "/\([a-zA-Z0-9_]*dup/";                                       // duplication

$sDel           = "/del/";                                                      // deletion
$sPredDel       = "/\([a-zA-Z0-9_]*del(\d)*//*";                                  // predicted deletion

$sSub           = "/>/";                                                        // substitution

$sProtSub       = "/(^p\.)?[^\[][a-zA-Z]{1,3}\d+[a-zA-Z]{1,3}/";                // protein substitution
$sPredProtSub   = "/(^p\.)?\([a-zA-Z]{1,3}(\d)+[a-zA-Z]{1,3}\)$/";              // predicted protein substitution

$sComma         = "/,/";                                                        // a comma denotes a complex situation

$sTwoVars       = "/\;/";                                                       // a semicolon denotes 2 mutations in 1 allele
$sPredTwoVars   = "/\([a-zA-Z0-9_]*\;/";                                        // a semicolon denotes 2 mutations in 1 allele

$sNoRNA         = "/r\.0/";                                                     // no RNA produced

$sProtComp      = "/^p\.\(\=\)/";                                               // (=) denotes a complex situation in case of protein (p.)
$sProtUnknown   = "/\?/";                                                       // unknown in case of a protein


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

// Counting the DNA variants
if ($_CURRDB->colExists('Variant/DNA')) {
    // Checking the DNA column

    // Initialize arrays for counting locations of DNA variants (5'ATG, coding, intron, 3'stop)
    $aLocationSub       = array(0, 0, 0, 0);    //substitutions
    $aLocationDel       = array(0, 0, 0, 0);    //deletions
    $aLocationDup       = array(0, 0, 0, 0);    //duplications
    $aLocationIns       = array(0, 0, 0, 0);    //insertions
    $aLocationDelIns    = array(0, 0, 0, 0);    //insertions/deletions
    $aLocationInv       = array(0, 0, 0, 0);    //inversions

    // Initialize the counter array of DNA variants
    $aCounts = array();
    $aCounts['sub']     = $aLocationSub;    //substitutions
    $aCounts['del']     = $aLocationDel;    //deletions
    $aCounts['dup']     = $aLocationDup;    //duplications
    $aCounts['ins']     = $aLocationIns;    //insertions
    $aCounts['delins']  = $aLocationDelIns; //insertions/deletions
    $aCounts['inv']     = $aLocationInv;    //inversions
    $aCounts['twovars'] = 0;                //2 variants in 1 allel
    $aCounts['complex'] = 0;                //complex variants
    $aCounts['unknown'] = 0;                //unknown variants

    // Initialize the total sum of the counter array
    $nTotalSum = 0;

    // 2009-12-16; 2.0-24; added v.type
    if ($_CURRDB->colExists('Patient/Times_Reported')) {
        // Use the Times_Reported column to count the number of patients.
        $sQ = 'SELECT v.variantid, v.`Variant/DNA`, v.type, SUM(p.`Patient/Times_Reported`) AS sum' . 
              ' FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)' .
              ' LEFT JOIN ' . TABLE_PATIENTS . ' AS p USING (patientid)';
    } else {
        // No Times_Reported column found, consider every patient entry to be one case.
        $sQ = 'SELECT v.variantid, v.`Variant/DNA`, v.type, COUNT(p2v.patientid) AS sum' .
              ' FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)';
    }
    $sQ .= ' WHERE p2v.symbol = "' . $_SESSION['currdb'] . '"' . (IS_CURATOR? '' : ' AND p2v.status >= ' . STATUS_MARKED) . ' AND `' . $sMutationCol . '` NOT IN ("c.=", "c.0")' .
           ' GROUP BY v.variantid';

    $qDNA = @mysql_query($sQ);
    while (list($nVariantid, $sDNA, $sType, $nCount) = mysql_fetch_row($qDNA)) {
        // 2009-12-16; 2.0-24; added $sType and use that for counting variant types if possible        
        // 2010-05-21; 2.0-27; added cases for duplications and inversions which were lacking
        if ($sType) {
            switch ($sType) {
                case 'subst':
                    // variant is a substitution
                    $aCounts['sub'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_sub'][] = $nVariantid;
                    break;
                case 'del':
                    // variant is a deletion
                    $aCounts['del'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_del'][] = $nVariantid;
                    break;
                case 'dup':
                    // variant is a duplication
                    $aCounts['dup'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_dup'][] = $nVariantid;
                    break;
                case 'ins':
                    // variant is an insertion
                    $aCounts['ins'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_ins'][] = $nVariantid;
                    break;
                case 'inv':
                    // variant is an inversion
                    $aCounts['inv'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_inv'][] = $nVariantid;
                    break;
                case 'delins':
                    // variant is an indel
                    $aCounts['delins'][lovd_determineLocation($sDNA)] += $nCount;
                    $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_delins'][] = $nVariantid;
                    break;
            }
        } else {
            if (preg_match($sTwoVars, $sDNA)) {
                // two variants in one allele
                $aCounts['twovars'] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_twovars'][] = $nVariantid;
            } elseif (preg_match($sDelIns, $sDNA) || (preg_match($sIns, $sDNA) && preg_match($sDel, $sDNA))) {
                // variant is an indel
                $aCounts['delins'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_delins'][] = $nVariantid;
            } elseif (preg_match($sInv, $sDNA)) {
                // variant is an inversion
                $aCounts['inv'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_inv'][] = $nVariantid;
            } elseif (preg_match($sIns, $sDNA)) {
                // variant is an insertion
                $aCounts['ins'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_ins'][] = $nVariantid;
            } elseif (preg_match($sDup, $sDNA) && !preg_match($sDel, $sDNA)) {
                // variant is a duplication (and not a deletion)
                $aCounts['dup'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_dup'][] = $nVariantid;
            } elseif (preg_match($sDel, $sDNA)) {
                // variant is a deletion
                $aCounts['del'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_del'][] = $nVariantid;
            } elseif (preg_match($sSub, $sDNA)) {
                // variant is a substitution
                $aCounts['sub'][lovd_determineLocation($sDNA)] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_sub'][] = $nVariantid;
            } elseif (preg_match($sUnknown, $sDNA) || !$sDNA) {
                // unknown variant
                $aCounts['unknown'] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_unknown'][] = $nVariantid;
            } else {
                // variant is complex
                $aCounts['complex'] += $nCount;
                $_SESSION['variant_statistics'][$_SESSION['currdb']]['DNA_complex'][] = $nVariantid;
            }
        }
    }

    // Calculate the total number of variants (total sum of the counter array)
    foreach ($aCounts as $sVariant => $nVariants) {
        if (is_array($nVariants)) {
                $nTotalSum += array_sum($nVariants);
        } else {
                $nTotalSum += $nVariants;
        }
    }



    // After fetching and counting data, print it to the screen.
    // Table in a fixed order, also print zero values
    // Print percentages in horizontal bars
    print('      <SPAN class="S15"><B>DNA variants</B></SPAN><BR>' . "\n" .
          '      <TABLE border cellpadding="2">' . "\n" .
          '        <TR>' . "\n" .
          '          <TH>variant</TH>' . "\n" .
          '          <TH>number</TH>' . "\n" .
          '          <TH colspan=4>location</TH>' . "\n" .
          '          <TH colspan=4>percentages</TH></TR>' . "\n" .
          '        <TR>' . "\n" .
          '          <TH>&nbsp;</TH>' . "\n" .
          '          <TH>&nbsp;</TH>' . "\n" .
          '          <TH>5\'start</TH>' . "\n" .
          '          <TH>coding</TH>' . "\n" .
          '          <TH>intron</TH>' . "\n" .
          '          <TH>3\'stop</TH><TD></TD></TR>' . "\n");

    $aAbsentVariants = array(); //2009-06-24; 2.0-19; keep track of non-observed variants
    foreach ($aCounts as $sVariant => $nVariants) {
        // Print for each variant type a row. 
        if (is_array($nVariants)) {
            // The substitutions, deletions, duplications, insertions, indels and inversions
            // can be subdivided according to their location (before the 5' start, coding, intron
            // or after the 3' stop
            // First calculate the sum of the number of variants and print the row header and the total number for this variant
            $nVariantsSum = array_sum($nVariants);
            if ($nVariantsSum != 0) {
                //2009-06-24; 2.0-19; print the observed variants only
                print('        <TR>' . "\n" .
                      '          <TD><A href="' . ROOT_PATH . 'variants.php?select_db=' . $sSymbol . '&action=view_all&view=DNA_' . str_replace('/', '', $sVariant) .'">' . $aVariants[$sVariant]['header'] . '</A></TD>' . "\n" .
                      '          <TD align="right">' . $nVariantsSum . '</TD>' . "\n");
                // Now print the numbers for each location
                foreach ($nVariants as $location => $locnumber) {
                    print('          <TD align="right">' . $locnumber . '</TD>' . "\n");
                }
            } else {
                // 2009-06-24; 2.0-19; store non-observed variants
                $aAbsentVariants[] = $aVariants[$sVariant]['header'];
            }
        } else {
            // The other variants
            // First calculate the sum of the number of variants
            $nVariantsSum = $nVariants;
            if ($nVariantsSum != 0) {
                //2009-06-24; 2.0-19; print observed variants
                print('        <TR>' . "\n" .
                      '          <TD><A href="' . ROOT_PATH . 'variants.php?select_db=' . $sSymbol . '&action=view_all&view=DNA_' . str_replace('/', '', $sVariant) .'">' . $aVariants[$sVariant]['header'] . '</A></TD>' . "\n" .
                      '          <TD align="right">' . $nVariantsSum . '</TD>' . "\n");
                // Now print 4 times a '-'
                for ($i = 0; $i < 4; $i++) {
                    print('          <TD align="right">-</TD>' . "\n");
                }
            } else {
                // 2009-06-24; 2.0-19; store non-observed variants
                $aAbsentVariants[] = $aVariants[$sVariant]['header'];
            }
        }
        // Print the bar with a length relative to the total for this variant.
        // 2009-02-12; 2.0-16 prevent division by zero
        $sPercentage = round($nVariantsSum/($nTotalSum+0.0000001)*100, 2) . '%';
        if ($nVariantsSum != 0) {
            print('          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="' . $sPercentage . '" title="' . $sPercentage . '" width="' . round($nVariantsSum/($nTotalSum+0.0000001)*$nBarWidth) . '" height="15"></TD></TR>' . "\n");
        }
    }

    // Totals row
    print('        <TR>' . "\n" .
          '          <TD>totals</TD>' . "\n" .
          '          <TD align="right">' . $nTotalSum . '</TD>' . "\n");
    for ($i = 0; $i < 4; $i++) {
        print('          <TD align="right">' . 
              ($aCounts['sub'][$i] + 
               $aCounts['del'][$i] + 
               $aCounts['dup'][$i] + 
               $aCounts['ins'][$i] + 
               $aCounts['delins'][$i] + 
               $aCounts['inv'][$i]) . 
              '</TD>' . "\n");
    }
    print('          <TD><IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="100%" title="100%" width="' . $nBarWidth . '" height="15"></TD></TR></TABLE>' . "\n\n\n\n");
    // 2009-06-24; 2.0-19; print non-observed variants
    if (!empty($aAbsentVariants)) {
        print('Variants not observed: ' . implode($aAbsentVariants, ', ') . '<BR><BR>' . "\n\n\n\n");
    } else {
        print('<BR>' . "\n\n\n\n");
    }
}




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




// Counting the Protein variants
if ($_CURRDB->colExists('Variant/Protein')) {
    $nTotalSum = 0;
    // Checking the Protein column.

    // Initialize the counter arrays, true and predicted
    $aCounts = array();
    $aCountsPred = array();
    $aCounts['sub']             = 0; // substitutions
    $aCountsPred['sub']         = 0; // substitutions (predicted)
    $aCounts['del']             = 0; // deletions
    $aCountsPred['del']         = 0; // deletions (predicted)
    $aCounts['dup']             = 0; // duplications
    $aCountsPred['dup']         = 0; // duplications (predicted)
    $aCounts['ins']             = 0; // insertions
    $aCountsPred['ins']         = 0; // insertions (predicted)
    $aCounts['delins']          = 0; // insertions/deletions
    $aCountsPred['delins']      = 0; // insertions/deletions (predicted)
    $aCounts['twovars']         = 0; // 2 variants in 1 allel
    $aCountsPred['twovars']     = 0; // 2 variants in 1 allel (predicted)
    $aCounts['fs']              = 0; // frame shift
    $aCountsPred['fs']          = 0; // frame shift (predicted)
    $aCounts['no protein']      = 0; // no protein
    $aCountsPred['no protein']  = 0; // predicted no protein (predicted)
    $aCounts['p.X']             = 0; // nonstop
    $aCountsPred['p.X']         = 0; // nonstop (predicted)
    $aCounts['X']               = 0; // nonsense
    $aCountsPred['X']           = 0; // nonsense (predicted)
    $aCounts['p.Met']           = 0; // translation initiation
    $aCountsPred['p.Met']       = 0; // translation initiation (predicted)
    $aCounts['=']               = 0; // silent
    $aCountsPred['=']           = 0; // silent (predicted)
    $aCounts['complex']         = 0; // complex variants
    $aCountsPred['complex']     = 0; // complex variants (predicted)
    $aCounts['unknown']         = 0; // unknown variants
    $aCountsPred['unknown']     = 0; // unknown variants (predicted)
    

    if ($_CURRDB->colExists('Patient/Times_Reported')) {
        // Use the Times_Reported column to count the number of patients.
        $sQ = 'SELECT v.variantid, v.`Variant/Protein`, SUM(p.`Patient/Times_Reported`) AS sum FROM ' . TABLE_CURRDB_VARS . 
              ' AS v LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid) LEFT JOIN ' . TABLE_PATIENTS . ' AS p USING (patientid)';
    } else {
        // No Times_Reported column found, consider every patient entry to be one case.
        $sQ = 'SELECT v.variantid, v.`Variant/Protein`, COUNT(p2v.patientid) AS sum FROM ' . TABLE_CURRDB_VARS . ' AS v' .
              ' LEFT JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (variantid)';
    }
    $sQ .= ' WHERE p2v.symbol = "' . $_SESSION['currdb'] . '"' . (IS_CURATOR? '' : ' AND p2v.status >= ' . STATUS_MARKED) . ' AND `' . $sMutationCol . '` NOT IN ("c.=", "c.0")' .
           ' GROUP BY v.variantid';
    $qProtein = @mysql_query($sQ);

    // 2009-08-24; 2.0-21; countings split in true and predicted
    while (list($nVariantid, $sProtein, $nCount) = mysql_fetch_row($qProtein)) {
        if (preg_match($sPredTwoVars, $sProtein)) {
            // two variants in one allele (predicted)
            $aCountsPred['twovars'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_twovars'][] = $nVariantid;
        } elseif (preg_match($sTwoVars, $sProtein)) {
            // two variants in one allele
            $aCounts['twovars'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_twovars'][] = $nVariantid;
        } elseif (preg_match($sComma, $sProtein)) {
            //complex variant
            $aCounts['complex'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_complex'][] = $nVariantid;
        } elseif (preg_match($sProtUnknown, $sProtein) || !$sProtein) {
            // unknown variant
            $aCounts['unknown'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_unknown'][] = $nVariantid;
        } elseif (preg_match($sPredFraShift, $sProtein)) {
            // predicted frame shift variant (predicted)
            $aCountsPred['fs'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_fs'][] = $nVariantid;
        } elseif (preg_match($sFraShift, $sProtein)) {
            // frame shift variant
            $aCounts['fs'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_fs'][] = $nVariantid;
        } elseif (preg_match($sPredNonStop, $sProtein)) {
            // nonstop variant (predicted)
            $aCountsPred['p.X'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_p.X'][] = $nVariantid;
        } elseif (preg_match($sNonStop, $sProtein)) {
            // nonstop variant
            $aCounts['p.X'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_p.X'][] = $nVariantid;
        } elseif (preg_match($sPredNonsense, $sProtein)) {
            // nonsense variant (predicted)
            $aCountsPred['X'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_X'][] = $nVariantid;
        } elseif (preg_match($sNonsense, $sProtein)) {
            // nonsense variant
            $aCounts['X'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_X'][] = $nVariantid;
        } elseif (preg_match($sPredDelIns, $sProtein)) {
            // variant is an indel (predicted)
            $aCountsPred['delins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_delins'][] = $nVariantid;
        } elseif (preg_match($sDelIns, $sProtein)) {
            // variant is an indel
            $aCounts['delins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_delins'][] = $nVariantid;
        } elseif (preg_match($sPredDel, $sProtein)) {
            // variant is a deletion (predicted)
            $aCountsPred['del'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_del'][] = $nVariantid;
        } elseif (preg_match($sDel, $sProtein)) {
            // variant is an deletion
            $aCounts['del'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_del'][] = $nVariantid;
        } elseif (preg_match($sPredDup, $sProtein)) {
            // variant is an duplication (predicted)
            $aCountsPred['dup'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_dup'][] = $nVariantid;
        } elseif (preg_match($sDup, $sProtein)) {
            // variant is an duplication
            $aCounts['dup'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_dup'][] = $nVariantid;
        } elseif (preg_match($sPredIns, $sProtein)) {
            // variant is an insertion (predicted)
            $aCountsPred['ins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_ins'][] = $nVariantid;
        } elseif (preg_match($sIns, $sProtein)) {
            // variant is an insertion
            $aCounts['ins'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_ins'][] = $nVariantid;
        } elseif (preg_match($sPredNoProtein, $sProtein)) {
            // a no protein variant (predicted)
            $aCountsPred['no protein'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_no protein'][] = $nVariantid;
        } elseif (preg_match($sNoProtein, $sProtein)) {
            // a no translation variant
            $aCounts['no protein'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_no protein'][] = $nVariantid;
        } elseif (preg_match($sPredTransInit, $sProtein)) {
            // a predicted translation initiation variant
            $aCountsPred['p.Met'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_p.Met'][] = $nVariantid;
        } elseif (preg_match($sTransInit, $sProtein)) {
            // a translation initiation variant
            $aCounts['p.Met'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_p.Met'][] = $nVariantid;
        } elseif (preg_match($sPredProtSub, $sProtein)) {
            // variant is an predicted substitution
            $aCountsPred['sub'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_sub'][] = $nVariantid;
        } elseif (preg_match($sProtSub, $sProtein, $aMatch)) {
            // variant is an substitution
            $aCounts['sub'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_sub'][] = $nVariantid;
        } elseif (preg_match($sProtComp, $sProtein)) {
            // a complex variant
            $aCounts['complex'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_complex'][] = $nVariantid;
        } elseif (preg_match($sSilent, $sProtein)) {
            // a silent variant
            $aCounts['='] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_='][] = $nVariantid;
        } else {
            //a complex variant
            $aCounts['complex'] += $nCount;
            $_SESSION['variant_statistics'][$_SESSION['currdb']]['Prot_complex'][] = $nVariantid;
        }
    }

    // After fetching and counting data, print it to the screen.
    // Print percentages in horizontal bars
    print('      <SPAN class="S15"><B>Protein variants</B></SPAN><BR>' . "\n" .
          '      <TABLE border="1">' . "\n" .
          '        <TR>' . "\n" .
          '          <TH>variant</TH>' . "\n" .
          '          <TH>number</TH>' . "\n" .
          '          <TH>percentages</TH></TR>' . "\n");

    $aAbsentVariants = array(); //2009-06-24; 2.0-19; keep track of non-observed variants
    $nSum = array_sum($aCounts) + array_sum($aCountsPred);
    foreach ($aCounts as $sVariant => $nVariants) {
        // 2009-02-12; 2.0-16 prevent division by zero
        $nPercentage = round($nVariants/($nSum + 0.0000001) * 100, 2);
        $nPercentagePred = round($aCountsPred[$sVariant]/($nSum + 0.0000001) *100, 2);
        $sPercentageTotal = ($nPercentage + $nPercentagePred) . '%';
        if ($nVariants != 0 || $aCountsPred[$sVariant] != 0) {
            //2009-06-24; 2.0-19; print observed variants only
            //2009-08-24; 2.0-21; print confirmed and predicted variants separately, except for the complex and unknown variants
            print('        <TR>' . "\n" .
                  '          <TD><A href="' . ROOT_PATH . 'variants.php?select_db=' . $sSymbol . '&action=view_all&view=Prot_' . $sVariant .'">' . $aVariants[$sVariant]['header'] . '</A></TD>' . "\n");
            if (!in_array($sVariant, array('unknown', 'complex'))) {
                print('          <TD align="right">' . ($nVariants?'confirmed: ' . $nVariants:'') . ($aCountsPred[$sVariant]?' predicted: ' . $aCountsPred[$sVariant]: '') . '</TD>' . "\n" .
                      '          <TD>' .
                      ($nPercentage ? '<IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="' . $nPercentage . '%" title="' . $nPercentage . '%" width="' . (round($nVariants/($nSum + 0.0000001) * $nBarWidth, 2)>1?round($nVariants/($nSum + 0.0000001) * $nBarWidth, 2):1) . '" height="15">' : '') .
                      ($nPercentagePred ? '<IMG src="' . ROOT_PATH . 'gfx/lovd_summ_red.png" alt="' . $nPercentagePred . '%" title="' . $nPercentagePred . '%" width="' . (round($aCountsPred[$sVariant]/($nSum + 0.0000001)*$nBarWidth, 2)>1?round($aCountsPred[$sVariant]/($nSum + 0.0000001)*$nBarWidth, 2):1) . '" height="15">' : '') .
                      '          </TD></TR>' . "\n");
            } else {
                print('          <TD align="right">' . $nVariants . '</TD>' . "\n" .
                      '          <TD>' .
                      ($nPercentage ? '<IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="' . $nPercentage . '%" title="' . $nPercentage . '%" width="' . (round($nVariants/($nSum + 0.0000001) * $nBarWidth, 2)>1?round($nVariants/($nSum + 0.0000001) * $nBarWidth, 2):1) . '" height="15">' : '') .
                      '          </TD></TR>' . "\n");
            }
                
        } else {
            // 2009-06-24; 2.0-19; store non-observed variants
            $aAbsentVariants[] = $aVariants[$sVariant]['header'];
        }
    }
    // Totals row
    print('        <TR>' . "\n" .
          '          <TD>total</TD>' . "\n" .
          '          <TD align="right">' . $nSum . '</TD>' . "\n" .
          '          <TD>' .
          '<IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="100%" title="100%" width="' . $nBarWidth . '" height="15">' .       '          </TD></TR></TABLE>' . "\n\n\n\n");

    // 2009-06-24; 2.0-19; print non-observed variants
    if (!empty($aAbsentVariants)) {
        print('Variants not observed: ' . implode($aAbsentVariants, ', ') . '<BR><BR>');
    }
    print('<BR>Legend: <IMG src="' . ROOT_PATH . 'gfx/lovd_summ_blue.png" alt="confirmed" title="confirmed" width="45" height="15"> confirmed <IMG src="' . ROOT_PATH . 'gfx/lovd_summ_red.png" alt="predicted" title="predicted" width="45" height="15"> predicted<BR>');
}


lovd_printGeneFooter();
require ROOT_PATH . 'inc-bot.php';
*////////////////// OLD LOVD 2.0 CODE THAT NEEDS TO BE IMPLEMENTED /////////////
?>
