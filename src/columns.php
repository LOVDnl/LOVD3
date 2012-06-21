<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-04
 * Modified    : 2012-06-18
 * For LOVD    : 3.0-beta-06
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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





if (PATH_COUNT < 3 && !ACTION) {
    // URL: /columns
    // URL: /columns/(VariantOnGenome|VariantOnTranscript|Individual|...)
    // View all columns.

    if (!empty($_PE[1])) {
        // FIXME; Is there a better way checking if it's a valid category?
        if (in_array($_PE[1], array('Individual', 'Phenotype', 'Screening', 'VariantOnGenome', 'VariantOnTranscript'))) {
            // Category given.
            $_GET['search_category'] = $_PE[1];
            define('PAGE_TITLE', 'Browse ' . $_PE[1] . ' custom data columns');

            require ROOT_PATH . 'inc-lib-columns.php';
            $aTableInfo = lovd_getTableInfoByCategory($_PE[1]);
        } else {
            header('Location:' . lovd_getInstallURL() . $_PE[0] . '?search_category=' . $_PE[1]);
            exit;
        }
    } else {
        define('PAGE_TITLE', 'Browse custom data columns');
    }

    $_T->printHeader();
    $_T->printTitle();

    lovd_isAuthorized('gene', $_AUTH['curates']); // Will set user's level to LEVEL_CURATOR if he is one at all.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();
    if ($_DATA->getCount()) {
        lovd_showInfoTable('Please note that these are all ' . (empty($_PE[1])? '' : $_PE[1]) . ' columns available in this LOVD installation. This is not the list of columns actually added to the system.' .
                           (!empty($_PE[1]) && !$aTableInfo['shared']? '' :
                            ' Also, modifications made to the columns added to ' . (empty($_PE[1])? 'the system' : 'a certain ' . $aTableInfo['unit']) . ' are not shown.'), 'information', 950);
    }
    $aSkip = array();
    print('      <UL id="viewlistMenu_Columns" class="jeegoocontext jeegooviewlist">' . "\n");
    if (!empty($_PE[1])) {
        $_DATA->setSortDefault('col_order'); // To show the user we're now sorting on this (the ViewList does so by default, anyway).
        $aSkip = array('category');
        print('        <LI><A href="' . $_PE[0] . '">Show all custom columns</A></LI>' . "\n");
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            print('        <LI><A click="lovd_openWindow(\'' . lovd_getInstallURL() . CURRENT_PATH . '?order&amp;in_window\', \'ColumnSort' . $_PE[1] . '\', 800, 500);">Change ' . ($aTableInfo['shared']? 'default ' : '') . 'order of columns</A></LI>' . "\n");
        }

    } else {
        // Let users restrict their choices.
        $aCategories = array('Individual', 'Phenotype', 'Screening', 'VariantOnGenome', 'VariantOnTranscript');
        foreach ($aCategories as $sCategory) {
            print('        <LI><A href="' . CURRENT_PATH . '/' . $sCategory . '">Show only ' . $sCategory . ' columns</A></LI>' . "\n");
        }
    }
    print('      </UL>' . "\n\n");
    $_DATA->viewList('Columns', $aSkip, false, false, (bool) ($_AUTH['level'] >= LEVEL_CURATOR));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 2 && !ACTION) {
    // URL: /columns/VariantOnGenome/DNA
    // URL: /columns/Phenotype/Blood_pressure/Systolic
    // View specific column.

    $aCol = $_PE;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);

    define('PAGE_TITLE', 'View custom data column ' . $sColumnID);
    $_T->printHeader();
    $_T->printTitle();

    lovd_isAuthorized('gene', $_AUTH['curates']); // Will set user's level to LEVEL_CURATOR if he is one at all.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'inc-lib-columns.php';
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
            $sNavigation = '<A href="' . CURRENT_PATH . '?add">Enable column</A>';
        } else {
            $sNavigation = '<A style="color : #999999;">Enable column</A>';
        }
        // Disable column.
        if ($zData['active'] && !$zData['hgvs']) {
            $sNavigation .= ' | <A href="' . CURRENT_PATH . '?remove">Disable column</A>';
        } else {
            $sNavigation .= ' | <A style="color : #999999;">Disable column</A>';
        }
        // Delete column.
        if (!$zData['active'] && !$zData['hgvs'] && (int) $zData['created_by']) {
            $sNavigation .= ' | <A href="' . CURRENT_PATH . '?delete">Delete column</A>';
        } else {
            $sNavigation .= ' | <A style="color : #999999;">Delete column</A>';
        }
        $sNavigation .= ' | <A href="' . CURRENT_PATH . '?edit">Edit custom data column settings</A>';
        $sNavigation .= ' | <A href="' . $_PE[0] . '/' . $zData['category'] . '?order">Re-order all ' . $zData['category'] . ' columns</A>';
