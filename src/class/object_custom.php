<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-17
 * Modified    : 2021-07-27
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
require_once ROOT_PATH . 'class/objects.php';





class LOVD_Custom extends LOVD_Object
{
    // This class extends the basic Object class and it handles all Custom objects, serving as a parent class.
    var $sObject = 'Custom';
    var $sCategory = '';
    var $aColumns = array();
    var $aCustomLinks = array();
    var $sObjectID = '';
    var $nID = '';





    function __construct ()
    {
        // Default constructor.
        global $_AUTH, $_DB, $_SETT;

        $aArgs = array();

        $this->sCategory = (empty($this->sCategory)? $this->sObject : $this->sCategory);
        $aTableInfo = lovd_getTableInfoByCategory($this->sCategory);

        if (empty($aTableInfo['shared'])) {
            // "Simple", non-shared, data types (individuals, genomic variants, screenings).
            $sSQL = 'SELECT c.*, ac.* ' .
                    'FROM ' . TABLE_ACTIVE_COLS . ' AS ac ' .
                    'LEFT OUTER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) ' .
                    'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                    'ORDER BY c.col_order';
        } else {
            // Shared data type (variants on transcripts, phenotypes).
            if ($this->sObjectID) {
                // Parent object given (a gene for variants, a disease for phenotypes).
                if ($this->sObject == 'Phenotype') {
                    $sSQL = 'SELECT c.*, sc.* ' .
                            'FROM ' . TABLE_COLS . ' AS c ' .
                            'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                            'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                            'AND sc.diseaseid = ? ' .
                            'ORDER BY sc.col_order, sc.colid';
                    $aArgs[] = $this->sObjectID;
                } elseif ($this->sObject == 'Transcript_Variant') {
                    $aArgs = explode(',', $this->sObjectID);
                    $sSQL = 'SELECT c.*, sc.* ' .
                            'FROM ' . TABLE_COLS . ' AS c ' .
                            'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                            'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                            'AND sc.geneid IN (?' . str_repeat(', ?', count($aArgs) - 1) . ') ' .
                            'ORDER BY sc.col_order, sc.colid';
                }
            } elseif ($this->nID) {
                // FIXME; kan er niet wat specifieke info in de objects (e.g. object_phenotypes) worden opgehaald, zodat dit stukje hier niet nodig is?
                if ($this->sObject == 'Phenotype') {
                    $sSQL = 'SELECT c.*, sc.*, p.id AS phenotypeid ' .
                            'FROM ' . TABLE_COLS . ' AS c ' .
                            'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                            'INNER JOIN ' . TABLE_PHENOTYPES . ' AS p USING (diseaseid) ' .
                            'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                            'AND p.id = ? ' .
                            'ORDER BY sc.col_order';
                } elseif ($this->sObject == 'Transcript_Variant') {
                    $sSQL = 'SELECT c.*, sc.*, vot.id AS variantid ' .
                            'FROM ' . TABLE_COLS . ' AS c ' .
                            'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                            'INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t USING (geneid) ' .
                            'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) ' .
                            'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                            'AND vot.id = ? ' .
                            'ORDER BY sc.col_order';
                }
                $aArgs[] = $this->nID;
            } else {
                $sSQL = 'SELECT c.*, c.id AS colid ' .
                        'FROM ' . TABLE_COLS . ' AS c ' .
                        'WHERE c.id LIKE "' . $this->sCategory . '/%" ' .
                        'ORDER BY c.col_order';
            }
        }
        $q = $_DB->query($sSQL, $aArgs);
        while ($z = $q->fetchAssoc()) {
            $z['custom_links'] = array();
            $z['form_type'] = explode('|', $z['form_type']);
            // Modify form_type to include full legend text in second index of form_type.
            if (!empty($z['description_legend_full'])) {
                $z['form_type'][1] .= (empty($z['form_type'][1])? '' : '<BR>') . '<B>Legend: </B>' . str_replace(array("\r", "\n"), '', $z['description_legend_full']);
            }

            $z['select_options'] = explode("\r\n", $z['select_options']);
            $this->aColumns[$z['id']] = $z;
        }



        // Gather the custom link information.
        // 2015-01-23; 3.0-13; But not when importing, then we don't need this at all.
        if (lovd_getProjectFile() != '/import.php') {
            $aLinks = $_DB->query('SELECT l.*, GROUP_CONCAT(c2l.colid SEPARATOR ";") AS colids FROM ' . TABLE_LINKS . ' AS l INNER JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid) WHERE c2l.colid LIKE ? GROUP BY l.id',
                array($this->sCategory . '/%'))->fetchAllAssoc();
            foreach ($aLinks as $aLink) {
                $aLink['regexp_pattern'] = '/' . str_replace(array('{', '}'), array('\{', '\}'), preg_replace('/\[\d\]/', '([^:]*)', $aLink['pattern_text'])) . '/';
                $aLink['replace_text'] = preg_replace('/\[(\d)\]/', '\$$1', $aLink['replace_text']);
                $aCols = explode(';', $aLink['colids']);
                foreach ($aCols as $sColID) {
                    if (isset($this->aColumns[$sColID])) {
                        $this->aColumns[$sColID]['custom_links'][] = $aLink['id'];
                    }
                }
                $this->aCustomLinks[$aLink['id']] = $aLink;
            }
        }

        parent::__construct();

        // Hide entries that are not marked or public.
        if ($_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) { // This check assumes lovd_isAuthorized() has already been called for gene-specific overviews.
            if (in_array($this->sCategory, array('VariantOnGenome', 'VariantOnTranscript'))) {
                $sAlias = 'vog';
            } else {
                $sAlias = strtolower($this->sCategory{0});
            }

            // Construct list of user IDs for current user and users who share access with them.
            $aOwnerIDs = array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL));
            $sOwnerIDsSQL = join(', ', $aOwnerIDs);

