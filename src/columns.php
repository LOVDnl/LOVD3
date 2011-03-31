<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-04
 * Modified    : 2011-03-17
 * For LOVD    : 3.0-pre-18
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (empty($_PATH_ELEMENTS[2]) && !ACTION) {
    // URL: /columns
    // URL: /columns/(VariantOnGenome|VariantOnTranscript|Patient|...)
    // View all columns.

    if (!empty($_PATH_ELEMENTS[1])) {
        // Category given.
        $aCol = explode('/', $_PATH_ELEMENTS[1], 2);
        $_GET['search_category'] = $aCol[0];
        if (!empty($aCol[1])) { // This is possible, when the / was URL encoded and therefore not recognized as a separator for $_PATH_ELEMENTS.
            $_GET['search_colid'] = $aCol[1]; // FIXME; this doesn't work. Such calls generate an 404 for some reason.
        }
    }

    define('PAGE_TITLE', 'Browse custom data columns');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Require curator clearance.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    if ($_DATA->getCount()) {
        lovd_showInfoTable('Please note that these are all columns available in this LOVD installation. This is not the list of columns actually added to the system. Also, modifications made to the columns added to the system are not shown.', 'information', 950);
    }
    $_DATA->viewList();

/*
// DMD_SPECIFIC
// TEST $AUTH level
    // 2009-07-10; 2.0-20; Change the default order of the Variant columns for new genes.
    print('      <BUTTON onclick="javascript:lovd_openWindow(\'' . $_SERVER['PHP_SELF'] . '?action=order\', \'ColOrderDefault\', 550, 375);"' . ($nTotal > 1? '' : ' style="color : #999999;" disabled') . '>Change default column order (for new genes)</BUTTON><BR>' . "\n");
*/

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[2]) && !ACTION) {
    // URL: /columns/Variant/DNA
    // View specific column.

    $aCol = $_PATH_ELEMENTS;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);

    define('PAGE_TITLE', 'View custom data column ' . $sColumnID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Require curator clearance.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    $zData = $_DATA->viewEntry($sColumnID);
    $aTableInfo = lovd_getTableInfoByCategory($zData['category']);

    $sNavigation = '';
    if ($_AUTH['level'] >= LEVEL_MANAGER || ($aTableInfo['shared'] && $_AUTH['level'] >= LEVEL_CURATOR)) {
        // Authorized user (admin or manager, or curator in case of shared column) is logged in. Provide tools.
        if (!$zData['active'] || $aTableInfo['shared']) {
            // FIXME; needs exact check if there are genes/diseases left that do not have this column.
            // A check on 'active' is way too simple and does not work for shared columns.
            $sNavigation = '<A href="columns/' . $zData['id'] . '?add">Enable column</A>';
        } else {
            $sNavigation = '<A style="color : #999999;">Enable column</A>';
        }
//        // Remove column.
//        if ($zData['active']) {
//            $sNavigation .= ' | <A href="columns/' . $zData['id'] . '?remove">Disable column</A>';
//        } else {
//            $sNavigation .= ' | <A style="color : #999999;">Disable column</A>';
//        }
        $sNavigation .= ' | <A href="columns/' . $zData['id'] . '?edit">Edit custom data column settings</A>';
/*

        // Drop global column.
        $bSelected = true;
        if (substr($zData['colid'], 0, 7) == 'Variant') {
            // Check genes to find if column is active.
            $aGenes = lovd_getGeneList();
            foreach ($aGenes as $sSymbol) {
                list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLEPREFIX . '_' . $sSymbol . '_columns WHERE colid = "' . $zData['colid'] . '"'));
                if ($bSelected) {
                    // Column present in this gene.
                    break;
                }
            }
        } elseif (substr($zData['colid'], 0, 7) == 'Patient') {
            // Patient column.
            list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"'));
        }

        if ($zData['created_by'] && !$bSelected) {
            $sNavigation .= ' | <A href="' . $_SERVER['PHP_SELF'] . '?action=edit_colid&amp;edit_colid=' . rawurlencode($zData['colid']) . '">Edit column ID</A>';
            $sNavigation .= ' | <A href="' . $_SERVER['PHP_SELF'] . '?action=drop&amp;drop=' . rawurlencode($zData['colid']) . '">Delete column</A>';
        } else {
            $sNavigation .= ' | <A style="color : #999999;">Edit column ID</A>';
            $sNavigation .= ' | <A style="color : #999999;">Delete column</A>';
        }
*/
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





/*******************************************************************************
// take from new LOVD 2.0 curator sort???
if ($_GET['action'] == 'order') {
    // 2009-07-10; 2.0-20; Change the default order of the Variant columns for new genes.
    // Change column order.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

    $qData = mysql_query('SELECT colid, CONCAT(colid, " (", head_column, ")") AS name FROM ' . TABLE_COLS . ' WHERE LEFT(colid, 8) = "Variant/" AND (hgvs = 1 OR standard = 1) ORDER BY col_order');
    $nData = mysql_num_rows($qData);

    if (!$nData) {
        // Can this actually happen?
        require ROOT_PATH . 'inc-top-clean.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('There are no custom columns!', 'information');
        require ROOT_PATH . 'inc-bot-clean.php';
        exit;
    }

    if (isset($_GET['sent']) && count($_POST['columns']) == $nData) {
        foreach ($_POST['columns'] as $nOrderID => $sColID) {
            // Query text.
            $sQ = 'UPDATE ' . TABLE_COLS . ' SET col_order = ' . $nOrderID . ' WHERE colid = "' . $sColID . '"';

            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top-clean.php';
                lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
                lovd_dbFout('ColOrderDefault' . str_pad($nOrderID + 1, 2, '0', STR_PAD_LEFT), $sQ, $sError);
                require 'inc-bot-clean.php';
                exit;
            }
        }

        // Thank the user...
        require ROOT_PATH . 'inc-top-clean.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        print('      Default column order has successfully been set!<BR><BR>' . "\n\n");
        print('      <SCRIPT type="text/javascript">' . "\n" .
              '        <!--' . "\n" .
              '        setTimeout("self.close()", 1000);' . "\n" .
              '        // --> ' . "\n" .
              '      </SCRIPT>' . "\n");

        require ROOT_PATH . 'inc-bot-clean.php';
        exit;
    }



    require ROOT_PATH . 'inc-top-clean.php';
    lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
    lovd_showInfoTable('These are all default Variant columns, which will be added to new genes. You can control this by setting or unsetting the "Standard for new genes" checkbox on the "Edit column default settings" form.', 'information');

    $nData = ($nData > 15? 15 : ($nData < 6? 6 : $nData));

    print('      <SCRIPT type="text/javascript" src="inc-js-columnsort.js"></SCRIPT>' . "\n\n");
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;sent=true" method="post" onsubmit="javascript:lovd_orderSubmit()">' . "\n" .
          '        <TABLE border="0" cellpadding="0" cellspacing="0">' . "\n" .
          '          <TR>' . "\n" .
          '            <TD valign="top" rowspan="2">' . "\n" .
          '              <SELECT name="columns[]" multiple size="' . $nData . '" id="select_order">' . "\n");

    while ($zData = mysql_fetch_array($qData)) {
        print('            <OPTION value="' . $zData['colid'] . '">' . $zData['name'] . '</OPTION>' . "\n");
    }

    print('              </SELECT></TD>' . "\n" .
          '            <TD valign="top"><A href="#" onclick="lovd_orderHome(); return false;"><IMG src="gfx/order_button_home.png" alt="Top" width="24" height="24" id="butHome" onmouseover="lovd_imageSwitch(\'butHome\', \'H\');" onmouseout="lovd_imageSwitch(\'butHome\', \'B\');"></A><BR><A href="#" onclick="lovd_orderUp(); return false;"><IMG src="gfx/order_button_up.png" alt="Up" width="24" height="24" id="butUp" onmouseover="lovd_imageSwitch(\'butUp\', \'H\');" onmouseout="lovd_imageSwitch(\'butUp\', \'B\');"></A></TD></TR>' . "\n" .
          '          <TR>' . "\n" .
          '            <TD valign="bottom"><A href="#" onclick="lovd_orderDown(); return false;"><IMG src="gfx/order_button_down.png" alt="Down" width="24" height="24" id="butDown" onmouseover="lovd_imageSwitch(\'butDown\', \'H\');" onmouseout="lovd_imageSwitch(\'butDown\', \'B\');"></A><BR><A href="#" onclick="lovd_orderEnd(); return false;"><IMG src="gfx/order_button_end.png" alt="Bottom" width="24" height="24" id="butEnd" onmouseover="lovd_imageSwitch(\'butEnd\', \'H\');" onmouseout="lovd_imageSwitch(\'butEnd\', \'B\');"></A></TD></TR></TABLE>' . "\n" .
          '        <INPUT TYPE="submit" value="Change column order"><BR>' . "\n" .
          '      </FORM>' . "\n");

    require ROOT_PATH . 'inc-bot-clean.php';
    exit;
}
*///////////////////////////////////////////////////////////////////////////////





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'data_type_wizard') {
    // Show form type forms and send info back.

    define('PAGE_TITLE', 'Data type wizard');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    // Step 1: Choose column form type.
    if (empty($_POST['form_type'])) {
        // Choose from the form types, and continue.

        require ROOT_PATH . 'inc-top-clean.php';
        lovd_printHeader(PAGE_TITLE);

        if (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], -4) == 'edit') {
            lovd_showInfoTable('Please note that changing the data type of an existing column causes a risk of loosing data!', 'warning');
        }

        print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n");

        // If we've been here before, select last used option.
        if (!empty($_SESSION['data_wizard']['form_type'])) {
            $_POST['form_type'] = $_SESSION['data_wizard']['form_type'];
        }

        // Form types.
        $aTypes =
                 array(
                        'text' => 'Text/numeric input field',
                        'int' => 'Integer input field',
                        'dec' => 'Decimal input field',
                        'textarea' => 'Large multi-row textual input field',
                        'select' => 'Drop down list (one option selected)',
                        'select_multiple' => 'Selection list (multiple options selected)',
                        'date' => 'Date input field',
                        'checkbox' => 'On/off checkbox',
                      );

        // Array which will make up the form table.
        $aForm = array(
                        array('POST', '', '', '', '30%', '14', '70%'),
                        array('', '', 'print', '<B>Select custom column\'s form style</B>'),
                        array('Basic form style', '', 'select', 'form_type', 1, $aTypes, false, false, false),
                        array('', '', 'print', '<SPAN class="form_note">This is the type of field your custom column will appear on the data entry (submission) forms.</SPAN>'),
                        'skip',
                        array('', '', 'submit', 'Next &raquo;'),
                      );
        lovd_viewForm($aForm);

        print('</FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot-clean.php';
        exit;
    }

    // Store in SESSION.
    $_SESSION['data_wizard']['form_type'] = $_POST['form_type'];



    // Step 2: Gather options.
    if (count($_POST) > 1) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheckM =
                 array(
                        'name' => 'Column name on form',
                      );

        // Numeric fields.
        $aCheckN = array();

        // FIXME; I'm not happy with this code, but I simply don't know a better way right now. Thus, keep it...
        // Mandatory and Numeric fields depend on column type.
        switch ($_POST['form_type']) {
            case 'text':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['maxlength'] = 'Maximum input length (characters)';
                $aCheckN['size'] = 'Width on form (characters)';
                $aCheckN['maxlength'] = 'Maximum input length (characters)';
                break;
            case 'int':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['maxlength'] = 'Maximum input length (characters)';
                $aCheckN['size'] = 'Width on form (characters)';
                $aCheckN['maxlength'] = 'Maximum input length (characters)';
                $aCheckN['default_val'] = 'Default value (optional)';
                break;
            case 'dec':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['maxlength'] = 'Number of digits before the decimal point';
                $aCheckM['scale'] = 'Number of digits following the decimal point';
                $aCheckN['size'] = 'Width on form (characters)';
                $aCheckN['maxlength'] = 'Number of digits before the decimal point';
                $aCheckN['scale'] = 'Number of digits following the decimal point';
                $aCheckN['default_val'] = 'Default value (optional)';
                break;
            case 'date':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckN['size'] = 'Width on form (characters)';
                break;
            case 'textarea':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['rows'] = 'Height on form (lines)';
                $aCheckN['size'] = 'Width on form (characters)';
                $aCheckN['rows'] = 'Height on form (lines)';
                break;
            case 'select':
                $aCheckM['select_options'] = 'List of possible options';
                break;
            case 'select_multiple':
                $aCheckM['rows'] = 'Height on form (lines)';
                $aCheckM['select_options'] = 'List of possible options';
                $aCheckN['rows'] = 'Height on form (lines)';
                break;
        }

        // Mandatory fields...
        foreach ($aCheckM as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // Numeric fields...
        foreach ($aCheckN as $key => $val) {
            if ($_POST[$key]) {
                if ($_POST[$key] < 0 && ($key != 'default_val' || $_POST['unsigned'])) {
                    lovd_errorAdd($val, 'The \'' . $val . '\' field has to contain a positive numeric value.');
                } elseif (!is_numeric($_POST[$key])){
                    lovd_errorAdd($val, 'The \'' . $val . '\' field has to contain a numeric value.');
                }
            }
        }

        // FIXME; add proper check on proper PHP Perl-compatible regexp syntax.
        // Check regexp syntax.
        if (!empty($_POST['preg_pattern']) && !preg_match('/^\/.+\/[imsxeADSUXu]*$/', $_POST['preg_pattern'])) {
            lovd_errorAdd('preg_pattern', 'The \'Regular expression pattern\' field does not seem to contain valid PHP Perl compatible regexp syntax.');
        }

        // Select_options.
        if (!empty($_POST['select_options'])) {
            $aOptions = explode("\r\n", $_POST['select_options']);
            foreach ($aOptions as $n => $sOption) {
                if (!preg_match('/^([^=]+|[A-Z0-9 \/\()?._+-]+ *= *[^=]+)$/i', $sOption)) {
                    lovd_errorAdd('select_options', 'Select option #' . ($n + 1) . ' &quot;' . htmlspecialchars($sOption) . '&quot; not understood.');
                }
            }
        }

        if (!empty($_POST['default_val'])) {
            // Default values in text field cannot contain a quote.
            if ($_POST['form_type'] == 'text' && !preg_match('/^[^"]*$/', $_POST['default_val'])) {
                lovd_errorAdd('default_val', 'The \'Default value\' field can not contain a quote.');
            }

            // Format for the DATE/DATETIME column types.
            if ($_POST['form_type'] == 'date' && !lovd_matchDate($_POST['default_val'], !empty($_POST['time']))) {
                lovd_errorAdd('default_val', 'The \'Default value\' for the date field should be like YYYY-MM-DD' . (empty($_POST['time'])? '.' : ' HH:MM:SS.'));
            }
        }

        if (!lovd_error()) {
            // Build proper values and send them through.
            $sMySQLType = '';
            $sFormType = '';
            $sPregPattern = '';

            // Store vars in $_SESSION...
            $aStore = array('name', 'help_text', 'description_form', 'size', 'rows', 'maxlength', 'scale', 'time', 'preg_pattern', 'unsigned', 'default_val', 'select', 'select_options', 'select_all');
            foreach ($aStore as $key) {
                if (!isset($_POST[$key])) {
                    $_POST[$key] = '';
                }
                $_SESSION['data_wizard'][$key] = $_POST[$key];
            }

            // MySQL and Form type.
            // FIXME; put this in a function in inc-lib-columns when it's used more than once in the code.
            $sFormType = $_POST['name'] . '|' . $_POST['help_text'];
            switch ($_POST['form_type']) {
                case 'text':
                    if ($_POST['maxlength'] > 255) {
                        $sMySQLType = 'TEXT';
                    } else {
                        $sMySQLType = 'VARCHAR(' . $_POST['maxlength'] . ')';
                    }
                    $sFormType .= '|text|' . $_POST['size'];
                    $sPregPattern = $_POST['preg_pattern'];
                    break;
                case 'int':
                    if ($_POST['maxlength'] < 3) {
                        $sMySQLType = 'TINY';
                    } elseif ($_POST['maxlength'] < 5) {
                        $sMySQLType = 'SMALL';
                    } elseif ($_POST['maxlength'] < ($_POST['unsigned']? 8 : 7)) {
                        $sMySQLType = 'MEDIUM';
                    } elseif ($_POST['maxlength'] < 10) {
                        $sMySQLType = '';
                    } else {
                        $sMySQLType = 'BIG';
                    }
                    if ($_POST['unsigned']) {
                        $sMySQLType .= 'INT(' . ($_POST['maxlength'] > 19? 19 : $_POST['maxlength']) . ') UNSIGNED';
                    } else {
                        $sMySQLType .= 'INT(' . ($_POST['maxlength'] > 18? 18 : $_POST['maxlength']) . ')';
                    }
                    $sFormType .= '|text|' . $_POST['size'];
                    break;
                case 'dec':
                    $_POST['maxlength'] += $_POST['scale']; // Maxlength was number of digits before the decimal point.
                    $sMySQLType = 'DEC(' . ($_POST['maxlength'] > 65? 65 : $_POST['maxlength']) . ',' . $_POST['scale'] . ')' . ($_POST['unsigned']? ' UNSIGNED' : '');
                    $sFormType .= '|text|' . $_POST['size'];
                    break;
                case 'date':
                    $sMySQLType = 'DATE' . ($_POST['time']? 'TIME' : '');
                    $sFormType .= '|text|' . $_POST['size'];
                    break;
                case 'textarea':
                    $sMySQLType = 'TEXT';
                    $sFormType .= '|textarea|' . $_POST['size'] . '|' . $_POST['rows'];
                    break;
                case 'select':
                    // FIXME; In fact we should check the length of the longest select option???
                    $sMySQLType = 'VARCHAR(100)';
                    $sFormType .= '|select|1|' . ($_POST['select']? 'true' : 'false') . '|false|false';
                    break;
                case 'select_multiple':
                    $sMySQLType = 'TEXT';
                    $sFormType .= '|select|' . $_POST['rows'] . '|false|true|' . ($_POST['select_all']? 'true' : 'false');
                    break;
                case 'checkbox':
                    $sMySQLType = 'TINYINT(1) UNSIGNED';
                    $sFormType .= '|checkbox';
                    break;
            }

            // Set default value.
            if (in_array($_POST['form_type'], array('text', 'int', 'dec', 'date')) && $_POST['default_val']) {
                $sMySQLType .= ' DEFAULT "' . $_POST['default_val'] . '"';
            }

            // Thank the user...
            require ROOT_PATH . 'inc-top-clean.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Done! Created MySQL data type and form definition.', 'success');

            // Pass it on to the opener...
            print('      <SCRIPT type="text/javascript">' . "\n" .
                  '        <!--' . "\n" .
                  '        opener.document.forms[0][\'mysql_type\'].value = \'' . $sMySQLType . '\';' . "\n" .
                  '        opener.document.forms[0][\'form_type\'].value = \'' . $sFormType . '\';' . "\n" .
                  '        opener.document.forms[0][\'description_form\'].value = \'' . $_POST['description_form'] . '\';' . "\n" .
                  '        opener.document.forms[0][\'preg_pattern\'].value = \'' . $sPregPattern . '\';' . "\n" .
                  '        opener.document.forms[0][\'select_options\'].value = \'' . (empty($_POST['select_options'])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), $_POST['select_options'])) . '\';' . "\n" .
                  '        window.close();' . "\n" .
                  '        // -->' . "\n" .
                  '      </SCRIPT>' . "\n\n");

            // Script up there should suffice actually...
            print('      <BUTTON onclick="javascript:self.close();">Close window</BUTTON><BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot-clean.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_POST = $_SESSION['data_wizard'];

        switch ($_POST['form_type']) {
            case 'text':
                $aVals = array('size' => 30, 'maxlength' => 255);
                break;
            case 'int':
                $aVals = array('size' => 10, 'maxlength' => 8);
                break;
            case 'dec':
                $aVals = array('size' => 10, 'maxlength' => 5, 'scale' => 2);
                break;
            case 'date':
                $aVals = array('size' => 20);
                break;
            case 'textarea':
                $aVals = array('size' => 40, 'rows' => 4);
                break;
            case 'select':
                $aVals = array('select' => 1);
                break;
            case 'select_multiple':
                $aVals = array('rows' => 4);
                break;
        }

        foreach ($aVals as $key => $val) {
            $_POST[$key] = (!empty($_SESSION['data_wizard'][$key])? $_SESSION['data_wizard'][$key] : $val);
        }
    }



    require ROOT_PATH . 'inc-top-clean.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="form_type" value="' . $_POST['form_type'] . '">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '40%', '14', '60%'),
                    array('', '', 'print', '<B>Column options</B>'),
                    array('Column name on form', '', 'text', 'name', 30),
                    array('Help text', 'If you think the data field needs clarification given as an icon such as this one, add it here.', 'text', 'help_text', 50),
                    array('Notes on form (optional)', '', 'textarea', 'description_form', 40, 2),
                    array('', '', 'note', 'If you think the data field needs clarification on the data entry form, add it here - it will appear below the field on the data entry form just like this piece of text.'),
                  );

    // Individual field definitions.
    $aWidth     = array(array('Width on form (characters)', '', 'text', 'size', 5));
    $aHeight    = array(array('Height on form (lines)', '', 'text', 'rows', 5));
    $aMaxLength = array(array('Maximum input length (characters)', '', 'text', 'maxlength', 5));
    $aDecimal   = array(
                    array('Number of digits before the decimal point', '', 'text', 'maxlength', 5),
                    array('Number of digits following the decimal point', '', 'text', 'scale', 5));
    $aTime      = array(array('Also store time?', '', 'checkbox', 'time'));
    $aRegExp    = array(
                    array('Regular expression pattern (optional)', '', 'text', 'preg_pattern', 50),
                    array('', '', 'note', 'Note: for advanced users only. Type in a full regular expression pattern (PHP\'s Perl-compatible regexp syntax), including \'/\' delimiters and possible modifiers. Make sure it\'s valid, otherwise you risk getting all this column\'s data input rejected.'));
    $aDefault   = array(array('Default value (optional)', '', 'text', 'default_val', 20));
    $aPositive  = array(array('Allow only positive values', '', 'checkbox', 'unsigned'));
    $aSelect    = array(array('Provide "-- select --" option', 'This will add an option called "-- select --" that will be regarded as an empty value.', 'checkbox', 'select'));
    $aSelectAll = array(array('Provide "select all" link', 'This will add a link next to the selection list that allows the user to instantly select all available options.', 'checkbox', 'select_all'));
    $aOptions   = array(
                    array('List of possible options', '', 'print', '<TEXTAREA name="select_options" cols="70" rows="5" class="S11">' . (empty($_POST['select_options'])? '' : $_POST['select_options']) . '</TEXTAREA>'),
                    array('', '', 'note', 'This is used to build the available options for the selection list.<BR>One option per line.<BR>If you want to use abbreviations, use: Abbreviation = Long name<BR>Example: &quot;DMD = Duchenne Muscular Dystrophy&quot;'));

    // Form depends on chosen form type.
    switch ($_POST['form_type']) {
        case 'text':
            $aForm = array_merge($aForm, $aWidth, $aMaxLength, $aRegExp, $aDefault);
            break;
        case 'int':
            $aDefault[0][4] = 5;
            $aForm = array_merge($aForm, $aWidth, $aMaxLength, $aPositive, $aDefault);
            break;
        case 'dec':
            $aDefault[0][4] = 5;
            $aForm = array_merge($aForm, $aWidth, $aDecimal, $aPositive, $aDefault);
            break;
        case 'textarea':
            $aForm = array_merge($aForm, $aWidth, $aHeight);
            break;
        case 'select':
            $aForm = array_merge($aForm, $aSelect, $aOptions);
            break;
        case 'select_multiple':
            $aForm = array_merge($aForm, $aHeight, $aSelectAll, $aOptions);
            break;
        case 'date':
            $aForm = array_merge($aForm, $aWidth, $aTime, $aDefault);
            $aForm[] = array('', '', 'note', 'YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.');
            break;
    }

    $aForm[] = 'skip';
    $aForm[] = array('', '', 'submit', 'Finish');
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot-clean.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // URL: /columns?create
    // Create a new column.

    // Let user pick column type first.
    if (empty($_POST['category'])) {
        define('PAGE_TITLE', 'Create new custom data column');

        // Require manager clearance.
        lovd_requireAUTH(LEVEL_MANAGER);

        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        print('      You\'re about to create a new custom data column. This will allow you to define what kind of information you would like to store in the database. Please note that <I>defining</I> this type of information, does not automatically make LOVD store this information. You will need to <I>enable</I> it after defining it, so it actually gets added to the data entry form.<BR><BR>' . "\n" .
              '      Firstly, please choose what kind of category the new type of data belongs:<BR><BR>' . "\n\n" .
              '      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n" .
              '        <TABLE border="0" cellpadding="10" cellspacing="1" width="950" class="data" style="font-size : 15px;">' . "\n" .
              '          <TR>' . "\n" .
              '            <TD width="30"><INPUT type="radio" name="category" value="Patient"></TD>' . "\n" .
              '            <TD><B>Information on the patient, not related to disease</B>, not changing over time, such as date of birth</TD></TR>' . "\n" .
              '          <TR>' . "\n" .
              '            <TD width="30"><INPUT type="radio" name="category" value="Phenotype"></TD>' . "\n" .
              '            <TD><B>Information on the phenotype, related to disease</B>, possibly changing over time, such as blood pressure</TD></TR>' . "\n" .
              '          <TR>' . "\n" .
              '            <TD width="30"><INPUT type="radio" name="category" value="Screening"></TD>' . "\n" .
              '            <TD><B>Information on the detection of new variants</B>, such as detection technique or laboratory conditions</TD></TR>' . "\n" .
              '          <TR>' . "\n" .
              '            <TD width="30"><INPUT type="radio" name="category" value="VariantOnGenome"></TD>' . "\n" .
              '            <TD><B>Information on the variant(s) found, in general or on the genomic level</B>, such as restriction site change</TD></TR>' . "\n" .
              '          <TR>' . "\n" .
              '            <TD width="30"><INPUT type="radio" name="category" value="VariantOnTranscript"></TD>' . "\n" .
              '            <TD><B>Information on the variant(s) found, specific for the transcript level</B>, such as predicted effect on protein level</TD></TR></TABLE><BR>' . "\n\n" .
              '        <INPUT type="submit" value="Next &raquo;"><BR>' . "\n" .
              '      </FORM>' . "\n\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    define('PAGE_TITLE', 'Create new custom ' . strtolower($_POST['category']) . ' data column');
    define('LOG_EVENT', 'ColCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    require ROOT_PATH . 'inc-lib-form.php';

    if (count($_POST) > 1) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('id', 'col_order', 'width', 'hgvs', 'standard', 'mandatory', 'head_column', 'description_form', 'description_legend_short', 'description_legend_full', 'mysql_type', 'form_type', 'select_options', 'preg_pattern', 'public_view', 'public_add', 'allow_count_all', 'created_by', 'created_date');

            // Prepare values.
            $_POST['id'] = $_POST['category'] . '/' . $_POST['colid'];
            $_POST['col_order'] = 9999; // New columns should sort at the end.
            $_POST['hgvs'] = '0';
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');
            if (!isset($_POST['standard'])) {
                // LOVD functionality for preventing notices kind of fails here, because 'standard' was removed from the form, so not filled when not present!
                $_POST['standard'] = 0;
            }

            // Return value doesn't matter here, since there is no AUTO_INCREMENT column available.
            $_DATA->insertEntry($_POST, $aFields);

            // Store custom link connections.
            $aLinks = array();
            if ($_POST['active_links']) {
                $q = lovd_queryDB('SELECT id, name FROM ' . TABLE_LINKS . ' WHERE id IN (?' . str_repeat(', ?', count($_POST['active_links']) - 1) . ')', $_POST['active_links']);
                while ($r = mysql_fetch_row($q)) {
                    $aLinks[$r[0]] = $r[1];
                }
            }

            $bFailedLinks = false;
            foreach ($aLinks AS $nID => $sName) {
                $q = @lovd_queryDB('INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES (?, ?)', array($_POST['id'], $nID));
                if (!$q) {
                    $bFailedLinks = true;
                    lovd_writeLog('Error', 'LinkAdd', 'Custom link ' . $nID . ' (' . $sName . ') could not be added to ' . $_POST['colid'] . "\n" . mysql_error());
                } else {
                    lovd_writeLog('Event', 'LinkAdd', 'Custom link ' . $nID . ' (' . $sName . ') successfully added to ' . $_POST['colid'] . "\n" . mysql_error());
                }
            }

            // Clean up...
            $_SESSION['data_wizard'] = array();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created column ' . $_POST['id'] . ' (' . $_POST['head_column'] . ')');

            // Thank the user...
            header('Refresh: ' . (!$bFailedLinks? 3 : 10) . '; url=' . lovd_getInstallURL() . 'columns/' . $_POST['id']);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully created the new "' . $_POST['id'] . '" column!', 'success');

            if ($bFailedLinks) {
                lovd_showInfoTable('One or more custom links could not be added to the newly created column. More information can be found in the system logs.', 'warning');
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
        $_SESSION['data_wizard'] = array();
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="category" value="' . $_POST['category'] . '">' . "\n" .
          '        <INPUT type="hidden" name="description_form" value="' . $_POST['description_form'] . '">' . "\n" .
          '        <INPUT type="hidden" name="select_options" value="' . $_POST['select_options'] . '">' . "\n" .
          '        <INPUT type="hidden" name="preg_pattern" value="' . $_POST['preg_pattern'] . '">' . "\n" .
// DMD_SPECIFIC; remove this when implemented properly.
          '        <INPUT type="hidden" name="allow_count_all" value="' . $_POST['allow_count_all'] . '">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', PAGE_TITLE),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[2]) && ACTION == 'edit') {
    // URL: /columns/Variant/DNA?edit
    // Edit specific column.
die();

    $aCol = $_PATH_ELEMENTS;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);
    $sCategory = substr($sColumnID, 0, strpos($sColumnID, '/'));

    define('PAGE_TITLE', 'Edit custom data column ' . $sColumnID);
    define('LOG_EVENT', 'ColEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    $zData = $_DATA->loadEntry($sColumnID);

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (count($_POST) > 1) {
/*********************************
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        // Mandatory fields.
        $aCheck =
                 array(
                        'head_column' => 'Column heading',
                        'description_legend_short' => 'Description on short legend',
                        'description_legend_full' => 'Description on full legend',
                        'mysql_type' => 'MySQL data type',
                        'form_type' => 'Form type',
                        'password' => 'Enter your password for authorization',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // FIXME; are we just assuming that select_options, preg_pattern and form_format are OK?

        // MySQL type format.
        // 2009-02-16; 2.0-16; Allow for input of default values using "DEFAULT ..."
        // 2009-06-16; 2.0-19; Added DEC, DATE and DATETIME types.
        if ($_POST['mysql_type'] && !preg_match('/^(TEXT|VARCHAR\([0-9]{1,3}\)|DATE(TIME)?|((TINY|SMALL|MEDIUM|BIG)?INT\([0-9]{1,2}\)|DEC\([0-9]{1,2}\,[0-9]{1,2}\))( UNSIGNED)?)( DEFAULT ([0-9]+|"[^"]+"))?$/i', stripslashes($_POST['mysql_type']))) {
            lovd_errorAdd('mysql_type', 'The MySQL data type is not recognized. Please use the data type wizard to generate a proper MySQL data type.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // FIXME; Warning if MySQL type has been changed... possibly loss of data will occur.

            // First, alter all tables who have this column. Less problematic if things go wrong.
            if ($zData['mysql_type'] != stripslashes($_POST['mysql_type'])) {
                // Type changed... take action!
                $aSQL = array();
                if (substr($zData['colid'], 0, 7) == 'Variant') {
                    // Check genes to find if column is active.
                    $aGenes = lovd_getGeneList();
                    foreach ($aGenes as $sSymbol) {
                        list($b) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLEPREFIX . '_' . $sSymbol . '_columns WHERE colid = "' . $zData['colid'] . '"'));
                        if ($b) {
                            // Column present in this gene.
                            // 2009-02-16; 2.0-16; Added stripslashes to allow receiving quotes. This variable has been checked using regexps, so can be considered safe.
                            $aSQL[TABLEPREFIX . '_' . $sSymbol . '_variants'] = 'ALTER TABLE ' . TABLEPREFIX . '_' . $sSymbol . '_variants MODIFY COLUMN `' . $zData['colid'] . '` ' . stripslashes($_POST['mysql_type']) . ' NOT NULL';
                        }
                    }

                } else {
                    // Patient column.
                    list($b) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"'));
                    if ($b) {
                        // Column present in patient table.
                        // 2009-02-16; 2.0-16; Added stripslashes to allow receiving quotes. This variable has been checked using regexps, so can be considered safe.
                        $aSQL[TABLE_PATIENTS] = 'ALTER TABLE ' . TABLE_PATIENTS . ' MODIFY COLUMN `' . $zData['colid'] . '` ' . stripslashes($_POST['mysql_type']) . ' NOT NULL';
                    }
                }

                // If we've got any entries in $aSQL, go ahead...
                $nSQL = count($aSQL);
                if ($nSQL) {
                    // Loop needed queries...
                    foreach ($aSQL as $sTable => $sQ) {
                        $q = mysql_query($sQ);
                        if (!$q) {
                            $sError = mysql_error(); // Save the mysql_error before it disappears.
                            require ROOT_PATH . 'inc-top.php';
                            lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
                            lovd_dbFout('ColEditError', $sQ, $sError, false);
                            lovd_showInfoTable('Error while modifying ' . $sTable . ' table!<BR>This may indicate an error in the MySQL data definition. For more information, see the error log.', 'warning');
                            lovd_showInfoTable('Unfortunately, due to an error I cannot continue to edit the column. Your changes are lost.', 'stop');
                            require ROOT_PATH . 'inc-bot.php';
                            exit;
                        }
                    }
                }
            }



            // Use ::updateEntry() here!
            // Query text.
            $sQ = 'UPDATE ' . TABLE_COLS . ' SET ';

            // Standard fields to be used.
            $aQ = array('standard', 'mandatory', 'head_column', 'description_form', 'description_legend_short', 'description_legend_full', 'mysql_type', 'form_type', 'select_options', 'preg_pattern', 'public_view', 'public_add', 'allow_count_all');

            foreach ($aQ as $key => $val) {
                $sQ .= ($key? ', ' : '') . $val . ' = "' . $_POST[$val] . '"';
            }

            $sQ .= ', edited_by = "' . $_AUTH['id'] . '", edited_date = NOW() WHERE colid = "' . $zData['colid'] . '"';

            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
                lovd_dbFout('ColEdit', $sQ, $sError);
            }

            // Write to log...
            lovd_writeLog('MySQL:Event', 'ColEdit', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully edited column ' . $zData['colid'] . ' (' . $_POST['head_column'] . ')');



            // 2009-04-15; 2.0-18; Allow to update all active columns as well.
            // Fields to be used.
            $aColsToCopy = array('mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public_view', 'public_add');

            if ($_POST['apply_to_all']) {
                if (substr($zData['colid'], 0, 7) == 'Variant') {
                    // Variant column.
                    // Check all genes; update will just fail if column is not active for a particular gene.
                    $aGenes = lovd_getGeneList();
                    foreach ($aGenes as $sSymbol) {
                        $sQ = 'UPDATE ' . TABLEPREFIX . '_' . $sSymbol . '_columns SET ';
                        foreach ($aColsToCopy as $key => $val) {
                            $sQ .= ($key? ', ' : '') . $val . ' = "' . $_POST[$val] . '"';
                        }
                        $sQ .= ', edited_by = "' . $_AUTH['id'] . '", edited_date = NOW() WHERE colid = "' . $zData['colid'] . '"';

                        $q = mysql_query($sQ);
                        if (mysql_affected_rows()) {
                            // Write to log...
                            lovd_writeLog('MySQL:Event', 'ColEdit', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully edited variant column ' . $zData['colid'] . ' (' . mysql_real_escape_string($zData['head_column']) . ') in ' . $sSymbol . ' gene');
                        }
                    }

                } else {
                    // Patient column.
                    $sQ = 'UPDATE '. TABLE_PATIENTS_COLS . ' SET ';
                    foreach ($aColsToCopy as $key => $val) {
                        $sQ .= ($key? ', ' : '') . $val . ' = "' . $_POST[$val] . '"';
                    }
                    $sQ .= ', edited_by = "' . $_AUTH['id'] . '", edited_date = NOW() WHERE colid = "' . $zData['colid'] . '"';

                    $q = mysql_query($sQ);
                    if (mysql_affected_rows()) {
                        // Write to log...
                        lovd_writeLog('MySQL:Event', 'ColEdit', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully edited patient column ' . $zData['colid'] . ' (' . mysql_real_escape_string($zData['head_column']) . ')');
                    }
                }
            }



            // 2008-12-02; 2.0-15; Change active columns. added by Gerard
            // Fetch columns link is currently active for.
            $aLinksActive = array();
            $q = mysql_query('SELECT l.linkid, l.linkname FROM ' . TABLE_COLS2LINKS . ' AS c2l LEFT JOIN ' . TABLE_LINKS . ' AS l USING (linkid) WHERE c2l.colid = "' . $zData['colid'] . '"');
            while (list($nLinkID, $sLinkName) = mysql_fetch_row($q)) {
                $aLinksActive[$nLinkID] = $sLinkName;
            }

            // To prevent errors to show up on screen.
            if (!isset($_POST['active_links'])) {
                $_POST['active_links'] = array();
            }

            // Remove link.
            foreach ($aLinksActive AS $nLinkID => $sLinkName) {
                if (!in_array($nLinkID, $_POST['active_links'])) {
                    // User has requested removal...
                    $q = mysql_query('DELETE FROM ' . TABLE_COLS2LINKS . ' WHERE linkid = "' . $nLinkID . '" AND colid = "' . $zData['colid'] . '"');
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('MySQL:Error', 'ColEdit', 'Custom link ' . $nLinkID . ' (' . $sLinkName . ') could not be removed from ' . $zData['colid']);
                    } else {
                        lovd_writeLog('MySQL:Event', 'ColEdit', 'Custom link ' . $nLinkID . ' (' . $sLinkName . ') successfully removed from ' . $zData['colid']);
                    }
                }
            }

            // Fetch requested custom link names.
            $aLinksRequested = array();
            if (count($_POST['active_links'])) {
                $q = mysql_query('SELECT linkid, linkname FROM ' . TABLE_LINKS . ' WHERE linkid IN (' . implode(', ', $_POST['active_links']) . ')');
                while (list($nLinkID, $sLinkName) = mysql_fetch_row($q)) {
                    $aLinksRequested[$nLinkID] = $sLinkName;
                }
            }

            // Add link.
            foreach ($_POST['active_links'] AS $nLinkID) {
                if (!array_key_exists($nLinkID, $aLinksActive)) {
                    // User has requested addition...
                    $q = mysql_query('INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES ("' . $zData['colid'] . '", "' . $nLinkID . '")');
                    if (!$q) {
                        // Silent error
                        lovd_writeLog('MySQL:Error', 'ColEdit', 'Custom link ' . $nLinkID . ' (' . $aLinksRequested[$nLinkID] . ') could not be added to ' . $zData['colid']);
                    } else {
                        lovd_writeLog('MySQL:Event', 'ColEdit', 'Custom link ' . $nLinkID . ' (' . $aLinksRequested[$nLinkID] . ') successfully added to ' . $zData['colid']);
                    }
                }
            }

            // Clean up...
            $_SESSION['data_wizard'] = array();

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view&view=' . rawurlencode($_GET['edit']));

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
            print('      Successfully edited column \'' . $zData['colid'] . '\'!<BR>' .
                  ($zData['mysql_type'] != stripslashes($_POST['mysql_type']) && $nSQL? 'Modified ' . $nSQL . ' data table' . ($nSQL == 1? '.' : 's.') : '') .
                  '<BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        foreach ($zData as $key => $val) {
            if (!isset($_POST[$key]) || !$_POST[$key]) {
                $_POST[$key] = $val;
            }
        }
        $_POST['password'] = '';

        // 2008-12-02; 2.0-15; Load connected links.
        $_POST['active_links'] = array();
        $q = mysql_query('SELECT linkid FROM ' . TABLE_COLS2LINKS . ' WHERE colid = "' . $zData['colid'] . '"');
        while (list($nLinkID) = @mysql_fetch_row($q)) {
            $_POST['active_links'][] = $nLinkID;
        }

        // Default data type information, loaded in SESSION.
        $aFormType = explode('|', $zData['form_type']);
        $_SESSION['data_wizard'] =
                 array(
                        'form_type' => '',
                        'name' => $aFormType[0],
                        'size' => '',
                        'maxlength' => '',
                        'scale' => '',
                        'preg_pattern' => $zData['preg_pattern'],
                        'unsigned' => '',
                        'default_val' => '',
                        'rows' => '',
                        'select' => '',
                        'select_options' => $zData['select_options'],
                        'select_all' => '',
                      );

        // Load $_SESSION['data_wizard'] with current data from form_type and mysql_type.
        switch ($aFormType[1]) {
            case 'text':
                // VARCHAR, TEXT or INT columns.
                $_SESSION['data_wizard']['size'] = $aFormType[2];
                if (preg_match('/^VARCHAR\(([0-9]+)\)/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard']['form_type'] = 'text';
                    $_SESSION['data_wizard']['maxlength'] = $aRegs[1];
                } elseif (substr($zData['mysql_type'], 0, 4) == 'TEXT') {
                    $_SESSION['data_wizard']['form_type'] = 'text';
                    $_SESSION['data_wizard']['maxlength'] = 65535;
                } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\(([0-9]+)\) *(UNSIGNED)?/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard']['form_type'] = 'int';
                    // 2009-02-16; 2.0-16; Should be $aRegs[2], not [1] of course.
                    $_SESSION['data_wizard']['maxlength'] = $aRegs[2];
                    // 2009-02-16; 2.0-16; Should be $aRegs[3], not [2] of course.
                    $_SESSION['data_wizard']['unsigned']  = (!empty($aRegs[3])? 1 : 0);
                } elseif (preg_match('/^DEC\(([0-9]+),([0-9]+)\) *(UNSIGNED)?/', $zData['mysql_type'], $aRegs)) {
                    // 2009-06-11; 2.0-19; Added DEC, DATE and DATETIME types.
                    $_SESSION['data_wizard']['form_type'] = 'dec';
                    $_SESSION['data_wizard']['maxlength'] = $aRegs[1] - $aRegs[2];
                    $_SESSION['data_wizard']['scale'] = $aRegs[2];
                    $_SESSION['data_wizard']['unsigned']  = (!empty($aRegs[3])? 1 : 0);
                } elseif (preg_match('/^DATE(TIME)?/', $zData['mysql_type'], $aRegs)) {//need $aRegs for the default value
                    $_SESSION['data_wizard']['form_type'] = 'date';
                    $_SESSION['data_wizard']['time'] = (!empty($aRegs[1])? 1 : 0);
                } else {
                    // Should not happen.
                    $_SESSION['data_wizard']['form_type'] = 'text';
                }

                // 2009-02-16; 2.0-16; Introducing default values.
                if (preg_match('/ DEFAULT ([0-9]+|"[^"]+")/', $zData['mysql_type'], $aRegs)) {
                    // Process default values.
                    $_SESSION['data_wizard']['default_val'] = trim($aRegs[1], '"');
                }
                break;
            case 'textarea':
                // TEXT column.
                $_SESSION['data_wizard']['form_type'] = 'textarea';
                $_SESSION['data_wizard']['size']      = $aFormType[2];
                $_SESSION['data_wizard']['rows']      = $aFormType[3];
                break;
            case 'select':
                // VARCHAR or TEXT columns.
                if ($aFormType[4] == 'false') {
                    $_SESSION['data_wizard']['form_type'] = 'select';
                    $_SESSION['data_wizard']['select']    = ($aFormType[3] == 'false'? 0 : 1);
                } else {
                    $_SESSION['data_wizard']['form_type']  = 'select_multiple';
                    $_SESSION['data_wizard']['rows']       = $aFormType[2];
                    $_SESSION['data_wizard']['select']     = ($aFormType[3] == 'false'? 0 : 1);
                    $_SESSION['data_wizard']['select_all'] = ($aFormType[5] == 'false'? 0 : 1);
                }
                break;
            case 'checkbox':
                // TINYINT(1) UNSIGNED column.
                $_SESSION['data_wizard']['form_type']  = 'checkbox';
                break;
        }
*/
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $sColumnID . '?' . ACTION . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="description_form" value="' . $_POST['description_form'] . '">' . "\n" .
          '        <INPUT type="hidden" name="select_options" value="' . $_POST['select_options'] . '">' . "\n" .
          '        <INPUT type="hidden" name="preg_pattern" value="' . $_POST['preg_pattern'] . '">' . "\n" .
// DMD_SPECIFIC; remove this when implemented properly.
          '        <INPUT type="hidden" name="allow_count_all" value="' . $_POST['allow_count_all'] . '">' . "\n");
/*

    // Allow to update all active columns as well.
        // Check if column is active anywhere.
        list($bInUse) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_COLS . ' WHERE colid = "' . $zData['colid'] . '" UNION SELECT COUNT(*) FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"'));
    }

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('', 'print', '<B>Column name and descriptions</B>'),
                    array('Column heading', 'text', 'head_column', 30),
                    array('', 'print', '<SPAN class="form_note">This will appear above the column on data listings and the legend.</SPAN>'),
                    array('Description on form', 'textarea', 'description_form', 40, 2),
                    array('Description on short legend', 'textarea', 'description_legend_short', 40, 2),
                    array('Description on full legend', 'textarea', 'description_legend_full', 40, 2),
                    'skip',
                    array('', 'print', '<B>Data settings</B> (Use data type wizard to change values)'),
                    array('', 'print', '<BUTTON type="button" onclick="javascript:lovd_openWindow(\'' . $_SERVER['PHP_SELF'] . '?action=form_type_define&amp;from_action=' . $_GET['action'] . '\', \'FormTypeDefine\', 800, 400); return false;">Start data type wizard</BUTTON>'),
                    array('MySQL data type', 'text', 'mysql_type', 30),
                    array('Form type', 'text', 'form_type', 30),
                    'skip',
                    array('', 'print', '<B>Column settings</B>'),
                    array('Standard for new genes (Variant columns only)', 'checkbox', 'standard'),
                    array('Mandatory field', 'checkbox', 'mandatory'),
                    array('Show contents to public', 'checkbox', 'public'),
                    array('Show field on public forms', 'checkbox', 'public_form'),
                    array('Include in search form', 'checkbox', 'allow_count_all'),
                    array('', 'print', '<SPAN class="form_note">Selecting this checkbox allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on this column.</SPAN>'),
                    'skip',
                    array('', 'print', '<B>Link settings</B>'),
                    array('Active custom links', 'select', 'active_links', $nLinkSize, $aLinks, false, true, false),
                    'skip',
                  );
    // 2009-04-15; 2.0-18; Allow to update all active columns as well.
    if ($bInUse) {
        if (substr($zData['colid'], 0, 7) == 'Variant') {
            $aForm[] = array('Apply changes to all genes where this column is active', 'checkbox', 'apply_to_all');
        } else {
            $aForm[] = array('Apply changes to patient table where this column is active', 'checkbox', 'apply_to_all');
        }
    }
*////////////////////////////////////

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', PAGE_TITLE),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





/*
if ($_GET['action'] == 'drop' && !empty($_GET['drop'])) {
    // Deleting self-created columns.

// Require manager clearance.
lovd_requireAUTH(LEVEL_CURATOR);

    $zData = @mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_COLS . ' WHERE created_by != 0 AND colid = "' . $_GET['drop'] . '"'));
    if (!$zData) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    $bSelected = true;
    if (substr($zData['colid'], 0, 7) == 'Variant') {
        // Check genes to find if column is active.
        $aGenes = lovd_getGeneList();
        foreach ($aGenes as $sSymbol) {
            list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLEPREFIX . '_' . $sSymbol . '_columns WHERE colid = "' . $zData['colid'] . '"'));
            if ($bSelected) {
                // Column present in this gene.
                break;
            }
        }
    } elseif (substr($zData['colid'], 0, 7) == 'Patient') {
        // Patient column.
        list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"'));
    }

    if (!$zData['created_by'] || $bSelected) {
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('Column has been selected, cannot be removed!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheck =
                 array(
                        'password' => 'Enter your password for authorization',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Delete the row in the general_columns table.
            $sQ = 'DELETE FROM ' . TABLE_COLS . ' WHERE colid = "' . $_GET['drop'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
                lovd_dbFout('ColDrop', $sQ, $sError);
            }

            // Delete the links in the general_columns2links table.
            $sQ = 'DELETE FROM ' . TABLE_COLS2LINKS . ' WHERE colid = "' . $_GET['drop'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                // Silent error.
                lovd_writeLog('MySQL:Error', 'ColDrop', 'Custom links could not be removed from ' . $zData['colid']);
            } else {
                lovd_writeLog('MySQL:Event', 'ColDrop', 'Custom links successfully removed from ' . $zData['colid']);
            }

            // Write to log...
            lovd_writeLog('MySQL:Event', 'ColDrop', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully deleted column ' . $zData['colid'] . ' (' . mysql_real_escape_string($zData['head_column']) . ')');

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
            print('      Successfully deleted column "' . $zData['colid'] . '"!<BR><BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;drop=' . rawurlencode($zData['colid']) . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('Permanently deleting column', 'print', $zData['colid'] . ' (' . $zData['head_column'] . ')'),
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'submit', 'Delete column permanently'),
                  );
    $_MODULES->processForm('SetupColumnsDelete', $aForm);
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;





} elseif ($_GET['action'] == 'edit_colid' && !empty($_GET['edit_colid'])) {
    // Edit specific custom colid.

// Require manager clearance.
lovd_requireAUTH(LEVEL_CURATOR);

    $zData = @mysql_fetch_assoc(mysql_query('SELECT * FROM ' . TABLE_COLS . ' WHERE created_by != 0 AND colid = "' . $_GET['edit_colid'] . '"'));
    if (!$zData) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    $bSelected = true;
    if (substr($zData['colid'], 0, 7) == 'Variant') {
        // Check genes to find if column is active.
        $aGenes = lovd_getGeneList();
        foreach ($aGenes as $sSymbol) {
            list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLEPREFIX . '_' . $sSymbol . '_columns WHERE colid = "' . $zData['colid'] . '"'));
            if ($bSelected) {
                // Column present in this gene.
                break;
            }
        }
    } elseif (substr($zData['colid'], 0, 7) == 'Patient') {
        // Patient column.
        list($bSelected) = mysql_fetch_row(mysql_query('SELECT colid FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"'));
    }

    if (!$zData['created_by'] || $bSelected) {
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('Column has been selected, cannot be renamed!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheck =
                 array(
                        'col_cat' => 'Category',
                        'colid' => 'Column ID',
                        'password' => 'Enter your password for authorization',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd($key, 'Please fill in the \'' . $val . '\' field.');
            }
        }

        // ColID format.
        if ($_POST['colid'] && !preg_match('/^[A-Za-z0-9_]+(\/[A-Za-z0-9_]+)*$/', $_POST['colid'])) {
            lovd_errorAdd('colid', 'The column ID is not of the correct format. It can contain only letters, numbers and underscores. Subcategories must be devided by a slash (/).');
        }

        // ColID must not exist in the database.
        if ($_POST['col_cat'] && $_POST['colid'] && $_POST['col_cat'] . '/' . $_POST['colid'] != $zData['colid']) {
            list($n) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM ' . TABLE_COLS . ' WHERE colid = "' . $_POST['col_cat'] . '/' . $_POST['colid'] . '"'));
            if ($n) {
                lovd_errorAdd('colid', 'There is already a ' . $_POST['col_cat'] . ' column with this column ID. Please choose another one.');
            }
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            $_POST['colid'] = $_POST['col_cat'] . '/' . $_POST['colid'];
            $sQ = 'UPDATE ' . TABLE_COLS . ' SET colid = "' . $_POST['colid'] . '", edited_by = "' . $_AUTH['id'] . '", edited_date = NOW() WHERE colid = "' . $zData['colid'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
                lovd_dbFout('ColEditColID', $sQ, $sError);
            }

            // Write to log...
            lovd_writeLog('MySQL:Event', 'ColEditColID', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully changed column ID ' . $zData['colid'] . ' to ' . $_POST['colid']);

            // 2008-12-03; 2.0-15; Update links (whether they exist or not)
            $sQ = 'UPDATE ' . TABLE_COLS2LINKS . ' SET colid="' . $_POST['colid'] . '" WHERE colid="' . $zData['colid'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                // Silent error.
                lovd_writeLog('MySQL:Error', 'ColEdit', 'Custom links could not be updated for ' . $_POST['colid']);
            } else {
                lovd_writeLog('MySQL:Event', 'ColEdit', 'Custom links successfully updated for ' . $_POST['colid']);
            }

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view&view=' . rawurlencode($_POST['colid']));

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');
            print('      Successfully changed column ID \'' . $zData['colid'] . '\' to \'' . $_POST['colid'] . '\'!<BR><BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        foreach ($zData as $key => $val) {
            if (!isset($_POST[$key]) || !$_POST[$key]) {
                $_POST[$key] = $val;
            }
        }
        list($_POST['col_cat'], $_POST['colid']) = explode('/', $_POST['colid'], 2);
        $_POST['password'] = '';
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_columns_manage_defaults', 'LOVD Setup - Manage custom column defaults');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;edit_colid=' . rawurlencode($zData['colid']) . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('Category', 'select', 'col_cat', 1, array('Patient' => 'Patient', 'Variant' => 'Variant'), true, false, false),
                    array('Column ID', 'text', 'colid', 30),
                    array('', 'print', '<SPAN class="form_note">This ID must be unique and may contain only letters, numbers and underscores. Subcategories must be divided by a slash (/), such as \'Phenotype/Disease\'.<BR>Do NOT add \'Patient/\' or \'Variant/\' here.</SPAN>'),
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'submit', 'Edit column ID'),
                  );
    $_MODULES->processForm('SetupColumnsGlobalEdit', $aForm);
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
*/





if (!empty($_PATH_ELEMENTS[2]) && ACTION == 'add') {
    // URL: /columns/Variant/DNA?add
    // Add specific column to the data table, and enable.

    $aCol = $_PATH_ELEMENTS;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);

    define('PAGE_TITLE', 'Add/enable custom data column ' . $sColumnID);
    define('LOG_EVENT', 'ColAdd');

    // Require form & column functions.
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-columns.php';

    // Required clearance depending on which type of column is being added.
    $sCategory = substr($sColumnID, 0, strpos($sColumnID, '/')); // Temporarely because we don't have $zData['category'] yet.
    $aTableInfo = lovd_getTableInfoByCategory($sCategory);
    if ($aTableInfo['shared']) {
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    $zData = $_DATA->loadEntry($sColumnID);



    // In case of a shared column (VariantOnTranscript & Phenotype), the user
    // needs to select for which target (gene, disease) the column needs to be added to.
    if ($aTableInfo['shared']) {
        if (empty($_GET['target'])) {
            if (POST && !empty($_POST['target']) && is_array($_POST['target'])) {
                // I don't seem to be able to find a better way of doing this.
                // I need this data in GET, but I have it in POST (and can't use a
                // GET form with the current URL setup for ACTION in LOVD 3.0).
                header('Location: ' . lovd_getInstallURL() . implode('/', $_PATH_ELEMENTS) . '?' . ACTION . '&target=' . rawurlencode(implode(',', $_POST['target'])));
                exit;
            }

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);

            if ($sCategory == 'VariantOnTranscript') {
                // Add column to a certain gene.

                // Retrieve list of genes which do NOT have this column yet.
                $sSQL = 'SELECT g.id, CONCAT(g.id, " (", g.name, ")") FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_SHARED_COLS . ' AS c ON (g.id = c.geneid AND c.colid = ?) WHERE c.colid IS NULL';
                $aSQL = array($zData['id']);
                if ($_AUTH['level'] < LEVEL_MANAGER) {
                    // Maybe a JOIN would be simpler?
                    $sSQL .= ' AND g.id IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ')';
                    $aSQL = array_merge($aSQL, $_AUTH['curates']);
                }
                $sSQL .= ' ORDER BY g.id';
                $qTargets = lovd_queryDB($sSQL, $aSQL);
                $nTargets = mysql_num_rows($qTargets);
                if ($nTargets) {
                    print('      Please select the gene(s) for which you want to enable the ' . $zData['colid'] . ' column.<BR><BR>' . "\n");
                } else {
                    lovd_showInfoTable('There are no genes available that you can add this column to. Possibly all configured genes already have this column enabled, or you do not have the rights to edit the gene you wish to add this column to.', 'stop');
                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }

            } elseif ($sCategory == 'Phenotype') {
                // Add column to a certain disease.

                // Retrieve list of diseases which do NOT have this column yet.
                $sSQL = 'SELECT DISTINCT d.id, CONCAT(d.symbol, " (", d.name, ")") FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) LEFT JOIN ' . TABLE_SHARED_COLS . ' AS c ON (d.id = c.diseaseid AND c.colid = ?) WHERE c.colid IS NULL';
                $aSQL = array($zData['id']);
                if ($_AUTH['level'] < LEVEL_MANAGER) {
                    // Maybe a JOIN would be simpler?
                    $sSQL .= ' AND g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ')';
                    $aSQL = array_merge($aSQL, $_AUTH['curates']);
                }
                $sSQL .= ' ORDER BY d.symbol';
                $qTargets = lovd_queryDB($sSQL, $aSQL);
                $nTargets = mysql_num_rows($qTargets);
                if ($nTargets) {
                    print('      Please select the disease(s) for which you want to enable the ' . $zData['colid'] . ' column.<BR><BR>' . "\n");
                } else {
                    lovd_showInfoTable('There are no diseases available that you can add this column to. Possibly all configured diseases already have this column enabled, or you do not have the rights to edit the disease you wish to add this column to; make sure it\'s connected to a gene you are a curator of.', 'stop');
                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }
            }

            print('      <FORM action="' . implode('/', $_PATH_ELEMENTS) . '?' . ACTION . '" method="post">' . "\n");

            $nTargets = ($nTargets > 10? 10 : $nTargets);

            // Array which will make up the form table.
            $aForm = array(
                            array('POST', '', '', '', '50%', '14', '50%'),
                            array('Add this column to', '', 'select', 'target', $nTargets, $qTargets, false, true, true),
                            'skip',
                            array('', '', 'submit', 'Next &gt;'),
                          );
            lovd_viewForm($aForm);

            print('</FORM>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;



        } else {
            // Verify that this target exists and is allowed to be modified by the current user.

            $aTargets = explode(',', $_GET['target']);
            if ($sCategory == 'VariantOnTranscript') {
                // Check if targets (genes) exist and/or are editable for the user.
                $aAvailableGenes = ($_AUTH['level'] < LEVEL_MANAGER? $_AUTH['curates'] : lovd_getGeneList());
                foreach ($aTargets as $sSymbol) {
                    if (!in_array($sSymbol, $aAvailableGenes)) {
                        require ROOT_PATH . 'inc-top.php';
                        lovd_printHeader(PAGE_TITLE);
                        lovd_showInfoTable('Gene ' . htmlspecialchars($sSymbol) . ' does not exist or you do not have permission to edit it.', 'stop');
                        require ROOT_PATH . 'inc-bot.php';
                        exit;
                    }
                }



            } elseif ($sCategory == 'Phenotype') {
                // Add column to a certain disease.

                // First, build list of diseases, then go through them.
                $sSQL = 'SELECT d.id, CONCAT(d.symbol, " (", d.name, ")") FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid)';
                $aSQL = array();
                if ($_AUTH['level'] < LEVEL_MANAGER) {
                    // Maybe a JOIN would be simpler?
                    $sSQL .= ' WHERE g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ')';
                    $aSQL = $_AUTH['curates'];
                }
                $qDiseases = lovd_queryDB($sSQL, $aSQL);
                $nDiseases = mysql_num_rows($qDiseases);
                $aDiseases = array();
                while ($r = mysql_fetch_row($qDiseases)) {
                    $aDiseases[$r[0]] = $r[1];
                }

                foreach ($aTargets as $nID) {
                    if (!array_key_exists($nID, $aDiseases)) {
                        require ROOT_PATH . 'inc-top.php';
                        lovd_printHeader(PAGE_TITLE);
                        lovd_showInfoTable('Disease ' . htmlspecialchars($nID) . ' does not exist or you do not have the rights to edit it.', 'stop');
                        require ROOT_PATH . 'inc-bot.php';
                        exit;
                    }
                }
            }
        }
    }



    // Verify in the data table if column is already active or not.
    $zData['active_checked'] = false;
    $q = lovd_queryDB('DESCRIBE ' . $aTableInfo['table_sql']);
    while (list($sCol) = mysql_fetch_row($q)) {
        if ($sCol == $zData['id']) {
            $zData['active_checked'] = true;
            break;
        }
    }

    // If already active and this is not a shared table, adding it again is useless!
    if ($zData['active'] && $zData['active_checked']) {
        if (!$aTableInfo['shared']) {
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('This column has already been added to the ' . $aTableInfo['table_name'] . ' table!', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        } else {
            list($nError) = mysql_fetch_row(lovd_queryDB('SELECT COUNT(*) FROM ' . TABLE_SHARED_COLS . ' WHERE colid = ? AND ' . ($sCategory == 'VariantOnTranscript'? 'geneid' : 'diseaseid') . ' IN (?' . str_repeat(', ?', count($aTargets) - 1) . ')', array_merge(array($zData['id']), $aTargets)));
            if ($nError) {
                // Target already has this column enabled!
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('This column has already been added to ' . ($nError == 1? 'the ' . $aTableInfo['unit'] : $nError . ' of the ' . $aTableInfo['unit'] . 's') . ' you selected!', 'stop');
                require ROOT_PATH . 'inc-bot.php';
                exit;
            }
        }
    }

    // If not active yet, check size of table where this column needs to be added to and determine necessary time.
    $tAlterMax = 5; // If it takes more than 5 seconds, complain.
    if ($zData['active_checked']) {
        $tAlter = 0;
    } else {
        $zStatus = mysql_fetch_assoc(lovd_queryDB('SHOW TABLE STATUS LIKE "' . $aTableInfo['table_sql'] . '"'));
        $nSizeData = ($zStatus['Data_length'] + $zStatus['Index_length']);
        $nSizeIndexes = $zStatus['Index_length'];
        // Calculating time it could take to rebuild the table. This is just an estimate and it depends
        // GREATLY on things like disk connection type (SATA etc), RPM and free space in InnoDB tablespace.
        // We are not checking the tablespace right now. Assuming the data throughput is 8MB / second, Index creation 10MB / sec.
        // (results of some quick benchmarks in September 2010 by ifokkema)
        $tAlter = ($nSizeData / (8*1024*1024)) + ($nSizeIndexes / (10*1024*1024));
    }



    if (POST) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Start with inc-top.php and text, because we want to show a progress bar...
            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);

            if (!$zData['active_checked']) {
                $sMessage = 'Adding column to data table ' . ($tAlter < 4? '' : '(this make take some time)') . '...';
            } else {
                $sMessage = 'Enabling column...';
            }

            // If ALTER time is large enough, mention something about it.
            if ($tAlter > $tAlterMax) {
                lovd_showInfoTable('Please note that the time estimated to add this column to the internal data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table is possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
            }

            require ROOT_PATH . 'class/progress_bar.php';
            // This already puts the progress bar on the screen.
            $_BAR = new ProgressBar('', $sMessage);

            define('_INC_BOT_CLOSE_HTML_', false); // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
            require ROOT_PATH . 'inc-bot.php';
            // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
            flush();



            // Now, start with ALTER TABLE if necessary, since that will take the longest time and ends a transaction anyway.
            // If it fails directly after this, one can always just redo the add. LOVD will detect properly that it needs to be added to the ACTIVE_COLS table, then.
            if (!$zData['active_checked']) {
                $sSQL = 'ALTER TABLE ' . $aTableInfo['table_sql'] . ' ADD COLUMN `' . $zData['id'] . '` ' . $zData['mysql_type'] . ' NOT NULL';
                $dStart = time();
                $q = lovd_queryDB($sSQL);
                if (!$q) {
                    $tPassed = time() - $dStart;
                    $sMessage = ($tPassed < 2? '' : ' (fail after ' . $tPassed . ' seconds - disk full maybe?)');
                    lovd_queryError(LOG_EVENT . $sMessage, $sSQL, mysql_error());
                }
            }

            $_BAR->setProgress(80);
            $_BAR->setMessage('Enabling column...');

            lovd_queryDB('START TRANSACTION');
            if (!$zData['active']) {
                $sSQL = 'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES (?, ?, NOW())';
                $q = lovd_queryDB($sSQL, array($zData['id'], $_AUTH['id']));
                if (!$q) {
                    $sError = mysql_error(); // Save the mysql_error before it disappears...
                    lovd_queryDB('ROLLBACK'); // ... because we need to end the transaction.
                    lovd_queryError(LOG_EVENT, $sSQL, $sError);
                }
            }

            // Write to log...
            if (!$zData['active']) {
                lovd_writeLog('Event', LOG_EVENT,  'Added column ' . $zData['id'] . ' (' . $zData['head_column'] . ') to ' . $aTableInfo['table_name'] . ' table');
            }

            $_BAR->setProgress(90);
            $_BAR->setMessage('Registering column settings...');

            // If this is a VARIANT_ON_TRANSCRIPT or PHENOTYPE column, report in specific tables. So, check $sCategory.
            if ($aTableInfo['shared']) {
                // Register default settings in TABLE_SHARED_COLS.
                $aFields = array($aTableInfo['unit'] . 'id', 'colid', 'col_order', 'width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public_view', 'public_add', 'created_by', 'created_date');

                // Prepare values.
                $zData['colid'] = $zData['id'];
                $zData['created_by'] = $_AUTH['id'];
                $zData['created_date'] = date('Y-m-d H:i:s');

                $aTargets = explode(',', $_GET['target']);
                $nTargets = count($aTargets);
                $i = 1;
                foreach ($aTargets as $sID) {
                    // We'll add the column to the end of the list. Max order number?
                    list($zData['col_order']) = mysql_fetch_row(lovd_queryDB('SELECT MAX(col_order) FROM ' . TABLE_SHARED_COLS . ' WHERE colid LIKE ?', array($zData['category'] . '%')));
                    $zData['col_order'] ++;
                    $zData[$aTableInfo['unit'] . 'id'] = $sID;

                    // Query text.
                    $sSQL = 'INSERT INTO ' . TABLE_SHARED_COLS . ' (';
                    $aSQL = array();
                    foreach ($aFields as $key => $sField) {
                        $sSQL .= (!$key? '' : ', ') . $sField;
                        $aSQL[] = $zData[$sField];
                    }
                    $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';

                    $q = lovd_queryDB($sSQL, $aSQL);
                    if (!$q) {
                        $sError = mysql_error(); // Save the mysql_error before it disappears...
                        lovd_queryDB('ROLLBACK'); // ... because we need to end the transaction.
                        lovd_queryError(LOG_EVENT, $sSQL, $sError);
                    }
                    // FIXME; individual messages?
                    $_BAR->setProgress(90 + round(($i/$nTargets)*10));
                    $i ++;
                }
            }

            lovd_queryDB('COMMIT');
            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');

            // Write to log...
            if ($aTableInfo['shared']) {
                lovd_writeLog('Event', LOG_EVENT,  'Enabled column ' . $zData['id'] . ' (' . $zData['head_column'] . ') for ' . $nTargets . ' ' . $aTableInfo['unit'] . '(s): ' . $_GET['target']);
            }

            // Thank the user...
            $_BAR->setMessage('Successfully added column "' . $zData['head_column'] . '"!', 'done');
            $_BAR->setMessageVisibility('done', true);

            // When printing stuff on the page, NOTE that inc-bot.php has already been closed!!!!!!!!!!!!!!
/**************************************
            // 2010-07-26; 2.0-28; In case the column is mandatory, check for existing patient entries that cause problems importing downloaded data.
            $nEmptyValues = 0;
            if ($zData['mandatory'] == '1') {
                $sQ = 'SELECT COUNT(*) FROM ' . TABLE_PATIENTS;
                $nEmptyValues = @mysql_fetch_row(mysql_query($sQ));
            }

            // 2010-07-27; 2.0-28; Only forward the user when there is no problem adding the column.
            if (!$nEmptyValues) {
                // Dit moet nu met JS!
                header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true));
*/
// TMP:
$_BAR->redirectTo(lovd_getInstallURL() . 'columns/' . $zData['category'], 3);
/*
            }

            // Als we dan toch een lovd_showInfoTable() proberen te krijgen, doe die dan ook even voor de Done! message...
            // 2010-07-27; 2.0-28; Warning when a mandatory column has been added and there are already entries.
            if ($nEmptyValues) {
                lovd_showInfoTable('You added a mandatory column to the patient table, which already has entries. Please note that this will cause errors when importing data files you downloaded from LOVD.', 'warning');
            }
*//////////////////////////////////
            print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }





    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // If ALTER time is large enough, mention something about it.
    if ($tAlter > $tAlterMax) {
        lovd_showInfoTable('Please note that the time estimated to add this column to the ' . $aTableInfo['table_name'] . ' data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table is possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . implode('/', $_PATH_ELEMENTS) . '?' . ACTION . (empty($_GET['target'])? '' : '&target=' . htmlspecialchars($_GET['target'])) . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '35%', '14', '65%'),
                  );

    if (!$zData['active'] || !$zData['active_checked']) {
        // We need two activities now.
        $aForm[] = array('', '', 'print', '<B>Adding the ' . $zData['id'] . ' column to the ' . $aTableInfo['table_name'] . ' data table</B>');
    }
    if ($aTableInfo['shared']) {
        $aForm[] = array('', '', 'print', '<B>Enabling the ' . $zData['id'] . ' column for the ' . $aTableInfo['unit'] . '(s) ' . $_GET['target'] . '</B>');
    }

    if (count($aForm) == 1) {
        // I messed up somewhere.
        lovd_showInfoTable('Nothing to do???', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Array which will make up the form table.
    $aForm = array_merge($aForm,
             array(
                    'skip',
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', PAGE_TITLE),
                  ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





/*
if ($_GET['action'] == 'remove' && !empty($_GET['remove'])) {
    // Drop specific patient column.

authorization

    $zData = @mysql_fetch_assoc(mysql_query('SELECT c1.hgvs, c1.head_column, c2.* FROM ' . TABLE_COLS . ' AS c1 LEFT JOIN ' . TABLE_PATIENTS_COLS . ' AS c2 USING (colid) WHERE c1.colid = "' . $_GET['drop'] . '" AND c1.colid = c2.colid'));
    if (!$zData) {
        // Wrong ID, apparently.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');
        lovd_showInfoTable('No such ID!', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    if ($zData['hgvs']) {
        // This is a hack-attempt.
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');
        lovd_writeLog('MySQL:Error', 'HackAttempt', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') tried to remove ' . $zData['colid'] . ' (' . mysql_real_escape_string($zData['head_column']) . ')');
        print('      Hack Attempt.<BR>' . "\n");
        require ROOT_PATH . 'inc-bot.php';
        exit;
    }

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    if (isset($_GET['sent'])) {
        lovd_errorClean();

        // Mandatory fields.
        $aCheck =
                 array(
                        'password' => 'Enter your password for authorization',
                      );

        foreach ($aCheck as $key => $val) {
            if (empty($_POST[$key])) {
                lovd_errorAdd('Please fill in the \'' . $val . '\' field.');
            }
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && md5($_POST['password']) != $_AUTH['password']) {
            lovd_errorAdd('Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text; remove column registration first.
            $sQ = 'DELETE FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"';
            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');
                lovd_dbFout('ColRemoveA', $sQ, $sError);
            }

            // The whole transaction stuff is useless here; alter table will commit and there's just one query before that.

            // Alter patient table.
            $sQ = 'ALTER TABLE ' . TABLE_PATIENTS . ' DROP COLUMN `' . $zData['colid'] . '`';
            $q = mysql_query($sQ);
            if (!$q) {
                $sError = mysql_error(); // Save the mysql_error before it disappears.
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');
                lovd_dbFout('ColRemoveB', $sQ, $sError);
            }

            // Write to log...
            lovd_writeLog('MySQL:Event', 'ColRemove', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully removed column ' . $zData['colid'] . ' (' . mysql_real_escape_string($zData['head_column']) . ')');

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true));

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');
            print('      Successfully removed column "' . $zData['head_column'] . '"!<BR><BR>' . "\n\n");

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Errors, so the whole lot returns to the form.
            lovd_magicUnquoteAll();

            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader('setup_columns_manage_selected', 'LOVD Setup - Manage selected columns');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_SERVER['PHP_SELF'] . '?action=' . $_GET['action'] . '&amp;drop=' . rawurlencode($zData['colid']) . '&amp;sent=true" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '50%', '50%'),
                    array('Deleting column from patient table', 'print', $zData['colid'] . ' (' . $zData['head_column'] . ')'),
                    'skip',
                    array('Enter your password for authorization', 'password', 'password', 20),
                    array('', 'submit', 'Delete column from patient table'),
                  );
    $_MODULES->processForm('SetupColumnsRemove', $aForm);
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
*///////////////////////////////////////////////////////////////////////////////
?>
