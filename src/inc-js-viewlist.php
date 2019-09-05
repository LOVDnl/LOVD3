<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-29
 * Modified    : 2019-08-28
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
        // Only for visible search fields (hidden search inputs are set by server).
        $(oForm).find('input[name^="search_"][type!="hidden"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});
        $(oForm).find('input[name^="page"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});
        $(oForm).find('input[name="order"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});
        $(oForm).find('input[name="MVSCols"]').each(function (i, o) { if (o.value && !Hash[o.name]) { o.value = ""; }});

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

// State object for Find & Replace, holding per viewlist information on what
// phase of the F&R process we are (sPhase = 'none' | 'column_selection' |
// 'input' | 'preview'), whether the F&R options form and buttons should
// be shown (i.e. 'bShowMenu', 'bShowPreview' and 'bShowSubmit') and other
// related information (e.g. 'sFRRowsAffected' is the number of rows that
// will be affected by F&R)
var FRState = {};


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
                        // Fixme: check if code below can be replaced by just appending the response to the table.

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



function lovd_AJAX_viewListGoToPage (sViewListID, nPage)
{
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



function lovd_AJAX_viewListSubmit (sViewListID, callBack)
{
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
                        lovd_FRShowOptionsMenu(sViewListID);
<?php
if (!isset($_GET['nohistory'])) {
?>
                        var sHash = '';
                        if (prevHash != 'no_rehash') {
                            // The following adds the page to the history in Firefox, such that the user *can* push the back button.
                            // I chose not to use sGET (created somewhere below) here, because it contains 'viewlistid' and 'object' which I don't want to use now and I guess it would be possible that it won't be set.
                            $(oForm).find('input[type!="button"]').each(function(){
                                if (!this.disabled && this.value && this.name != 'viewlistid' &&
                                    this.name != 'object' && this.name.substring(0,6) != 'check_' &&
                                    this.name.substring(0,2) != 'FR') {
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

        // Put values into a GET param string for all input fields, except fields named check_*
        // and non-checked radio buttons and checkboxes.
        $(oForm).find('input').each(function(){
            if (!this.disabled && this.value && this.name.substring(0,6) != 'check_' &&
                (this.type != 'radio' || this.checked) &&
                (this.type != 'checkbox' || this.checked) &&
                this.type != 'password' && this.name) {
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

        // Append potential find & replace preview action to GET parameters.
        if (FRState.hasOwnProperty(sViewListID) && FRState[sViewListID]['phase'] == 'preview') {
            sGET += (sGET? '&' : '') + 'FRPreviewClicked_' + sViewListID + '=1';
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
                delay: 100,
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





function onNextDocumentClick (callback, eventName)
{
    if (typeof eventName == 'undefined') {
        // Create a random name if none is specified.
        eventName = 'click.' + Math.random().toString(36).substr(2, 5);
    }

    // Call callback function on next click anywhere on the page.
    $(document).on(eventName, function() {
        // Remove event binding.
        $(document).off(eventName);
        callback();
    });
}






function lovd_getFROptionsElement (sViewListID)
{
    // Display find & replace options menu, set its options and return it as a
    // jQuery object.

    if (!FRState.hasOwnProperty(sViewListID)) {
        FRState[sViewListID] = {};
    }

    // Bind actions to cancel, preview and submit buttons.
    var FRoptions = $('#viewlistFRFormContainer_' + sViewListID);
    FRoptions.find('#FRCancel_' + sViewListID).on('click', function () {
        lovd_FRCleanup(sViewListID);
    });

    FRoptions.find('#FRPreview_' + sViewListID).on('click', function () {
        lovd_FRPreview(sViewListID);
    });

    FRoptions.find('#FRSubmit_' + sViewListID).on('click', function () {
        lovd_FRSubmit(sViewListID);
    });

    // Hide/show buttons based on options.
    if (typeof FRState[sViewListID].bShowSubmit == 'undefined' || !FRState[sViewListID].bShowSubmit) {
        FRoptions.find('#FRSubmitDiv_' + sViewListID).hide();
    } else {
        FRoptions.find('#FRSubmitDiv_' + sViewListID).show();
    }
    if (typeof FRState[sViewListID].bShowPreview == 'undefined' || !FRState[sViewListID].bShowPreview) {
        FRoptions.find('#FRPreview_' + sViewListID).hide();
    } else {
        FRoptions.find('#FRPreview_' + sViewListID).show();
    }

    // Set selected column name (and display name) in menu text.
    if (FRState[sViewListID].hasOwnProperty('sDisplayname')) {
        FRoptions.find('#viewlistFRColDisplay_' + sViewListID).html(FRState[sViewListID]['sDisplayname']);
        FRoptions.find('#FRFieldDisplayname_' + sViewListID).val(FRState[sViewListID]['sDisplayname']);
    }
    if (FRState[sViewListID].hasOwnProperty('sFieldname')) {
        FRoptions.find('#FRFieldname_' + sViewListID).val(FRState[sViewListID]['sFieldname']);
    }

    // Set the option menu width equal to the viewlist's width
    var sVLWidth = $('#viewlistTable_' + sViewListID).outerWidth();
    FRoptions.outerWidth(sVLWidth);

    return FRoptions;
}




function lovd_ShowOverlayColumn (index, bSelectable, targetTH, sOverlayClassname, tableHeight,
                                   sViewListID, sViewListDivSelector, sTooltip, callback)
{
    // Show an overlay element for the viewlist column denoted by targetTH.
    // The overlay element is given class sOverlayClassname and has a height
    // equal to tableHeight. index is the number of the current column in the
    // viewlist.

    // Place DIVs overlaying table columns to get column selection.
    var overlayDiv = $().add('<DIV class="' + sOverlayClassname + '"></DIV>');
    var ePos = $(targetTH).offset();
    // var bAllowFindAndReplace = $(targetTH).data('allow_find_replace') == '1';

    // Show 'not-allowed' cursor type for non-custom columns.
    var overlayCursor = 'not-allowed';
    if (bSelectable) {
        overlayCursor = 'pointer';
    }

    // Position div over current column.
    overlayDiv.css({
        position: 'absolute',
        top: ePos.top,
        left: ePos.left,
        height: tableHeight,
        width: $(targetTH).outerWidth() + 1,
        cursor: overlayCursor
    });

    // Only make custom columns selectable.
    if (bSelectable) {
        var oCurrentOptions = {
            sFieldname: $(targetTH).data('fieldname'),
            sDisplayname: $(targetTH).text().trim(),
            phase: 'input',
            bShowPreview: true,
            bShowSubmit: false
        };
        overlayDiv.on('click', function (clickEvent) {
            // Make sure the click event is propagated now and not after this
            // function is finished.
            clickEvent.stopPropagation();
            $(this).parent().click();
            $('.' + sOverlayClassname).remove();

            // Open F&R options menu (including tooltip, which closes on next
            // click event).
            // lovd_FRShowOptionsMenu(sViewListID, oCurrentOptions);
            callback(sViewListID, oCurrentOptions);
        });
    } else {
        overlayDiv.on('click', function () {
            alert('This column is not available.');
        });
    }

    $(sViewListDivSelector).append(overlayDiv);

    if (index == 0) {
        // Show tooltip near first column.
        $(targetTH).tooltip({
            items: targetTH,
            content: sTooltip,
            disabled: true, // don't show tooltip on mouseover
            position: {
                my: 'left bottom',
                at: 'right top-20',
                using: function(position, feedback) {
                    $(this).css(position);
                    $('<DIV>')
                        .addClass('arrow')
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);
                    $(this).removeClass('ui-widget-content');
                }
            }
        }).tooltip('open');
        onNextDocumentClick(function() {
            $(targetTH).tooltip('close');
        });
    }
}




function lovd_columnSelector (sViewListID, colClickCallback, sTooltip, sDataAttribute)
{
    // Show a find & replace column selector for the given viewlist.
    // Params:
    // sDataAttribute   Determine whether column is selectable based on "data-"
    //                  atribute in column heading (TH element). If undefined
    //                  empty string, all columns are selectable.

    if (typeof sDataAttribute == "undefined") {
        sDataAttribute = '';
    }

    if (!FRState.hasOwnProperty(sViewListID)) {
        FRState[sViewListID] = {};
    }
    FRState[sViewListID]['phase'] = 'column_selection';

    var sViewListDivSelector = '#viewlistDiv_' + sViewListID;
    if ($(sViewListDivSelector).length == 0) {
        // No viewlist with ID sViewListID found
        return;
    }

    // Get viewlist table element and its height.
    var sVLTableSelector = '#viewlistTable_' + sViewListID;
    var tableHeight = $(sVLTableSelector).css('height');

    // Overlay each column in the viewlist with a clickable element to select
    // it.
    var sOverlayClassname = 'vl_overlay';
    $(sVLTableSelector).find('th').each(function (index) {
        // Decide whether current column is selectable based on presence of
        // certain data attribute.
        bSelectable = $(this).is('[data-fieldname]'); // Disable non-content cols like checkbox
        bSelectable &= (sDataAttribute == '' || $(this).data(sDataAttribute) == '1');
        lovd_ShowOverlayColumn(index, bSelectable, this, sOverlayClassname, tableHeight,
                               sViewListID, sViewListDivSelector, sTooltip, colClickCallback);
    });

    // Capture clicks outside the column overlays to cancel the F&R action.
    onNextDocumentClick(function () {
        $('.' + sOverlayClassname).remove();
    });
}



function lovd_FRShowOptionsMenu (sViewListID, oNewOptions)
{
    // Display the options menu for column-wise find & replace in the given
    // viewlist.
    if (!FRState.hasOwnProperty(sViewListID)) {
        FRState[sViewListID] = {'phase': 'none'};
    }
    if (typeof oNewOptions != 'undefined') {
        $.extend(FRState[sViewListID], oNewOptions);
    }

    if (FRState[sViewListID]['phase'] == 'preview' && FRState[sViewListID]['bShowSubmit']) {
        // When previewing changes, only show submit button when there are no
        // errors displayed.
        FRState[sViewListID]['bShowSubmit'] = $('div.err').length == 0;
    }

    if (FRState[sViewListID]['phase'] == 'input' || FRState[sViewListID]['phase'] == 'preview') {
        var FROptions = lovd_getFROptionsElement(sViewListID);
        FROptions.show();

        // Jquery-ui tooltip keeps jumping just after it is displayed. Attempts to
        // avoid this (show: false, hide:false, collision: 'none') have failed. As
        // it is quite annoying, I have disabled it for now.
//        if (FRState[sViewListID]['phase'] == 'input') {
//            // Display a tooltip for the options menu.
//            FROptions.tooltip({
//                items: FROptions,
//                content: 'Specify find & replace options',
//                disabled: true, // Do not show tooltip on mouseover.
//                show: false,
//                hide: false,
//                position: {
//                    my: 'left bottom',
//                    at: 'left top+40',
//                    collision: 'none',
//                    using: function (position, feedback) {
//                        $(this).css(position);
//                        $('<DIV>')
//                            .addClass('arrow')
//                            .addClass(feedback.vertical)
//                            .addClass(feedback.horizontal)
//                            .appendTo(this);
//                        $(this).removeClass('ui-widget-content');
//                    }
//                }
//            }).tooltip('open');
//            onNextDocumentClick(function() {
//                FROptions.tooltip('close');
//            });
//        }
    }
}



function lovd_FRPreview (sViewListID)
{
    // Show a preview for find & replace for the given viewlist ID and options.

    if (!FRState.hasOwnProperty(sViewListID)) {
        FRState[sViewListID] = {};
    }
    FRState[sViewListID]['phase'] = 'preview';
    FRState[sViewListID]['bShowSubmit'] = true;

    // Submit the current viewlist with find & replace options.
    lovd_AJAX_viewListSubmit(sViewListID, function() {
        // Get the predicted number of affected rows from the retrieved HTML.
        var sFRRowsAffected = $('#FRRowsAffected_' + sViewListID).val();

        // Show tooltip above column with changes about to be applied.
        var FRPreviewHeader = $('th[data-fieldname="' + FRState[sViewListID]['sFieldname'] +
                                '_FR' + '"] > img');
        FRPreviewHeader.tooltip({
            items: 'th',
            content: 'Preview changes (' + sFRRowsAffected + ' rows affected)',
            disabled: true, // Don't show tooltip on mouseover.
            position: {
                my: 'center bottom',
                at: 'center top-15',
                using: function(position, feedback) {
                    $(this).css(position);
                    $('<DIV>')
                        .addClass('arrow')
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);
                    $(this).removeClass('ui-widget-content');
                }
            }
        }).tooltip('open');
        onNextDocumentClick(function() {
            FRPreviewHeader.tooltip('close');
            // Calling 'close' in this case does not remove tooltip from page,
            // therefore we destroy the tooltip too, removing it from the DOM.
            FRPreviewHeader.tooltip('destroy');
        });
    });
}




function lovd_FRShowConfirmation (sViewListID, sDisplayname, sFRRowsAffected)
{
    if (!FRState.hasOwnProperty(sViewListID)) {
        FRState[sViewListID] = {};
    }

    var FRoptions = $('#viewlistFRFormContainer_' + sViewListID);
    FRoptions.before(
        '<table border="0" cellpadding="2" cellspacing="0" width="100%" class="info" style="margin: 10px 0px;">' +
            '<tbody><tr>' +
                '<td valign="top" align="center" width="40">' +
                    '<img src="gfx/lovd_information.png" alt="Information" title="Information"' +
                          ' width="32" height="32" style="margin : 4px;">' +
                '</td><td valign="middle">' +
                    'Find & Replace applied to column "' + sDisplayname + '" for ' +
                    sFRRowsAffected + ' records.' +
        '</td></tr></tbody></table>');
}



function lovd_FRCleanup (sViewListID, bSubmitVL, afterSubmitCallback)
{
    // Cleanup HTML from find & replace (form values + preview viewlist column).

    // Clear F&R state.
    FRState[sViewListID] = {phase: 'none'};

    // Clear the find & replace options form.
    var FRoptions = $('#viewlistFRFormContainer_' + sViewListID);
    FRoptions.find('input[type=text]').val('');
    FRoptions.find('input[type=checkbox]').prop('checked', false);
    var radioButtons = FRoptions.find('input[type=radio]');
    radioButtons.prop('checked', false);
    // Check the first radio button (as default value)
    radioButtons.first().prop('checked', true);

    // Hide F&R options form.
    $(FRoptions).hide();

    if (bSubmitVL || typeof bSubmitVL == 'undefined') {
        // Reload the viewlist to remove a potential preview column.
        lovd_AJAX_viewListSubmit(sViewListID, afterSubmitCallback);
    }

    // Hide all tooltips.
    $('div[role="tooltip"]').remove();
}



function lovd_FRSubmit (sViewListID)
{
    // Check if password field is empty.
    var sViewlistFormSelector = '#viewlistForm_' + sViewListID;
    if ($(sViewlistFormSelector).find(':password').val() == '') {
        alert('Please fill in your password to authorize.');
        return false;
    }

    var sFRRowsAffected = $('#FRRowsAffected_' + sViewListID).val();
    if (!window.confirm('You are about to modify ' + sFRRowsAffected +
                        ' records. Do you wish to continue?')) {
        return false;
    }

    // Submit a find & replace action for the given viewlist.
    var postResponse = $.post('<?php echo lovd_getInstallURL() . 'ajax/viewlist.php?applyFR'; ?>',
                              $(sViewlistFormSelector).serialize(), null, 'text');

    var sDisplayname = '';
    if (FRState.hasOwnProperty(sViewListID)) {
        sDisplayname = FRState[sViewListID]['sDisplayname'];
    }

    postResponse.done(function(sData) {
        // Fixme: consider requiring inc-init.php to use AJAX_* constants for checking response.
        if (sData === '1') {
            // Clean up F&R settings menu.
            lovd_FRCleanup(sViewListID, true, function() {
                // Show confirmation after cleanup.
                lovd_FRShowConfirmation(sViewListID, sDisplayname, sFRRowsAffected);
            });
        } else if (sData === '8') {
            alert('You do not have authorization to perform this action. Did you enter your ' +
                  'password correctly?');
        } else {
            alert('Something went wrong while applying find and replace.');
        }
    })
    .fail(function() {
        alert('Something went wrong while applying find and replace.');
    })
    .always(function() {
        // clear password field.
        $(sViewlistFormSelector).find(':password').val('');
    });
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



function lovd_passAndRemoveViewListRow (sViewListID, sRowID, aRowData, callback)
{
    // Select item at row (sRowID) of ViewList (sViewListID), pass its data (as
    // received) to the callback function (callback). The selected item will be
    // removed from the ViewList and the ViewList will be updated (extending the
    // view to the original number of rows and making sure the deleted item will
    // not re-occur).
    // FIXME: This function is using search_id which is usually shown, so that's a pretty bad idea. It should have been
    //  made configurable. Will not fix this now, will just override it to search_userid, as it's only used for users.

    var oViewListForm = $('#viewlistForm_' + sViewListID).get(0);

    // Change the search terms in the ViewList such that submitting it will not reshow this item.
    oViewListForm.search_userid.value += ' !' + sRowID;
    // Does an ltrim, too. But trim() doesn't work in IE < 9.
    oViewListForm.search_userid.value = oViewListForm.search_userid.value.replace(/^\s*/, '');

    lovd_AJAX_viewListHideRow(sViewListID, sRowID);
    oViewListForm.total.value --;
    lovd_AJAX_viewListUpdateEntriesString(sViewListID);
    lovd_AJAX_viewListAddNextRow(sViewListID);

    // Function call to callback with the ViewList row as argument.
    callback(aRowData);
}





function lovd_toggleMVSCol (sViewListID, oColumn)
{
    // Add or remove (depending on its presence) multivalued search argument
    // oColumn to a semicolon-separated list stored in form field.
    var sMVSCol = oColumn.sFieldname;
    var oMVSInput = $('#viewlistForm_' + sViewListID + ' input[name="MVSCols"]');
    var aCurrentCols = [];
    if (oMVSInput.val().length > 0) {
        aCurrentCols = oMVSInput.val().split(';');
    }

    if ($.inArray(sMVSCol, aCurrentCols) >= 0) {
        aCurrentCols.splice(aCurrentCols.indexOf(sMVSCol), 1);
    } else {
        aCurrentCols.push(sMVSCol);
    }
    oMVSInput.val(aCurrentCols.join(';'));
    lovd_AJAX_viewListSubmit(sViewListID);
}





$(document).ready(function() {
    // Bind mouse clicking events to viewlist rows to let clicking of middle-
    // mousebutton open URLs in new tab/window. Open urls specified in a
    // 'data-url' attribute if it's available.
    $('table .data').on('mouseup', 'tr', function(e) {
        if ($(this).data('href') && e.which == 2) {
            // Middle mouse button clicked, open url in new window.
            window.open($(this).data('href'), '_blank');
        }
    });
});
