<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2024-05-07
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

function lovd_checkDBID ($aData)
{
    // Checks if given variant and DBID match.
    // For LOVD+, regenerates the DBID and verifies.
    // For LOVD, it checks whether or not there is already an entry where this
    //  variant and this DBID come together. Ignores the current variant, if the
    //  ID is given.
    // NOTE: We're assuming that the DBID field actually exists. Using this
    // function implies you've checked for it's presence.
    global $_DB;

    if (LOVD_plus) {
        // The LOVD+ version of the fetch function is very fast and will
        //  generate an DBID irrespective of the database contents.
        return ($aData['VariantOnGenome/DBID'] == lovd_fetchDBID($aData));
    }

    // <chr||GENE>_000000 is always allowed.
    $sSymbol = substr($aData['VariantOnGenome/DBID'], 0, strpos($aData['VariantOnGenome/DBID'], '_'));
    $sGenomeVariant = str_replace(array('(', ')', '?'), '', $aData['VariantOnGenome/DNA']);
    if (!isset($aData['aTranscripts'])) {
        $aData['aTranscripts'] = array();
    }
    $aTranscriptVariants = array();
    if (!empty($aData['aTranscripts'])) {
        $aGenes = array();
        foreach ($aData['aTranscripts'] as $nTranscriptID => $aTranscript) {
            // Check for non-empty VariantOnTranscript/DNA fields for each transcript and return true immediately when GENE_000000 is used.
            $aGenes[] = $aTranscript[1];
            if (!empty($aData[$nTranscriptID . '_VariantOnTranscript/DNA'])) {
                $aTranscriptVariants[$nTranscriptID] = str_replace(array('(', ')', '?'), '', $aData[$nTranscriptID . '_VariantOnTranscript/DNA']);
            }
            if (!isset($aData['ignore_' . $nTranscriptID]) && $aData['VariantOnGenome/DBID'] == $aTranscript[1] . '_000000') {
                return true;
            }
        }
    }

    if ($aData['VariantOnGenome/DBID'] == 'chr' . $aData['chromosome'] . '_000000') {
        // Check if chr_000000 is used and return true if this is the case.
        return true;
    }

    $nIDtoIgnore = (!empty($aData['id'])? $aData['id'] : 0);

    // Check if the DBID entered is already in use by a variant entry excluding the current one.
    $nHasDBID = $_DB->q('SELECT COUNT(id) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` = ? AND id != ?', array($aData['VariantOnGenome/DBID'], $nIDtoIgnore))->fetchColumn();
    if ($nHasDBID && (!empty($sGenomeVariant) || !empty($aTranscriptVariants))) {
        // This is the standard query that will be used to determine if the DBID given is correct.
        $sSQL = 'SELECT COUNT(*) ' .
                'FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id) ' .
                'WHERE (';
        $aArgs = array();
        $sWhere = '';
        if (!empty($sGenomeVariant)) {
            // SQL addition to check the genomic notation-chromosome combination.
            $sWhere .= '(REPLACE(REPLACE(REPLACE(vog.`VariantOnGenome/DNA`, "(", ""), ")", ""), "?", "") = ? AND vog.chromosome = ?) ';
            $aArgs[] = $sGenomeVariant;
            $aArgs[] = $aData['chromosome'];
        }
        foreach ($aTranscriptVariants as $nTranscriptID => $sTranscriptVariant) {
            // SQL addition to check the transcript notation-transcript combination.
            $sWhere .= (!empty($sWhere)? 'OR ' : '') . '(REPLACE(REPLACE(REPLACE(vot.`VariantOnTranscript/DNA`, "(", ""), ")", ""), "?", "") = ? AND vot.transcriptid = ?) ';
            $aArgs[] = $sTranscriptVariant;
            $aArgs[] = $nTranscriptID;
        }
        // SQL addition to check if the above combinations are found with the given DBID.
        $sWhere .= ') AND BINARY vog.`VariantOnGenome/DBID` = ? ';
        $aArgs[] = $aData['VariantOnGenome/DBID'];
        if ($nIDtoIgnore > 0) {
            // SQL addition to exclude the current variant, where the $aData belongs to.
            $sWhere .= 'AND vog.id != ? ';
            $aArgs[] = sprintf('%010d', $nIDtoIgnore);
        }
        $sSQL .= $sWhere;
        $nOptions = $_DB->q($sSQL, $aArgs)->fetchColumn();

        if (!$nOptions) {
            return false;
        }
    } elseif (!empty($aGenes) && !in_array($sSymbol, $aGenes)) {
        // VOT, but DBID does not use the gene symbol of one of these VOT's.
        return false;
    } elseif (empty($aGenes)) {
        // VOG
        if (substr($aData['VariantOnGenome/DBID'], 0, 3) != 'chr') {
            if (!in_array($sSymbol, lovd_getGeneList())) {
                // Gene symbol used in the DBID does not exist in the database.
                return false;
            }
        } elseif ($sSymbol != 'chr' . $aData['chromosome']) {
            // Chromosome number in the DBID does not match the chromosome of the genomic variant.
            return false;
        }
    }
    return true;
}





function lovd_checkORCIDChecksum ($sID)
{
    // Partially based on function taken 2012-10-24 from:
    // http://support.orcid.org/knowledgebase/articles/116780-structure-of-the-orcid-identifier
    // Checks "check digit" as per ISO 7064 11,2, for a given ORCID ID.

    $sBaseDigits = ltrim(str_replace('-', '', substr($sID, 0, -1)), '0'); // '0000-0002-1368-1939' => 21368193
    $nTotal = 0;
    for ($i = 0; $i < strlen($sBaseDigits); $i++) {
        $nDigit = (int) $sBaseDigits[$i];
        $nTotal = ($nTotal + $nDigit) * 2;
    }
    $nRemainder = $nTotal % 11;
    $nResult = (12 - $nRemainder) % 11;
    $sResult = ($nResult == 10? 'X' : (string) $nResult);
    return ($sResult == substr($sID, -1));
}