/*

        if ($zData['created_by'] && !$bSelected) {
            $sNavigation .= ' | <A href="' . $_SERVER['PHP_SELF'] . '?action=edit_colid&amp;edit_colid=' . rawurlencode($zData['colid']) . '">Edit column ID</A>';
        } else {
            $sNavigation .= ' | <A style="color : #999999;">Edit column ID</A>';
        }
*/
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'order') {
    // URL: /columns/Individual?order
    // Change in what order the columns will be shown in a viewList/viewEntry.

    $sCategory = $_PE[1];

    require ROOT_PATH . 'inc-lib-columns.php';
    $aTableInfo = lovd_getTableInfoByCategory($sCategory);
    if (!$aTableInfo) {
        $_T->printHeader();
        $_T->printTitle('Re-order columns');
        lovd_showInfoTable('The specified category does not exist!', 'stop');
        $_T->printFooter();
        exit;
    }

    define('PAGE_TITLE', 'Re-order ' . $aTableInfo['table_name'] . ' columns');
    define('LOG_EVENT', 'ColumnOrder');

    lovd_requireAUTH(LEVEL_MANAGER);

    $lCategory = strlen($sCategory);

    if (POST) {
        $_DB->beginTransaction();

        foreach ($_POST['columns'] as $nOrder => $sID) {
            if (strpos($sID, $sCategory . '/') !== 0) {
                continue; // Column not in category we're working in (hack attempt, however quite innocent)
            }
            $nOrder ++; // Since 0 is the first key in the array.
            $_DB->query('UPDATE ' . TABLE_COLS . ' SET col_order = ? WHERE id = ?', array($nOrder, $sID));
        }

        // If we get here, it all succeeded.
        $_DB->commit();

        // Write to log...
        lovd_writeLog('Event', LOG_EVENT, 'Updated the ' . $aTableInfo['table_name'] . ' column order');

        // Thank the user...
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Successfully updated the ' . $aTableInfo['table_name'] . ' column order!', 'success');

        if (isset($_GET['in_window'])) {
            // We're in a new window, refresh opener en close window.
            print('      <SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
        } else {
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . $_PE[0] . '/' . $sID . '\\\';\', 1000);</SCRIPT>' . "\n\n");
        }

        $_T->printFooter();
        exit;
    }

    $_T->printHeader();
    $_T->printTitle();

    // Retrieve column IDs in current order.
    $aColumns = $_DB->query('SELECT id FROM ' . TABLE_COLS . ' WHERE id LIKE ? ORDER BY col_order ASC', array($sCategory . '/%'))->fetchAllColumn();

    lovd_showInfoTable('Below is a sorting list of all available columns (active & inactive). By clicking & dragging the arrow next to the column up and down you can rearrange the columns. Re-ordering them will affect listings, detailed views and data entry forms in the same way.' .
                       (!$aTableInfo['shared']? '' :
                        '<BR>Please note that this will change the <B>default</B> order of the columns only. You can change the order of the enabled columns per ' . $aTableInfo['unit'] . ' from the detailed view.'), 'information');

    // Form & table.
    print('      <TABLE cellpadding="0" cellspacing="0" class="sortable_head" style="width : 302px;"><TR><TH width="20">&nbsp;</TH><TH>Column ID ("' . $sCategory . '")</TH></TR></TABLE>' . "\n" .
          '      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n" .
          '        <UL id="column_list" class="sortable" style="width : 300px; margin-top : 0px;">' . "\n");

    // Now loop the items in the order given.
    foreach ($aColumns as $sID) {
        print('        <LI><INPUT type="hidden" name="columns[]" value="' . $sID . '"><TABLE width="100%"><TR><TD class="handle" width="13" align="center"><IMG src="gfx/drag_vertical.png" alt="" title="Click and drag to sort" width="5" height="13"></TD><TD>' . substr($sID, $lCategory+1) . '</TD></TR></TABLE></LI>' . "\n");
    }

    print('        </UL>' . "\n" .
          '        <INPUT type="submit" value="Save">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="' . (isset($_GET['in_window'])? 'self.close(); return false;' : 'window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1] . '\'; return false;') . '" style="border : 1px solid #FF4422;">' . "\n" .
          '      </FORM>' . "\n\n");

    lovd_includeJS('lib/jQuery/jquery-ui.sortable.min.js');

?>
      <SCRIPT type='text/javascript'>
        $(function() {
          $('#column_list').sortable({
            containment: 'parent',
            tolerance: 'pointer',
            handle: 'TD.handle',
          });
          $('#column_list').disableSelection();
        });
      </SCRIPT>
<?php

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'data_type_wizard') {
    // URL: /columns?data_type_wizard
    // Show form type forms and send info back.

    define('PAGE_TITLE', 'Data type wizard');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    // Step 1: Choose column form type.
    if (empty($_POST['form_type'])) {
        // Choose from the form types, and continue.

        $_T->printHeader(false);
        $_T->printTitle();

        if (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], -4) == 'edit') {
            lovd_showInfoTable('Please note that changing the data type of an existing column causes a risk of losing data!', 'warning');
        }

        print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&amp;workID=' . $_GET['workID'] . '" method="post">' . "\n");

        // If we've been here before, select last used option.
        if (!empty($_SESSION['data_wizard'][$_GET['workID']]['form_type'])) {
            $_POST['form_type'] = $_SESSION['data_wizard'][$_GET['workID']]['form_type'];
        }

        // Form types.
        $aTypes =
                 array(
                        'text' => 'Text/numeric input field',
                        'int' => 'Integer input field',
                        'decimal' => 'Decimal input field',
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

        $_T->printFooter();
        exit;
    }

    // Store in SESSION.
    $_SESSION['data_wizard'][$_GET['workID']]['form_type'] = $_POST['form_type'];



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
            case 'int':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['maxlength'] = 'Maximum input length (characters)';
                break;
            case 'decimal':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['maxlength'] = 'Number of digits before the decimal point';
                $aCheckM['scale'] = 'Number of digits following the decimal point';
                break;
            case 'date':
                $aCheckM['size'] = 'Width on form (characters)';
                break;
            case 'textarea':
                $aCheckM['size'] = 'Width on form (characters)';
                $aCheckM['rows'] = 'Height on form (lines)';
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

        if (substr($_POST['form_type'], 0, 6) != 'select') {
            $aCheckN = $aCheckM;
            unset($aCheckN['name']);
        }
        if (in_array($_POST['form_type'], array('int', 'decimal'))) {
            $aCheckN['default_val'] = 'Default value (optional)';
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

        // Check regexp syntax.
        if (!empty($_POST['preg_pattern']) && ($_POST['preg_pattern']{0} != '/' || @preg_match($_POST['preg_pattern'], '') === false)) {
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
                $_SESSION['data_wizard'][$_GET['workID']][$key] = $_POST[$key];
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
                case 'decimal':
                    $_POST['maxlength'] += $_POST['scale']; // Maxlength was number of digits before the decimal point.
                    $sMySQLType = 'DECIMAL(' . ($_POST['maxlength'] > 65? 65 : $_POST['maxlength']) . ',' . $_POST['scale'] . ')' . ($_POST['unsigned']? ' UNSIGNED' : '');
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
            if (in_array($_POST['form_type'], array('text', 'int', 'decimal', 'date')) && $_POST['default_val']) {
                $sMySQLType .= ' DEFAULT "' . $_POST['default_val'] . '"';
            }

            // Thank the user...
            $_T->printHeader(false);
            $_T->printTitle();
            lovd_showInfoTable('Done! Created MySQL data type and form definition.', 'success');

            // Pass it on to the opener...
            print('      <SCRIPT type="text/javascript">' . "\n" .
                  '        <!--' . "\n" .
                  '        opener.document.forms[0][\'mysql_type\'].value = \'' . addslashes($sMySQLType) . '\';' . "\n" .
                  '        opener.document.forms[0][\'form_type\'].value = \'' . addslashes($sFormType) . '\';' . "\n" .
                  '        opener.document.forms[0][\'description_form\'].value = \'' . str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($_POST['description_form'])) . '\';' . "\n" .
                  '        opener.document.forms[0][\'preg_pattern\'].value = \'' . addslashes($sPregPattern) . '\';' . "\n" .
                  '        opener.document.forms[0][\'select_options\'].value = \'' . (empty($_POST['select_options'])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($_POST['select_options']))) . '\';' . "\n" .
                  '        window.close();' . "\n" .
                  '        // -->' . "\n" .
                  '      </SCRIPT>' . "\n\n");

            // Script up there should suffice actually...
            print('      <BUTTON onclick="javascript:self.close();">Close window</BUTTON><BR>' . "\n\n");

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_POST = $_SESSION['data_wizard'][$_GET['workID']];

        $aVals = array();
        switch ($_POST['form_type']) {
            case 'text':
                $aVals = array('size' => 30, 'maxlength' => 255);
                break;
            case 'int':
                $aVals = array('size' => 10, 'maxlength' => 8);
                break;
            case 'decimal':
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
            $_POST[$key] = (!empty($_SESSION['data_wizard'][$_GET['workID']][$key])? $_SESSION['data_wizard'][$_GET['workID']][$key] : $val);
        }
    }



    $_T->printHeader(false);
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&amp;workID=' . $_GET['workID'] . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="form_type" value="' . $_POST['form_type'] . '">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '40%', '14', '60%'),
                    array('', '', 'print', '<B>Column options</B>'),
                    array('Column name on form', '', 'text', 'name', 30),
                    array('Help text', 'If you think the data field needs clarification given as an icon such as this one, add it here.', 'text', 'help_text', 50),
                    array('Notes on form (optional)<BR>(HTML enabled)', '', 'textarea', 'description_form', 40, 2),
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
                    array('List of possible options', '', 'textarea', 'select_options', 50, 5),
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
        case 'decimal':
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

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /columns?create
    // Create a new column.

    define('PAGE_TITLE', 'Create new custom ' . (!empty($_POST['category'])? strtolower($_POST['category']) : '') . ' data column');
    define('LOG_EVENT', 'ColCreate');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);
    require ROOT_PATH . 'inc-lib-form.php';

    // Let user pick column type first.
    if (empty($_POST['category'])) {
        $_T->printHeader();
        $_T->printTitle();

        $aOptionsList =
             array(
                    'width' => 950,
                    'options' =>
                         array(
                             array(
                                    'onclick'     => 'javascript:$(\'#optionForm input\').attr(\'value\', \'Individual\'); $(\'#optionForm\').submit();',
                                    'option_text' => '<B>Information on the individual, not related to disease</B>, not changing over time, such as date of birth',
                                  ),
                             array(
                                    'onclick'     => 'javascript:$(\'#optionForm input\').attr(\'value\', \'Phenotype\'); $(\'#optionForm\').submit();',
                                    'option_text' => '<B>Information on the phenotype, related to disease</B>, possibly changing over time, such as blood pressure',
                                  ),
                             array(
                                    'onclick'     => 'javascript:$(\'#optionForm input\').attr(\'value\', \'Screening\'); $(\'#optionForm\').submit();',
                                    'option_text' => '<B>Information on the detection of new variants</B>, such as detection technique or laboratory conditions',
                                  ),
                             array(
                                    'onclick'     => 'javascript:$(\'#optionForm input\').attr(\'value\', \'VariantOnGenome\'); $(\'#optionForm\').submit();',
                                    'option_text' => '<B>Information on the variant(s) found, in general or on the genomic level</B>, such as restriction site change',
                                  ),
                             array(
                                    'onclick'     => 'javascript:$(\'#optionForm input\').attr(\'value\', \'VariantOnTranscript\'); $(\'#optionForm\').submit();',
                                    'option_text' => '<B>Information on the variant(s) found, specific for the transcript level</B>, such as predicted effect on protein level',
                                  ),
                              ),
                  );

        print('      You\'re about to create a new custom data column. This will allow you to define what kind of information you would like to store in the database. Please note that <I>defining</I> this type of information, does not automatically make LOVD store this information. You will need to <I>enable</I> it after defining it, so it actually gets added to the data entry form.<BR><BR>' . "\n" .
              '      Firstly, please choose what kind of category the new type of data belongs:<BR><BR>' . "\n\n" .
              '      <FORM id="optionForm" action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
              lovd_buildOptionTable($aOptionsList) .
              '        <INPUT name="category" type="hidden" value="">' . "\n" .
              '      </FORM>' . "\n\n");

        $_T->printFooter();
        exit;
    }

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();

    // Generate a unique workID, that is sortable.
    if (!isset($_POST['workID'])) {
        $nTime = gettimeofday();
        $_POST['workID'] = $nTime['sec'] . $nTime['usec'];
    }

    if (count($_POST) > 2) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('id', 'col_order', 'width', 'hgvs', 'standard', 'mandatory', 'head_column', 'description_form', 'description_legend_short', 'description_legend_full', 'mysql_type', 'form_type', 'select_options', 'preg_pattern', 'public_view', 'public_add', 'allow_count_all', 'created_by', 'created_date');

            // Prepare values.
            $_POST['id'] = $_POST['category'] . '/' . $_POST['colid'];
            $_POST['col_order'] = 255; // New columns should sort at the end.
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
                $qLinks = $_DB->query('SELECT id, name FROM ' . TABLE_LINKS . ' WHERE id IN (?' . str_repeat(', ?', count($_POST['active_links']) - 1) . ')', $_POST['active_links']);
                while ($rLink = $qLinks->fetchRow()) {
                    $aLinks[$rLink[0]] = $rLink[1];
                }
            }

            $bFailedLinks = false;
            foreach ($aLinks AS $nID => $sName) {
                $q = @$_DB->query('INSERT INTO ' . TABLE_COLS2LINKS . ' VALUES (?, ?)', array($_POST['id'], $nID));
                if (!$q) {
                    $bFailedLinks = true;
                    lovd_writeLog('Error', 'LinkAdd', 'Custom link ' . $nID . ' (' . $sName . ') could not be added to ' . $_POST['colid'] . "\n" . $_DB->formatError());
                } else {
                    lovd_writeLog('Event', 'LinkAdd', 'Custom link ' . $nID . ' (' . $sName . ') successfully added to ' . $_POST['colid'] . "\n" . $_DB->formatError());
                }
            }

            // Clean up...
            $_SESSION['data_wizard'][$_POST['workID']] = array();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created column ' . $_POST['id'] . ' (' . $_POST['head_column'] . ')');

            // Thank the user...
            header('Refresh: ' . (!$bFailedLinks? 3 : 10) . '; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $_POST['id']);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the new "' . $_POST['id'] . '" column!', 'success');

            if ($bFailedLinks) {
                lovd_showInfoTable('One or more custom links could not be added to the newly created column. More information can be found in the system logs.', 'warning');
            }

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();

        if (!isset($_SESSION['data_wizard'])) {
            $_SESSION['data_wizard'] = array();
        }

        while (count($_SESSION['data_wizard']) >= 5) {
            unset($_SESSION['data_wizard'][min(array_keys($_SESSION['data_wizard']))]);
        }

        $_SESSION['data_wizard'][$_POST['workID']] = array();
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n" .
          '        <INPUT type="hidden" name="category" value="' . $_POST['category'] . '">' . "\n" .
          '        <INPUT type="hidden" name="description_form" value="' . $_POST['description_form'] . '">' . "\n" .
          '        <INPUT type="hidden" name="select_options" value="' . $_POST['select_options'] . '">' . "\n" .
          '        <INPUT type="hidden" name="preg_pattern" value="' . $_POST['preg_pattern'] . '">' . "\n" .
