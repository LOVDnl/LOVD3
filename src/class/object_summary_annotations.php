<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-12
 * Modified    : 2019-11-20
 * For LOVD    : 3.0-23
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_SummaryAnnotation extends LOVD_Custom
{
    // This class extends the Custom class and it handles the Summary Annotations.
    var $sObject = 'Summary_Annotation';
    var $sCategory = 'SummaryAnnotation';
    var $sTable = 'TABLE_SUMMARY_ANNOTATIONS';





    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'sa.*, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SUMMARY_ANNOTATIONS . ' AS sa ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (sa.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (sa.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'sa.id';

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'TableHeader_General' => 'Summary annotations',
                        'effectid' => 'Classification',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                      ));
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.

        // Mandatory fields.
        $this->aCheckMandatory =
            array(
                'effectid',
            );
        parent::checkFields($aData, $zData, $aOptions);

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }
        global $_SETT;

        // Manipulate the effectid list to remove the "Not curated" option, and make the field mandatory.
        $aEffectIDs = $_SETT['var_effect'];
        unset($aEffectIDs[0]);


        $this->aFormData = array_merge(
            array(
                array('POST', '', '', '', '50%', '14', '50%'),
                array('Classification', '', 'select', 'effectid', 1, $aEffectIDs, true, false, false),
            ),
            $this->buildForm(),
            array(
                'skip',
  'password' => array('Enter your password for authorization', '', 'password', 'password', 20),
            )
        );

        if (ACTION == 'create') {
            // When creating, unset the password field.
            unset($this->aFormData['password']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            if ($zData['effectid'] != '') {
                // Replace the effectid with the effect text if it has been set.
                $zData['effectid'] = $_SETT['var_effect'][$zData['effectid']];
            }
        }

        return $zData;
    }
}
?>