function lovd_checkXSS ($aInput = '')
{
    // XSS attack prevention. Deny input of HTML.

    if ($aInput === '') {
        if (count($_POST)) {
            return lovd_checkXSS($_POST);
        } else {
            return true;
        }
    }

    if (!is_array($aInput)) {
        $aInput = array($aInput);
    }

    $bSuccess = true;
    foreach ($aInput as $key => $val) {
        if (empty($val)) {
            $bSuccess = ($bSuccess && true);
        } elseif (is_array($val)) {
            $bSuccess = $bSuccess && lovd_checkXSS($val);
        } elseif (strpos($key, '/') !== false && preg_match('/<.*>/s', $val)) {
            // Disallowed tag found. This check is for custom columns, that often contain < characters.
            $bSuccess = false;
            lovd_errorAdd($key, 'Disallowed tag found in form field' . (is_numeric($key)? '.' : ' "' . htmlspecialchars($key) . '".') . ' XSS attack?');
        } elseif (strpos($key, '/') === false && strpos($val, '<') !== false) {
            // This check is for any fixed field, such as the registration form.
            // Just disallow any use of <; it can introduce XSS even without a matching >.
            $bSuccess = false;
            lovd_errorAdd($key, 'The use of \'&lt;\' in form fields is now allowed.' .
                (is_numeric($key)? '.' : ' Please remove it from the "' . htmlspecialchars($key) . '" field.'));
        }
    }
    return $bSuccess;
}





function lovd_emailError ($sErrorCode, $sSubject, $sTo, $bHalt = false)
{
    // Formats email errors for the error log, and optionally halts the system.

    // Format the error message.
    // FIXME; Kan makkelijker??? // Een str_replace() zou ook wel werken... Deze code staat op minimaal 3 plaatsen.
    $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in code block ' . $sErrorCode . '.' . "\n" .
              'Error : Couldn\'t send a mail with subject "' . $sSubject . '" to ' . $sTo;

    // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
    // write it to the system log, and halt the system. Otherwise, just log it to the database.
    if ($bHalt) {
        lovd_displayError('SendMail', $sError);
    } else {
        lovd_writeLog('Error', 'SendMail', $sError);
    }
}





function lovd_error ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Tells the program whether or not we've had an error.
    global $_ERROR;

    return (isset($_ERROR['messages']) && count($_ERROR['messages']) > 1);
}





function lovd_errorAdd ($sField, $sError)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Add error to error variable.
    global $_ERROR;

    // Initialize the error array, if it hasn't been initialized yet.
    if (empty($_ERROR)) {
        lovd_errorClean();
    }

    $sError = trim($sError);
    if (strlen($sError)) {
        $_ERROR['messages'][] = $sError;
    }
    $_ERROR['fields'][] = $sField;
}





function lovd_errorClean ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Clean error variable.
    global $_ERROR;

    $_ERROR =
             array(
                    // The quotes are there, to make sure key [0] is already in use.
                    'fields' => array(''),
                    'messages' => array(''),
                  );
}





function lovd_errorFindField ($sField)
{
    // Returns index of whether or not a certain form field has an error.
    global $_ERROR;

    return (!empty($_ERROR['fields']) && array_search($sField, $_ERROR['fields']));
}





function lovd_errorPrint ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Prints error variable.
    global $_ERROR;

    if (isset($_ERROR['messages']) && count($_ERROR['messages']) > 1) {
        unset($_ERROR['messages'][0]);
        if (FORMAT == 'text/html') {
            print('      <DIV class="err">' . "\n" .
                  '        ' . implode('<BR>' . "\n" . '        ', $_ERROR['messages']) . '</DIV><BR>' . "\n\n");
        } elseif (FORMAT == 'text/plain') {
            print(':' . implode("\n" . ':', array_map('strip_tags', $_ERROR['messages'])) . "\n\n");
        }
    }
}





function lovd_formatMail ($aBody)
{
    // Returns a formatted body to send to the user.
    // Format:
    // $aBody = array(
    //                '<introduction message>',
    //                '<topic_header>' => array(
    //                                       '<sourceVariableName>',
    //                                       '<key>' => '<valueHeader>',
    //                                       '<key>' => '<valueHeader>',
    //                                      )
    //               );

    if (empty($aBody) || !is_array($aBody)) {
        return false;
    }
    $sBody = $aBody[0];
    unset($aBody[0]);
    foreach ($aBody as $sTopic => $aContent) {
        $sBody .= str_repeat('-', 70) . "\n" .
                  '  ' . strtoupper(str_replace('_', ' ', $sTopic))  . "\n" .
                  str_repeat('-', 70) . "\n";
        if (!is_array($aContent[0])) {
            $aContent = array($aContent);
        }

        foreach ($aContent as $aSubContent) {
            if (!is_array($aSubContent)) {
                if ($aSubContent == 'skip') {
                    $sBody .= "\n";
                } elseif ($aSubContent == 'hr') {
                    $sBody .= str_repeat('-', 70) . "\n";
                }
                continue;
            }
            $sSource = $aSubContent[0];
            unset($aSubContent[0]);

            // Padding to...
            $lPad = 0;
            foreach ($aSubContent as $val) {
                $l = strlen($val);
                if ($l > $lPad) {
                    $lPad = $l;
                }
            }

            foreach ($aSubContent as $key => $val) {
                $sBody .= sprintf('%-' . $lPad . 's', $val) . ' : ' . str_replace("\n", "\n" . str_repeat(' ', $lPad + 3), lovd_wrapText($GLOBALS[$sSource][$key], 70 - $lPad - 3)) . "\n";
            }
            $sBody .= str_repeat('-', 70) . "\n";
        }
        $sBody .= "\n\n";
    }

    return $sBody;
}





