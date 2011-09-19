<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-21
 * Modified    : 2011-09-09
 * For LOVD    : 3.0-alpha-05
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
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
lovd_requireAUTH(LEVEL_SUBMITTER);




if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /submit
    // Submission process 

    define('PAGE_TITLE', 'Submit new information to this database');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);
    print('      Do you have any information available regarding an individual or a group of individuals?<BR><BR>' . "\n\n" .
          '      <TABLE border="0" cellpadding="5" cellspacing="1" class="option">' . "\n" .
          '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'individuals?create\'">' . "\n" .
          '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
          '          <TD><B>Yes, I want to submit information on individuals</B>, such as phenotype or mutation screening information</TD></TR>' . "\n" .
          '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'variants?create\'">' . "\n" .
          '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
          '          <TD><B>No, I will only submit summary variant data</B></TD></TR></TABLE><BR>' . "\n\n");
    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && $_PATH_ELEMENTS[1] == 'individual') {

    define('LOG_EVENT', 'SubmitIndividual');

    if (isset($_GET['individualid']) && ctype_digit($_GET['individualid'])) {
        $_GET['individualid'] = str_pad($_GET['individualid'], 8, "0", STR_PAD_LEFT);
        if (isset($_SESSION['work']['submits'][$_GET['individualid']])) {
            $aSubmit = $_SESSION['work']['submits'][$_GET['individualid']];
        } else {
            exit;
        }
    } else {
        exit;
    }

    // FIXME; Implement a proper check if the user is curator of ALL the data that is being submitted.
    // If there is even one variant that is not connected to this user, then the user needs to be thanked anyway
    // and the proper curator(s) need(s) to be informed through mail of these particular variants.
    if (isset($aSubmit['screenings'])) {
        $aVariantIDs = array();
        foreach($aSubmit['screenings'] as $nScreeningID => $aVariants) {
            if (isset($aVariants['variants'])) {
                $aVariantIDs = array_merge($aVariantIDs, array_keys($aVariants['variants']));
            }
        }
        lovd_isAuthorized('variant', $aVariantIDs);
    }

    if ($_AUTH['level'] >= LEVEL_CURATOR) {
        header('Location: ' . lovd_getInstallURL() . 'individuals/' . $_GET['individualid']);
    } else {
        header('Refresh: 3; url=' . lovd_getInstallURL() . 'individuals/' . $_GET['individualid']);
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        $_DB->beginTransaction();
        $q = $_DB->prepare('UPDATE ' . TABLE_INDIVIDUALS . ' SET statusid=? WHERE id=? AND statusid=?', array(STATUS_PENDING, $_GET['individualid'], STATUS_IN_PROGRESS));
        if (!$q->rowCount()) {
            lovd_showInfoTable('Individual submission entry not found!', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
        if (!empty($aSubmit['phenotypes'])) {
            $_DB->prepare('UPDATE ' . TABLE_PHENOTYPES . ' SET statusid=? WHERE id IN (?' . str_repeat(', ?', count($aSubmit['phenotypes']) - 1) . ')', array_merge(array(STATUS_PENDING), $aSubmit['phenotypes']));
        }
        if (!empty($aVariantIDs)) {
            $_DB->prepare('UPDATE ' . TABLE_VARIANTS . ' SET statusid=? WHERE id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge(array(STATUS_PENDING), $aVariantIDs));
        }
        // FIXME; Implement mail to curator here.
        // mail();
        $_DB->commit();
        lovd_showInfoTable('Successfully processed your submission<!-- and sent an e-mail notification to the relevant curator(s)-->!', 'success');
        require ROOT_PATH . 'inc-bot.php';
    }
    unset($_SESSION['work']['submits'][$_GET['individualid']]);
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && $_PATH_ELEMENTS[1] == 'variant') {

    if (isset($_GET['variantid']) && ctype_digit($_GET['variantid'])) {
        $_GET['variantid'] = str_pad($_GET['variantid'], 10, "0", STR_PAD_LEFT);
        $zData = $_DB->prepare('SELECT id FROM ' . TABLE_VARIANTS . ' WHERE id=? AND statusid=?', array($_GET['variantid'], STATUS_IN_PROGRESS));
        if ($zData) {
            // STUB
        } else {
            exit;
        }
    } else {
        exit;
    }

    lovd_isAuthorized('variant', $_GET['variantid']);

    if ($_AUTH['level'] >= LEVEL_CURATOR) {
        header('Location: ' . lovd_getInstallURL() . 'variants/' . $_GET['variantid']);
    } else {
        header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $_GET['variantid']);
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        $q = $_DB->prepare('UPDATE ' . TABLE_VARIANTS . ' SET statusid=? WHERE id=? AND statusid=?', array(STATUS_PENDING, $_GET['variantid'], STATUS_IN_PROGRESS));
        if (!$q->rowCount()) {
            lovd_showInfoTable('Variant submission entry not found!', 'stop');
            require ROOT_PATH . 'inc-bot.php';
            exit;
        }
        // FIXME; Implement mail to curator here.
        // mail();
        lovd_showInfoTable('Successfully processed your submission<!-- and sent an e-mail notification to the relevant curator(s)-->!', 'success');
        require ROOT_PATH . 'inc-bot.php';
    }
    exit;
}
?>
