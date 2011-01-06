<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2011-01-06
 * For LOVD    : 3.0-pre-13
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *             : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

/*
DMD_SPECIFIC
function lovd_checkDBID ($sGene, $sVariant, $sMutationCol = 'Variant/DNA', $sDBID, $nIDtoIgnore = 0)
{
    // Checks if given $sVariant and $sDBID match. I.e., whether or not there is
    // already an entry where this variant and DBID come together.
    // NOTE: We're assuming that the DBID field actually exists. Using this
    // function implies you've checked for it's presence.
    // 2008-06-25; 2.0-08; All checks ignore the current variant, if the ID is given.

    // 2009-06-11; 2.0-19; GENE_00000 is always allowed.
    if ($sDBID == substr($sGene, 0, strpos($sGene . '_', '_')) . '_00000') {
        return true;
    }

    $sVariant = str_replace(array('(', ')', '?'), '', $sVariant);
    // Variant/DBID combo already exists?
    list($n) = @mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM `' . TABLEPREFIX . '_' . mysql_real_escape_string($sGene) . '_variants` WHERE REPLACE(REPLACE(REPLACE(`' . $sMutationCol . '`, "(", ""), ")", ""), "?", "") = "' . $sVariant . '" AND `Variant/DBID` LIKE "' . $sDBID . '%"' . ($nIDtoIgnore? ' AND variantid != "' . $nIDtoIgnore . '"' : '')));
    if (!$n) {
        // Check if the chosen ID is empty, then.
        list($n) = @mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM `' . TABLEPREFIX . '_' . mysql_real_escape_string($sGene) . '_variants` WHERE `Variant/DBID` LIKE "' . $sDBID . '%"' . ($nIDtoIgnore? ' AND variantid != "' . $nIDtoIgnore . '"' : '')));
        if ($n) {
            return false;
        }
    }
    return true;
}
*/





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

    if (is_array($aInput)) {
        foreach ($aInput as $key => $val) {
            if (is_array($val)) {
                lovd_checkXSS($val);
            } elseif (!empty($val) && preg_match('/<.*>/', $val)) {
                // Disallowed tag found.
                lovd_errorAdd($key, 'Disallowed tag found in form field' . (is_numeric($key)? '.' : ' "' . $key . '".') . ' XSS attack?');
            }
        }
        return true;
    }

    return false;
}





function lovd_error ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Tells the program whether or not we've had an error.
    global $_ERROR;

    return (count($_ERROR['messages']) > 1);
}





function lovd_errorAdd ($sField, $sError)
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Add error to error variable.
    global $_ERROR;
    
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
    // Returns index of whether or not a certain form field has an error or not.
    global $_ERROR;
    return @array_search($sField, $_ERROR['fields']);
}





function lovd_errorPrint ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Prints error variable.
    global $_ERROR;

    if (count($_ERROR['messages']) > 1) {
        unset($_ERROR['messages'][0]);
        print('      <DIV class="err">' . "\n" .
              '        ' . implode('<BR>' . "\n" . '        ', $_ERROR['messages']) . '</DIV><BR>' . "\n\n");
    }
}





