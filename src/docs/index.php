<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-11-27
 * Modified    : 2024-09-10
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', '../');
define('TAB_SELECTED', 'docs');
require ROOT_PATH . 'inc-init.php';





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /docs
    // Provide link to PDF and HTML file.

    define('PAGE_TITLE', 'LOVD' . (LOVD_plus? '+' : ' 3.0') . ' documentation');
    $_T->printHeader();
    $_T->printTitle();

    if (LOVD_plus) {
        print('      Currently available is the LOVD+ user manual, in PDF format.<BR>' .
              '      <UL>' . "\n" .
              '        <LI>LOVD manual 3.0-17r (<A href="docs/LOVD+_manual.pdf" target="_blank"><B>PDF</B>, 21 pages, 1.2Mb</A>) - last updated March 13th, 2019</LI></UL>' . "\n\n");
    } else {
        print('      Currently available is the LOVD 3.0 user manual, in PDF and HTML formats.<BR>' .
              '      <UL>' . "\n" .
              '        <LI>LOVD manual 3.0-31 (<A href="docs/LOVD_manual_3.0.pdf" target="_blank"><B>PDF</B>, 88 pages, 1.5Mb</A>) (<A href="docs/manual.html" target="_blank"><B>HTML</B>, single file, 5.2Mb</A>) - last updated September 10th 2024</LI></UL>' . "\n\n");
    }

    $_T->printFooter();
    exit;
}
?>
