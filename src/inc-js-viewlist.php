<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-29
 * Modified    : 2011-03-28
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
require ROOT_PATH . 'inc-lib-init.php';
require ROOT_PATH . 'inc-js-ajax.php';

// Find out whether or not we're using SSL.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' && !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('PROTOCOL', 'https://');
} else {
    define('PROTOCOL', 'http://');
}
?>

function lovd_loadListView ()
{
    // Get the hash, explode it, interpret it.
    if (window.location.hash) {
        if (window.location.hash != prevHash) {
            var aGET = window.location.hash.substr(1).split('&');
            var GET = new Array();

            // In case multiple viewList's exist, we choose the first one. In practice, most likely hashing is turned off on pages with multiple viewLists.
            for (var i in document.forms) {
                if (document.forms[i].id && document.forms[i].id.substring(0, 13) == 'viewlistForm_') {
                    oForm = document.forms[i];
                    sViewListID = document.forms[i].id.substring(13);
                    break;
                }
            }

            for (var i in aGET) {
                var tmp = aGET[i].split('=');
                GET[tmp[0]] = tmp[1];
                // Search fields.
                if (tmp[0].substr(0,7) == 'search_' && tmp[1] && oForm[tmp[0]]) {
                    oForm[tmp[0]].value = decodeURIComponent(tmp[1]);
                }
            }
            if (GET['order']) {
                oForm.order.value = decodeURIComponent(GET['order']);
            }
            if (GET['page_size'] && GET['page']) {
                oForm.page_size.value = decodeURIComponent(GET['page_size']);
                oForm.page.value = decodeURIComponent(GET['page']);
            }
            lovd_submitList(sViewListID);
            prevHash = window.location.hash;
        }
    }
}



function lovd_navPage (sViewListID, nPage) {
    oForm = document.forms['viewlistForm_' + sViewListID];
    oForm.page.value = nPage;
    lovd_submitList(sViewListID);
}



function lovd_submitList (sViewListID) {
    oForm = document.forms['viewlistForm_' + sViewListID];
    // Used to have a simple loop through oForm, but Google Chrome does not like that.
    aInput = oForm.getElementsByTagName('input');
    for (var i in aInput) {
        if (aInput[i].name && aInput[i].name.substring(0, 7) == 'search_' && !aInput[i].value) {
            aInput[i].disabled = true;
        }
    }

    // Create HTTP request object.
    var objHTTP = lovd_createHTTPRequest();
    if (!objHTTP) {
        // Ajax not supported. Use fallback non-Ajax navigation.
        oForm['viewlistid'].disabled = true; // We use this for Ajax capabilities, but don't want it to show in the URL.
        oForm['object'].disabled = true; // We use this for Ajax capabilities, but don't want it to show in the URL.
        oForm.submit();
    } else {
        // Submit the form over Ajax. Sort of.

        var oDiv = document.getElementById('viewlistDiv_' + sViewListID);
        document.body.style.cursor = 'progress'; // 'wait' is probably too much.

        objHTTP.onreadystatechange = function ()
        {
            if (objHTTP.readyState == 4) {
                document.body.style.cursor = '';
                if (objHTTP.status == 200) {
                    if (objHTTP.responseText.length > 100) {
                        // Successfully retrieved stuff.
                        oDiv.innerHTML = objHTTP.responseText;
                        // The following adds the page to the history in Firefox, such that the user *can* push the back button.
                        // I chose not to use sGET (created somewhere below) here, because it contains 'viewlistid' and 'object' which I don't want to use now and I guess it would be possible that it won't be set.
                        var sHash = '';
                        aInput = oForm.getElementsByTagName('input');
                        for (var i in aInput) {
                            if (!aInput[i].disabled && aInput[i].value && aInput[i].name != 'viewlistid' && aInput[i].name != 'object') {
                                sHash = (sHash? sHash + '&' : '') + aInput[i].name + '=' + encodeURIComponent(aInput[i].value);
                            }
                        }
                        window.location.hash = sHash;
                        // lovd_loadListView() itself will actually allow that when the back button is pressed, the correct page is loaded.
                        // The following makes sure this change we just made does not have reload lovd_loadListView().
                        prevHash = window.location.hash;
                        return true;
                    } else if (objHTTP.responseText == '8') {
                        window.alert('Lost your session. Please log in again.');
                    } else if (objHTTP.responseText == '9') {
                        window.alert('Error while sending data. Please try again.');
                    } else if (!objHTTP.responseText || objHTTP.responseText == '0') {
                        // Silent failure.
                        return false;
                    } else {
                        window.alert('Unknown response :' + objHTTP.responseText);
                    }
                } else {
                    // Maybe we should remove this...?
                    window.alert('Server error: ' + objHTTP.status);
                }
            }
        }
        // Build GET query.
        var sGET = '';
        aInput = oForm.getElementsByTagName('input');
        for (var i in aInput) {
            if (!aInput[i].disabled && aInput[i].value) {
                sGET = (sGET? sGET + '&' : '') + aInput[i].name + '=' + encodeURIComponent(aInput[i].value);
            }
        }
        objHTTP.open('GET', '<?php echo lovd_getInstallURL() . 'ajax/viewlist.php?'; ?>' + sGET, true);
        objHTTP.send(null);
    }
}




// This allows the interpretation of the URL hash part, such that pages can be linked to.
window.onload = function ()
{
    prevHash = '';
    lovd_loadListView();
    setInterval(lovd_loadListView, 250);
}

