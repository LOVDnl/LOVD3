<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2011-05-26
 * For LOVD    : 3.0-alpha-01
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/object_custom.php';





class LOVD_Screening extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Screening';
    var $bShared = false;





    function LOVD_Screening ()
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT s.*, ' .
        // FIXME; kijkend hiernaar realiseer ik me, dat ik misschien "ownerid" beter "owned_by" had kunnen noemen... Wat denk jij?
        // FIXME; helemaal mee eens...
                               'uo.name AS owner ' .
                               'FROM ' . TABLE_SCREENINGS . ' AS s ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id) ' .
                               'WHERE s.id = ? ' .
                               'GROUP BY s.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 's.*, ' .
                                            // FIXME; ik moest even zoeken waar deze voor waren; misschien kunnen we ze beter hernoemen naar "search_geneid" ofzo?
                                           'GROUP_CONCAT("=\"", s2g.geneid, "\"" SEPARATOR "|") AS geneids, ' .
                                           'GROUP_CONCAT(DISTINCT s2v.variantid SEPARATOR "|") AS variantids, ' .
                                           'uo.name AS owner, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (s.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (s.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 's.id';

        // SQL code for viewing the list of screenings
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          'COUNT(DISTINCT s2v.variantid) AS variants, ' .
                                          'uo.name AS owner';
        $this->aSQLViewList['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.ownerid = uo.id)';
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        // Run parent constructor to find out about the custom columns.
        parent::LOVD_Custom();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'individualid_' => 'Individual ID',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'owner_' => 'Owner name',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'id' => array(
                                    'view' => array('Screening ID', 110),
                                    'db'   => array('s.id', 'ASC', true)),
                        'individualid' => array(
                                    'view' => array('Individual ID', 110),
                                    'db'   => array('s.individualid', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'variants' => array(
                                    'view' => array('Variants found', 120),
                                    'db'   => array('variants', 'ASC', 'INT_UNSIGNED')),
                        'owner' => array(
                                    'view' => array('Owner', 200),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'created_date' => array(
                                    'view' => array('Date created', 130),
                                    'db'   => array('s.created_date', 'ASC', true)),
                        'edited_date' => array(
                                    'view' => array('Date edited', 130),
                                    'db'   => array('s.edited_date', 'ASC', true)),
                      ));
        $this->sSortDefault = 'id';
        parent::LOVD_Object();
    }





    function checkFields ($aData)
    {
        global $_AUTH;

        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
        }

        // FIXME; if empty, should be removed perhaps?
        // Mandatory fields.
        $this->aCheckMandatory =
                 array(

                      );
        parent::checkFields($aData);

        // FIXME; Ik stel voor om de ownerid/owned_by checks in object_custom.php te doen, scheelt het herhalen van de code in iedere checkFields().
        if (isset($_POST['ownerid'])) {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE id=?', array($_POST['ownerid']));
                if (!$q) {
                    lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
                }
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
            }
        } else {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                lovd_errorAdd('ownerid' ,'Not allowed to change \'Owner of this individual\'.');
            }
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH;
        
        $aSelectOwner = array();

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            // FIXME; select * is overkill, en hier moet een ORDER BY komen. Level ook laten zien?
            $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS);
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aFormOwner = array('Owner of this screening', '', 'select', 'ownerid', 1, $aSelectOwner, false, false, false);
        } else {
            $aFormOwner = array('Owner of this screening', '', 'print', '<B>' . $_AUTH['name'] . '</B>');
        }

        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Screening information</B>'),
                        'hr',
                      ),
                 $this->buildViewForm(),
                 array(
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        $aFormOwner,
                        'hr',
                        'skip',
                      ));

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'screenings/' . rawurlencode($zData['id']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            // FIXME; ik bedenk me nu, dat deze aanpassingen zo klein zijn, dat ze ook in MySQL al gedaan kunnen worden. Wat denk jij?
            $zData['individualid_'] = '<A href="individuals/' . $zData['individualid'] . '">' . $zData['individualid'] . '</A>';
            $zData['owner_'] = '<A href="users/' . $zData['ownerid'] . '">' . $zData['owner'] . '</A>';
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;

        $_POST['ownerid'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
