<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-03-11
 * Modified    : 2015-03-31
 * For LOVD    : 3.0-14
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}

$aPathElements = $_PE; // We'll manipulate $aPathElements, so making a copy from $_PE.
// Check if last element is image.
if ($bImage = (end($aPathElements) == 'image')) {
    // Set $bImage variable and remove last element. So we can do an implode.
    array_pop($aPathElements);
}

// Implode elements 1 up to the end, this is because DOI: can have slashes (/).
$aPathElements[1] = implode('/', array_slice($aPathElements, 1));
// From now on, we'll use $aPathElements instead of $_PE.





if (!ACTION && (empty($aPathElements[1]) || preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($aPathElements[1])))) {
    // URL: /references
    // URL: /references/DMD
    // View all entries (optionally restricted by gene).

    // STUB.
    exit;
}





if (PATH_COUNT >= 2 && (substr($aPathElements[1], 0, 4) == 'DOI:' || substr($aPathElements[1], 0, 5) == 'PMID:')) {
    // URL: /references/DOI:.....
    // URL: /references/DOI:...../image
    // URL: /references/PMID:.....
    // URL: /references/PMID:...../image
    // View specific DOI or PMID.

    require ROOT_PATH . 'inc-lib-columns.php';

    if (substr($aPathElements[1], 0, 4) == 'DOI:') {
        $sSearchPattern = '%{DOI:%' . substr($aPathElements[1], 4) . '}%';
        $sAjaxSearchPattern = '{DOI: ' . ':' . substr($aPathElements[1], 4) . '}';
        $sType = 'DOI';
    } elseif (substr($aPathElements[1], 0, 5) == 'PMID:') {
        $sSearchPattern = '%{PMID:%' . substr($aPathElements[1], 5) . '}%';
        $sAjaxSearchPattern = '{PMID: ' . ':' . substr($aPathElements[1], 5) . '}';
        $sType = 'PubMed';
    }

    $aCategories = array();
    $aColsToHide = array(
        'VariantOnGenome' => array('allele_'),
        'Individual' => array('panelid', 'diseaseids'),
    );
    $aColNames = $_DB->query('SELECT colid FROM ' . TABLE_COLS2LINKS . ' AS c2l LEFT JOIN ' . TABLE_LINKS . ' AS l ON (l.id = c2l.linkid) WHERE name = ?', array($sType))->fetchAllColumn();
    foreach ($aColNames as $sColName) {
        $sCategory = substr($sColName, 0, strpos($sColName . '/', '/'));
        $aTable = lovd_getTableInfoByCategory($sCategory);
        $aData = $_DB->query('SELECT id FROM ' . $aTable['table_sql'] . ' WHERE `' . $sColName . '` LIKE ? LIMIT 1', array($sSearchPattern))->fetchAllColumn();
        if (!empty($aData)) {
            if ($bImage){
                header('Content-type: image/png');
                readfile(ROOT_PATH . 'gfx/LOVD_logo60x26.png');
                exit;
            }
            $aCategories[$sCategory] = true;
            // FIXME: This means that if the PMID/DOI is found in *more than 1 column*, filters will be applied to *both* columns, which may return *no results*.
            // There is no way to make these filters an *OR* filter. Only solution is to select *one* column out of the multiple, but based on what?
            $_GET['search_' . $sColName] = $sAjaxSearchPattern;
            $aColsToHide[$sCategory][] = $sColName;
        }
    }

    if ($bImage) {
        // Apparently, no data has been found.
        header('Content-type: image/png');
        readfile(ROOT_PATH . 'gfx/trans.png');
        exit;
    }

    define('PAGE_TITLE', 'View data for reference: ' . $aPathElements[1]);
    $_T->printHeader();
    $_T->printTitle();

    // Print info table when no variants or individuals are availble for given reference.
    if (empty($aCategories['VariantOnGenome']) && empty($aCategories['Individual'])) {
        lovd_showInfoTable('No data found for reference ' . $aPathElements[1], 'stop');
        $_T->printFooter();
        exit;
    }

    // Check which tab is active and which must be disabled. Depending on available data
    $nActiveTab = 0;
    $nDisabledTab = null;

    if (empty($aCategories['Individual'])) {
        $nActiveTab = 0;
        $nDisabledTab = 1;
    } elseif (empty($aCategories['VariantOnGenome'])) {
        $nActiveTab = 1;
        $nDisabledTab = 0;
    }

    if (!empty($aCategories['VariantOnGenome'])) {
        require ROOT_PATH . 'class/object_genome_variants.php';
        $_DATAvariants = new LOVD_GenomeVariant();
    }

    if (!empty($aCategories['Individual'])) {
        require ROOT_PATH . 'class/object_individuals.php';
        $_DATAindividuals = new LOVD_Individual();
    }

    print('   <SCRIPT type="text/javascript">' . "\n" .
          '       $(function() {' . "\n" .
          '           $("#tabs").tabs({active: ' . $nActiveTab . ', disabled: [' . $nDisabledTab . ']});' . "\n" .
          '       });' . "\n" .
          '   </SCRIPT>' . "\n" .
          '   <DIV id="tabs">' . "\n" .
          '       <UL>' . "\n" .
          '           <LI><A href="' . lovd_getInstallURL() . implode('/', $_PE) . '#tabs-variants">Variants</A></LI>' . "\n" .
          '           <LI><A href="' . lovd_getInstallURL() . implode('/', $_PE) . '#tabs-individuals">Individuals</A></LI>' . "\n" .
          '       </UL>' . "\n" .
          '       <DIV id="tabs-variants">' . "\n");
    if (!empty($_DATAvariants)){
        $_DATAvariants->viewList('Variants_per_reference', $aColsToHide['VariantOnGenome'], true, true);
    }
    print('       </DIV>' . "\n" .
          '       <DIV id="tabs-individuals">' . "\n");
    if (!empty($_DATAindividuals)){
        $_DATAindividuals->viewList('Individuals_per_reference', $aColsToHide['Individual'], true, true);
    }
    print('       </DIV>' . "\n" .
          '   </DIV>');

    $_T->printFooter();
    exit;
}

if ($bImage) {
    header('Content-type: image/png');
    readfile(ROOT_PATH . 'gfx/trans.png');
    exit;
}

define('PAGE_TITLE', 'View data for reference: ' . $aPathElements[1]);
$_T->printHeader();
$_T->printTitle();

lovd_showInfoTable('Unknown reference ' . $aPathElements[1], 'stop');
$_T->printFooter();
exit;