/*
DMD_SPECIFIC
function lovd_fetchDBID ($sGene, $sVariant, $sMutationCol = 'Variant/DNA')
{
    // Searches through the $sGene variants to fetch lowest DBID belonging to
    // this variant, otherwise returns next variant ID not in use.
    // NOTE: We're assuming that the DBID field actually exists. Using this
    // function implies you've checked for it's presence.

    $sSymb = substr($sGene, 0, strpos($sGene . '_', '_'));
    $lID = strlen($sSymb) + 6;
    $sVariant = str_replace(array('(', ')', '?'), '', $sVariant);
    // 2008-06-27; 2.0-08; Drop the regexp check. The field either contains an ID or not, and the actual regexp is much more extensive anyway!
    list($sVariantDB, $sID) = @mysql_fetch_row(mysql_query('SELECT DISTINCT `' . $sMutationCol . '`, `Variant/DBID` FROM `' . TABLEPREFIX . '_' . mysql_real_escape_string($sGene) . '_variants` WHERE REPLACE(REPLACE(REPLACE(`' . $sMutationCol . '`, "(", ""), ")", ""), "?", "") = "' . $sVariant . '" AND `Variant/DBID` != "" ORDER BY `Variant/DBID`'));
    if (empty($sVariantDB)) {
        // Nieuwe!
        list($sID) = @mysql_fetch_row(mysql_query('SELECT MAX(LEFT(`Variant/DBID`, ' . $lID . ')) FROM `' . TABLEPREFIX . '_' . mysql_real_escape_string($sGene) . '_variants` WHERE LEFT(`Variant/DBID`, ' . $lID . ') REGEXP "^' . $sSymb . '_[0-9]{5}"'));
        if (!$sID) {
            $sID = $sSymb . '_00001';
        } else {
            $nID = substr($sID, -5) + 1;
            $sID = $sSymb . '_' . str_pad($nID, 5, '0', STR_PAD_LEFT);
        }
    } else {
        // 2009-08-26; 2.0-21; Select the first so-called word of the Variant/DBID field
        // We're assuming here that the start of the DBID field will always be the ID, like the column's default RegExp forces.
        preg_match('/^(\w+)\b/', $sID, $aMatches);
        $sID = $aMatches[1];
    }
    return $sID;
}
*/





function lovd_getColumnData ($sTable)
{
    // Gets and returns the column data for a certain table.
    static $aTableCols = array();

    if (empty($aTableCols[$sTable])) {
        $q = mysql_query('SHOW COLUMNS FROM ' . $sTable);
        if (!$q) {
            // Table does not exist.
            return false;
        }
        $aTableCols[$sTable] = array();
        while ($z = @mysql_fetch_assoc($q)) {
            $aTableCols[$sTable][$z['Field']] =
                     array(
                            'type' => $z['Type'],
                            'null' => $z['Null'],
                            'default' => $z['Default'],
                          );
        }
    }
    
    return $aTableCols[$sTable];
}





function lovd_getColumnLength ($sTable, $sCol)
{
    // Determines the column lengths for a given table and column.
    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/(CHAR|INT)\(([0-9]+)\)/i', $sColType, $aRegs)) {
            return (int) $aRegs[2];

        } elseif (preg_match('/^DATE(TIME)?/i', $sColType, $aRegs)) {
            return (10 + (empty($aRegs[1])? 0 : 9));

        } elseif (preg_match('/^DEC\(([0-9]+),([0-9]+)\)/i', $sColType, $aRegs)) {
            return (int) $aRegs[1];

        } elseif (preg_match('/^(TINY|MEDIUM|LONG)?(TEXT|BLOB)/i', $sColType, $aRegs)) {
            switch ($aRegs[1]) { // Key [1] must exist, because $aRegs[2] exists.
                case 'TINY':
                    return 255;
                case 'MEDIUM':
                    return 16777215;
                case 'LONG':
                    return 4294967295;
                default:
                    return 65535;
            }
        }
    }

    return 0;
}





/*
function lovd_getColumnMaxValue ($sTable, $sCol)
{
    // Determines the column's maximum value for numeric columns.
    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/^DEC\(([0-9]+),([0-9]+)\)/i', $sColType, $aRegs)) {
            return (float) (str_repeat('9', $aRegs[1] - $aRegs[2]) . '.' . str_repeat('9', $aRegs[2]));

        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?(INT)/i', $sColType, $aRegs)) {
            switch ($aRegs[1]) { // Key [1] must exist, because $aRegs[2] exists.
                case 'TINY':
                    return 255; // 2^8; 1 byte
                case 'SMALL':
                    return 65535; // 2^16; 2 bytes
                case 'MEDIUM':
                    return 16777215; // 2^24; 3 bytes
                case 'BIG':
                    return 18446744073709551615; // 2^64; 8 bytes
                default:
                    return 4294967295; // 2^32; 4 bytes
            }
        }
    }

    return 0;
}
*/





