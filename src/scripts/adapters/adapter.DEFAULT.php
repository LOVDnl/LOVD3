<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE FOR DIAGNOSTICS (LOVD+)
 *
 * Created     : 2018-08-16
 * Modified    : 2018-12-19
 * Version     : 0.2
 * For LOVD+   : 3.0-18
 *
 * Purpose     : Prepares the conversion script; runs the VEP2TSV converter to
 *               create data files, and creates meta data files if needed.
 *
 * Changelog   : 0.2    2018-12-19
 *               No longer create the id_sample column in the Meta data files,
 *               we've dropped this column.
 *               0.1    2018-08-28
 *               Initial release.
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD+.
 *
 * LOVD+ is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD+ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD+. If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Command line only.
if (isset($_SERVER['HTTP_HOST'])) {
    die('Please run this script through the command line.' . "\n");
}

//define('ROOT_PATH', '../../');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__) . '/../../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);

$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => __FILE__,
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require ROOT_PATH . 'inc-init.php';

// Default settings.
$_CONFIG = array(
    'vcf_file_suffix' => 'vcf',
);

// More exit codes.
define('EXIT_ERROR_DATA_DIR_CANT_OPEN', 65);
$bWarningsOcurred = false;





// Collect all files from the data dir.
$aFiles = lovd_getFilesFromDir(
    $_INI['paths']['data_files'],
    $_ADAPTER->getInputFilePrefixPattern(),
    // Add the 'vcf' suffix.
    array_map('preg_quote', array_values(array_merge($_INSTANCE_CONFIG['conversion']['suffixes'], array($_CONFIG['vcf_file_suffix']))))
);
if ($aFiles === false) {
    lovd_printIfVerbose(VERBOSITY_LOW, 'Can\'t open directory.' . "\n");
    die(EXIT_ERROR_DATA_DIR_CANT_OPEN);
}



// Check if there are VEP annotated VCF files that are missing a tsv data file.
foreach ($aFiles as $sID => $aFileTypes) {
    if (in_array($_INSTANCE_CONFIG['conversion']['suffixes']['total'], $aFileTypes)  // Already merged.
        || in_array($_INSTANCE_CONFIG['conversion']['suffixes']['vep'], $aFileTypes) // Already converted.
        || !in_array($_CONFIG['vcf_file_suffix'], $aFileTypes)) {                    // No source file.
        continue;
    }

    // Found file to convert.
    // Run the VCF to TSV converter script.
    // NOTE: We don't check if this VCF file is actually VEP annotated. The conversion script will check that.
    lovd_printIfVerbose(VERBOSITY_HIGH, 'Running VCF to TSV adapter for sample ' . $sID . '...' . "\n");
    $sCmd = 'php ' .
        ROOT_PATH . '/scripts/vcf_to_tsv.php ' .
        $_INI['paths']['data_files'] . '/' . $sID . '.' . $_CONFIG['vcf_file_suffix'] . ' > ' .
        $_INI['paths']['data_files'] . '/' . $sID . '.' . $_INSTANCE_CONFIG['conversion']['suffixes']['vep'];
    passthru($sCmd, $nResult);
    if ($nResult == EXIT_WARNINGS_OCCURRED) {
        lovd_printIfVerbose(VERBOSITY_LOW, "VCF converter completed with warnings.\n");
        $bWarningsOcurred = true;
    } elseif ($nResult !== EXIT_OK) {
        lovd_printIfVerbose(VERBOSITY_LOW, "VCF converter failed.\n");
        $bWarningsOcurred = true;
    }
    $aFiles[$sID][] = $_INSTANCE_CONFIG['conversion']['suffixes']['vep'];

    // So, we completed. This probably took long enough, let's continue.
    // Also, by the time this script has completed, our information on files missing may have become outdated.
    // If users want their VCFs converter more quickly, they'll just run this adapter manually a few times.
    break;
}





// Put this here for now; I might move it to a separate script, separate function, or just keep it here.
// Check if there are tsv data files that are missing a meta file.
if (!empty($_INSTANCE_CONFIG['conversion']['create_meta_file_if_missing'])) {
    // Loop through the files in the dir and try and find a data file without a meta and total data file.
    // Find data files that are not done yet, but don't have a meta.
    foreach ($aFiles as $sID => $aFileTypes) {
        if (in_array($_INSTANCE_CONFIG['conversion']['suffixes']['total'], $aFileTypes)) {
            // Already merged.
            continue;
        }

        // Report incomplete data sets; meta data without variant data, for instance, and data sets still running (maybe split that, if this happens more often).
        if (!in_array($_INSTANCE_CONFIG['conversion']['suffixes']['meta'], $aFileTypes)
            && in_array($_INSTANCE_CONFIG['conversion']['suffixes']['vep'], $aFileTypes)) {
            // No meta data, but we do have a data file.
            // Just simple defaults...
            $aMetaFile = array(
                'Diseases' => array(),
                'Individuals' => array(
                    'id' => 1,
                    'panel_size' => 1, // FIXME: We could count the number of samples from the data file?
                    'Individual/Lab_ID' => $sID, // FIXME: Should we instead pick the header from the file?
                ),
                'Individuals_To_Diseases' => array(),
                'Screenings' => array(
                    'id' => 1,
                    'individualid' => 1,
                    'variants_found' => 1,
                    'Screening/Technique' => '?',
                    'Screening/Template' => 'DNA',
                ),
            );

            $sOutput =
                '### LOVD-version ' . lovd_calculateVersion($_SETT['system']['version']) . ' ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
                '# charset = UTF-8' . "\r\n\r\n";
            foreach ($aMetaFile as $sObject => $aObject) {
                $sOutput .= '## ' . $sObject . ' ## Do not remove or alter this header ##' . "\r\n";
                if ($aObject) {
                    $sOutput .=
                        '"{{' . implode("}}\"\t\"{{", array_keys($aObject)) . "}}\"\r\n" .
                        '"' . implode("\"\t\"", $aObject) . "\"\r\n\r\n";
                }
            }
            if (file_put_contents($_INI['paths']['data_files'] . '/' . $sID . '.' . $_INSTANCE_CONFIG['conversion']['suffixes']['meta'], $sOutput)) {
                lovd_printIfVerbose(VERBOSITY_MEDIUM, 'Created default meta data file for sample ' . $sID . '.' . "\n");
            } else {
                lovd_printIfVerbose(VERBOSITY_LOW, 'Failed creating meta data file for sample ' . $sID . '.' . "\n");
                $bWarningsOcurred = true;
            }
        }
    }
}

die($bWarningsOcurred? EXIT_WARNINGS_OCCURRED : EXIT_OK);
?>
