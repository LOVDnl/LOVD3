#!/usr/bin/php
<?php
date_default_timezone_set('Europe/Amsterdam');
/*******************************************************************************
 *
 * LOVD scripts: Gene Loader
 *
 * (based on load_HGNC_data.php, created 2013-02-13, last modified 2015-10-08)
 * Created     : 2016-02-22
 * Modified    : 2016-03-15
 * Version     : 0.3
 * For LOVD    : 3.0-15
 *
 * Purpose     : To help the user automatically load a large number of genes
 *               into LOVD3, together with the desired transcripts, and
 *               optionally, the diseases (to be implemented).
 *               This script retrieves the list of genes from the HGNC and
 *               creates an LOVD3 import file format with the gene and
 *               transcript information. It checks on LOVD.nl whether or not to
 *               use LRG, NG or NC. It also queries Mutalyzer for the reference
 *               transcript's information, and puts these in the file, too.
 *
 * Changelog   : 0.3    2016-03-15
 *               Added the option to import OMIM disease data.
 *               0.2b   2016-02-26
 *               Genes in "bad" locus groups and types are no longer added to
 *               the list of genes to ignore, because it's hard to remove them
 *               later from it, and we're not getting a speed gain from putting
 *               them in that list.
 *               0.2    2016-02-26
 *               Removed locus type "unknown" from the list of locus types to be ignored.
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
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

// FIXME: Chromosome band is not getting imported.
// FIXME: OMIM ID is being set to 0 when empty.

if (isset($_SERVER['HTTP_HOST'])) {
    die('Please run this script through the command line.' . "\n");
}

$_CONFIG = array( //T: Config, wordt vaak aangesproken in script (Is een multidimension-array? - Bevat bad_locus, user, HGNC_columns, LOVD_gene_columns, LOVD_transcript_columns)
    'version' => '0.3',
    'hgnc_file' => 'HGNC_download.txt', //T: Is niet te gebruiken voor hond 
    'hgnc_base_url' => 'http://www.genenames.org/cgi-bin/download',
    'hgnc_col_var_name' => 'col',
    'hgnc_other_vars' => 'status=Approved&status_opt=2&where=&order_by=gd_app_sym_sort&format=text&limit=&submit=submit',
    // We ignore genes from the following locus groups:
    'bad_locus_groups' => array(
        'phenotype', // No transcripts.
        'withdrawn', // Do not exist anymore.
    ),
    // We ignore genes from the following locus types (most of these are in group "other"):
    'bad_locus_types' => array(
        'endogenous retrovirus',  // From group "other", none of them work (verified).
        'fragile site',           // From group "other", none of them work (verified).
        'immunoglobulin gene',    // From group "other", none of them work (verified).
        'region',                 // From group "other", none of them work (verified).
        'transposable element',   // From group "other", none of them work (verified).
        // LOVD actually allows this group since Jan 2015.
        // 'unknown',                // From group "other", none of them work (verified).
        'virus integration site', // From group "other", none of them work (verified).
        'immunoglobulin pseudogene', // From group "pseudogene", none of them work (verified).
    ),
    'user' => array(
        // Variables we will be asking the user.
        'lovd_path' => '/Applications/XAMPP/htdocs/LOVDv.3.0',
        'update_hgnc' => 'n',
        'gene_list' => 'all',
        'transcript_list' => 'best',
        'genes_to_ignore' => 'genes_to_ignore.txt',
        'omim_data' => 'morbidmap.txt',
    ),
    'hgnc_columns' => array(                                                                              //T: Bestaat niet voor de hond
        'gd_hgnc_id' => 'HGNC ID',                                                                        //T: Bestaat niet voor niet-mens : Vervangen door entrez gene
        'gd_app_sym' => 'Approved Symbol',                                                                //T: Naam = Gensymbool - Wordt gebruik bij aanmaken genen
        'gd_app_name' => 'Approved Name',
        'gd_locus_type' => 'Locus Type',
        'gd_locus_group' => 'Locus Group',
        'gd_pub_chrom_map' => 'Chromosome',
        'gd_pub_eg_id' => 'Entrez Gene ID', // Curated by the HGNC, not the other one.
        'gd_pub_refseq_ids' => 'RefSeq IDs', // Curated by the HGNC.
        'md_mim_id' => 'OMIM ID(supplied by OMIM)',
        'md_refseq_id' => 'RefSeq(supplied by NCBI)', // Downloaded from external sources.
    ),
    'lovd_gene_columns' => array(
        'id' => 'gd_app_sym',
        'name' => 'gd_app_name',
        'chromosome' => '',     // Will be filled in later.
        'chrom_band' => '',     // Will be filled in later.
        'refseq_genomic' => '', // Will be filled in later.
        'refseq_UD' => '',      // Deze moet nu gevuld worden om met JSON data te verkrijgen van mutalyzer, beter om deze te verkrijgen uit de hgnc_clumns
        'id_hgnc' => 'gd_hgnc_id',
        'id_entrez' => 'gd_pub_eg_id',
        'id_omim' => 'md_mim_id',
        'created_by' => '',     // Will be filled in later.
        'created_date' => '',   // Will be filled in later.
    ),
    'lovd_transcript_columns' => array(
        'geneid',
        'name',
        'id_mutalyzer',
        'id_ncbi',
        'id_protein_ncbi',
        'position_c_mrna_start',
        'position_c_mrna_end',
        'position_c_cds_end',
        'position_g_mrna_start',
        'position_g_mrna_end',
        'created_by',
        'created_date',
    ),
    // Column headers for the OMIM disease file morbidmap.txt. T: Onnodig, maken voor nu geen gebruik OMIM/OMIA
    'omim_columns' => array(
        'disease' => 'Phenotype',
        'genes' => 'Gene Symbols',
        'mim' => 'MIM Number',
        'cyto_location' => 'Cyto Location',
    ),
);





function lovd_verifySettings ($sKeyName, $sMessage, $sVerifyType, $options)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Check if settings match certain input.
    global $_CONFIG;

    switch($sVerifyType) {
        case 'array':
            $aOptions = $options;
            if (!is_array($aOptions)) {
                return false;
            }
            break;

        case 'int':
            // Integer, options define a range in the format '1,3' (1 to 3) or '1,' (1 or higher).
            $aRange = explode(',', $options);
            if (!is_array($aRange) ||
                ($aRange[0] === '' && $aRange[1] === '') ||
                ($aRange[0] !== '' && !ctype_digit($aRange[0])) ||
                ($aRange[1] !== '' && !ctype_digit($aRange[1]))) {
                return false;
            }
            break;
    }

    while (true) {
        print('  ' . $sMessage .
            (empty($_CONFIG['user'][$sKeyName])? '' : ' [' . $_CONFIG['user'][$sKeyName] . ']') . ' : ');
        $sInput = trim(fgets(STDIN));
        if (!strlen($sInput) && !empty($_CONFIG['user'][$sKeyName])) {
            $sInput = $_CONFIG['user'][$sKeyName];
        }

        switch ($sVerifyType) {
            case 'array':
                $sInput = strtolower($sInput);
                if (in_array($sInput, $aOptions)) {
                    $_CONFIG['user'][$sKeyName] = $sInput;
                    return true;
                }
                break;

            case 'int':
                $sInput = (int) $sInput;
                // Check if input is lower than minimum required value (if configured).
                if ($aRange[0] !== '' && $sInput < $aRange[0]) {
                    break;
                }
                // Check if input is higher than maximum required value (if configured).
                if ($aRange[1] !== '' && $sInput > $aRange[1]) {
                    break;
                }
                $_SETT[$sKeyName] = $sInput;
                return true;

            case 'file':
            case 'lovd_path':
            case 'path':
                // Always accept the default (if non-empty) or the given options.
                if (($sInput && ($sInput == $_CONFIG['user'][$sKeyName] ||
                        $sInput === $options)) ||
                    (is_array($options) && in_array($sInput, $options))) {
                    $_CONFIG['user'][$sKeyName] = $sInput; // In case an option was chosen that was not the default.
                    return true;
                }
                if (in_array($sVerifyType, array('lovd_path', 'path')) && !is_dir($sInput)) {
                    print('    Given path is not a directory.' . "\n");
                    break;
                } elseif (!is_readable($sInput)) {
                    print('    Cannot read given path.' . "\n");
                    break;
                }

                if ($sVerifyType == 'lovd_path') {
                    if (!file_exists($sInput . '/config.ini.php')) {
                        if (file_exists($sInput . '/src/config.ini.php')) {
                            $sInput .= '/src';
                        } else {
                            print('    Cannot locate config.ini.php in given path.' . "\n" .
                                  '    Please check that the given path is a correct path to an LOVD installation.' . "\n");
                            break;
                        }
                    }
                    if (!is_readable($sInput . '/config.ini.php')) {
                        print('    Cannot read configuration file in given LOVD directory.' . "\n");
                        break;
                    }
                    // We'll set everything up later, because we don't want to
                    // keep the $_DB open for as long as the user is answering questions.
                }
                $_CONFIG['user'][$sKeyName] = $sInput;
                return true;
            case 'data_file': //Instead of using HGNC, use a datafile
		if ($sVerifyType == 'data_file') { 
		    if (is_readable($sInput. '/Test.csv')) { //Checks wether the "Test.txt" file is in the specified path
		        print('    File exists in directory and is readable' . "\n"); //File is found
		    } else {
		        print('    File does not exist in directory or is not readable' . "\n"); //File is not found
			break;
		    }
		}
		$_CONFIG['user'][$sKeyName] = $sInput;
		return true;

            default:
                return false;
        }
    }
    return false; // We'd actually never get here.
}





// Obviously, we could be running for quite some time.
set_time_limit(0);





print('Gene Loader v' . $_CONFIG['version'] . '.' . "\n");

//T: HGNC download en verificatie hoeven niet in geval van file.
// Verify settings with user.

// T: Is stuk code voor later, checkt of de map met data bestaat. (Maakt gebruik van verifySettings functie
//if (!lovd_verifySettings('data_path', 'Path of directory for file containing data to upload', 'data_file', '')) {
//    die('  Failed to get Datafile path.' . "\n");
//}

//$aHGNCFile = file($_CONFIG['hgnc_file'], FILE_IGNORE_NEW_LINES); T: PrintTest wijst uit dat dit de gehele ingelade file is
//print_r($aHGNCFile);
//print('Hier gebeurd wat');

if (!lovd_verifySettings('lovd_path', 'Path of LOVD installation to load data into', 'lovd_path', '')) {
    die('  Failed to get LOVD path.' . "\n");
}
lovd_verifySettings('update_hgnc', 'Download new HGNC data if file already available? (Yes/No)', 'array', array('yes', 'no', 'y', 'n'));
lovd_verifySettings('gene_list', 'File containing the gene symbols that you want created,
    or just press enter to create all genes', 'file', '');
lovd_verifySettings('transcript_list', 'File containing the transcripts that you want created,
    type \'best\' to have best transcripts created,
    or just press enter to let LOVD pick the best transcript per gene', 'file', array('all', 'best'));
lovd_verifySettings('genes_to_ignore', 'File that we can read and write to, containing gene symbols
    to ignore to speed up consecutive runs', 'file', '');

/* T: OMIM/OMIA is secundair, niet belangrijk voor nu
lovd_verifySettings('omim_data', 'File containing the OMIM disease data,
    otherwise type \'n\' to not import OMIM data,
    or just press enter to use the default file name', 'file', array('n'));
*/


