<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-03-26
 * Modified    : 2020-06-03
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
function lovd_setWidth ()
{
    var line = $(this).parent().parent().next().children(':last').children(':first');
    // No minimum defined here, since sometimes you just want to remove what is there and type a new number.
    // This maximum is also defined in object_columns.php and object_shared_columns.php.
    if ($(this).val() > 500) {
        $(this).val(500);
        alert('The width cannot be more than 500 pixels!');
        return false;
    }
    $(line).attr('width', $(this).val());
    $(line).next().next().html('(This is ' + $(this).val() + ' pixels)');
    return false;
}





$(function ()
{
    $('input[name="width"]').change(lovd_setWidth);
    $('input[name="width"]').keyup(lovd_setWidth);
});
