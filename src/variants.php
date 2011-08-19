<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-21
 * Modified    : 2011-08-18
 * For LOVD    : 3.0-alpha-04
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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
    // URL: /variants
    // View all entries.

    define('PAGE_TITLE', 'View genomic variants');
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $_DATA->viewList(false, 'screeningids');

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && !ACTION) {
    // URL: /variants/0000000001
    // View specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'View genomic variant #' . $nID);
    require ROOT_PATH . 'inc-top.php';
    lovd_printHeader(PAGE_TITLE);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->viewEntry($nID);
    
    $sNavigation = '';
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        // Authorized user (admin or manager) is logged in. Provide tools.
        $sNavigation = '<A href="variants/' . $nID . '?edit">Edit variant entry</A>';
        $sNavigation .= ' | <A href="variants/' . $nID . '?delete">Delete variant entry</A>';
    }

    if ($sNavigation) {
        print('      <IMG src="gfx/trans.png" alt="" width="1" height="5"><BR>' . "\n");
        lovd_showNavigation($sNavigation);
    }

    $_GET['search_id'] = $nID;
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Variant on transcripts', 'H4');
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = new LOVD_TranscriptVariant();
    $_DATA->sRowLink = '';
    $_DATA->viewList(false, array('id'), true, true);
    unset($_GET['search_id']);

    $_GET['search_screeningid'] = (!empty($zData['screeningids'])? $zData['screeningids'] : 0);
    print('<BR><BR>' . "\n\n");
    lovd_printHeader('Screenings', 'H4');
    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $_DATA->viewList(false, array('screeningid', 'individualid', 'created_date', 'edited_date'), true, true);

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (empty($_PATH_ELEMENTS[1]) && ACTION == 'create') {
    // Create a new entry.

    lovd_requireAUTH();

    if (isset($_GET['reference']) && $_GET['reference'] == 'Genome') {
        // URL: /variants?create&reference='Genome'
        // Create a variant on the genome.
        define('LOG_EVENT', 'GenomeVariantCreate');

        if (isset($_GET['target'])) {
            if (ctype_digit($_GET['target'])) {
                $_GET['target'] = str_pad($_GET['target'], 10, '0', STR_PAD_LEFT);
                if (mysql_num_rows(lovd_queryDB_Old('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target'])))) {
                    $_POST['screeningid'] = $_GET['target'];
                    define('PAGE_TITLE', 'Create a new variant entry for screening #' . $_GET['target']);
                } else {
                    define('PAGE_TITLE', 'Create a new variant entry');
                    require ROOT_PATH . 'inc-top.php';
                    lovd_printHeader(PAGE_TITLE);
                    lovd_showInfoTable('The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.', 'warning');
                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }
            } else {
                exit;
            }
        } else {
            define('PAGE_TITLE', 'Create a new variant entry');
        }

        require ROOT_PATH . 'class/object_genome_variants.php';
        $_DATA = new LOVD_GenomeVariant();
        require ROOT_PATH . 'inc-lib-form.php';
        
        if (POST) {
            lovd_errorClean();

            $_DATA->checkFields($_POST);

            if (!lovd_error()) {
                // Fields to be used.
                $aFields = array_merge(
                                array('allele', 'chromosome', 'ownerid', 'statusid', 'created_by', 'created_date'),
                                $_DATA->buildFields());

                // Prepare values.
                $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
                $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
                $_POST['created_by'] = $_AUTH['id'];
                $_POST['created_date'] = date('Y-m-d H:i:s');

                $nID = $_DATA->insertEntry($_POST, $aFields);

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created variant entry ' . $nID);

                // Add variant to screening.
                if (isset($_POST['screeningid'])) {
                    $q = lovd_queryDB_Old('INSERT INTO ' . TABLE_SCR2VAR . ' VALUES (?, ?)', array($_POST['screeningid'], $nID));
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Variant entry could not be added to screening #' . $_POST['screeningid']);
                    }
                }

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('Successfully created the variant entry!', 'success');

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
            print('      To create a new variant entry, please fill out the form below.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Tooltip JS code.
        lovd_includeJS('inc-js-tooltip.php');
        lovd_includeJS('inc-js-custom_links.php');

        // Table.
        print('      <FORM action="' . CURRENT_PATH . '?create&amp;reference=Genome' . (isset($_POST['screeningid'])? '&amp;target=' . $_GET['target'] : '') . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array_merge(
                     $_DATA->getForm(),
                     array(
                            array('', '', 'submit', 'Create variant entry'),
                          ));
        lovd_viewForm($aForm);

        print('      </FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;





    } elseif (isset($_GET['reference']) && $_GET['reference'] == 'Transcript') {
        // URL: /variants?create&reference='Transcript&transcriptid=00001'
        // Create a variant on a transcript.
        define('LOG_EVENT', 'TranscriptVariantCreate');
        
        if (isset($_GET['target'])) {
            if (ctype_digit($_GET['target'])) {
                $_GET['target'] = str_pad($_GET['target'], 10, '0', STR_PAD_LEFT);
                if (mysql_num_rows(lovd_queryDB_Old('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($_GET['target'])))) {
                    $_POST['screeningid'] = $_GET['target'];
                } else {
                    define('PAGE_TITLE', 'Create a new variant entry');
                    require ROOT_PATH . 'inc-top.php';
                    lovd_printHeader(PAGE_TITLE);
                    lovd_showInfoTable('The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.', 'warning');
                    require ROOT_PATH . 'inc-bot.php';
                    exit;
                }
            } else {
                exit;
            }
        }

        if (isset($_GET['transcriptid']) && ctype_digit($_GET['transcriptid'])) {
            $_GET['transcriptid'] = str_pad($_GET['transcriptid'], 5, '0', STR_PAD_LEFT);
            list($sGene) = mysql_fetch_row(lovd_queryDB_Old('SELECT geneid FROM ' . TABLE_TRANSCRIPTS . ' WHERE id = ?', array($_GET['transcriptid'])));
            if (empty($sGene)) {
                define('PAGE_TITLE', 'Create a new variant entry');
                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('The transcript ID given is not valid, please go to the create variant page and select the desired transcript entry.', 'warning');
                require ROOT_PATH . 'inc-bot.php';
                exit;
            } else {
                define('PAGE_TITLE', 'Create a new variant entry for transcript #' . $_GET['transcriptid']);
            }
        } else {
            exit;
        }

        require ROOT_PATH . 'class/object_genome_variants.php';
        require ROOT_PATH . 'class/object_transcript_variants.php';

        $_DATA = array();
        $_DATA['Genome'] = new LOVD_GenomeVariant();
        $_DATA['Transcript'] = new LOVD_TranscriptVariant($sGene);
        require ROOT_PATH . 'inc-lib-form.php';

        if (POST) {
            lovd_errorClean();
            // FIXME; ik raak je hier kwijt; waar is dit allemaal voor? Waarom niet gewoon de checkFields() aanroepen?
            // Ivar: Omdat het 2 verschillende data objecten zijn. Moet je dus ook de aparte checkFields en insertEntry aanroepen e.d.
            //       En dus dan ook de $aFields apart doen.
            // Fields to be used.
            $aFieldsGenome = array_merge(
                                array('allele', 'chromosome', 'ownerid', 'statusid', 'created_by', 'created_date'),
                                $_DATA['Genome']->buildFields());

            $aFieldsTranscript = array_merge(
                                array('id', 'transcriptid'),
                                $_DATA['Transcript']->buildFields());

            $aPOSTgenome = array();
            $aPOSTtranscript = array();

            foreach($aFieldsGenome as $key) {
                if (isset($_POST[$key])) {
                    $aPOSTgenome[$key] = $_POST[$key];
                }
            }

            foreach($aFieldsTranscript as $key) {
                if (isset($_POST[$key])) {
                    $aPOSTtranscript[$key] = $_POST[$key];
                }
            }

            $_DATA['Genome']->checkFields($aPOSTgenome);
            $_DATA['Transcript']->checkFields($aPOSTtranscript);

            $_POST = array_merge($aPOSTgenome, $aPOSTtranscript);

            if (!lovd_error()) {
                // Prepare values.
                $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
                $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
                $_POST['created_by'] = $_AUTH['id'];
                $_POST['created_date'] = date('Y-m-d H:i:s');

                $nID = $_DATA['Genome']->insertEntry($_POST, $aFieldsGenome);
                
                $_POST['id'] = $nID;
                $_POST['transcriptid'] = $_GET['transcriptid'];
                
                $_DATA['Transcript']->insertEntry($_POST, $aFieldsTranscript);

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created variant entry ' . $nID);

                // Add variant to screening.
                if (isset($_POST['screeningid'])) {
                    $q = lovd_queryDB_Old('INSERT INTO ' . TABLE_SCR2VAR . ' VALUES (?, ?)', array($_POST['screeningid'], $nID));
                    if (!$q) {
                        // Silent error.
                        lovd_writeLog('Error', LOG_EVENT, 'Variant entry could not be added to screening #' . $_POST['screeningid']);
                    }
                }

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nID);

                require ROOT_PATH . 'inc-top.php';
                lovd_printHeader(PAGE_TITLE);
                lovd_showInfoTable('Successfully created the variant entry!', 'success');

                require ROOT_PATH . 'inc-bot.php';
                exit;
            }

        } else {
            // Default values.
            $_DATA['Genome']->setDefaultValues();
            $_DATA['Transcript']->setDefaultValues();
        }



        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        if (GET) {
            print('      To create a new variant entry, please fill out the form below.<BR>' . "\n" .
                  '      <BR>' . "\n\n");
        }

        lovd_errorPrint();

        // Tooltip JS code.
        lovd_includeJS('inc-js-tooltip.php');
        lovd_includeJS('inc-js-custom_links.php');

        // Table.
        print('      <FORM action="' . CURRENT_PATH . '?create&amp;reference=Transcript&amp;transcriptid=' . $_GET['transcriptid'] . (isset($_POST['screeningid'])? '&amp;target=' . $_GET['target'] : '') . '" method="post">' . "\n");

        // Array which will make up the form table.
        $aForm = array_merge(
                     $_DATA['Genome']->getForm($_DATA['Transcript']),
                     array(
                            array('', '', 'submit', 'Create variant entry'),
                          ));
        lovd_viewForm($aForm);

        print('      </FORM>' . "\n\n");

        require ROOT_PATH . 'inc-bot.php';
        exit;





    } else {
        // URL: /variants?create
        // Select wether the you want to create a variant on the genome or on a transcript.
        define('LOG_EVENT', 'VariantCreate');
        define('PAGE_TITLE', 'Create a new variant entry');
        require ROOT_PATH . 'inc-top.php';
        lovd_printHeader(PAGE_TITLE);

        print('<TABLE class="data" border="0" cellpadding="0" cellspacing="2" width="300">' . "\n" .
              '  <TBODY>' . "\n" .
              '    <TR class="" style="cursor: pointer;" onclick="window.location=\'variants?create&reference=Genome' . (isset($_GET['target'])? '&target=' . $_GET['target'] : '') . '\'">' . "\n" .
              '      <TH><H5>Create a genomic variant &raquo;&raquo;</H5></TH>' . "\n" .
              '    </TR>' . "\n" .
              '    <TR class="" style="cursor: pointer;" onclick="$( \'#TranscriptViewList\' ).toggle();">' . "\n" .
              '      <TH><H5>Create a transcript variant &raquo;&raquo;</H5></TH>' . "\n" .
              '    </TR>' . "\n" .
              '  </TBODY>' . "\n" .
              '</TABLE>' . "\n\n");

        print ('<BR>' . "\n");

        require ROOT_PATH . 'class/object_transcripts.php';
        $_GET['page_size'] = 10;
        $_DATA = new LOVD_Transcript();
        $_DATA->sRowLink = 'variants?create&reference=Transcript&transcriptid=' . $_DATA->sRowID . (isset($_GET['target'])? '&target=' . $_GET['target'] : '');
        print('<DIV id="TranscriptViewList" style="display:none;">');
        $_DATA->viewList(false, array('id_', 'variants'), false, false, false);
        print('</DIV>');

        require ROOT_PATH . 'inc-bot.php';
        exit;
    }
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'edit') {
    // URL: /variants/0000000001?edit
    // Edit an entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Edit a variant entry');
    define('LOG_EVENT', 'VariantEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('allele', 'chromosome', 'ownerid', 'statusid', 'edited_by', 'edited_date'),
                            $_DATA->buildFields());

            // Prepare values.
            // FIXME; deze checks kloppen niet; een submitter's edit zal nu een variant per definitie verbergen en de owner wordt mogelijk ook gereset.
            //   De "else" in deze checks zou eigenlijk de $zData waardes moeten zijn, of beter nog, pas in de $aFields zetten bij level >= LEVEL_CURATOR.
            $_POST['ownerid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['ownerid'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_HIDDEN);
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited variant entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PATH_ELEMENTS[0] . '/' . $nID);

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully edited the variant entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
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
        print('      To edit a variant entry, please fill out the form below.<BR>' . "\n" .
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
                        array('', '', 'submit', 'Edit variant entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}





if (!empty($_PATH_ELEMENTS[1]) && ctype_digit($_PATH_ELEMENTS[1]) && ACTION == 'delete') {
    // URL: /variants/0000000001?delete
    // Drop specific entry.

    $nID = str_pad($_PATH_ELEMENTS[1], 10, '0', STR_PAD_LEFT);
    define('PAGE_TITLE', 'Delete variant entry #' . $nID);
    define('LOG_EVENT', 'VariantDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
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
            // This also deletes the entries in variants_on_transcripts.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted variant entry #' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants');

            require ROOT_PATH . 'inc-top.php';
            lovd_printHeader(PAGE_TITLE);
            lovd_showInfoTable('Successfully deleted the variant entry!', 'success');

            require ROOT_PATH . 'inc-bot.php';
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
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
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('Deleting variant entry', '', 'print', $nID),
                        'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete variant entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    require ROOT_PATH . 'inc-bot.php';
    exit;
}
?>