function lovd_getColumnType ($sTable, $sCol)
{
    // Determines the column type for a given table and column.
    $aTableCols = lovd_getColumnData($sTable);

    if (!empty($aTableCols[$sCol])) {
        // Table && col exist.
        $sColType = $aTableCols[$sCol]['type'];

        if (preg_match('/^(TINY|MEDIUM|LONG)?(BLOB)/i', $sColType)) {
            return 'BLOB';
        } elseif (preg_match('/^DATE/i', $sColType)) {
            return 'DATE';
        } elseif (preg_match('/^DATETIME/i', $sColType)) {
            return 'DATETIME';
        } elseif (preg_match('/^DEC\([0-9]+,[0-9]+\)/i', $sColType)) {
            return 'DEC';
        } elseif (preg_match('/^((VAR)?CHAR|(TINY|MEDIUM|LONG)?TEXT)/i', $sColType)) {
            return 'TEXT';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\([0-9]+\) UNSIGNED/i', $sColType)) {
            return 'INT_UNSIGNED';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\([0-9]+\)/i', $sColType)) {
            return 'INT';
        }
    }
    return false;
}






// DMD_SPECIFIC
function lovd_matchDate ($s, $bTime = false)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the date pattern, one that MySQL can understand.
    // 2009-06-18; 2.0-19; Added $bTime flag to check DATETIME column types.
    return (preg_match('/^[0-9]{4}[.\/-][0-9]{2}[.\/-][0-9]{2}' . ($bTime? ' [0-2][0-9]\:[0-5][0-9]\:[0-5][0-9]' : '') . '$/', $s));
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
    return (preg_match('/^(ht|f)tps?:\/\/([0-9]{1,3}(\.[0-9]{1,3}){3}|(([0-9a-z][-0-9a-z]*[0-9a-z]|[0-9a-z])' . ($bAllowCustomHosts? '' : '\.') . ')+[a-z]{2,6})\/?[%&=#0-9a-z\/._+-]*\??.*$/i', $s));
}





function lovd_matchUsername ($s)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the username pattern (non standard).
    return (preg_match('/^[A-Z][A-Z0-9_.-]{3,19}$/i', $s));
}





/*
DMD_SPECIFIC
function lovd_setUpdatedDate ($sGene)
{
    // Updates the updated_date field of the indicated gene.
    global $_AUTH;

    // Does this user have rights on this gene? It doesn't really matter that much, but still.
    if (lovd_isCurator($sGene)) {
        // Just update the database and we'll see what happens.
        @mysql_query('UPDATE ' . TABLE_DBS . ' SET updated_by = "' . $_AUTH['userid'] . '", updated_date = NOW() WHERE symbol = "' . $sGene . '"');
        if (mysql_affected_rows() > 0) {
            return true;
        }
    }

    return false;
}
*/





