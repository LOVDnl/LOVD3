<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2022-01-14
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
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
header('Content-type: text/javascript; charset=UTF-8');

// If not installed...
if (!isset($_CONF['location_url'])) {
    $_CONF['location_url'] = '';
}
?>
function lovd_checkForm () {
    sMessage = '';
    if (<?php echo (int) ($_CONF['location_url'] != ''); ?>) {
        // URL was filled in...
        if (document.forms[0].location_url.value == '') {
            // ... but is now removed!
            sMessage = 'Are you sure you want to remove the database URL? This has serious consequences!\nLOVD will no longer be able to generate reliable links to itself, for instance for emails sent by the system. Please consider configuring a correct and lasting URL!\n\nPress "Cancel" to return to the form to fill in a url, or "OK" to ignore this warning.';
        } else if (document.forms[0].location_url.value != '<?php echo $_CONF['location_url']; ?>') {
            // ... but is now changed!
            sMessage = 'Are you really sure you want to change the database URL? This may have serious consequences!\nIf this URL is not correct, links generated to this LOVD, for instance in emails sent by the system, will cease to function. Please make sure you configure a correct and lasting URL!\n\nPress "Cancel" to return to the form, or "OK" to ignore this warning.';
        }
    } else if (document.forms[0].location_url.value == '' && <?php echo (LOVD_plus? 'false' : 'true'); ?>) {
        // Wasn't filled in before, and now still isn't. // We don't care of this is LOVD+.
        sMessage = 'Are you sure you don\'t want to select a database url?\nPress "Cancel" to return to the form to fill in an URL, or "OK" to ignore this warning.';
    }

    // Now, if there's a message, display it.
    if (sMessage) {
        if (window.confirm(sMessage)) {
            return true;
        } else {
            scroll(0,0);
            return false;
        }
    } else {
        return true;
    }
}
