<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2011-03-08
 * For LOVD    : 3.0-pre-18
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

// Stupid solution, but because of the (sane) JS restrictions to access files on other domains, I have to do it this way.
if (isset($_GET['check_url'])) {
    // Verify signature also.
    if (empty($_GET['check_url'])) {
        readfile($_SETT['check_location_URL'] . '?url=' . rawurlencode(lovd_getInstallURL()) . '&signature=' . rawurlencode($_STAT['signature']));
    } else {
        readfile($_SETT['check_location_URL'] . '?url=' . rawurlencode(rtrim($_GET['check_url'], '/') . '/') . '&signature=' . rawurlencode($_STAT['signature']));
    }
    exit;
}

require ROOT_PATH . 'inc-js-ajax.php';

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
    } else if (document.forms[0].location_url.value == '') {
        // Wasn't filled in before, and now still isn't.
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



function lovd_checkURL () {
    var objField = document.getElementById('location_url');
    var objCheck = document.getElementById('location_url_check');

    // Reset (check) link.
    // 2009-06-26; 2.0-19; Fixed URL such that it works from all locations.
    objCheck.innerHTML = '<IMG src="<?php echo lovd_getInstallURL(); ?>gfx/lovd_loading.gif" align="top">';

    // Create HTTP request object to contact the LOVD website to verify the database URL.
    var objHTTP = lovd_createHTTPRequest();
    if (objHTTP) {
        // 2009-06-26; 2.0-19; Fixed URL such that it works from all locations.
        objHTTP.open("GET", "<?php echo lovd_getInstallURL(); ?>inc-js-submit-settings.php?check_url=" + escape(objField.value), false);
        objHTTP.send(null);
        if (objHTTP.status == 200 && objHTTP.responseText.substring(0,4) == "http") {
            objField.value = objHTTP.responseText;
            // 2009-06-26; 2.0-19; Fixed URL such that it works from all locations.
            objCheck.innerHTML = '<IMG src="<?php echo lovd_getInstallURL(); ?>gfx/mark_1.png" align="top">';
        } else {
            // Throw error.
            if (!objField.value) {
                // Well no, we were just trying the automated values. So, it doesn't work. Big deal.
                window.alert("Please fill in a value in this field.");
            } else {
                window.alert("Error!\n" + objHTTP.responseText);
            }
            objCheck.innerHTML = '(<A href="#" onclick="javascript:lovd_checkURL(); return false;">check</A>)';
        }

    } else {
        // Change "loading" image with a clean "Failed" image.
        window.alert("Sorry, your browser does not support automated verification of the URL.");
        // 2009-06-26; 2.0-19; Fixed URL such that it works from all locations.
        objCheck.innerHTML = '<IMG src="<?php echo lovd_getInstallURL(); ?>gfx/mark_0.png" align="top">';
    }
}
