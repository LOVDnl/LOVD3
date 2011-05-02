<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-04-19
 * Modified    : 2011-04-29
 * For LOVD    : 3.0-pre-20
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

header('Content-type: text/javascript; charset=UTF-8');
?>

function lovd_insertCustomLink (element, sLink) {
    var currentRow = element.parentNode.parentNode.parentNode;
    var field = currentRow.parentNode.rows[currentRow.rowIndex - 1].lastChild.childNodes[0];
    if (field.setSelectionRange){
        field.value = field.value.substring(0,field.selectionStart) + sLink + field.value.substring(field.selectionStart,field.selectionEnd) + field.value.substring(field.selectionEnd,field.value.length);
    }
    else if (document.selection && document.selection.createRange) {
        field.focus();
        var range = document.selection.createRange();
        range.text = sLink + range.text;
    }
}
