<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-25
 * Modified    : 2022-11-22
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

// Send manager and database administrator to setup, curators to the config, with selected database to the gene homepage, the rest to the gene listing.
if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
    $sFile = 'setup';
} elseif ($_AUTH && $_SESSION['currdb'] && lovd_isAuthorized('gene', $_SESSION['currdb'], false)) {
    $sFile = 'configuration';
} elseif ($_SESSION['currdb']) {
    $sFile = 'genes/' . $_SESSION['currdb'];
} else {

    $aGeneIDs = $_DB->q('SELECT id FROM ' . TABLE_GENES . ' LIMIT 2')->fetchAllColumn();
    if (count($aGeneIDs) == 1) {
        $sFile = 'genes/' . $aGeneIDs[0];
    } else {
        $sFile = 'genes';
    }
}

if (LOVD_plus) {
    if ($_AUTH && $_AUTH['level'] == LEVEL_ADMIN) {
        $sFile = 'setup';
    } elseif ($_AUTH) {
        $sFile = 'individuals';
    } else {
        $sFile = 'login';
    }
}

header('Location: ' . lovd_getInstallURL() . $sFile);
exit;
?>
