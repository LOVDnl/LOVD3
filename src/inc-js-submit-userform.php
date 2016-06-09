<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-06-09
 * Modified    : 2016-06-09
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

header('Content-type: text/javascript; charset=UTF-8');
header('Expires: ' . date('r', time()+(180*60)));
?>
function lovd_checkForm ()
{
    // Check the user account creation/edit form.
    // The IP address allow list is a sensitive field that sometimes can lead to
    // problems when a value is filled in that does not match the user's IP
    // later in time. Not everybody understands that this is an optional field
    // that should normally be left empty.
    var oIPField = $("input[name='allowed_ip']");

    // Check if the field contains something else than just '*'.
    if (oIPField && oIPField.val() && oIPField.val() != '*') {
        // FIXME: Make a dialog? With 3 buttons? Yes, Review settings, Allow all?
        if (window.confirm('Are you sure you want to restrict access to your account using this IP address?\nYou can then not access your account from a different computer, or if your computer changes its address.')) {
            return true;
        } else {
            return false;
        }
    } else {
        return true;
    }
}
