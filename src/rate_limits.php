<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-09-03
 * Modified    : 2024-09-05
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /rate_limits
    // View all entries.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    if (empty($_CONF['use_rate_limiting'])) {
        lovd_showInfoTable(
            "Rate limiting is currently turned off in the system settings. Any created limits won't work until rate limiting is enabled. Click here to change the system settings.",
            'information',
            '100%',
            'settings?edit'
        );
    }

    lovd_showInfoTable('<B>To create a new rate limit, click here.</B>', 'question', '33%', CURRENT_PATH . '?create');

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();
    $_DATA->viewList('RateLimits');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /rate_limits/00001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    if (empty($_CONF['use_rate_limiting'])) {
        lovd_showInfoTable(
            "Rate limiting is currently turned off in the system settings. Any created limits won't work until rate limiting is enabled. Click here to change the system settings.",
            'information',
            '100%',
            'settings?edit'
        );
    }

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();

    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    // Authorized user is logged in. Provide tools.
    $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Edit rate limit', 1);
    $aNavigation[CURRENT_PATH . '?clear'] = array('menu_empty.png', 'Clear history and current blocks', 1);
    $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete rate limit', 1);

    lovd_showJGNavigation($aNavigation, 'RateLimits');



    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Most recent activity for this rate limit', 'H4');

    require ROOT_PATH . 'class/object_rate_limits_data.php';
    $_DATA = new LOVD_RateLimitData();
    $_GET['search_ratelimitid'] = $nID;
    $_DATA->viewList('RLData_for_RateLimits_VE');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /rate_limits?create
    // Create a new rate limit.

    define('LOG_EVENT', 'RateLimit' . ucfirst(ACTION));
    define('PAGE_TITLE', lovd_getCurrentPageTitle());

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('active', 'name', 'ip_pattern', 'user_agent_pattern', 'url_pattern', 'max_hits_per_min', 'delay', 'message', 'created_by', 'created_date');

            // Prepare values.
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created rate limit ' . $nID . ' (' . $_POST['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $nID);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the rate limit!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable("Be very careful when setting a rate limit. <B>Only use this feature when you know what it's for and when you're sure what you're doing.</B> When set incorrectly, a large number of users won't be able to connect to LOVD. <B>In the worst case scenario, you won't be able to connect to LOVD to correct the issue.</B> So make sure you create a very specific rate limit. Also, when unsure, keep the allowed rate high, check the status, and decrease the limit after you've confirmed that only the intended users are affected.", 'warning');

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Create rate limit'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /rate_limits/00001?edit
    // Edit a specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'RateLimit' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('active', 'name', 'ip_pattern', 'user_agent_pattern', 'url_pattern', 'max_hits_per_min', 'delay', 'message', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited rate limit ' . $nID . ' (' . $_POST['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the rate limit!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable("Be very careful when setting a rate limit. <B>Only use this feature when you know what it's for and when you're sure what you're doing.</B> When set incorrectly, a large number of users won't be able to connect to LOVD. <B>In the worst case scenario, you won't be able to connect to LOVD to correct the issue.</B> So make sure you create a very specific rate limit. Also, when unsure, keep the allowed rate high, check the status, and decrease the limit after you've confirmed that only the intended users are affected.", 'warning');

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit rate limit'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'clear') {
    // URL: /rate_limits/00001?clear
    // Clear a limit's data and current blocks.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'RateLimit' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();
    $zData = $_DATA->loadEntry($nID);

    // Let's just do this the easy way. If we're here, the entry exists.
    $b = $_DB->q('DELETE FROM ' . TABLE_RATE_LIMITS_DATA . ' WHERE ratelimitid = ?', [$nID], false);
    if (!$b) {
        // Failed.
        lovd_showInfoTable("Failed to clear this limit's history and blocks.", 'stop');
        $_T->printFooter();
        exit;
    }

    // If we get here, we succeeded. Just send the user back.
    header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /rate_limits/00001?delete
    // Delete a specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'RateLimit' . ucfirst(ACTION));

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_rate_limits.php';
    $_DATA = new LOVD_RateLimit();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');

        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter their password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted rate limit ' . $nID . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the rate limit!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
        array('POST', '', '', '', '50%', '14', '50%'),
        array('Deleting rate limit', '', 'print', $zData['name']),
        'skip',
        array('Enter your password for authorization', '', 'password', 'password', 20),
        array('', '', 'submit', 'Delete rate limit'),
    );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
