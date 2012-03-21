<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2012-03-21
 * For LOVD    : 3.0-beta-03
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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





if (empty($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /screenings
    // View all entries.

    define('PAGE_TITLE', 'View screenings');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $_DATA->viewList('Screenings', 'screeningid');

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /screenings/0000000001
    // View specific entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'View screening #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    // Load appropiate user level for this screening entry.
    lovd_isAuthorized('screening', $nID);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening($nID);
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH) {
        if ($_AUTH['level'] >= LEVEL_OWNER) {
            $sNavigation = '<A href="screenings/' . $nID . '?edit">Edit screening information</A>';
            if ($zData['variants_found']) {
                $sNavigation .= ' | <A href="variants?create&amp;target=' . $nID . '">Add variant to screening</A>';
            }
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $sNavigation .= ' | <A href="screenings/' . $nID . '?delete">Delete screening entry</A>';
            }
        }
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    if (!empty($zData['search_geneid'])) {
        $_GET['search_geneid'] = html_entity_decode(rawurldecode($zData['search_geneid']));
        print('<BR><BR>' . "\n\n");
        lovd_printHeader('Genes screened', 'H4');
        require ROOT_PATH . 'class/object_genes.php';
        $_DATA = new LOVD_Gene();
        $_DATA->setSortDefault('id');
        $_DATA->viewList('Genes_for_S_VE', 'geneid', true, true);
        unset($_GET['search_geneid']);
    }
    
    if ($zData['variants_found'] || !empty($zData['variants'])) {
        $_GET['search_screeningids'] = $nID;
        print('<BR><BR>' . "\n\n");
        lovd_printHeader('Variants found', 'H4');
        require ROOT_PATH . 'class/object_genome_variants.php';
        $_DATA = new LOVD_GenomeVariant();
        $_DATA->setSortDefault('id');
        $_DATA->viewList('VOG_for_S_VE', array('id', 'screeningids'));
    }

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create' && isset($_GET['target']) && ctype_digit($_GET['target'])) {
    // URL: /screenings?create
    // Create a new entry.

    define('LOG_EVENT', 'ScreeningCreate');

    lovd_requireAUTH();

    $_GET['target'] = sprintf('%08d', $_GET['target']);
    $z = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($_GET['target']))->fetchAssoc();
    if (!$z) {
        define('PAGE_TITLE', 'Create a new screening entry');
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_showInfoTable('The individual ID given is not valid, please go to the desired individual entry and click on the "Add screening" button.', 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    } elseif (!lovd_isAuthorized('individuals', $_GET['target'], true)) {
        lovd_requireAUTH(LEVEL_OWNER);
    }
    $_POST['individualid'] = $_GET['target'];
    define('PAGE_TITLE', 'Create a new screening information entry for individual #' . $_GET['target']);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('individualid', 'variants_found', 'owned_by', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created screening information entry ' . $nID);
            
            $aSuccessGenes = array();
            if (!empty($_POST['genes']) && is_array($_POST['genes'])) {
                foreach ($_POST['genes'] as $sGene) {
                    // Add disease to gene.
                    if (in_array($sGene, lovd_getGeneList())) {
                        $q = $_DB->query('INSERT INTO ' . TABLE_SCR2GENE . ' VALUES (?, ?)', array($nID, $sGene));
                        // FIXME; I think this is not possible without a query error, that by default halts the system. Maybe you want to set $_DB->query()'s third argument to false?
                        if (!$q->rowCount()) {
                            // Silent error.
                            // FIXME; maybe better to group the error messages, just like when editing?
                            lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGene . ' - could not be added to screening ' . $nID);
                        } else {
                            $aSuccessGenes[] = $sGene;
                        }
                    }
                }
            }

            if (count($aSuccessGenes)) {
                lovd_writeLog('Event', LOG_EVENT, 'Gene entries successfully added to screening ' . $nID);
            }

            $bSubmitType = '';
            if (isset($_SESSION['work']['submits']['individual'][$_POST['individualid']])) {
                $bSubmitType = 'individual';

                $nPanel = $_SESSION['work']['submits']['individual'][$_POST['individualid']]['panel_size'];

                if (!isset($_SESSION['work']['submits']['individual'][$_POST['individualid']]['screenings'])) {
                    $_SESSION['work']['submits']['individual'][$_POST['individualid']]['screenings'] = array();
                }

                $_SESSION['work']['submits']['individual'][$_POST['individualid']]['screenings'][] = $nID;
            } else {
                $bSubmitType = 'screening';

                $nPanel = $_DB->query('SELECT panel_size FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($_POST['individualid']))->fetchColumn();

                if (!isset($_SESSION['work']['submits']['screening'])) {
                    $_SESSION['work']['submits']['screening'] = array();
                }

                while (count($_SESSION['work']['submits']['screening']) >= 10) {
                    unset($_SESSION['work']['submits']['screening'][min(array_keys($_SESSION['work']['submits']['screening']))]);
                }

                $_SESSION['work']['submits']['screening'][$nID] = array();
            }

            $sPersons = ($nPanel > 1? 'this group of individuals' : 'this individual');

            if ($bSubmitType == 'screening' && !$_POST['variants_found']) {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/screening/' . $nID);
                exit;
            } else {
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                $aOptionsList = array();
                print('      Were there any variants found with this mutation screening?<BR><BR>' . "\n\n");
                if (!$_POST['variants_found']) {
                    $aOptionsList['options'][0]['disabled'] = true;
                    $aOptionsList['options'][0]['onclick']  = 'alert(\'You cannot add variants to this screening, because you have unchecked the &quot;Have variants been found?&quot; checkbox!\');';
                } else {
                    $aOptionsList['options'][0]['onclick'] = 'window.location.href=\'' . lovd_getInstallURL() . 'variants?create&amp;target=' . $nID . '\'';
                }
                $aOptionsList['options'][0]['option_text'] = '<B>Yes, I want to submit variants found by this mutation screening</B>';

                if ($_POST['variants_found']) {
                    $aOptionsList['options'][2]['disabled'] = $aOptionsList['options'][1]['disabled'] = true;
                    $aOptionsList['options'][1]['onclick']  = 'alert(\'You cannot add a new screening to ' . $sPersons . ', because no variants have been added to this screening yet!\');';
                    $aOptionsList['options'][2]['onclick']  = 'alert(\'You cannot finish your submission, because no variants have been added to this screening yet!\');';
                } elseif ($bSubmitType == 'individual' && !$_POST['variants_found']) {
                    $aOptionsList['options'][1]['onclick'] = 'window.location.href=\'' . lovd_getInstallURL() . 'screenings?create&amp;target=' . $_POST['individualid'] . '\'';
                    $aOptionsList['options'][2]['onclick'] = 'window.location.href=\'' . lovd_getInstallURL() . 'submit/finish/individual/' . $_POST['individualid'] . '\'';
                }
                $aOptionsList['options'][1]['option_text'] = '<B>No, I want to submit another mutation screening on ' . $sPersons . ' instead</B>';
                $aOptionsList['options'][2]['option_text'] = '<B>No, I have finished my submission</B>';

                print(lovd_buildOptionTable($aOptionsList));
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To create a new screening information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&amp;target=' . $_GET['target'] . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create screening information entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /screenings/0000000001?edit
    // Edit an entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Edit an screening information entry');
    define('LOG_EVENT', 'ScreeningEdit');

    // Load appropiate user level for this screening entry.
    lovd_isAuthorized('screening', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('variants_found', 'edited_by', 'edited_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['variants_found'] = (empty($_POST['variants_found'])? '1' : $_POST['variants_found']);
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFieldsGenome[] = 'owned_by';
            }
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');
            
            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited screening information entry ' . $nID);

            // Change linked genes?
            // Genes the screening is currently linked to.

            // Remove genes.
            $aToRemove = array();
            foreach ($zData['genes'] as $sGene) {
                if (!in_array($sGene, $_POST['genes'])) {
                    // User has requested removal...
                    $aToRemove[] = $sGene;
                }
            }

            if ($aToRemove) {
                $q = lovd_queryDB_Old('DELETE FROM ' . TABLE_SCR2GENE . ' WHERE screeningid = ? AND geneid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove));
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from screening ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from screening ' . $nID);
                }
            }

            // Add genes.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['genes'] as $sGene) {
                if (!in_array($sGene, $zData['genes']) && in_array($sGene, lovd_getGeneList())) {
                    // Add gene to screening.
                    $q = lovd_queryDB_Old('INSERT IGNORE INTO ' . TABLE_SCR2GENE . ' VALUES (?, ?)', array($nID, $sGene));
                    if (!$q) {
                        $aFailed[] = $sGene;
                    } else {
                        $aSuccess[] = $sGene;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to screening ' . $nID);
            } elseif ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to screening ' . $nID);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the screening information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    if (GET) {
        print('      To edit an screening information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Edit screening information entry'),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'confirm') {
    // URL: /screenings/0000000001?confirm
    // Confirm existing variant entries within the same individual.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Confirm variant entries for with screening #' . $nID);
    define('LOG_EVENT', 'VariantConfirm');

    $z = $_DB->query('SELECT id, individualid, variants_found FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($nID))->fetchAssoc();
    $sMessage = '';
    if (!$z) {
        $sMessage = 'The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.';
    } elseif (!lovd_isAuthorized('screening', $nID)) {
        lovd_requireAUTH(LEVEL_OWNER);
    } elseif (!$z['variants_found']) {
        $sMessage = 'Cannot add variant to the given screening, because the value \'Have variants been found?\' is unchecked.';
    }
    if ($sMessage) {
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);
        lovd_showInfoTable($sMessage, 'stop');
        require ROOT_PATH . 'inc-bot.php';
        exit;
    } else {
        $nIndividual = $z['individualid'];
        $_GET['search_screeningids'] = $_DB->query('SELECT GROUP_CONCAT(id SEPARATOR "|") FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ? GROUP BY individualid', array($nIndividual))->fetchColumn();
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'] stores the IDs of the variants that are supposed to go in TABLE_SCR2VAR.
        if (isset($_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'])) {
            // Check if all checked variants are actually from this individual.
            $aVariantIDs = $_DB->query('SELECT s2v.variantid FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = ?', array($nIndividual))->fetchAllColumn();
            foreach ($_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'] as $nVariant) {
                if (!in_array($nVariant, $aVariantIDs)) {
                    // The user tried to fake a $_POST by inserting an ID that did not come from our code.
                    lovd_errorAdd('', 'Invalid variant, please select the variants from the top viewlist!');
                    break;
                }
            }
        }

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DB->beginTransaction();

            $aCurrentVariants = $_DB->query('SELECT variantid FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchAllColumn();
            $nVariantsChecked = 0; // Amount of variants checked. Determines which options to show after submit.

            // Insert newly confirmed variants.
            $q = $_DB->prepare('INSERT INTO ' . TABLE_SCR2VAR . '(screeningid, variantid) VALUES (?, ?)');
            foreach ($_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'] as $nVariant) {
                $nVariantsChecked ++;
                if (!in_array($nVariant, $aCurrentVariants)) {
                    // If the variant is not already connected to this screening, we will add it now.
                    $q->execute(array($nID, $nVariant));
                }
            }

            // Build list of deselected variants.
            $aToRemove = array();
            $nNotRemoved = 0; // Variants that could not be removed, because they would be orphaned otherwise.
            foreach ($aCurrentVariants as $nVariant) {
                if (!in_array($nVariant, $_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'])) {
                    // If one of the variants currently present in the database is not present in $_SESSION, we will want to remove it.
                    $aToRemove[] = $nVariant;
                }
            }

            // Now, check if the variants that are to be removed, would not end up without any screenings!
            if (!empty($aToRemove)) {
                $aToRemoveFinal = $_DB->query('SELECT variantid, COUNT(screeningid) AS nCount FROM ' . TABLE_SCR2VAR . ' WHERE variantid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ') GROUP BY variantid HAVING nCount > 1', $aToRemove)->fetchAllColumn();
                // Remove variants from screening...
                if (!empty($aToRemoveFinal)) {
                    $_DB->query('DELETE FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ? AND variantid IN (?' . str_repeat(', ?', count($aToRemoveFinal) - 1) . ')', array_merge(array($nID), $aToRemoveFinal));
                }
                $nNotRemoved = count($aToRemove) - count($aToRemoveFinal);
            }

            // If we get here, it all succeeded.
            $_DB->commit();
            unset($_SESSION['viewlists']['Screenings_' . $nID . '_confirm']);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated the list of variants confirmed with screening #' . $nID);

            $bSubmit = false;
            $bSubmitIndividual = false;
            $bSubmitScreening = false;
            if (isset($_SESSION['work']['submits']['individual'][$nIndividual])) {
                $bSubmit = true;
                $bSubmitIndividual = true;
                $nPanel = $_SESSION['work']['submits']['individual'][$nIndividual]['panel_size'];
            } elseif (isset($_SESSION['work']['submits']['screening'][$nID])) {
                $bSubmit = true;
                $bSubmitScreening = true;
                $nPanel = $_DB->query('SELECT panel_size FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($nIndividual))->fetchColumn();
            }

            if ($bSubmit) {
                $sPersons = ($nPanel > 1? 'this group of individuals' : 'this individual');

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                print('      Were there any additional variants found with this mutation screening?<BR><BR>' . "\n\n" .
                      '      <TABLE border="0" cellpadding="5" cellspacing="1" class="option">' . "\n" .
                      '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'variants?create&amp;target=' . $nID . '\'">' . "\n" .
                      '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
                      '          <TD><B>Yes, I want to submit additional variants found by this mutation screening</B></TD></TR>' . "\n" .
 ($bSubmitIndividual && $nVariantsChecked? 
                      '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'screenings?create&amp;target=' . $nIndividual . '\'">' . "\n" .
                      '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
                      '          <TD><B>No, I want to submit another mutation screening on ' . $sPersons . ' instead</B></TD></TR>' . "\n" : '') .
  ($nVariantsChecked? '        <TR onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/finish/' . ($bSubmitIndividual? 'individual/' . $nIndividual : 'screening/' . $nID) . '\'">' . "\n" .
                      '          <TD width="30" align="center"><SPAN class="S18">&raquo;</SPAN></TD>' . "\n" .
                      '          <TD><B>No, I have finished my submission</B></TD></TR>' : '      ') .  '</TABLE><BR>' . "\n\n");
                require ROOT_PATH . 'inc-bot.php';
                exit;
            }

            // Thank the user...
            header('Refresh: ' . ($nNotRemoved? 10 : 3) . '; url=' . lovd_getInstallURL() . 'screenings/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            if ($nNotRemoved) {
                lovd_showInfoTable('Successfully updated the list of variants confirmed with this screening!<BR>' . $nNotRemoved . ' variant' . ($nNotRemoved == 1? '' : 's') . ' could not be removed from this screening, because this is the only screening they are connected to.', 'information');
            } else {
                lovd_showInfoTable('Successfully updated the list of variants confirmed with this screening!', 'success');
            }

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }

    } else {
        // Default session values.
        $_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked_all'] = false;
        $_SESSION['viewlists']['Screenings_' . $nID . '_confirm']['checked'] = $_DB->query('SELECT variantid FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchAllColumn();
    }

    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();
    lovd_showInfoTable('The variant entries below are all variants found in this individual. If checked, the variant is already added to/confirmed by this screening.', 'information');

    $_GET['page_size'] = 10;
    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $_DATA->viewList('Screenings_' . $nID . '_confirm', array('id_', 'screeningids', 'chromosome'), true, false, true);

    print('      <BR><BR>' . "\n\n");

    // Table.
    print('      <FORM id="confirmVariants" action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '0%', '0', '100%'),
                    array('', '', 'print', 'Enter your password for authorization'),
                    array('', '', 'password', 'password', 20),
                    array('', '', 'print', '<INPUT type="submit" value="Save variant list" onclick="lovd_AJAX_viewListSubmit(\'Screenings_' . $nID . '_confirm\', function () { $(\'#confirmVariants\').submit(); }); return false;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="document.location.href=\'' . lovd_getInstallURL() . 'screenings/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">'),
                  );
    lovd_viewForm($aForm);

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /screenings/0000000001?delete
    // Drop specific entry.

    $nID = sprintf('%010d', $_PATH_ELEMENTS[1]);
    define('PAGE_TITLE', 'Delete screening information entry ' . $nID);
    define('LOG_EVENT', 'ScreeningDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in gen2dis and transcripts.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted screening information entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'screenings');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the screening information entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . $_PATH_ELEMENTS[0] . '/' . $nID . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting screening information entry', '', 'print', $nID),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete screening information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}

?>
