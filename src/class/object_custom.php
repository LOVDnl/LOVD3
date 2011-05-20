<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-17
 * Modified    : 2011-05-17
 * For LOVD    : 3.0-pre-21
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
require_once ROOT_PATH . 'class/objects.php';





class LOVD_Custom extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Custom';
    var $bShared = false;
    var $aColumns = array();





    function LOVD_Custom ()
    {
        // Default constructor.
        global $_AUTH, $_SETT;

        if (empty($this->sObjectID) && $this->bShared) {
            // FIXME; Fix this text a bit and let displayError() add the sentence about the BTS.
            lovd_displayError('BadObjectCall', 'LOVD_Custom::' . "\n\t" . 'Bad call for shared column using empty gene and disease variables.' . "\n\n" .
                                               'Please go to our <A href="' . $_SETT['upstream_BTS_URL_new_ticket'] . '" target="_blank">bug tracking system</A> ' .
                                               'and report this error to help improve LOVD3.');
        }

        $aArgs = array();	

        if (!$this->bShared) {
            $sSQL = 'SELECT c.*, ac.* ' .
                    'FROM ' . TABLE_ACTIVE_COLS . ' AS ac ' .
                    'LEFT OUTER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) ' .
                    'WHERE c.id LIKE "' . (isset($this->sCategory)? $this->sCategory : $this->sObject) . '/%" ' .
                    'ORDER BY c.col_order';
        } else {
            $sSQL = 'SELECT c.*, sc.* ' .
                    'FROM ' . TABLE_COLS . ' AS c ' .
                    'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                    'WHERE c.id LIKE "' . (isset($this->sCategory)? $this->sCategory : $this->sObject) . '/%" ' .
                    'AND ' . ($this->sObject == 'Phenotype'? 'sc.diseaseid=' : 'sc.geneid=') . '? ' .
                    'ORDER BY sc.col_order';
            $aArgs[] = $this->sObjectID;
        }
        $q = lovd_queryDB($sSQL, $aArgs);
        while ($z = mysql_fetch_assoc($q)) {
            $z['custom_links'] = array();
            $z['form_type'] = explode('|', $z['form_type']);
            $z['select_options'] = explode("\r\n", $z['select_options']);
            $this->aColumns[$z['id']] = $z;
        }
        
        // Gather the custom link information.
        $qLinks = lovd_queryDB('SELECT c2l.colid, l.* ' .
                               'FROM ' . TABLE_COLS2LINKS . ' AS c2l ' .
                               'INNER JOIN ' . TABLE_LINKS . ' AS l ON (c2l.linkid = l.id) ' .
                               'WHERE c2l.colid LIKE ?', array($this->sObject . '/%'));
        while ($z = mysql_fetch_assoc($qLinks)) {
            if (isset($this->aColumns[$z['colid']])) {
                $this->aColumns[$z['colid']]['custom_links'][$z['id']] = $z;
            }
        }

        parent::LOVD_Object();
    }





    function buildViewEntry ()
    {
        // FIXME; define function's purpose.
        $aViewEntry = array();
        foreach ($this->aColumns as $sID => $aCol) {
            $aViewEntry[$sID] = $aCol['head_column'];
        }
        return $aViewEntry;
    }





    function buildFields ()
    {
        // FIXME; define function's purpose. Seems more like a getFields(). 
        // FIXME; implement using implode().
        $aFields = array();
        foreach($this->aColumns as $sCol => $aCol) {
            $aFields[] = $sCol;
        }
        return $aFields;
    }





    function buildViewForm ()
    {
        // Builds the array needed to display the form.
        global $_PATH_ELEMENTS;

        $aFormData = array();

        foreach ($this->aColumns as $sCol => $aCol) {
            // Build what type of form entry?
            $aEntry = array();
            if ($aCol['form_type'][2] != 'select') {
                // No select entry; add entry name.
                foreach ($aCol['form_type'] as $key => $val) {
                    if (!$key && !$aCol['mandatory']) {
                        // Add '(Optional)'.
                        $val .= ' (Optional)';
                    } elseif ($key == 3) {
                        // Add the form entry name.
                        $aEntry[] = $sCol;
                    }
                    $aEntry[] = $val;
                }
                $aFormData[] = $aEntry;

            } else {
                // Select entries are modified a little more - need source data.
                foreach ($aCol['form_type'] as $key => $val) {
                    if ($key == 3) { // Size
                        // We need to place the form entry name (e.g. "Individual/Gender") in between.
                        $aEntry[] = $sCol;
                    } elseif ($key == 4) { // Select: true|false|--select--
                        // We need to place the form entry data in between.
                        $aData = array();
                        foreach ($aCol['select_options'] as $sLine) {
                            if (substr_count($sLine, '=')) {
                                list($sKey, $sVal) = explode('=', $sLine, 2);
                                $sVal = lovd_shortenString(trim($sVal), 75);
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

                $aFormData[] = $aEntry;
            }

            // Any custom links we want to mention?
            if (!empty($aCol['custom_links'])) {
                $sLinks = '';
                foreach ($aCol['custom_links'] as $nLink => $aLink) {
                    $sToolTip = str_replace(array("\r\n", "\r", "\n"), '<BR>', 'Click to insert:<BR>' . $aLink['pattern_text'] . '<BR><BR>' . addslashes(htmlspecialchars($aLink['description'])));
                    $sLinks .= ($sLinks? ', ' : '') . '<A href="#" onmouseover="lovd_showToolTip(\'' . $sToolTip . '\');" onmouseout="lovd_hideToolTip();" onclick="lovd_insertCustomLink(this, \'' . $aLink['pattern_text'] . '\'); return false">' . $aLink['name'] . '</A>';
                }
                $aFormData[] = array('', '', 'print', '<SPAN class="S11">(Active custom link' . (count($aCol['custom_links']) == 1? '' : 's') . ' : ' . $sLinks . ')</SPAN>');
            }

            // Need to add description?
            if ($aCol['description_form']) {
                $aFormData[] = array('', '', 'note', $aCol['description_form']);
            }
        }

        return $aFormData;
    }





    function buildViewList ()
    {
        // FIXME; define function's purpose.
        $aViewList = array();
        foreach ($this->aColumns as $sID => $aCol) {
            $aViewList[$sID] = 
                            array(
                                    'view' => array($aCol['head_column'], $aCol['width']),
                                    'db'   => array('`' . $aCol['colid'] . '`', 'ASC', true),
                                 );
        }
        return $aViewList;
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        foreach ($this->aColumns as $sCol => $aCol) {
            if ($aCol['mandatory']) {
                $this->aCheckMandatory[] = $sCol;
            }
            if (isset($aData[$sCol])) {
                $this->checkInputRegExp($sCol, $aData[$sCol]);
                $this->checkSelectedInput($sCol, $aData[$sCol]);
            }
        }
        parent::checkFields($aData);
    }





    function checkInputRegExp ($sCol, $val)
    {
        // Checks if field input corresponds to the given regexp pattern.
        if ($this->aColumns[$sCol]['preg_pattern'] && !empty($_POST[$sCol])) {
            if (!preg_match($this->aColumns[$sCol]['preg_pattern'], $val)) {
                lovd_errorAdd($sCol, 'The input in the \'' . $this->aColumns[$sCol]['form_type'][0] . '\' field does not correspond to the required input pattern.');
            }
        }
    }





    function checkSelectedInput ($sCol, $val)
    {
        // Checks if the selected values are indeed from the selection list.
        if ($this->aColumns[$sCol]['form_type'][2] == 'select' && $this->aColumns[$sCol]['form_type'][3] >= 1) {
            if (!empty($val)) {
                $aOptions = preg_replace('/ =.*$/', '', $this->aColumns[$sCol]['select_options']);
                (!is_array($val)? $val = array($val) : false);
                foreach ($val as $sValue) {
                    if (!in_array($sValue, $aOptions)) {
                        lovd_errorAdd($sCol, 'Please select a valid entry from the \'' . $this->aColumns[$sCol]['form_type'][0] . '\' selection box.');
                        break;
                    }
                }
            }
        }
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
            if ($aCol['form_type'][2] == 'select' && $aCol['form_type'][3] >= 1) {
                $zData[$sCol] = explode(';', $zData[$sCol]);
            }
        }

        return $zData;
    }
    
    
    
    
    
    function prepareData ($zData = '', $sView = 'list')
    {
        $zData = parent::prepareData($zData, $sView);
        // FIXME; ik denk niet dat dit een handige plek is; prepareData() hoort eigenlijk geen output te geven lijkt me; als je deze JS nodig hebt, moet hij automatisch bij viewLists() of viewEntry() erbij worden gedaan.
        lovd_includeJS('inc-js-tooltip.php');
        foreach ($this->aColumns as $sCol => $aCol) {
            $bCustomLink = false;
            if (!empty($aCol['custom_links'])) {
                foreach ($aCol['custom_links'] as $nLink => $aLink) {
                    $sPatternText = preg_replace('/\[\d\]/', '(.*)', $aLink['pattern_text']);
                    $sReplaceText = preg_replace('/\[(\d)\]/', '\$$1', $aLink['replace_text']);
                    // FIXME; dit moet gefixed worden voor viewLists.
                    if (preg_match($sPatternText, $zData[$aCol['colid']])) {
                        $bCustomLink = true;
                    }
                    if ($sView == 'list' && $bCustomLink) {
                        //$sReplaceText = '<A onmouseover="lovd_showToolTip(\'Tooltip\');" onmouseout="lovd_hideToolTip();" onclick="return false;">' . strip_tags($sReplaceText) . '</A>';
                        $sReplaceText = strip_tags($sReplaceText);
                    }
                    $zData[$aCol['colid']] = preg_replace('/' . $sPatternText . '/U', $sReplaceText, $zData[$aCol['colid']]);
                    //$zData[$aCol['colid']] = ($sView == 'list' && $bCustomLink? '<A onmouseover="lovd_showToolTip(\'Tooltip\');" onmouseout="lovd_hideToolTip();" onclick="return false;">' . strip_tags($zData[$aCol['colid']]) . '</A>' : $zData[$aCol['colid']]);
                }
            }
        }
        return $zData;
    }
}
?>
