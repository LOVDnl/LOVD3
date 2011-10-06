<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-21
 * Modified    : 2011-10-05
 * For LOVD    : 3.0-alpha-05
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

    if (!is_array($aInput)) {
        $aInput = array($aInput);
    }

    $bSuccess = true;
    foreach ($aInput as $key => $val) {
        if (is_array($val)) {
            $bSuccess = $bSuccess && lovd_checkXSS($val);
        } elseif (!empty($val) && preg_match('/<.*>/s', $val)) {
            // Disallowed tag found.
            $bSuccess = false;
            lovd_errorAdd($key, 'Disallowed tag found in form field' . (is_numeric($key)? '.' : ' "' . $key . '".') . ' XSS attack?');
        }
    }
    return $bSuccess;
}





function lovd_emailError ($sErrorCode, $sType, $bHalt = false)
{
    // Formats email errors for the error log, and optionally halts the system.

    // Format the error message.
    // FIXME; Kan makkelijker???
    $sError = preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in code block ' . $sErrorCode . '.' . "\n" .
              'Email type : ' . $sType;

    // If the system needs to be halted, send it through to lovd_displayError() who will print it on the screen,
    // write it to the system log, and halt the system. Otherwise, just log it to the database.
    if ($bHalt) {
        lovd_displayError('Email', $sError);
    } else {
        lovd_writeLog('Error', 'Email', $sError);
    }
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
    // Returns index of whether or not a certain form field has an error.
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





function lovd_formatMail ($aBody)
{
    // Returns a formatted body to send to the user.
    // Format:
    // $aBody = array(
    //                'message' => 'Standard introduction message to the user',
    //                'topic_title' => array(
    //                                       'data_source' => 'variableName',
    //                                       'key' => 'valueHeader',
    //                                       'key' => 'valueHeader',
    //                                      )
    //               );

    if (empty($aBody) || !is_array($aBody)) {
        return false;
    }

    $sBody = $aBody['message'];
    unset($aBody['message']);
    if (count($aBody)) {
        foreach($aBody as $sTopic => $aContent) {
            $sSource = $aContent['data_source'];
            unset($aContent['data_source']);

            $sBody .= str_repeat('-', 70) . "\n" .
                      '  ' . strtoupper(str_replace('_', ' ', $sTopic))  . "\n" .
                      str_repeat('-', 70) . "\n";

            // Padding to...
            $lPad = 0;
            foreach ($aContent as $val) {
                $l = strlen($val);
                if ($l > $lPad) {
                    $lPad = $l;
                }
            }

            foreach ($aContent as $key => $val) {
                $sBody .= str_pad($val, $lPad) . ' : ' . str_replace("\n", "\n" . str_repeat(' ', $lPad + 3), lovd_wrapText($GLOBALS[$sSource][$key], 70 - $lPad - 3)) . "\n";
            }
            $sBody .= str_repeat('-', 70) . "\n\n";
        }
    }

    return $sBody;
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





function lovd_matchDate ($s, $bTime = false)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Matches a string to the date pattern, one that MySQL can understand.
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





function lovd_sendMail ($aTo, $sSubject, $sBody, $sHeaders, $bFwdAdmin = true)
{
    // Format:
    // $aTo = array(
    //              array('Name', "Email\r\nEmail\r\nEmail"),
    //              array('Name', "Email\r\nEmail")
    //             );

    global $_SETT, $_CONF;

    $sTo = '';
    foreach ($aTo as $aRecipient) {
        list($sName, $sEmails) = array_values($aRecipient);
        $aEmails = explode("\r\n", $sEmails);
        foreach ($aEmails as $sEmail) {
            $sTo .= (ON_WINDOWS? '' : '"' . trim($sName) . '" ') . '<' . trim($sEmail) . '>, ';
        }
    }
    $sTo = rtrim($sTo, ', ');
    $sBody = lovd_wrapText($sBody);

    $bSafeMode = ini_get('safe_mode');

    if (!$bSafeMode) {
        $bMail = @mail($sTo, $sSubject, $sBody, $sHeaders, '-f ' . $_CONF['email_address']);
    } else {
        $bMail = @mail($sTo, $sSubject, $sBody, $sHeaders);
    }

    if ($bMail && $bFwdAdmin) {
        $sBody = preg_replace('/^(Password[\s*]+: ).+/m', "$1" . '<password hidden>', $sBody);
        $sBody = 'Dear ' . $_SETT['admin']['name'] . ",\n\n" .
                 'As requested, a copy of the message I\'ve just sent.' . "\n\n" .
                 str_repeat('-', 25) . ' Forwarded  Message ' . str_repeat('-', 25) . "\n\n" .
                 rtrim($sBody) . "\n\n" .
                 str_repeat('-', 22) . ' End of Forwarded Message ' . str_repeat('-', 22) . "\n";
        return lovd_sendMail(array($_SETT['admin']), 'FW: ' . $sSubject, $sBody, $_SETT['email_headers'], false);
    } elseif (!$bMail) {
        lovd_writeLog('Error', 'SendMail', preg_replace('/^' . preg_quote(rtrim(lovd_getInstallURL(false), '/'), '/') . '/', '', $_SERVER['REQUEST_URI']) . ' returned error in code block ' . LOG_EVENT . '.' . "\n" .
                                           'Error : Couldn\'t send a mail with subject ' . $sSubject . ' to ' . $sTo);
    }

    return $bMail;
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
        @mysql_query('UPDATE ' . TABLE_DBS . ' SET updated_by = "' . $_AUTH['id'] . '", updated_date = NOW() WHERE id = "' . $sGene . '"');
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
                print('        <FIELDSET style="width : ' . ($nFormWidth + 4) . 'px;"><LEGEND style="margin-left : ' . $sHeaderWidth . ';"><B>' . $aField[2] . '</B> <SPAN class="S11">[<A href="#" id="' . $aField[1] . '_link" onClick="lovd_toggleVisibility(\'' . $aField[1] . '\'); return false;">' . ($bShow? 'Hide' : 'Show') . '</A>]</SPAN></LEGEND>' . "\n" .
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
                if ($bMultiple && $bSelectAll) {                    
                    print('&nbsp;<A href="#" onclick="var list = this.previousSibling.previousSibling; for (i=0;i<list.options.length;i++) { list.options[i].selected = true; }; return false">Select&nbsp;all</A>');
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

    if (empty($sCut) || !is_string($sCut)) {
        $sCut = ' ';
    } elseif (strlen($sCut) > 1) {
        $sCut = $sCut{0};
    }
    if ($sCut != ' ') {
        // If it's not a space, we will add it to the end of each line as well, so we use extra space.
        // If word has no length, this may lovd_wrapText wrap at $l - 1;
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
?>
