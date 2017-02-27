<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-09-28 (based on Reading Frame Checker 1.9/2009-03-03)
 * Modified    : 2017-03-02
 * Version     : 1.2
 * For LOVD    : 3.0-19
 *
 * Access      : Public
 * Purpose     : Provide information on effect of whole-exon changes of a gene,
 *               based on the gene structure table, created by the Reference
 *               Sequence Parser.
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ir. Gerard C.P. Schaafsma <G.C.P.Schaafsma@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 *************/

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-form.php';

$_T->printHeader();
define('PAGE_TITLE', 'LOVD exonic deletions/duplications reading-frame checker');
$_T->printTitle();


function lovd_switchDB()
{
    global $_DB, $_T;

    $aArgs  = array();
    $sQ = 'SELECT CONCAT(g.id, "_", t.id_ncbi), CONCAT(g.id, " (", t.id_ncbi , " -> ", t.id_protein_ncbi, ")") FROM ' . TABLE_GENES . ' AS g INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid)';
    $sQ .= ' ORDER BY g.id, t.id_ncbi';
    $aTranscripts = $_DB->query($sQ, $aArgs)->fetchAllCombine();

    print('<FORM action="' . $_SERVER['SCRIPT_NAME'] . '" method="post">' . "\n");

    $aForm = array(
        array('POST', '', '', '', '35%', '14', '65%'),
        array('Select gene and transcript', '', 'select', 'symbol', 1, $aTranscripts, false, false, false),
        array('', '', 'submit', 'Continue'),
    );

    lovd_viewForm($aForm);
    print("\n\n" . '  </FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}

if (isset($_GET['explain'])) {
    // Here is explained everything about mutations difficult to predict.
    print('      &laquo; Return to <A href="' . $_SERVER['PHP_SELF'] . '">check another deletion/duplication</A><BR><BR>' . "\n\n");
?>

      The consequences of rearrangements involving the transcription initiation &amp; start site (first exon/promoter, cap site), translation initiation &amp; start site (exon with the ATG), the translation termination site (exon with the stop codon) and/or the transcription termination site (last exon/poly-A addition site) are difficult to predict. Below a short summary of what might happen.<BR>
      <BR>

      <H5>Effects on transcription (RNA-level);</H5>
      <B>Deletion</B><BR>
      <UL>
        <LI><I>first exon/promoter, cap site</I><BR>
          A deletion including the transcription initiation &amp; start site (first exon) most probably prevents any transcription, i.e. no RNA (r.0? > p.0?) is produced. Note that a gene might have alternative promoters which might become activated by the deletion.</LI>
        <LI><I>last exon/poly-A addition site</I><BR>
          A deletion including the transcription termination site (last exon/poly-A addition site) most probably produces an instable RNA (r.? > p.?). When the gene has several last exons/poly-A addition sites these might be activated. Alternatively transcription proceeds until a new last exon/poly-A addition site is encountered, with potentially one or more internal exons in between. These could be from a downstream gene in the same transcriptional orientation, producing a fusion transcript / protein (thereby interfering with the normal expression of this gene as well !).</LI>
        <LI><I>only containing internal non-coding exons (5' or 3' UTR)</I><BR>
          Deletions involving only internal non-coding exons (5' or 3' UTR) usually effect RNA stability (r.? > p.?).</LI>
      </UL>

      <B>Duplication</B><BR>
      NOTE - the descriptions below hold for duplications that are in tandem, they are not valid for other duplications (incl. transpositions)<BR>
      <UL>
        <LI><I>first exon/promoter, cap site</I><BR>
          A duplication including the transcription initiation &amp; start site (first exon) may produce a normal transcript (r.= > p.=, initiated at the duplicated first exon), a transcript containing the duplicated exons (from the second exon till the last duplicated exon) or a combination of both (r.[=, ?] > p.[=, ?]).</LI>
        <LI><I>last exon/poly-A addition site</I><BR>
          A duplication including transcription termination site (last exon/poly-A addition site) may produce a normal transcript (r.= > p.=), a transcript containing the duplicated exons from the one-but-last exon to the that of the first duplicated exon (r.? > p.?) or a combination of both (r.[=, ?] > p.[=, ?]).<BR>
        <LI><I>only containing internal non-coding exons (5' or 3' UTR)</I><BR>
          Duplications involving only internal non-coding exons (5' or 3' UTR) usually effect RNA stability (r.? > p.?).</LI>
      </UL>
      <BR>
      <BR>
      <BR>

      <H5>Effects on translation (protein-level);</H5>
      <B>Deletion</B><BR>
      <UL>
        <LI><I>translation initiation & start site (ATG)</I><BR>
          A deletion including the translation initiation &amp; start site (exon with the ATG) prevents normal translation, i.e. no protein (p.0?) is produced. Note however that the transcript might contain additional, mostly downstream, translation initiation &amp; start sites that may be activated. The result is then a N-terminally truncated protein.</LI>
        <LI><I>translation termination site (stop codon)</I><BR>
          A deletion including the translation termination site (exon with the stop codon) most probably produces an instable protein (p.?). It largely depends on the new transcription termination site what the ultimate outcome is. The normal C-terminus will be replaced by a new one of unpredictable length. When a fsion occurs with a downstream gene the new C-terminus might have a considerable length (fusion protein).</LI>
        <LI><I>only containing internal non-coding exons (5' or 3' UTR)</I></LI>
          Deletions involving only internal non-coding exons (5' or 3' UTR) usually effect RNA stability and therefore the amount of protein produced (p.?).</LI>
      </UL>

      <B>Duplication</B><BR>
      NOTE - the descriptions below hold for duplications that are in tandem, they are not valid for other duplications (incl. transpositions)<BR>
      <UL>
        <LI><I>translation initiation &amp; start site (ATG)</I><BR>
          A duplication including the translation initiation &amp; start site (exon with the ATG) may produce a normal protein (p.=, initiated in the duplicated exon), a protein with a new N-terminus fusing translation of the last exon before the duplication breakpoint to that of the first duplicated exon (p.?, giving either truncated or in frame translation) or a combination of both (p.[=, ?]).</LI>
        <LI><I>translation termination site (stop codon)</I><BR>
          A duplication including the translation termination site (exon with the stop codon) may produce a normal protein (p.=), a protein with a new C-terminus (fusing translation of the one-but-last exon to the that of the first duplicated exon, p.?) or a combination of both (p.[=, ?]).</LI>
        <LI><I>only containing internal non-coding exons (5' or 3' UTR)</I><BR>
          Duplications involving only internal non-coding exons (5' or 3' UTR) usually effect RNA stability and therefore the amount of protein produced (p.?).</LI>
      </UL>

<?php
    $_T->printFooter();
    exit;
}


if (isset($_REQUEST['symbol'])) {
    // Write selected transcript (from lovd_switchDB()) to session, as this
    // script requires multiple page loads.
    if (empty($_REQUEST['symbol'])) {
        $_SESSION['rf_checker_symbol'] = null;
    } else {
        $_SESSION['rf_checker_symbol'] = $_REQUEST['symbol'];
    }
}
$sSymbol = isset($_SESSION['rf_checker_symbol'])? $_SESSION['rf_checker_symbol'] : null;


// If no gene selected, present the selection list.
if (is_null($sSymbol)) {
    lovd_switchDB();
    exit;
}

// Now that we have a gene, check if we can make some sense of the gene structure table.
$nATGExon = 0;                   // Exonnumber of the exon with the start codon.
$nStopExon = 0;                  // Exonnumber of the exon with the stop codon.
$aStartLengthTable = array();    // Array for exon starts and lengths. (2011-04-04; 2.0-31; Not really correct name anymore)
$aReadingFrame = array(0 => -1); // Need this for proper calculation - can be removed later.
$aExonNames = array();           // Simple method to store possible custom exon names (FIXME; for efficiency, it should be merged with $aStartLengthTable and $aReadingFrame).
$sFilePath = ROOT_PATH . 'refseq/' . $sSymbol . '_table.txt';
if (is_readable($sFilePath)) {
    $sTable = file_get_contents($sFilePath);
    if (preg_match('/^exon #	c\.startExon	c\.endExon	g\.startExon	g\.endExon	lengthExon	lengthIntron(\n[^ \t<>]+(\t[0-9*-]+){5,6})+$/', $sTable)) {
        // Store data.
        $aTable = explode("\n", $sTable);
        foreach ($aTable as $nExon => $sLine) {
            if (!$nExon) { continue; } // Skip header.
            // $nExon should equal $aData[0], now.
            $aData = explode("\t", $sLine);
            if (trim($sLine)) {
                list($sName, $nStart, $nEnd, , , $nLength) = $aData;
                $aStartLengthTable[$nExon] = array($nStart, $nLength, $nEnd); // 2011-04-04; 2.0-31; Added $nEnd, isn't that much easier to use?
                $aExonNames[$nExon] = $sName;
                if ($nEnd < 0) {
                    // ATG still to come...
                    $aReadingFrame[$nExon] = -1;
                } else {
                    if (!$nATGExon) {
                        $nATGExon = $nExon;
                        if ($nStart < 0) {
                            $nStart = 0; // For easier calculation of "End - Start".
                        }
                    }
                    if (!$nStopExon && $nEnd{0} == '*') {
                        $nStopExon = $nExon;
                    }
                    $aReadingFrame[$nExon] = ($aReadingFrame[$nExon - 1] + $nLength)%3;
                }
            }
        }
        unset($aReadingFrame[0]); // We don't need it anymore.
        $nExons = count($aReadingFrame);
        if (!$nStopExon) {
            $nStopExon = $nExons;
        }

    } else {
        print('      Unfortunately, the gene structure table does not look like it should, so I can\'t interpret it.<BR>' . "\n\n");
        print(' (<A href="' . $_SERVER['PHP_SELF'] . '?symbol=">switch gene / transcript</A>)');
        $_T->printFooter();
        exit;
    }

} else {
    print('      There is no gene structure table available for <B>' . $sSymbol . '</B>. To get one, you\'ll have to have a GenBank file for your gene with genomic sequence, and run the <A href="scripts/refseq_parser.php">Reference Sequence Parser</A>.<BR><BR>' . "\n\n");
    lovd_switchDB();
}



// If sent, verify.
if (!empty($_GET['mutation'])) {
    // Aan fouten doen we niet!
    if (!isset($_GET['exon_from'])) {
        $_GET['exon_from'] = 1; // Exon 1, even if the translation does not start there.
    }
    if (!isset($_GET['exon_to'])) {
        $_GET['exon_to'] = $nExons;
    }

    if ($_GET['exon_from'] > $_GET['exon_to']) {
        $s = $_GET['exon_from'];
        $_GET['exon_from'] = $_GET['exon_to'];
        $_GET['exon_to'] = $s;
    }
}

lovd_showInfoTable('<I>The predictions are based on direct translation of the mRNA, which is generated by deletion / insertion (duplication) of the exons as selected by the user. Please note that for data derived from analysis of DNA the result is just a prediction based on these data only; without confirmation on RNA level (experimental evidence), this prediction does not provide certainty and cannot be used as evidence for the effect which the change detected will have on RNA level. Literature reports several exceptions where changes at DNA level do not match exactly with changes on RNA level. For example, on RNA-level more exons might be missing because signals required for correct splicing are disrupted or deleted. In addition, intronic sequences flanked by inefficient splicing signals (so called \'cryptic\' splice sites) might be activated yielding newly recognized exons incorporated in the mRNA.</I>', 'information');

print('<P>Currently viewing gene/transcript: <B>' . $sSymbol . '</B> ('.
    '<A href="' . $_SERVER['PHP_SELF'] . '?symbol=">switch</A>)</P>' .
        '      <BR>' . "\n\n" .
      '      <FORM action="' . $_SERVER['PHP_SELF'] . '" method="get">' . "\n" .
      '        <TABLE border="0" cellpadding="1" cellspacing="0" width="600">');

$aForm = array(
                array('GET', '', '', '', '50%', '14', '50%'),
                'hr',
                array('Deletion or Duplication', '', 'select', 'mutation', 1, array('del' => 'Deletion', 'dup' => 'Duplication'), false, false, false),
                'hr',
                array('From exon', '', 'select', 'exon_from', 1, $aExonNames, false, false, false),
                'hr',
                array('To exon', '', 'select', 'exon_to', 1, $aExonNames, false, false, false),
                'hr',
                array('', '', 'submit', 'Check'),
                'hr',
              );
lovd_viewForm($aForm);
print('</TABLE>' . "\n" .
      '      </FORM><BR><BR>' . "\n\n");



// If sent, verify.
if (!empty($_GET['mutation']) && !empty($_GET['exon_from']) && !empty($_GET['exon_to']) && is_numeric($_GET['exon_from']) && is_numeric($_GET['exon_to'])) {
    print(($_GET['mutation'] == 'del'? 'Deleting' : 'Duplicating') . ' exon ' . ($_GET['exon_from'] == $_GET['exon_to']? $aExonNames[$_GET['exon_from']] : $aExonNames[$_GET['exon_from']] . ' to exon ' . $aExonNames[$_GET['exon_to']]) . ' leads to ... ');

    $sResult = '';

    // Deleted a-b : if (a-1) == b -> OK
    // Duplic. a-b : if (a-1) == b -> OK
    if ($_GET['exon_from'] > $nATGExon && $_GET['exon_to'] < $nStopExon) {
        if ($aReadingFrame[$_GET['exon_from']-1] == $aReadingFrame[$_GET['exon_to']]) {
            $sResult = 'an IN-FRAME ' . ($_GET['mutation'] == 'del'? 'deletion' : 'duplication') . '.';
        } else {
            $sResult = 'an OUT-OF-FRAME ' . ($_GET['mutation'] == 'del'? 'deletion' : 'duplication') . '.';
        }
    
    } else {
        // Something else.
        $sResult = 'an effect that is difficult to predict. See <A href="' . $_SERVER['PHP_SELF'] . '?explain">more information about these kind of mutations</A>.';
    }

    print($sResult . '<BR><BR>' . "\n\n\n");





    // 2009-11-16; 2.0-23; Added HGVS mutation name generator.
    // Change ex01ex02del -> something more useful...
    if ($_GET['exon_from'] <= count($aStartLengthTable) && $_GET['exon_to'] <= count($aStartLengthTable)) {
        $aStartLengthTable = array($aStartLengthTable[intval($_GET['exon_from'])], $aStartLengthTable[intval($_GET['exon_to'])]);
        // 2011-04-04; 2.0-31; Replaced this part by simply using $nEnd, and not $nStart + $nLength. Why wasn't this done like this before? Makes me think I'm missing something...
        // Using $nEnd and not $nStart + $nLength makes LOVD apply to the generated HGVS nomenclature a bit more (c.310-?_*544+?del).
//        $sVariantHGVS = 'c.' . $aStartLengthTable[0][0] . '-?_' . (($aStartLengthTable[1][0] + $aStartLengthTable[1][1]) - ($aStartLengthTable[1][0] < 0 && ($aStartLengthTable[1][0] + $aStartLengthTable[1][1]) >= 1? 0 : 1)) . '+?' . $_GET['mutation'];
        $sVariantHGVS = 'c.' . $aStartLengthTable[0][0] . '-?_' . $aStartLengthTable[1][2] . '+?' . $_GET['mutation'];
        $sVariant = 'ex' . str_pad($aExonNames[$_GET['exon_from']], 2, '0', STR_PAD_LEFT) . ($_GET['exon_from'] == $_GET['exon_to']? '' : 'ex' . str_pad($aExonNames[$_GET['exon_to']], 2, '0', STR_PAD_LEFT)) . $_GET['mutation'];
        print('According to the ' . $sSymbol . ' reference sequence in the LOVD database, the HGVS notation of this ' . ($_GET['mutation'] == 'del'? 'deletion' : 'duplication') . ' is:<BR>' . "\n");
        print($sVariant . '&nbsp;-&gt;&nbsp;' . $sVariantHGVS . '<BR>' . "\n");
    }
}

$_T->printFooter();
?>
