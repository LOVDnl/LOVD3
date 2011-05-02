<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-17
 * Modified    : 2011-04-29
 * For LOVD    : 3.0-pre-20
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
                    'WHERE c.id LIKE "' . $this->sObject . '/%" ' .
                    'ORDER BY c.col_order';
        } else {
            $sSQL = 'SELECT c.*, sc.* ' .
                    'FROM ' . TABLE_COLS . ' AS c ' .
                    'INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = c.id) ' .
                    'WHERE c.id LIKE "' . $this->sObject . '/%" ' .
                    'AND ' . ($this->sObject == 'Phenotype'? 'sc.diseaseid=' : 'sc.geneid=') . '? ' .
                    'ORDER BY sc.col_order';
            $aArgs[] = $this->sObjectID;
        }
        $q = lovd_queryDB($sSQL, $aArgs);
        while ($z = mysql_fetch_assoc($q)) {
            $z['custom_links'] = array();
            $z['form_type'] = explode('|', $z['form_type']);
            $this->aColumns[$z['id']] = $z;
        }

        parent::LOVD_Object();
    }





    function buildViewEntry ()
    {
        $aViewEntry = array();
        foreach ($this->aColumns as $sID => $aCol) {
            $aViewEntry[$sID] = $aCol['head_column'];
        }
        return $aViewEntry;
    }





    function buildFields ()
    {
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

        require_once ROOT_PATH . 'class/object_links.php';

        // Gather the custom link information.
        $qLinks = lovd_queryDB('SELECT c2l.colid, c2l.linkid, l.name ' .
                               'FROM ' . TABLE_COLS2LINKS . ' AS c2l ' .
                               'LEFT OUTER JOIN ' . TABLE_LINKS . ' AS l ON (c2l.linkid = l.id) ' .
                               'WHERE c2l.colid LIKE "' . $this->sObject . '/%"', array());
        while ($z = mysql_fetch_assoc($qLinks)) {
            // 2008-02-29; 2.0-04; Only if column is active, duh!
            if (isset($this->aColumns[$z['colid']])) {
                $this->aColumns[$z['colid']]['custom_links'][$z['linkid']] = $z['name'];
            }
        }

        foreach ($this->aColumns as $sCol => $aCol) {
            // Build what type of form entry?
            if ($aCol['form_type'][2] != 'select') {
                // No select entry; add entry name.
                $aEntry = array();
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
                $aEntry = array();
                foreach ($aCol['form_type'] as $key => $val) {
                    if ($key == 3) { // Size
                        // We need to place the form entry name (e.g. "Individual/Gender") in between.
                        $aEntry[] = $sCol;
                    } elseif ($key == 4) { // Select: true|false|--select--
                        // We need to place the form entry data in between.
                        $a = explode("\r\n", $aCol['select_options']);
                        $aData = array();
                        foreach ($a as $sLine) {
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
            $_DATA = new LOVD_Link();
            if (!empty($aCol['custom_links'])) {
                $sLinks = '';
                foreach ($aCol['custom_links'] as $nLink => $sLink) {
                    $zData = $_DATA->loadEntry($nLink);
                    $sToolTip = str_replace(array("\r\n", "'"), array('<BR>', "\'"), $zData['description']);
                    $sLinks .= ($sLinks? ', ' : '') . '<A href="#" onmouseover="lovd_showToolTip(\'' . $sToolTip . '\');" onmouseout="lovd_hideToolTip();" onclick="lovd_insertCustomLink(this, \'' . $zData['pattern_text'] . '\'); return false">' . $sLink . '</A>';
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
        foreach ($aData as $sCol => $val) {
            if (isset($this->aColumns[$sCol])) {
                //$this->checkInputType($sCol, $val);
                //$this->checkInputLength($sCol, $val);
                $this->checkInputRegExp($sCol, $val);
                if ($this->aColumns[$sCol]['mandatory']) {
                    $this->aCheckMandatory[] = $sCol;
                }
            }
        }

        parent::checkFields($aData);
    }





    function checkInputLength ($sCol, $val)
    {
        // Checks if field input is not too long for the field.
        $nMaxLength = $this->getFieldLength($sCol);
        if (is_array($val)) {
            $val = implode(';', $val);
        }
        $nLength = strlen($val);
        if ($nMaxLength < $nLength) {
            lovd_errorAdd($sCol, 'The \'' . $this->aColumns[$sCol]['form_type'][0] . '\' field is limited to ' . $nMaxLength . ' characters, you entered ' . $nLength . '.');
        }
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





    function checkInputType ($sCol, $val)
    {
        // Checks if field input is of the correct type.
        $sType = $this->getFieldType($sCol);
        switch ($sType) {
            case 'INT':
                if (!preg_match('/^\d+$/', $val)) {
                    lovd_errorAdd($sCol, 'The field \'' . $this->aColumns[$sCol]['form_type'][0] . '\' must contain an integer.');
                }
                break;
            case 'DEC':
                if (!is_numeric($val)) {
                    lovd_errorAdd($sCol, 'The field \'' . $this->aColumns[$sCol]['form_type'][0] . '\' must contain a number.');
                }
                break;
            case 'DATETIME':
                if (!preg_match('/^\d{4}[.\/-]\d{2}[.\/-]\d{2}( \d{2}\:\d{2}\:\d{2})?$/', $val)) {
                    lovd_errorAdd($sCol, 'The field \'' . $this->aColumns[$sCol]['form_type'][0] . '\' must contain a date, possibly including a time.');
                }
                break;
            case 'DATE':
                if (!lovd_matchDate($val)) {
                    lovd_errorAdd($sCol, 'The field \'' . $this->aColumns[$sCol]['form_type'][0] . '\' must contain a date.');
                }
                break;
        }
    }





    function getDefaultValue ($sCol)
    {
        // Returns the column type, so the input can be checked.
        // 2009-02-16; 2.0-16; Introducing default values.
        if (preg_match('/ DEFAULT (\d+|"[^"]+")/', $this->aColumns[$sCol]['mysql_type'], $aRegs)) {
            // Process default values.
            return trim($aRegs[1], '"');
        } else {
            return '';
        }
    }





    function getFieldLength ($sCol)
    {
        // Returns the maximum number of characters a column can hold, so the input can be checked.
        $sColType = $this->aColumns[$sCol]['mysql_type'];
        if (preg_match('/^((TINY|SMALL|MEDIUM|BIG)?INT|(VAR)?CHAR)\((\d+)\)/', $sColType, $aRegs) && is_numeric($aRegs[4])) {
            return $aRegs[4];

        } elseif (preg_match('/^DEC\((\d+),(\d+)\)/', $sColType, $aRegs) && is_numeric($aRegs[1]) && is_numeric($aRegs[2])) {
            return ($aRegs[1] + 1);

        } elseif (preg_match('/^(TINY|MEDIUM|LONG)?(TEXT|BLOB)/', $sColType, $aRegs)) {
            switch ($aRegs[1]) {
                case 'TINY':
                    return 256; // 2^8; 1 byte
                case 'MEDIUM':
                    return 16777216; // 2^24; 3 bytes
                case 'LONG':
                    return 4294967296; // 2^32; 4 bytes
                default:
                    return 65536; // 2^16; 2 bytes
            }
        } elseif (preg_match('/^DATE(TIME)?/', $sColType, $aRegs)) {
            // 2009-06-19; 2.0-19; added DATE and DATETIME datatypes.
            return (10 + (!empty($aRegs[1])? 9 : 0));
        }

        return 0;
    }





    function getFieldType ($sCol)
    {
        // Returns the column type, so the input can be checked.
        $sColType = $this->aColumns[$sCol]['mysql_type'];
        if (preg_match('/^((VAR)?CHAR\(\d+\)|(TINY|MEDIUM|LONG)?(TEXT|BLOB))/', $sColType)) {
            return 'CHAR';
        } elseif (preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT\(\d+\)/', $sColType)) {
            return 'INT';
        } elseif (preg_match('/^DEC\(\d+,\d+\)/', $sColType)) {
            return 'DEC';
        } elseif (preg_match('/^DATETIME/', $sColType)) {
            return 'DATETIME';
        } elseif (preg_match('/^DATE/', $sColType)) {
            return 'DATE';
        }
        return false;
    }





    function initDefaultValues ()
    {
        // Initiate default values of fields in $_POST.
        // 2009-02-16; 2.0-16; Introducing default values.
        foreach ($this->aColumns as $sCol => $aCol) {
            // Fill $_POST with the column's default value.
            $_POST[$sCol] = $this->getDefaultValue($sCol);
        }
    }
}
?>
