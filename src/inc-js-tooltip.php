<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-06-25
 * Modified    : 2010-06-25
 * For LOVD    : 3.0-pre-07
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

header('Content-type: text/javascript; charset=UTF-8');
?>

// Firstly, create the new tooltop DIV.
var oTT = window.document.createElement('div');
oTT.setAttribute('id', 'tooltip');
oTT.className = 'tooltip';
window.document.body.appendChild(oTT);

function lovd_showToolTip (sText) {
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
    var oTT = document.getElementById('tooltip');
    
    x = eval(x + 20); // Move it a little bit to the right.
    oTT.style.left = x + 'px';
    oTT.style.top = y + 'px';
    oTT.innerHTML = sText;
    oTT.style.visibility = 'visible';
}

function lovd_hideToolTip () {
    var oTT = document.getElementById("tooltip");
    oTT.style.visibility = 'hidden';
}

function recordEvent (oEvent) {
    window.windowevent = oEvent;
}

window.document.onmousemove = recordEvent;
