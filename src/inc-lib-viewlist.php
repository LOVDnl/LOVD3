<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-22
 * Modified    : 2015-12-22
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
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





function lovd_formatSearchExpression ($sExpression, $sColumnType)
{
    // Formats the search expressions for the "title" text of the input field so that the users can understand what their search does.
    if ($sColumnType == 'DATETIME') {
        $sExpression = preg_replace('/ (\d)/', "{{SPACE}}$1", trim($sExpression));
    } else {
        $sExpression = preg_replace_callback('/("[^"]+")/', create_function('$aRegs', 'return str_replace(\' \', \'{{SPACE}}\', $aRegs[1]);'), trim($sExpression));
    }
    $aANDExpressions = explode(' ', $sExpression);
    $nANDLength = count($aANDExpressions);
    $sFormattedExpressions = '';
    foreach ($aANDExpressions as $nANDIndex => $sANDExpression) {
        if (!trim($sANDExpression)) {
            // Double spaces in the search terms.
            continue;
        }
        $aORExpressions = explode('|', $sANDExpression);
        $nORLength = count($aORExpressions);
        $sFormattedExpression = ($sColumnType == 'TEXT'? ' - ' : ' ');
        if ($nORLength > 1) {
            $sFormattedExpression .= '(';
        }
        foreach ($aORExpressions as $nORIndex => $sORExpression) {
            switch ($sColumnType) {
                case 'TEXT':
                    if ($sORExpression{0} == '!' && $sORExpression{1} == '=') {
                        $sFormattedExpression .= 'Does not exactly match ' . trim($sORExpression, '!="');
                    } elseif ($sORExpression{0} == '!' && $sORExpression{1} != '=') {
                        $sFormattedExpression .= 'Does not contain ' . trim($sORExpression, '!=');
                    } elseif ($sORExpression{0} == '=') {
                        $sFormattedExpression .= 'Exactly matches ' . trim($sORExpression, '="');
                    } else {
                        $sFormattedExpression .= 'Contains ' . $sORExpression;
                    }
                    break;
                case 'INT':
                case 'INT_UNSIGNED':
                case 'DECIMAL':
                case 'DECIMAL_UNSIGNED':
                case 'DATE':
                case 'DATETIME':
                    if (!in_array($sORExpression{0}, array('<', '>', '!'))) {
                        $sFormattedExpression .= '=' . $sORExpression;
                    } else {
                        $sFormattedExpression .= $sORExpression;
                    }
                    break;
                default:
                    $sFormattedExpression .= $sORExpression;
            }
            if ($nORIndex + 1 != $nORLength) {
                $sFormattedExpression .= ' OR ';
            } elseif ($nORLength > 1) {
                $sFormattedExpression .= ')';
            }
        }
        $sFormattedExpressions .= $sFormattedExpression;
        if ($nANDIndex + 1 != $nANDLength) {
            $sFormattedExpressions .= "\nAND\n";
        }
    }

    return preg_replace('/{{SPACE}}/', ' ', $sFormattedExpressions);
}





function lovd_hideEmail ($s)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Obscure email addresses from spambots.

    $a_replace = array(45 => '-', '.',
        48 => '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        64 => '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        95 => '_',
        97 => 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
    );

    $s_return = '';
    for ($i = 0; $i < strlen($s); $i ++) {
        $s_sub = substr($s, $i, 1);
        if ($key = array_search($s_sub, $a_replace)) {
            $s_return .= '&#' . str_pad($key, 3, '0', STR_PAD_LEFT) . ';';
        } else {
            $s_return .= $s_sub;
        }
    }

    return $s_return;
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





function lovd_pagesplitShowNav ($sViewListID, $nTotal, $bTrueTotal = true, $bSortable = true, $bLegend = true, $nShownPages = 10)
{
    // Function kindly provided by Ileos.nl in the interest of Open Source.
    // Initializes page splitting function which converts long lists of results
    // to a set number of results per page. Navigation code is included.
    // The ShowNav function writes PageSplit navigation to stdout.

    // Give me my vars back!
    global $_PAGESPLIT, $_SETT;
    $_PAGESPLIT['total'] = $nTotal;
    foreach ($_PAGESPLIT as $key => $val) {
        ${$key} = $val; // Defines $first_entry && $total.
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
              '        ' . ($bTrueTotal? '' : '<SPAN class="custom_link" onmouseover="lovd_showToolTip(\'If LOVD takes too much time to determine the number of results,<BR>LOVD will not recalculate this number on every page view.\', this);">About</SPAN> ') .
                  $total . ' entr' . ($total == 1? 'y' : 'ies') . ' on ' . $nPages . ' page' . ($nPages == 1? '' : 's') . '.');

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

    // 2013-03-05; 3.0-03; Added an icon that indicates if sorting is not allowed on this VL.
    if (!$bSortable) {
        print("\n" .
              '          <TD><IMG src="gfx/order_arrow_off.png" width="19" height="19" alt="No sorting possible" onmouseover="lovd_showToolTip(\'Sorting is disabled on this result set due to necessary restrictions;<BR> ' . ($nTotal > $_SETT['lists']['max_sortable_rows']? 'too many results are returned' : 'database takes too much time to generate view') . ', please narrow your search.\', this);" style="margin-right : 5px;"></TD>');
    }

    // Put a button here that shows the full legend, if it's available for this VL. We don't know that here, so we use JS to show it if necessary.
    if ($bLegend) {
        print("\n" .
              '          <TD><B onclick="lovd_showLegend(\'' . $sViewListID . '\');" title="Click here to see the full legend of this data table." class="legend">Legend</B>&nbsp;&nbsp;</TD>');
    }

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