// Check gene and transcript files and file formats.
$aGenesToCreate = $aTranscriptsToCreate = array();

// Gene list. T: Huidige gen lijst doorloopt dit prima
if (is_readable($_CONFIG['user']['gene_list'])) {
    // Gene list argument is a file.
    $aFile = file($_CONFIG['user']['gene_list']);
    // Loop through the file to check it.
    foreach ($aFile as $nLine => $sLine) {
        $sLine = trim($sLine);
        if (!$sLine || $sLine{0} == '#') {
            continue;
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9_@-]+$/', $sLine)) {
            $nLine ++;
            die('  Can not read gene list file on line ' . $nLine . ', not a valid gene symbol format.' . "\n"); //T: Mogelijk aanpassen, HGNC versus Refseq
        }
        $aGenesToCreate[$sLine] = 1; // Using genes as keys speeds up the lookup process a lot.
    }
    print('  Read ' . count($aGenesToCreate) . ' genes to create.' . "\n");
}
// Transcript list. T: Huidige transcriptlijst doorloopt dit prima
if (is_readable($_CONFIG['user']['transcript_list'])) {
    // Gene list argument is a file.
    $aFile = file($_CONFIG['user']['transcript_list']);
    print_r("Hierzo ook");
    // Loop through the file to check it.
    foreach ($aFile as $nLine => $sLine) {
        $sLine = trim($sLine);
        if (!$sLine || $sLine{0} == '#') {
            continue;
        }
        if (!preg_match('/^[NX][MR]_[0-9]{6,9}(\.[0-9]+)?$/', $sLine)) {
            $nLine ++;
            die('  Can not read transcript list file on line ' . $nLine . ', not a valid transcript format.' . "\n");
        }
        @list($sIDWithoutVersion, $nVersion) = explode('.', $sLine); // Version is not mandatory.
        if (!isset($aTranscriptsToCreate[$sIDWithoutVersion])) {
            $aTranscriptsToCreate[$sIDWithoutVersion] = array();
        }
        $aTranscriptsToCreate[$sIDWithoutVersion][] = $nVersion;
    }
    print('  Read ' . count($aTranscriptsToCreate) . ' transcripts to create.' . "\n");
}

// Secundaire aanpak, Huidige aanpak: HGNC_file nabootsen
// T: Block code om file uit te lezen en aan goede kolommen te linken.
//$csv = array_map('str_getcsv', file('Data/Test.csv'));
//print_r(array_values($csv)); T: Testpurpose - print de array die gemaakt is uit de csv file
//
//


