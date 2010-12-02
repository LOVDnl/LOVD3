<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-01
 * Modified    : 2010-07-26
 * For LOVD    : 3.0-pre-08
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

function lovd_addNextRow ()
{
    // Load row for next page, because one row got deleted. So basically we're
    // asking for the row that we *expect* to be the first on the next page,
    // but we provide coordinates for the last row on this page.
    oForm = document.forms['viewlist_form'];

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
        oTable = document.getElementById('viewlist_table');

        objHTTP.onreadystatechange = function ()
        {
            if (objHTTP.readyState == 4) {
                if (objHTTP.status == 200) {
                    if (objHTTP.responseText.length > 100) {
                        // Successfully retrieved stuff.
                        // Now start an amazing detour.
                        oTFoot = oTable.createTFoot();                 // Create table footer.
                        oTFoot.style.display = 'none';                 // , but hide it!
                        // The following line doesn't work in IE 7. Don't know why. It says "Unknown runtime error". Other versions unknown.
                        oTFoot.innerHTML = objHTTP.responseText;       // Now, put the row in using innerHTML
                        oTable.tBodies[0].appendChild(oTFoot.rows[0]); // Then, when that's all parsed, append that to the table.
                        oTable.deleteTFoot();                          // Then remove the temporary table footer.
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



function lovd_AjaxDeleteLog (nID)
{
    // Create HTTP request object.
    var objHTTP = lovd_createHTTPRequest();
    objElement = document.getElementById(nID);
    objElement.style.cursor = 'progress';
    if (objHTTP) {
        objHTTP.onreadystatechange = function ()
        {
            if (objHTTP.readyState == 4) {
                objElement.style.cursor = '';
                if (objHTTP.status == 200) {
                    if (objHTTP.responseText == '1') {
                        // Object successfully deleted.
                        lovd_hideRow(nID);
                        document.forms['viewlist_form'].total.value --;
                        lovd_updateEntriesMessage();
// FIXME; disable for IE or try to fix?
                        // This one doesn't really work in IE 7. Other versions not known.
                        lovd_addNextRow();
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
        objHTTP.open('GET', '<?php echo lovd_getInstallURL() . 'ajax/delete_log.php?id='; ?>' + escape(nID), true);
        objHTTP.send(null);
    }
}



function lovd_hideRow (sElementID)
{
    // FIXME; not really correct; the entire first part gets repeatedly called. It should only be the last part.
    oTable = document.getElementById('viewlist_table');
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
        setTimeout('lovd_hideRow(\'' + sElementID + '\');', 50);
    } else {
        // Really remove this row now.
        oTable.tBodies[0].removeChild(oElement);
    }
}



function lovd_updateEntriesMessage ()
{
    // Updates the line above the table that says; "# entries on # pages". Showing entries # - ##."
    oForm = document.forms['viewlist_form'];
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
    document.getElementById('pagesplit_num').innerHTML = sMessage;
}
