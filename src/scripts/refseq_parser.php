<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-06-29
 * Modified    : 2012-07-03
 * For LOVD    : 3.0-beta-07
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ir. Gerard C.P. Schaafsma <G.C.P.Schaafsma@LUMC.nl>
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
require ROOT_PATH . 'inc-lib-form.php';
$_T->printHeader(false); // We'll use the "clean" template.

// Require curator clearance (curator of any gene).
lovd_isAuthorized('gene', $_AUTH['curates']);
lovd_requireAUTH(LEVEL_CURATOR);

define('LENGTH_LINE', 60);
$sLinemark     = str_repeat('         .', (LENGTH_LINE / 10));
$sLinemarkBack = str_repeat('.         ', (LENGTH_LINE / 10));

if (!isset($_GET['step'])) {
    $_GET['step'] = '';
}

function lovd_fileCopiesExist($sFileName) {
    //renames existing files up to three copies
    $nDotPos = strpos($sFileName, '.', 3);//start counting at the third position because ROOT_PATH can be included
    if (file_exists(substr_replace($sFileName, '.0' . substr($sFileName, $nDotPos), $nDotPos))) {
        if (file_exists(substr_replace($sFileName, '.1' . substr($sFileName, $nDotPos), $nDotPos))) {
            if (file_exists(substr_replace($sFileName, '.2' . substr($sFileName, $nDotPos), $nDotPos))) {
                unlink(substr_replace($sFileName, '.2' . substr($sFileName, $nDotPos), $nDotPos));
            }
            rename(substr_replace($sFileName, '.1' . substr($sFileName, $nDotPos), $nDotPos), substr_replace($sFileName, '.2' . substr($sFileName, $nDotPos), $nDotPos));
        }
        rename((substr_replace($sFileName, '.0' . substr($sFileName, $nDotPos), $nDotPos)), (substr_replace($sFileName, '.1' . substr($sFileName, $nDotPos), $nDotPos)));
    }
    rename($sFileName, substr_replace($sFileName, '.0' . substr($sFileName, $nDotPos), $nDotPos));
    return $sFileName;
}

// Check presence or writability of the refseq directory
if (!is_dir(ROOT_PATH . 'refseq') || !is_writable(ROOT_PATH . 'refseq')) {
    $_T->printTitle('LOVD Reference Sequence Parser');
    lovd_showInfoTable('The \'refseq\' directory does not exist or is not writable.<BR>Please make sure it exists and that it is (world) writable, otherwise you can\'t use the Reference Sequence Parser.<!-- For more information or troubleshooting, please refer to the <A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">LOVD manual</A>.-->', 'stop');
    $_T->printFooter();
    exit;
}





