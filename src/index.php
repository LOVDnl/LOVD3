<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-05-25
 * Modified    : 2011-07-05
 * For LOVD    : 3.0-alpha-02
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Already logged in to the system.
if ($_AUTH) {
    // Send manager and database administrator to setup, the rest to the gene page.
    // FIXME; Read current gene database from cookie! (but check value, gene may have been deleted by now)
    header('Location: ' . lovd_getInstallURL() . ($_AUTH['level'] >= LEVEL_MANAGER? 'setup' : 'genes'));
    exit;
} else {
    // Gene listing.
    header('Location: ' . lovd_getInstallURL() . 'genes');
    exit;
}
?>
