<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-17
 * Modified    : 2011-02-21
 * For LOVD    : 3.0-pre-17
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





class Custom extends Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Custom';
    
    
    
    
    
    function Custom ()
    {
        // Default constructor.
        global $_AUTH;
        
        parent::Object();
        /*$qCols = 'SELECT cust.id FROM ' . TABLE_COLS . ' AS cust, ' . TABLE_ACTIVE_COLS . ' AS a WHERE SUBSTRING_INDEX(cust.id, "/", 1)="Patient" AND cust.id=a.colid';
        echo "<BR><BR>";
        $result = mysql_query($qCols);
        $qPatients = 'SELECT p.id';
        while($data = mysql_fetch_row($result)) {
            $qPatients .= ', p.`' . $data[0] . '`';
        }
        $qPatients .= ', uo.name AS owner, s.name AS status FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) LEFT JOIN ' . TABLE_DATA_STATUS . ' AS s ON (p.statusid = s.id)';
        $result = mysql_query($qPatients);
        while ($data = mysql_fetch_row($result)) {
            $this->aColumnsViewList['Patient/Age_of_diagnosis'] = array
                                                                      (
                                                                        'view' => array('Patient ID', 70),
                                                                        'db'   => array('p.id', 'ASC', true)
                                                                      )
        }*/
        
        //$qPatients = 'SELECT p.*, uo.name AS owner, s.name AS status FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) LEFT JOIN ' . TABLE_DATA_STATUS . ' AS s ON (p.statusid = s.id)';
        //$result = mysql_query($qPatients);
        //$aHidden = array('statusid', 'created_by', 'created_date', 'edited_by' ,'valid_from' ,'valid_to', 'deleted', 'deleted_by');
        //while ($data = mysql_fetch_assoc($result)) {
            //foreach ($aHidden as $sHidden) {
                //unset($data[$sHidden]);
            //}
            //var_dump($data);
        //}
    }
}
?>