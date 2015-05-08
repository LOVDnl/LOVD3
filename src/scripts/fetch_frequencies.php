<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-08-11
 * Modified    : 2013-10-29
 * For LOVD    : 3.0-09
 *
 * Copyright   : 2004-2013 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();

$_T->printHeader(false); // We'll use the "clean" template.
$nLimit = 25; // For how many variants at the same time are we requesting the frequencies? 25 is the max allowed by the WGS API.
$sURL = 'http://databases.lovd.nl/whole_genome/api/rest/get_frequencies?format=text/json'; // URL to request data from (GET (variant=chr;pos_start;pos_end;DNA) or POST (JSON)).





// Fetch frequencies from whole genome installation of LOVD, and import into this LOVD.
// This query may take some time in very large databases, so we'll try not to run it too often.
$nToFetch = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' WHERE average_frequency IS NULL AND chromosome IS NOT NULL AND position_g_start IS NOT NULL AND position_g_end IS NOT NULL')->fetchColumn();
if (!$nToFetch) {
    print('All done.');
    $_T->printFooter();
    exit;
}

require ROOT_PATH . 'class/progress_bar.php';
$oPB = new ProgressBar('', 'Fetching frequency data...', '&nbsp;');
$_T->printFooter(false);

@set_time_limit(0);

$sVariants = ' ';
$nDone = 0;
while ($nDone < $nToFetch && $sVariants) {
    $aVariants = $_DB->query('SELECT chromosome, position_g_start, position_g_end, `VariantOnGenome/DNA` AS DNA FROM ' . TABLE_VARIANTS . ' WHERE average_frequency IS NULL AND chromosome IS NOT NULL AND position_g_start IS NOT NULL AND position_g_end IS NOT NULL LIMIT ' . $nLimit)->fetchAllAssoc();
    if ($aVariants === array()) {
        // No results.
        $oPB->setProgress(100);
        // To prevent ending up in a loop because of a programming error, or unexpected results from the database, we'll redirect with a GET variable that we'll check for, here.
        if (ACTION == 'done') {
            // We were supposedly done before. Don't reload.
            $oPB->setMessage('Done.');
        } else {
            $oPB->setMessage('Done, reloading...');
            $oPB->redirectTo(CURRENT_PATH . '?done', 1);
        }
        die('    </BODY>' . "\n" . '</HTML>');

    } elseif (!$aVariants) {
        // Failed query,
        $oPB->setMessage('Error, did not get proper response from database.');
        die('    </BODY>' . "\n" . '</HTML>');

    } else {
        // Proceed.
        $sVariants = json_encode($aVariants);
        $aResponse = lovd_php_file($sURL, false, 'variants=' . $sVariants);
        if ($aResponse) {
            $sResponse = implode($aResponse);
        }
        if (!$aResponse || !$sResponse) {
            $oPB->setMessage('Error, did not get proper response from remote source.');
            die('    </BODY>' . "\n" . '</HTML>');
        }
        $aFrequencies = @json_decode($sResponse, true);
        if ($aFrequencies === false || $aFrequencies === NULL) {
            $oPB->setMessage('Error, could not decode response from remote source: ' . htmlspecialchars($sResponse));
            die('    </BODY>' . "\n" . '</HTML>');
        }

        // Reply received, now go through results to update the database.
        // NOTE that you might not receive the same number of results that you
        // sent; variants that are not found in the WGS install will not be
        // returned in the output at all.
        $nUpdated = 0;
        foreach ($aVariants as $nKey => $aVariant) {
            if (!empty($aFrequencies[$nKey]) && preg_match('/^(\d+)\/(\d+)$/', $aFrequencies[$nKey], $aRegs)) {
                $nFrequency = $aRegs[1] / $aRegs[2];
            } else {
                // Not found in WGS, WGS has no frequency (should not happen), or misformed frequency (also should not happen).
                // Regard as frequency = 0, meaning not found before.
                $nFrequency = 0;
            }
            // By passing False as the third argument, this query will not halt if failed.
            $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET average_frequency = ? WHERE chromosome = ? AND position_g_start = ? AND position_g_end = ? AND `VariantOnGenome/DNA` = ?', array($nFrequency, $aVariant['chromosome'], $aVariant['position_g_start'], $aVariant['position_g_end'], $aVariant['DNA']), false);
            if (!$q) {
                $oPB->setMessage('Error, failed to update entry ' . implode(';', $aVariant) . '<BR>' . $_DB->formatError());
                die('    </BODY>' . "\n" . '</HTML>');
            }
            $nUpdated ++; // Not necessarily updated, but at least the query was successful.
        }
        $nDone += $nLimit;
        $nProgress = round(($nDone / $nToFetch)*100);
        $oPB->setProgress($nProgress);
        $oPB->setMessage('Updated ' . $nUpdated . '/' . $nLimit . ' entries, ' . $nDone . '/' . $nToFetch . ' in total, at ' . $nProgress . '%.');
    }

    // Take a short break...
    usleep(200000); // 0.2s
}

// Usually, we're done when we get here.
$oPB->setProgress(100);
$oPB->setMessage('Done, reloading...');
$oPB->redirectTo(CURRENT_PATH . '?done', 1);
die('    </BODY>' . "\n" . '</HTML>');
?>
