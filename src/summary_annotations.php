<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-04
 * Modified    : 2019-11-20
 * For LOVD    : 3.0-23
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

if (!LOVD_plus && PATH_COUNT > 1) {
    // Load appropriate user level for this ID's gene.
    list($sGene,) = explode('_', $_PE[1], 2);
    lovd_isAuthorized('gene', $sGene);
}





if (PATH_COUNT == 2 && ACTION == 'create') {
    // URL: /summary_annotations/chrX_000030?create
    // Create a new summary annotation record.

    $sID = $_PE[1];
    define('PAGE_TITLE', 'Create a new summary annotation record');
    define('LOG_EVENT', 'SARCreate');

    // For redirection.
    $nIDToRedirectTo = (empty($_REQUEST['redirect_to'])? 0 : $_REQUEST['redirect_to']);

    lovd_requireAUTH($_SETT['user_level_settings']['summary_annotation_create']); // FIXME; authorize curators.

    require ROOT_PATH . 'class/object_summary_annotations.php';
    $_DATA = new LOVD_SummaryAnnotation();
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                array('id', 'effectid', 'created_by', 'created_date'),
                $_DATA->buildFields());

            // Prepare values.
            $_POST['id'] = $sID;
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created summary annotation record - ' . $sID);

            // Thank the user...
            if ($nIDToRedirectTo) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nIDToRedirectTo . (isset($_GET['in_window'])? '?&in_window' : ''));
            }

            $_T->printHeader();
            $_T->printTitle();

            lovd_showInfoTable('Successfully created the summary annotation record!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new summary annotation record, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&in_window' : '') . '" method="post">' . "\n" .
        (!$nIDToRedirectTo? '' :
            '        <INPUT type="hidden" name="redirect_to" value="' . $nIDToRedirectTo . '">' . "\n"));

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Create summary annotation record'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'edit') {
    // URL: /summary_annotations/chrX_000030?edit
    // Edit a specific entry.

    $sID = $_PE[1];
    define('PAGE_TITLE', 'Edit summary annotations for variant ' . $sID);
    define('LOG_EVENT', 'SAREdit');

    // For redirection.
    $nIDToRedirectTo = (empty($_REQUEST['redirect_to'])? 0 : $_REQUEST['redirect_to']);

    lovd_requireAUTH($_SETT['user_level_settings']['summary_annotation_edit']);

    require ROOT_PATH . 'class/object_summary_annotations.php';
    $_DATA = new LOVD_SummaryAnnotation();
    $zData = $_DATA->loadEntry($sID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                array('effectid', 'edited_by', 'edited_date'),
                $_DATA->buildFields());

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($sID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited summary annotation record - ' . $sID);

            // Thank the user...
            if ($nIDToRedirectTo) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nIDToRedirectTo . (isset($_GET['in_window'])? '?&in_window' : ''));
            }

            $_T->printHeader();
            $_T->printTitle();

            lovd_showInfoTable('Successfully edited the summary annotation record!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To edit the summary annotation record, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&in_window' : '') . '" method="post">' . "\n" .
        (!$nIDToRedirectTo? '' :
            '        <INPUT type="hidden" name="redirect_to" value="' . $nIDToRedirectTo . '">' . "\n"));

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Edit summary annotation record'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





print('No condition met using the provided URL.');
?>