function lovd_viewForm ($a,
                        $sHeaderPrefix = "\n          <TR valign=\"top\">\n            <TD class=\"{{ CLASS }}\" width=\"{{ WIDTH }}\">",
                        $sHeaderSuffix = '</TD>',
                        $sHelpPrefix   = "\n            <TD class=\"{{ CLASS }}\" width=\"{{ WIDTH }}\">",
                        $sHelpSuffix   = '</TD>',
                        $sDataPrefix   = "\n            <TD class=\"{{ CLASS }}\" width=\"{{ WIDTH }}\">",
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

    // Class names and widths.
    $aCats = array('Header', 'Help', 'Data');
    foreach ($aCats as $sCat) {
        $sClass  = 's' . $sCat . 'Class';
        $sPrefix = 's' . $sCat . 'Prefix';
        $sWidth  = 's' . $sCat . 'Width';
        if ($$sClass) {
            $$sPrefix = str_replace('{{ CLASS }}', $$sClass, $$sPrefix);
        } else {
            $$sPrefix = str_replace(' class="{{ CLASS }}"', '', $$sPrefix);
        }

        if ($$sWidth) {
            $$sPrefix = str_replace('{{ WIDTH }}', $$sWidth, $$sPrefix);
        } else {
            $$sPrefix = str_replace(' width="{{ WIDTH }}"', '', $$sPrefix);
        }
    }





    // First: print the table.
    $bInFieldset = false;
    $nFormWidth = 760;
    if (!(!empty($a[1][0]) && $a[1][0] == 'fieldset')) {
        // Table should only be printed when the first field is not a fieldset definition, that definition will close and open a new table.
        print('        <TABLE border="0" cellpadding="0" cellspacing="1" width="' . $nFormWidth . '">');
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
                echo str_replace(' width="' . $sHeaderWidth . '"', '', str_replace('<TD', '<TD colspan="3"', $sHeaderPrefix)) . '<IMG src="' . ROOT_PATH . 'gfx/trans.png" alt="" width="100%" height="1" class="form_hr">' . $sDataSuffix;
                continue;
            } elseif ($aField == 'end_fieldset' && $bInFieldset) {
                // End of fieldset. Only given when fieldset is open and no new fieldset should be opened.
                print('</TABLE>' . "\n" .
                      '        </FIELDSET>' . "\n" .
                      '        <TABLE border="0" cellpadding="0" cellspacing="1" width="' . $nFormWidth . '">');
                $nInFieldset = false;
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
                print('        <FIELDSET style="width : ' . $nFormWidth . 'px;"><LEGEND><B>' . $aField[2] . '</B> <SPAN class="S11">[<A href="#" id="' . $aField[1] . '_link" onClick="lovd_toggleVisibility(\'' . $aField[1] . '\'); return false;">' . ($bShow? 'Hide' : 'Show') . '</A>]</SPAN></LEGEND>' . "\n" .
                      '        <TABLE border="0" cellpadding="0" cellspacing="1" width="' . $nFormWidth . '" id="' . $aField[1] . '"' . ($bShow? '' : ' style="display : none"') . '>');
                $bInFieldset = true;
                continue;
            }

            // Print the HTML parts and add the help button.
            print($sHeaderPrefix . $aField[0] . $sHeaderSuffix . $sHelpPrefix);
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



            } elseif (in_array($aField[2], array('text', 'password', 'file'))) {
                list($sHeader, $sHelp, $sType, $sName, $nSize) = $aField;
                if (!isset($GLOBALS['_' . $sMethod][$sName])) { $GLOBALS['_' . $sMethod][$sName] = ''; }

                print('<INPUT type="' . $sType . '" name="' . $sName . '" size="' . $nSize . '" value="' . htmlspecialchars($GLOBALS['_' . $sMethod][$sName]) . '"' . (!lovd_errorFindField($sName)? '' : ' class="err"') . '>' . $sDataSuffix);
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
                    foreach ($oData as $key => $val) {
                        $bSelected = ((!$bMultiple && $GLOBALS['_' . $sMethod][$sName] == $key) || ($bMultiple && is_array($GLOBALS['_' . $sMethod][$sName]) && in_array($key, $GLOBALS['_' . $sMethod][$sName])));
                        print("\n" . $sNewLine . '  <OPTION value="' . htmlspecialchars($key) . '"' . ($bSelected? ' selected' : '') . '>' . htmlspecialchars($val) . '</OPTION>');
                    }

                } elseif(is_resource($oData)) {
                    // Query input.
                    while (list($key, $val) = mysql_fetch_row($oData)) {
                        $bSelected = ((!$bMultiple && $GLOBALS['_' . $sMethod][$sName] == $key) || ($bMultiple && is_array($GLOBALS['_' . $sMethod][$sName]) && in_array($key, $GLOBALS['_' . $sMethod][$sName])));
                        print("\n" . $sNewLine . '  <OPTION value="' . htmlspecialchars($key) . '"' . ($bSelected? ' selected' : '') . '>' . htmlspecialchars($val) . '</OPTION>');
                    }
                }
                print('</SELECT>');

                // Select all link.
                if ($bSelectAll) {
                    // FIXME; beware that if this is not working if there is more than 1 form. Add form ID to the function?
                    print('&nbsp;<A href="#" onclick="var list = document.forms[0][\'' . $sName . '[]\']; for (i=0;i<list.options.length;i++) { list.options[i].selected = true; }; return false">Select all</A>');
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





/*
function lovd_wrapText ($s, $l = 80, $sCut = ' ')
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Wraps a text to a certain length.

    if (empty($sCut) || !is_string($sCut)) {
        $sCut = ' ';
    } elseif (strlen($sCut) > 1) {
        $sCut = $sCut{0};
    }
    if ($sCut != ' ') {
        // If it's not a space, we will add it to the end of each line as well, so we use extra space.
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
*/
?>
