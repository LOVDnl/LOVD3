<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-12
 * Modified    : 2024-09-04
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

// Require manager clearance.
lovd_requireAUTH(LEVEL_MANAGER);





if (PATH_COUNT == 1 && ACTION == 'edit') {
    // URL: /settings?edit
    // Edit system settings.

    define('PAGE_TITLE', 'Edit system settings');
    define('LOG_EVENT', 'ConfigEdit');

    require ROOT_PATH . 'class/object_system_settings.php';
    $_DATA = new LOVD_SystemSetting();
    $zData = $_CONF;
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Standard fields to be used.
            // Removed compared to the install form: refseq_build, lock_uninstall.
            // FIXME; we can't use updateEntry() right now, because that needs an ID (and damn right, too). Now this is more manual work. Can we fix/bypass that?
            $aFields = array(
                'system_title', 'institute', 'location_url', 'email_address', 'send_admin_submissions',
                'proxy_host', 'proxy_port', 'proxy_username', 'proxy_password',
                'md_apikey',
                'logo_uri', 'donate_dialog_allow', 'donate_dialog_months_hidden',
                'send_stats', 'include_in_listing',
                'allow_submitter_registration', 'lock_users', 'allow_unlock_accounts', 'allow_submitter_mods', 'use_ssl', 'use_rate_limiting'
            );

            // Prepare values.
            // Make sure the database URL ends in a /.
            if ($_POST['location_url'] && substr($_POST['location_url'], -1) != '/') {
                $_POST['location_url'] .= '/';
            }
            // This optimalization is normally done in updateEntry().
            if (empty($_POST['proxy_port'])) {
                // Empty port number, insert NULL instead of 0.
                $_POST['proxy_port'] = NULL;
            }

            // Query text.
            $sSQL = 'UPDATE ' . TABLE_CONFIG . ' SET ';
            $aSQL = array();
            foreach ($aFields as $key => $sField) {
                $sSQL .= (!$key? '' : ', ') . $sField . ' = ?';
                $aSQL[] = $_POST[$sField];
            }

            $q = $_DB->q($sSQL, $aSQL, true, true);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited system configuration');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'setup');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the system settings!', 'success');

            $_T->printFooter();
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
    // Allow checking the database URL.
    lovd_includeJS('inc-js-submit-settings.php');

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" onsubmit="return lovd_checkForm();">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        'skip',
                        array('', '', 'submit', PAGE_TITLE),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
