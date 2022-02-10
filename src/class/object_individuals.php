<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2022-02-10
 * For LOVD    : 3.0-28
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
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





class LOVD_Individual extends LOVD_Custom
{
    // This class extends the Custom class and it handles the Individuals.
    var $sObject = 'Individual';





    function __construct ()
    {
        // Default constructor.
        global $_AUTH, $_DB, $_SETT;

        // SQL code for preparing load entry query.
        // Increase DB limits to allow concatenation of large number of disease IDs.
        $this->sSQLPreLoadEntry = 'SET group_concat_max_len = 200000';

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT i.*, ' .
                               'uo.name AS owned_by_, ' .
                               'GROUP_CONCAT(DISTINCT i2d.diseaseid ORDER BY i2d.diseaseid SEPARATOR ";") AS _active_diseases ' .
                               'FROM ' . TABLE_INDIVIDUALS . ' AS i ' .
                               'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                               'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) ' .
                               'WHERE i.id = ? ' .
                               'GROUP BY i.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, ' .
                                           'IFNULL(NULLIF(i.license, ""), uc.default_license) AS license, ' .
                                           'GROUP_CONCAT(DISTINCT d.id SEPARATOR ";") AS _diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT p.diseaseid SEPARATOR ";") AS _phenotypes, ' .
                                           'GROUP_CONCAT(DISTINCT s.id SEPARATOR ";") AS _screeningids, ' .
                                           'uo.name AS owned_by_, CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ' .
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
                                          (LOVD_plus? '' :
                                              'GROUP_CONCAT(DISTINCT s2g.geneid ORDER BY s2g.geneid SEPARATOR ", ") AS genes_screened_, ' .
                                              'GROUP_CONCAT(DISTINCT t.geneid ORDER BY t.geneid SEPARATOR ", ") AS variants_in_genes_, ' .
                                              'COUNT(DISTINCT ' . ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? 's2v.variantid' : 'vog.id') . ') AS variants_, ' // Counting s2v.variantid will not include the limit opposed to vog in the join's ON() clause.
                                          ) .
                                          'uo.name AS owned_by_, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner, ' .
                                          'ds.name AS status';

        if ($_AUTH) {
            // Construct list of user IDs for current user and users who share access with them.
            $aOwnerIDs = array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL));
            $sOwnerIDsSQL = join(', ', $aOwnerIDs);
        } else {
            $sOwnerIDsSQL = '';
        }

        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                          (LOVD_plus? '' :
                                              'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
                                              ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' :
                                                  'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by IN (' . $sOwnerIDsSQL . ')') . ')) ') .
                                              'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (' . ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? 's2v.variantid' : 'vog.id') . ' = vot.id) ' .
                                              'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid) '
                                          ) .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS ds ON (i.statusid = ds.id)';
        // Conditional inclusion of a JOIN that is only needed to search. This prevents long delays when using HAVING on
        //  the Individual's Disease column. We can't just use a WHERE on the same JOIN, as it will limit the results,
        //  meaning no diseases other than the one searched for will be shown. This extra join should fix that.
        // If we need this more often, we should handle it more gracefully.
        if (!empty($_GET['search_diseaseids_searched'])) {
            $this->aSQLViewList['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d_s ON (i.id = i2d_s.individualid)';
            // The search field will be defined below.
        }

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
                        'status' => array('Individual data status', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'license_' => 'Database submission license',
                        'created_by_' => 'Created by',
                        'created_date_' => array('Date created', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_by_' => array('Last edited by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_date_' => array('Date last edited', $_SETT['user_level_settings']['see_nonpublic_data']),
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'individualid' => array(
                                    'view' => false,
                                    'db'   => array('i.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Individual ID', 110, 'style="text-align : right;"'),
                                    'db'   => array('i.id', 'ASC', true)),
                        'panelid' => array(
                                    'view' => array('Panel ID', 70, 'style="text-align : right;"'),
                                    'db'   => array('i.panelid', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'diseaseids' => array(
                                    'view' => array('Disease ID', 0),
                                    'db'   => array('diseaseids', false, true)),
                        'diseaseids_searched' => array( // Special, optionally included joined table.
                                    'view' => false,
                                    'db'   => array('i2d_s.diseaseid', false, 'INT_UNSIGNED')),
                        'diseases_' => array(
                                    'view' => array('Disease', 175),
                                    'db'   => array('diseases_', 'ASC', true)),
                 ),
                 (LOVD_plus? array() : array(
                        'phenotypes_' => array(
                                    'view' => false), // Placeholder for Phenotype/Additional.
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
                                    'view' => array('Variants', 75, 'style="text-align : right;"'),
                                    'db'   => array('variants_', 'DESC', 'INT_UNSIGNED')),
                 )),
                 array(
                        'panel_size' => array(
                                    'view' => array('Panel size', 70, 'style="text-align : right;"'),
                                    'db'   => array('i.panel_size', 'DESC', true),
                                    'legend' => array('Number of individuals this entry ' .
                                        'represents; e.g. 1 for an individual, 5 for a family ' .
                                        'with 5 affected members.')),
                        'owned_by_' => array(
                                    'view' => array('Owner', 160),
                                    'db'   => array('uo.name', 'ASC', true)),
                        'owner_countryid' => array(
                                    'view' => false,
                                    'db'   => array('uo.countryid', 'ASC', true)),
                        'status' => array(
                                    'view' => array('Status', 70),
                                    'db'   => array('ds.name', false, true),
                                    'auth' => $_SETT['user_level_settings']['see_nonpublic_data']),
                        'created_by' => array(
                                    'view' => false,
                                    'db'   => array('i.created_by', false, true)),
                      ));
        $this->sSortDefault = 'id';

        // For installations with Phenotype/Additional enabled, link to that column as well and show it,
        //  so users can quickly identify individuals with certain features.
        // Simplest is to check for ourselves, so we don't need to initiate any object.
        // FIXME: Should this be replaced by a CustomVL, with Ind joined to Phenotypes to create this?
        // FIXME: This Ind VL has more than that Ind VL, though.
        if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = ?', array('Phenotype/Additional'))->fetchColumn()) {
            // Column is active, include in SELECT, JOIN and the column list.
            $this->aSQLViewList['SELECT'] .= ', GROUP_CONCAT(DISTINCT p.`Phenotype/Additional` ORDER BY p.`Phenotype/Additional` SEPARATOR ", ") AS phenotypes_';

            $this->aSQLViewList['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_PHENOTYPES . ' AS p ON (i.id = p.individualid';
            if (!$_AUTH || $_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) { // This check assumes lovd_isAuthorized() has already been called for gene-specific overviews.
                $this->aSQLViewList['FROM'] .= ' AND (p.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR (p.created_by = "' . $_AUTH['id'] . '" OR p.owned_by IN (' . join(', ', array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL))) . '))') . ')';
            }
            $this->aSQLViewList['FROM'] .= ')';

            $this->aColumnsViewList['phenotypes_'] = array(
                'view' => array('Phenotype details', 200),
                'db' => array('p.`Phenotype/Additional`', 'ASC', true),
            );
        }

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
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

        // Check the 'active_diseases' field only when not importing.
        if (!$bImport) {
            $this->aCheckMandatory[] = 'active_diseases';
        }

        // Checks fields before submission of data.
        parent::checkFields($aData, $zData, $aOptions);

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

        if (!empty($aData['active_diseases'])) {
            $aDiseases = array_keys($this->aFormData['aDiseases'][5]);
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

        // LOVD+: Don't allow individuals with an identical Lab-ID.
        // Can't enforce this in the table, because it's a custom column, so I'll just do it like this.
        if (LOVD_plus && !empty($aData['Individual/Lab_ID'])) {
            if ($zData && isset($zData['id'])) {
                $r = $_DB->query('SELECT id, created_date
                                  FROM ' . TABLE_INDIVIDUALS . '
                                  WHERE `Individual/Lab_ID` = ? AND id != ?',
                        array($aData['Individual/Lab_ID'], $zData['id']))->fetchRow();
            } else {
                $r = $_DB->query('SELECT id, created_date
                                  FROM ' . TABLE_INDIVIDUALS . '
                                  WHERE `Individual/Lab_ID` = ?',
                        array($aData['Individual/Lab_ID']))->fetchRow();
            }
            if ($r) {
                list($nID, $sCreatedDate) = $r;
                // NOTE: Do not just change this error message, we are parsing for it in ajax/import_scheduler.php.
                lovd_errorAdd('Individual/Lab_ID',
                    'Another individual with this Lab ID already exists in the database; #' . $nID . ', imported ' . $sCreatedDate . '.');
            }
        }

        lovd_checkXSS($aData);
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
            $aSelectOwner = $_DB->query('SELECT id, CONCAT(name, " (#", id, ")") as name_id FROM ' . TABLE_USERS .
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
                        array('POST', '', '', '', '35%', '14', '65%'),
                        array('', '', 'print', '<B>Individual information</B>'),
                        'hr',
                      ),
                 $this->buildForm(),
                 array(
                        array('Panel size', '', 'text', 'panel_size', 10),
                        array('', '', 'note', 'The number of individuals this entry represents; e.g.' .
                            ' 1 for an individual, 5 for a family with 5 affected members. To ' .
                            'report different Individuals from one family, link them using the ' .
                            '"ID of panel this entry belongs to" field.'),
           'panelid' => array('ID of panel this entry belongs to (optional)', 'Different individuals can be linked together. To link, specify here the ID of a previously submitted panel, i.e. an individual with a panel size larger than 1, that this individual belongs to (Optional).', 'text', 'panelid', 10),
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
        if (LOVD_plus) {
            unset($this->aFormData['panelid']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_SETT;

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

            // Tool to set/change the license.
            // Provide this link only here, because here we can reload the Individual VE.
            if (lovd_isAuthorized('individual', $zData['id'])) {
                $zData['license_'] .= '<SPAN style="float:right;">(<A href="#" onclick="$.get(\'ajax/licenses.php/individual/' . $zData['id'] . '?edit\').fail(function(){alert(\'Error viewing license information, please try again later.\');}); return false;">Change</A>)</SPAN>';
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
