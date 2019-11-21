<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-06-06
 * Modified    : 2019-11-21
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
require_once ROOT_PATH . 'class/object_summary_annotations.php';





class LOVD_SummaryAnnotationREV extends LOVD_SummaryAnnotation
{
    // This class extends the SummaryAnnotation class and it handles the Summary Annotation's Revisions.
    var $sObject = 'Summary_Annotation_REV';





    function __construct ()
    {
        // Default constructor.

        // For all the defaults.
        parent::__construct();

        $this->sTable  = 'TABLE_SUMMARY_ANNOTATIONS_REV';

        // SQL code for viewing the list of summary annotation changes
        $this->aSQLViewList['SELECT']   = 'sa.*, sa.id AS dbid, uc.name AS created_by_, ue.name AS edited_by_, ud.name AS deleted_by_ ';
        $this->aSQLViewList['FROM']     = TABLE_SUMMARY_ANNOTATIONS_REV . ' AS sa ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (sa.created_by = uc.id) 
         LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (sa.edited_by = ue.id)
         LEFT OUTER JOIN ' . TABLE_USERS . ' AS ud ON (sa.deleted_by = ud.id)';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
            array(
                'id' => array(
                    'view' => false,
                    'db'   => array('sa.id', 'ASC', true)),
            ),
            $this->buildViewList(),
            array(
                'effectid_' => array(
                    'view' => array('Effect', 110),
                    'db'   => array('sa.effectid', 'DESC', true),
                    'legend' => array('The variant effect')),
                'created_by_' => array(
                    'view' => array('Added By', 110),
                    'db'   => array('uc.name', 'DESC', true),
                    'legend' => array('The user created this summary annotation record.')),
                'created_date' => array(
                    'view' => array('Added Date', 110),
                    'db'   => array('sa.created_date', 'DESC', true),
                    'legend' => array('The date this summary annotation record was created.')),
                'edited_by_' => array(
                    'view' => array('Edited By', 110),
                    'db'   => array('ue.name', 'DESC', true),
                    'legend' => array('The user last edited this summary annotation record.')),
                'edited_date' => array(
                    'view' => array('Date edited', 110),
                    'db'   => array('sa.edited_date', 'DESC', true),
                    'legend' => array('The date the summary annotation record was last edited.')),
                'valid_from' => array(
                    'view' => array('Valid From', 110),
                    'db'   => array('sa.valid_from', 'ASC', true),
                    'legend' => array('The date this version became valid.')),
                'valid_to' => array(
                    'view' => array('Valid to', 110),
                    'db'   => array('sa.valid_to', 'DESC', true),
                    'legend' => array('The date this version was invalidated by an update.')),
                'reason' => array(
                    'view' => array('Reason', 110),
                    'db'   => array('sa.reason', 'ASC', true),
                    'legend' => array('The reason for editing or deleting this entry.')),
                'deleted_' => array(
                    'view' => array('Deleted?', 50, 'style="text-align: center;"'),
                    'db'   => array('sa.deleted', 'ASC', true),
                    'legend' => array('Whether this entry has been deleted or not.')),
                'deleted_by_' => array(
                    'view' => array('Deleted by', 110),
                    'db'   => array('ud.name', 'ASC', true),
                    'legend' => array('The user that deleted this summary annotation record.')),
            ));
        $this->sSortDefault = 'valid_from';
        $this->sRowLink = '';
    }





    function prepareData ($zData = '', $sView = 'list') {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['reason'] = str_replace("\r\n", '<BR>', $zData['reason']);
        $zData['deleted_'] = '';
        if (isset($zData['effectid']) && $zData['effectid'] != '') {
            $zData['effectid_'] = $_SETT['var_effect'][$zData['effectid']];
        } else {
            $zData['effectid_'] = '';
        }

        // Changes dependent on version.
        if ($zData['valid_to'] == '9999-12-31 00:00:00') {
            // Most current entry.
            $zData['valid_to'] = '(current)';
            $zData['class_name'] = 'colGreen';
        } elseif ($zData['deleted']) {
            // Entry has been deleted.
            $zData['deleted_'] = '<IMG src="gfx/mark_0.png">';
            $zData['class_name'] = 'colRed';
        } elseif ($zData['created_date'] != $zData['valid_from']) {
            // Updated entry.
            $zData['class_name'] = 'colOrange';
        } else {
            // Created entry (not the most current one).
            $zData['class_name'] = 'del';
        }

        return $zData;
    }
}
?>
