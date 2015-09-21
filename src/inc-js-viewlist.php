<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-29
 * Modified    : 2015-09-21
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
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
header('Expires: ' . date('r', time()+(180*60)));
require ROOT_PATH . 'inc-lib-init.php';
require ROOT_PATH . 'inc-js-ajax.php';

// Find out whether or not we're using SSL.
if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('PROTOCOL', 'https://');
} else {
    define('PROTOCOL', 'http://');
}

if (!isset($_GET['nohistory'])) {
?>


function lovd_AJAX_processViewListHash ()
{
    // Get the hash, explode it, interpret it. Fill in the hash values in the form's fields and resubmit.
    // If this function notices we don't have a hash *anymore*, it realized we pushed back and it will parse the GET query string to return to the original viewlist.
    // When we do this, we must make SURE we don't put a hash anymore, otherwise we're going forward in the browser history again and the user will never be able to get back beyond this VL.

    if (window.location.hash != prevHash && prevHash != 'no_rehash') {
        // In case multiple viewList's exist, we choose the first one. In practice, hashing is turned off on pages with multiple viewLists.
        $("form").each(function(){
            if ($(this).attr('id') && $(this).attr('id').substring(0, 13) == 'viewlistForm_') {
                oForm = this;
                sViewListID = $(this).attr('id').substring(13);
                return false; // Break the loop.
            }
            return true;
        });

        // Based on the function by Andy E at http://stackoverflow.com/questions/901115/how-can-i-get-query-string-values
        var Hash = {},
            match,
            search = /([^&=]+)=?([^&]*)/g;

        while (match = search.exec(window.location.hash.substring(1))) {
            Hash[match[1]] = decodeURIComponent(match[2]);
            // Search fields.
            if ((match[1].substr(0,7) == 'search_' || match[1].substr(0,4) == 'page' || match[1] == 'order') && oForm[match[1]]) {
                // Fill in values in search boxes.
                oForm[match[1]].value = Hash[match[1]];
            }
        }

        // Values which are NO LONGER in the Hash (added search term, then back button) need to be removed!!!
        $(oForm).find('input[name^="search_"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});
        $(oForm).find('input[name^="page"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});
        $(oForm).find('input[name="order"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});

        if (!window.location.hash) {
            // We don't have a hash anymore. This means we went back to the original viewlist. We must reload it, WITHOUT
            // having lovd_AJAX_viewListSubmit() put a new hash, otherwise we can never navigate back beyond this VL.
            var GET = {};
            while (match = search.exec(window.location.search.substring(1))) {
                GET[match[1]] = decodeURIComponent(match[2]);
                if ((match[1].substr(0,7) == 'search_' || match[1].substr(0,4) == 'page' || match[1] == 'order') && oForm[match[1]]) {
                    // Fill in values in search boxes.
                    oForm[match[1]].value = GET[match[1]];
                }
            }
        }

        lovd_AJAX_viewListSubmit(sViewListID);
        if (window.location.hash) {
            prevHash = window.location.hash;
        } else {
            // We must send a signal to lovd_AJAX_viewListSubmit() to NOT load a new hash.
            prevHash = 'no_rehash';
        }
    }
}
<?php
}
?>


// List for recording checkbox changes in viewLists.
var check_list = [];

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
        $(oForm).find('input').each(function(){
            if (!this.disabled && this.value) {
                sVal = this.value;
                if (this.name == 'page_size')
                    sVal = 1;
                if (this.name == 'page')
                    sVal = oForm['page_size'].value * oForm['page'].value;
                sGET += (sGET? '&' : '') + this.name + '=' + encodeURIComponent(sVal);
            }
        });
        sGET += (sGET? '&' : '') + 'only_rows=true';
        objHTTP.open('GET', '<?php echo lovd_getInstallURL() . 'ajax/viewlist.php?'; ?>' + sGET, true);
        objHTTP.send(null);
    }
}



function lovd_AJAX_viewListGoToPage (sViewListID, nPage) {
    oForm = document.forms['viewlistForm_' + sViewListID];
    oForm.page.value = nPage;
    lovd_AJAX_viewListSubmit(sViewListID);
}



function lovd_AJAX_viewListDownload (sViewListID, bAll)
{
    // Triggers a download of the given viewList.
    // We'll have to use a hidden IFrame for it.
    var oIFrame = document.createElement('iframe');
    oIFrame.style.display = 'none';

    // Build URL.
    var sURL = 'ajax/viewlist.php?download' + (bAll? '' : 'Selected') + '&format=text/plain';
    oForm = document.forms['viewlistForm_' + sViewListID];
    $(oForm).find('input').each(function(){
        // We actually don't need everything, but it's too difficult to manually add viewListID, object, order and skip.
        if (!this.disabled && this.value && this.name.substring(0,6) != 'check_') {
            sURL +=  '&' + this.name + '=' + encodeURIComponent(this.value);
        }
    });
    //oIFrame.src = 'ajax/viewlist.php?viewlistid=' + sViewListID + '&object=' + $('#viewlistForm_' + sViewListID + ' :input[name="object"]').val() + '&download';
    oIFrame.src = sURL;
    $('#viewlistDiv_' + sViewListID).append(oIFrame);
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



function lovd_AJAX_viewListSubmit (sViewListID, callBack) {
    oForm = document.forms['viewlistForm_' + sViewListID];
    // Used to have a simple loop through oForm, but Google Chrome does not like that.
    $(oForm).find('input').each(function(){
        if (this.name && this.name.substring(0, 7) == 'search_' && !this.value) {
            this.disabled = true;
        }
    });

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
                        lovd_activateMenu(sViewListID);
<?php
if (!isset($_GET['nohistory'])) {
?>
                        var sHash = '';
                        if (prevHash != 'no_rehash') {
                            // The following adds the page to the history in Firefox, such that the user *can* push the back button.
                            // I chose not to use sGET (created somewhere below) here, because it contains 'viewlistid' and 'object' which I don't want to use now and I guess it would be possible that it won't be set.
                            $(oForm).find('input').each(function(){
                                if (!this.disabled && this.value && this.name != 'viewlistid' && this.name != 'object' && this.name.substring(0,6) != 'check_') {
                                    sHash += (sHash? '&' : '') + this.name + '=' + encodeURIComponent(this.value);
                                }
                            });
                            window.location.hash = sHash;
                        }
                        // lovd_AJAX_processViewListHash() itself will actually allow that when the back button is pressed, the correct page is loaded.
                        // The following makes sure this change we just made does not have to reload lovd_AJAX_processViewListHash().
                        prevHash = window.location.hash;
<?php
}
?>
                        if (typeof callBack != 'undefined') {
                            callBack();
                        }
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
        $(oForm).find('input').each(function(){
            if (!this.disabled && this.value && this.name.substring(0,6) != 'check_') {
                sGET += (sGET? '&' : '') + this.name + '=' + encodeURIComponent(this.value);
            }
        });
        // Gather changed checkbox IDs and send, too.
        if (check_list[sViewListID] && check_list[sViewListID].length) {
            if ($.isArray(check_list[sViewListID])) {
                var sIDlist = check_list[sViewListID].join(';');
            } else {
                var sIDlist = check_list[sViewListID];
            }
            sGET += (sGET? '&' : '') + 'ids_changed=' + sIDlist;
            check_list[sViewListID] = [];
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



function lovd_showLegend (sViewListID)
{
    // Opens a jQuery Dialog containing a ViewList's full legend.

    $("#viewlistLegend_" + sViewListID).dialog({draggable:false,resizable:false,minWidth:800,modal:true,show:"fade",closeOnEscape:true});
}



function lovd_stretchInputs (id)
{
    // Stretches the input fields for search terms on all columns, since the
    // column's size may be stretched because of the data contents.

    var aColumns = $("#viewlistTable_"+id+" th");
    var nColumns = aColumns.size();
    for (var i = 0; i < nColumns; i ++) {
        if (aColumns.eq(i).width()) {
            // But only if the column actually has a width (= 0 if table is hidden for now)
            aColumns.eq(i).find("input").css("width", aColumns.eq(i).width() - 6);
        }
    }
}



function cancelParentEvent (event)
{
    // Cancels the event from the parent element.
    if ('bubbles' in event) {
        // All browsers except IE before version 9.
        if (event.bubbles) {
            event.stopPropagation();
        }
    } else {
        // Internet Explorer before version 9
        event.cancelBubble = true;
    }
}



function lovd_recordCheckChanges (element, sViewListID)
{
    // Record click events on the checkboxes in the viewlist in an array and so that
    // lovd_AJAX_viewListSubmit() can send it through GET to Objects::viewList().
    var sID = element.id.substring(6);
    var nIndex = $.inArray(sID, check_list[sViewListID]);

    if (nIndex != -1) {
        // If the checked checkbox is already in the check_list array, remove it!
        check_list[sViewListID].splice(nIndex,1);
        return true;
    } else {
        // If the checked checkbox is not yet in the check_list array, add it!
        check_list[sViewListID].push(sID);
        return true;
    }

    return false;
}



function lovd_activateMenu (sViewListID)
{
    // Activates the jeegoocontext menu for this viewList, if enabled.
    if ($('#viewlistOptionsButton_' + sViewListID).attr('id') != undefined) {
        // Options menu requested.
        $(function() {
            var aMenuOptions = {
                event: "click",
                openBelowContext: true,
                autoHide: true,
                delay: 1000,
                onSelect: function(e, context) {
                    // e.stopPropagation(); // Doesn't do anything... :(
                    if ($(this).hasClass("disabled")) {
                        return false;
                    } else if ($(this).find('a').attr('href') != undefined) {
                        window.location = $(this).find('a').attr('href');
                        return true; // True closes the menu.
                    } else if ($(this).find('a').attr('click') != undefined) {
                        eval($(this).find('a').attr('click'));
                        return true; // True closes the menu.
                    } else {
                        return false;
                    }
                }
            };
            // Because amount may have changed, reset "Select all" link.
            var nTotal = $('#viewlistForm_' + sViewListID + ' input[name="total"]').eq(0).val();
            $('#viewlistMenu_' + sViewListID + ' li').eq(0).find('span').eq(1).html(nTotal + ' entr' + (nTotal != 1? 'ies' : 'y'));
            // Add menu to options icon.
            $('#viewlistOptionsButton_' + sViewListID).jeegoocontext('viewlistMenu_' + sViewListID, aMenuOptions);
        });
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
