<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-28
 * Modified    : 2015-10-30
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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





class LOVD_Log extends LOVD_Object {
    // This class extends the basic Object class and it handles the Log object.
    var $sObject = 'Log';





    function __construct ()
    {
        // Default constructor.

        // SQL code for viewing a list of entries.
        $this->aSQLViewList['SELECT']   = 'l.*, CONCAT_WS(",", l.name, l.date, l.mtime) AS row_id, "" AS row_link, CONCAT(l.date, " ", l.mtime) AS timestamp, u.name AS user';
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
                                    'db'   => array('l.date', 'DESC', 'DATETIME')),
                        'userid' => array(
                                    'view' => false,
                                    'db'   => array('l.userid', 'ASC', true)),
                        'user_' => array(
                                    'view' => array('User', 160),
                                    'db'   => array('u.name', 'ASC', true)),
                        'event' => array(
                                    'view' => array('Event', 100),
                                    'db'   => array('l.event', 'ASC', true)),
                        'del' => array(
                                    'view' => array('&nbsp;', 14, 'style="text-align : center;"')),
                        'entry' => array(
                                    'view' => array('Entry', 700),
                                    'db'   => array('l.log', false, true)),
                      );
        $this->sSortDefault = 'date';

        parent::__construct();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['user_'] = '<A href="users/' . $zData['userid'] . '">' . $zData['user'] . '</A>';
        $zData['del'] = '<A href="#" onclick="lovd_AJAX_deleteLogEntry(\'Logs\', \'' . $zData['row_id'] . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Delete" title="Delete" width="11" height="11" style="margin-top : 3px;"/></A>';
        $zData['entry'] = str_replace(array("\r\n", "\r", "\n"), '<BR/>', $zData['log']);
        $zData['entry'] = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $zData['entry']);

        // 2013-09-30; 3.0-08; Make log entry texts clickable.
        switch ($zData['event']) {
            case 'ColCreate':
                $zData['entry'] = preg_replace('/(link) ([0-9]+) /', '$1 <A href="links/$2">$2</A> ', $zData['entry']);
            case 'ColAdd':
            case 'ColEdit':
            case 'ColRemove':
                $zData['entry'] = preg_replace('/olumn ([A-Z][A-Za-z0-9\/_]+) /', 'olumn <A href="columns/$1">$1</A> ', $zData['entry']);
                break;
            case 'DiseaseCreate':
            case 'DiseaseEdit':
                $zData['entry'] = preg_replace('/(disease|entry) ([0-9]+)( .+)?$/', '$1 <A href="diseases/$2">$2</A>$3', $zData['entry']);
                break;
            case 'CuratorAuthorize':
            case 'CuratorSort':
            case 'GeneCreate':
            case 'GeneEdit':
            case 'GeneEmpty':
            case 'TranscriptCreate':
                $zData['entry'] = preg_replace('/(database|entry|gene|the) ([A-Z][A-Za-z0-9-]+)([ ;].+)?$/', '$1 <A href="genes/$2">$2</A>$3', $zData['entry']);
                break;
            case 'IndividualCreate':
            case 'IndividualEdit':
                $zData['entry'] = preg_replace('/(entry|individual) ([0-9]{8})( .+)?$/', '$1 <A href="individuals/$2">$2</A>$3', $zData['entry']);
                break;
            case 'LinkCreate':
            case 'LinkEdit':
                $zData['entry'] = preg_replace('/(link) ([0-9]+) /', '$1 <A href="links/$2">$2</A> ', $zData['entry']);
                break;
            case 'PhenotypeCreate':
                $zData['entry'] = preg_replace('/(entry) ([0-9]+) /', '$1 <A href="phenotypes/$2">$2</A> ', $zData['entry']);
                $zData['entry'] = preg_replace('/(individual) ([0-9]+) /', '$1 <A href="individuals/$2">$2</A> ', $zData['entry']);
                $zData['entry'] = preg_replace('/(disease) ([0-9]+)$/', '$1 <A href="diseases/$2">$2</A>', $zData['entry']);
                break;
            case 'PhenotypeEdit':
                $zData['entry'] = preg_replace('/(entry) ([0-9]+)$/', '$1 <A href="phenotypes/$2">$2</A>', $zData['entry']);
                break;
            case 'ScreeningCreate':
            case 'ScreeningEdit':
            case 'VariantConfirm':
            case 'VariantRemove':
                $zData['entry'] = preg_replace('/(entry|screening) (#)?([0-9]+)$/', '$1 $2<A href="screenings/$3">$3</A>', $zData['entry']);
                break;
            case 'TranscriptEdit':
                $zData['entry'] = preg_replace('/(entry) (#)?([0-9]+) /', '$1 $2<A href="transcripts/$3">$3</A> ', $zData['entry']);
                break;
            case 'UserBoot':
            case 'UserCreate':
            case 'UserEdit':
            case 'UserLock':
            case 'UserRegister':
            case 'UserResetPassword':
            case 'UserUnlock':
                $zData['entry'] = preg_replace('/(ID|user) (#)?([0-9]+)( .+)?$/', '$1 $2<A href="users/$3">$3</A>$4', $zData['entry']);
                break;
            case 'VariantCreate':
            case 'VariantEdit':
            case 'VariantMap':
                $zData['entry'] = preg_replace('/(entry|variant) (#)?([0-9]+)( .+)?$/', '$1 $2<A href="variants/$3">$3</A>$4', $zData['entry']);
                break;
        }

        return $zData;
    }
}
?>
