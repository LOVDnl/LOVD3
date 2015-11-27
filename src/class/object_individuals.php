<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2015-11-27
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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





class LOVD_Individual extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Individual';
    var $bShared = false;





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        // FIXME; change owner to owned_by_ in the load entry query below.
        $this->sSQLLoadEntry = 'SELECT i.*, ' .
                               'uo.name AS owner, ' .
                               'GROUP_CONCAT(DISTINCT i2d.diseaseid ORDER BY i2d.diseaseid SEPARATOR ";") AS active_diseases_ ' .
                               'FROM ' . TABLE_INDIVIDUALS . ' AS i ' .
                               'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) ' .
                               'WHERE i.id = ? ' .
                               'GROUP BY i.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, ' .
                                           'GROUP_CONCAT(DISTINCT d.id SEPARATOR ";") AS _diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT p.diseaseid SEPARATOR ";") AS _phenotypes, ' .
                                           'GROUP_CONCAT(DISTINCT s.id SEPARATOR ";") AS _screeningids, ' .
                                           'uo.id AS owner, ' .
                                           'uo.name AS owned_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (i.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'i.id AS individualid, ' .
                                          'GROUP_CONCAT(DISTINCT d.id) AS diseaseids, ' .
                                        // FIXME; Can we get this order correct, such that diseases without abbreviation nicely mix with those with? Right now, the diseases without symbols are in the back.
                                          'GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, ' .
                                          'GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ", ") AS genes_screened_, ' .
                                          'GROUP_CONCAT(DISTINCT t.geneid ORDER BY t.geneid SEPARATOR ", ") AS variants_in_genes_, ' .
                                          'COUNT(DISTINCT ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ') AS variants_, ' . // Counting s2v.variantid will not include the limit opposed to vog in the join's ON() clause.
                                          'uo.name AS owned_by_, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ' .
                                          ($_AUTH['level'] < LEVEL_COLLABORATOR? '' :
                                              'CASE ds.id WHEN ' . STATUS_MARKED . ' THEN "marked" WHEN ' . STATUS_HIDDEN .' THEN "del" WHEN ' . STATUS_PENDING .' THEN "del" END AS class_name,') .
                                          'ds.name AS status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
                                          ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' :
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by = "' . $_AUTH['id'] . '"') . ')) ') .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ' = vot.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'i.id';

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'panelid_' => 'Panel ID',
                        'panel_size' => 'Panel size',
                        'diseases_' => 'Diseases',
                        'parents_' => 'Parent(s)',
                        'owned_by_' => 'Owner name',
                        'status' => array('Individual data status', LEVEL_COLLABORATOR),
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'individualid' => array(
                                    'view' => false,
                                    'db'   => array('i.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Individual ID', 110),
                                    'db'   => array('i.id', 'ASC', true)),
                        'panelid' => array(
                                    'view' => array('Panel ID', 70),
                                    'db'   => array('i.panelid', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'diseaseids' => array(
                                    'view' => array('Disease ID', 0),
                                    'db'   => array('diseaseids', false, true)),
                        'diseases_' => array(
                                    'view' => array('Disease', 175),
                                    'db'   => array('diseases_', 'ASC', true)),
                        'genes_searched' => array(
                                    'view' => false,
                                    'db'   => array('t.geneid', false, true)),
                        'genes_screened_' => array(
                                    'view' => array('Genes screened', 175),
                                    'db'   => array('genes_screened_', false, true)),
                        'variants_in_genes_' => array(
                                    'view' => array('Variants in genes', 175),
                                    'db'   => array('variants_in_genes_', false, true),
                                    'legend' => array('The individual has variants for this gene.')),
                        'variants_' => array(
                                    'view' => array('Variants', 75),
                                    'db'   => array('variants_', 'DESC', 'INT_UNSIGNED')),
                        'panel_size' => array(
                                    'view' => array('Panel size', 70),
                                    'db'   => array('i.panel_size', 'DESC', true),
                                    'legend' => array('How many individuals does this entry represent?')),
                        'owned_by_' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'owner_countryid' => array(
                                    'view' => false,
                                    'db'   => array('uo.countryid', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true),
                                    'auth' => LEVEL_COLLABORATOR),
                      ));
        $this->sSortDefault = 'id';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function checkFields ($aData, $zData = false)
    {
        global $_DB;

        // During import panelid, fatherid and motherid are checked in import.php.
        $bImport = (lovd_getProjectFile() == '/import.php');

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'panel_size',
                        'owned_by',
                        'statusid',
                      );

        // Checks fields before submission of data.
        parent::checkFields($aData);

        foreach (array('fatherid', 'motherid') as $sParentalField) {
            // This is not yet implemented correctly. These checks are implemented correctly in import.php in section "Individuals".
            if (isset($aData[$sParentalField]) && ctype_digit($aData[$sParentalField]) && !$bImport) {
                // FIXME: Also check gender!!! Check if field is available, download value (or '' if not available), then check possible conflicts.
                // Partially, the code is already written below.
                $nParentID = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($aData[$sParentalField]))->fetchColumn();
                if (empty($nParentID)) {
                    // FIXME: Once we have this on the form, replace with form description.
                    lovd_errorAdd($sParentalField, 'No individual found with this \'' . $sParentalField . '\'.');
                } elseif ($sParentalField == 'fatherid' && false) {
                    lovd_errorAdd($sParentalField, 'The \'' . $sParentalField . '\' you entered does not refer to a male individual.');
                } elseif ($sParentalField == 'motherid' && false) {
                    lovd_errorAdd($sParentalField, 'The \'' . $sParentalField . '\' you entered does not refer to a female individual.');
                } elseif ($aData[$sParentalField] == $this->nID) {
                    lovd_errorAdd($sParentalField, 'The \'' . $sParentalField . '\' can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual.');
                }
            } elseif (!empty($aData[$sParentalField]) && !ctype_digit($aData[$sParentalField])) {
                // FIXME: Normally we don't have to check this, because objects.php is taking care of this.
                // But as long as the field is not defined on the form, there is no check.
                lovd_errorAdd($sParentalField, 'The \'' . $sParentalField . '\' must contain a positive integer.');
            }
        }

        // Changes in these checks should also be implemented in import.php in section "Individuals"
        if (isset($aData['panelid']) && ctype_digit($aData['panelid']) && !$bImport) {
            $nPanel = $_DB->query('SELECT panel_size FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($aData['panelid']))->fetchColumn();
            if (empty($nPanel)) {
                lovd_errorAdd('panelid', 'No Panel found with this \'Panel ID\'.');
            } elseif ($nPanel == 1) {
                lovd_errorAdd('panelid', 'The \'Panel ID\' you entered refers to an individual, not a panel (group of individuals). If you want to configure that individual as a panel, set its \'Panel size\' field to a value higher than 1.');
            } elseif ($nPanel <= $aData['panel_size']) {
                lovd_errorAdd('panel_size', 'The entered \'Panel size\' must be lower than the \'Panel size\' of the panel you refer to with the entered \'Panel ID\'.');
            } elseif ($aData['panelid'] == $this->nID) {
                lovd_errorAdd('panel_size', 'The \'Panel ID\' can not link to itself; this field is used to indicate which group of individuals (\'panel\') this entry belongs to.');
            }
        }

        $aDiseases = array_keys($this->aFormData['aDiseases'][5]);
        if (!empty($aData['active_diseases'])) {
            if (count($aData['active_diseases']) > 1 && in_array('00000', $aData['active_diseases'])) {
                lovd_errorAdd('active_diseases', 'You cannot select both "Healthy/Control" and a disease for the same individual entry.');
            } else {
                foreach ($aData['active_diseases'] as $nDisease) {
                    if ($nDisease && !in_array($nDisease, $aDiseases)) {
                        lovd_errorAdd('active_diseases', htmlspecialchars($nDisease) . ' is not a valid disease.');
                    }
                }
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

        global $_AUTH, $_DB, $_SETT;

        // Get list of diseases.
        $aDiseasesForm = $_DB->query('SELECT id, IF(CASE symbol WHEN "-" THEN "" ELSE symbol END = "", name, CONCAT(symbol, " (", name, ")")) FROM ' . TABLE_DISEASES . ' ORDER BY (id > 0), (symbol != "" AND symbol != "-") DESC, symbol, name')->fetchAllCombine();
        $nDiseases = count($aDiseasesForm);
        foreach ($aDiseasesForm as $nID => $sDisease) {
            $aDiseasesForm[$nID] = lovd_shortenString($sDisease, 75);
        }
        $nFieldSize = ($nDiseases < 15? $nDiseases : 15);
        if (!$nDiseases) {
            $aDiseasesForm = array('' => 'No disease entries available');
            $nFieldSize = 1;
        }

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aSelectOwner = $_DB->query('SELECT id, name FROM ' . TABLE_USERS .
                (ACTION == 'edit' && (int) $_POST['owned_by'] === 0? '' : ' WHERE id > 0') .
                ' ORDER BY name')->fetchAllCombine();
            $aFormOwner = array('Owner of this data', '', 'select', 'owned_by', 1, $aSelectOwner, false, false, false);
            $aSelectStatus = $_SETT['data_status'];
            if (lovd_getProjectFile() == '/import.php') {
                // During an import the status pending is allowed, therefore only status in progress is unset.
                unset($aSelectStatus[STATUS_IN_PROGRESS]);
            } else {
                unset($aSelectStatus[STATUS_PENDING], $aSelectStatus[STATUS_IN_PROGRESS]);
            }
            $aFormStatus = array('Status of this data', '', 'select', 'statusid', 1, $aSelectStatus, false, false, false);
        } else {
            $aFormOwner = array();
            $aFormStatus = array();
        }

        // FIXME; right now two blocks in this array are put in, and optionally removed later. However, the if() above can build an entire block, such that one of the two big unset()s can be removed.
        // A similar if() to create the "authorization" block, or possibly an if() in the building of this form array, is easier to understand and more efficient.
        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Individual information</B>'),
                        'hr',
                      ),
                 $this->buildForm(),
                 array(
                        array('Panel size', '', 'text', 'panel_size', 10),
                        array('', '', 'note', 'Fill in how many individuals this entry represents (default: 1).'),
                        array('ID of panel this entry belongs to (optional)', 'Fill in LOVD\'s individual ID of the group to which this individual or group of individuals belong to (Optional).', 'text', 'panelid', 10),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        'hr',
         'aDiseases' => array('This individual has been diagnosed with these diseases', '', 'select', 'active_diseases', $nFieldSize, $aDiseasesForm, false, true, false),
     'diseases_info' => array('', '', 'note', ($nDiseases < 25? '' : '<A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'diseases?&amp;no_links&amp;in_window\', \'Diseases\', 1000, 550); return false;">Find the used disease abbreviation in the list of diseases</A>.<BR>') . 'Diseases not in this list are not yet configured in this LOVD. If any disease you would like to select is not in here, please mention this in the remarks, preferably including the omim number. This way, a manager can configure this disease in this LOVD.'),
   'diseases_create' => array('', '', 'note', ($nDiseases < 25? '' : '<A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'diseases?&amp;no_links&amp;in_window\', \'Diseases\', 1000, 550); return false;">Find the used disease abbreviation in the list of diseases</A>.<BR>') . 'Diseases not in this list are not yet configured in this LOVD.<BR>Do you want to <A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'diseases?create&amp;in_window\', \'DiseasesCreate\', 800, 550); return false;">configure more diseases</A>?'),
                     'hr',
      'general_skip' => 'skip',
           'general' => array('', '', 'print', '<B>General information</B>'),
       'general_hr1' => 'hr',
             'owner' => $aFormOwner,
            'status' => $aFormStatus,
       'general_hr2' => 'hr',
                        'skip',
      'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      ));

        if (ACTION == 'create' || (ACTION == 'publish' && GET)) {
            unset($this->aFormData['authorization']);
        }
        if ($_AUTH['level'] < LEVEL_CURATOR) {
            unset($this->aFormData['general_skip'], $this->aFormData['general'], $this->aFormData['general_hr1'], $this->aFormData['owner'], $this->aFormData['status'], $this->aFormData['general_hr2']);
        }
        if ($_AUTH['level'] < LEVEL_MANAGER) {
            unset($this->aFormData['diseases_create']);
        } else {
            unset($this->aFormData['diseases_info']);
        }

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

        if ($sView == 'entry') {
            if (!empty($zData['Individual/Age_of_death']) && preg_match('/^([<>])?(\d+y)(\d+m)?(\d+d)?(\?)?$/', htmlspecialchars_decode($zData['Individual/Age_of_death']), $aMatches)) {
                $aMatches = $aMatches + array_fill(0, 5, ''); // Fill $aMatches with enough values.
                $nYears = (int) $aMatches[2];
                $nMonths = (int) $aMatches[3];
                $nDays = (int) $aMatches[4];
                $sAge  = (!$nYears? '' : $nYears . ' year' . ($nYears == 1? '' : 's'));
                $sAge .= (!$nMonths? '' : ($sAge? ', ' : '') . $nMonths . ' month' . ($nMonths == 1? '' : 's'));
                $sAge .= (!$nDays? '' : ($sAge? ', ' : '') . $nDays . ' day' . ($nDays == 1? '' : 's'));
                $zData['Individual/Age_of_death'] .= ' (' . (!$aMatches[1]? '' : ($aMatches[1] == '>'? 'later than' : 'before') . ' ') . (empty($aMatches[5])? '' : 'approximately ') . $sAge . ')';
            }
            // Hide Panel ID if not applicable.
            if (empty($zData['panelid'])) {
                unset($this->aColumnsViewEntry['panelid_']);
            } else {
                $zData['panelid_'] = '<A href="individuals/' . $zData['panelid'] . '">' . $zData['panelid'] . '</A>';
            }
            // Associated with diseases...
            $zData['diseases_'] = '';
            foreach($zData['diseases'] as $aDisease) {
                list($nID, $sSymbol, $sName) = $aDisease;
                $zData['diseases_'] .= (!$zData['diseases_']? '' : ', ') . '<A href="diseases/' . $nID . '" title="' . $sName . '">' . $sSymbol . '</A>';
            }
            // Parents...
            if (empty($zData['fatherid']) && empty($zData['motherid'])) {
                unset($this->aColumnsViewEntry['parents_']);
            } else {
                if ($zData['fatherid']) {
                    $zData['parents_'] = '<A href="individuals/' . $zData['fatherid'] . '">Father</A>';
                }
                if ($zData['motherid']) {
                    $zData['parents_'] .= (empty($zData['parents_'])? '' : ', ') . '<A href="individuals/' . $zData['motherid'] . '">Mother</A>';
                }
            }
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;

        $_POST['panel_size'] = 1;
        $_POST['statusid'] = STATUS_OK;
        $_POST['owned_by'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
