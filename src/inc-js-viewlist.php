<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-29
 * Modified    : 2011-10-18
 * For LOVD    : 3.0-alpha-05
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

if (!isset($_GET['nohistory'])) {
?>


function lovd_AJAX_processViewListHash ()
{
    // Get the hash, explode it, interpret it.
    if (window.location.hash) {
        if (window.location.hash != prevHash) {
            var aGET = window.location.hash.substr(1).split('&');
            var GET = new Array();

            // In case multiple viewList's exist, we choose the first one. In practice, hashing is turned off on pages with multiple viewLists.
            for (var i in document.forms) {
                if (document.forms[i].id && document.forms[i].id.substring(0, 13) == 'viewlistForm_') {
                    oForm = document.forms[i];
                    sViewListID = document.forms[i].id.substring(13);
                    break;
                }
            }

            for (var i in aGET) {
                // Split on the *first* = character. Firefox (at least 3.6.16@Ubuntu) doesn't get that %3D is not the same as =. IE and Chrome do well.
                var tmp = aGET[i].split(/=(.+)?/);
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
            lovd_AJAX_viewListSubmit(sViewListID);
            prevHash = window.location.hash;
        }
    }
}
<?php
}
?>


function lovd_AJAX_viewListAddNextRow (sViewListID)
{
    // Load row for next page, because one row got deleted. So basically we're
    // asking for the row that we *expect* to be the first on the next page,
    // but we provide coordinates for the last row on this page.
    oForm = document.forms['viewlistForm_' + sViewListID];

    // However, make sure we only do this when it's needed!
    // Check if we're the last page or not...!
    if (oForm.total.value < (oForm.page.value * oForm.page_size.value)) {
        return true;
    }

    // Disable the empty search fields.
    for (var i in oForm) {
        if (i.substring(0, 7) == 'search_') {
            if (!oForm[i].value) {
                oForm[i].disabled = true;
            }
        }
    }

    // Create HTTP request object.
    var objHTTP = lovd_createHTTPRequest();
    if (!objHTTP) {
        // Ajax not supported. Use fallback non-Ajax navigation.
        //oForm['object'].disabled = true; // We use this for Ajax capabilities, but don't want it to show in the URL.
        //oForm.submit(); // Simply refresh the page.
    } else {
        // Find the next entry and add it to the table.

        objHTTP.onreadystatechange = function ()
        {
            if (objHTTP.readyState == 4) {
                if (objHTTP.status == 200) {
                    if (objHTTP.responseText.length > 100) {
                        // Successfully retrieved stuff.
                        var sResponse = objHTTP.responseText;
                        // Clone last TR and fill in the new response data and returns the row.
                        var newRow = $('#viewlistTable_' + sViewListID + ' tr:last').clone();
                        // For some reason .clone() adds a style attribute to the row. Let's remove it.
                        $(newRow).removeAttr('style');
                        var attributes = new RegExp(/<TR( ([a-z]+)="(.+?)")/i);
                        var values = new RegExp(/>(.+)<\/TD/);
                        var aResponse = jQuery.trim(sResponse).split(/\n/);
                        // Overwrite all attributes of newRow with the attributes given row retrieved with Ajax.
                        while (attributes.test(aResponse[0])) {
                            aAttributes = attributes.exec(aResponse[0]);
                            $(newRow).attr(aAttributes[2], aAttributes[3]);
                            aResponse[0] = aResponse[0].replace(aAttributes[1], ''); // Remove attribute from row string so we can get the next attribute.
                        }
                        // Unset first element of aResponse (which is the TR).
                        aResponse.splice(0,1);
                        // Loop through TDs to copy their values.
                        for (i in aResponse) {
                            var aValue = values.exec(aResponse[i]);
                            $(newRow).children().get(i).innerHTML = aValue[1];
                        }
                        newRow.insertAfter($('#viewlistTable_' + sViewListID + ' tr:last'));
                        return true;
                    }
                }
            }
        }

        // Build GET query.
        var sGET = '';
        aInput = oForm.getElementsByTagName('input');
        for (var i in aInput) {
            if (!aInput[i].disabled && aInput[i].value) {
                sVal = aInput[i].value;
                if (aInput[i].name == 'page_size')
                    sVal = 1;
                if (aInput[i].name == 'page')
                    sVal = oForm['page_size'].value * oForm['page'].value;
                sGET = (sGET? sGET + '&' : '') + aInput[i].name + '=' + encodeURIComponent(sVal);
            }
        }
        sGET = (sGET? sGET + '&' : '') + 'only_rows=true';
        objHTTP.open('GET', '<?php echo lovd_getInstallURL() . 'ajax/viewlist.php?'; ?>' + sGET, true);
        objHTTP.send(null);
    }
}



function lovd_AJAX_viewListGoToPage (sViewListID, nPage) {
    oForm = document.forms['viewlistForm_' + sViewListID];
    oForm.page.value = nPage;
    lovd_AJAX_viewListSubmit(sViewListID);
}



function lovd_AJAX_viewListHideRow (sViewListID, sElementID)
{
    // FIXME; not actually an AJAX function.
    // FIXME; not really correct; the entire first part gets repeatedly called. It should only be the last part.
    oTable = document.getElementById('viewlistTable_' + sViewListID);
    oElement = document.getElementById(sElementID);
    // Get current height and set it in the style.
    nHeight = oElement.offsetHeight;
    oElement.style.height = nHeight + 'px';

    // Remove all text...
    aCells = oElement.cells;
    for (var i = aCells && aCells.length; i--;) {
        aCells[i].innerHTML = '';
    }

    // Done, now shrink the row.
    if (nHeight > 1) {
        nHeight = parseInt(parseInt(oElement.style.height)/1.8);
        oElement.style.height = nHeight + 'px';
        setTimeout('lovd_AJAX_viewListHideRow(\'' + sViewListID + '\', \'' + sElementID + '\');', 50);
    } else {
        // Really remove this row now.
        oTable.tBodies[0].removeChild(oElement);
    }
}



function lovd_AJAX_viewListSubmit (sViewListID) {
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
                        lovd_stretchInputs(sViewListID);
<?php
if (!isset($_GET['nohistory'])) {
?>
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
                        // lovd_AJAX_processViewListHash() itself will actually allow that when the back button is pressed, the correct page is loaded.
                        // The following makes sure this change we just made does not have reload lovd_AJAX_processViewListHash().
                        prevHash = window.location.hash;
<?php
}
?>
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



function lovd_AJAX_viewListUpdateEntriesString (sViewListID)
{
    // FIXME; not actually an AJAX function.
    // Updates the line above the table that says; "# entries on # pages". Showing entries # - ##."
    oForm = document.forms['viewlistForm_' + sViewListID];
    var nPages = Math.ceil(oForm.total.value / oForm.page_size.value);
    var nFirstEntry = ((oForm.page.value - 1) * oForm.page_size.value + 1);
    var nLastEntry = oForm.page.value * oForm.page_size.value;
    if (nLastEntry > oForm.total.value) {
        nLastEntry = oForm.total.value;
    }
    var sMessage = oForm.total.value + ' entr' + (oForm.total.value == 1? 'y' : 'ies') + ' on ' + nPages + ' page' + (nPages == 1? '' : 's') + '.';
    if (nFirstEntry == nLastEntry) {
        sMessage += ' Showing entry ' + nFirstEntry + '.';
    } else if (nFirstEntry <= oForm.total.value) {
        sMessage += ' Showing entries ' + nFirstEntry + ' - ' + nLastEntry + '.';
    }
    document.getElementById('viewlistPageSplitText_' + sViewListID).innerHTML = sMessage;
}



function lovd_stretchInputs (id)
{
    // Stretches the input fields for search terms on all columns, since the
    // column's size may be stretched because of the data contents.

    var aColumns = $("#viewlistTable_"+id+" th");
    var nColumns = aColumns.size();
    for (var i = 0; i < nColumns; i ++) {
        aColumns.eq(i).find("input").css("width", aColumns.eq(i).width() - 6);
    }
}
<?php
if (!isset($_GET['nohistory'])) {
?>



// This allows the interpretation of the URL hash part, such that pages can be linked to.
window.onload = function ()
{
    prevHash = '';
    lovd_AJAX_processViewListHash();
    setInterval(lovd_AJAX_processViewListHash, 250);
}
<?php
}
?>
