<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-06-25
 * Modified    : 2012-03-14
 * For LOVD    : 3.0-beta-03
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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

header('Content-type: text/javascript; charset=UTF-8');
?>

// Firstly, create the new tooltop DIV.
var oTT = window.document.createElement('div');
oTT.setAttribute('id', 'tooltip');
oTT.className = 'tooltip';
oTT.style.display = 'none'; // To prevent whitespace at the end of the page.
window.document.body.appendChild(oTT);

// Icon to close the tooltip.
var imgHide = window.document.createElement('img');
// In principle, this class name is not necessary. If we're not going to use
// more images in the tooltips, we could just adapt the stylesheet to align all
// images like this one.
imgHide.className = 'tooltip-hide';
imgHide.setAttribute('src', 'gfx/mark_0.png');
imgHide.setAttribute('onclick', 'lovd_hideToolTip(this); return false;');

var timer;
var timer_is_on = 0;



function lovd_showToolTip (sText, handle)
{
    if (typeof(handle) == 'undefined') {
        // No element given, tooltip linked to the mouse, like on data entry forms.
        var oEvent = window.windowevent;
        if (!oEvent) {
            // IE
            // These vars on oEvent would actually also work on FF.
            var x = event.clientX + document.documentElement.scrollLeft;
            var y = event.clientY + document.documentElement.scrollTop;
        } else {
            var x = oEvent.pageX;
            var y = oEvent.pageY;
        }

        x = eval(x + 20); // Move it a little bit to the right.
        oTT.style.left = x + 'px';
        oTT.style.top = y + 'px';
        oTT.innerHTML = sText;
        oTT.style.display = 'block';

    } else {
        // Link tooltip to element.
        var aPosition = lovd_getPosition(handle);
        oTT.style.left = aPosition[0]+'px';
        oTT.style.top = aPosition[1]+13+'px'; // FIXME; can height of element be used here?
        oTT.style.display = 'block';
        oTT.innerHTML = sText;
        oTT.firstChild.innerHTML = $(oTT).children(0).attr('href'); // oTT.firstChild.href; // Put the href URL in the visible part of the link.
        oTT.appendChild(imgHide); // Hide icon gets lost when setting innerHTML, re-add it.
        oTT.style.width = 'auto'; // Adapt size of tooltip to contents.
        oTT.style.paddingRight = '20px'; // But leave some space for the image.

        if (timer_is_on) {
            clearTimeout(timer);
            timer_is_on = 0;
        }

        oTT.onmouseover = function () {
            handle.isMouseOver = true;
            if (timer_is_on) {
                clearTimeout(timer);
                timer_is_on = 0;
            }
        }

        handle.onmouseout = function () {
            timer = setTimeout('lovd_hideToolTip()', 100);
            timer_is_on = 1;
        }

        oTT.onmouseout = function () {
            handle.isMouseOver = false;
            timer = setTimeout('lovd_hideToolTip()', 100);
            timer_is_on = 1;
        }
    }
}



function lovd_getPosition (oElement)
{
    var aReturnArray = new Array(0, 0);
    while (oElement != null) {
        aReturnArray[0] += oElement.offsetLeft;
        aReturnArray[1] += oElement.offsetTop;
        oElement = oElement.offsetParent;
    }
    return aReturnArray;
}



function lovd_hideToolTip () {
    clearTimeout(timer);
    timer_is_on = 0;
    oTT.style.display = 'none';
}



function recordEvent (oEvent) {
    window.windowevent = oEvent;
}



window.document.onmousemove = recordEvent;