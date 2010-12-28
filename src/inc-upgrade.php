<?php
// DMD_SPECIFIC: REMEMBER. If you add code that adds SQL for all genes, you MUST add the key first to the large array. Otherwise, the order in which upgrades are done is WRONG!!!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2010-12-28
 * For LOVD    : 3.0-pre-12
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.NL>
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

// 2009-07-17; 2.0-20; Added increased execution time to help perform large upgrades.
if ((int) ini_get('max_execution_time') < 60) {
    set_time_limit(60);
}

// How are the versions related?
$sCalcVersionFiles = lovd_calculateVersion($_SETT['system']['version']);
$sCalcVersionDB = lovd_calculateVersion($_STAT['version']);

if ($sCalcVersionFiles != $sCalcVersionDB) {
    // Version of files are not equal to version of database backend.

    // DB version greater than file version... then we have a problem.
    if ($sCalcVersionFiles < $sCalcVersionDB) {
        lovd_displayError('UpgradeError', 'Database version ' . $_STAT['version'] . ' found newer than file version ' . $_SETT['system']['version']);
    }

    define('PAGE_TITLE', 'Upgrading LOVD...');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    print('      Please wait while LOVD is upgrading the database backend from ' . $_STAT['version'] . ' to ' . $_SETT['system']['version'] . '.<BR><BR>' . "\n");

    // Array of changes.
    $aUpdates =
             array(
                    '3.0-pre-06' =>
                             array(
                                    'ALTER TABLE ' . TABLE_VARIANTS . ' DROP COLUMN sort',
                                    'UPDATE ' . TABLE_COLS . ' SET id = "Screening/Template" WHERE id = "Patient/Detection/Template"',
                                    'UPDATE ' . TABLE_COLS . ' SET id = "Screening/Technique" WHERE id = "Patient/Detection/Technique"',
                                    'UPDATE ' . TABLE_COLS . ' SET id = "Screening/Tissue" WHERE id = "Patient/Detection/Tissue"',
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN form_type VARCHAR(255) NOT NULL',
                                    'UPDATE ' . TABLE_COLS . ' SET form_type = CONCAT(SUBSTRING_INDEX(form_type, "|", 1), "||", SUBSTRING_INDEX(form_type, "|", -2))',
                                    'INSERT INTO ' . TABLE_COLS . ' VALUES ("Variant/Reference", 0, 200, 1, 1, 0, "Reference", "", "Reference describing the variant.", "Literature reference with possible link to publication in PubMed, dbSNP entry or other online resource.", "VARCHAR(200)", "Reference||text|50", "", "", 1, 1, 1, 1, NOW(), NULL, NULL)',
                                    'UPDATE ' . TABLE_COLS . ' SET description_legend_short = "Reference describing the patient, &quot;Submitted:&quot; indicating that the mutation was submitted directly to this database.", description_legend_full = "Literature reference with possible link to publication in PubMed or other online resource. &quot;Submitted:&quot; indicates that the mutation was submitted directly to this database by the laboratory indicated." WHERE id = "Patient/Reference"',
                                    'ALTER TABLE ' . TABLE_LINKS . ' DROP COLUMN active',
                                    'INSERT INTO ' . TABLE_LINKS . ' VALUES (001, "PubMed", "{PMID:[1]:[2]}", "<A href=\"http://www.ncbi.nlm.nih.gov/pubmed/[2]\" target=\"_blank\">[1]</A>", "Links to abstracts in the PubMed database.\r\n[1] = The name of the author(s).\r\n[2] = The PubMed ID.", 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_LINKS . ' VALUES (002, "DbSNP", "{dbSNP:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?type=rs&amp;rs=rs[1]\" target=\"_blank\">dbSNP</A>", "Links to the DbSNP database.\r\n[1] = The DbSNP ID.", 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_LINKS . ' VALUES (003, "GenBank", "{GenBank:[1]}", "<A href=\"http://www.ncbi.nlm.nih.gov/entrez/viewer.fcgi?cmd=Retrieve&amp;db=nucleotide&amp;dopt=GenBank&amp;list_uids=[1]\" target=\"_blank\">GenBank</A>", "Links to GenBank sequences.\r\n[1] = The GenBank ID.", 1, NOW(), NULL, NULL)',
                                    'INSERT INTO ' . TABLE_LINKS . ' VALUES (004, "OMIM", "{OMIM:[1]:[2]}", "<A href=\"http://www.ncbi.nlm.nih.gov/entrez/dispomim.cgi?id=[1]&amp;a=[1]_AllelicVariant[2]\" target=\"_blank\">(OMIM [2])</A>", "Links to an allelic variant on the gene\'s OMIM page.\r\n[1] = The OMIM gene ID.\r\n[2] = The number of the OMIM allelic variant on that page.", 1, NOW(), NULL, NULL)',
                                  ),
                    '3.0-pre-07' =>
                             array(
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN description_form TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_COLS . ' MODIFY COLUMN description_legend_short TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANT_COLS . ' MODIFY COLUMN description_form TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANT_COLS . ' MODIFY COLUMN description_legend_short TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPE_COLS . ' MODIFY COLUMN description_form TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPE_COLS . ' MODIFY COLUMN description_legend_short TEXT NOT NULL',
                                    'ALTER TABLE ' . TABLE_GENES . ' MODIFY COLUMN name VARCHAR(175) NOT NULL',
                                    'DELETE FROM ' . TABLE_COLS,
                                  ),
                    '3.0-pre-08' =>
                             array(
                                    'ALTER TABLE ' . TABLE_COLS . ' CHANGE COLUMN public public_view TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_COLS . ' CHANGE COLUMN public_form public_add TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANT_COLS . ' CHANGE COLUMN public public_view TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_VARIANT_COLS . ' CHANGE COLUMN public_form public_add TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPE_COLS . ' CHANGE COLUMN public public_view TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_PHENOTYPE_COLS . ' CHANGE COLUMN public_form public_add TINYINT(1) UNSIGNED NOT NULL',
                                    'ALTER TABLE ' . TABLE_CURATES . ' ADD COLUMN show_order TINYINT(2) UNSIGNED NOT NULL DEFAULT 1 AFTER allow_edit',
                                  ),
//////////////////// 3.0-pre-09; you'll need to re-install, too much stuff changed!!!
                    '3.0-pre-10' =>
                             array(
                                    'UPDATE ' . TABLE_LINKS . ' SET replace_text = "<A href=\"http://www.ncbi.nlm.nih.gov/omim/[1]#[1]Variants[2]\" target=\"_blank\">(OMIM [2])</A>" WHERE id = 4',
                                    'DELETE FROM ' . TABLE_SOURCES . ' WHERE name = "omim_disease"',
                                    'UPDATE ' . TABLE_SOURCES . ' SET name = "omim", url = "http://www.ncbi.nlm.nih.gov/omim/{{ ID }}" WHERE name = "omim_gene"',
                                    'INSERT INTO ' . TABLE_PATHOGENIC . ' (SELECT * FROM ' . TABLE_DATA_STATUS . ' WHERE id > 9)',
                                    'DELETE FROM ' . TABLE_DATA_STATUS . ' WHERE id > 9',
                                    'UPDATE ' . TABLE_SOURCES . ' SET url = "http://www.ncbi.nlm.nih.gov/nuccore/{{ ID }}" WHERE name = "genbank"',
                                  ),
                    '3.0-pre-11' =>
                             array(
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP COLUMN id_uniprot',
                                    'ALTER TABLE ' . TABLE_TRANSCRIPTS . ' ADD COLUMN id_protein_uniprot VARCHAR(8) NOT NULL AFTER id_protein_ensembl',
                                    'INSERT INTO ' . TABLE_SOURCES . ' VALUES("hgnc", "http://www.genenames.org/data/hgnc_data.php?hgnc_id={{ ID }}")',
                                  ),
                    '3.0-pre-12' =>
                             array(
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP COLUMN genbank',
                                    'ALTER TABLE ' . TABLE_GENES . ' DROP COLUMN genbank_uri',
                                    'ALTER TABLE ' . TABLE_SOURCES . ' CHANGE COLUMN name id VARCHAR(15) NOT NULL',
                                  ),
                  );

    // Addition for upgrade to LOVD v.3.0-pre-07.
    if ($sCalcVersionDB < lovd_calculateVersion('3.0-pre-07')) {
        // Simply reload all custom columns.
        require ROOT_PATH . 'install/inc-sql-columns.php';
        $aUpdates['3.0-pre-07'] = array_merge($aUpdates['3.0-pre-07'], $aColSQL);
    }










    // To make sure we upgrade the database correctly, we add the current version to the list...
    if (!isset($aUpdates[$_SETT['system']['version']])) {
        $aUpdates[$_SETT['system']['version']] = array();
    }

    print('      <TABLE border="0" cellpadding="0" cellspacing="0" width="440">' . "\n" .
          '        <TR>' . "\n" .
          '          <TD width="400" style="border : 1px solid black; height : 15px;">' . "\n" .
          '            <IMG src="gfx/trans.png" alt="" title="0%" width="0%" height="15" id="lovd_install_progress_bar" style="background : #224488;"></TD>' . "\n" .
          '          <TD width="40" align="right" id="lovd_install_progress_value">0%</TD></TR></TABLE>' . "\n\n" .
          '      <DIV id="lovd_install_progress_text" style="margin-top : 0px;">' . "\n" .
          '        Checking upgrade lock...' . "\n" .
          '      </DIV><BR>' . "\n\n\n" .
          '      <DIV id="install_form" style="visibility : hidden;">' . "\n" .
          '        <FORM action="' . $_SERVER['REQUEST_URI'] . '" method="post" id="upgrade_form">' . "\n");
    foreach ($_POST as $key => $val) {
        // Added htmlspecialchars to prevent XSS and allow values to include quotes.
        if (is_array($val)) {
            foreach ($val as $value) {
                print('          <INPUT type="hidden" name="' . $key . '[]" value="' . htmlspecialchars($value) . '">' . "\n");
            }
        } else {
            print('          <INPUT type="hidden" name="' . $key . '" value="' . htmlspecialchars($val) . '">' . "\n");
        }
    }
    print('          <INPUT type="submit" id="submit" value="Proceed &gt;&gt;">' . "\n" .
          '        </FORM>' . "\n" .
          '      </DIV>' . "\n\n" .
          '      <SCRIPT type="text/javascript">' . "\n" .
          '        var progress_bar = document.getElementById(\'lovd_install_progress_bar\');' . "\n" .
          '        var progress_value = document.getElementById(\'lovd_install_progress_value\');' . "\n" .
          '        var progress_text = document.getElementById(\'lovd_install_progress_text\');' . "\n" .
          '        var install_form = document.getElementById(\'install_form\');' . "\n" .
          '      </SCRIPT>' . "\n\n\n");

    define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually cloes the <BODY> and <HTML> tags.
    require 'inc-bot.php';



    // Now we're still in the <BODY> so we can add <SCRIPT> tags as much as we want.
    flush();



    // Try to update the upgrade lock.
    $sQ = 'UPDATE ' . TABLE_STATUS . ' SET lock_update = 1 WHERE lock_update = 0';
    $nMax = 3; //FIXME should be higher, this value is for dev only
    for ($i = 0; $i < $nMax; $i ++) {
        lovd_queryDB($sQ);
        $bLocked = !mysql_affected_rows();
        if (!$bLocked) {
            break;
        }

        // No update means that someone else is updating the system.
        print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'Update lock is in place, so someone else is already upgrading the database.<BR>Waiting for other user to finish... (' . ($nMax - $i) . ')\';</SCRIPT>' . "\n");
        flush();
        sleep(1);
    }

    if ($bLocked) {
        // Other user is taking ages! Or somethings wrong...
        print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'Other user upgrading the database is still not finished.<BR>' . (isset($_GET['force_lock'])? 'Forcing upgrade as requested...' : 'This may indicate something went wrong during upgrade.') . '\';</SCRIPT>' . "\n");
        if (isset($_GET['force_lock'])) {
            $bLocked = false;
        }
    } else {
        print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'Upgrading database backend...\';</SCRIPT>' . "\n");
    }
    flush();





    if (!$bLocked) {
        // There we go...

        // This recursive count returns a higher count then we would seem to want at first glance,
        // because each version's array of queries count as one as well.
        // However, because we will run one additional query per version, this number will be correct anyway.
        $nSQL = count($aUpdates, true);

        // Actually run the SQL...
        $nSQLDone = 0;
        $nSQLDonePercentage = 0;
        $nSQLDonePercentagePrev = 0;
        $nSQLFailed = 0;
        $sSQLFailed = '';

        foreach ($aUpdates as $sVersion => $aSQL) {
            if (lovd_calculateVersion($sVersion) <= $sCalcVersionDB || lovd_calculateVersion($sVersion) > $sCalcVersionFiles) {
                continue;
            }
            print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'To ' . $sVersion . '...\';</SCRIPT>' . "\n");

            $aSQL[] = 'UPDATE ' . TABLE_STATUS . ' SET version = "' . $sVersion . '", updated_date = NOW()';

            // Loop needed queries...
            foreach ($aSQL as $i => $sSQL) {
                $i ++;
                if (!$nSQLFailed) {
                    $q = mysql_query($sSQL); // This means that there is no SQL injection check here. But hey - these are our own queries. DON'T USE lovd_queryDB(). It complains because there are ?s in the queries.
                    if (!$q) {
                        $nSQLFailed ++;
                        // Error when running query.
                        $sError = mysql_error();
                        lovd_queryError('RunUpgradeSQL', $sSQL, $sError, false);
                        $sSQLFailed = 'Error!<BR><BR>\n\n' .
                                      'Error while executing query ' . $i . ':\n' .
                                      '<PRE style="background : #F0F0F0;">' . htmlspecialchars($sError) . '</PRE><BR>\n\n' .
                                      'This implies these MySQL queries need to be executed manually:<BR>\n' .
                                      '<PRE style="background : #F0F0F0;">\n<SPAN style="background : #C0C0C0;">' . str_pad($i, strlen(count($aSQL)), ' ', STR_PAD_LEFT) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';

                    } else {
                        $nSQLDone ++;

                        $nSQLDonePercentage = floor(100*$nSQLDone / $nSQL); // Don't want to show 100% when an error occurs at 99.5%.
                        if ($nSQLDonePercentage != $nSQLDonePercentagePrev) {
                            print('<SCRIPT type="text/javascript">progress_bar.style.width = \'' . $nSQLDonePercentage . '%\'; progress_value.innerHTML = \'' . $nSQLDonePercentage . '%\'; </SCRIPT>' . "\n");
                            $nSQLDonePercentagePrev = $nSQLDonePercentage;
                        }

                        flush();
                        usleep(1000);
                    }

                } else {
                    // Something went wrong, so we need to print out the remaining queries...
                    $nSQLFailed ++;
                    $sSQLFailed .= '<SPAN style="background : #C0C0C0;">' . str_pad($i, strlen(count($aSQL)), ' ', STR_PAD_LEFT) . '</SPAN> ' . htmlspecialchars($sSQL) . ';\n';
                }
            }

            if ($nSQLFailed) {
                $sSQLFailed .= '</PRE>';
                print('<SCRIPT type="text/javascript">progress_text.innerHTML=\'' . str_replace('\'', '\\\'', $sSQLFailed) . '\';</SCRIPT>' . "\n" .
                      '<SCRIPT type="text/javascript">install_form.innerHTML=\'After executing th' . ($nSQLFailed == 1? 'is query' : 'ese queries') . ', please try again.\'; install_form.style.visibility=\'visible\';</SCRIPT>' . "\n");
                break;
            }
            usleep(300000);
        }

        if (!$nSQLFailed) {
            // Upgrade complete, all OK!
            lovd_writeLog('Install', 'Upgrade', 'Successfully upgraded LOVD from ' . $_STAT['version'] . ' to ' . $_SETT['system']['version'] . ', executing ' . $nSQLDone . ' quer' . ($nSQLDone == 1? 'y' : 'ies'));
            print('<SCRIPT type="text/javascript">progress_bar.style.width = \'100%\'; progress_value.innerHTML = \'100%\'; </SCRIPT>' . "\n" .
                  '<SCRIPT type="text/javascript">progress_text.innerHTML=\'Successfully upgraded to ' . $_SETT['system']['version'] . '!<BR>Executed ' . $nSQLDone . ' database quer' . ($nSQLDone == 1? 'y' : 'ies') . '.\';</SCRIPT>' . "\n");
        } else {
            // Bye bye, they should not see the form!
            print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
            exit;
        }

        // Remove update lock.
        $q = lovd_queryDB('UPDATE ' . TABLE_STATUS . ' SET lock_update = 0');
    }

    // Now that this is over, let the user proceed to whereever they were going!
    if ($bLocked) {
        // Have to force upgrade...
        $_SERVER['REQUEST_URI'] .= ($_SERVER['QUERY_STRING']? '&' : '?') . 'force_lock';
    } else {
        // Remove the force_lock thing again... (might not be there, but who cares!)
        $_SERVER['REQUEST_URI'] = preg_replace('/[?&]force_lock$/', '', $_SERVER['REQUEST_URI']);
    }

    print('<SCRIPT type="text/javascript">document.forms[\'upgrade_form\'].action=\'' . str_replace('\'', '\\\'', $_SERVER['REQUEST_URI']) . '\';</SCRIPT>' . "\n");
    if ($bLocked) {
        print('<SCRIPT type="text/javascript">document.forms[\'upgrade_form\'].submit.value = document.forms[\'upgrade_form\'].submit.value.replace(\'Proceed\', \'Force upgrade\');</SCRIPT>' . "\n");
    }
    print('<SCRIPT type="text/javascript">install_form.style.visibility=\'visible\';</SCRIPT>' . "\n" .
          '</BODY>' . "\n" .
          '</HTML>' . "\n");
    exit;
}
?>
