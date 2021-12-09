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





if (PATH_COUNT == 1 && ACTION == 'checkOne') {
    // URL: /checkHGVS?checkOne
    // Simple HGVS check of one variant.

    define('PAGE_TITLE', 'Single variant HGVS Check');
    define('LOG_EVENT', 'CheckHGVS');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-variants.php';

    $_T->printHeader(false);
    $_T->printTitle();

    print('      To check your variant, please write it down in the input bar below.<BR><BR>');

    // Show form.
    print(
        '<FORM onsubmit="showResponse(); return false;" action="">
            <INPUT onchange="showResponse();" id="variant"/>
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
            $.get("ajax/checkHGVS.php?var=" + $("#variant").val() + "&method=single").fail(function(){alert("Error checking variant, please try again later.");});
        }
        </SCRIPT>'
    );

    exit;
}





if (PATH_COUNT == 1 && ACTION == 'checkList') {
    // URL: /checkHGVS?checkList
    // HGVS check of a list of variants as given through an input box.

    define('PAGE_TITLE', 'HGVS Check of a list of variants');
    define('LOG_EVENT', 'CheckHGVS');

    require ROOT_PATH . 'inc-lib-form.php';
    require ROOT_PATH . 'inc-lib-variants.php';


    $_T->printHeader(false);
    $_T->printTitle();

    print('      To check your variants, please write them down in the input bar below, each on one line.<BR><BR>');

    // Show form.
    print(
    '<FORM onsubmit="showResponse(); return false;" action="">
            <TEXTAREA onchange="showResponse();" cols="30" rows="10" id="variant"></TEXTAREA>
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
            $.get("ajax/checkHGVS.php?var=" + encodeURIComponent($("#variant").val()) + "&method=list").fail(function(){alert("Error checking variant, please try again later.");});
        }
        </SCRIPT>'
    );

    exit;
}

?>