function lovd_fetchDBID ($aData)
{
    // For LOVD+, generates the DBID based on the DNA data.
    // For LOVD, searches through the $aData variants to fetch lowest DBID
    //  belonging to this variant, otherwise returns next variant ID not in use.
    // NOTE: We're assuming that the DBID field actually exists. Using this
    // function implies you've checked for it's presence.
    global $_DB, $_CONF;

    if (empty($aData['chromosome'])) {
        return false;
    }

    // Array to remember which IDs we saw. This is to speed up the generation of
    //  DBIDs for new variants. The search in the database for the max ID in use
    //  may take several seconds in databases with 20M+ variants, even with an
    //  index on DBID. If we limit the search by the DBID that we have seen
    //  before, we can greatly speed up the query (requires an index on the DBID
    //  column).
    static $aDBIDsSeen = array();

    $sGenomeVariant = '';
    if (!empty($aData['VariantOnGenome/DNA'])) {
        $sGenomeVariant = str_replace(array('(', ')', '?'), '', $aData['VariantOnGenome/DNA']);
    }

    if (LOVD_plus) {
        if (!empty($aData) && !empty($sGenomeVariant)) {
            // TODO: WARNING! UPDATE THE QUERY IN scripts/hash_dbid.php WHENEVER THIS IS UPDATED!
            $sDBID = sha1($_CONF['refseq_build'] . '.chr' . $aData['chromosome'] . ':' . $sGenomeVariant);
            return $sDBID;
        } else {
            return false;
        }
    }

    if (!isset($aData['aTranscripts'])) {
        $aData['aTranscripts'] = array();
    }
    $aGenes = array();
    $aTranscriptVariants = array();
    foreach ($aData['aTranscripts'] as $nTranscriptID => $aTranscript) {
        // Check for non-empty VariantOnTranscript/DNA fields.
        if (!empty($aData[$nTranscriptID . '_VariantOnTranscript/DNA'])) {
            $aTranscriptVariants[$nTranscriptID] = str_replace(array('(', ')', '?'), '', $aData[$nTranscriptID . '_VariantOnTranscript/DNA']);
        }
        $aGenes[] = $aTranscript[1];
    }

    if (!empty($aData) && (!empty($sGenomeVariant) || !empty($aTranscriptVariants))) {
        // Gather a list of DBIDs already present in the database to use.
        // 2013-03-01; 3.0-03; To speed up this query in large databases, it has been optimized and rewritten with a UNION.
        $sSQL = '';
        $aArgs = array();
        if (!empty($sGenomeVariant)) {
            // SQL addition to check the genomic notation-chromosome combination.
            $sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID` ' .
                    'FROM ' . TABLE_VARIANTS . ' AS vog ' .
                    'WHERE `VariantOnGenome/DBID` IS NOT NULL AND `VariantOnGenome/DBID` != "" AND REPLACE(REPLACE(REPLACE(vog.`VariantOnGenome/DNA`, "(", ""), ")", ""), "?", "") = ? AND vog.chromosome = ?';
            $aArgs[] = $sGenomeVariant;
            $aArgs[] = $aData['chromosome'];
            // 2013-02-28; 3.0-03; If we have the variant's position available, we can use that, speeding up the query from
            // 0.11s to 0.00s when having 1M variants. Would the position ever be different when we've got the same DNA field?
            if (!empty($aData['position_g_start'])) {
                $sSQL .= ' AND vog.position_g_start = ?';
                $aArgs[] = $aData['position_g_start'];
            }
        }
        if (!empty($aTranscriptVariants)) {
            if (!empty($sGenomeVariant)) {
                $sSQL .= ' UNION ';
            }
            // 2013-03-01; 3.0-03; To speed up this query in large databases, it has been optimized and rewritten using INNER JOIN instead of LEFT OUTER JOIN, requiring a UNION.
            $sSQL .= 'SELECT DISTINCT vog.`VariantOnGenome/DBID` ' .
                     'FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) ' .
                     'INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) ' .
                     'WHERE `VariantOnGenome/DBID` IS NOT NULL AND `VariantOnGenome/DBID` != "" AND (';
            $sWhere = '';
            foreach ($aTranscriptVariants as $nTranscriptID => $sTranscriptVariant) {
                // SQL addition to check the transcript notation-transcript combination.
                $sWhere .= (empty($sWhere)? '' : ' OR ') . '(REPLACE(REPLACE(REPLACE(vot.`VariantOnTranscript/DNA`, "(", ""), ")", ""), "?", "") = ? AND vot.transcriptid = ?)';
                $aArgs[] = $sTranscriptVariant;
                $aArgs[] = $nTranscriptID;
            }
            $sWhere .= ')';
            $sSQL .= $sWhere;
        }

        $aDBIDOptions = $_DB->q($sSQL, $aArgs)->fetchAllColumn();

        // Set the default for the DBID.
        $sDBID = 'chr' . $aData['chromosome'] . '_999999';
        foreach ($aDBIDOptions as $sDBIDoption) {
            // Loop through all the options returned from the database and decide which option to take.
            preg_match('/^((.+)_(\d{6}))$/', $sDBID, $aMatches);
            //              2 = chr## or gene
            //                   3 = the actual ID.
            list($sDBIDnewSymbol, $sDBIDnewNumber) = array($aMatches[2], $aMatches[3]);

            if (preg_match('/^(.+)_(\d{6})$/', $sDBIDoption, $aMatches)) {
                list($sDBIDoption, $sDBIDoptionSymbol, $sDBIDoptionNumber) = $aMatches;
                // Check this option, if it doesn't pass we'll skip it now.
                $aDataCopy = $aData;
                $aDataCopy['VariantOnGenome/DBID'] = $sDBIDoption;
                if (!lovd_checkDBID($aDataCopy)) {
                    continue;
                }
                if ($sDBIDoptionSymbol == $sDBIDnewSymbol && $sDBIDoptionNumber < $sDBIDnewNumber && $sDBIDoptionNumber != '000000') {
                    // If the symbol of the option is the same, but the number is lower (not including 000000), take it.
                    $sDBID = $sDBIDoption;
                } elseif (substr($sDBIDnewSymbol, 0, 3) == 'chr' && substr($sDBIDoptionSymbol, 0, 3) != 'chr') {
                    // If the symbol of the option is not a chromosome, but the current DBID is, take it.
                    $sDBID = $sDBIDoption;
                } elseif ($sDBIDoptionSymbol != $sDBIDnewSymbol && isset($aGenes) && in_array($sDBIDoptionSymbol, $aGenes)
                    && (!in_array($sDBIDnewSymbol, $aGenes)
                        || ($sDBIDoptionNumber != '000000' && $sDBIDnewNumber == '000000')
                        || $sDBIDoptionNumber < $sDBIDnewNumber)) {
                    // If the symbol of the option is different and is one of the genes of the variant you are editing/creating, take it.
                    // (but only if the currently selected DBID is *not* in the gene list or if we can pick a non 000000 ID, or if the option's number is lower)
                    $sDBID = $sDBIDoption;
                }
            }
        }
        if ((substr($sDBID, 0, 3) == 'chr' && !empty($aGenes)) || $sDBID == 'chr' . $aData['chromosome'] . '_999999') {
            // Either this variant has a DBID with chr, but also a VOT that we want to change to, or
            // no entries found with these combinations and a DBID, so we are going to use the gene symbol
            // (or chromosome if there is no gene) and take the first number available to make a DBID.
            // Query for getting the first available number for the new DBID.
            if (empty($aGenes)) {
                // No genes, simple query only on TABLE_VARIANTS.
                // 2013-02-28; 3.0-03; By querying the chromosome also we sped up this query from 0.43s to 0.09s when having 1M variants.
                // NOTE: By adding an index on `VariantOnGenome/DBID` this query time can be reduced to 0.00s because of the LIKE on the DBID field.
                // 2017-06-02; LOVD+ 3.0-17k; Even with an index, on a database with 22M variants, this query takes 2.2 seconds.
                // Cache the DBIDs we saw to speed this up when we repeatedly ask for the DBIDs on the same chromosome.
                $sSymbol = 'chr' . $aData['chromosome'];
                $sSQL = 'SELECT IFNULL(RIGHT(MAX(`VariantOnGenome/DBID`), 6), 0) + 1 FROM ' . TABLE_VARIANTS . ' AS vog WHERE vog.chromosome = ? AND `VariantOnGenome/DBID` LIKE ? AND `VariantOnGenome/DBID` REGEXP ?';
                $aArgs = array($aData['chromosome'], $sSymbol . '\_%', '^' . $sSymbol . '_[0-9]{6}$');
                if (isset($aDBIDsSeen[$aData['chromosome']])) {
                    $sSQL .= ' AND `VariantOnGenome/DBID` >= ?';
                    $aArgs[] = $aDBIDsSeen[$aData['chromosome']];
                }
                $nDBIDnewNumber = $_DB->q($sSQL, $aArgs)->fetchColumn();
                // Update the cache!
                $aDBIDsSeen[$aData['chromosome']] = $sSymbol . '_' . sprintf('%06d', ($nDBIDnewNumber - 1));
            } else {
                // We used to speed up this query by joining to VOT and T and
                //  placing a WHERE on t.geneid. However, this assumes that all
                //  variants with gene-based DBIDs still have VOTs on that gene.
                // This wasn't always the case in our database as transcripts
                //  were sometimes removed and added with time in between them,
                //  and variants were imported and given DBIDs already in use.
                // So, we now speed up this query by adding an additional WHERE
                //  on the chromosome. It provides a small speedup, while the
                //  risk of missing variants is very small (they have to be on
                //  a different chromosome).
                $sSymbol = $aGenes[0];
                $nDBIDnewNumber = $_DB->q('
                    SELECT IFNULL(RIGHT(MAX(`VariantOnGenome/DBID`), 6), 0) + 1
                    FROM ' . TABLE_VARIANTS . '
                    WHERE chromosome = ? AND `VariantOnGenome/DBID` REGEXP ?',
                        array($aData['chromosome'], '^' . $sSymbol . '_[0-9]{6}$'))->fetchColumn();
            }
            $sDBID = $sSymbol . '_' . sprintf('%06d', $nDBIDnewNumber);
        }
        return $sDBID;
    } else {
        return false;
    }
}





function lovd_buildOptionTable ($aOptionsList = array())
{
    // Build the options list that the user encounters after each seperate entry creation within a submission.

    if (empty($aOptionsList) || !is_array($aOptionsList) || empty($aOptionsList['options']) || !is_array($aOptionsList['options'])) {
        return false;
    }

    $sOptionsTable = '      <TABLE border="0" cellpadding="5" cellspacing="1" ' . (!empty($aOptionsList['width'])? 'style="width : ' . $aOptionsList['width'] . 'px;" ' : '') . 'class="option">' . "\n";

    foreach ($aOptionsList['options'] as $aOption) {
        $sOptionsTable .=  '        <TR ';
        if (!empty($aOption['disabled'])) {
            $sOptionsTable .= 'class="disabled" ';
        }
        if (substr($aOption['onclick'], 0, 11) == 'javascript:') {
            $aOption['onclick'] = str_replace('javascript:', '', $aOption['onclick']);
        } else {
            $aOption['onclick'] = 'window.location.href=\'' . lovd_getInstallURL() . $aOption['onclick'] . '\'';
        }
        $sOptionsTable .= 'onclick="' . $aOption['onclick'] . '">' . "\n" .
                         '          <TD width="30" align="center"><SPAN class="S18">&' . (!empty($aOption['type'])? $aOption['type'] : 'r') . 'aquo;</SPAN></TD>' . "\n" .
                         '          <TD>' . (!empty($aOption['disabled'])? '<I>' . $aOption['option_text'] . '</I>' : $aOption['option_text']) . '</TD></TR>' . "\n";
    }

    $sOptionsTable .= '      </TABLE><BR>' . "\n\n";

    return $sOptionsTable;
}





function lovd_matchDate ($s, $bTime = false, $bNoZeroDate = false)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the date pattern, one that MySQL can understand.

    $bFormat = preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}' . ($bTime? ' [0-2][0-9]\:[0-5][0-9]\:[0-5][0-9]' : '') . '$/', $s);
    if (!$bFormat) {
        return false;
    }

    // We'll need this a few times.
    $sDate = substr($s, 0, 10);

    // We need a valid date, always.
    if (strtotime($s) === false) {
        return false;
    }

    // Finally, since strtotime() allows 31 days in months that have 30, do a better check.
    list($nYear, $nMonth, $nDay) = explode('-', $sDate);
    if (!checkdate($nMonth, $nDay, $nYear) && !(!$bNoZeroDate && $sDate == '0000-00-00')) {
        return false;
    }

    return true;
}





function lovd_matchEmail ($s)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the email address pattern.

    return (preg_match('/^[A-Z0-9_.+-]+@(([A-Z0-9][A-Z0-9-]*[A-Z0-9]|[A-Z0-9])\.)+[A-Z]{2,6}$/i', $s));
}





function lovd_matchIPRange ($s, $sField = '')
{
    // Matches a string containing an IP address range.

    //FIXME; include check on numbers higher than 255; preg_split on [^0-9] and foreach() through the results.
    $a = preg_split('/[;,]/', $s);
    $b = true;
    foreach ($a as $val) {
        if (!preg_match('/^(\*|[0-9]{1,3}\.(\*|[0-9]{1,3}(\-[0-9]{1,3})?\.(\*|[0-9]{1,3}(\-[0-9]{1,3})?\.(\*|[0-9]{1,3}(\-[0-9]{1,3})?))))$/', $val)) {
            $b = false;
            if ($sField) {
                lovd_errorAdd($sField, 'Value "' . $val . '" not understood as a given IP range.');
            }
            return $b;
        }
    }
    return $b;
}





function lovd_matchPassword ($s)
{
    // Matches a string to the password pattern (non standard). This somewhat
    // enforces the choice of a good password. This should be extended with a
    // dictionary search, maybe.

    if (strlen($s) < 4) {
        return false;
    }

    // OK... what if we remove all characters. Anything left?
    $s = preg_replace('/[A-Za-z]+/', '', $s);
    return (strlen($s) > 0);
}





function lovd_matchURL ($s, $bAllowCustomHosts = false)
{
    // Based on a function provided by Ileos.nl.
    // Matches a string to the standard URL pattern (including those using IP addresses).
    // If $bAllowCustomHosts is true, hosts like "localhost" (hosts without dots) are allowed.

    return (preg_match('/^(ht|f)tps?:\/\/([0-9]{1,3}(\.[0-9]{1,3}){3}|(([0-9a-z][-0-9a-z]*[0-9a-z]|[0-9a-z])\.' . ($bAllowCustomHosts? '?' : '') . ')+[a-z]{2,})(\/[%&=#0-9a-z\/._+-]*\??.*)?$/i', $s));
}





function lovd_matchUsername ($s)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the username pattern (non standard).

    return (preg_match('/^[A-Z][A-Z0-9_.-]{3,19}$/i', $s));
}





function lovd_recaptchaV2_verify ($sUserResponse)
{
    // Function to verify the "response" from the user with Google.

    try {
        // Verify reCaptcha V2 user response with Google.
        $aPostVars = array('secret' => '6Lf_XBsUAAAAAIjtOpBdpVyzwsWYO4AtgmgjxDcb',
            'response' => $sUserResponse);
        $aResponseRaw = (lovd_php_file('https://www.recaptcha.net/recaptcha/api/siteverify', false,
            http_build_query($aPostVars), 'Accept: application/json') ?: []);
        // Note: "error-codes" in the response object is optional, even when
        // verification fails.
        $aResponse = json_decode(implode('', $aResponseRaw), true);
        return $aResponse['success'];
    } catch (Exception $e) {
        // FIXME: Consider logging debug information here.
    }
    return false;
}





function lovd_sendMail ($aTo, $sSubject, $sBody, $sHeaders, $bHalt = true, $bFwdAdmin = true, $aCc = array(), $aBcc = array())
{
    // Format:
    // $aTo, $aCc, $aBcc = array(
    //                           array('Name', "Email\r\nEmail\r\nEmail"),
    //                           array('Name', "Email\r\nEmail")
    //                          );
    global $_SETT, $_CONF;

    $aEmailsUsed = array(); // Make sure no email address is used more than once.
    $sTo = lovd_sendMailFormatAddresses($aTo, $aEmailsUsed);
    $sCc = lovd_sendMailFormatAddresses($aCc, $aEmailsUsed);
    $sBcc = lovd_sendMailFormatAddresses($aBcc, $aEmailsUsed);

    // 2013-02-06; 3.0-02; Fix for MIME emails that have long lines in the MIME headers.
    // Lines that are not to be wrapped will have their spaces (and other characters lovd_wrapText()
    // responds to) replaced with something else; then the body is wrapped, and then the spaces are replaced back in.
    $sBody = preg_replace_callback('/^(Content-(Type|Description):.+)/im',
        function ($aRegs) { return str_replace(array(' ', '-', ',', ':', ';'), array('{{SPACE}}', '{{HYPHEN}}', '{{COMMA}}', '{{COLON}}', '{{SEMICOLON}}'), $aRegs[1]);},
        $sBody);
    // Normal message body wrapping, which now cannot wrap the headers anymore...
    $sBody = lovd_wrapText($sBody);
    // Now, let's restore what we replaced.
    $sBody = preg_replace_callback('/^(Content{{HYPHEN}}(Type|Description){{COLON}}.+)/im',
        function ($aRegs) { return str_replace(array('{{SPACE}}', '{{HYPHEN}}', '{{COMMA}}', '{{COLON}}', '{{SEMICOLON}}'), array(' ', '-', ',', ':', ';'), $aRegs[1]);},
        $sBody);

    $sHeaders = $sHeaders . (!empty($sCc)? PHP_EOL . 'Cc: ' . $sCc : '') . (!empty($sBcc)? PHP_EOL . 'Bcc: ' . $sBcc : '');

    // Submission emails should have the Reply-To set to the curator
    //  and the submitter, so both benefit from it.
    if (strpos($sSubject, 'LOVD submission') === 0) {
        // Reply-to should be original addressees.
        $sHeaders .= PHP_EOL . 'Reply-To: ' . $sTo . ', ' . $sCc;
    }

    // 2013-08-26; 3.0-08; Encode the subject as well. Prefixing with "Subject: " to make sure the first line including the SMTP header does not exceed the 76 chars.
    $sSubjectEncoded = substr(mb_encode_mimeheader('Subject: ' . $sSubject, 'UTF-8'), 9);
    $bSafeMode = ini_get('safe_mode');
    if (!$bSafeMode) {
        $bMail = @mail($sTo, $sSubjectEncoded, $sBody, $sHeaders, '-f ' . $_CONF['email_address']);
    } else {
        $bMail = @mail($sTo, $sSubjectEncoded, $sBody, $sHeaders);
    }

    if ($bMail && $bFwdAdmin) {
        $sBody = preg_replace('/^(Password[\s*]+: ).+/m', "$1" . '<password hidden>', $sBody);
        $sBody = 'Dear ' . $_SETT['admin']['name'] . ",\n\n" .
                 'As requested, a copy of the message I\'ve just sent.' . "\n\n" .
                 str_repeat('-', 25) . ' Forwarded  Message ' . str_repeat('-', 25) . "\n\n" .
                 rtrim($sBody) . "\n\n" .
                 str_repeat('-', 22) . ' End of Forwarded Message ' . str_repeat('-', 22) . "\n";

        // The admin should have a proper Reply-to header.
        $sAdditionalHeaders = '';
        if (in_array($sSubject, array('LOVD account registration', 'LOVD password reset'))) {
            // Reply-to should be original addressees.
            $sAdditionalHeaders .= 'Reply-To: ' . $sTo;
        } elseif (strpos($sSubject, 'LOVD submission') === 0) {
            // Reply-to should be submitter.
            $sAdditionalHeaders .= 'Reply-To: ' . $sCc;
        }

        $sSubject = 'FW: ' . $sSubject;
        // 2013-08-26; 3.0-08; Encode the subject as well. Prefixing with "Subject: " to make sure the first line including the SMTP header does not exceed the 76 chars.
        $sSubjectEncoded = substr(mb_encode_mimeheader('Subject: ' . $sSubject, 'UTF-8'), 9);
        return lovd_sendMail(array($_SETT['admin']), $sSubjectEncoded, $sBody, $_SETT['email_headers'] . ($sAdditionalHeaders? PHP_EOL . $sAdditionalHeaders : ''), $bHalt, false);
    } elseif (!$bMail) {
        // $sSubject is used here as it can always be used to describe the email type. This function also logs the email error.
        lovd_emailError(LOG_EVENT, $sSubject, $sTo, $bHalt);
    }

    return $bMail;
}





function lovd_sendMailFormatAddresses ($aRecipients, & $aEmailsUsed)
{
    // Formats the To, Cc or Bcc headers for emails sent by LOVD.

    if (!is_array($aRecipients) || !count($aRecipients)) {
        return false;
    }
    if (!is_array($aEmailsUsed)) {
        $aEmailsUsed = array();
    }

    $sRecipients = '';
    foreach ($aRecipients as $aRecipient) {
        list($sName, $sEmails) = array_values($aRecipient);
        $aEmails = explode("\r\n", $sEmails);
        foreach ($aEmails as $sEmail) {
            if ($sEmail && !in_array($sEmail, $aEmailsUsed)) {
                // Plain mb_encode_mimeheader() has some limitations:
                // - It doesn't know the length of the header, which is needed because lines can be no longer than 76 chars.
                // - It encodes the email address which it should not do, breaking the sending of email.
                // - It doesn't handle spaces well.
                // Solution is to split on spaces first, and encode the name only, each word on a different line to be relatively sure we don't cross the line border.
                if (preg_match('/[\x80-\xFF]/', $sName)) {
                    // Special characters. Encode the name, split on each word. This technique will not be sufficient in case of very
                    // long names (longer than 24 chars with many special chars). In that case it could cross the 76-char line boundary.
                    $aName = explode(' ', trim($sName));
                    foreach ($aName as $nKey => $sWord) {
                        // To include spaces where they belong, include a space in the encoded name.
                        if ($nKey) {
                            $sWord = ' ' . $sWord;
                        }
                        $sRecipients .= (!$sRecipients? '' : PHP_EOL . ' ') . mb_encode_mimeheader($sWord, 'UTF-8');
                    }
                    $sRecipients .= PHP_EOL . ' <' . trim($sEmail) . '>, ';
                } else {
                    $sRecipients .= (ON_WINDOWS? '' : '"' . trim($sName) . '" ') . '<' . trim($sEmail) . '>, ';
                }
                $aEmailsUsed[] = $sEmail;
            }
        }
    }
    return rtrim($sRecipients, ', ');
}





function lovd_setUpdatedDate ($aGenes, $bAuth = true)
{
    // Updates the updated_date field of the indicated gene.
    // $bAuth allows you to control who gets marked as updated_by; the current
    //  user (the default) or LOVD (pass false).
    global $_AUTH, $_DB;

    if (LOVD_plus) {
        // LOVD+ does not use these timestamps.
        return count($aGenes);
    }

    if (!$aGenes) {
        return false;
    } elseif (!is_array($aGenes)) {
        $aGenes = array($aGenes);
    }

    // Check if this user have rights on this gene? It doesn't really matter that much, but still.
    foreach ($aGenes as $nKey => $sGene) {
        if (!lovd_isAuthorized('gene', $sGene)) {
            unset($aGenes[$nKey]);
        }
    }
    // So perhaps now no gene is left.
    if (!$aGenes) {
        return false;
    }

    // Just update the database and we'll see what happens.
    $q = $_DB->q('
        UPDATE ' . TABLE_GENES . '
        SET updated_by = ?, updated_date = NOW()
        WHERE id IN (?' . str_repeat(', ?', count($aGenes) - 1) . ')',
        array_merge(array((!$bAuth? 0 : $_AUTH['id'])), $aGenes), false);
    return ($q->rowCount());
}





function lovd_trimField ($sVal)
{
    // Trims data fields in an intelligent way. We don't just strip the quotes off, as this may effect quotes in the fields.
    // Instead, we check if the field is surrounded by quotes. If so, we take the first and last character off and return the field.

    $sVal = trim($sVal);
    if ($sVal && $sVal[0] == '"' && substr($sVal, -1) == '"') {
        $sVal = substr($sVal, 1, -1); // Just trim the first and last quote off, nothing else!
    }
    return trim($sVal);
}





function lovd_viewForm ($a,
                        $sHeaderPrefix = "\n          <TR valign=\"top\">\n            <TD class=\"{{ CLASS }}\">",
                        $sHeaderSuffix = '</TD>',
                        $sHelpPrefix   = "\n            <TD class=\"{{ CLASS }}\">",
                        $sHelpSuffix   = '</TD>',
                        $sDataPrefix   = "\n            <TD class=\"{{ CLASS }}\">",
                        $sDataSuffix   = '</TD></TR>',
                        $sNewLine      = '              ')
{
    // Based on a function provided by Ileos.nl.
    /***************************************************************************
     * Display HTML form according to input array containing settings and field
     * descriptions.
     *
     * Sytax for values of input array :
     *
     * array('GET|POST', 'header_class_name', 'help_class_name', 'data_class_name', 'header_width', 'help_width', 'data_width'),
     * 'skip',
     * 'hr',
     * array('print', '<text>'),
     * array('fieldset', '<id>', '<text>', '<open>:[true]|false'),
     * 'end_fieldset',
     * array('<header>', '<help_text>', 'print', '<text>'),
     * array('<header>', '<help_text>', 'note', '<text>'),
     * array('<header>', '<help_text>', 'text|password|file', '<field_name>', <field_size>),
     * array('<header>', '<help_text>', 'textarea', '<field_name>', <field_cols>, <field_rows>),
     * array('<header>', '<help_text>', 'select', '<field_name>', <field_size>, <data> (array, key => val|query, [0] => [1]), <select>:true|false|select_text, <multiple>:true|false, <select_all_link>:true|false|link_text),
     * array('<header>', '<help_text>', 'checkbox', '<field_name>'),
     * array('<header>', '<help_text>', 'submit', '<button_value>', '<field_name>'),
     *
     **********/

    // Options.
    list($sMethod, $sHeaderClass, $sHelpClass, $sDataClass, $sHeaderWidth, $sHelpWidth, $sDataWidth) = $a[0];

    // Method.
    if (!in_array($sMethod, array('GET', 'POST'))) {
        $sMethod = 'POST';
    }

    // Class names, widths are taken care of in the COLGROUP element (that doesn't support class name).
    $aCats = array('Header', 'Help', 'Data');
    foreach ($aCats as $sCat) {
        $sClass  = 's' . $sCat . 'Class';
        $sPrefix = 's' . $sCat . 'Prefix';
        if ($$sClass) {
            $$sPrefix = str_replace('{{ CLASS }}', $$sClass, $$sPrefix);
        } else {
            $$sPrefix = str_replace(' class="{{ CLASS }}"', '', $$sPrefix);
        }
    }
    // Table structure, use COLGROUP for the width to ensure table-layout:fixed gets the width correctly.
    $nFormWidth = 760;
    $sTable = '        <TABLE border="0" cellpadding="0" cellspacing="1" width="' . $nFormWidth . '" class="dataform">
          <COLGROUP>
            <COL width="' . $sHeaderWidth . '"></COL>
            <COL width="' . $sHelpWidth . '"></COL>
            <COL width="' . $sDataWidth . '"></COL>
          </COLGROUP>';





    // First: print the table.
    $bInFieldset = false;
    if (!(!empty($a[1][0]) && $a[1][0] == 'fieldset')) {
        // Table should only be printed when the first field is not a fieldset definition, that definition will close and open a new table.
        print($sTable);
    }

    // Now loop the array with fields, to print them on the screen.
    foreach ($a as $nKey => $aField) {
        if (!$nKey) {
            // Options, already read out.
            continue;

        } elseif (!is_array($aField)) {
            // Commands like "skip" and "hr" (horizontal rule).

            if ($aField == 'skip') {
                // Skip line.
                echo $sHeaderPrefix . '&nbsp;' . $sHeaderSuffix . $sHelpPrefix . '&nbsp;' .  $sHelpSuffix . $sDataPrefix . '&nbsp;' . $sDataSuffix;
                continue;
            } elseif ($aField == 'hr') {
                // Horizontal line (ruler).
                // This construction may not entirely be correct when this function is called with different prefixes & suffixes than the default ones.
                echo str_replace('<TD', '<TD colspan="3"', $sHeaderPrefix) . '<IMG src="gfx/trans.png" alt="" width="100%" height="1" class="form_hr">' . $sDataSuffix;
                continue;
            } elseif ($aField == 'end_fieldset' && $bInFieldset) {
                // End of fieldset. Only given when fieldset is open and no new fieldset should be opened.
                print('</TABLE>' . "\n" .
                      '        </FIELDSET>' . "\n" .
                      $sTable);
                $bInFieldset = false;
                continue;
            }

        } else {
            // Build some form content.

            if ($aField[0] == 'print') {
                // Print text.
                echo $aField[1] . "\n";
                continue;

            } elseif ($aField[0] == 'fieldset') {
                $bShow = !(isset($aField[3]) && $aField[3] === false);
                print('</TABLE>' . "\n");
                if ($bInFieldset && $nKey > 1) {
                    // $nKey needs to be > 1 because if fieldset is the first thing the form does, there is no opening table yet.
                    print('        </FIELDSET>' . "\n");
                }
                print('        <FIELDSET style="width : ' . ($nFormWidth + 4) . 'px;"><LEGEND style="margin-left : ' . $sHeaderWidth . ';"><B>' . $aField[2] . '</B> <SPAN class="S11">[<A href="#" id="' . $aField[1] . '_link" onClick="lovd_toggleVisibility(\'' . $aField[1] . '\'); return false;">' . ($bShow? 'Hide' : 'Show') . '</A>]</SPAN></LEGEND>' . "\n" .
                      preg_replace('/>/', ' id="' . $aField[1] . '"' . ($bShow? '' : ' style="display : none"') . '>', $sTable, 1));
                $bInFieldset = true;
                continue;
            }

            // Print the HTML parts and add the help button.
            print($sHeaderPrefix . $aField[0] . $sHeaderSuffix . $sHelpPrefix);
            if ($aField[2] == 'select' && $aField[7]) {
                $aField[1] .= ($aField[1]? '<BR><BR>' : '') . 'You can select a range of items by clicking the first item, holding "Shift" and clicking the last item in the range. Selecting/deselecting individual items can be done by holding "Ctrl" on a PC or "Command" on a Mac and clicking on the items.';
            }
            if (!empty($aField[1])) {
                // Somehow, we need the str_replace() because the htmlspecialchars() with ENT_QUOTES does not prevent JS errors due to single quotes.
                print('<IMG src="gfx/lovd_form_question.png" alt="" onmouseover="lovd_showToolTip(\'' . htmlspecialchars(str_replace("'", "\'", $aField[1])) . '\');" onmouseout="lovd_hideToolTip();" class="help" width="14" height="14">');
            } else {
                print('&nbsp;');
            }
            print($sHelpSuffix . $sDataPrefix);



            if (in_array($aField[2], array('print', 'note'))) {
                // Print text separated in header and field values.
                list($sHeader, $sHelp, $sType, $sPrint) = $aField;

                print(($sType == 'note'? '<SPAN class="form_note">' . $sPrint . '</SPAN>' : $sPrint) . $sDataSuffix);
                continue;



            } elseif (in_array($aField[2], array('text', 'file'))) {
                list($sHeader, $sHelp, $sType, $sName, $nSize) = $aField;
                if (!isset($GLOBALS['_' . $sMethod][$sName])) {
                    $GLOBALS['_' . $sMethod][$sName] = '';
                }

                print('<INPUT type="' . $sType . '" name="' . $sName . '" size="' . $nSize . '" value="' . htmlspecialchars($GLOBALS['_' . $sMethod][$sName]) . '"' . (!lovd_errorFindField($sName)? '' : ' class="err"') . '>' . $sDataSuffix);
                continue;


            } elseif ($aField[2] == 'password') {
                // Add default values to any missing entries at the end of the field array.
                $aFieldComplete = array_pad($aField, 6, false);
                list( , , $sType, $sName, $nSize, $bBlockAutofillPass) = $aFieldComplete;

                if (!isset($GLOBALS['_' . $sMethod][$sName])) {
                    $GLOBALS['_' . $sMethod][$sName] = '';
                }

                // Setup password field attributes.
                $sFieldAtts = ' type="' . $sType . '" name="' . $sName . '" size="' . $nSize . '"';
                if ($bBlockAutofillPass) {
                    // Block editing of the actual password field until JS onFocus event.
                    $sFieldAtts .= ' readonly onfocus="this.removeAttribute(\'readonly\');"';
                }

                // Output a hidden text field before password field, to catch a possible
                // mistaken automatic fill of a username.
                print('<INPUT type="text" name="fake_username" style="width:0; margin:-3px; padding:0; visibility: hidden" />' . PHP_EOL);
                // Print indentation for new line.
                print($sNewLine);

                print('<INPUT' . $sFieldAtts . ' value="' . htmlspecialchars(
                        $GLOBALS['_' . $sMethod][$sName]) . '"' . (!lovd_errorFindField($sName)?
                        '' : ' class="err"') . '>' . $sDataSuffix);
                continue;



            } elseif ($aField[2] == 'textarea') {
                list($sHeader, $sHelp, $sType, $sName, $nCols, $nRows) = $aField;
                if (!isset($GLOBALS['_' . $sMethod][$sName])) { $GLOBALS['_' . $sMethod][$sName] = ''; }

                print('<TEXTAREA name="' . $sName . '" cols="' . $nCols . '" rows="' . $nRows . '"' . (!lovd_errorFindField($sName)? '' : ' class="err"') . '>' . htmlspecialchars($GLOBALS['_' . $sMethod][$sName]) . '</TEXTAREA>' . $sDataSuffix);
                continue;



            } elseif ($aField[2] == 'select') {
                list($sHeader, $sHelp, $sType, $sName, $nSize, $oData, $sSelect, $bMultiple, $bSelectAll) = $aField;
                if (!isset($GLOBALS['_' . $sMethod][$sName])) { $GLOBALS['_' . $sMethod][$sName] = ''; }

                print('<SELECT name="' . $sName . ($bMultiple? '[]' : '') . '"' . ($bMultiple == true || $nSize != 1? ' size="' . $nSize . '"' : '') . ($bMultiple? ' multiple' : '') . '' . (!lovd_errorFindField($sName)? '' : ' class="err"') . '>');

                if ($sSelect) {
                    // Print the first 'select' element, by default valued '-- select --'.
                    $bSelected = ((!$bMultiple && !$GLOBALS['_' . $sMethod][$sName]) || ($bMultiple && is_array($GLOBALS['_' . $sMethod][$sName]) && !count($GLOBALS['_' . $sMethod][$sName])));
                    print("\n" . $sNewLine . '  <OPTION value=""' . ($bSelected? ' selected' : '') . '>' . ($sSelect === "true" || $sSelect === true? '-- select --' : $sSelect) . '</OPTION>');
                }

                if (is_array($oData)) {
                    // Array input.
                    $bInOptGroup = false; // Used to determine if we are in an option group.
                    foreach ($oData as $key => $val) {
                        // Create option groups for select boxes.
                        if (substr($key, 0, 8) == 'optgroup') {
                            // This handles the creation of option groups.
                            // To add option groups include array values above each group as follows array('optgroup1' => 'Group 1 Name').

                            // If we are in an option group then we need to close it before we start a new option group.
                            print(($bInOptGroup? '' : "\n" . $sNewLine . '</OPTGROUP>') . "\n" .
                                  $sNewLine . '  <OPTGROUP label="' . htmlspecialchars($val) . '">');
                            $bInOptGroup = true;
                        } else {
                            // We have to cast the $key to string because PHP made integers of them, if they were integer strings.
                            $bSelected = ((!$bMultiple && (string) $GLOBALS['_' . $sMethod][$sName] === (string) $key) || ($bMultiple && is_array($GLOBALS['_' . $sMethod][$sName]) && in_array((string) $key, $GLOBALS['_' . $sMethod][$sName], true)));
                            print("\n" . $sNewLine . '  <OPTION value="' . htmlspecialchars($key) . '"' . ($bSelected? ' selected' : '') . '>' . htmlspecialchars($val) . '</OPTION>');
                        }
                    }
                    // If we are still in an option group then lets close it.
                    print($bInOptGroup? '' : "\n" . $sNewLine . '</OPTGROUP>');
                }

                print('</SELECT>');

                // Select all link.
                if ($bMultiple && $bSelectAll) {
                    print('&nbsp;<A href="#" onclick="$(this.previousSibling.previousSibling).children().each(function(){$(this).prop(\'selected\', true);}); $(this.previousSibling.previousSibling).change(); return false">Select&nbsp;all</A>');
                }

                print($sDataSuffix);
                continue;



            } elseif ($aField[2] == 'checkbox') {
                list($sHeader, $sHelp, $sType, $sName) = $aField;
                if (!isset($GLOBALS['_' . $sMethod][$sName])) { $GLOBALS['_' . $sMethod][$sName] = ''; }

                print('<INPUT type="checkbox" name="' . $sName . '" value="1"' . ($GLOBALS['_' . $sMethod][$sName]? ' checked' : '') . ' style="margin-top : 4px; border : 0px;"' . (!lovd_errorFindField($sName)? '' : ' class="err"') . '>' . $sDataSuffix);
                continue;



            } elseif ($aField[2] == 'submit') {
                print('<INPUT type="submit"' . (isset($aField[4])? ' name="' . $aField[4] . '"' : '') . ' value="' . $aField[3] . '">' . $sDataSuffix);
                continue;
            }
        }
    }
    print('</TABLE>');
    if ($bInFieldset) {
        print('</FIELDSET>');
    }
}





function lovd_wrapText ($s, $l = 70, $sCut = ' ')
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Wraps a text to a certain length.

    if (!$s) {
        // When we receive NULL, just return an empty string.
        return '';
    } elseif (strlen($s) <= $l) {
        // No work needed.
        return $s;
    }

    if (empty($sCut) || !is_string($sCut)) {
        $sCut = ' ';
    } elseif (strlen($sCut) > 1) {
        $sCut = $sCut[0];
    }
    if ($sCut != ' ') {
        // If it's not a space, we will add it to the end of each line as well, so we use extra space.
        // If word has no length, this may make lovd_wrapText() wrap at $l - 1;
        $l --;
    }
    $aCutAlt = array('-', ';', ',', ':', ')', '(', '&', '*', '>', '<');
    $lLine = 0;
    $a = preg_split("/\r?\n/", $s);
    $s = '';

    foreach ($a as $nLine => $sLine) {
        // Loop per input line.
        $aWords = explode($sCut, $sLine);

        foreach ($aWords as $nWord => $sWord) {
            // Loop per word on this input line.
            $lWord = strlen($sWord);

            // If there is only one word on this output line, but it does not fit, we need to cut it some other way.
            if ((!$s || substr($s, -1) == "\n") && $lWord > $l) {
                // We're at the first word of the output line, but it's too long!
                foreach ($aCutAlt as $sCutAlt) {
                    if (substr_count($sWord, $sCutAlt)) {
                        // But we found an alternative cutting character.
                        $s .= lovd_wrapText($sWord, $l, $sCutAlt);
                        $lLine = strlen(strrchr($s, "\n")) - 1;
                        continue 2;
                    }
                }
            }

            if (!$nWord) {
                // The first word of the input line, which is not too long (that has been checked right above here).
                $s .= $sWord;
                $lLine = $lWord;

            } else {
                // Not the first word of the input line.
                if (($lLine + 1 + $lWord + ($sCut != ' '? 1 : 0)) <= $l) {
                    // This fits.
                    $s .= $sCut . $sWord;
                    $lLine += (1 + $lWord);

                } else {
                    // This does not fit! Only stick $sCut to the end of the line, if it's not a space.
                    $s .= ($sCut != ' '? $sCut : '') . "\n" . lovd_wrapText($sWord, $l, $sCut);
                    $lLine = strlen(strrchr($s, "\n")) - 1;
                }
            }
        }

        // Are there lines left?
        if (isset($a[$nLine + 1])) {
            $s .= "\n";
        }
    }

    return $s;
}





function utf8_encode_array ($Data)
{
    // Recursively loop array to encode values.

    if (!is_array($Data)) {
        return utf8_encode($Data);
    } else {
        foreach ($Data as $key => $val) {
            $Data[$key] = utf8_encode_array($val);
        }
        return $Data;
    }
}
?>
