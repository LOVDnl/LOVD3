<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2011-04-13
 * For LOVD    : 3.0-pre-19
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /individuals
    // View all entries.

    define('PAGE_TITLE', 'View individuals');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $_DATA->viewList();

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^(\d)+$/', $_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /individuals/00000001
    // View specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'View individual #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual($nID);
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="individuals/' . $nID . '?edit">Edit individual information</A>';
        $sNavigation .= ' | <A href="individuals/' . $nID . '?delete">Delete individual entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }
    
    $_GET['search_diseaseid'] = explode(',', $zData['diseases']);
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Diseases', 'H4');
    require ROOT_PATH . 'class/object_diseases.php';
    $_DATA = new LOVD_Disease();
    $_DATA->setSortDefault('id');
    $_DATA->viewList(false, 'individualid', true, true);
    
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Phenotypes', 'H4');
    $qDiseases = lovd_queryDB('SELECT d.id, d.symbol, d.name FROM ' . TABLE_IND2DIS . ' AS i2d LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) WHERE individualid="' . $nID . '"');
    require ROOT_PATH . 'class/object_phenotypes.php';
    while($zDisease = mysql_fetch_assoc($qDiseases)) {
        $_GET['search_diseaseid'] = $zDisease['id'];
        $_DATA = new LOVD_Phenotype($zDisease['id']);
        $_DATA->setSortDefault('id');
        print('<B>' . $zDisease['name'] . ' (<A href="diseases/' . $zDisease['id'] . '">' . $zDisease['symbol'] . '</A>)</B>');
        $_DATA->viewList(false, array('individualid', 'diseaseid'), true, true);
    }
    
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Variants', 'H4');
    require ROOT_PATH . 'class/object_variants.php';
    $_DATA = new LOVD_Variant();
    $_DATA->setSortDefault('id');
    $_DATA->viewList(false, 'individualid', true);

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && preg_match('/^\d+$/', $_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /individuals/00000001?delete
    // Drop specific entry.

    $nID = $_PATH_ELEMENTS[1];
    define('PAGE_TITLE', 'Delete individual information entry ' . $nID);
    define('LOG_EVENT', 'IndividualDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
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
            // Query text.
            // This also deletes the entries in gen2dis and transcripts.
            // FIXME; implement deleteEntry()
            $sSQL = 'DELETE FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?';
            $aSQL = array($zData['id']);
            $q = lovd_queryDB($sSQL, $aSQL);
            if (!$q) {
                lovd_queryError(LOG_EVENT, $sSQL, mysql_error());
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted individual information entry ' . $nID . ' - ' . $zData['id'] . ' (Owner: ' . $zData['owner'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'individuals');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the individual information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting individual information entry', '', 'print', $zData['id'] . ' (Owner: ' . $zData['owner'] . ')'),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete individual information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}

?>
