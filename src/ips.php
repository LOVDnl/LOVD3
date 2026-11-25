<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2026-11-21
 * Modified    : 2026-11-25
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2026 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
define('TAB_SELECTED', 'setup');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /ips
    // View all IP entries.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('<B>To create a new IP entry, click here.</B>', 'question', '33%', CURRENT_PATH . '?create');

    require ROOT_PATH . 'class/object_ips.php';
    $_DATA = new LOVD_IP();

    $_DATA->viewList('IPs', array('show_options' => true));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /ips?create
    // Create a new IP entry.

    define('LOG_EVENT', 'IPCreate');
    define('PAGE_TITLE', lovd_getCurrentPageTitle());

    require ROOT_PATH . 'class/object_ips.php';
    $_DATA = new LOVD_IP();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('ip_start', 'ip_end', 'level', 'reason', 'annotation');

            // Prepare values.
            $sIPStart = str_replace('*', '0', $_POST['name']);
            $sIPEnd = str_replace('*', '255', $_POST['name']);
            while (substr_count($sIPStart, '.') < 3) {
                $sIPStart .= '.0';
                $sIPEnd .= '.255';
            }
            $_POST['ip_start'] = ip2long($sIPStart);
            $_POST['ip_end'] = ip2long($sIPEnd);
            $b = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created IP entry ' . $_POST['ip_start'] . ' (' . $_POST['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $_POST['ip_start'] . '?edit');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the IP entry!', 'success');

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new IP entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Create IP entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /ips/1?edit
    // Edit specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IPEdit');

    require ROOT_PATH . 'class/object_ips.php';
    $_DATA = new LOVD_IP();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('level', 'reason', 'annotation');

            if (!empty($_POST['stretch-up'])) {
                // Instead of editing normally, we'll also update the start IP to *.0.
                $nNewID = lovd_getIPSubnetStart($zData['ip_start']);
                if ($nNewID > $zPrevIP['ip_end']) {
                    $_DATA->updateEntry(['ip_start' => $nID], $_POST, $aFields);
                    // By design, updateEntry() can't help us, as we need to change the ID of the entry.
                    $b = $_DB->q('UPDATE ' . TABLE_IPS . ' SET ip_start = ? WHERE ip_start = ?', [$nNewID, $nID]);
                }

            } else {
                $_DATA->updateEntry(['ip_start' => $nID], $_POST, $aFields);
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited IP entry ' . $nID . ' (' . $zData['ip_range'] . ')');

            // We don't thank the user, we want to move on quickly.
            if (!isset($nNewID)) {
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . '?' . ACTION);
            } else {
                header('Location: ' . lovd_getInstallURL() . $_PE[0] . "/$nNewID?" . ACTION);
            }
            exit;
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" onsubmit="return lovd_checkForm();">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit IP entry'),
                      ));

    // If we can stretch the IP up, offer to do so.
    if (!lovd_isIPFirstInSubnet($zData['ip_start'])
        && lovd_getIPSubnetEnd($zPrevIP['ip_end']) < lovd_getIPSubnetStart($zData['ip_start'])) {
        // We can stretch this IP up, as its start doesn't end with *.0.
        // Also, the previous IP entry does not end in the same subnet.
        $aForm[] = ['', '', 'submit', 'Stretch up to *.0', 'stretch-up'];
    }
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
