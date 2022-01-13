<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-12-03
 * Modified    : 2021-12-09
 * For LOVD    : 3.5-pre-02
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

const ROOT_PATH = './';
const TAB_SELECTED = 'setup';
require ROOT_PATH . 'inc-init.php';
require_once(ROOT_PATH . 'inc-lib-init.php');

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 5 && !ACTION) {
    // URL: /checkHGVS
    // Main page of the checker.

    define('PAGE_TITLE', 'HGVS Checker');

    $_T->printHeader(false);
    $_T->printTitle();

    echo 'Back to main...';

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && substr(ACTION, 0, 5) ==  'check') {
    // URL: /checkHGVS?checkOne or /checkHGVS?checkList

    define('METHOD', (substr(ACTION, -3) == 'One'? 'single' : 'list'));
    define('PAGE_TITLE', (METHOD == 'single'? 'Single variant' : 'Batch') . ' HGVS Check');
    define('LOG_EVENT', 'CheckHGVS');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-variants.php';

    $_T->printHeader(false);
    $_T->printTitle();

    print('      To check your variant, please write it down in the input bar below.<BR><BR>');

    // Show form.
    print(
    '<FORM onsubmit="showResponse(); return false;" action="">
        <INPUT type="checkbox" id="nameCheck"/>
        <LABEL for="syntax">Besides checking the syntax, please also check the contents of my variant' . (METHOD == 'single'? '' : 's') . '.</LABEL>
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
            $.get("ajax/checkHGVS.php?var=" + encodeURIComponent($("#variant").val()) + "&method=' . METHOD . '&nameCheck=" + $("#nameCheck").is(":checked"))
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
                             + encodeURI(row.children().eq(1).text()) + "\t" // isHGVS
                             + encodeURI(row.children().eq(2).text()) + "\t" // fixedVariant
                             + encodeURI(row.children().eq(3).text())        // warnings and errors
                             + (!$("#nameCheck").is(":checked")? "" :        // nameCheck
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
