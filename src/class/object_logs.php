<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-28
 * Modified    : 2010-05-07
 * For LOVD    : 3.0-pre-07
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class Log extends Object {
    // This class extends the basic Object class and it handles the Log object.
    var $sObject = 'Log';





    function Log ()
    {
        // Default constructor.

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'l.*, CONCAT(l.date, " ", l.mtime) AS timestamp, u.name AS user';
        $this->aSQLViewList['FROM']     = TABLE_LOGS . ' AS l LEFT JOIN ' . TABLE_USERS . ' AS u ON (l.userid = u.id)';
        $this->aSQLViewList['ORDER_BY'] = 'timestamp DESC';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'name' => array(
                                    'view' => array('Log', 50),
                                    'db'   => array('l.name', 'ASC', true)),
                        'date' => array(
                                    'view' => array('Date', 130),
                                    'db'   => array('CONCAT(l.date, " ", l.mtime)', 'DESC', true)),
                        'user' => array(
                                    'view' => array('User', 160),
                                    'db'   => array('u.name', 'ASC', true)),
                        'event' => array(
                                    'view' => array('Event', 100),
                                    'db'   => array('l.event', 'ASC', true)),
                        'del' => array(
                                    'view' => array('&nbsp;', 14, 'align="center"')),
                        'entry' => array(
                                    'view' => array('Entry', 700),
                                    'db'   => array('l.log', false, true)),
                      );
        $this->sSortDefault = 'date';

        parent::Object();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['row_id'] = $zData['name'] . ',' . $zData['date'] . ',' . $zData['mtime'];
        $zData['del'] = '<A href="#" onclick="lovd_AjaxDeleteLog(\'' . $zData['row_id'] . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Delete" title="Delete" width="11" height="11" style="margin-top : 3px;"/></A>';
        $zData['entry'] = str_replace(array("\r\n", "\r", "\n"), '<BR/>', $zData['log']);

        return $zData;
    }
}
?>