            $this->aSQLViewList['WHERE'] .= (!empty($this->aSQLViewList['WHERE'])? ' AND ' : '') . '(' . ($this->sObject == 'Screening'? 'i' : $sAlias) . '.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR (' . $sAlias . '.created_by = "' . $_AUTH['id'] . '" OR ' . $sAlias . '.owned_by IN (' . $sOwnerIDsSQL . '))') . '';
            $this->aSQLViewEntry['WHERE'] .= (!empty($this->aSQLViewEntry['WHERE'])? ' AND ' : '') . '(' . ($this->sObject == 'Screening'? 'i' : $sAlias) . '.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR (' . $sAlias . '.created_by = "' . $_AUTH['id'] . '" OR ' . $sAlias . '.owned_by IN (' . $sOwnerIDsSQL . '))') . ')';
            if ($this->sCategory == 'VariantOnGenome' && $_AUTH && (count($_AUTH['curates']) || count($_AUTH['collaborates']))) {
                // Added so that Curators and Collaborators can view the variants for which they have viewing rights in the genomic variant viewlist.
                $this->aSQLViewList['WHERE'] .= ' OR t.geneid IN ("' . implode('", "', array_merge($_AUTH['curates'], $_AUTH['collaborates'])) . '"))';
            } else {
                $this->aSQLViewList['WHERE'] .= ')';
            }
        }
    }





    function buildFields ()
    {
        // Gathers the columns to be used for lovd_(insert/update)Entry and returns them
        global $_AUTH;

        $aFields = array();
        foreach($this->aColumns as $sCol => $aCol) {
            if (!$aCol['public_add'] && $_AUTH['level'] < LEVEL_CURATOR) {
                continue;
            }
            $aFields[] = $sCol;
        }
        return $aFields;
    }





    function buildForm ($sPrefix = '')
    {
        // Builds the array needed to display the form.
        global $_AUTH;
        $aFormData = array();

        foreach ($this->aColumns as $sCol => $aCol) {
            if (!$aCol['public_add'] && $_AUTH['level'] < LEVEL_CURATOR) {
                continue;
            }
            // Build what type of form entry?
            $aEntry = array();
            if ($aCol['form_type'][2] != 'select') {
                // No select entry; add entry name.
                foreach ($aCol['form_type'] as $key => $val) {
                    if (!$key && !$aCol['mandatory']) {
                        // Add '(Optional)'.
                        $val .= ' (optional)';
                    }
                    $aEntry[] = $val;
                    if ($key == 2) {
                        // Add the form entry name.
                        $aEntry[] = $sPrefix . $sCol;
                    }
                }
                // Setting the key allows easy post-processing of the form.
                $aFormData[$sPrefix . $sCol] = $aEntry;

            } else {
                // Select entries are modified a little more - need source data.
                foreach ($aCol['form_type'] as $key => $val) {
                    if (!$key && !$aCol['mandatory']) {
                        // Add '(Optional)'.
                        $val .= ' (optional)';
                    } elseif ($key == 3) { // Size
                        // We need to place the form entry name (e.g. "Individual/Gender") in between.
                        $aEntry[] = $sPrefix . $sCol;
                    } elseif ($key == 4) { // Select: true|false|--select--
                        // We need to place the form entry data in between.
                        $aData = array();
                        foreach ($aCol['select_options'] as $sLine) {
                            if (substr_count($sLine, '=')) {
                                list($sKey, $sVal) = explode('=', $sLine, 2);
                                $sVal = lovd_shortenString(trim($sVal), 75);
                                // NOTE: This array *refuses* to create string keys if the contents are integer strings. So the keys can actually be integers.
                                $aData[trim($sKey)] = $sVal;
                            } else {
                                $sVal = trim($sLine);
                                $sVal = lovd_shortenString($sVal, 75);
                                $aData[$sVal] = $sVal;
                            }
                        }

                        // Add currently filled in data if it's not in the selection_values, or else we'll lose it!
                        if (!empty($_POST[$sCol])) {
                            if (is_array($_POST[$sCol])) {
                                $aPOST = $_POST[$sCol]; // Multiple selection list.
                            } else {
                                $aPOST = array($_POST[$sCol]); // Drop down list.
                            }
                            foreach ($aPOST as $sOption) {
                                if ($sOption && !array_key_exists($sOption, $aData)) {
                                    // Add entry!
                                    $aData[$sOption] = $sOption;
                                }
                            }
                        }

                        $aEntry[] = $aData;
                    }

                    if ($val == 'false') {
                        $val = false;
                    } elseif ($val == 'true') {
                        $val = true;
                    }
                    $aEntry[] = $val;
                }

                // Shorten selection list if source data is shorter.
                if ($aEntry[4] > 1) {
                    // Size > 1.
                    $nItems = count($aEntry[5]);
                    if ($nItems < $aEntry[4]) {
                        // Set size = number of options.
                        $aEntry[4] = $nItems;
                    }
                }

                // Setting the key allows easy post-processing of the form.
                $aFormData[$sPrefix . $sCol] = $aEntry;
            }

            // The element data will be passed on, so that viewForm() can make sure it will be added to the HTML.
            // NOTE: Element data is not stored in the database, but added by, for instance, getForm().
            if (!empty($aCol['element_data'])) {
                $aFormData[$sPrefix . $sCol][] = $aCol['element_data'];
            }

            // Any custom links we want to mention?
            if (!empty($aCol['custom_links'])) {
                $sLinks = '';
                foreach ($aCol['custom_links'] as $nLink) {
                    $aLink = $this->aCustomLinks[$nLink];
                    $sToolTip = str_replace(array("\r\n", "\r", "\n"), '<BR>', 'Click to insert:<BR>' . $aLink['pattern_text'] . '<BR><BR>' . addslashes(htmlspecialchars($aLink['description'])));
                    $sLinks .= ($sLinks? ', ' : '') . '<A href="#" onmouseover="lovd_showToolTip(\'' . $sToolTip . '\');" onmouseout="lovd_hideToolTip();" onclick="lovd_insertCustomLink(this, \'' . $aLink['pattern_text'] . '\'); return false">' . $aLink['name'] . '</A>';
                }
                $aFormData[$sPrefix . $sCol . '_links'] = array('', '', 'print', '<SPAN class="S11">(Active custom link' . (count($aCol['custom_links']) == 1? '' : 's') . ' : ' . $sLinks . ')</SPAN>');
            }

            // Need to add description?
            if ($aCol['description_form']) {
                $aFormData[$sPrefix . $sCol . '_notes'] = array('', '', 'note', $aCol['description_form']);
            }
        }

        return $aFormData;
    }





    function buildViewEntry ()
    {
        // Gathers the columns which are active for the current data type and returns them in a viewEntry format
        // Note: object_custom_viewlists.php implements their own version of this code.
        global $_AUTH, $_SETT;

        $aViewEntry = array();
        foreach ($this->aColumns as $sID => $aCol) {
            if (!$aCol['public_view'] && $_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) {
                continue;
            }
            $aViewEntry[$sID] = $aCol['head_column'];
        }
        return $aViewEntry;
    }





    function buildViewList ()
    {
        // Gathers the columns which are active for the current data type and returns them in a viewList format
        // Note: object_custom_viewlists.php implements their own version of this code.
        global $_AUTH, $_SETT;

        $aViewList = array();
        foreach ($this->aColumns as $sID => $aCol) {
            // In LOVD_plus, the public_view field is used to set if a custom column will be displayed in a VL or not.
            // So, in LOVD_plus we need to check for ALL USERS if a custom column has public_view flag turned on or not.
            if (!$aCol['public_view'] && (LOVD_plus? true : $_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data'])) {
                continue;
            }
            $bAlignRight = preg_match('/^(DEC|FLOAT|(TINY|SMALL|MEDIUM|BIG)?INT)/', $aCol['mysql_type']);

            $aViewList[$sID] =
                            array(
                                    'view'   => array($aCol['head_column'], $aCol['width'], ($bAlignRight? ' align="right"' : '')),
                                    'db'     => array('`' . $aCol['colid'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
                                    'legend' => array($aCol['description_legend_short'], $aCol['description_legend_full']),
                                    'allow_find_replace' => true, // All custom columns allow Find & Replace.
                                 );
        }
        return $aViewList;
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        global $_AUTH;
        // Checks fields before submission of data.
        foreach ($this->aColumns as $sCol => $aCol) {
            if ($aCol['mandatory']) {
                $this->aCheckMandatory[] = $sCol;
            }
            if (!LOVD_plus) {
                // Disabled for LOVD+, as it takes a lot of time, and we don't use it.
                // Make it easier for users to fill in the age fields. Change 5d into 00y00m05d, for instance.
                if (preg_match('/\/Age(\/.+|_.+)?$/', $sCol) && isset($aData[$sCol]) && preg_match('/^([<>])?(\d{1,2}y)?(\d{1,2}m)?(\d{1,2}d)?(\?)?$/', $aData[$sCol], $aRegs)) {
                    $aRegs = array_pad($aRegs, 6, '');
                    if ($aRegs[2] || $aRegs[3] || $aRegs[4]) {
                        // At least some data needs to be filled in!
                        // First, pad the numbers.
                        foreach ($aRegs as $key => $val) {
                            if (preg_match('/^\d{1}[ymd]$/', $val)) {
                                $aRegs[$key] = '0' . $val;
                            }
                        }
                        // Then, glue everything together.
                        $aData[$sCol] = $_POST[$sCol] = $aRegs[1] . (!$aRegs[2]? '00y' : $aRegs[2]) . (!$aRegs[3] && $aRegs[4]? '00m' : $aRegs[3]) . (!$aRegs[4]? '' : $aRegs[4]) . (!$aRegs[5]? '' : $aRegs[5]);
                    }
                }
            }
            if (!empty($aData[$sCol])) {
                if (!LOVD_plus) {
                    // Disabled for LOVD+, to speed up the import.
                    $this->checkInputRegExp($sCol, $aData[$sCol]);
                }
                if (!(LOVD_plus && lovd_getProjectFile() == '/import.php')) {
                    // We disable this check in LOVD+, to speed up the import.
                    $this->checkSelectedInput($sCol, $aData[$sCol]);
                }
            }
        }

        if ($_AUTH['level'] < LEVEL_CURATOR) {
            if (!empty($aData['owned_by'])) {
                // FIXME; this is a hack attempt. We should consider logging this. Or just plainly ignore the value.
                lovd_errorAdd('owned_by', 'Not allowed to change \'Owner of this data\'.');
            }

            if (!empty($aData['statusid'])) {
                lovd_errorAdd('statusid', 'Not allowed to set \'Status of this data\'.');
            }
        }

        if (lovd_getProjectFile() == '/import.php' && $this->sObject == 'Genome_Variant' && ($nIndex = array_search('VariantOnGenome/DBID', $this->aCheckMandatory)) !== false) {
            // Importing, and in VOG. Make the DBID non-mandatory, because it will be predicted by the import script when it's empty.
            unset($this->aCheckMandatory[$nIndex]);
        }

        parent::checkFields($aData, $zData, $aOptions);
    }





    function checkInputRegExp ($sCol, $val)
    {
        // Checks if field input corresponds to the given regexp pattern.
        global $_SETT;

        $sColClean = preg_replace('/^\d{' . $_SETT['objectid_length']['transcripts'] . '}_/', '', $sCol); // Remove prefix (transcriptid) that LOVD_TranscriptVariants puts there.
        if ($this->aColumns[$sColClean]['preg_pattern'] && $val) {
            if (!preg_match($this->aColumns[$sColClean]['preg_pattern'], $val)) {
                lovd_errorAdd($sCol, 'The input in the \'' . (lovd_getProjectFile() == '/import.php'? $sColClean : $this->aColumns[$sColClean]['form_type'][0]) . '\' field does not correspond to the required input pattern.');
            }
        }
    }





    function checkSelectedInput ($sCol, $Val)
    {
        // Checks if the selected values are indeed from the selection list.
        global $_SETT;

        $sColClean = preg_replace('/^\d{' . $_SETT['objectid_length']['transcripts'] . '}_/', '', $sCol); // Remove prefix (transcriptid) that LOVD_TranscriptVariants puts there.
        if ($this->aColumns[$sColClean]['form_type'][2] == 'select' && $this->aColumns[$sColClean]['form_type'][3] >= 1) {
            if (!empty($Val)) {
                $aOptions = preg_replace('/ *(=.*)?$/', '', $this->aColumns[$sColClean]['select_options']); // Trim whitespace from the options.
                // Not a great way to check if we need to explode the values, but it works.
                // It would be better however, if we would pass on the settings like objects::checkFields() does.
                if (in_array(lovd_getProjectFile(), array('/import.php', '/ajax/viewlist.php'))) {
                    // Importing, or running F&R!
                    $Val = explode(';', $Val); // Normally the form sends an array, but from the import I need to create an array.
                } elseif (!is_array($Val)) {
                    $Val = array($Val);
                } elseif (GET) {
                    // 2013-10-15; 3.0-08; Not importing, $Val is already an array, and we're here using GET.
                    // When directly publishing an entry, not having filled in a selection list will trigger
                    // an error when an empty string is not an option in this selection list.
                    if ($Val === array('') && !in_array('', $aOptions)) {
                        // Error would be triggered wrongly.
                        $Val = array();
                    }
                }
                foreach ($Val as $sValue) {
                    $sValue = trim($sValue); // Trim whitespace from $sValue to ensure match independent of whitespace.
                    if (!in_array($sValue, $aOptions)) {
                        if (in_array(lovd_getProjectFile(), array('/import.php', '/ajax/viewlist.php'))) {
                            // Importing, or running F&R!
                            lovd_errorAdd($sCol, 'Please select a valid entry from the \'' . $sColClean . '\' selection box, \'' . strip_tags($sValue) . '\' is not a valid value. Please choose from these options: \'' . implode('\', \'', $aOptions) . '\'.');
                        } else {
                            lovd_errorAdd($sCol, 'Please select a valid entry from the \'' . $this->aColumns[$sColClean]['form_type'][0] . '\' selection box, \'' . strip_tags($sValue) . '\' is not a valid value.');
                        }
                        break;
                    }
                }
            }
        }
    }





    function colExists ($sCol)
    {
        // Returns true if column exists.
        return (isset($this->aColumns[$sCol]));
    }





    function getStatusColor ($nStatusID)
    {
        // Returns the color coding that fits the given status.

        if ($nStatusID < STATUS_MARKED) {
            $sColor = 'F00'; // Red.
        } elseif ($nStatusID < STATUS_OK) {
            $sColor = 'A30'; // Dark red.
        } else {
            $sColor = '0A0'; // Green.
        }
        return $sColor;
    }





    function getDefaultValue ($sCol)
    {
        // Returns the column type, so the input can be checked.
        if (preg_match('/ DEFAULT (\d+|"[^"]+")/', $this->aColumns[$sCol]['mysql_type'], $aRegs)) {
            // Process default values.
            return trim($aRegs[1], '"');
        } else {
            return '';
        }
    }





    function initDefaultValues ()
    {
        // Initiate default values of fields in $_POST.
        foreach ($this->aColumns as $sCol => $aCol) {
            // Fill $_POST with the column's default value.
            $_POST[$sCol] = $this->getDefaultValue($sCol);
        }
    }





    function loadEntry ($nID = false)
    {
        // Loads and returns an entry from the database.
        $zData = parent::loadEntry($nID);

        foreach ($this->aColumns as $sCol => $aCol) {
            if ($aCol['form_type'][2] == 'select' && $aCol['form_type'][3] > 1) {
                $zData[$sCol] = explode(';', $zData[$sCol]);
            }
        }

        return $zData;
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data before returning it to the user.
        global $_AUTH, $_SETT;

        $zData = parent::prepareData($zData, $sView);
        foreach ($this->aColumns as $sCol => $aCol) {
            if (!$aCol['public_view'] && $_AUTH['level'] < LEVEL_OWNER) {
                continue;
            }
            if (!empty($aCol['custom_links'])) {
                foreach ($aCol['custom_links'] as $nLink) {
                    $sRegexpPattern = $this->aCustomLinks[$nLink]['regexp_pattern'];
                    $sReplaceText = $this->aCustomLinks[$nLink]['replace_text'];
                    if ($sView == 'list') {
                        $sReplaceText = '<SPAN class="custom_link" onmouseover="lovd_showToolTip(\'' . str_replace('"', '\\\'', $sReplaceText) . '\', this);">' . strip_tags($sReplaceText) . '</SPAN>';
                    }
                    $zData[$aCol['id']] = preg_replace($sRegexpPattern . 'U', $sReplaceText, $zData[$aCol['id']]);
                }
            }
        }
        if ($sView == 'entry') {
            // Mark the status, if shown on the page.
            if (isset($zData['statusid'])) {
                $zData['status'] = '<SPAN style="color : #' . $this->getStatusColor($zData['statusid']) . '">' . $_SETT['data_status'][$zData['statusid']] . '</SPAN>';
            }

            // License information.
            if (isset($this->aColumnsViewEntry['license_'])) {
                if (empty($zData['license'])) {
                    $zData['license_'] = 'No license selected';

                } elseif (strpos($zData['license'], ';;') !== false) {
                    // Variants are special cases, they can be linked to multiple individuals.
                    // This is not an LOVD feature, but a consequence of the data model chosen.
                    // In some cases, this may mean various licenses apply.
                    $zData['license_'] = 'Multiple licenses, see links to submissions above.';

                } else {
                    // Normal case, one license only (but still possibly from multiple individuals).
                    // The license contains both the license for the world as well as the license for LOVD.
                    $zData['license'] = strstr($zData['license'] . ';', ';', true);
                    $sLicenseName = substr($zData['license'], 3, -4);
                    $sLicenseVersion = substr($zData['license'], -3);
                    $nIndividualID = false;

                    if ($this->sObject == 'Genome_Variant') {
                        if (count($zData['individuals']) > 1) {
                            // We need to pick one, to link to it. We'll pick the public IDs first.
                            foreach (array(STATUS_OK, STATUS_MARKED, STATUS_HIDDEN, STATUS_PENDING) as $nStatus) {
                                foreach ($zData['individuals'] as list($nIndividualID, $nIndStatus)) {
                                    if ($nStatus == $nIndStatus) {
                                        break 2;
                                    }
                                }
                            }
                        } elseif ($zData['individuals']) {
                            $nIndividualID = $zData['individuals'][0][0];
                        }
                    } elseif ($this->sObject == 'Individual') {
                        $nIndividualID = $zData['id'];
                    } elseif (in_array($this->sObject, array('Phenotype', 'Screening'))) {
                        $nIndividualID = $zData['individualid'];
                    }

                    $zData['license_'] =
                        '<A rel="license" href="https://creativecommons.org/licenses/' . $sLicenseName . '/' . $sLicenseVersion . '/" target="_blank" onclick="$.get(\'ajax/licenses.php/' . ($nIndividualID? 'individual/' . $nIndividualID : 'user/' . $zData['created_by']) . '?view\').fail(function(){alert(\'Error viewing license information, please try again later.\');}); return false;">' .
                        '<SPAN style="display: none;">' . $_SETT['licenses'][$zData['license']] . '</SPAN>' .
                        '<IMG src="gfx/' . str_replace($sLicenseVersion, '80x15', $zData['license']) . '.png" alt="Creative Commons License" title="' . $_SETT['licenses'][$zData['license']] . '" border="0">' .
                        '</A> ';
                    // Also annotate the HTML.
                    $this->aColumnsViewEntry['license_'] = '<SPAN xmlns:dct="http://purl.org/dc/terms/" href="http://purl.org/dc/dcmitype/Dataset" property="dct:title" rel="dct:type">Database submission</SPAN> license';
                    // NOTE: We're using our created_by here, but the license has been set perhaps by somebody else (the Individual's creator).
                    // This isn't very important as this is just annotation. The detailed view of the license will list the correct name.
                    $zData['created_by_'] = '<SPAN xmlns:cc="http://creativecommons.org/ns#" property="cc:attributionName">' . $zData['created_by_'] . '</SPAN>';

                    // A tool to change the license is only given on the Individuals VE, which has the ability to be reloaded.
                }
            }
        }
        return $zData;
    }
}
?>