// FIXME; remove this when implemented properly.
          '        <INPUT type="hidden" name="allow_count_all" value="' . $_POST['allow_count_all'] . '">' . "\n" .
          '        <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', PAGE_TITLE),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

?>
<SCRIPT>
function lovd_setWidth () {
    var line = $(this).parent().parent().next().children(':last').children(':first');
    if ($(this).attr('value') > 999) {
        $(this).attr('value', 999);
        alert('The width cannot be more than 3 digits!');
        return false;
    }
    $(line).attr('width', $(this).attr('value'));
    $(line).next().next().html('(This is ' + $(this).attr('value') + ' pixels)');
    return false;
}

$( function () {
    $('input[name="width"]').change(lovd_setWidth);
});

</SCRIPT>
<?php

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 2 && ACTION == 'edit') {
    // URL: /columns/VariantOnGenome/DNA?edit
    // URL: /columns/Phenotype/Blood_pressure/Systolic?edit
    // Edit specific column.

    $aCol = $_PE;
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
    require ROOT_PATH . 'inc-lib-columns.php';

    // Generate a unique workID, that is sortable.
    if (!isset($_POST['workID'])) {
        $nTime = gettimeofday();
        $_POST['workID'] = $nTime['sec'] . $nTime['usec'];
    }

    $aColumnInfo = lovd_getTableInfoByCategory($sCategory);
    // If type has changed... take action!
    // Check size of table where this column needs to be added to and determine necessary time.
    $tAlterMax = 5; // If it takes more than 5 seconds, complain.
    $zStatus = $_DB->query('SHOW TABLE STATUS LIKE "' . $aColumnInfo['table_sql'] . '"')->fetchAssoc();
    $nSizeData = ($zStatus['Data_length'] + $zStatus['Index_length']);
    $nSizeIndexes = $zStatus['Index_length'];
    // Calculating time it could take to rebuild the table. This is just an estimate and it depends
    // GREATLY on things like disk connection type (SATA etc), RPM and free space in InnoDB tablespace.
    // We are not checking the tablespace right now. Assuming the data throughput is 8MB / second, Index creation 10MB / sec.
    // (results of some quick benchmarks in September 2010 by ifokkema)
    $tAlter = ($nSizeData / (8*1024*1024)) + ($nSizeIndexes / (10*1024*1024));

    if (count($_POST) > 1) {

        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            $_T->printHeader();
            $_T->printTitle();

            // Fields to be used.
            $aFields = array('width', 'standard', 'mandatory', 'head_column', 'description_form', 'description_legend_short', 'description_legend_full', 'mysql_type', 'form_type', 'select_options', 'preg_pattern', 'public_view', 'public_add', 'allow_count_all', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['standard'] = (isset($_POST['standard'])? $_POST['standard'] : $zData['standard']); 
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $sMessage = 'Editing columns MySQL type' . ($tAlter < 4? '' : '(this make take some time)') . '...';

            // If ALTER time is large enough, mention something about it.
            if ($tAlter > $tAlterMax) {
                lovd_showInfoTable('Please note that the time estimated to edit this columns MySQL type is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table are possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
            }

            require ROOT_PATH . 'class/progress_bar.php';
            // This already puts the progress bar on the screen.
            $_BAR = new ProgressBar('', $sMessage);

            $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.
            // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
            flush();



            if ($zData['mysql_type'] != $_POST['mysql_type']) {
                // Now, start with ALTER TABLE if necessary, since that will take the longest time and ends a transaction anyway.
                // If it fails directly after this, one can always just redo the edit. LOVD will detect properly that it still needs to be edited in TABLE_COLS.
                $aColumns = $_DB->query('DESCRIBE ' . $aColumnInfo['table_sql'])->fetchAllColumn();
                if (in_array($sColumnID, $aColumns)) {
                    // Column active for this table.
                    // This variables have been checked using regexps, so can be considered safe.
                    $q = $_DB->query('ALTER TABLE ' . $aColumnInfo['table_sql'] . ' MODIFY COLUMN `' . $sColumnID . '` ' . $_POST['mysql_type']);
                }
            }

            $_BAR->setProgress(80);
            $_BAR->setMessage('Editing column information...');

            // Update entry.
            $_DATA->updateEntry($sColumnID, $_POST, $aFields);

            // Change active custom links?
            // Remove custom links.
            $aToRemove = array();
            foreach ($zData['active_links'] as $nLinkID) {
                if ($nLinkID && !in_array($nLinkID, $_POST['active_links'])) {
                    // User has requested removal...
                    $aToRemove[] = $nLinkID;
                }
            }
            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_COLS2LINKS . ' WHERE colid = ? AND linkid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($sColumnID), $aToRemove));
                if (!$q) {
                    // Silent error.
                    // FIXME; deze log entries zijn precies andersom dan bij create (wat wordt aan wat toegevoegd/verwijderd). Dat moeten we standaardiseren, maar wellicht even overleggen over LOVD-breed.
                    lovd_writeLog('Error', LOG_EVENT, 'Custom link' . (count($aToRemove) > 1? 's' : '') . ' ' . implode(', ', $aToRemove) . ' could not be removed from column ' . $sColumnID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Custom link' . (count($aToRemove) > 1? 's' : '') . ' ' . implode(', ', $aToRemove) . ' successfully removed from column ' . $sColumnID);
                }
            }

            // Add custom links.
            if (preg_match('/^TEXT|VARCHAR/', $_POST['mysql_type']) && $sColumnID != 'VariantOnGenome/DBID') {
                $aSuccess = array();
                $aFailed = array();
                $q = $_DB->prepare('INSERT IGNORE INTO ' . TABLE_COLS2LINKS . ' VALUES (?, ?)');
                foreach ($_POST['active_links'] as $nLinkID) {
                    if (!in_array($nLinkID, $zData['active_links'])) {
                        // Add custom link to column.
                        $q->execute(array($sColumnID, $nLinkID));
                        if (!$q) {
                            $aFailed[] = $nLinkID;
                        } else {
                            $aSuccess[] = $nLinkID;
                        }
                    }
                }
                if ($aFailed) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Custom link' . (count($aFailed) > 1? 's' : '') . ' ' . implode(', ', $aFailed) . ' could not be added to column ' . $sColumnID);
                }
                if ($aSuccess) {
                    lovd_writeLog('Event', LOG_EVENT, 'Custom link' . (count($aSuccess) > 1? 's' : '') . ' ' . implode(', ', $aSuccess) . ' successfully added to column ' . $sColumnID);
                }
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited column ' . $sColumnID . ' (' . $_POST['head_column'] . ')');

            $_BAR->setProgress(90);

            // Allow to update all active columns as well.
            if (!empty($_POST['apply_to_all'])) {
                $_BAR->setMessage('Applying new default settings for this column to all ' . $aColumnInfo['unit'] . 's...');

                // Fields to be used.
                $aColsToCopy = array('width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public_view', 'public_add');

                if ($aColumnInfo['shared']) {
                    $sSQL = 'UPDATE ' . TABLE_SHARED_COLS . ' SET ';
                    $aArgs = array();
                    foreach ($aColsToCopy as $key => $val) {
                        $sSQL .= ($key? ', ' : '') . $val . ' = ?';
                        $aArgs[] = $_POST[$val];
                    }
                    $sSQL .= ', edited_by = ?, edited_date = ? WHERE colid = ?';
                    $aArgs[] = $_AUTH['id'];
                    $aArgs[] = $_POST['edited_date'];
                    $aArgs[] = $sColumnID;

                    $q = $_DB->query($sSQL, $aArgs);
                    if ($q->rowCount()) {
                        // Write to log...
                        lovd_writeLog('Error', LOG_EVENT, 'Column ' . $sColumnID . ' reset to new defaults for all ' . $aColumnInfo['unit'] . 's');
                    }
                }
            }

            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');

            // Clean up...
            unset($_SESSION['data_wizard'][$_POST['workID']]);

            // Thank the user...
            $_BAR->setMessage('Successfully edited column "' . $zData['head_column'] . '"!', 'done');
            $_BAR->setMessageVisibility('done', true);

                // When printing stuff on the page, NOTE that footer has already been closed!!!!!!!!!!!!!!
        /**************************************
                // 2010-07-26; 2.0-28; In case the column is mandatory, check for existing patient entries that cause problems importing downloaded data.
                $nEmptyValues = 0;
                if ($zData['mandatory'] == '1') {
                    $sQ = 'SELECT COUNT(*) FROM ' . TABLE_PATIENTS;
                    $nEmptyValues = @$_DB->query($sQ)->fetchColumn();
                }

                // 2010-07-27; 2.0-28; Only forward the user when there is no problem adding the column.
                if (!$nEmptyValues) {
                    // Dit moet nu met JS!
                    header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true));
        */
        // TMP:
        $_BAR->redirectTo(lovd_getInstallURL() . CURRENT_PATH, 3);
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

    } else {
        // Default values.
        if (!isset($_SESSION['data_wizard'])) {
            $_SESSION['data_wizard'] = array();
        }

        while (count($_SESSION['data_wizard']) >= 5) {
            unset($_SESSION['data_wizard'][min(array_keys($_SESSION['data_wizard']))]);
        }

        $_SESSION['data_wizard'][$_POST['workID']] = $_POST = array_merge($_POST, $zData);

        $aFormType = explode('|', $_POST['form_type']);
        $_SESSION['data_wizard'][$_POST['workID']] =
                 array(
                        'form_type' => $aFormType[2],
                        'name' => $aFormType[0],
                        'help_text' => $aFormType[1],
                        'size' => '',
                        'description_form' => $zData['description_form'],
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
        switch ($aFormType[2]) {
            case 'text':
                // VARCHAR, TEXT or INT columns.
                $_SESSION['data_wizard'][$_POST['workID']]['size'] = $aFormType[3];
                if (preg_match('/^VARCHAR\(([0-9]+)\)/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard'][$_POST['workID']]['maxlength'] = $aRegs[1];
                } elseif (substr($zData['mysql_type'], 0, 4) == 'TEXT') {
                    $_SESSION['data_wizard'][$_POST['workID']]['maxlength'] = 65535;
                } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\(([0-9]+)\) *(UNSIGNED)?/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard'][$_POST['workID']]['form_type'] = 'int';
                    $_SESSION['data_wizard'][$_POST['workID']]['maxlength'] = $aRegs[2];
                    $_SESSION['data_wizard'][$_POST['workID']]['unsigned']  = (!empty($aRegs[3])? 1 : 0);
                } elseif (preg_match('/^DECIMAL\(([0-9]+),([0-9]+)\) *(UNSIGNED)?/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard'][$_POST['workID']]['form_type'] = 'decimal';
                    $_SESSION['data_wizard'][$_POST['workID']]['maxlength'] = $aRegs[1] - $aRegs[2];
                    $_SESSION['data_wizard'][$_POST['workID']]['scale'] = $aRegs[2];
                    $_SESSION['data_wizard'][$_POST['workID']]['unsigned']  = (!empty($aRegs[3])? 1 : 0);
                } elseif (preg_match('/^DATE(TIME)?/', $zData['mysql_type'], $aRegs)) {
                    $_SESSION['data_wizard'][$_POST['workID']]['form_type'] = 'date';
                    $_SESSION['data_wizard'][$_POST['workID']]['time'] = (!empty($aRegs[1])? 1 : 0);
                }

                if (preg_match('/ DEFAULT ([0-9]+|"[^"]+")/', $zData['mysql_type'], $aRegs)) {
                    // Process default values.
                    $_SESSION['data_wizard'][$_POST['workID']]['default_val'] = trim($aRegs[1], '"');
                }
                break;
            case 'textarea':
                // TEXT column.
                $_SESSION['data_wizard'][$_POST['workID']]['size'] = $aFormType[3];
                $_SESSION['data_wizard'][$_POST['workID']]['rows'] = $aFormType[4];
                break;
            case 'select':
                // VARCHAR or TEXT columns.
                if ($aFormType[5] == 'false') {
                    $_SESSION['data_wizard'][$_POST['workID']]['select'] = ($aFormType[4] == 'false'? 0 : 1);
                } else {
                    $_SESSION['data_wizard'][$_POST['workID']]['form_type'] .= '_multiple';
                    $_SESSION['data_wizard'][$_POST['workID']]['rows']       = $aFormType[3];
                    $_SESSION['data_wizard'][$_POST['workID']]['select']     = ($aFormType[4] == 'false'? 0 : 1);
                    $_SESSION['data_wizard'][$_POST['workID']]['select_all'] = ($aFormType[6] == 'false'? 0 : 1);
                }
                break;
            case 'checkbox':
                // TINYINT(1) UNSIGNED column.
                break;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" onsubmit="return lovd_checkSubmittedForm();">' . "\n" .
          '        <INPUT type="hidden" name="category" value="' . $_POST['category'] . '">' . "\n" .
          '        <INPUT type="hidden" name="description_form" value="' . $_POST['description_form'] . '">' . "\n" .
          '        <INPUT type="hidden" name="select_options" value="' . $_POST['select_options'] . '">' . "\n" .
          '        <INPUT type="hidden" name="preg_pattern" value="' . $_POST['preg_pattern'] . '">' . "\n" .
// FIXME; remove this when implemented properly.
          '        <INPUT type="hidden" name="allow_count_all" value="' . $_POST['allow_count_all'] . '">' . "\n" .
          '        <INPUT type="hidden" name="workID" value="' . $_POST['workID'] . '">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit custom data column'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $sJSMessage = 'Are you sure you want to change the MySQL data type of this column? Changing the data type of an existing column causes a risk of losing data!';
    $sJSMessage .= ($tAlter > $tAlterMax? '\nPlease note that the time estimated to edit this columns MySQL type is ' . round($tAlter) . ' seconds. During this time, no updates to the data table are possible.' : '');

?>
<SCRIPT type="text/javascript">
function lovd_checkSubmittedForm ()
{
    if ($('input[name="mysql_type"]').attr('value') != '<?php echo $zData['mysql_type'] ?>') {
        return window.confirm('<?php echo $sJSMessage ?>');
    }
}

function lovd_setWidth ()
{
    var line = $(this).parent().parent().next().children(':last').children(':first');
    if ($(this).attr('value') > 999) {
        $(this).attr('value', 999);
        alert('The width cannot be more than 3 digits!');
        return false;
    }
    $(line).attr('width', $(this).attr('value'));
    $(line).next().next().html('(This is ' + $(this).attr('value') + ' pixels)');
    return false;
}

$(function ()
{
    $('input[name="width"]').change(lovd_setWidth);
});

</SCRIPT>
<?php

    $_T->printFooter();
    exit;
}





/*******************************************************************************
if ($_GET['action'] == 'edit_colid' && !empty($_GET['edit_colid'])) {
    // Edit specific custom colid.

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);

    $zData = @$_DB>query('SELECT * FROM ' . TABLE_COLS . ' WHERE created_by != 0 AND colid = "' . $_GET['edit_colid'] . '"')->fetchAssoc();
    if (!$zData) {
        // Wrong ID, apparently.
        $_T->printHeader();
        $_T->printTitle('LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    $bSelected = true;
    if (substr($zData['colid'], 0, 7) == 'Variant') {
        // Check genes to find if column is active.
        $aGenes = lovd_getGeneList();
        foreach ($aGenes as $sSymbol) {
            $bSelected = $_DB->query('SELECT colid FROM ' . TABLEPREFIX . '_' . $sSymbol . '_columns WHERE colid = "' . $zData['colid'] . '"')->fetchColumn();
            if ($bSelected) {
                // Column present in this gene.
                break;
            }
        }
    } elseif (substr($zData['colid'], 0, 7) == 'Patient') {
        // Patient column.
        $bSelected = $_DB->query('SELECT colid FROM ' . TABLE_PATIENTS_COLS . ' WHERE colid = "' . $zData['colid'] . '"')->fetchColumn();
    }

    if (!$zData['created_by'] || $bSelected) {
        $_T->printHeader();
        $_T->printTitle('LOVD Setup - Manage custom column defaults');
        lovd_showInfoTable('Column has been selected, cannot be renamed!', 'stop');
        $_T->printFooter();
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
            $n = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_COLS . ' WHERE colid = "' . $_POST['col_cat'] . '/' . $_POST['colid'] . '"')->fetchColumn();
            if ($n) {
                lovd_errorAdd('colid', 'There is already a ' . $_POST['col_cat'] . ' column with this column ID. Please choose another one.');
            }
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            $_POST['colid'] = $_POST['col_cat'] . '/' . $_POST['colid'];
            $sQ = 'UPDATE ' . TABLE_COLS . ' SET colid = "' . $_POST['colid'] . '", edited_by = "' . $_AUTH['id'] . '", edited_date = NOW() WHERE colid = "' . $zData['colid'] . '"';
            $q = $_DB->query($sQ);
            if (!$q) {
                $sError = $_DB->formatError(); // Save the mysql_error before it disappears.
                $_T->printHeader();
                $_T->printTitle('LOVD Setup - Manage custom column defaults');
                lovd_dbFout('ColEditColID', $sQ, $sError);
            }

            // Write to log...
            lovd_writeLog('MySQL:Event', 'ColEditColID', $_AUTH['username'] . ' (' . mysql_real_escape_string($_AUTH['name']) . ') successfully changed column ID ' . $zData['colid'] . ' to ' . $_POST['colid']);

            // 2008-12-03; 2.0-15; Update links (whether they exist or not)
            $sQ = 'UPDATE ' . TABLE_COLS2LINKS . ' SET colid="' . $_POST['colid'] . '" WHERE colid="' . $zData['colid'] . '"';
            $q = $_DB->query($sQ);
            if (!$q) {
                // Silent error.
                lovd_writeLog('MySQL:Error', 'ColEdit', 'Custom links could not be updated for ' . $_POST['colid']);
            } else {
                lovd_writeLog('MySQL:Event', 'ColEdit', 'Custom links successfully updated for ' . $_POST['colid']);
            }

            // Thank the user...
            header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view&view=' . rawurlencode($_POST['colid']));

            $_T->printHeader();
            $_T->printTitle('LOVD Setup - Manage custom column defaults');
            print('      Successfully changed column ID \'' . $zData['colid'] . '\' to \'' . $_POST['colid'] . '\'!<BR><BR>' . "\n\n");

            $_T->printFooter();
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



    $_T->printHeader();
    $_T->printTitle('LOVD Setup - Manage custom column defaults');

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

    $_T->printFooter();
    exit;
}
*///////////////////////////////////////////////////////////////////////////////





if (PATH_COUNT > 2 && ACTION == 'add') {
    // URL: /columns/VariantOnGenome/DNA?add
    // URL: /columns/Phenotype/Blood_pressure/Systolic?add
    // Add specific column to the data table, and enable.

    $aCol = $_PE;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);
    $sCategory = $aCol[1];

    define('PAGE_TITLE', 'Add/enable custom data column ' . $sColumnID);
    define('LOG_EVENT', 'ColAdd');

    // Require form & column functions.
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-columns.php';

    // Required clearance depending on which type of column is being added.
    $aTableInfo = lovd_getTableInfoByCategory($sCategory);
    if ($aTableInfo['shared']) {
        lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    if ($aTableInfo['shared']) {
        // FIXME; If, for curator level users, we'd made a JOIN here, we could see beforehand that there will be no targets left, instead of having to check it some 50 lines below here.
        $nCount = $_DB->query('SELECT COUNT(id) FROM ' . constant(strtoupper('table_' . $aTableInfo['unit'] . 's')))->fetchColumn();
        $zData = $_DB->query('SELECT c.*, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid FROM ' . TABLE_COLS . ' AS c LEFT OUTER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (c.id = sc.colid) WHERE c.id = ? GROUP BY sc.colid HAVING count(sc.' . $aTableInfo['unit'] . 'id) < ?', array($sColumnID, $nCount))->fetchAssoc();
    } else {
        $zData = $_DB->query('SELECT c.*, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid FROM ' . TABLE_COLS . ' AS c LEFT OUTER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id = ? AND ac.colid IS NULL', array($sColumnID))->fetchAssoc();
    }

    if (!$zData) {
        // Column doesn't exist or has already been added to everything it can be added to.
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('This column does not exist or has already been ' . ($aTableInfo['shared']? 'added to all ' . $aTableInfo['unit'] . 's' : 'enabled') . '.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (!POST && !empty($_GET['target'])) {
        $_POST['target'] = $_GET['target'];
    }

    // In case of a shared column (VariantOnTranscript & Phenotype), the user
    // needs to select for which target (gene, disease) the column needs to be added to.
    if ($aTableInfo['shared']) {
        if ($sCategory == 'VariantOnTranscript') {
            // Retrieve list of genes which do NOT have this column yet.
            $sSQL = 'SELECT g.id, CONCAT(g.id, " (", g.name, ")") FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_SHARED_COLS . ' AS c ON (g.id = c.geneid AND c.colid = ?) WHERE c.colid IS NULL';
            $aSQL = array($zData['id']);
            if ($_AUTH['level'] < LEVEL_MANAGER) {
                // Maybe a JOIN would be simpler?
                $sSQL .= ' AND g.id IN (?' . str_repeat(', ?', count($_AUTH['curates']) - 1) . ')';
                $aSQL = array_merge($aSQL, $_AUTH['curates']);
            }
            $sSQL .= ' ORDER BY g.id';
            $aPossibleTargets = array_map('lovd_shortenString', $_DB->query($sSQL, $aSQL)->fetchAllCombine());
            $nPossibleTargets = count($aPossibleTargets);
        } elseif ($sCategory == 'Phenotype') {
            // Retrieve list of diseases which do NOT have this column yet.
            $sSQL = 'SELECT DISTINCT d.id, CONCAT(d.symbol, " (", d.name, ")") FROM ' . TABLE_DISEASES . ' AS d LEFT JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) LEFT JOIN ' . TABLE_SHARED_COLS . ' AS c ON (d.id = c.diseaseid AND c.colid = ?) WHERE c.colid IS NULL';
            $aSQL = array($zData['id']);
            if ($_AUTH['level'] < LEVEL_MANAGER) {
                // Maybe a JOIN would be simpler?
                $sSQL .= ' AND g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates'])-1) . ')';
                $aSQL = array_merge($aSQL, $_AUTH['curates']);
            }
            $sSQL .= ' ORDER BY d.symbol';
            $aPossibleTargets = array_map('lovd_shortenString', $_DB->query($sSQL, $aSQL)->fetchAllCombine());
            $nPossibleTargets = count($aPossibleTargets);
        }

        if (!$nPossibleTargets) {
            // Column has already been added to everything it can be added to.
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('This column has already been added to all ' . $aTableInfo['unit'] . 's.', 'stop');
            $_T->printFooter();
            exit;
        }
    }

    // Check if column is enabled for target.
    lovd_errorClean();
    if (!empty($_POST['target'])) {
        $aTargets = $_POST['target'];
        if (!is_array($aTargets)) {
            $aTargets = array($aTargets);
        }
        foreach($aTargets as $sTarget) {
            if (!isset($aPossibleTargets[$sTarget])) {
                lovd_errorAdd('target', 'Please a select valid ' . $aTableInfo['unit'] . ' from the list!');
                break;
            }
        }
    }

    $tAlterMax = 5; // If it takes more than 5 seconds, complain.
    $zStatus = $_DB->query('SHOW TABLE STATUS LIKE "' . $aTableInfo['table_sql'] . '"')->fetchAssoc();
    $nSizeData = ($zStatus['Data_length'] + $zStatus['Index_length']);
    $nSizeIndexes = $zStatus['Index_length'];
    // Calculating time it could take to rebuild the table. This is just an estimate and it depends
    // GREATLY on things like disk connection type (SATA etc), RPM and free space in InnoDB tablespace.
    // We are not checking the tablespace right now. Assuming the data throughput is 8MB / second, Index creation 10MB / sec.
    // (results of some quick benchmarks in September 2010 by ifokkema)
    $tAlter = ($nSizeData / (8*1024*1024)) + ($nSizeIndexes / (10*1024*1024));

    if (POST) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if ($aTableInfo['shared'] && empty($_POST['target'])) {
            lovd_errorAdd('target', 'Please select a ' . $aTableInfo['unit'] . ' from the list!');
        }

        if (!lovd_error()) {
            // Start with header and text, because we want to show a progress bar...
            $_T->printHeader();
            $_T->printTitle();

            $zData['active_checked'] = false;
            if (in_array($sColumnID, $_DB->query('DESCRIBE ' . $aTableInfo['table_sql'])->fetchAllColumn())) {
                $zData['active_checked'] = true;
            }
            $zData['active'] = false;
            if (in_array($sColumnID, $_DB->query('SELECT colid FROM  ' . TABLE_ACTIVE_COLS)->fetchAllColumn())) {
                $zData['active'] = true;
            }

            if (!$zData['active_checked']) {
                $sMessage = 'Adding column to data table ' . ($tAlter < 4? '' : '(this make take some time)') . '...';
            } else {
                $sMessage = 'Enabling column...';
            }

            // If ALTER time is large enough, mention something about it.
            if ($tAlter > $tAlterMax) {
                lovd_showInfoTable('Please note that the time estimated to add this column to the internal data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table are possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
            }

            require ROOT_PATH . 'class/progress_bar.php';
            // This already puts the progress bar on the screen.
            $_BAR = new ProgressBar('', $sMessage);

            $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.
            // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
            flush();



            // Now, start with ALTER TABLE if necessary, since that will take the longest time and ends a transaction anyway.
            // If it fails directly after this, one can always just redo the add. LOVD will detect properly that it needs to be added to the ACTIVE_COLS table, then.
            if (!$zData['active_checked']) {
                $sSQL = 'ALTER TABLE ' . $aTableInfo['table_sql'] . ' ADD COLUMN `' . $zData['id'] . '` ' . $zData['mysql_type'];
                $dStart = time();
                $q = $_DB->exec($sSQL, false);
                if ($q === false) {
                    $sError = $_DB->formatError(); // Save the PDO error before it disappears.
                    $tPassed = time() - $dStart;
                    $sMessage = ($tPassed < 2? '' : ' (fail after ' . $tPassed . ' seconds - disk full maybe?)');
                    lovd_queryError(LOG_EVENT . $sMessage, $sSQL, $sError);
                }
            }

            $_BAR->setProgress(80);
            $_BAR->setMessage('Enabling column...');

            $_DB->beginTransaction();
            if (!$zData['active']) {
                $sSQL = 'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES (?, ?, NOW())';
                $_DB->query($sSQL, array($zData['id'], $_AUTH['id']));
            }

            // Write to log...
            if (!$zData['active']) {
                lovd_writeLog('Event', LOG_EVENT,  'Added column ' . $zData['id'] . ' (' . $zData['head_column'] . ') to ' . $aTableInfo['table_name'] . ' table');
            }

            $_BAR->setProgress(90);
            $_BAR->setMessage('Registering column settings...');

            // If this is a shared (VARIANT_ON_TRANSCRIPT or PHENOTYPE) column, report in specific tables. So, check column info.
            if ($aTableInfo['shared']) {
                // Register default settings in TABLE_SHARED_COLS.
                $aFields = array($aTableInfo['unit'] . 'id', 'colid', 'col_order', 'width', 'mandatory', 'description_form', 'description_legend_short', 'description_legend_full', 'select_options', 'public_view', 'public_add', 'created_by', 'created_date');

                // Prepare values.
                $zData['colid'] = $zData['id'];
                $zData['created_by'] = $_AUTH['id'];
                $zData['created_date'] = date('Y-m-d H:i:s');

                $nTargets = count($aTargets);
                $i = 1;

                foreach ($aTargets as $sID) {
                    $zData[$aTableInfo['unit'] . 'id'] = $sID;

                    // Query text.
                    $sSQL = 'INSERT INTO ' . TABLE_SHARED_COLS . ' (';
                    $aSQL = array();
                    foreach ($aFields as $key => $sField) {
                        $sSQL .= (!$key? '' : ', ') . $sField;
                        $aSQL[] = $zData[$sField];
                    }
                    $sSQL .= ') VALUES (?' . str_repeat(', ?', count($aFields) - 1) . ')';

                    $_DB->query($sSQL, $aSQL);
                    // FIXME; individual messages?
                    $_BAR->setProgress(90 + round(($i/$nTargets)*10));
                    $i ++;
                }
            }

            $_DB->commit();
            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');

            // Write to log...
            if ($aTableInfo['shared']) {
                lovd_writeLog('Event', LOG_EVENT,  'Enabled column ' . $zData['id'] . ' (' . $zData['head_column'] . ') for ' . $nTargets . ' ' . $aTableInfo['unit'] . '(s): ' . $aTargets);
            }

            // Thank the user...
            $_BAR->setMessage('Successfully added column "' . $zData['head_column'] . '"!', 'done');
            $_BAR->setMessageVisibility('done', true);

            // When printing stuff on the page, NOTE that footer has already been closed!!!!!!!!!!!!!!
/**************************************
            // 2010-07-26; 2.0-28; In case the column is mandatory, check for existing patient entries that cause problems importing downloaded data.
            $nEmptyValues = 0;
            if ($zData['mandatory'] == '1') {
                $sQ = 'SELECT COUNT(*) FROM ' . TABLE_PATIENTS;
                $nEmptyValues = @$_DB->query($sQ)->fetchColumn();
            }

            // 2010-07-27; 2.0-28; Only forward the user when there is no problem adding the column.
            if (!$nEmptyValues) {
                // Dit moet nu met JS!
                header('Refresh: 3; url=' . PROTOCOL . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?action=view_all' . lovd_showSID(true));
*/
// TMP:
if (!isset($_GET['in_window'])) {
    $_BAR->redirectTo(lovd_getInstallURL() . $_PE[0] . '/' . $sCategory, 3);
} else {
    print('<SCRIPT type="text/javascript">' . "\n" .
          '    if (opener.lovd_checkColumns) {' . "\n" .
          '        opener.lovd_checkColumns();' . "\n" .
          '    }' . "\n" .
          '    window.close();' . "\n" .
          '</SCRIPT>');
    
}
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



    $_T->printHeader();
    $_T->printTitle();

    // If ALTER time is large enough, mention something about it.
    if ($tAlter > $tAlterMax) {
        lovd_showInfoTable('Please note that the time estimated to add this column to the ' . $aTableInfo['table_name'] . ' data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table are possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    
    // Table
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '40%', '14', '60%'),
                  );

    if ($aTableInfo['shared']) {
        // If the target is received through $_GET do not show the selection list unless there is a problem with the target.
        if (!empty($_POST['target']) && !is_array($_POST['target']) && !in_array('target', $_ERROR['fields'])) {
            $aForm[] = array('', '', 'print', '<B>Enabling the ' . $zData['id'] . ' column for the ' . $aTableInfo['unit'] . ' ' . $_POST['target'] . '</B><BR><BR>' . "\n");
            print('      <INPUT type="hidden" name="target" value="' . $_POST['target'] . '">' . "\n");
        } else {
            print('      Please select the ' . $aTableInfo['unit'] . '(s) for which you want to add the ' . $zData['colid'] . ' column.<BR><BR>' . "\n");
            $nPossibleTargets = ($nPossibleTargets > 15? 15 : $nPossibleTargets);
            $aForm['target'] = array('Add this column to', '', 'select', 'target', $nPossibleTargets, $aPossibleTargets, false, true, true);
            $aForm['target_skip'] = 'skip';
        }
    } else {
        $aForm[] = array('', '', 'print', '<B>Adding the ' . $zData['id'] . ' column to the ' . $aTableInfo['table_name'] . ' data table</B></B>');
    }

    // Array which will make up the form table.
    $aForm = array_merge($aForm,
             array(
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', PAGE_TITLE),
                  ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}






if (PATH_COUNT > 2 && ACTION == 'remove') {
    // URL: /columns/VariantOnGenome/DNA?remove
    // URL: /columns/Phenotype/Blood_pressure/Systolic?remove
    // Disable specific custom column.

    $aCol = $_PE;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);
    $sCategory = $aCol[1];

    define('PAGE_TITLE', 'Remove custom data column ' . $sColumnID);
    define('LOG_EVENT', 'ColRemove');

    // Require form & column functions.
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-columns.php';

    // Required clearance depending on which type of column is being added.
    $aTableInfo = lovd_getTableInfoByCategory($sCategory);
    if ($aTableInfo['shared']) {
        lovd_isAuthorized('gene', $_AUTH['curates']); // Any gene will do.
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_MANAGER);
    }

    $zData = $_DB->query('SELECT c.*, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id = ? AND c.hgvs = 0', array($sColumnID))->fetchAssoc();
    if (!$zData) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    if (!POST && !empty($_GET['target'])) {
        $_POST['target'] = $_GET['target'];
    }

    if ($aTableInfo['shared']) {
        if ($sCategory == 'VariantOnTranscript') {
            // Retrieve list of genes that DO HAVE this column and you are authorized to remove columns from.
            $sSQL = 'SELECT g.id, CONCAT(g.id, " (", g.name, ")") FROM ' . TABLE_GENES . ' AS g INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (g.id = sc.geneid AND sc.colid = ?)';
            $aSQL = array($zData['id']);
            if ($_AUTH['level'] < LEVEL_MANAGER) {
                $sSQL .= ' AND g.id IN (?' . str_repeat(', ?', count($_AUTH['curates']) - 1) . ')';
                $aSQL = array_merge($aSQL, $_AUTH['curates']);
            }
            $sSQL .= ' ORDER BY g.id';
            $aPossibleTargets = array_map('lovd_shortenString', $_DB->query($sSQL, $aSQL)->fetchAllCombine());
            $nPossibleTargets = count($aPossibleTargets);

        } elseif ($sCategory == 'Phenotype') {
            // Retrieve list of diseases that DO HAVE this column and you are authorized to remove columns from.
            $sSQL = 'SELECT DISTINCT d.id, CONCAT(d.symbol, " (", d.name, ")") FROM ' . TABLE_DISEASES . ' AS d INNER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (d.id = sc.diseaseid AND sc.colid = ?)';
            $aSQL = array($zData['id']);
            if ($_AUTH['level'] < LEVEL_MANAGER) {
                $sSQL .= ' AND g2d.geneid IN (?' . str_repeat(', ?', count($_AUTH['curates']) - 1) . ')';
                $aSQL = array_merge($aSQL, $_AUTH['curates']);
            }
            $sSQL .= ' ORDER BY d.symbol';
            $aPossibleTargets = array_map('lovd_shortenString', $_DB->query($sSQL, $aSQL)->fetchAllCombine());
            $nPossibleTargets = count($aPossibleTargets);
        }

        if (!$nPossibleTargets) {
            // Column has already been added to everything it can be added to.
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('This column has already been removed from all ' . $aTableInfo['unit'] . 's.', 'stop');
            $_T->printFooter();
            exit;
        }
    }

    // Check if column is enabled for target.
    lovd_errorClean();
    if (!empty($_POST['target'])) {
        $aTargets = $_POST['target'];
        if (!is_array($aTargets)) {
            $aTargets = array($aTargets);
        }
        foreach($aTargets as $sTarget) {
            if (!isset($aPossibleTargets[$sTarget])) {
                lovd_errorAdd('target', 'Please a select valid ' . $aTableInfo['unit'] . ' from the list!');
                break;
            }
        }
    }

    $tAlterMax = 5; // If it takes more than 5 seconds, complain.
    $zStatus = $_DB->query('SHOW TABLE STATUS LIKE "' . $aTableInfo['table_sql'] . '"')->fetchAssoc();
    $nSizeData = ($zStatus['Data_length'] + $zStatus['Index_length']);
    $nSizeIndexes = $zStatus['Index_length'];
    // Calculating time it could take to rebuild the table. This is just an estimate and it depends
    // GREATLY on things like disk connection type (SATA etc), RPM and free space in InnoDB tablespace.
    // We are not checking the tablespace right now. Assuming the data throughput is 8MB / second, Index creation 10MB / sec.
    // (results of some quick benchmarks in September 2010 by ifokkema)
    $tAlter = ($nSizeData / (8*1024*1024)) + ($nSizeIndexes / (10*1024*1024));

    if (POST) {
        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if ($aTableInfo['shared'] && empty($_POST['target'])) {
            lovd_errorAdd('target', 'Please select a ' . $aTableInfo['unit'] . ' from the list!');
        }

        if (!lovd_error()) {
            $_T->printHeader();
            $_T->printTitle();

            $sMessage = 'Removing column from data table ' . ($tAlter < 4? '' : '(this make take some time)') . '...';

            // If ALTER time is large enough, mention something about it.
            if ($tAlter > $tAlterMax) {
                lovd_showInfoTable('Please note that the time estimated to add this column to the ' . $aInfoTable['table_name'] . ' data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table are possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
            }

            require ROOT_PATH . 'class/progress_bar.php';
            // This already puts the progress bar on the screen.
            $_BAR = new ProgressBar('', $sMessage);

            $_T->printFooter(false); // The false prevents the footer to actually close the <BODY> and <HTML> tags.
            // Now we're still in the <BODY> so the progress bar can add <SCRIPT> tags as much as it wants.
            flush();



            // Now, end with ALTER TABLE if necessary, since that will take the longest time and ends a transaction anyway.
            if (!$aTableInfo['shared']) {
                // Query text; remove column registration first.
                $sQ = 'DELETE FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = ?';
                $_DB->query($sQ, array($zData['id']));
                $_BAR->setProgress(20);
                $_BAR->setMessage('Removing column...');
                // The whole transaction stuff is useless here; alter table will commit and there's just one query before that.

                // Alter data table.
                $sQ = 'ALTER TABLE ' . $aTableInfo['table_sql'] . ' DROP COLUMN `' . $zData['id'] . '`';
                $_DB->query($sQ);
                $sMessage = 'Removed column ' . $zData['colid'] . ' (' . $zData['head_column'] . ')';

            } else {
                // Query text; remove column registration first.
                $sObject = $aTableInfo['unit'] . 'id';
                $_DB->beginTransaction();
                $sQ = 'DELETE FROM ' . TABLE_SHARED_COLS . ' WHERE ' . $sObject . ' IN (?' . str_repeat(', ?', count($aTargets) - 1) . ') AND colid = ?';
                $aQ = array_merge($aTargets, array($zData['id']));
                $_DB->query($sQ, $aQ);
                $_DB->commit();
                $_BAR->setProgress(10);
                $_BAR->setMessage('Inactivating column...');

                // Check if the column is inactive in all diseases/genes. If so, DROP column from phenotypes/variants_on_transcripts table and delete from ACTIVE_COLS.
                $nTargets = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SHARED_COLS . ' WHERE colid = ?', array($zData['id']))->fetchColumn();
                if (!$nTargets) {
                    // Deactivate the column.
                    $sQ = 'DELETE FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = ?';
                    $q = $_DB->query($sQ, array($zData['id']));
                    $_BAR->setProgress(80);
                    $_BAR->setMessage('Removing column...');

                    // Alter data table.
                    $sQ = 'ALTER TABLE ' . $aTableInfo['table_sql'] . ' DROP COLUMN `' . $zData['id'] . '`';
                    $_DB->query($sQ);
                    $sMessage = 'Removed column ' . $zData['colid'] . ' (' . $zData['head_column'] . ')';
                } else {
                    $sMessage = 'Removed column ' . $zData['colid'] . ' (' . $zData['head_column'] . ') from ' . strtoupper(substr($sObject, 0, -2)) . '(s) ' . implode(', ', $aTargets);
                }
            }

            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');

            // Write to log...
            if ($aTableInfo['shared']) {
                lovd_writeLog('Event', LOG_EVENT,  'Disabled column ' . $zData['id'] . ' (' . $zData['head_column'] . ') for ' . $nTargets . ' ' . $aTableInfo['unit'] . '(s): ' . implode(', ', $aTargets));
            }

            // Thank the user...
            $_BAR->setMessage('Successfully removed column "' . $zData['head_column'] . '"!', 'done');
            $_BAR->setMessageVisibility('done', true);

            $_BAR->redirectTo(lovd_getInstallURL() . $_PE[0] . '/' . $sCategory, 3);

           print('</BODY>' . "\n" .
                  '</HTML>' . "\n");
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    // If ALTER time is large enough, mention something about it.
    if ($tAlter > $tAlterMax) {
        lovd_showInfoTable('Please note that the time estimated to remove this column from the ' . $aTableInfo['table_name'] . ' data table is <B>' . round($tAlter) . ' seconds</B>.<BR>During this time, no updates to the data table are possible. If other users are trying to update information in the database during this time, they will have to wait a long time, or get an error.', 'warning');
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '40%', 14, '60%')
                  );

    if ($aTableInfo['shared']) {
        // If the target is received through $_GET do not show the selection list unless there is a problem with the target.
        if (!empty($_POST['target']) && !is_array($_POST['target']) && !in_array('target', $_ERROR['fields'])) {
            $aForm[] = array('', '', 'print', '<B>Removing the ' . $zData['id'] . ' column from ' . $aTableInfo['unit'] . ' ' . $_POST['target'] . '.</B><BR><BR>' . "\n");
            print('      <INPUT type="hidden" name="target" value="' . $_POST['target'] . '">' . "\n");
        } else {
            print('      Please select the ' . $aTableInfo['unit'] . '(s) for which you want to remove the ' . $zData['colid'] . ' column.<BR><BR>' . "\n");
            $nPossibleTargets = ($nPossibleTargets > 15? 15 : $nPossibleTargets);
            $aForm[] = array('Remove this column from', '', 'select', 'target', $nPossibleTargets, $aPossibleTargets, false, true, true);
            $aForm[] = 'skip';
        }
    } else {
        $aForm[] = array('', '', 'print', '<B>Removing the ' . $zData['colid'] . ' column from the ' . $aTableInfo['table_name'] . ' data table</B>');
    }

    $aForm = array_merge($aForm,
             array(
                    array('Enter your password for authorization', '', 'password', 'password', 20),
                    array('', '', 'submit', PAGE_TITLE),
                  )
                       );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT > 2 && ACTION == 'delete') {
    // URL: /columns/VariantOnGenome/DNA?delete
    // URL: /columns/Phenotype/Blood_pressure/Systolic?delete
    // Drop specific custom column.
    
    $aCol = $_PE;
    unset($aCol[0]); // 'columns';
    $sColumnID = implode('/', $aCol);
    $sCategory = $aCol[1];

    $zData = $_DB->query('SELECT c.id, c.hgvs, c.head_column, ac.colid, c.created_by FROM ' . TABLE_COLS . ' AS c LEFT OUTER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c.id = ac.colid) WHERE c.id = ?', array($sColumnID))->fetchAssoc();

    $sMessage = '';
    if (!$zData) {
        $sMessage = 'No such column!';
    } elseif ($zData['colid']) {
        $sMessage = 'Column is still active, disable it first!';
    } elseif (!(int) $zData['created_by']) {
        $sMessage = 'Only custom columns created by an LOVD user may be deleted from the system. This column however, is created by LOVD itself.';
    } elseif ($zData['hgvs']) {
        lovd_writeLog('Error', 'HackAttempt', 'Tried to remove HGVS column ' . $zData['id'] . ' (' . $zData['head_column'] . ')');
        $sMessage = 'Hack Attempt!';
    }
    if ($sMessage) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable($sMessage, 'stop');
        $_T->printFooter();
        exit;
    }

    // Require form & column functions.
    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-columns.php';

    define('PAGE_TITLE', 'Delete custom data column ' . $sColumnID);
    define('LOG_EVENT', 'ColDelete');

    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_columns.php';
    $_DATA = new LOVD_Column();

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            $_DATA->deleteEntry($sColumnID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted column ' . $sColumnID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $sCategory);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the column ' . $sColumnID . '!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");
    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Permanently deleting column', '', 'print', '<B>' . $sColumnID . '</B>'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete column permanently'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
