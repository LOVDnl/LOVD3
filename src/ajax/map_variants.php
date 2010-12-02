<?php
/******************************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-15
 * Modified    : 2010-01-15
 * For LOVD    : 3.0-pre-02
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

/*//////////////////////////////////////////////////////////////////////////////
define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

// We will start checking if we need to map the variants of the currently selected gene. After that, we'll continue with the other genes.
// Select 10 variants which have not been mapped, try to map them, then wait for a second and continue.

header('Content-type: text/javascript; charset=ISO-8859-1');

// Map 10 variants at a time max.
$nMaxVariants = 10;

// List of possible percentages...
$aImages = array(0, 6, 13, 19, 25, 31, 38, 44, 50, 56, 63, 69, 75, 81, 88, 94, 99);
$nParts = 100 / (count($aImages) - 1);

$_SESSION['mapping']['time_complete'] = 0;

// Get Gene list...
if (empty($_SESSION['mapping']['genes'])) {
    // Check all genes!
    $_SESSION['mapping']['genes'] = lovd_getGeneList();
}

$nDone = 0;

$_SESSION['mapping']['genes'] = lovd_getGeneList();

ignore_user_abort(true);

// Now, we'll traverse this array, pick max 10 variants and start processing...
foreach ($_SESSION['mapping']['genes'] as $key => $sGene) {
    // Which human build, reference sequence?
    list($sRefSeq, $sBuild) = mysql_fetch_row(mysql_query('SELECT refseq_mrna, refseq_build FROM ' . TABLE_DBS . ' WHERE symbol = "' . $sGene . '"'));
    if (!$sRefSeq || !$sBuild) {
        unset($_SESSION['mapping']['genes'][$key]);
        continue;
    }

    // How many variants are there that we'll need to index? `Variant/DNA` also checks (crude way) if the DNA column is there.
    list($nToDo) = @mysql_fetch_row(mysql_query('SELECT COUNT(DISTINCT `Variant/DNA`) FROM ' . TABLEPREFIX . '_' . $sGene . '_variants WHERE c_position_start IS NULL'));

    if (!$nToDo) {
        unset($_SESSION['mapping']['current'], $_SESSION['mapping']['genes'][$key]);
        continue;
    }

    // I want to store the progress to be able to show a progress meter...
    if (empty($_SESSION['mapping']['current']) || $_SESSION['mapping']['current'][1] < $nToDo) {
        $_SESSION['mapping']['current'] = array(0, $nToDo);
    }

    $i = 0;
    while ($nDone < $nMaxVariants) {
        // Pick $nMaxVariants variants max.
        $qVars = @mysql_query('SELECT DISTINCT `Variant/DNA` FROM ' . TABLEPREFIX . '_' . $sGene . '_variants WHERE c_position_start IS NULL LIMIT ' . $nMaxVariants);
        $nVars = mysql_num_rows($qVars);

        // Per variant, request the positions from Mutalyzer.
        while (list($sVariant) = mysql_fetch_row($qVars)) {
            if ($nDone < $nMaxVariants) { // Somehow can't combine this with the while() one line up.
                // 2010-01-12; 2.0-24; Create "clean variant name" to compensate for databases with gene names or reference sequences in front of the variant names.
                // Yes, this means possible reference sequence information in front of the variant is IGNORED and overruled by the info provided by the gene settings.
                if (preg_match('/^([A-Z_0-9.]+:)([cg]\..+)$/', $sVariant, $aRegs)) {
                    // NG_000000.0:g.123...
                    // GENE:c.123...
                    $sVariantClean = $aRegs[2];
                } else {
                    $sVariantClean = $sVariant;
                }
                $sURL = 'http://www.mutalyzer.nl/2.0/Variant_info?LOVD_ver=' . $_SETT['system']['version'] . '&build=' . $sBuild . '&acc=' . $sRefSeq . '&var=' . rawurlencode($sVariantClean);
                $aOutput = lovd_php_file($sURL);
                // 2010-01-07; 2.0-24; Make sure we're parsing an array to prevent errors.
                if (!is_array($aOutput)) {
                    $aOutput = array();
                }
                if (count($aOutput) == 7) {
                    foreach ($aOutput as $key => $val) {
                        $aOutput[$key] = mysql_real_escape_string(trim($val));
                    }
                } elseif (count($aOutput) == 1 && preg_match('/^Error (Variant_info): Reference sequence (version )?not found\./', $aOutput[0])) {
                    // Save ourselves a lot of time mapping something that cannot work.
                    // Reference Sequence not found. Don't map these variants.
                    @mysql_query('UPDATE ' . TABLEPREFIX . '_' . $sGene . '_variants SET c_position_start = "0", c_position_start_intron = "0", c_position_end = "0", c_position_end_intron = "0", g_position_start = "0", g_position_end = "0", type = "" WHERE c_position_start IS NULL');
                    continue; // Will be enough for now.
                } else {
                    $aOutput = array(0, 0, 0, 0, 0, 0, '');
                }

                // 2010-01-12; 2.0-24; Update mapping for clean variant and full variant name like it was in the database.
                @mysql_query('UPDATE ' . TABLEPREFIX . '_' . $sGene . '_variants SET c_position_start = "' . $aOutput[0] . '", c_position_start_intron = "' . $aOutput[1] . '", c_position_end = "' . $aOutput[2] . '", c_position_end_intron = "' . $aOutput[3] . '", g_position_start = "' . $aOutput[4] . '", g_position_end = "' . $aOutput[5] . '", type = "' . $aOutput[6] . '" WHERE `Variant/DNA` = "' . $sVariant . '" OR `Variant/DNA` = "' . $sVariantClean . '"');

                $nDone ++;
                $i ++;
            }
        }

        if ($nVars < $nMaxVariants) {
            // For sure, we're done with this gene.
            $i = $nToDo; // Because $nToDo can be bigger than $nVars; the latter is grouped...
            unset($_SESSION['mapping']['current'], $_SESSION['mapping']['genes'][$key]);
            break;
        }
    }

    // We really need to quit if time has run out.
    if ($nDone >= $nMaxVariants) {
        break;
    }
}

if (!count($_SESSION['mapping']['genes'])) {
    // For sure, we're done.
    unset($_SESSION['mapping']['current']);
    $_SESSION['mapping']['time_complete'] = time();
    die("99\tAll done!"); // Don't just change this text... It's being checked at inc-bot.php.
}

$_SESSION['mapping']['current'][0] += $i;

$nPercentage = round(round((($_SESSION['mapping']['current'][0] * 100) / $_SESSION['mapping']['current'][1]) / $nParts) * $nParts);
if ($nPercentage == 100) { $nPercentage --; }
print(str_pad($nPercentage, 2, '0', STR_PAD_LEFT) . "\t" . 'Mapping ' . $sGene . ': ' . round(($_SESSION['mapping']['current'][0] * 100) / $_SESSION['mapping']['current'][1]) . '% done...');
*///////////////////////////////////////////////////////////////////////////////
?>

