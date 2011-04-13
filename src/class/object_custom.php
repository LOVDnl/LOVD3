<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-17
 * Modified    : 2011-04-07
 * For LOVD    : 3.0-pre-19
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
        
        //$this->sObjectID = $sObjectID;
        
        if (!$this->bShared) {
            // FIXME; Hoewel $this->sObject door ons wordt gegenereerd, lijkt het me toch beter 'm niet in de SQL te zetten maar in de argumenten.
            $sSQL = 'SELECT c.*, a.* ' .
                    'FROM ' . TABLE_ACTIVE_COLS . ' AS a ' .
                    'LEFT OUTER JOIN ' . TABLE_COLS . ' AS c ON (c.id = a.colid) ' .
                    'WHERE c.id LIKE "' . $this->sObject . '/%" ' .
                    'ORDER BY c.col_order';
        } else {
            // FIXME; SQL INJECTION!!!!!!! $this->sObjectID is ontvangen van de gebruiker!!!
            $sSQL = 'SELECT c.*, s.* ' .
                    'FROM ' . TABLE_COLS . ' AS c ' .
                    'INNER JOIN ' . TABLE_SHARED_COLS . ' AS s ON (s.colid = c.id) ' .
                    'WHERE c.id LIKE "' . $this->sObject . '/%" ' .
                    'AND ' . ($this->sObject == 'Phenotype'? 's.diseaseid="' : 's.geneid="') . $this->sObjectID . '" ' .
                    'ORDER BY s.col_order';
        }
        $q = lovd_queryDB($sSQL, array());
        while ($z = mysql_fetch_assoc($q)) {
            $this->aColumns[$z['id']] = $z;
        }
        parent::LOVD_Object();
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
    
    
    
    
    
    function buildViewEntry ()
    {
        $aViewEntry = array();
        foreach ($this->aColumns as $sID => $aCol) {
            $aViewEntry[$sID] = $aCol['head_column'];
        }
        return $aViewEntry;
    }
}
?>