// PT: Dit kan dus niet, er is geen HGNC data voor de hond
// Download HGNC data first. We might not be able to send the full query to the HGNC,
//  so better just download the whole thing and loop through it.
// In case it already exists, check if we need to download it again.
if (!file_exists($_CONFIG['hgnc_file']) || in_array($_CONFIG['user']['update_hgnc'], array('y', 'yes'))) {
    // Construct link.
    $sURL = $_CONFIG['hgnc_base_url'] . '?' . $_CONFIG['hgnc_col_var_name'] . '=' . implode('&' . $_CONFIG['hgnc_col_var_name'] . '=', array_keys($_CONFIG['hgnc_columns'])) . '&' . $_CONFIG['hgnc_other_vars'];
    $f = fopen($_CONFIG['hgnc_file'], 'w');
    if ($f === false) {
        die('  Could not create new HGNC data file.' . "\n");
    }
    print('  Downloading HGNC data... ');
    $sHGNCData = @file_get_contents($sURL);
    if (!$sHGNCData) {
        die('Failed.
    Could not download HGNC data. URL used:
      ' . $sURL . "\n");
    }
    if (!fputs($f, $sHGNCData)) {
        die('Failed.
    Could not write to HGNC data file.' . "\n");
    }
    print('OK!' . "\n");
}
print("\n");


// T: Start connectie met de database, 
// Find LOVD installation, run it's inc-init.php to get DB connection, initiate $_SETT, etc.
define('ROOT_PATH', $_CONFIG['user']['lovd_path'] . '/');
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/' . basename(__FILE__),
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));
// If I put a require here, I can't nicely handle errors, because PHP will die if something is wrong.
// However, I need to get rid of the "headers already sent" warnings from inc-init.php.
// So, sadly if there is a problem connecting to LOVD, the script will die here without any output whatsoever.
ini_set('display_errors', '0');
ini_set('log_errors', '0'); // CLI logs errors to the screen, apparently.
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';   // For lovd_getUDForGene().
require ROOT_PATH . 'inc-lib-actions.php'; // For lovd_addAllDefaultCustomColumns().
ini_set('display_errors', '1'); // We do want to see errors from here on.


// T: Onmogelijk, data moet uit datafile komen ipv. HGNC. -NODIG
// Start checking and reading out the HGNC file.
print('  Reading HGNC data...' . "\n");

$aHGNCFile = file($_CONFIG['hgnc_file'], FILE_IGNORE_NEW_LINES);
if ($aHGNCFile === false) {
    die('  Could not open the HGNC data file.
    Please check the file\'s permissions and try again.' . "\n");
} else {
    print('  Checking file header... ');
}

// We need very selective info from the HGNC.
// Check if all the columns we need are there, and check the order in which they appear.
$aColumnsInFile = explode("\t", $aHGNCFile[0]);
$aHGNCColumns = array(); // array(0 => 'gd_hgnc_id', 1 => 'gd_app_sym', ...); T: 0 = HGNC_ID (mag nu null zijn), 1 = gensymbool - NODIG/VERPLICHT
// Determine the correct keys for the columns we want.
foreach ($aColumnsInFile as $nKey => $sName) {
    if ($sCol = array_search($sName, $_CONFIG['hgnc_columns'])) {
        // We need this column!
        $aHGNCColumns[$nKey] = $sCol; //$sCol wordt geinsert in de database
    }
}

