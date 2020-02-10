<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2020-02-10
 * For LOVD    : 3.0-23
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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





class LOVD_Screening extends LOVD_Custom
{
    // This class extends the Custom class and it handles the Screenings.
    var $sObject = 'Screening';





    function __construct ()
    {
        // Default constructor.
        global $_AUTH, $_SETT;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT s.*, ' .
                               'GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ";") AS _genes, ' .
                               'uo.name AS owned_by_ ' .
                               'FROM ' . TABLE_SCREENINGS . ' AS s ' .
                               'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) ' .
                               'WHERE s.id = ? ' .
                               'GROUP BY s.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 's.*, ' .
                                           'i.statusid AS individual_statusid, ' .
                                           'GROUP_CONCAT(DISTINCT "=\"", s2g.geneid, "\"" SEPARATOR "|") AS search_geneid, ' .
                                           'IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT ' . ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? 's2v.variantid' : 'vog.id') . ')) AS variants_found_, ' .
                                           'uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';

        // Construct list of user IDs for current user and users who share access with him.
        $aOwnerIDs = array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL));
        $sOwnerIDsSQL = join(', ', $aOwnerIDs);

        $this->aSQLViewEntry['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                           ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' :
                                               'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by IN (' . $sOwnerIDsSQL . ')') . ')) ') .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (s.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (s.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 's.id';

        // SQL code for viewing the list of screenings
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          's.id AS screeningid, ' .
                                          'IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT ' . ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? 's2v.variantid' : 'vog.id') . ')) AS variants_found_, ' .
                                          'GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes, ' .
                                          'i.statusid, ' .
                                          'uo.name AS owned_by_, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner';
        $this->aSQLViewList['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                          ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' :
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by IN (' . $sOwnerIDsSQL . ')') . ')) ') .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id)';
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'individualid_' => 'Individual ID',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'variants_found_' => 'Variants found?',
                        'owned_by_' => 'Owner name',
                        'created_by_' => array('Created by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'created_date_' => array('Date created', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_by_' => array('Last edited by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_date_' => array('Date last edited', $_SETT['user_level_settings']['see_nonpublic_data']),
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'screeningid' => array(
                                    'view' => false,
                                    'db'   => array('s.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Screening ID', 110, 'style="text-align : right;"'),
                                    'db'   => array('s.id', 'ASC', true)),
                        'individualid' => array(
                                    'view' => array('Individual ID', 110, 'style="text-align : right;"'),
                                    'db'   => array('s.individualid', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'genes' => array(
                                    'view' => array('Genes screened', 20),
                                    'db'   => array('genes', 'ASC', 'TEXT')),
                        'variants_found_' => array(
                                    'view' => array('Variants found', 100, 'style="text-align : right;"'),
                                    'db'   => array('variants_found_', 'ASC', 'INT_UNSIGNED')),
                        'owned_by_' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'created_by' => array(
                                    'view' => false,
                                    'db'   => array('s.created_by', false, true)),
                      ));
        $this->sSortDefault = 'id';

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'owned_by',
                      );
        parent::checkFields($aData, $zData, $aOptions);

        if (!empty($aData['genes']) && is_array($aData['genes'])) {
            if (count($aData['genes']) > 15) {
                lovd_errorAdd('genes', 'Please select no more than 15 genes. For genome-wide analysis, <B>no</B> genes should be selected.');
            }
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        global $_AUTH, $_DB, $nID;

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aSelectOwner = $_DB->query('SELECT id, CONCAT(name, " (#", id, ")") as name_id FROM ' . TABLE_USERS .
                ' ORDER BY name')->fetchAllCombine();
            $aFormOwner = array('Owner of this data', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false);
        } else {
            $aFormOwner = array();
        }

        // Get list of genes.
        $aGenesForm = $_DB->query('SELECT id, name FROM ' . TABLE_GENES . ' ORDER BY id')->fetchAllCombine();
        $nData = count($aGenesForm);
        foreach ($aGenesForm as $sID => $sGene) {
            $aGenesForm[$sID] = $sID . ' (' . lovd_shortenString($sGene, 50) . ')';
        }
        if (!$nData) {
            $aGenesForm = array('' => 'No gene entries available');
        }
        $nFieldSize = (count($aGenesForm) < 10? count($aGenesForm) : 10);

        // FIXME; right now two blocks in this array are put in, and optionally removed later. However, the if() above can build an entire block, such that one of the two big unset()s can be removed.
        // A similar if() to create the "authorization" block, or possibly an if() in the building of this form array, is easier to understand and more efficient.
        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '35%', '14', '65%'),
                        array('', '', 'print', '<B>Screening information</B>'),
                        'hr',
                      ),
                 $this->buildForm(),
                 array(
            'aGenes' => array('Genes screened', '', 'select', 'genes', $nFieldSize, $aGenesForm, false, true, false),
                        array('', '', 'note', 'Please select no more than 15 genes. For genome-wide analysis, <B>no</B> genes should be selected.'),
    'variants_found' => array('Have variants been found?', 'Please uncheck this box when no variants have been found using this screening.', 'checkbox', 'variants_found'),
                        'hr',
      'general_skip' => 'skip',
           'general' => array('', '', 'print', '<B>General information</B>'),
       'general_hr1' => 'hr',
             'owner' => $aFormOwner,
       'general_hr2' => 'hr',
                        'skip',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      ));

        if (ACTION == 'create' || (ACTION == 'publish' && GET)) {
            // When creating, or when publishing without any changes, unset the authorization.
            unset($this->aFormData['authorization']);
        } elseif (lovd_getProjectFile() != '/import.php') {
            if ($_DB->query('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchColumn()) {
                unset($this->aFormData['variants_found']);
            };
        }
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            unset($this->aFormData['general_skip'], $this->aFormData['general'], $this->aFormData['general_hr1'], $this->aFormData['owner'], $this->aFormData['general_hr2']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            $zData['individualid_'] = '<A href="individuals/' . $zData['individualid'] . '">' . $zData['individualid'] . '</A>';
            if ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']) {
                $zData['individualid_'] .= ' <SPAN style="color : #' . $this->getStatusColor($zData['individual_statusid']) . '">(' . $_SETT['data_status'][$zData['individual_statusid']] . ')</SPAN>';
            }
        }
        $zData['variants_found_'] = ($zData['variants_found_'] == -1? 'Not yet submitted' : $zData['variants_found_']);

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;

        $_POST['variants_found'] = '1';
        $_POST['owned_by'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
