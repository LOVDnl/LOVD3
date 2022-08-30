<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-12-03
 * Modified    : 2022-07-29
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';





if (!ACTION) {
    // URL: /scripts/check_HGVS.php
    header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . '?checkOne');
    exit;
}





if (PATH_COUNT == 2 && substr(ACTION, 0, 5) == 'check') {
    // URL: /scripts/check_HGVS.php?checkOne
    // URL: /scripts/check_HGVS.php?checkList

    define('METHOD', (substr(ACTION, -3) == 'One'? 'single' : 'list'));
    define('PAGE_TITLE', (METHOD == 'single'? 'Single variant' : 'Batch') . ' HGVS Check');
    define('LOG_EVENT', 'CheckHGVS');

    $_T->printHeader(false);
    $_T->printTitle();

    print('      To check your variant, please write it down in the input bar below.<BR><BR>');

    // Show form.
    print(
    '<FORM onsubmit="showResponse(); return false;" action="">
        <INPUT type="checkbox" id="callVV"/>
        <LABEL for="syntax">Besides checking the syntax of my variant' . (METHOD == 'single'? '' : 's') . ', please also run VariantValidator.</LABEL>
        <DIV><BR></DIV>' .
        (METHOD == 'single'?
        '<INPUT onchange="showResponse();" id="variant"/>' :
        '<TEXTAREA onchange="showResponse();" cols="30" rows="10" id="variant"></TEXTAREA>'
        ) . '
        <INPUT type="submit" id="checkButton" value="Check"/>
        <IMG src="gfx/trans.png" id="checkResult">
        <DIV><BR></DIV>
        <DIV id="response"></DIV>
    </FORM>'
    );

    // Call AJAX.
    print(
    '<SCRIPT>
        function showResponse() {
            $.get("ajax/check_HGVS.php?var=" + encodeURIComponent($("#variant").val()) + "&method=' . METHOD . '&callVV=" + $("#callVV").is(":checked"))
            .fail(function(){alert("Error checking variant, please try again later.");})
            ;
        }
        </SCRIPT>'
    );


    // Allow download.
    print(
    '<SCRIPT>
        function downloadResponse(){
            var fileContent = "data:text/tab-seperated-values;charset=utf-8,";

            for(var i=0; i<$("#responseTable tr").length; i++){
                row = $("#responseTable tr").eq(i);
                fileContent += encodeURI(row.children().eq(0).text()) + "\t" // variant
                             + encodeURI(row.children().eq(1).children().prop("alt")) + "\t" // isHGVS
                             + encodeURI(row.children().eq(2).text()) + "\t" // fixedVariant
                             + encodeURI(row.children().eq(3).text())        // warnings and errors
                             + (!$("#callVV").is(":checked")? "" :           // result of VariantValidator
                                 "\t" + encodeURI(row.children().eq(4).text()))
                             + "\r\n";                
            }

            var link = document.createElement("a");
            link.setAttribute("href", fileContent);
            link.setAttribute("download", "LOVD_HGVSCheck ' . date("Y-m-d H.i.s") . '.txt");
            document.body.appendChild(link);

            link.click();
        }
        </SCRIPT>'
    );

    exit;
}

?>