<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-22
 * Modified    : 2012-03-29
 * For LOVD    : 3.0-beta-04
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

function lovd_escapeSearchTerm ($sTerm)
{
    // Escapes search terms entered by the user.
    // FIXME; allow * for % and ? for _?
    $sTerm = str_replace('%', '\%', $sTerm);
    $sTerm = str_replace('_', '\_', $sTerm);
    // Reverse the insertion of {{SPACE}} done to allow for searches where the order of words is forced by enclosing the values with double quotes.
    $sTerm = str_replace('{{SPACE}}', ' ', $sTerm);
    return $sTerm;
}





function lovd_pagesplitInit ()
{
    // Based on a function provided by Ileos.nl in the interest of Open Source.
    // Initializes page splitting function which converts long lists of results
    // to a set number of results per page.
    // The Init function returns $sSQLLimit, a string with the limit
    // information you need to include in your query.
    global $_SETT;

    if (!empty($_GET['page']) && is_numeric($_GET['page'])) {
        $_GET['page'] = (int) $_GET['page'];
        if ($_GET['page'] < 1) {
            $_GET['page'] = 1;
        }
    } else {
        $_GET['page'] = 1;
    }

    if (!empty($_GET['page_size']) && (in_array($_GET['page_size'], $_SETT['list_sizes']) || $_GET['page_size'] == '1')) {
        // Special allowance for page_size = 1 for Ajax retrieval of 1 row after deleting one.
        $_GET['page_size'] = (int) $_GET['page_size'];
    } else {
        $_GET['page_size'] = 100;
    }

    global $_PAGESPLIT;
    $_PAGESPLIT = array();
    $_PAGESPLIT['offset'] = ($_GET['page'] - 1) * $_GET['page_size'];
    $_PAGESPLIT['first_entry'] = $_PAGESPLIT['offset'] + 1;

    return $_GET['page_size'] . ' OFFSET ' . $_PAGESPLIT['offset'];
}





function lovd_pagesplitShowNav ($sViewListID, $nTotal, $nShownPages = 10)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Initializes page splitting function which converts long lists of results
    // to a set number of results per page. Navigation code is included.
    // The ShowNav function writes PageSplit navigation to stdout.

    // Give me my vars back!
    global $_PAGESPLIT, $_SETT;
    $_PAGESPLIT['total'] = $nTotal;
    foreach ($_PAGESPLIT as $key => $val) {
        ${$key} = $val;
    }

    // Last entry of the page.
    $last_entry = $_GET['page'] * $_GET['page_size'];
    if ($last_entry > $total) {
        $last_entry = $total;
        $_GET['page'] = floor($last_entry/$_GET['page_size']) + 1;
    }

    // Total number of pages needed for all entries.
    $nPages = ceil($total / $_GET['page_size']);

    if (empty($_PAGESPLIT['printed'])) {
        // First, print the range currently on the screen.
        print('      <SPAN class="S11" id="viewlistPageSplitText_' . $sViewListID . '">' . "\n" .
              '        ' . $total . ' entr' . ($total == 1? 'y' : 'ies') . ' on ' . $nPages . ' page' . ($nPages == 1? '' : 's') . '.');

        // lovd_pagesplitInit() is not run, when we have a BadSyntax error message.
        if (isset($first_entry)) {
            if ($first_entry == $last_entry) {
                print(' Showing entry ' . $first_entry . ".\n");
            } elseif ($first_entry <= $total) {
                print(' Showing entries ' . $first_entry . ' - ' . $last_entry . ".\n");
            }
        }
        print('      </SPAN>' . "\n");
    }

    print('      <TABLE border="0" cellpadding="0" cellspacing="3" class="pagesplit_nav">' . "\n" .
          '        <TR>' . "\n" .
          '          <TD style="border : 0px; cursor : default; padding-right : 10px;">' . "\n" .
          '            <SELECT onchange="document.forms[\'viewlistForm_' . $sViewListID . '\'].page_size.value = this.value; document.forms[\'viewlistForm_' . $sViewListID . '\'].page.value = 1; lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\');">');
    foreach ($_SETT['list_sizes'] as $nSize) {
        print("\n" .
              '              <OPTION value="' . $nSize . '"' . ($nSize == $_GET['page_size']? ' selected' : '') . '>' . $nSize . ' per page</OPTION>');
    }
    print("\n" .
          '            </SELECT></TD>');

    if ($nPages > 1) {
        // First printed page number.
        $nFirstPage = $_GET['page'] - $nShownPages;
        if ($nFirstPage < 1) {
            $nFirstPage = 1;
        }

        // Provide "First" and "Previous" buttons; even though they may not be active.
        print("\n" .
              '          <TH ' . ($_GET['page'] > 1? 'onclick="lovd_AJAX_viewListGoToPage(\'' . $sViewListID . '\', 1);"' : 'class="inactive"') . '>&laquo; First</TH>' . "\n" .
              '          <TH ' . ($_GET['page'] > 1? 'onclick="lovd_AJAX_viewListGoToPage(\'' . $sViewListID . '\', ' . ($_GET['page'] - 1) . ');"' : 'class="inactive"') . '>&#139; Prev</TH>' . "\n" .
              '          <TD>&nbsp;&nbsp;&nbsp;</TD>');

        // Pages that aren't be printed.
        if ($nFirstPage > 1) {
            print("\n" . '          <TD class="inactive">...</TD>');
        }

        // Loop through all pages that need to be printed.
        for ($i = $nFirstPage; $i <= $nPages && $i <= ($_GET['page'] + $nShownPages); $i ++) {
            print("\n" .
                  '          <TD class="' . ($_GET['page'] == $i? 'selected' : 'num" onclick="lovd_AJAX_viewListGoToPage(\'' . $sViewListID . '\', ' . $i . ');') . '">' . $i . '</TD>');
        }

        // Last printed page block.
        $nLastPage = $i - 1;

        // Pages that aren't be printed.
        if ($nLastPage != $nPages) {
            print("\n" . '          <TD class="inactive">...</TD>');
        }

        // Provide "Next" and "Last" buttons; even though they may not be active.
        print("\n" .
              '          <TD>&nbsp;&nbsp;&nbsp;</TD>' . "\n" .
              '          <TH ' . (($_GET['page_size'] * $_GET['page']) < $total? 'onclick="lovd_AJAX_viewListGoToPage(\'' . $sViewListID . '\', ' . ($_GET['page'] + 1) . ');"' : 'class="inactive"') . '>Next &#155;</TH>' . "\n" .
              '          <TH ' . ($_GET['page'] != $nPages? 'onclick="lovd_AJAX_viewListGoToPage(\'' . $sViewListID . '\', ' . $nPages .');"' : 'class="inactive"') . '>Last &raquo;</TH>');
    }

    print('</TR></TABLE>' . "\n\n");
    $_PAGESPLIT['printed'] = true;
}
?>