if ($_GET['step'] == 1) {
    // Step 1: Importing a GenBank file.
    if (POST) {
        // Error check
        lovd_errorClean();

        // Mandatory fields with their names.
        if (empty($_POST['symbol'])) {
            lovd_errorAdd('symbol', 'Please fill in the \'Select gene and transcript\' field.');

        } else {
            // Get the UD number from the genes table.
            $_POST['transcript_id'] = $_POST['symbol'];
            list($_POST['symbol'], $_POST['file'], $_POST['protein_id']) = $_DB->query('SELECT g.id, g.refseq_UD, t.id_protein_ncbi FROM ' . TABLE_GENES . ' AS g INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) WHERE t.id_ncbi = ?', array($_POST['symbol']))->fetchRow();

            if (empty($_POST['file']) || empty($_POST['transcript_id']) || empty($_POST['protein_id'])) {
                lovd_errorAdd('symbol', 'This gene or transcript does not seem to be configured correctly, we currently can\'t generate a human-readable reference sequence file using this gene.');
            }

            // Check on avoidance of selection lists.
            if (!empty($_POST['symbol']) && !lovd_isAuthorized('gene', $_POST['symbol'])) {
                lovd_errorAdd('symbol', 'You do not have rights to run this script for this gene!');
            }
        }



        if (!lovd_error()) {
            // All fields filled in, go ahead.
            // Read file into an array.
            // FIXME!!! LOVD's lovd_php_file() can't communicate in HTTPS, but Mutalyzer forces it...
            $aGenBank = file('https://mutalyzer.nl/Reference/' . $_POST['file'] . '.gb');

            if (!$aGenBank) {
                lovd_errorAdd('symbol', 'We couldn\'t retreive the reference sequence file for this gene. Please try again later.');

            } else {
                // Select mRNA and CDS field based on transcript and protein id's.
                // First you want to find the mRNA and CDS field corresponding to the provided transcript_id and protein_id.
                $aTranscriptID = array();
                $nTranscriptID = 0;
                $nProteinID = 0;
                $bGene = false;
                $bGeneFound = false;
                $bmRNA = false;
                $bCDS = false;
                $nRNAField = 0;
                $nCDSField = 0;

                // Go through the array until you found the mRNA and CDS field numbers
                foreach ($aGenBank as $line) {
                    if (substr($line, 0, 5) == 'LOCUS') {
                        if (!strpos($line, 'DNA')) {
                            lovd_errorAdd('symbol', 'Couldn\'t parse this reference sequence.');
                        }
                    }
                    // Determine the accession number, including version.
                    if (substr($line, 0, 7) == 'VERSION') {
                        $_POST['version_id'] = preg_replace('/^VERSION\s+(N[CG]_[0-9]+\.[0-9]+)\s+.*$\n/', "$1", $line);
                    }
                    if ('/gene="' . $_POST['symbol'] . '"' == preg_replace('/\s+/', '', $line)) {
                        // We are in the right gene.
                        $bGene = true;
                    }
                    if ((substr($line, 5, 4) == 'mRNA') && $bGene) {
                        // we are now in the mRNA part where the coordinates of the exons are provided.
                        $bmRNA = true;
                        $nRNAField++;
                    }
                    if ('/transcript_id="' . $_POST['transcript_id'] . '"' == preg_replace('/\s+/', '', $line) && $bmRNA) {
                        // We are in the right mRNA field.
                        $nTranscriptID = $nRNAField;
                    } elseif ('/transcript_id=' == substr(preg_replace('/\s+/', '', $line), 0, strpos(preg_replace('/\s+/', '', $line), '=')+1) && $bmRNA){
                        // Take the mRNA accession number from the file.
                        $aTranscriptID[] = substr(preg_replace('/[\s"]+/', '', $line), strpos(preg_replace('/\s+/', '', $line), '=')+1);
                    }
                    if ((substr($line, 5, 3) == 'CDS') && $bGene) {
                        // We are now in the CDS part where the coordinates of the coding sequence are provided.
                        $bCDS = true;
                        $nCDSField++;
                    }
                    if ('/protein_id="' . $_POST['protein_id'] . '"' == preg_replace('/\s+/', '', $line) && $bCDS) {
                        // We are in the right mRNA field.
                        $nProteinID = $nCDSField;
                    }
                    if ($nTranscriptID && $nProteinID) {
                        // When the mRNA and CDS field numbers are found we can stop.
                        $bGene = false;
                        $bmRNA = false;
                        $bCDS = false;
                        break;
                    }
                }

                if (!$nTranscriptID) {
                    lovd_errorAdd('symbol', 'Transcript ID was not found in reference sequence...');
                }
                if (!$nProteinID) {
                    lovd_errorAdd('symbol', 'Protein ID was not found in reference sequence...');
                }



                // FIXME; This should not be run when we're having errors of course...
                // It also looks very inefficient to have two such similar blocks right after each other.
                // Now you know the mRNA and CDS field you want, go through the array again.
                $sSourcePositions = '';
                $sExonPositions = '';
                $sCDSPositions = '';
                $bGene = false;
                $bGeneFound = false;
                $bmRNA = false;
                $bCDS = false;
                $nRNAField = 0;
                $nCDSField = 0;
                $sProteinID = '';

                // Find the mRNA coordinates.
                foreach ($aGenBank as $nCounter => $line) {
                    // Apparently, Windows line endings gave problems during parsing.
                    $line = rtrim($line);

                    // Prevent problems with absence of up- and downstream.
                    if (substr($line, 5, 6) == 'source') {
                        $sSourcePositions .= $line;
                    }
                    if ('/gene="' . $_POST['symbol'] . '"' == preg_replace('/\s+/', '', $line)) {
                        // We are in the right gene.
                        $bGene = true;
                        $bGeneFound = true;
                    }
                    if ((substr($line, 5, 4) == 'mRNA') && $bGene) {
                        // We are now in the mRNA part where the coordinates of the exons are provided.
                        $bmRNA = true;
                        $nRNAField++;
                    }
                    if ((substr($line, 21, 5) != '/gene') && $bGene && $bmRNA && ($nRNAField == $nTranscriptID)) {
                        // Now we are in the right mRNA field.
                        if (preg_match('/(\s*mRNA\s*join\()?\d+\.\.\d+(,\s*\d+\.\.\d+)*(,|\))?$/', $line)) {
                            $sExonPositions .= $line;
                        } else {
                            lovd_errorAdd('symbol', 'Error : An unexpected character was found in line: ' . ($nCounter+1) . ' of your GenBank file.');
                        }
                    }
                    if ((substr($line, 21, 5) == '/gene') && $bGene && $bmRNA) {
                        // We reached the end of the mRNA coordinates.
                        $bmRNA = false;
                    }
                    if ((substr($line, 5, 3) == 'CDS') && $bGene) {
                        // We are now in the CDS part where the coordinates of the coding sequence are provided.
                        $bCDS = true;
                        $nCDSField++;
                    }
                    if ((substr($line, 21, 5) != '/gene') && $bGene && $bCDS && ($nCDSField == $nProteinID)) {
                        // Now we are in the right CDS field.
                        if (preg_match('/(\s*CDS\s*join\()?\d+\.\.\d+(,\s*\d+\.\.\d+)*(,|\))?$/', $line)) {
                            $sCDSPositions .= $line;
                        } else {
                            lovd_errorAdd('file', 'Error : An unexpected character was found in line: ' . ($nCounter+1) . ' of your GenBank file<BR>');
                        }
                    }
                    if ((substr($line, 21, 5) == '/gene') && $bGene && $bCDS) {
                        // We reached the end of the CDS coordinates.
                        $bCDS = false;
                    }
                    if (substr($line, 0, 6) == 'ORIGIN') {
                        // From here the sequence is provided.
                        $nSeqOffset = $nCounter + 1;
                    }
                }

                if (!$bGeneFound) {
                    lovd_errorAdd('file', 'The gene ' . $_POST['symbol'] . ' was not found in your GenBank file');
                }
            }



            if (!lovd_error()) {
                // FIXME; this is old code, not in LOVD 3.0 style at all.
                $sOut  = '';

                //2.0-13; 2008-10-28; Fixed bug absence of up- and downstream
                // Put the source start and end position in an array
                // Get rid of source and the brackets
                $sSourcePositions = preg_replace('/[source()]/', '', $sSourcePositions);
                // Get rid of any form of whitespace
                $sSourcePositions = preg_replace('/\s+/', '', $sSourcePositions);
                // write the start and end positions to an array
                $aSourcePositions = explode('..', $sSourcePositions);

                // Extract the ORIGIN part of the GenBank file to an array
                $aSequence = array_slice($aGenBank, $nSeqOffset, (count($aGenBank) - 2 - $nSeqOffset));
                // write the sequence to a string
                $sSequence = implode($aSequence, '');
                // Get rid of any form of whitespace
                $sSequence = preg_replace('/\s+/', '', $sSequence);
                // Get rid of numbers
                $sSequence = preg_replace('/\d+/', '', $sSequence);

                // Put the exon start and end positions in an array
                // Get rid of mRNA join and the brackets
                $sExonPositions = preg_replace('/[mRNA join()]/', '', $sExonPositions);
                // Get rid of any form of whitespace
                $sExonPositions = preg_replace('/\s+/', '', $sExonPositions);
                // write the exon start and end positions to an array
                $aExonPositionsmRNA = explode(',', $sExonPositions);
                // write the start and end positions to arrays
                for ($i = 0; $i < count($aExonPositionsmRNA); $i++) {
                    $aExonPositionsmRNA[$i] = explode('..', $aExonPositionsmRNA[$i]);
                }
                //2.0-13; 2008-10-28; Fixed bug absence of up- and downstream
                if ($aSourcePositions[0] == $aExonPositionsmRNA[0][0]) {
                    print('No upstream sequence was provided<BR>');
                }
                if ($aSourcePositions[1] == $aExonPositionsmRNA[count($aExonPositionsmRNA) - 1][1]) {
                    print('No downstream sequence was provided<BR>');
                }

                // add an element to the exon positions array, now the indexes are the same as the exon numbers
                $aExonPositionsmRNA[] = array(0, 0);
                sort($aExonPositionsmRNA);

                // Put the exon start and end positions of the coding sequence in an array
                // Get rid of CDS join and the brackets
                $sCDSPositions = preg_replace('/[CDS join()]/', '', $sCDSPositions);
                // Get rid of any form of whitespace
                $sCDSPositions = preg_replace('/\s+/', '', $sCDSPositions);
                // write the exon start and end positions to an array
                $aExonPositionsCDS = explode(',', $sCDSPositions);
                // write the start and end positions to arrays
                for ($i = 0; $i < count($aExonPositionsCDS); $i++) {
                    $aExonPositionsCDS[$i] = explode('..', $aExonPositionsCDS[$i]);
                }
                // add an element to the exon positions array, now the indexes are the same as the exon numbers
                $aExonPositionsCDS[] = array(0, 0);
                sort($aExonPositionsCDS);

                // find the translation start
                $nStartTransl = $aExonPositionsCDS[1][0] - 1; // Minus 1 because you want the "|" before the start of translation
                // create the introns positions array
                $aIntronsPositions = array(0 => array(0, 0));
                for ($i = 1; $i < count($aExonPositionsmRNA) - 1; $i++) {
                    $aIntronsPositions[] = array($aExonPositionsmRNA[$i][1] + 1, $aExonPositionsmRNA[$i + 1][0] - 1);
                }

                // Create the sequence for step 2
                $nExons = count($aExonPositionsmRNA) - 1;// number of exons
                // add upstream sequence to $sSeqNextStep, wich will be the sequence for step 2
                $sSeqNextStep = substr($sSequence, 0, $aExonPositionsmRNA[1][0] - 1);
                // now for the exons and introns
                for ($i = 1; $i <= $nExons; $i++) {
                    $sSeqNextStep .= "<";
                    // add exon
                    // 2009-03-30; 2.0-17; Added +1, because translation can start at exon start
                    if ($aExonPositionsmRNA[$i][0] <= ($nStartTransl + 1) && $nStartTransl < $aExonPositionsmRNA[$i][1]) {
                        // if start of translation is in this exon, add a |
                        $sExonWithStartTransl = substr($sSequence, $aExonPositionsmRNA[$i][0] - 1, $aExonPositionsmRNA[$i][1] - $aExonPositionsmRNA[$i][0] + 1);
                        $sExonWithStartTransl = substr_replace($sExonWithStartTransl, "|", $nStartTransl - $aExonPositionsmRNA[$i][0] + 1, 0);
                        $sSeqNextStep .= $sExonWithStartTransl;
                    } else {
                        $sSeqNextStep .= substr($sSequence, $aExonPositionsmRNA[$i][0] - 1, $aExonPositionsmRNA[$i][1] - $aExonPositionsmRNA[$i][0] + 1);
                    }
                    $sSeqNextStep .= ">";
                    if ($i < $nExons) {
                        // add intron
                        $sSeqNextStep .= substr($sSequence, $aIntronsPositions[$i][0] - 1, $aIntronsPositions[$i][1] - $aIntronsPositions[$i][0] + 1);
                    }
                }

                // add downstream sequence
                $sSeqNextStep .= substr($sSequence, $aExonPositionsmRNA[$nExons][1], strlen($sSequence) - $aExonPositionsmRNA[$nExons][1] + 2);
                $sOut .= ($sOut? "\n" : '') . 'Successfully created input for step 2';
            }
        }

        if (!lovd_error()) {
            // Create sequence for step 2
            $_POST['sequence'] = wordwrap($sSeqNextStep, LENGTH_LINE, "\n", 1);
            if (!isset($_POST['exists'])) {
                $_POST['exists'] = '';
            }

            // 2009-12-03; 2.0-23; you need this one when there is no VERSION line in the GenBank file
            if (!isset($_POST['version_id'])) {
                $_POST['version_id'] = $_SETT['currdb']['refseq_genomic'];
            }

            $_T->printTitle('Step 1 - Import annotated Genbank sequence');
            print('Output for this step :<BR>' . "\n" . str_replace("\n", '<BR>' . "\n", $sOut) . '<BR><BR>' . "\n");

            // To continue to step 2, we need to create a form and send all data.
            print('<FORM action="' . $_SERVER['SCRIPT_NAME'] . '?step=2" method="post">' . "\n" .
                '  <INPUT type="hidden" name="symbol" value="' . $_POST['symbol'] . '">' . "\n" .
                '  <INPUT type="hidden" name="sequence" value="' . $_POST['sequence'] . '">' . "\n" .
                '  <INPUT type="hidden" name="exists" value="' . $_POST['exists'] . '">' . "\n" .
                '  <INPUT type="hidden" name="version_id" value="' . $_POST['version_id'] . '">' . "\n" . // 2009-12-03; 2.0-23; Add GenBank ID.
                '  <INPUT type="hidden" name="transcript_id" value="' . $_POST['transcript_id'] . '">' . "\n" . // 2009-03-09; 2.0-17 by Gerard: to fill in the textboxes if you come from step 1
                '  <INPUT type="hidden" name="protein_id" value="' . $_POST['protein_id'] . '">' . "\n" . // 2009-03-09; 2.0-17 by Gerard: to fill in the textboxes if you come from step 1
                '  <INPUT type="hidden" name="step1" value="true">' . "\n" . // 2009-06-22; 2.0-19; by Gerard: need to know if you came from step 1
                '  <INPUT type="submit" value="Continue to next step &raquo;">' . "\n" .
                '</FORM><BR>' . "\n\n");

            $_T->printFooter();
            exit;

        } else {
            // Because in the current setup, $_POST['symbol'] is changed into something else, we need to change it back when there are errors.
            $_POST['symbol'] = $_POST['transcript_id'];
        }

    } else {
        // Standard settings.
        $_POST['exists'] = 'overwrite';

        // Do we have a gene selected?
        if ($_SESSION['currdb'] || !empty($_GET['symbol'])) {
            // Select first transcript added to this gene.
            $sNCBI = $_DB->query('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? ORDER BY id ASC LIMIT 1', array((!empty($_GET['symbol'])? $_GET['symbol'] : $_SESSION['currdb'])))->fetchColumn();
            // FIXME; Replace "symbol" with something more useful.
            $_POST['symbol'] = $sNCBI;
        }
    }



    // Show a list of available GenBank files.
    $aGenes = array();
    $aArgs  = array();
    $sQ = 'SELECT t.id_ncbi, CONCAT(g.id, " (", t.id_ncbi , " -> ", t.id_protein_ncbi, ")") FROM ' . TABLE_GENES . ' AS g INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid)';
    if ($_AUTH['level'] == LEVEL_CURATOR) {
        $sQ .= ' INNER JOIN ' . TABLE_CURATES . ' AS g2u ON (g.id = g2u.geneid AND g2u.userid = ?)';
        $aArgs[] = $_AUTH['id'];
    }
    $sQ .= ' ORDER BY g.id';
	$aGenes = $_DB->query($sQ, $aArgs)->fetchAllCombine();

    // Print the form for step 1: import a GenBank file
    $_T->printTitle('Step 1 - Import annotated Genbank sequence to extract genomic sequence');
    print('    <BR>' . "\n\n");

    lovd_errorPrint();

    print('<FORM action="' . $_SERVER['SCRIPT_NAME'] . '?step=1&amp;sent=true" method="post">' . "\n");

    $aForm = array(
        array('POST', '', '', '', '35%', '14', '65%'),
        array('Select gene and transcript', '', 'select', 'symbol', 1, $aGenes, false, false, false),
        array('', '', 'submit', 'Continue'),
    );

    lovd_viewForm($aForm);
    print("\n\n" . '  </FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}





//STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-STEP2-
if ($_GET['step'] == 2) {
    // Get sequence from 1 and parse intronic sequences. Prepare sequence for step 3.

    // We need to know if you came from step 1.
    if (isset($_POST['step1'])) {
        $bStep1 = $_POST['step1'];
    } elseif (!isset($_GET['sent'])) {
        $bStep1 = false;

        // Actually, not supported anymore.
        lovd_showInfoTable('Sorry, it is not supported to start at step 2.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (isset($_GET['sent'])) {
        // Verification of the sequence

        // Error check
        lovd_errorClean();

        // Mandatory fields with their names.
        $aCheck = array(
            'symbol' => 'Gene symbol',
            'sequence' => 'Input sequence'
        );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // Check on avoidance of selection lists.
        if (!empty($_POST['symbol']) && !lovd_isAuthorized('gene', $_POST['symbol'])) {
            lovd_errorAdd('symbol', 'You do not have rights to run this script for this gene!');
        }



        if (!lovd_error()) {
            // All fields filled in, go ahead.
            $sSeq = str_replace("\r", '', $_POST['sequence']);
            $sSeq = str_replace("\n", '', $sSeq);

            $sUpstream = '';       // The upstream sequence
            $aIntron = array();    // The introns array
            $aExon = array();      // The exons array
            $nExonNumber = 0;      // Exon number
            $nExonNucleotides = 0; // Number of exon nucleotides
            $nStartTranslation = 0;// Where the translation starts
            $where = 'intron';        // Start with the upstream sequence
            $aExonEnds = array();     // Array with exon ending positions.

            $_POST['gene'] = $_DB->query('SELECT name FROM ' . TABLE_GENES . ' WHERE id = ?', array($_POST['symbol']))->fetchColumn();

            for ($i = 0; $i < strlen($sSeq); $i ++) {
                $s = $sSeq{$i};
                // We will need to loop through the sequence to provided detailed error messages.
                // up and downstream are first considered introns (first and last elements of the
                // intron array $aIntron)
                switch ($where) {
                    case 'intron' :
                        // We are in an intron.
                        if (preg_match('/[ACGT]/i', $s)) {
                            // We stay in the intron
                            if (empty($aIntron[$nExonNumber])) {
                                $aIntron[$nExonNumber] = '';
                            }
                            $aIntron[$nExonNumber] .= $s;

                        } elseif ($s == '<') {
                            // We are moving into an exon.
                            $where = 'exon';
                            $nExonNumber ++;

                        } else {
                            lovd_errorAdd('file', 'Error : Unexpected character \'' . $s . '\' at char ' . ($i + 1));
                            break 2;
                        }
                        break;

                    case 'exon';
                        // We are in an exon.
                        if (preg_match('/[ACGT]/i', $s)) {
                            // We stay in the exon
                            if (empty($aExon[$nExonNumber])) {
                                $aExon[$nExonNumber] = '';
                            }
                            $aExon[$nExonNumber] .= $s;
                            $nExonNucleotides ++;

                        } elseif ($s == '>') {
                            // We are moving into an intron.
                            $where = 'intron';
                            $aExonEnds[$nExonNumber] = $nExonNucleotides;

                        } elseif ($s == '|' && !$nStartTranslation) {
                            // We are starting translation.
                            if (empty($aExon[$nExonNumber])) {
                                $aExon[$nExonNumber] = '';
                            }
                            $aExon[$nExonNumber] .= $s; // The | is included!!
                            $nStartTranslation = $nExonNucleotides + 1;// Need this one later

                        } else {
                            lovd_errorAdd('file', 'Error : Unexpected character \'' . $s . '\' at char ' . ($i + 1));
                            break 2;
                        }
                        break;
                }
            }
        }

        if (!lovd_error()) {
            // Fix $aExonEnds (last exon not completely translated)
            // and compensate for $nStartTranslation where nucleotide numbering starts.
            foreach ($aExonEnds as $key => $nEnd) {
                $nEnd -= $nStartTranslation;
                $aExonEnds[$key] = ($nStartTranslation < 0? $nEnd : $nEnd + 1);
            }

            // 2.0-13; 2008-10-30 by Gerard fix bug when no up/downstream sequences are provided
            $sCodingSequence = implode($aExon);// put the whole exon array in a string
            $nEnd = 0;
            for ($i = $nStartTranslation; $i < $nExonNucleotides; $i += 3) {//$nExonNucleotides should be the same as strlen($sCodingSequence) check this
                if (in_array(strtolower(substr($sCodingSequence, $i, 3)), array('taa', 'tag', 'tga'))) {
                    // stop codon!
                    $nEnd = $i + 3;
                    break;
                }
            }

            // All sequences have been parsed and stored. Now create the intron and if there the upstream and downstream files.
            $sNow = date('F j, Y');
            $sNowHead = date('Y-m-d H:i:s'); // Produces a strict warning
            $sPath = ROOT_PATH . 'refseq/';
            $sOut = '';

            /*******************************************************************
             * We will now traverse the intronic sequence array and create the *
             * intron files. I have traded speed for using less memory by not  *
             * using foreach(), which is faster but creates a copy of the      *
             * array. This array can get huge, so I'm not willing to copy the  *
             * array and risk a failure.                                       *
             ******************************************************************/

            reset($aIntron);
            // 2008-10-30; 2.0-13; by Gerard
            $bFilesExisted = false;
            // 2009-02-25; 2.0-16; need this one for the genomic numbering (by Gerard)
            // 2009-03-25; 2.0-17; adapted by Gerard to avoid notices
            $nGenomicNumberIntron = (array_key_exists(0, $aIntron) ? strlen($aIntron[0]) : 0);

            while (list($nIntron, $sIntron) = each($aIntron)) {
                if (!$sIntron) {
                    // No intronic sequence. Wouldn't know why, but whatever.
                    continue;
                }

                // Determine the file names
                $sNIntron = str_pad($nIntron, 2, '0', STR_PAD_LEFT);//add a 0 when intron number is 1 digit
                if (!$nIntron) {
                    // First intron is upstream sequence
                    $sFileName = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_upstream.html';
                    $sTitle = 'upstream';
                    $where = 'up';
                } elseif ($nIntron == $nExonNumber) {
                    // Last intron is downstream sequence
                    $sFileName = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_downstream.html';
                    $sTitle = 'downstream';
                    $where = 'down';
                } else {
                    // The real introns
                    $sFileName = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_intron_' . $sNIntron . '.html';
                    $sTitle = strlen($sIntron) . ' nt intron ' . $sNIntron;
                    $where = 'intron';
                }

                if (file_exists($sFileName)) {
                    switch ($_POST['exists']) {
                        case 'skip' :
                            // Skip this intron, we already have a file.
                            $sOut .= ($sOut? "\n" : '') . 'Skipped ' . $sTitle . ', file existed';
                            $bFilesExisted = true;
                            continue 2;
                        case 'rename' :
                            // Rename the old file, we create a new intron refseq.
                            $sFileName = lovd_fileCopiesExist($sFileName);
                            break;
                    }
                }


                // Write to file.
                $fIntron = @fopen($sFileName, 'w');
                // 2009-12-28; 2.0-24; remove file if it cannot be opened for writing
                if (!$fIntron) {
                    unlink($sFileName);
                    $fIntron = fopen($sFileName, 'w');
                }
                if ($fIntron) {
                    fputs($fIntron, '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"' . "\n" .
                        '        "http://www.w3.org/TR/html4/loose.dtd">' . "\n" .
                        '<HTML lang="en">' . "\n" .
                        '<HEAD>' . "\n" .
                        '  <TITLE>' . $_POST['gene'] . ' (' . $_POST['symbol'] . ') - ' . $sTitle . ' reference sequence</TITLE>' . "\n" .
                        '  <META http-equiv="content-type" content="text/html; charset=UTF-8">' . "\n" .
                        '  <META name="generator" content="LOVD v.' . $_SETT['system']['version'] . ' Reference Sequence Parser @ ' . $sNowHead . '">' . "\n" .
                        '  <META name="LOVD copyright" content="&copy; 2004-' . date('Y') . ' LUMC: http://www.LUMC.nl/">' . "\n\n" .
                        '  <STYLE type="text/css">' . "\n" .
                        '    body {font-family : Verdana, Helvetica, sans-serif; font-size : 13px;}' . "\n" .
                        '    pre  {font-family : monospace;}' . "\n" .
                        '  </STYLE>' . "\n" .
                        '</HEAD>' . "\n\n" .
                        '<BODY>' . "\n\n" .
                        '<HR>' . "\n" .
                        '<H1 align="center">' . $_POST['gene'] . ' (' . $_POST['symbol']  . ') - ' . $sTitle . ' reference sequence</H1>' . "\n" .
                        ($where == 'intron'? '<P align="center"><I>(intronic numbering for coding DNA Reference Sequence)</I></P>' . "\n" : '') .
                        '<HR>' . "\n\n" .
                        '<PRE>' . "\n");

//UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM
//UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM-UPSTREAM
                    if ($where == 'up') {
                        // Upstream sequence. No separation in the middle, present the sequence as before the start of translation point.
                        // Prepare sequence and declare some vars (part I).
                        $sUpstream = strtolower($aIntron[0]);
                        $lUpstream = strlen($sUpstream); // length of the upstream sequence

                        // 2009-02-27; 2.0-16 added by Gerard
                        $aLengthsSequenceParts['upstream'] = $lUpstream;

                        // determine the number of exon nucleotides you want to add to the upstream sequence
                        $nExonNuclsToAdd = ($nStartTranslation - 1) % LENGTH_LINE;
                        // determine the number of upstream nucleotides in the first line: leftover
                        $nLeftover = ($lUpstream + $nExonNuclsToAdd) % LENGTH_LINE;

                        // 2009-02-27; 2.0-16; need this one for the g. numbering
                        $nGenomicNumberUp = $nLeftover;
                        // Get the exon nucleotides from the exons array;

                        $nExonNuclsStillToAdd = $nExonNuclsToAdd;
                        $n = 1;
                        while (strlen($aExon[$n]) < $nExonNuclsStillToAdd) {
                            $sUpstream .= $aExon[$n];
                            $nExonNuclsStillToAdd -= strlen($aExon[$n]);
                            $n ++;
                        }

                        $sUpstream .= substr($aExon[$n], 0, $nExonNuclsStillToAdd);
                        $sUpstream = strtolower($sUpstream);

                        // determine the number of upstream lines after the first line
                        $nLineMultFactor = (int) (($lUpstream + $nStartTranslation - 1) / LENGTH_LINE);// could be replaced by floor()??
                        $lUpstream = strlen($sUpstream);

                        // print the first line
                        $sPreSpaces = str_repeat(' ', (LENGTH_LINE - $nLeftover));// Spaces before the leftover part to be added
                        if ($lUpstream <= LENGTH_LINE) {
                            // First line is also last line of upstream sequence
                            // Determine the preceeding nucleotide number
                            $nPreceedNumber = -($nLineMultFactor*LENGTH_LINE) - strlen(substr($sUpstream, 0, $lUpstream - $nExonNuclsToAdd)) - strlen(substr($sUpstream, $lUpstream - $nExonNuclsToAdd, $nExonNuclsToAdd));
                            if (strlen($sPreSpaces) > strlen($nPreceedNumber) + 3) {// +3 because of the extra space and c.
                                // There is enough room for the preceeding nucleotide number
                                fputs($fIntron, $sPreSpaces . 'g.1' . str_repeat(' ', strlen($nPreceedNumber)) . substr($sLinemarkBack, LENGTH_LINE - $lUpstream, $lUpstream - $nExonNuclsToAdd) . '   ' . substr($sLinemarkBack,  -$nExonNuclsToAdd, $nExonNuclsToAdd) . '  g.' . $nLeftover . "\n");
                                fputs($fIntron, $sPreSpaces . 'c.' . $nPreceedNumber . ' ' . substr($sUpstream, 0, $lUpstream - $nExonNuclsToAdd) . ' \\ ' . substr($sUpstream, $lUpstream - $nExonNuclsToAdd, $nExonNuclsToAdd) . '  c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            } else {
                                // No preceeding nucleotide number will be printed
                                fputs($fIntron, $sPreSpaces . substr($sLinemarkBack, LENGTH_LINE - $lUpstream, $lUpstream - $nExonNuclsToAdd) . '   ' . substr($sLinemarkBack,  -$nExonNuclsToAdd, $nExonNuclsToAdd) . '  g.' . $nLeftover . "\n");
                                fputs($fIntron, $sPreSpaces . substr($sUpstream, 0, $lUpstream - $nExonNuclsToAdd) . ' \\ ' . substr($sUpstream, $lUpstream - $nExonNuclsToAdd, $nExonNuclsToAdd) . '  c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            }
                        } else {
                            // First line is not the last line
                            // Determine the preceeding nucleotide number
                            $nPreceedNumber = -($nLineMultFactor*LENGTH_LINE) - strlen(substr($sUpstream, 0, $nLeftover));
                            $sPreSpaces = str_repeat(' ', (LENGTH_LINE - $nLeftover));
                            if (strlen($sPreSpaces) > strlen($nPreceedNumber)) {// + 3) {// +3 because of the extra space
                                // Determine if there is enough room for the preceeding nucleotide number
                                $sPreSpaces = str_repeat(' ', (LENGTH_LINE - $nLeftover - 3 - strlen($nPreceedNumber)));
                                fputs($fIntron, $sPreSpaces . 'g.1' . str_repeat(' ', strlen($nPreceedNumber)) . substr($sLinemarkBack, -$nLeftover) . '    g.' . $nLeftover . "\n");// Print the line with the 10th position marks (dots)
                                fputs($fIntron, $sPreSpaces . 'c.' . $nPreceedNumber . ' ' . substr($sUpstream, 0, $nLeftover) . '    c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            } else {
                                // No preceeding nucleotide number will be printed
                                fputs($fIntron, $sPreSpaces . substr($sLinemarkBack, -$nLeftover) . '    g.' . $nLeftover . "\n");// Print the line with the 10th position marks (dots)
                                fputs($fIntron, $sPreSpaces . substr($sUpstream, 0, $nLeftover) . '    c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            }
                        }

                        // print the succeeding lines
                        for ($i = $nLeftover; $i <= $lUpstream - LENGTH_LINE + 1; $i += LENGTH_LINE) {
                            $nLineMultFactor --;
                            $nGenomicNumberUp += LENGTH_LINE;
                            if ($i == $lUpstream - LENGTH_LINE) {
                                // It is the last line
                                fputs($fIntron, substr($sLinemarkBack, 0, LENGTH_LINE - $nExonNuclsToAdd) . '   ' . substr($sLinemarkBack, LENGTH_LINE - $nExonNuclsToAdd, $nExonNuclsToAdd) . ' g.' . $nGenomicNumberUp . "\n");
                                fputs($fIntron, substr($sUpstream, $i, LENGTH_LINE - $nExonNuclsToAdd) . ' \\ ' . substr($sUpstream, $i + LENGTH_LINE - $nExonNuclsToAdd, $nExonNuclsToAdd) . ' c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            } else {
                                fputs($fIntron, $sLinemarkBack . '    g.' . $nGenomicNumberUp . "\n" . substr($sUpstream, $i, LENGTH_LINE) . '    c.' . -($nLineMultFactor*LENGTH_LINE + 1) . "\n\n");
                            }
                        }
//INTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRON
//INTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRONINTRON
// except for some variable names, this part has not been changed, works OK
                    } elseif ($where == 'intron') {
                        // The 'real' introns
                        // Prepare sequence and declare some vars.
                        $sIntron = strtolower($sIntron);
                        $lIntron = strlen($sIntron);// length of the intron

                        // 2009-02-27; 2.0-16; added by Gerard
                        $aLengthsSequenceParts[] = $lIntron;

                        $nMiddle = round($lIntron / 2);// find the middle of the intron
                        $nStart2 = $nMiddle - $lIntron;
                        $lLeftover = -($nStart2 % LENGTH_LINE);

                        // 2009-02-27; 2.0-16; need this one for the g. numbering
                        $nGenomicNumberIntron += (strlen(str_replace("|", "", $aExon[$nIntron])) + LENGTH_LINE);

                        // Printing sequence...
                        for ($i = 0; $i + LENGTH_LINE <= $nMiddle; $i += LENGTH_LINE) {
                            // Continuing untill the middle of the intron.
                            fputs($fIntron, $sLinemark . '  g.' . ($nGenomicNumberIntron). "\n");
                            fputs($fIntron, substr($sIntron, $i, LENGTH_LINE) . '  c.' . ($aExonEnds[$nIntron] <= 0? $aExonEnds[$nIntron] - 1 : $aExonEnds[$nIntron]) . '+' . ($i + LENGTH_LINE) . "\n\n");
                            $nGenomicNumberIntron += LENGTH_LINE;
                        }

                        // Remaining for the middle.
                        $nRemain = $nMiddle - $i;

                        if ($nRemain) {
                            $nGenomicNumberIntron += ($nRemain - LENGTH_LINE);
                            fputs($fIntron, substr($sLinemark, 0, $nRemain) . '  g.' . ($nGenomicNumberIntron) . "\n");
                            fputs($fIntron, substr($sIntron, $i, $nRemain) . '  c.' . ($aExonEnds[$nIntron] <= 0? $aExonEnds[$nIntron] - 1 : $aExonEnds[$nIntron]) . '+' . ($i + $nRemain) . "\n\n");
                        }

                        // 2009-02-27; 2.0-16; by Gerard
                        if ($nRemain == 0) {
                            $nGenomicNumberIntron -= LENGTH_LINE;
                        }

                        fputs($fIntron, str_pad(' middle of intron ', LENGTH_LINE, '-', STR_PAD_BOTH) . "\n");

                        // Middle of the intron
                        if ($lLeftover) {
                            // Line markings.
                            $sPreSpaces = str_repeat(' ', LENGTH_LINE - $lLeftover);
                            $nGenomicNumberIntron += $lLeftover;

                            // Room left for a genomic nucleotide number in front of the sequence?
                            if (strlen($sPreSpaces) > (strlen($aExonEnds[$nIntron]) + strlen($nStart2 - 1) + 1)) {
                                // 2009-02-27; 2.0-16; by Gerard
                                $x = LENGTH_LINE - $lLeftover - strlen($aExonEnds[$nIntron]) - strlen($nStart2 - 1) - 4;
                                fputs($fIntron, substr($sPreSpaces, 0, (LENGTH_LINE - $lLeftover - strlen($aExonEnds[$nIntron]) - strlen($nStart2 - 1) - 4)) . 'g.' . ($nGenomicNumberIntron - $lLeftover + 1) . substr($sPreSpaces, 0, ($sPreSpaces - $x - strlen($nGenomicNumberIntron - $lLeftover + 1) - 2)) . substr($sLinemarkBack, -$lLeftover) . '  g.' . $nGenomicNumberIntron . "\n");// + strlen( + 2
                                fputs($fIntron, substr($sPreSpaces, 0, (LENGTH_LINE - $lLeftover - strlen($aExonEnds[$nIntron]) - strlen($nStart2 - 1) - 4)) . 'c.' . ($aExonEnds[$nIntron] < 0? $aExonEnds[$nIntron] : $aExonEnds[$nIntron] + 1) . ($nStart2) . '  ');
                            } else {
                                fputs($fIntron, $sPreSpaces . substr($sLinemarkBack, -$lLeftover) . '  g.' . $nGenomicNumberIntron . "\n");
                                fputs($fIntron, $sPreSpaces);
                            }
                            fputs($fIntron, substr($sIntron, $nStart2, $lLeftover) . '  c.' . ($aExonEnds[$nIntron] < 0? $aExonEnds[$nIntron] : $aExonEnds[$nIntron] + 1) . ($nStart2 + $lLeftover - 1) . "\n\n");
                        }

                        // After the middle.
                        for ($i = ($nStart2 + $lLeftover); $i + LENGTH_LINE <= 0; $i += LENGTH_LINE) {
                            // Continuing untill the end of the intron.
                            $nGenomicNumberIntron += LENGTH_LINE;
                            fputs($fIntron, $sLinemarkBack . '  g.' . $nGenomicNumberIntron . "\n");
                            fputs($fIntron, substr($sIntron, $i, LENGTH_LINE) . '  c.' . ($aExonEnds[$nIntron] < 0? $aExonEnds[$nIntron] : $aExonEnds[$nIntron] + 1) . ($i + LENGTH_LINE - 1) . "\n\n");
                        }

                    } else {
//DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-
//DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-DOWNSTREAM-
                        // Downstream, keep on counting as if the exon refseq goes on...

                        // Prepare sequence and declare some vars (part I).
                        $sIntron = strtolower($sIntron);
                        $lIntron = strlen($sIntron);// length of the downstream sequence

                        // 2009-02-27; 2.0-16; added by Gerard, need this for the g. numbering
                        $aLengthsSequenceParts['downstream'] = $lIntron;
                        $nGenomicNumberDown = array_sum($aLengthsSequenceParts) + strlen($sCodingSequence) - $aLengthsSequenceParts['downstream'];

                        // We may need to align the downstream nicely, so we may need to copy some nucleotides from the last exon to prepend.
                        // To determine how much to copy, we need to know exactly where the translation stops.
                        // We'll have to loop through the sequence.

                        $nEnd  = 0;
                        for ($i = $nStartTranslation; $i < $nExonNucleotides; $i += 3) {
                            if (in_array(strtolower(substr($sCodingSequence, $i, 3)), array('taa', 'tag', 'tga'))) {
                                // stop codon!
                                $nEnd = $i + 3;
                                break;
                            }
                        }
                        $nStart2 = ($nExonNucleotides - $nEnd) + 1;
                        $nExonNuclsToAdd   = $nStart2 % LENGTH_LINE;
                        $nStart2 = $nStart2 - $nExonNuclsToAdd;

                        $sIntron  = strtolower(substr($aExon[count($aExon)], -$nExonNuclsToAdd)) . $sIntron;
                        $lIntron += $nExonNuclsToAdd;
                        $aExonEnds[$nIntron] -= $nExonNuclsToAdd;

                        // Printing sequence
                        if ($lIntron <= LENGTH_LINE) {
                            // Length of downstream sequence is shorter than the length of a line CHECK THIS
                            fputs($fIntron, substr($sLinemark, 0, $nExonNuclsToAdd) . '   ' . substr($sLinemark, $nExonNuclsToAdd, $lIntron - $nExonNuclsToAdd) . ' g.' . ($nGenomicNumberDown + $lIntron) . "\n");
                            fputs($fIntron, substr($sIntron, 0, $nExonNuclsToAdd) . ' / ' . substr($sIntron, $nExonNuclsToAdd, $lIntron - $nExonNuclsToAdd) . ' c.*' . $lIntron . "\n\n");
                        } else {
                            for ($i = 0; $i + LENGTH_LINE <= $lIntron; $i += LENGTH_LINE) {
                                if (!$i) {
                                    // First line, we may need to indicate the border between the last exon and the downstream sequence
                                    $nGenomicNumberDown += LENGTH_LINE - $nExonNuclsToAdd - 1;
                                    fputs($fIntron, substr($sLinemark, 0, $nExonNuclsToAdd) . '   ' . substr($sLinemark, $nExonNuclsToAdd, LENGTH_LINE - $nExonNuclsToAdd) . ' g.' . ($nGenomicNumberDown) . "\n");
                                    fputs($fIntron, substr($sIntron, $i, $nExonNuclsToAdd) . ' / ' . substr($sIntron, $nExonNuclsToAdd, LENGTH_LINE - $nExonNuclsToAdd) . ' c.*' . ($nStart2 + $i + LENGTH_LINE) . "\n\n");
                                } else {
                                    // Not the first or last line
                                    $nGenomicNumberDown += LENGTH_LINE;
                                    fputs($fIntron, $sLinemark . '    g.' . $nGenomicNumberDown . "\n");
                                    fputs($fIntron, substr($sIntron, $i, LENGTH_LINE) . '    c.*' . ($nStart2 + $i + LENGTH_LINE) . "\n\n");
                                }
                            }
                            // Remainder for the end (last line).
                            $nRemain = $lIntron - $i;// $i has the last value of the previous for loop?
                            if ($nRemain) {
                                fputs($fIntron, substr($sLinemark, 0, $nRemain) . str_repeat(' ', LENGTH_LINE - $nRemain) . '    g.' . ($nGenomicNumberDown + $nRemain) . "\n");
                                fputs($fIntron, str_pad(substr($sIntron, $i, $nRemain), LENGTH_LINE) . '    c.*' . (/*$aExonEnds[$nIntron]*/$nStart2 + $i + $nRemain) . "\n\n");
                            }
                        }
                    }

                    $sOut .= ($sOut? "\n" : '') . 'Successfully wrote ' . ($where == 'up'? 'upstream sequence' : ($where == 'intron'? 'intron ' . $sNIntron : 'downstream sequence'));
                    // 2010-01-28; 2.0-24; replaced link to www.dmd.nl by link to www.lovd.nl
                    fputs($fIntron, '</PRE>' . "\n\n" .
                        '<HR>' . "\n" .
                        '<P align="center" style="font-size : 11px;">' . "\n" .
                        '  Powered by <A href="' . $_SETT['upstream_URL'] . $_STAT['tree'] . '/" target="_blank">LOVD v.' . $_STAT['tree'] . '</A> Build ' . $_STAT['build'] . '<BR>' . "\n" .
                        '  &copy;2004-' . date('Y') . ' <A href="http://www.lumc.nl/" target="_blank">Leiden University Medical Center</A>' . "\n" .
                        '</P>' . "\n" .
                        '<HR>' . "\n\n" .
                        '</BODY>' . "\n" .
                        '</HTML>');
                    fclose($fIntron);

                } else {
                    // This really shouldn't happen, as we have checked this already...
                    lovd_errorAdd('file', 'Couldn\'t open file to write to for intron ' . $sNIntron);
                }
            }

// END OF CREATING THE UPSTREAM, DOWNSTREAM AND INTRON FILES/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

            if (!lovd_error()) {
                // Create sequence for step 3.

                $_POST['sequence'] = wordwrap(implode(';', $aExon), LENGTH_LINE, "\n", 1);
                print('<SPAN class="S15"><B>Step 2 - Create intronic sequences</B></SPAN><BR><BR>' . "\n\n");

                // 2009-12-03; 2.0-23; set the version_id if not set by user
                if (!isset($_POST['version_id'])) {
                    $_POST['version_id'] = $_SETT['currdb']['refseq_genomic'];
                }

                // Create a table with start and end positions of exons in genomic and coding DNA, including lengths and intron lengths
                // and write to a tab-delimited text file
                // 2012-07-02; 3.0-beta05
                // $sTableFile = $sPath . $_POST['symbol'] . '_table.txt';
                $sTableFile = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_table.txt';
                if (file_exists($sTableFile)) {
                    switch ($_POST['exists']) {
                        case 'skip' :
                            // Skip this file, we already have a file
                            $sOut .= ($sOut? "\n" : '') . 'Skipped creation of table, file existed';
                            $sTableFile = '';
                            break;
                        case 'rename' :
                            // Rename the old file, we create a new refseq
                            $sTableFile = lovd_fileCopiesExist($sTableFile);
                            break;
                    }
                }

                $fTable = @fopen($sTableFile, 'w');
                // 2009-12-28; 2.0-24; remove file if it cannot be opened for writing
                if (!$fTable) {
                    unlink($sTableFile);
                    $fTable = fopen($sTableFile, 'w');
                }
                if ($fTable) {
                    if ($bFilesExisted) {
                        //If the intron files were skipped you'll need to adjust $nEnd
                        $sCodingSequence = implode($aExon);// put the whole exon array in a string
                        $nEnd  = 0;
                        for ($i = $nStartTranslation; $i < $nExonNucleotides; $i += 3) {//$nExonNucleotides should be the same as strlen($sCodingSequence) check this
                            if (in_array(strtolower(substr($sCodingSequence, $i, 3)), array('taa', 'tag', 'tga'))) {
                                // stop codon!
                                $nEnd = $i + 3;
                                break;
                            }
                        }
                    }

                    // write the column headers
                    fwrite($fTable, 'exon #' . "\t" . 'c.startExon' . "\t" . 'c.endExon' . "\t" . 'g.startExon' . "\t" . 'g.endExon' . "\t" . 'lengthExon' . "\t" . 'lengthIntron' . "\n");

                    $nStartExonCoding = 1 - $nStartTranslation;     //start of nucleotide numbering exon coding DNA
                    $nEndExonCoding = 0;                            //end of nucleotide numbering exon coding DNA

                    // 2008-10-29; 2.0-13; Added by Gerard to solve bug when no up and/or downstream sequences were provided in the GenBank file
                    if (!isset($aIntron[0])) {
                        // no upstream sequence provided
                        $aIntron[0] = '';
                    }

                    $nStartExonGenomic = 1 + strlen($aIntron[0]);   //start of nucleotide numbering exon genomic DNA
                    $nEndExonGenomic = 0;                           //end of nucleotide numbering exon genomic DNA
                    $lCodingSeq = $nEnd - $nStartTranslation;       //length of the coding sequence (from start to stop codon)
                    $bStopExon = false;                             // flag if translation already stopped

                    for ($i = 1; $i <= count($aExon); $i++) {// start at 1 because first element is upstream sequence
                        $nEndExonCoding = $nStartExonCoding + strlen($aExon[$i]) - 1;
                        $nEndExonGenomic = $nStartExonGenomic + strlen(str_replace("|", "", $aExon[$i])) - 1;

                        // 2008-10-29; 2.0-13; by Gerard to solve bugs when no up and/or downstream sequences are provided
                        if (!isset($aIntron[$i])) {
                            // no downstream sequence provided
                            $aIntron[$i] = '';
                        }

                        if (($nEndExonCoding >= $lCodingSeq) && $nStartExonCoding < $lCodingSeq && $bStopExon == false) {
                            // Translation stops in this exon
                            $bStopExon = true;
                            $nEndExonCoding = $nStartExonCoding + $nStartTranslation + strlen($aExon[$i]) - $nEnd - 1;
                            if ($i == count($aExon)) {
                                // last exon, no intron length to write
                                fwrite($fTable, $i . "\t" . $nStartExonCoding . "\t" . "*" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\n");
                            } else {
                                // not the last exon, also write the intron length
                                fwrite($fTable, $i . "\t" . $nStartExonCoding . "\t" . "*" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\t" . strlen($aIntron[$i]) . "\n");
                            }
                            $nStartExonCoding = $nEndExonCoding + 1;

                        } elseif ($bStopExon == true) {
                            // Translation stopped in a previous exon
                            if ($i == count($aExon)) {
                                // last exon, no intron length to write
                                fwrite($fTable, $i . "\t" . "*" . $nStartExonCoding . "\t" . "*" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\n");
                            } else {
                                // not the last exon, also write the intron length
                                fwrite($fTable, $i . "\t" . "*" . $nStartExonCoding . "\t" . "*" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\t" . strlen($aIntron[$i]) . "\n");
                            }
                            $nStartExonCoding = $nEndExonCoding + 1;

                        } else {
                            // no translation stop in this or previous exons
                            if ($i == count($aExon)) {
                                // last exon, no intron length to write
                                // 2009-03-30; 2.0-17; In case translation start coincides with an exon start, numbering has to be adapted (position 0 does not exist)
                                fwrite($fTable, $i . "\t" . ($nStartExonCoding != 0? $nStartExonCoding : 1) . "\t" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\n");
                            } else {
                                // not the last exon, also write the intron length
                                // 2009-03-30; 2.0-17; In case translation start coincides with an exon start, numbering has to be adapted (position 0 does not exist)
                                fwrite($fTable, $i . "\t" . ($nStartExonCoding != 0? $nStartExonCoding : 1) . "\t" . $nEndExonCoding . "\t" . $nStartExonGenomic . "\t" . $nEndExonGenomic . "\t" . strlen(str_replace("|", "", $aExon[$i])) . "\t" . strlen($aIntron[$i]) . "\n");
                            }
                            $nStartExonCoding += strlen($aExon[$i]);
                        }
                        $nStartExonGenomic += (strlen(str_replace("|", "", $aExon[$i])) + strlen($aIntron[$i]));
                    }
                    // 2012-07-02; 3.0-beta05
                    // $sOut .= ($sOut? "\n" : '') . 'Successfully wrote exon lengths table, see: <A href="'. ROOT_PATH . 'refseq/' . $_POST['symbol'] . '_table.txt" target="_blank">' . $_POST['symbol'] . '_table.txt</A>)';
                    $sOut .= ($sOut? "\n" : '') . 'Successfully wrote exon lengths table, see: <A href="refseq/' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_table.txt" target="_blank">' . $_POST['symbol'] . '_table.txt</A>)';
                    fclose($fTable);
                } else {
                    // This really shouldn't happen, as we have checked this already...
                    lovd_errorAdd('file', 'Couldn\'t open file to write to for table ' . $fTable);
                }

                // Create a table with start and end positions of exons in genomic and coding DNA, including lengths and intron lengths
                // and write to a html file
                // 2012-07-02; 3.0-beta05
                // $sTableHTMLFile = $sPath . $_POST['symbol'] . '_table.html';
                $sTableHTMLFile = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_table.html';
                if (file_exists($sTableHTMLFile)) {
                    switch ($_POST['exists']) {
                        case 'skip' :
                            // Skip this file, we already have a file
                            $sOut .= ($sOut? "\n" : '') . 'Skipped creation of html table, file existed';
                            $sTableHTMLFile = '';
                            break;
                        case 'rename' :
                            // Rename the old file, we create a new refseq
                            $sTableHTMLFile = lovd_fileCopiesExist($sTableHTMLFile);
                            break;
                    }
                }
                $fTable = @fopen($sTableHTMLFile, 'w');
                // 2009-12-28; 2.0-24; remove file if it cannot be opened for writing
                if (!$fTable) {
                    unlink($sTableHTMLFile);
                    $fTable = fopen($sTableHTMLFile, 'w');
                }
                if ($fTable) {
                    if ($bFilesExisted) {
                        //If the intron files were skipped you'll need to adjust $nEnd
                        $sCodingSequence = implode($aExon);// put the whole exon array in a string
                        $nEnd  = 0;
                        for ($i = $nStartTranslation; $i < $nExonNucleotides; $i += 3) {//$nExonNucleotides should be the same as strlen($sCodingSequence) check this
                            if (in_array(strtolower(substr($sCodingSequence, $i, 3)), array('taa', 'tag', 'tga'))) {
                                // stop codon!
                                $nEnd = $i + 3;
                                break;
                            }
                        }
                    }

                    fwrite($fTable, '<HTML><BODY>' . "\n\n" . '<TABLE border="1">' . "\n");
                    // write the column headers
                    fwrite($fTable, '  <TR>' . "\n" . '    <TH>exon</TH>' . "\n" . '    <TH>c.startExon</TH>' . "\n" . '    <TH>c.endExon</TH>' . "\n" . '    <TH>g.startExon</TH>' . "\n" . '    <TH>g.endExon</TH>' . "\n" . '    <TH>lengthExon</TH>' . "\n" . '    <TH>lengthIntron</TH></TR>');
                    $nStartExonCoding = 1 - $nStartTranslation;     //start of nucleotide numbering exon coding DNA
                    $nEndExonCoding = 0;                            //end of nucleotide numbering exon coding DNA
                    $nStartExonGenomic = 1 + strlen($aIntron[0]);   //start of nucleotide numbering exon genomic DNA
                    $nEndExonGenomic = 0;                           //end of nucleotide numbering exon genomic DNA
                    $lCodingSeq = $nEnd - $nStartTranslation;       //length of the coding sequence (from start to stop codon)
                    $bStopExon = false;                             // flag if translation already stopped

                    for ($i = 1; $i <= count($aExon); $i++) {// start at 1 because first element is upstream sequence
                        $nEndExonCoding = $nStartExonCoding + strlen($aExon[$i]) - 1;
                        $nEndExonGenomic = $nStartExonGenomic + strlen(str_replace("|", "", $aExon[$i])) - 1;
                        if (($nEndExonCoding >= $lCodingSeq) && $nStartExonCoding < $lCodingSeq && $bStopExon == false) {
                            // Translation stops in this exon
                            $bStopExon = true;
                            $nEndExonCoding = $nStartExonCoding + $nStartTranslation + strlen($aExon[$i]) - $nEnd - 1;
                            fwrite($fTable, "\n" . '<TR>' . "\n" . '    <TD>' . $i . '</TD>' . "\n" . '    <TD>' . $nStartExonCoding . '</TD>' . "\n" . '    <TD>' . "*" . $nEndExonCoding . '</TD>' . "\n" . '    <TD>' . $nStartExonGenomic . '</TD>' . "\n" . '    <TD>' . $nEndExonGenomic . '</TD>' . "\n" . '    <TD>' . strlen(str_replace("|", "", $aExon[$i])) . '</TD>' . "\n" . '    <TD>' . ($i == count($aExon)? '&nbsp;' : strlen($aIntron[$i])) . '</TD></TR>');
                            $nStartExonCoding = $nEndExonCoding + 1;

                        } elseif ($bStopExon == true) {
                            // Translation stopped in a previous exon
                            fwrite($fTable, "\n" . '<TR>' . "\n" . '    <TD>' . $i . '</TD>' . "\n" . '    <TD>' . "*" . $nStartExonCoding . '</TD>' . "\n" . '    <TD>' . "*" . $nEndExonCoding . '</TD>' . "\n" . '    <TD>' . $nStartExonGenomic . '</TD>' . "\n" . '    <TD>' . $nEndExonGenomic . '</TD>' . "\n" . '    <TD>' . strlen(str_replace("|", "", $aExon[$i])) . '</TD>' . "\n" . '    <TD>' . ($i == count($aExon)? '&nbsp;' : strlen($aIntron[$i])) . '</TD></TR>');
                            $nStartExonCoding = $nEndExonCoding + 1;

                        } else {
                            // no translation stop in this or previous exons
                            // 2009-03-30; 2.0-17; In case translation start coincides with an exon start, numbering has to be adapted (position 0 does not exist)
                            fwrite($fTable, "\n" . '<TR>' . "\n" . '    <TD>' . $i . '</TD>' . "\n" . '    <TD>' . ($nStartExonCoding != 0? $nStartExonCoding : 1) . '</TD>' . "\n" . '    <TD>' . $nEndExonCoding . '</TD>' . "\n" . '    <TD>' . $nStartExonGenomic . '</TD>' . "\n" . '    <TD>' . $nEndExonGenomic . '</TD>' . "\n" . '    <TD>' . strlen(str_replace("|", "", $aExon[$i])) . '</TD>' . "\n" . '    <TD>' . ($i == count($aExon)? '&nbsp;' : strlen($aIntron[$i])) . '</TD></TR>');
                            $nStartExonCoding += strlen($aExon[$i]);
                        }
                        $nStartExonGenomic += (strlen(str_replace("|", "", $aExon[$i])) + strlen($aIntron[$i]));
                    }
                    // 2012-07-02; 3.0-beta05
                    // $sOut .= ($sOut? "\n" : '') . 'Successfully wrote exon lengths table, see: <A href="'. ROOT_PATH . 'refseq/' . $_POST['symbol'] . '_table.html" target="_blank">' . $_POST['symbol'] . '_table.html</A>)';
                    $sOut .= ($sOut? "\n" : '') . 'Successfully wrote exon lengths table, see: <A href="refseq/' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_table.html" target="_blank">' . $_POST['symbol'] . '_table.html</A>)';
                    fwrite($fTable, '</TABLE>' . "\n\n" . '</BODY></HTML>');
                    fclose($fTable);
                } else {
                    // This really shouldn't happen, as we have checked this already...
                    lovd_errorAdd('file', 'Couldn\'t open file to write to for table ' . $fTable);
                }



                print('Output for this step:<BR>' . "\n" . str_replace("\n", '<BR>' . "\n", $sOut) . '<BR><BR>' . "\n");

                // To continue to step 3, we need to create a form and send all data.
                print('<FORM action="' . $_SERVER['SCRIPT_NAME'] . '?step=3" method="post">' . "\n" .
                    '  <INPUT type="hidden" name="symbol" value="' . $_POST['symbol'] . '">' . "\n" .
                    '  <INPUT type="hidden" name="sequence" value="' . $_POST['sequence'] . '">' . "\n" .
                    '  <INPUT type="hidden" name="version_id" value="' . $_POST['version_id'] . '">' . "\n" . // 2009-12-03; 2.0-23; accession number with version added
                    '  <INPUT type="hidden" name="transcript_id" value="' . $_POST['transcript_id'] . '">' . "\n" .
                    '  <INPUT type="hidden" name="exists" value="' . $_POST['exists'] . '">' . "\n" .
                    '  <INPUT type="hidden" name="step2" value="true">' . "\n"); // 2009-03-09; 2.0-17; by Gerard: need to know if you created the up- and downstream sequences in step 2

                // 2009-02-27; 2.0-16; you need these lengths for the g. numbering
                if (!empty($aLengthsSequenceParts)) {
                    print('  <INPUT type="hidden" name="aLengthsSequenceParts" value="' . htmlspecialchars(serialize($aLengthsSequenceParts)) . '">' . "\n");
                }

                print('  <INPUT type="submit" value="Continue to next step &raquo;">' . "\n" .
                    '</FORM><BR>' . "\n\n");

                $_T->printFooter();
                exit;
            }
        }

    } else {
        // Standard settings.
        $_POST['exists'] = 'overwrite';
    }



    // Print the form for step 2: create intronic sequences
    $_T->printTitle('Step 2 - Create intronic sequences');
    print('      <BR>' . "\n\n");

    lovd_errorPrint();

    print('      <FORM action="' . $_SERVER['SCRIPT_NAME'] . '?step=2&amp;sent=true" method="post">' . "\n");

    // Need to pass symbol.
    if ($bStep1) {
        print('        <INPUT type="hidden" name="symbol" value="' . $_POST['symbol'] . '">'. "\n" .
              '        <INPUT type="hidden" name="transcript_id" value="' . $_POST['transcript_id'] . '">' . "\n" .
              '        <INPUT type="hidden" name="version_id" value="' . $_POST['version_id'] . '">'. "\n");
    }



    $aForm = array();
    $aForm[] = array('POST', '', '', '', '50%', '14', '50%');
    // 2009-06-22; 2.0-19; Replaced gene and symbol.
//    if (!$bStep1) {
//        // 2009-06-25; 2.0-19; Retrieve list of symbols and gene names for form lists, for the genes you 'own'.
//        // 2010-03-12; 2.0-25; This will make this query very complicated, but it will also shorten the gene names quite properly.
//        // 2012-07-02; 3.0-beta05
//        // $sQ = 'SELECT g.symbol, CONCAT(SUBSTRING(CONCAT(g.symbol, " (", g.gene), 1, 60), SUBSTRING("...)", (((LENGTH(CONCAT(g.symbol, g.gene))<59)+1)*3)-2)) AS gene FROM ';
//        $sQ = 'SELECT g.id, CONCAT(SUBSTRING(CONCAT(g.id, " (", g.name), 1, 60), SUBSTRING("...)", (((LENGTH(CONCAT(g.id, g.name))<59)+1)*3)-2)) AS gene FROM ';
//        if ($_AUTH['level'] == LEVEL_CURATOR) {
//            $sQ .= TABLE_CURATES . ' AS c LEFT JOIN ' . TABLE_GENES . ' AS g ON (g.id=c.geneid) WHERE userid = "' . $_AUTH['id'] . '" ORDER BY c.geneid';
//        } else {
//            $sQ .= TABLE_GENES . ' AS g ORDER BY g.id';
//        }
//        $qGenes = mysql_query($sQ);
//        $aGenes = $_DB->query($sQ)->fetchAllCombine();
//        $aForm[] = array('Select gene database',  '','select', 'symbol', 1, $aGenes, false, false, false);
//    }
    $aForm[] = array('Input sequence', '', 'textarea', 'sequence', '60', '8');
//    $aForm[] = array('', '', 'print', '<A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">More information on the format</A>');
    $aForm[] = 'skip';
    $aForm[] = array('If files are found to exist, I will', '', 'select', 'exists', 1, array('skip' => 'skip the file', 'rename' => 'rename the old file', 'overwrite' => 'overwrite it'), '', '', '');
    $aForm[] = array('', '', 'submit', 'Continue');
    lovd_viewForm($aForm);
    print('      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}






if ($_GET['step'] == 3) {
    // Get sequence from 2 and parse the coding DNA sequence.
    // 2009-03-09; 2.0-17; added by Gerard, need this for the g. numbering
    // 2009-09-15; 2.0-22; added else to prevent notice
    if (isset($_POST['aLengthsSequenceParts'])) {
        $aLengthsSequenceParts = unserialize(stripslashes($_POST['aLengthsSequenceParts']));
    } else {
        $aLengthsSequenceParts = array();
    }

    // We need to know if you came from step 2.
    if (isset($_POST['step2'])) {
        $bStep2 = $_POST['step2'];
    } else {
        $bStep2 = false;

        // Actually, not supported anymore.
        lovd_showInfoTable('Sorry, it is not supported to start at step 3.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (isset($_GET['sent'])) {
        // Verification of the sequence.

        // Error check.
        lovd_errorClean();

        // Mandatory fields with their names.
        // 2009-06-22; 2.0-19; Removed gene.
        $aCheck = array(
            'symbol' => 'Gene symbol',
            'sequence' => 'Input sequence'
        );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // Check on avoidance of selection lists.
        if (!empty($_POST['symbol']) && !lovd_isAuthorized('gene', $_POST['symbol'])) {
            lovd_errorAdd('symbol', 'You do not have rights to run this script for this gene!');
        }

        // 2009-12-07; 2.0-23; check the format of the link to the GenBank record
        if ($_POST['version_id'] && !preg_match('/(N[CG]_[0-9]+\.[0-9]+)/', $_POST['version_id'])) {
            // Error in GenBank accession number.
            lovd_errorAdd('file', 'Incorrect GenBank link. This field can only contain accession numbers starting with NC or NG appended with an underscore followed by numbers, a dot and the version number.');
        }



        if (!lovd_error()) {
            // All fields filled in, go ahead

            $sSeq = str_replace("\r", '', $_POST['sequence']);
            $sSeq = str_replace("\n", '', $sSeq);

            // Needed variables
            $nNuclPreTranslStart = 0;    // Number of nucleotides before the translation starts
            $nNuclPostTranslStart = 0;   // Number of nucleotides after the translation starts
            $started = false;   // Did we find the translation sign yet?

            $_POST['gene'] = $_DB->query('SELECT name FROM ' . TABLE_GENES . ' WHERE id = ?', array($_POST['symbol']))->fetchColumn();

            for ($i = 0; $i < strlen($sSeq); $i ++) {
                $s = $sSeq{$i};
                // We will need to loop through the sequence to provided detailed error messages
                if (!$started) {
                    // We are still before the translation
                    if (preg_match('/[ACGT]/i', $s)) {
                        // We stay where we are
                        $nNuclPreTranslStart ++;
                    } elseif ($s == '|') {
                        // Translation starts
                        $started = true;
                    } elseif ($s == ';') {
                        // Next exon, who cares?
                    } else {
                        lovd_errorAdd('file', 'Error : Unexpected character \'' . $s . '\' at char ' . ($nNuclPreTranslStart + 1));
                        break;
                    }
                } else {
                    // We are already translating.
                    if (preg_match('/[ACGT]/i', $s)) {
                        // All ok
                        $nNuclPostTranslStart ++;
                    } elseif ($s == ';') {
                        // Next exon, who cares
                    } else {
                        lovd_errorAdd('file', 'Error : Unexpected character \'' . $s . '\' at char ' . ($nNuclPreTranslStart + $nNuclPostTranslStart + 1));
                        break;
                    }
                }
            }
            if (!$started) {
                lovd_errorAdd('file', 'No translation start could be found. This doesn\'t seem to be a valid coding DNA sequence.');
            }

            // The sequence has been parsed. Now create the coding DNA file
            $sNow = date('F j, Y');
            $sNowHead = date('Y-m-d H:i:s');
            $sPath = ROOT_PATH . 'refseq/';
            $sOut = '';

            // $sFileName = $sPath . $_POST['symbol'] . '_codingDNA.html';
            $sFileName = $sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_codingDNA.html';
            if (file_exists($sFileName)) {
                switch ($_POST['exists']) {
                    case 'skip' :
                        // Skip this file, we already have a file
                        $sOut .= ($sOut? "\n" : '') . 'Skipped coding DNA, file existed';
                        print('<SPAN class="S15"><B>Step 3 - Create coding DNA reference sequence</B></SPAN><BR><BR>' . "\n\n");

                        print('Output for this step :<BR>' . "\n" . str_replace("\n", '<BR>' . "\n", $sOut) . '<BR><BR>' . "\n");

                        $_T->printFooter();
                        exit;
                    case 'rename' :
                        // Rename the old file, we create a new refseq
                        $sFileName = lovd_fileCopiesExist($sFileName);
                        break;
                }
            }

            // Write to file.
            $fCoding = @fopen($sFileName, 'w');
            // 2009-12-28; 2.0-24; remove file if it cannot be opened for writing
            if (!$fCoding) {
                unlink($sFileName);
                $fCoding = fopen($sFileName, 'w');
            }

            if ($fCoding) {
                fputs($fCoding, '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"' . "\n" .
                    '        "http://www.w3.org/TR/html4/loose.dtd">' . "\n" .
                    '<HTML lang="en">' . "\n" .
                    '<HEAD>' . "\n" .
                    '  <TITLE>' . $_POST['gene'] . ' (' . $_POST['symbol'] . ') - coding DNA reference sequence</TITLE>' . "\n" .
                    '  <META http-equiv="content-type" content="text/html; charset=UTF-8">' . "\n" .
                    '  <META name="generator" content="LOVD v.' . $_SETT['system']['version'] . ' Reference Sequence Parser @ ' . $sNowHead . '">' . "\n" .
                    '  <META name="LOVD copyright" content="&copy; 2004-' . date('Y') . ' LUMC: http://www.LUMC.nl/">' . "\n\n" .
                    '  <STYLE type="text/css">' . "\n" .
                    '    body {font-family : Verdana, Helvetica, sans-serif; font-size : 13px;}' . "\n" .
                    '    pre  {font-family : monospace;}' . "\n" .
                    '    sup  {font-size : 0.5em;}' . "\n" .
                    '  </STYLE>' . "\n" .
                    '</HEAD>' . "\n\n" .
                    '<BODY>' . "\n\n" .
                    '<HR>' . "\n" .
                    '<H1 align="center">' . $_POST['gene'] . ' (' . $_POST['symbol'] . ') - coding DNA reference sequence</H1>' . "\n" .
                    '<P align="center"><I>(used for mutation description)<BR><BR>(last modified ' . $sNow . ')</I></P>' . "\n" .
                    '<HR>' . "\n\n");

                // 2009-12-03; 2.0-23; added the mRNA accession number
                if (!isset($_POST['transcript_id']) || empty($_POST['transcript_id'])) {
                    $_POST['transcript_id'] = $_SETT['currdb']['refseq_mrna'];
                }
                // 2009-12-03; 2.0-23; added the mRNA accession number, but only if it is the same in the database
                if (!empty($_POST['version_id'])) {
                    $_POST['note'] .= ' The sequence was taken from <a href="http://www.ncbi.nlm.nih.gov/nucleotide/' . $_POST['version_id'] . '">' . $_POST['version_id'] . '</a>' . ($bStep2 ? ', covering ' . $_POST['symbol'] . ' transcript <a href="http://www.ncbi.nlm.nih.gov/nucleotide/' . $_POST['transcript_id'] . '">' . $_POST['transcript_id'] . '</a>.' : '.') .'</p>';
                }

                if (trim($_POST['note'])) {
                    fputs($fCoding, $_POST['note'] . "\n" . '<HR>' . "\n");
                }
                if ($_POST['link'] && $bStep2) {
                    // 2009-03-00; 2.0-17; by Gerard: do you want links and do you come from step 2
                    fputs($fCoding, '<I>Please note that introns are available by clicking on the exon numbers above the sequence.</I>' . "\n" . '<HR>' . "\n");
                } else {
                    fputs($fCoding, '<I>Please note that no genomic reference sequence is available. Therefore g. numbering and intron sequences are not provided.</I>' . "\n" . '<HR>' . "\n");
                }
                // 2008-10-30; 2.0-13; added by Gerard: provide links to an upstream sequence or not?

                if (file_exists($sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_upstream.html') && $bStep2) {
                    fputs($fCoding, '<PRE>' . "\n" . ($_POST['link']? ' (<A href="' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_upstream.html">upstream sequence</A>)' . "\n" : ''));
                } else {
                    fputs($fCoding, '<PRE>' . "\n" . '(upstream sequence) ' . "\n");
                }
                // Get rid of any form of whitespace.
                $_POST['sequence'] = preg_replace('/\s+/', '', $sSeq);
                // $sSeq will now contains the entire sequence including the exon splits
                $sSeq = strtolower($sSeq);
                $sTranslStart = '|';      // At this character translation starts
                $nStartTranslation = strpos(' ' . $sSeq, $sTranslStart);    // attention: can be present in the 5utr region
                $s5utr  = substr($sSeq, 0, $nStartTranslation - 1);         // Sequence before the ATG
                $sTranslated = strtoupper(substr($sSeq, $nStartTranslation));     // Sequence from the ATG
                $l5utr  = strlen(str_replace(';', '', $s5utr));             // Number of nucleotides in $s5utr
                $lTranslated = strlen(str_replace(';', '', $sTranslated));  // Number of nucleotides $sTranslated

                // We must know the locations of the exon splits
                $aExonStartPos5utr = array(-1 => 0); // start positions of exons in the 5utr region
                $nExon5utr = substr_count($s5utr, ';'); //number of exons splits in the 5utr region
                for ($i = 0; $i < $nExon5utr; $i ++) {//$aExonStartPos5utr[$i-1] bestaat in de eerste stap niet Als je nu gewoon es bij 1 begint
                    // Loop; continue until you have found all the exons. Puts all the exon split locations in an array
                    $aExonStartPos5utr[$i] = $aExonStartPos5utr[$i-1] + strpos(substr($s5utr, $aExonStartPos5utr[$i-1]), ';') + 1;
                    // Removes the ';' exon split from the sequence.
                    $s5utr = substr_replace($s5utr, '', $aExonStartPos5utr[$i] - 1, 1);
                }

                // Keep the exon splits in an array both for the 5utr and the translated region
                $aExonStartPosTransl = array(-1 => 0); // start positions of exons in the translated region
                $nExonTrans = substr_count($sTranslated, ';');
                for ($i = 0; $i < $nExonTrans; $i ++) {//$aExonStartPosTransl[$i-1] bestaat in de eerste stap nog niet
                    // Loop; continue until you have found all the exons. Puts all the exon split locations in an array
                    $aExonStartPosTransl[$i] = $aExonStartPosTransl[$i-1] + strpos(substr($sTranslated, $aExonStartPosTransl[$i-1]), ';') + 1;
                    // Removes the ';' exon split from the sequence.
                    $sTranslated = substr_replace($sTranslated, '', $aExonStartPosTransl[$i] - 1, 1);
                }

                // Prevent error if split occurs right before the ATG.
                if (in_array(strlen($s5utr) + 1, $aExonStartPos5utr)) {
                    $aExonStartPosTransl[] = 1;
                    sort($aExonStartPosTransl);
                    $nExonTrans ++;
                }


                $nExon = 1;
                $sNumr = '      ';
                $nExonSplits = 0;// 2009-07-03; 2.0-19; there can be exon splits in the first line

                if ($s5utr) {
                    // determine the number of 5utr nucleotides in the first line: leftover
                    $nLeftover = $l5utr % LENGTH_LINE;
                    // 2009-07-03; 2.0-19 added, need this for the g. numbering
                    $nGenomicNumber = (array_key_exists('upstream', $aLengthsSequenceParts)? $aLengthsSequenceParts['upstream'] : 0) + $nLeftover;
                    if ($nLeftover) {
                        $sRegl = substr($s5utr, 0, $nLeftover);
                        $l_voor_left = LENGTH_LINE - $nLeftover;
                        $nReglExon = 0;
                        foreach ($aExonStartPos5utr as $val) {
                            if ($val<$nLeftover) {
                                $nReglExon ++;
                            } else {
                                break;
                            }
                        }
                        fputs($fCoding, " ");
                        $nLeft = $l_voor_left;//-(3*$nReglExon);//changed by Gerard at 22-08-2008
                        if ($nLeft >= 1) {
                            fputs($fCoding, str_repeat(" ", $nLeft));
                        }
                        $sReglDots = substr($sLinemarkBack, -$nLeftover);
                        if ($nReglExon) {
                            for ($i=0; $i<strlen($sRegl); $i++) {
                                if (in_array($i+1, $aExonStartPos5utr)) {
                                    $nExon ++;
                                    fputs($fCoding, " | ");

                                    // 2009-07-03; 2.0-19; by Gerard; there can be exon splits in the first line
                                    $nExonSplits++;

                                    if ($_POST['link'] && $bStep2) {
                                        // 2009-03-09; 2.0-17; by Gerard: do you want links and do you come from step 2
                                        fputs($fCoding, "<A href=\"" . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_intron_' . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . ".html\" name=\"" . ($nExon - 1) . "\">");
                                    } else {
                                        fputs($fCoding, "<A name=\"" . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . "\">");
                                    }
                                    $sTmp = "";
                                    if (substr($sReglDots,$i,1) == ".") {
                                        $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1) . "</B>";
                                    } else {
                                        $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1);
                                    }
                                    if ($nExon >= 1) {
                                        $i++;
                                        if (substr($sLinemarkBack, $i, 1) == ".") {
                                            $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1) . "</B>";
                                        } else {
                                            $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1);
                                        }
                                    }
                                    fputs($fCoding, $sTmp);
                                    fputs($fCoding, "</A>");
                                } else {
                                    fputs($fCoding, substr($sReglDots, $i, 1));
                                }
                            }
                        } else {
                            fputs($fCoding, $sReglDots);
                        }

                        if ($bStep2) {
                            // 2009-07-03; 2.0-19; there can be exon splits in the first line
                            if ($nExonSplits) {
                                if ($nExonSplits > 1) {
                                    for ($es = 0; $es < $nExonSplits; $es++) {
                                        $nGenomicNumber += $aLengthsSequenceParts[$nExon - 2 - $es];
                                    }
                                } else {
                                    $nGenomicNumber += $aLengthsSequenceParts[$nExon-2];
                                }
                                fwrite($fCoding, substr($sNumr, 3*$nExonSplits) . " g." . $nGenomicNumber . "\n ");
                            } else {
                                fwrite($fCoding, ($nLeft < 1? substr($sNumr, -$nLeft) : $sNumr) . " g." . $nGenomicNumber . "\n ");
                            }

                        } else {
                            fputs($fCoding, "\n ");
                        }

                        if ($nLeft >= 1) {
                            fputs($fCoding, str_repeat(" ", $nLeft));
                        }
                        if ($nReglExon) {
                            for ($i=0; $i<strlen($sRegl); $i++) {
                                if (in_array($i+1, $aExonStartPos5utr)) {
                                    fputs($fCoding, " | ");
                                }
                                fputs($fCoding, substr($sRegl, $i, 1));
                            }
                        } else {
                            fputs($fCoding, $sRegl);
                        }
                        if ($nExonSplits) {
                            fwrite($fCoding, substr($sNumr, 3*$nExonSplits) . " c.-" . ($l5utr-$nLeftover+1) . "\n\n");
                        } else {
                            fputs($fCoding, ($nLeft < 1? substr($sNumr, -$nLeft) : $sNumr) . " c.-" . ($l5utr-$nLeftover+1) . "\n\n");
                        }
                    }
                    // 2009-03-25; 2.0-17; added and adapted by Gerard, need this for the g. numbering, only if you come from step 2
                    // line by line
                    for ($i=-($l5utr-$nLeftover); $i+LENGTH_LINE<=0; $i+=LENGTH_LINE) {
                        $k = $i+$l5utr+1;
                        $sRegl = substr($s5utr, $i, LENGTH_LINE);
                        $nVoorExon = 0;
                        foreach ($aExonStartPos5utr as $val) {
                            if ($val >= $k && $val < $k+LENGTH_LINE) {
                                $nVoorExon ++;
                            } elseif ($val > $k+LENGTH_LINE) {
                                continue;
                            }
                        }

                        // 2009-07-06; 2.0-19; there can be more than one exon split in a line
                        $nExonSplits = 0;

                        if ($nVoorExon) {
                            fputs($fCoding, " ");
                            for ($j=0; $j<LENGTH_LINE; $j++, $k++) {

                                if (in_array($k, $aExonStartPos5utr)) {
                                    $nExon ++;
                                    fputs($fCoding, " | ");

                                    // 2009-07-06; 2.0-19; there can be more than one exon split in a line
                                    $nExonSplits ++;

                                    if ($_POST['link'] && $bStep2) {
                                        // 2009-03-09; 2.0-17; do you want links and do you come from step 2
                                        fputs($fCoding, "<A href=\"" . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_intron_' . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . ".html\" name=\"" . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . "\">");
                                    } else {
                                        fputs($fCoding, "<A name=\"" . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . "\">");
                                    }
                                    $sTmp = "";
                                    if (substr($sLinemarkBack, $j, 1) == ".") {
                                        $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1) . "</B>";
                                    } else {
                                        $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1);
                                    }
                                    if ($nExon >= 1) {
                                        $j++;
                                        $k++;
                                        if (substr($sLinemarkBack, $j, 1) == ".") {
                                            $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1) . "</B>";
                                        } else {
                                            $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1);
                                        }
                                    }
                                    if ($nExon >= 100) {
                                        $j++;
                                        $k++;
                                        if (substr($sLinemarkBack, $j, 1) == ".") {
                                            $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 2, 1) . "</B>";
                                        } else {
                                            $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 2, 1);
                                        }
                                    }
                                    fputs($fCoding, $sTmp);
                                    fputs($fCoding, "</A>");
                                } else {
                                    fputs($fCoding, substr($sLinemarkBack, $j, 1));
                                }
                            }
                        } else {
                            fputs($fCoding, " " . $sLinemarkBack);
                        }

                        if ($bStep2) {
                            // 2009-03-09; 2.0-17; added by Gerard, need this for the g. numbering, only if you come from step 2
                            if ($nExonSplits) {
                                if ($nExonSplits > 1) {
                                    for ($es = 0; $es < $nExonSplits; $es++) {
                                        $nGenomicNumber += $aLengthsSequenceParts[$nExon - 2 - $es];
                                    }
                                } else {
                                    $nGenomicNumber += $aLengthsSequenceParts[$nExon-2];
                                }
                                $nGenomicNumber += LENGTH_LINE;
                                fwrite($fCoding, substr($sNumr, 3*$nVoorExon) . " g." . $nGenomicNumber . "\n ");
                            } else {
                                $nGenomicNumber += LENGTH_LINE;
                                fwrite($fCoding, substr($sNumr, 3*$nVoorExon) . " g." . $nGenomicNumber . "\n ");
                            }
                        } else {
                            fputs($fCoding, "\n ");
                        }

                        if ($nVoorExon) {
                            for ($j=0,$k=$i+$l5utr+1; $j<LENGTH_LINE; $j++,$k++) {
                                if (in_array($k, $aExonStartPos5utr)) {
                                    fputs($fCoding, " | ");
                                }
                                fputs($fCoding, substr($sRegl, $j, 1));
                            }
                        } else {
                            fputs($fCoding, $sRegl);
                            $k += LENGTH_LINE;
                        }
                        fputs($fCoding, substr($sNumr, 3*$nVoorExon) .  " c." . ($i+LENGTH_LINE-1) . "\n\n");
                    }
                }


                // ATG
                $l_prnt = 0;
                $l_prot = 0;
                $stop = false;
                $a_trns = array();
                $a_trns[] = array("A","Ala",array("GCA","GCC","GCG","GCT"));
                $a_trns[] = array("C","Cys",array("TGC","TGT"));
                $a_trns[] = array("D","Asp",array("GAC","GAT"));
                $a_trns[] = array("E","Glu",array("GAA","GAG"));
                $a_trns[] = array("F","Phe",array("TTC","TTT"));
                $a_trns[] = array("G","Gly",array("GGA","GGC","GGG","GGT"));
                $a_trns[] = array("H","His",array("CAC","CAT"));
                $a_trns[] = array("I","Ile",array("ATA","ATC","ATT"));
                $a_trns[] = array("K","Lys",array("AAA","AAG"));
                $a_trns[] = array("L","Leu",array("CTA","CTC","CTG","CTT","TTA","TTG"));
                $a_trns[] = array("M","Met",array("ATG"));
                $a_trns[] = array("N","Asn",array("AAC","AAT"));
                $a_trns[] = array("P","Pro",array("CCA","CCC","CCG","CCT"));
                $a_trns[] = array("Q","Gln",array("CAA","CAG"));
                $a_trns[] = array("R","Arg",array("AGA","AGG","CGA","CGC","CGG","CGT"));
                $a_trns[] = array("S","Ser",array("AGC","AGT","TCA","TCC","TCG","TCT"));
                $a_trns[] = array("T","Thr",array("ACA","ACC","ACG","ACT"));
                $a_trns[] = array("V","Val",array("GTA","GTC","GTG","GTT"));
                $a_trns[] = array("W","Trp",array("TGG"));
                $a_trns[] = array("X","***",array("TAA","TAG","TGA"));
                $a_trns[] = array("Y","Tyr",array("TAC","TAT"));

                for ($i=0; $i<=$lTranslated; $i+=LENGTH_LINE) {
                    $sPrnt = substr($sTranslated, $i, LENGTH_LINE);
                    $sPrntFinl = "";
                    $l_line_nucl = 0;
                    $l_line_prot = 0;
                    $n_trns_exon = 0;
                    $k = $i+1;
                    foreach ($aExonStartPosTransl as $val) {
                        if ($val >= $k && $val < $k+LENGTH_LINE) {
                            $n_trns_exon ++;
                        } elseif ($val > $k+LENGTH_LINE) {
                            continue;
                        }
                    }

                    // frameshift
                    $a_lowr = array();
                    $a_undr = array();
                    $a_bold = array();

                    // translation
                    //$s_prot = ""; // changed by Gerard at 22-08-2008; was never used
                    $sProtShrt = "";

                    for ($j=0; $j < LENGTH_LINE && $stop == false; $j+=3) {
                        for ($k=0; $k < count($a_trns); $k++) {
                            if (in_array(substr($sPrnt, $j, 3), $a_trns[$k][2])) {
                                $sTemp = $a_trns[$k][0];
                                $l_prot ++;
                                if ($a_trns[$k][0] == "X") {
                                    $l_prot --;
                                    $stop = "stopped";
                                    $j += 3;
                                    // $n_break tells me where to break the line after the stop codon.
                                    $n_break = $j;
                                    for (; $j < LENGTH_LINE; $j++) {
                                        $a_lowr[] = $j;
                                    }
                                    break;
                                }
                            } else {
                                $sTemp = "";
                            }
                            if ($sTemp) { break; }
                        }
                        $sTemp = ($sTemp? $sTemp : "?");
                        $sTemp = ($j%30 == 27? "<B>" . $sTemp . "</B>" : $sTemp);
                        $sProtShrt .= $sTemp . "  ";
                        $l_line_prot += 3;
                    }

                    // 2009-04-22; 2.0-18; by Gerard: fixed bold and underlining
                    // in first line of coding sequence and underlining in the rest
                    // of the coding sequence at the change of line
                    if ($i == 0) {
                        $sPrnt2 = substr($sTranslated, 0, LENGTH_LINE+2);
                        for ($j=1; $j < LENGTH_LINE+6; $j += 3) {
                            if (in_array(substr($sPrnt2, $j, 3), $a_trns[19][2])) {
                                $a_bold[] = $j+2;
                                $a_bold[] = $j+1;
                                $a_bold[] = $j;
                            }
                        }
                        for ($j=2; $j < LENGTH_LINE+6; $j += 3) {
                            if (in_array(substr($sPrnt2, $j, 3), $a_trns[19][2])) {
                                $a_undr[] = $j+2;
                                $a_undr[] = $j+1;
                                $a_undr[] = $j;
                            }
                        }
                    } else {
                        $sPrnt2 = substr($sTranslated, $i-2, LENGTH_LINE+4);
                        for ($j=0; $j < LENGTH_LINE+6; $j += 3) {
                            if (in_array(substr($sPrnt2, $j, 3), $a_trns[19][2])) {
                                $a_bold[] = $j-2;
                                $a_bold[] = $j-1;
                                $a_bold[] = $j;
                            }
                        }
                        for ($j=1; $j < LENGTH_LINE+6; $j += 3) {
                            if (in_array(substr($sPrnt2, $j, 3), $a_trns[19][2])) {
                                $a_undr[] = $j-2;
                                $a_undr[] = $j-1;
                                $a_undr[] = $j;
                            }
                        }
                    }

                    // Prepare DNA sequence.
                    for ($j = 0, $k = $i + 1; $j < LENGTH_LINE; $j ++, $k ++) {
                        $c_prnt = substr($sPrnt, $j, 1);
                        if (in_array($j, $a_lowr) || $stop == "done") {
                            $c_prnt = strtolower($c_prnt);

                            // If this is the first line we switch to lowercase, we'll need to stop this line.
                            if (!empty($n_break)) {
                                $c_prnt = '';
                            }
                        }
                        if (in_array($j, $a_bold) && $c_prnt) {
                            $c_prnt = "<B>" . $c_prnt . "</B>";
                        }
                        if (in_array($j, $a_undr) && $c_prnt) {
                            $c_prnt = "<U>" . $c_prnt . "</U>";
                        }
                        if (in_array($k, $aExonStartPosTransl)) {
                            $c_prnt = " | " . $c_prnt;
                        }
                        $sPrntFinl .= $c_prnt;

                        // Create number at the right of the sequence.
                        if ($l_prnt{0} != '*') {
                            // Maybe this is a weird check. Will there ever be no $c_prnt?
                            $l_prnt = ($c_prnt? $l_prnt+1 : $l_prnt);
                        } elseif ($c_prnt) {
                            // We're at the special after-stop notation, $c_prnt should not be empty.
                            $l_prnt = '*' . (substr($l_prnt, 1) + 1);
                        }
                        $l_line_nucl = ($c_prnt? $l_line_nucl+1 : $l_line_nucl);
                    }
                    $sPrntFinl = str_replace("</B><B>", "", $sPrntFinl);
                    $sPrntFinl = str_replace("</U><U>", "", $sPrntFinl);

                    // 2009-07-06; 2.0-19; there can be more than one exon split in a line
                    $nExonSplits = 0;

                    // Dots; exon split in line?
                    if ($n_trns_exon) {
                        fputs($fCoding, " ");
                        for ($j=0,$k=$i+1; (empty($n_break) && $j < LENGTH_LINE) || (!empty($n_break) && $j < $n_break); $j++,$k++) {
                            if (in_array($k, $aExonStartPosTransl)) {
                                $nExon ++;
                                fputs($fCoding, " | ");

                                // 2009-07-06; 2.0-19; there can be more than one exon split in a line
                                $nExonSplits ++;

                                if ($_POST['link'] && $bStep2) {
                                    // 2009-03-09; 2.0-17; by Gerard: do you want links, only if you come from step 2
                                    fputs($fCoding, "<A href=\"" . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_intron_' . str_pad($nExon-1, "2", "0", STR_PAD_LEFT) . ".html\" name=\"" . ($nExon - 1) . "\">");
                                } else {
                                    fputs($fCoding, "<A name=\"" . ($nExon - 1) . "\">");
                                }
                                $sTmp = "";
                                if (substr($sLinemark, $j, 1) == ".") {
                                    $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1) . "</B>";
                                } else {
                                    $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 0, 1);
                                }
                                if ($nExon >= 1) {
                                    $j++;
                                    $k++;
                                    if (substr($sLinemark, $j, 1) == ".") {
                                        $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1) . "</B>";
                                    } else {
                                        $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 1, 1);
                                    }
                                }
                                if ($nExon >= 100) {
                                    $j++;
                                    $k++;
                                    if (substr($sLinemarkBack, $j, 1) == ".") {
                                        $sTmp .= "<B>" . substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 2, 1) . "</B>";
                                    } else {
                                        $sTmp .= substr(str_pad($nExon, "2", "0", STR_PAD_LEFT), 2, 1);
                                    }
                                }
                                fputs($fCoding, $sTmp);
                                fputs($fCoding, "</A>");
                            } else {
                                fputs($fCoding, substr($sLinemark,$j,1));
                            }
                        }
                    } else {
                        // Writes dots when line contains no exon split.
                        fputs($fCoding, " " . substr($sLinemark, 0, $l_line_nucl));
                    }

                    if ($bStep2) {
                        // 2009-03-09; 2.0-17; added by Gerard, need this for the g. numbering, only if you come from step 2
                        if ($nExonSplits) {
                            if ($nExonSplits > 1) {
                                for ($es = 0; $es < $nExonSplits; $es++) {
                                    $nGenomicNumber += $aLengthsSequenceParts[$nExon - 2 - $es];
                                }
                            } else {
                                $nGenomicNumber += $aLengthsSequenceParts[$nExon-2];
                            }
                            $nGenomicNumber += LENGTH_LINE;
                        } elseif (isset($n_break)) {//2009-03-09; 2.0-17 added isset() by Gerard
                            // when you encountered the stop codon
                            $nGenomicNumber += $n_break;
                        } elseif ($l_line_nucl != LENGTH_LINE) {// $l_line_nucl can be 0 (zero)!
                            // reached the last line
                            $nGenomicNumber += $l_line_nucl;
                        } else {
                            $nGenomicNumber += LENGTH_LINE;
                        }
                        // 2009-07-03; 2.0-19; if condition added to prevent g. numbering on empty last line when $l_line_nucl == 0
                        if ($l_line_nucl != 0) {
                            fwrite($fCoding, str_repeat(" ", LENGTH_LINE-$l_line_nucl) . substr($sNumr, 3*$n_trns_exon) . " g." . $nGenomicNumber . "\n ");// the last space should be there!
                        }
                    } else {
                        fputs($fCoding, "\n ");
                    }

                    // Writes DNA line
                    if ($sPrntFinl) {
                        fputs($fCoding, $sPrntFinl . str_repeat(" ", LENGTH_LINE-$l_line_nucl) . substr($sNumr, 3*$n_trns_exon) . " c." . $l_prnt);
                    }

                    // Protein line.
                    if ($stop != "done") {
                        fputs($fCoding, "\n ");
                        if ($n_trns_exon) {
                            for ($j=0,$k=$i+1; $j < strlen($sProtShrt); $j++,$k++) {
                                if (in_array($k, $aExonStartPosTransl)) {
                                    fputs($fCoding, " | ");
                                }
                                if (substr($sProtShrt, $j, 1) == "<") {
                                    fputs($fCoding, substr($sProtShrt, $j, 8));
                                    $j += 7;
                                } else {
                                    fputs($fCoding, substr($sProtShrt, $j, 1));
                                }
                            }
                        } else {
                            fputs($fCoding, $sProtShrt);
                        }
                        fputs($fCoding, str_repeat(" ", LENGTH_LINE-$l_line_prot) . substr($sNumr, 3*$n_trns_exon) . " p." . $l_prot);
                    }
                    fputs($fCoding, "\n\n");

                    $stop = ($stop == "stopped" || $stop == "done"? "done" : false);

                    // If we just had a break; some variables need to get changed.
                    if (isset($n_break)) {
                        $n_break = LENGTH_LINE - $n_break;
                        $i -= $n_break;
                        if ($l_prnt{1} == '*') {
                            $l_prnt = (substr($l_prnt, 1) - $n_break);
                        } else {
                            $l_prnt = '*0';
                        }
                        unset($n_break);
                    }
                }

                // 2008-10-30; 2.0-13; by Gerard: added if {} else {}
                // 2009-03-02; 2.0-16; need to know if you came from step 2, if not no link
                if (file_exists($sPath . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_downstream.html') && $bStep2) {
                    fputs($fCoding, ($_POST['link']? ' (<A href="' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_downstream.html">downstream sequence</A>)' . "\n" : '') .
                        "</PRE>\n\n" .
                        ($_POST['legend']?
                            "<SPAN style=\"font-size : 15px;\"><U><B>Legend:</B></U></SPAN><BR>\n" .
                                "Nucleotide numbering (following the rules of the <A href=\"http://www.HGVS.org/mutnomen/\" target=\"_blank\">HGVS</A> for a 'Coding DNA Reference Sequence') is indicated at the right of the sequence, counting the A of the ATG translation initiating Methionine as 1. Every 10<SUP>th</SUP> nucleotide is indicated by a &quot;.&quot; above the sequence. The " . ucfirst($_POST['gene']) . " protein sequence is shown below the coding DNA sequence, with numbering indicated at the right starting with 1 for the translation initiating Methionine. Every 10<SUP>th</SUP> amino acid is shown in bold. The position of introns is indicated by a vertical line, splitting the two exons. The start of the first exon (transcription initiation site) is indicated by a '\', the end of the last exon (poly-A addition site) by a '/'. The exon number is indicated above the first nucleotide(s) of the exon. To aid the description of frame shift mutations, all <B>stop codons in the +1 frame are shown in bold</B> while all <U>stop codons in the +2 frame are underlined</U>.<BR>\n\n" : ""));
                } else {
                    fputs($fCoding, ' (downstream sequence)' . "\n" .
                        "</PRE>\n\n" .
                        ($_POST['legend']?
                            "<SPAN style=\"font-size : 15px;\"><U><B>Legend:</B></U></SPAN><BR>\n" .
                                "Nucleotide numbering (following the rules of the <A href=\"http://www.HGVS.org/mutnomen/\" target=\"_blank\">HGVS</A> for a 'Coding DNA Reference Sequence') is indicated at the right of the sequence, counting the A of the ATG translation initiating Methionine as 1. Every 10<SUP>th</SUP> nucleotide is indicated by a &quot;.&quot; above the sequence. The " . ucfirst($_POST['gene']) . " protein sequence is shown below the coding DNA sequence, with numbering indicated at the right starting with 1 for the translation initiating Methionine. Every 10<SUP>th</SUP> amino acid is shown in bold. The position of introns is indicated by a vertical line, splitting the two exons. The start of the first exon (transcription initiation site) is indicated by a '\', the end of the last exon (poly-A addition site) by a '/'. The exon number is indicated above the first nucleotide(s) of the exon. To aid the description of frame shift mutations, all <B>stop codons in the +1 frame are shown in bold</B> while all <U>stop codons in the +2 frame are underlined</U>.<BR>\n\n" : ""));
                }

                // 2008-10-30; 2.0-13; link to coding sequence added by Gerard
                // 2009-03-17; 2.0-17; link should always be there, not only when the box for providing links to intronic sequences was ticked (Gerard)
                // 2010-01-28; 2.0-24; replaced link to www.dmd.nl by link to www.lovd.nl
                $sOut .= ($sOut? "\n" : '') . 'Successfully wrote coding DNA reference sequence (<A href="refseq/' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_codingDNA.html" target="_blank">' . $_POST['symbol'] . ' coding DNA sequence</A>)' . "\n";
                fputs($fCoding, '<HR>' . "\n" .
                    '<P align="center" style="font-size : 11px;">' . "\n" .
                    '  Powered by <A href="' . $_SETT['upstream_URL'] . $_STAT['tree'] . '/" target="_blank">LOVD v.' . $_STAT['tree'] . '</A> Build ' . $_STAT['build'] . '<BR>' . "\n" .
                    '  &copy;2004-' . date('Y') . ' <A href="http://www.lumc.nl/" target="_blank">Leiden University Medical Center</A>' . "\n" .
                    '</P>' . "\n" .
                    '<HR>' . "\n\n" .
                    '</BODY>' . "\n" .
                    '</HTML>');
                fclose($fCoding);

                // When the reference sequence has been created, put the URL in the database.
                if ($_CONF['location_url']) {
                    $sURL = $_CONF['location_url'] . 'refseq/' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_codingDNA.html';
                } else {
                    $sURL = lovd_getInstallURL() . 'refseq/' . $_POST['symbol'] . '_' . $_POST['transcript_id'] . '_codingDNA.html';
                }
                $_DB->query('UPDATE ' . TABLE_GENES . ' SET refseq = ?, refseq_url = ? WHERE id = ? AND refseq = "" AND refseq_url= ""', array(($_POST['link'] && $bStep2? 'g' : 'c'), $sURL, $_POST['symbol']));

            } else {
                // This really shouldn't happen, as we have checked this already...
                lovd_errorAdd('file', 'Couldn\'t open file to write to for coding DNA reference sequence');
            }

            if (!lovd_error()) {
                print('<SPAN class="S15"><B>Step 3 - Create coding DNA reference sequence</B></SPAN><BR><BR>' . "\n\n");

                print('Output for this step :<BR>' . "\n" . str_replace("\n", '<BR>' . "\n", $sOut) . '<BR><BR>' . "\n\n" .
                      '<BUTTON onclick="self.close();">Close</BUTTON>' . "\n\n");

                $_T->printFooter();
                exit;
            }
        }

    } else {
        // Standard settings.
        if (empty($_POST['exists'])) {
            $_POST['exists'] = 'overwrite';
        }

        $_POST['note'] = '<p>This file was created to facilitate the description of sequence variants' . (empty($_POST['symbol'])? '' : (empty($_POST['transcript_id'])? '' : ' on transcript ' . $_POST['transcript_id']) . ' in the ' . $_POST['symbol'] . ' gene') . ' based on a coding DNA reference sequence following <a href="http://www.HGVS.org/mutnomen/">the HGVS recommendations</a>.</p>';
        $_POST['legend'] = 1;

        // 2009-03-17; 2.0-17; by Gerard: box should only be ticked when coming from step 2 (links to intronic sequences)
        if ($bStep2) {
            $_POST['link'] = 1;
        }
    }

    $_T->printTitle('Step 3 - Create coding DNA reference sequence');

    lovd_errorPrint();

    print('<FORM action="' . $_SERVER['SCRIPT_NAME'] . '?step=3&amp;sent=true" method="post">' . "\n");

    // 2009-02-27; 2.0-16; added by Gerard, need this for the g. numbering
    if (!empty($aLengthsSequenceParts)) {
        print('  <INPUT type="hidden" name="aLengthsSequenceParts" value="' . htmlspecialchars(serialize($aLengthsSequenceParts)) . '">'. "\n");
    }
    // 2009-12-03; 2.0-23; set the version_id if not set by user
    if (!isset($_POST['version_id'])) {
        $_POST['version_id'] = $_SETT['currdb']['refseq_genomic'];
    }

    // 2009-03-09; 2.0-17; need these flags because you want to know if you created the up- and downstream sequences in step 2
    // 2009-06-22; 2.0-19; added symbol which you need if you come from step 2
    // 2009-12-03; 2.0-23; added the accession number, including version
    if ($bStep2) {
        print('  <INPUT type="hidden" name="step2" value="' . $_POST['step2'] . '">' . "\n" .
            '  <INPUT type="hidden" name="symbol" value="' . $_POST['symbol'] . '">' . "\n" .
            '  <INPUT type="hidden" name="version_id" value="' . $_POST['version_id'] . '">' . "\n" .
            '  <INPUT type="hidden" name="transcript_id" value="' . $_POST['transcript_id']. '">' . "\n");
    }


    $aForm   = array();
    $aForm[] = array('POST', '', '', '', '50%', '14', '50%');
    $aForm[] = array('', '', 'print', '(All fields are mandatory unless specified otherwise)');
    $aForm[] = array('Notes above sequence<BR><I>(optional, HTML enabled)</I>', '', 'textarea', 'note', '60', '2');
    $aForm[] = array('Include link to GenBank record in notes above sequence <I>(optional)</I>', '', 'text', 'version_id', '15');
    $aForm[] = array('', '', 'print', '<SPAN class="form_note">If you fill in a GenBank accession.version number, a link to the record at NCBI will be included.</SPAN>');
    $aForm[] = array('Provide links to intronic sequences', '', 'checkbox', 'link', 1);
    $aForm[] = array('Provide legend', '', 'checkbox', 'legend', 1);
    $aForm[] = 'skip';
    $aForm[] = array('Input sequence', '', 'textarea', 'sequence', '60', '8');
//    $aForm[] = array('', '', 'print', '<A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">More information on the format</A>');
    $aForm[] = array('If the file is found to exist, I will', '', 'select', 'exists', 1, array('skip' => 'skip the file', 'rename' => 'rename the old file', 'overwrite' => 'overwrite it'), '', '', '');
    $aForm[] = array('', '', 'submit', 'Continue');
    lovd_viewForm($aForm);
    print('</TABLE><BR>' . "\n\n" .
        '  </FORM>' . "\n\n");

    $_T->printFooter();
    exit;





//////////////////////////////////////////////////////////////////////////////////////////////
} else {
    // Print the form for choosing between the 3 steps:
    // 1) import a GenBank file
    // 2) create intronic sequences
    // 3) create the coding sequence
    $_T->printTitle('LOVD Reference Sequence Parser');
    print('Welcome to the LOVD Reference Sequence parser. With this tool, you can create your own genomic and/or coding DNA reference sequence. These will be written to the \'refseq\' directory.<!-- For more information or troubleshooting, please refer to the <A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">LOVD manual</A>.--><BR><BR>' . "\n\n");

    $aMenu = array(
                    array('Genbank file', 'Import annotated Genbank sequence to extract genomic sequence of your gene of interest for step 2.'),
                    array('Create intronic sequences', 'Parse genomic sequence from step 1 or a sequence you made yourself and create intronic sequences for the genomic reference sequence. Extract coding DNA sequence for step 3.<!--<BR><A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">More information on the format</A>.-->'),
                    array('Create coding DNA reference sequence', 'Parse coding DNA sequence from step 2 or a sequence you made yourself and create coding DNA sequence for the coding DNA reference sequence.<!--<BR><A href="docs/lovd_scripts/reference_sequence_parser.php" target="_blank">More information on the format</A>.-->'),
                   );
    foreach ($aMenu as $n_step => $a_print) {
        $n_step ++;
        print('<TABLE border="0" cellpadding="2" cellspacing="0" width="725" style="border : 1px solid #c0c0c0;">' . "\n" .
              '  <TR>' . "\n" .
              '    <TD valign="top" align="center" width="40"><IMG src="gfx/lovd_' . $n_step . '.png" alt="Step ' . $n_step . '" width="32" height="32" hspace="4" vspace="4"></TD>' . "\n" .
              '    <TD valign="middle"><SPAN class="S15">' .
            ($n_step != 1? '' : '<A href="' . $_SERVER['SCRIPT_NAME'] . '?step=' . $n_step . '">') .
            '<B>Step ' . $n_step . ' - ' . $a_print[0] . '</B>' .
            ($n_step != 1? '' : '</A>') .
            '</SPAN><BR>' . "\n" .
              '      ' . $a_print[1] . '</TD></TR></TABLE><BR>' . "\n\n");
    }
            
    $_T->printFooter();
    exit;
}
?>