if (count($aHGNCColumns) < count($_CONFIG['hgnc_columns'])) { //T: Als er minder dan count van de kolumns uit de config file worden gevonden - Stop programma
    // We didn't find all needed columns!
    die('Failed.
    Could not find all needed columns, please check the file\'s format,
      or redownload the file.' . "\n");
} else {
    print('OK!' . "\n");
    unset($aHGNCFile[0]);
}
$nHGNCGenes = count($aHGNCFile);

/*

// T: Niet persee nodig nu (OMIM/OMIA related)
// Check if we can open the OMIM data file.
if ($_CONFIG['user']['omim_data'] != 'n') {
    // At this time, the file may not exist, because we never checked for its existance.
    // FIXME: How would be accept the option "n" which is not a file,
    //  and at the same time, check even the default value of this config option if passed?
    print('  Reading OMIM data...' . "\n");
    $aOMIMColumns = array();
    $aOMIMFile = @file($_CONFIG['user']['omim_data'], FILE_IGNORE_NEW_LINES);
    if ($aOMIMFile === false) {
        if (file_exists($_CONFIG['user']['omim_data'])) {
            die('  Could not open the OMIM data file.
    Please check the file\'s permissions and try again.' . "\n");
        } else {
            die('  Could not open the OMIM data file.
    You can download this file from the OMIM website:
      http://www.omim.org/downloads' . "\n");
        }
    } else {
        print('  Checking OMIM file header... ');
    }
    // Validate the OMIM file format is correct by finding the header and confirming it contains the required columns.
    foreach ($aOMIMFile as $nLine => $sLine) {
        $sLine = trim($sLine);
        if (!$sLine) {
            continue;
        } elseif ($sLine{0} == '#') {
            // This is a comment line so search for the header.
            $sLine = trim(substr($sLine, 1)); // Removes "#" and any spaces from the start of the line.
            $aOMIMHeader = explode("\t", $sLine);
            if (count($aOMIMHeader) > 1) {
                // We are assuming this is the column header line as it should be the only comment line
                //  with tabs in it, check to see if it contains all the columns that we need.
                $aMissingOMIMCols = array_diff($_CONFIG['omim_columns'], $aOMIMHeader);
                if (!$aMissingOMIMCols) {
                    // All the columns have been found, so build our known column array and continue.
                    print('OK!' . "\n");
                    foreach ($aOMIMHeader as $nKey => $sName) {
                        if ($sCol = array_search($sName, $_CONFIG['omim_columns'])) {
                            // We need this column!
                            $aOMIMColumns[$nKey] = $sCol;
                        }
                    }
                    // No need to continue if we have found the header.
                    break;
                } else {
                    die('Failed.
    The header line in the OMIM data file was found,
      but it was missing required column(s):
      "' . implode('", " ', $aMissingOMIMCols) . '".
    You can download this file from the OMIM website:
      http://www.omim.org/downloads
    If you\'re sure this is the correct file, apparently the file
      format changed. Please report this as a bug here:
      https://github.com/LOVDnl/geneloader/issues
      and include the file header so we can handle new file formats.' . "\n");
                }
            }
        } else {
            // We could not identify the header line so we can not continue.
            die('Failed.
    Could not find the header line in the OMIM data file.
    You can download this file from the OMIM website:
      http://www.omim.org/downloads' . "\n");
        }
    }
}

*/

/* T: Bestaat niet voor de hond

print('  Retrieving additional resources... ');
$aLRGs = array();
$aNGs = array();
// Get list of LRGs and NGs to determine the genomic refseq of the genes. PT: Bestaan niet voor de hond
$aLRGFile = lovd_php_file('http://www.lovd.nl/mirrors/lrg/LRG_list.txt');
unset($aLRGFile[0], $aLRGFile[1]);
foreach ($aLRGFile as $sLine) {
    $aLine = explode("\t", $sLine);
    $aLRGs[$aLine[1]] = $aLine[0];
}
$aNGFile = lovd_php_file('http://www.lovd.nl/mirrors/ncbi/NG_list.txt');
unset($aNGFile[0], $aNGFile[1]);
foreach ($aNGFile as $sLine) {
    $aLine = explode("\t", $sLine);
    $aNGs[$aLine[0]] = $aLine[1];
}
if (!count($aLRGs) || !count($aNGs)) {
    die('Failed!
    Could not retrieve LRG and NG resources.' . "\n");
} else {
    print('OK!
  Resources stored, loading ignore list... ');
}

*/

// See if we can file genes to ignore.
$aGenesToIgnore = array();
$bGenesToIgnoreIsEmpty = false; // If it's empty, we'll write an informative header.
$bWroteToGenesFile = false; // The first gene we write there, will be a header with the current date.
if (file_exists($_CONFIG['user']['genes_to_ignore'])) {
    if (!is_readable($_CONFIG['user']['genes_to_ignore'])) {
        die('present, but can not open the file.
    Please check the file\'s permissions and try again.' . "\n");
    } else {
        $aFile = file($_CONFIG['user']['genes_to_ignore']);
        if (!$aFile) {
            $bGenesToIgnoreIsEmpty = true;
        }
        // Loop through the file to check it.
        foreach ($aFile as $nLine => $sLine) {
            $sLine = trim($sLine);
            if (!$sLine || $sLine{0} == '#') {
                continue;
            }
            $aGenesToIgnore[$sLine] = 1; // Using genes as keys speeds up the lookup process a lot.
        }
        print('OK!' . "\n");
    }
} else {
    print('None exists yet.' . "\n");
}
print('  Preparing gene ignore list for appending... ');
$fGenesToIgnore = fopen($_CONFIG['user']['genes_to_ignore'], 'a');
if ($fGenesToIgnore === false) {
    die('Failed!
    Could not append to file, please check its permissions.' . "\n");
} else {
    print('OK!
  Starting the run...' . "\n\n");
}

// Append header to $fGenesToIgnore, if empty.
if ($bGenesToIgnoreIsEmpty) {
    fputs($fGenesToIgnore,
        '# These genes were ignored, because no reference sequence or transcripts could be found.' . "\r\n" .
        '# Keeping this list speeds up the process a lot.' . "\r\n");
}


// Prepare for running queries.
$aGenesInLOVD = $_DB->query('SELECT id, refseq_UD FROM ' . TABLE_GENES)->fetchAllCombine();
$sSQL = 'INSERT INTO ' . TABLE_GENES . ' (';
foreach (array_keys($_CONFIG['lovd_gene_columns']) as $nKey => $sField) {
    $sSQL .= (!$nKey? '' : ', ') . '`' . $sField . '`';
}
$sSQL .= ') VALUES (?' . str_repeat(', ?', count($_CONFIG['lovd_gene_columns']) - 1) . ')';
$qGenes = $_DB->prepare($sSQL);



// T: Testcase Laat de opgebouwde SQL statement zien
//print($sSQL);
//print_r($_CONFIG); //T: Testprint
$aTranscriptsInLOVD = $_DB->query('SELECT SUBSTRING_INDEX(id_ncbi, ".", 1) AS IDWithoutVersion, GROUP_CONCAT(RIGHT(id_ncbi, LENGTH(id_ncbi) - LOCATE(".", id_ncbi)) SEPARATOR ";") AS versions FROM ' . TABLE_TRANSCRIPTS . ' GROUP BY IDWithoutVersion')->fetchAllCombine();
$sSQL = 'INSERT INTO ' . TABLE_TRANSCRIPTS . ' (';
foreach ($_CONFIG['lovd_transcript_columns'] as $nKey => $sField) {
    $sSQL .= (!$nKey? '' : ', ') . '`' . $sField . '`';
}
$sSQL .= ') VALUES (?' . str_repeat(', ?', count($_CONFIG['lovd_transcript_columns']) - 1) . ')';
$qTranscripts = $_DB->prepare($sSQL);
//print_r($qTranscripts);






// We're going to track some times, to see how much time we're spending using web resources.
$tStart = microtime(true);
$nGenes = 0;
$nTimeSpentGettingUDs = 0;
$nUDsRequested = 0;
$nTimeSpentGettingTranscripts = 0;
$nTranscriptsRequested = 0;
$nGenesCreated = 0;
$nTranscriptsCreated = 0;




//print($sSQL);

// Loop through the data and write to the database when needed.
$nGenesPerDot = 2;
if ($aGenesToCreate) {
    // The user requested a list of genes. Then we'll probably skip
    // through a lot of genes, so we'll increase $nGenesPerDot.
    //print('GenesToCreate count getal ' . count($aGenesToCreate). "\n"); //=23110
    //print('HGNCFile count getal ' . count($aHGNCFile). "\n"); //=2490
    $nPercentage = count($aGenesToCreate) / count($aHGNCFile);
    $nGenesPerDot = round($nGenesPerDot/$nPercentage);
}

$nDotsPerLine = 50;
foreach ($aHGNCFile as $nLine => $sLine) { // T: Leest de HGNC file regel per regel uit
    //print($sLine);
    // Write some statistics now and then, while we're waiting.
    // This is put on top of the loop, so that any continue calls used
    // below don't stop the script from making output now and then).
    if ($nGenes) {
	//print($nGenesPerDot); //Division by zero source
        if (!($nGenes % $nGenesPerDot)) {
            //print('.');
            flush();
        }
	//print($nGenesPerDot * $nDotsPerLine); //Division by zero source
        if (!($nGenes % ($nGenesPerDot * $nDotsPerLine))) {
            $nTimeSpent = microtime(true) - $tStart;
            $nTimeLeft = ($nHGNCGenes * $nTimeSpent / $nGenes) - $nTimeSpent;
            
            print("\n" .
                date('c') . "\n" .
                'Completed ' . $nGenes . ' genes (' . round(100 * $nGenes / $nHGNCGenes) . '%) in ' . round($nTimeSpent, 1) . ' seconds (' . round($nTimeSpent/$nGenes, 2) . 's/gene); ETC is ' . round($nTimeLeft) . 's (' . date('c', ($nTimeLeft+time())) . ').' . "\n" .
                '    Requested ' . $nUDsRequested . ' UDs' . (!$nUDsRequested? '' : ', taking ' . round($nTimeSpentGettingUDs, 1) . ' seconds (' . round($nTimeSpentGettingUDs/$nUDsRequested, 2) . 's/UD)') . "\n" .
                '    Requested transcript info for ' . $nTranscriptsRequested . ' UDs' . (!$nTranscriptsRequested? '' : ', taking ' . round($nTimeSpentGettingTranscripts, 1) . ' seconds (' . round($nTimeSpentGettingTranscripts/$nTranscriptsRequested, 2) . 's/UD)') . "\n" .
                '    Created ' . $nGenesCreated . ' gene' . ($nGenes == 1? '' : 's') . ' and ' . $nTranscriptsCreated . ' transcript' . ($nTranscriptsCreated == 1? '' : 's') . "\n");
        }
    }
    $nGenes ++;
    $aLineExplode = explode("\t", $sLine);
    $aLine = array();
    // The order in which the $aGene variables are stored, is important for the query that is run.
    // So we initialize the array with the same template that has fed the prepare().
    $aGene = array_combine(array_keys($_CONFIG['lovd_gene_columns']), array_fill(0, count($_CONFIG['lovd_gene_columns']), ''));
    foreach ($aHGNCColumns as $nKey => $sName) {
        $aLine[$sName] = $aLineExplode[$nKey];
        //print($nKey. "\n");
    }
    //print($aLineExplode[$nKey]);
    //print($aLine[$sName]);
    //Later pas erbij gezet
    $nGenes ++;
    $bLineExplode = explode("\t", $sLine);
    $bLine = array();
    // The order in which the $aGene variables are stored, is important for the query that is run.
    // So we initialize the array with the same template that has fed the prepare().
    $bGene = array_combine(array_keys($_CONFIG['lovd_transcript_columns']), array_fill(0, count($_CONFIG['lovd_transcript_columns']), ''));
    foreach ($aHGNCColumns as $nKey => $sName) {
        $bLine[$sName] = $bLineExplode[$nKey];
    }
    
    // Parse the HGNG's transcripts.
    $aTranscripts = array();
    $aTranscriptsFromHGNC = array();
    // Currently HGVS is splitting on ' ,', but this is more flexible.
    if ($aLine['gd_pub_refseq_ids']) {
        $aTranscripts = preg_split('/\s?[,;]\s?/', $aLine['gd_pub_refseq_ids']);
    }
    // Add the RefSeq provided by the HGNC that they got from somewhere else.
    // Could also be NGs etc, but never more than one.
    if ($aLine['md_refseq_id']) {
        $aTranscripts[] = $aLine['md_refseq_id'];
    }
    foreach ($aTranscripts as $sTranscriptID) {
        // HGNC doesn't often use versions in the transcripts they have stored, but sometimes they do.
        // We're currently ignoring any version number given by HGNC.
        $sIDWithoutVersion = preg_replace('/\.\d+$/', '', $sTranscriptID);
        $aTranscriptsFromHGNC[] = $sIDWithoutVersion;
    }

    // We'll silently ignore this gene in the following cases:
    // If the gene was specifically set to be ignored,
    // If we are using a gene list, and the gene is not in there,
    // If we already have the gene, we only want the best transcript, and we already have it (ignoring version).
    //print($aLine['gd_app_sym']. "\t");
    //print($aLine['gd_pub_refseq_ids']. "\n");
    // Testisterug T: Skip test
    if (isset($aGenesToIgnore[$aLine['gd_app_sym']]) ||
        ($aGenesToCreate && !isset($aGenesToCreate[$aLine['gd_app_sym']])) || // T: Hier knalt hij onze genen eruit
        (isset($aGenesInLOVD[$aLine['gd_app_sym']]) && $_CONFIG['user']['transcript_list'] == 'best' &&
            isset($aTranscriptsInLOVD[$aTranscriptsFromHGNC[0]]))) {
        continue;
    }
    // allesteken

    // By default, handle all the genes. If this is set to true, the gene will be added to our ignore list.
    $bIgnoreGene = false;

    // Ignore genes from the bad locus groups.
    if (in_array($aLine['gd_locus_group'], $_CONFIG['bad_locus_groups'])) {
        // Ignore them without logging them. Otherwise, it's very difficult to
        // add those genes later if the list of locus groups changes, and we're
        // not getting a speed optimization out of it, either.
        // If you want these genes logged again, just set $bIgnoreGene to true.
        continue;
    }
    // Ignore genes from the bad locus types.
    if (in_array($aLine['gd_locus_type'], $_CONFIG['bad_locus_types'])) {
        // Ignore them without logging them. Otherwise, it's very difficult to
        // add those genes later if the list of locus types changes, and we're
        // not getting a speed optimization out of it, either.
        // If you want these genes logged again, just set $bIgnoreGene to true.
        continue;
    }

    // Prepare chromosome fields.
    if ($aLine['gd_pub_chrom_map'] == 'mitochondria') {
        $aGene['chromosome'] = 'M';
        $sChromBand = '';
	//Aangepast: regex in preg_match voor hond nu (y chrom ontbreekt wel in CanFam3.1)
    } elseif (preg_match('/^chr(\d{1,2}|[XYM])/', $aLine['gd_pub_chrom_map'], $aMatches)) {
    //elseif (preg_match('/^(\d{1,2}|[XY])(.*)$/', $aLine['gd_pub_chrom_map'], $aMatches)) {
        $aGene['chromosome'] = $aMatches[1]; //Chromosome is nu 10 bijv. Bij $aMatches[0] wordt chr+ het cijfer (zonder +) geselecteerd.
	//print($aGene['chromosome']); T: Testprint
	//$aGene['chromosome'] = $aMatches[1]; //Is een cijfer 
        $sChromBand = ''; //Hebben we niet voor de hond dus maak hem maar leeg
	//$sChromBand = $aMatches[2];

    } else {
	print("\n" . 'CATCH - GENE SILENTLY IGNORED');
        // Silently ignore genes on weird chromosomes. Ivo - Soms zijn er genen die gemapped zijn op verkeerde (weird) chromosomen.
        //continue;
    }

    // Genomic RefSeq...
    if (isset($aLRGs[$aLine['gd_app_sym']])) {
        $aGene['refseq_genomic'] = $aLRGs[$aLine['gd_app_sym']];
    } elseif (isset($aNGs[$aLine['gd_app_sym']])) {
        $aGene['refseq_genomic'] = $aNGs[$aLine['gd_app_sym']];
    } else {
        $aGene['refseq_genomic'] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aGene['chromosome']]; //Undefined NCBI_sequences?
    }
	// T: Aangezet 3 regels ^

    // UD... But we won't request it, if we already have it!
    if (!$bIgnoreGene && isset($aGenesInLOVD[$aLine['gd_app_sym']])) {
        $aGene['refseq_UD'] = $aGenesInLOVD[$aLine['gd_app_sym']];
    } else {
        $aGene['refseq_UD'] = ''; // Gene not seen before, try and fetch.
    }

    if (!$bIgnoreGene && !$aGene['refseq_UD']) {
        // We deliberately don't check if we already have the gene.
        // If we have the gene, but it's not (or no longer) in the ignore list,
        // we'll simply try again to get the UD. If that fails, the gene will get into the ignore list anyway.

	$t = microtime(true);
	//refseq_build moet van de hond zijn, anders worde verkeerde UD's aangevraagd.
        $aGene['refseq_UD'] = lovd_getUDForGene($_CONF['refseq_build'], $aLine['gd_app_sym']);
        $nTimeSpentGettingUDs += (microtime(true) - $t);
        $nUDsRequested ++;
    }



    // Now load transcripts and see what we've got.

    $aTranscriptsInUD = array();
    if ($aLine['gd_pub_refseq_ids']) {
        $t = microtime(true);
        $sJSONResponse = @implode('', file(str_replace('/services', '', $_CONF['mutalyzer_soap_url']) . '/json/getTranscriptsAndInfo?genomicReference=' . $aLine['gd_pub_refseq_ids'] . '&geneName=' . $aLine['gd_app_sym']));
        //print($aLine['gd_app_sym']. "\t");
        //print($aLine['gd_pub_refseq_ids']. "\t");
        //print($bGene['name']. "\t");
        //print($aGene[0]. "\t");
        //print_r($aTranscriptsInUD[$sIDWithoutVersion][$nVersion]['geneid']. "\n");
        
        //print($sJSONResponse);
        $nTimeSpentGettingTranscripts += (microtime(true) - $t);
        $nTranscriptsRequested++;
        if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
            $aAvailableTranscripts = $aResponse;
            //print_r($sJSONResponse);
            foreach ($aAvailableTranscripts as $aAvailableTranscript) {
                if ($aAvailableTranscript['id']) { // Is this check needed? Copied from genes.php.
                    list($sIDWithoutVersion, $nVersion) = explode('.', $aAvailableTranscript['id']);
                    // We create a nested array like this, because possibly, we'll see two versions of one transcript.
                    print_r($aAvailableTranscript);
                    print_r($aAvailableTranscript['name']. "\t");
                    print_r($aAvailableTranscript['exons']['0']['gStart']. "\t");
                    print_r($aAvailableTranscript['exons']['0']['gStop']. "\t");
                    print_r($aAvailableTranscript['proteinTranscript']['product']. "\n");
                    $aTranscriptsInUD[$sIDWithoutVersion][$nVersion] =
                        array(
                            'geneid' => $aLine['gd_app_sym'],
                            'name' => (!$aAvailableTranscript['proteinTranscript']['product']? '' : $aAvailableTranscript['proteinTranscript']['product']),
                            //'name' => str_replace($aLine['gd_app_name'] . ', ', '', $aAvailableTranscript['proteinTranscript']['product']),
                            'id_mutalyzer' => str_replace($aLine['gd_app_sym'] . '_v', '', $aAvailableTranscript['name']),
                            'id_ncbi' => $aAvailableTranscript['id'],
                            // This is NULL sometimes, which crashes the insertion of the transcript.
                            'id_protein_ncbi' => (!$aAvailableTranscript['proteinTranscript']['id']? '' : $aAvailableTranscript['proteinTranscript']['id']),
                            'position_c_mrna_start' => $aAvailableTranscript['cTransStart'],
                            'position_c_mrna_end' => $aAvailableTranscript['sortableTransEnd'],
                            'position_c_cds_end' => $aAvailableTranscript['cCDSStop'],
                            'position_g_mrna_start' => $aAvailableTranscript['exons']['0']['gStart'],
                            'position_g_mrna_end' => $aAvailableTranscript['exons']['0']['gStop'],
                        );
                }
            }
        }
    }
    
    
    // T: Alle genen zijn hieraan toegevoegd, zorgt ervoor dat er geen genen ect. worden aangemaakt. Voor nu uit gecomment.
    // Now, if we don't have transcripts from the UD, we either didn't have a UD or we failed to get the transcripts.
    // Either way, we'll block the gene from further processing.
    /*
    if (!$aTranscriptsInUD) {
        // If we hadn't written a date in the genes to ignore list, then we'll do it now.
        if (!$bWroteToGenesFile) {
            $bWroteToGenesFile = fputs($fGenesToIgnore, '# Genes ignored on ' . date('Y-m-d') . "\r\n");
        }
        fputs($fGenesToIgnore, $aLine['gd_app_sym'] . "\r\n");
        continue;
    }
    */
    //print($aTranscriptsInUD['product']);
    // T: Na aanpassen preg_match bij chromosome field is SQL error weg. HGNC_ID in file is nog wel nodig, handmatig verwijderd na aanmaken genen.
    // Now, if needed, create the gene in the database.
    if (!isset($aGenesInLOVD[$aLine['gd_app_sym']])) { //gd_app_sym = Approved symbol van HGNC (gensymbool dus)
        // Copy the values from the HGNC data to $aGenes.
        foreach ($_CONFIG['lovd_gene_columns'] as $sCol => $sHGNCCol) {
            if ($sHGNCCol) {
                $aGene[$sCol] = $aLine[$sHGNCCol];
            }
        }
        $aGene['created_by'] = 0;
        // Because there is significant time between creating two entries, I prefer to run date() again.
        $aGene['created_date'] = date('Y-m-d H:i:s');
        $qGenes->execute(array_values($aGene));
        $nGenesCreated ++;

        // Also, activate the standard custom columns!!!
        lovd_addAllDefaultCustomColumns('gene', $aLine['gd_app_sym'], '0');
    }



    // Now go and find which transcripts to create.
    $aTranscriptsForLOVD = array();
    if (count($aTranscriptsInUD)) {
        // Now we must make a choice based on the transcripts we found.
        // By limiting ourselves to transcripts found in the UD we automatically filter the transcripts;
        // HGNC suggests NC, NG, NM, NP, NR, XM, XR, NT and YP RefSeqs.

        if ($aTranscriptsToCreate) {
            // A file is being used with transcripts to create.
            foreach ($aTranscriptsInUD as $sIDWithoutVersion => $aVersions) {
                // If it's in our list, with or without matching version, create.
                if (isset($aTranscriptsToCreate[$sIDWithoutVersion])) {
                    // NMs requested should usually just have one version, but well...
                    foreach ($aTranscriptsToCreate[$sIDWithoutVersion] as $nVersion) {
                        if (isset($aVersions[$nVersion])) {
                            $aTranscriptsForLOVD[] = $aVersions[$nVersion];
                        }
                    }
                    if (!$aTranscriptsForLOVD) {
                        // That didn't work, either because no specific version was requested, or because of
                        // a mismatch in versions. Simply get newest version from the UD.
                        $nMaxVersion = max(array_keys($aVersions));
                        $aTranscriptsForLOVD[] = $aVersions[$nMaxVersion];
                    }
                }
            }

        } elseif ($_CONFIG['user']['transcript_list'] == 'all') {
            // The user requested to have them all.
            foreach ($aTranscriptsInUD as $sIDWithoutVersion => $aVersions) {
                $aTranscriptsForLOVD = array_merge($aTranscriptsForLOVD, $aVersions);
            }

        } else {
            // User wanted the best option. We'll try the transcript(s) provided by the HGNC.
            // If they didn't provide any or if those transcripts are not in the UD,
            // we'll ask the gene's default LOVD installation to see which one they use.
            // That takes quite some time, but if it's what's requested, so be it...
            foreach ($aTranscriptsFromHGNC as $sIDWithoutVersion) {
                if (isset($aTranscriptsInUD[$sIDWithoutVersion])) {
                    // We might have different versions here in this array. Pick the highest one.
                    $nMaxVersion = max(array_keys($aTranscriptsInUD[$sIDWithoutVersion]));
                    $aTranscriptsForLOVD[] = $aTranscriptsInUD[$sIDWithoutVersion][$nMaxVersion];
                    // Done, stop searching for transcripts.
                    break;
                }
            }

            if (!$aTranscriptsForLOVD) {
                // Fallback; HGNC doesn't supply a transcript. As a fallback, see if there is a
                // reference LOVD for this gene, and see what transcript they use.
                // This does slow down the process quite a bit, so perhaps we need to make a setting out of it.
                // Suppress error messages, because LOVD.nl will return a 404 Not Found for genes not in the list.
                $aLOVDURL = @lovd_php_file('http://www.lovd.nl/' . $aLine['gd_app_sym'] . '?getURL');
                if ($aLOVDURL) {
                    // Now call the API, and see about the transcript info.
                    $aGeneInfo = @lovd_php_file($aLOVDURL[0] . 'api/rest.php/genes/' . $aLine['gd_app_sym']);
                    if ($aGeneInfo && is_array($aGeneInfo)) {
                        foreach ($aGeneInfo as $sLine) {
                            if (preg_match('/refseq_mrna[\s]*:[\s]*([\S]+)\.([\S]+)/', $sLine, $aMatches)) {
                                list(,$sIDWithoutVersion, $nVersion) = $aMatches;
                                if (isset($aTranscriptsInUD[$sIDWithoutVersion])) {
                                    if (isset($aTranscriptsInUD[$sIDWithoutVersion][$nVersion])) {
                                        // Same version as what this LOVD is using, is available in the UD. Take it.
                                        $aTranscriptsForLOVD[] = $aTranscriptsInUD[$sIDWithoutVersion][$nVersion];
                                    } else {
                                        // Pick the highest one from the UD.
                                        $nMaxVersion = max(array_keys($aTranscriptsInUD[$sIDWithoutVersion]));
                                        $aTranscriptsForLOVD[] = $aTranscriptsInUD[$sIDWithoutVersion][$nMaxVersion];
                                    }
                                }
                                // Done, stop searching for transcripts.
                                break;
                            }
                        }
                    }
                }

                if (!$aTranscriptsForLOVD) {
                    // So that didn't work either... then... as a final resort, just grab the first transcript.
                    $sIDWithoutVersion = key($aTranscriptsInUD);
                    // We might have different versions here in this array. Pick the highest one.
                    $nMaxVersion = max(array_keys($aTranscriptsInUD[$sIDWithoutVersion]));
                    $aTranscriptsForLOVD[] = $aTranscriptsInUD[$sIDWithoutVersion][$nMaxVersion];
                }
            }
        }
    }

    // It can happen here, that we do not have a transcript. This happens, when a certain list of transcripts has been
    // requested, but the UD didn't contain any of the transcripts. That's OK, we'll leave it at this.
    // If we do have transcripts to create, create them now.
    foreach ($aTranscriptsForLOVD as $aTranscriptForLOVD) {
        // First check if we don't already have this transcript.
        list($sIDWithoutVersion, $nVersion) = explode('.', $aTranscriptForLOVD['id_ncbi']);
        // This is a bit of a weird if(), but we didn't explode te list of versions yet.
        if (isset($aTranscriptsInLOVD[$sIDWithoutVersion]) &&
            in_array($nVersion, explode(';', $aTranscriptsInLOVD[$sIDWithoutVersion]))) {
            continue;
        }

        $aTranscriptForLOVD['created_by'] = 0;
        // Because there is significant time between creating two entries, I prefer to run date() again.
        $aTranscriptForLOVD['created_date'] = date('Y-m-d H:i:s');
        $qTranscripts->execute(array_values($aTranscriptForLOVD));
        $nTranscriptsCreated ++;
    }
}

//print($aTranscriptForLOVD['id_ncbi']);
//print($aGene['lovd_gene_columns']);
// Gene and transcript stats.
$nTimeSpent = microtime(true) - $tStart;

print("\n" .
    date('c') . "\n" .
    'Genes and transcripts done, completed ' . $nGenes . ' genes (' . round(100 * $nGenes / $nHGNCGenes) . '%) in ' . round($nTimeSpent, 1) . ' seconds (' . round($nTimeSpent/$nGenes, 2) . 's/gene).' . "\n" .
    '    Requested ' . $nUDsRequested . ' UDs' . (!$nUDsRequested? '' : ', taking ' . round($nTimeSpentGettingUDs, 1) . ' seconds (' . round($nTimeSpentGettingUDs/$nUDsRequested, 2) . 's/UD)') . "\n" .
    '    Requested transcript info for ' . $nTranscriptsRequested . ' UDs' . (!$nTranscriptsRequested? '' : ', taking ' . round($nTimeSpentGettingTranscripts, 1) . ' seconds (' . round($nTimeSpentGettingTranscripts/$nTranscriptsRequested, 2) . 's/UD)') . "\n" .
    '    Created ' . $nGenesCreated . ' gene' . ($nGenes == 1? '' : 's') . ' and ' . $nTranscriptsCreated . ' transcript' . ($nTranscriptsCreated == 1? '' : 's') . "\n");



/*

// T: Geen prioriteit, we gebruiken geen OMIM file voor nu (alles hieronder = OMIA related)
// Process the OMIM data.
if ($_CONFIG['user']['omim_data'] != 'n') {
    print("\n" .
          '  Processing OMIM data... ');

    // Load up the gene info into arrays, so we can do quick lookups.
    $aGenesInDBWithoutOMIM = $_DB->query('SELECT id, chromosome FROM ' . TABLE_GENES . ' WHERE id_omim IS NULL OR id_omim = 0')->fetchAllCombine();
    $aOMIMIDsInDB = $_DB->query('SELECT id_omim, id, chromosome FROM ' . TABLE_GENES . ' WHERE id_omim IS NOT NULL')->fetchAllGroupAssoc();
    $aInsertData = array();

    foreach ($aOMIMFile as $sLine) {
        $aData = array();

        $sLine = trim($sLine);
        // Detect comment or empty lines and skip.
        if (!$sLine || substr($sLine, 0, 1) == '#') {
            continue;
        }

        // Create an array from the line and rename the array keys to known column names.
        $aLineExplode = explode("\t", $sLine);
        $aLine = array();
        foreach ($aOMIMColumns as $nKey => $sName) {
            $aLine[$sName] = $aLineExplode[$nKey];
        }



        // Process the disease text and remove unwanted characters.
        $aData['disease'] = $aLine['disease'];
        // Take phenotype mapping number off.
        $aData['disease'] = preg_replace('/\s*\(\d\)$/', '', $aData['disease']);
        // Isolate OMIM ID.
        $aData['disease_id_omim'] = null;
        if (preg_match('/,? (\d{6})$/', $aData['disease'], $aRegs)) {
            $aData['disease_id_omim'] = $aRegs[1];
            // Now trim off the OMIM ID, the space and the optional comma.
            $aData['disease'] = substr($aData['disease'], 0, -strlen($aRegs[0]));
        } else {
            // Entry doesn't have disease OMIM ID.
            // These entries are problematic with other things as well (such as chromosome). Drop them.
            continue;
        }
        // Some entries start with a questionmark, or are surrounded by brackets.
        // Entries seem to be alright otherwise.
        $aData['disease'] = trim($aData['disease'], '?[]{}');



        // The MIM column is not always the gene's OMIM ID!
        // Sometimes it's the disease's OMIM ID, and the disease name doesn't contain any.
        // But we'll get rid of those cases, because they're not associated with a gene we can work with.
        $aData['gene_id_omim'] = $aLine['mim'];
        $aData['genes'] = preg_split('/, ?/', $aLine['genes']);



        // Parse chromosome out of the 11q13.32 format.
        if (preg_match('/^(\d+|X|Y)\w/', $aLine['cyto_location'], $aRegs)) {
            $aData['chr'] = $aRegs[1];
        } else {
            // This actually never happened, but just in case.
            continue;
        }

        // First try to see if there is an exact match with the OMIM ID, chromosome and gene symbol.
        if (!empty($aOMIMIDsInDB[$aData['gene_id_omim']]) &&
            in_array($aOMIMIDsInDB[$aData['gene_id_omim']]['id'], $aData['genes']) &&
            $aOMIMIDsInDB[$aData['gene_id_omim']]['chromosome'] == $aData['chr']) {
            $aData['db_gene'] = $aOMIMIDsInDB[$aData['gene_id_omim']]['id'];
        } else {
            // Otherwise, loop through the gene symbols to see if we find a match in the database
            //  just on symbol instead of the OMIM ID.
            foreach ($aData['genes'] as $sGene) {
                if (!empty($aGenesInDBWithoutOMIM[$sGene]) && $aGenesInDBWithoutOMIM[$sGene] == $aData['chr']) {
                    $aData['db_gene'] = $sGene;
                }
            }
        }

        if (!isset($aData['db_gene'])) {
            // We can not find a gene in the DB for this disease, so we ignore it.
            continue;
        }

        // If we're still here, then add this disease to the data to be inserted.
        if (!isset($aInsertData[$aData['disease_id_omim']])) {
            // This is the first time we have seen this disease so create a new entry for it and assign the gene.
            $aInsertData[$aData['disease_id_omim']] = array('disease' => $aData['disease'], 'genes' => array($aData['db_gene']));
        } else {
            // We have seen this disease previously.
            // Check if the name of the disease is shorter than the existing name, and if so, then use this one.
            if (strlen($aData['disease']) < strlen($aInsertData[$aData['disease_id_omim']]['disease'])) {
                $aInsertData[$aData['disease_id_omim']]['disease'] = $aData['disease'];
            }
            // Check if this gene has already been added and if not then add it
            if (!in_array($aData['db_gene'], $aInsertData[$aData['disease_id_omim']]['genes'])) {
                $aInsertData[$aData['disease_id_omim']]['genes'][] = $aData['db_gene'];
            }
        }
    }

    // $aInsertData should now contain unique diseases with their own OMIM IDs and unique genes associated with them.
    // Load up the existing disease and gen2dis tables in the DB.
    $aDiseasesInLOVD = $_DB->query('SELECT id_omim, id FROM ' . TABLE_DISEASES . ' WHERE id_omim IS NOT NULL')->fetchAllCombine();
    $aGen2DisInLOVD = $_DB->query('SELECT CONCAT(geneid, diseaseid), 1 FROM ' . TABLE_GEN2DIS)->fetchAllCombine();

    // Prepare the insert statements.
    $qDiseases = $_DB->prepare('INSERT INTO ' . TABLE_DISEASES . ' (name, id_omim, created_by, created_date) VALUES (?, ?, ?, ?)');
    $qGen2Dis = $_DB->prepare('INSERT INTO ' . TABLE_GEN2DIS . ' (geneid, diseaseid) VALUES (?, ?)');
    // The diseases will be done so quickly, let's not re-run this all the time.
    $sCreatedDate = date('Y-m-d H:i:s');

    $nDiseasesCreated = 0;
    $nGen2DisCreated = 0;
    $nLoopCount = 0;

    print(' OK!
  Importing OMIM data');

    // Loop through each of these $aInsertData records.
    $nDiseasesPerDot = floor(count($aInsertData) / $nDotsPerLine);
    foreach ($aInsertData as $nOMIMID => $OMIMEntry) {
        $nLoopCount ++;
        // Control when to show a progress dot.
        if (!($nLoopCount % $nDiseasesPerDot)) {
            print('.');
            flush();
        }

        // Check to see if the disease already exists within the DB.
        // If so, then get the ID and continue, otherwise insert and return the new ID.
        if (isset($aDiseasesInLOVD[$nOMIMID])) {
            $nID = $aDiseasesInLOVD[$nOMIMID];
        } else {
            // Setup the disease data to insert.
            $aSQL = array(
                'name' => $OMIMEntry['disease'],
                'id_omim' => $nOMIMID,
                'created_by' => 0,
                'created_date' => $sCreatedDate,
            );
            // Insert the new disease and return the new disease ID.
            $qDiseases->execute(array_values($aSQL));
            $nID = $_DB->lastInsertId();
            $nDiseasesCreated ++;
        }

        // Loop through each of the genes and check to see if it is already connected to the disease in the DB.
        // If so, then ignore, otherwise insert.
        foreach ($OMIMEntry['genes'] as $sGene) {
            if (!isset($aGen2DisInLOVD[$sGene . $nID])) {
                $aSQL = array(
                    'geneid' => $sGene,
                    'diseaseid' => $nID,
                );
                $qGen2Dis->execute(array_values($aSQL));
                $nGen2DisCreated ++;
            }
        }
    }

    // All done, so print out the statistics.
    print(' OK!' . "\n\n" .
        date('c') . "\n" .
        'OMIM diseases done, processed ' . count($aOMIMFile) . ' lines in the OMIM file.' . "\n" .
        'Inserted ' . $nDiseasesCreated . ' disease' . ($nDiseasesCreated == 1? '' : 's') . ' and added ' . $nGen2DisCreated . ' link' . ($nGen2DisCreated == 1? '' : 's') . ' from genes to diseases.' . "\n");
}

*/
/*
//print_r($qTranscripts);
//print_r($sSQL);
$sName = $_CONFIG['lovd_transcript_columns']['1'];
//print($sName);

$sSQL2 = 'UPDATE ' . TABLE_TRANSCRIPTS;
$sSQL2 .= ' SET name='."'hallo'";
$sSQL2 .= ' WHERE geneid='."'A1BG'" ;
$qTranscripts2 = $_DB->prepare($sSQL2);

//print_r($sSQL);
print_r($qTranscripts2);*/

print('All Done.' . "\n\n");
?>
