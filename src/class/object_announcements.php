<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-26
 * Modified    : 2017-10-26
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_Announcement extends LOVD_Object
{
    // This class extends the basic Object class and it handles the Announcements.
    var $sObject = 'Announcement';





    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'a.*, uc.name AS created_by_, ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_ANNOUNCEMENTS . ' AS a LEFT JOIN ' . TABLE_USERS . ' AS uc ON (a.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (a.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'a.id';

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'a.*, LEFT(a.start_date, 10) AS start_date_, LEFT(a.end_date, 10) AS end_date_';
        $this->aSQLViewList['FROM']     = TABLE_ANNOUNCEMENTS . ' AS a';
        $this->aSQLViewList['ORDER_BY'] = 'a.start_date ASC, a.end_date ASC, id ASC';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'id' => 'Announcement ID',
                        'type' => 'Type',
                        'announcement' => 'Announcement text',
                        'start_date' => 'Start date',
                        'end_date' => 'End date',
                        'lovd_read_only_' => 'Make LOVD read-only?',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('ID', 45),
                                    'db'   => array('a.id', 'ASC', true)),
                        'type_' => array(
                                    'view' => array('Type', 50, 'style="text-align : center;"'),
                                    'db'   => array('a.type', 'ASC', true)),
                        'announcement' => array(
                                    'view' => array('Announcement text', 600),
                                    'db'   => array('a.announcement', 'ASC', true)),
                        'start_date_' => array(
                                    'view' => array('Start date', 80),
                                    'db'   => array('a.start_date', 'ASC', true)),
                        'end_date_' => array(
                                    'view' => array('End date', 80),
                                    'db'   => array('a.end_date', 'ASC', true)),
                        'lovd_read_only_' => array(
                                    'view' => array('Read only?', 60, 'style="text-align : center;"'),
                                    'db'   => array('lovd_read_only', 'DESC', true)),
                      );
        $this->sSortDefault = 'start_date_';

        parent::__construct();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.
        global $_DB;

        // Mandatory fields.
        $this->aCheckMandatory =
            array(
                'type',
                'announcement',
            );
        parent::checkFields($aData, $zData, $aOptions);

        // NO XSS attack prevention, because the message might require HTML (links, markup etc).
        // lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        // Each type must have a picture named lovd_form_<type>.png, of 16x16 pixels.
        $aTypes = array(
            'information' => 'Information',
            'question' => 'Question',
            'warning' => 'Warning',
        );

        // Array which will make up the form table.
        $this->aFormData =
            array(
                array('POST', '', '', '', '35%', '14', '65%'),
                array('', '', 'print', '<B>Announcement details</B>'),
                array('Announcement type', '', 'select', 'type', 1, $aTypes, false, false, false),
                array('Announcement text', '', 'textarea', 'announcement', 60, 5),
                array('Start date', 'Use this field to create an announcement before it should be online. The announcement will be active only after this date and time. If you leave this field empty, the current time will be selected. To deactivate an announcement, put a date in the future, like 9999-12-31.', 'text', 'start_date', 20),
                array('End date', 'Use this field to automatically let an announcement disappear at a certain date or time. If you leave this field empty, the end date will be set to 9999-12-31 23:59:59.', 'text', 'end_date', 20),
                array('Make LOVD read-only?', 'Enabling this feature blocks logins and submitter registrations when this announcement is active. Only Managers and up will still be able to log into LOVD. Users of a lower level that are active when this announcement activates, will be logged out. A true read-only state is currently not enforced; Managers can still make changes to all data.<BR>Use this feature when the server is undergoing maintenance, for instance.', 'checkbox', 'lovd_read_only'),
                'skip',
                'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
            );

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
            $zData['type_'] = '<IMG src="gfx/lovd_form_' . $zData['type'] . '.png" alt="' . $zData['type'] . '" title="' . $zData['type'] . '" width="16" height="16">';
        } else {
            $zData['announcement'] = html_entity_decode($zData['announcement']);
        }
        $zData['lovd_read_only_'] = '<IMG src="gfx/mark_' . (int) $zData['lovd_read_only'] . '.png" alt="" width="11" height="11">';

        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['start_date'] = date('Y-m-d H:i:s');
        $_POST['end_date'] = '9999-12-31 23:59:59';
        return true;
    }
}
?>
