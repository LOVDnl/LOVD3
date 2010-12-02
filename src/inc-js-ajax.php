<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-02-01
 * Modified    : 2010-04-13
 * For LOVD    : 3.0-pre-06
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
function lovd_createHTTPRequest () {
    // Create HTTP request object.
    var objHTTP;
    try {
        // W3C standard.
        objHTTP = new XMLHttpRequest();
    } catch (e) {
        // Internet Explorer?
        try {
            objHTTP = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                objHTTP = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {
                // Ok, last try!
                try {
                    objHTTP = window.createRequest();
                } catch (e) {
                    // Never mind.
                    objHTTP = false;
                }
            }
        }
    }

    if (objHTTP) {
        return objHTTP;
    } else {
        return false;
    }
}

