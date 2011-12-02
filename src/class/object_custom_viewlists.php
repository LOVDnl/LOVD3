<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-15
 * Modified    : 2011-12-01
 * For LOVD    : 3.0-alpha-07
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_CustomViewList extends LOVD_Object {
    // This class extends the basic Object class and it handles pre-configured custom viewLists.
    var $sObject = 'Custom_ViewList';
    var $aColumns = array();
    var $aCustomLinks = array();
    var $nCount = 0; // Necessary for tricking Objects::getCount() that is run in viewList().





    function __construct ($aObjects = array())
    {
        // Default constructor.
        global $_DB, $_AUTH;
        //$_SETT, $nID;

        if (!is_array($aObjects)) {
            $aObjects = explode(',', $aObjects);
        }
        $this->sObjectID = implode(',', $aObjects);

        $aSQL = $this->aSQLViewList;
        // Loop requested data types, and keep columns in order indicated by request.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'Transcript':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 't.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_TRANSCRIPTS . ' AS t';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_TRANSCRIPTS)->fetchColumn();
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (';
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.transcriptid = t.id)';
                        } else {
                            // Fallback so not to have a failed query, but this will be weird.
//                            $aSQL['FROM'] .= '1=1)';
// However, since we're testing, I WANT an error.
                            $aSQL['FROM'] .= ')';
                        }
                    }
                    break;

                case 'VariantOnGenome':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vog.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_VARIANTS . ' AS vog';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS)->fetchColumn();
                        $aSQL['ORDER_BY'] = 'vog.chromosome ASC, vog.position_start';
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (';
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.id = vog.id)';
                        } else {
                            // Fallback so not to have a failed query, but this will be weird.
//                            $aSQL['FROM'] .= '1=1)';
// However, since we're testing, I WANT an error.
                            $aSQL['FROM'] .= ')';
                        }
                    }
                    break;

                case 'VariantOnTranscript':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vot.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchColumn();
//                        $aSQL['ORDER_BY'] = 'vot.id_sort';
                        $aSQL['ORDER_BY'] = 'vot.position_c_start, vot.position_c_start_intron';
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (';
                        $nKeyT   = array_search('Transcript', $aObjects);
                        $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                        if ($nKeyT !== false && $nKeyT < $nKey) {
                            // Earlier, T was used, join to that.
                            $aSQL['FROM'] .= 't.id = vot.transcriptid)';
                            // Nice, but if we're showing transcripts and variants on transcripts in one viewList, we'd only want to see the transcripts that HAVE variants.
                            $aSQL['WHERE'] .= (!$aSQL['WHERE']? '' : ' AND ') . 'vot.id IS NOT NULL';
                        } elseif ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                            // Earlier, VOG was used, join to that.
                            $aSQL['FROM'] .= 'vog.id = vot.id)';
                        } else {
                            // Fallback so not to have a failed query, but this will be weird.
//                            $aSQL['FROM'] .= '1=1)';
// However, since we're testing, I WANT an error.
                            $aSQL['FROM'] .= ')';
                        }
                    }
                    break;
            }
        }      
        if (!$aSQL['SELECT'] || !$aSQL['FROM']) {
            // Apparently, not implemented or no objects given.
            lovd_displayError('ObjectError', 'CustomViewLists::__construct() requested with non-existing or missing object(s) \'' . htmlspecialchars(implode(',', $aObjects)) . '\'.');
        }
        $this->aSQLViewList = $aSQL;



        // Collect custom column information, all active columns.
        $sSQL = 'SELECT c.id, c.width, c.head_column, c.mysql_type FROM ' . TABLE_ACTIVE_COLS . ' AS ac LEFT OUTER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) ' .
                    'WHERE ' . ($_AUTH['level'] >= LEVEL_MANAGER? '' : 'c.public_view = 1 AND ') . '(c.id LIKE ?' . str_repeat(' OR c.id LIKE ?', count($aObjects)-1) . ') ORDER BY c.col_order';
        $aSQL = array();
        foreach ($aObjects as $sObject) {
            $aSQL[] = $sObject . '/%';
        }
        $q = $_DB->query($sSQL, $aSQL);
        while ($z = $q->fetchAssoc()) {
            $z['custom_links'] = array();
            $this->aColumns[$z['id']] = $z;
        }



        if ($this->sObjectID == 'Transcript,VariantOnTranscript,VariantOnGenome') {
            // The joining of the tables needed for this view are in this order, but I want a different order on display.
            $aObjects = array('Transcript', 'VariantOnGenome', 'VariantOnTranscript');
        }



        // Now build $this->aColumnsViewList, from the order given by $aObjects and TABLE_COLS.col_order.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'Transcript':
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'geneid' => array(
                                        'view' => array('Gene', 70),
                                        'db'   => array('geneid', 'ASC', 'TEXT')),
                                'id_ncbi' => array(
                                        'view' => array('Transcript', 120),
                                        'db'   => array('id_ncbi', 'ASC', 'TEXT')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'geneid';
                    }
                    break;

                case 'VariantOnGenome':
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'chromosome' => array(
                                        'view' => array('Chr', 50),
                                        'db'   => array('vog.chromosome', 'ASC', 'TEXT')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = ''; // FIXME; how will we fake sorting on DNA, while in fact we'll sort on something else? Objects.php?
                    }
                    $this->sRowLink = 'variants/{{zData_id}}#{{zData_transcriptid}}';
                    break;

                case 'VariantOnTranscript':
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => array('TranscriptID', 50),
                                        'db'   => array('vot.transcriptid', 'ASC', 'INT_UNSIGNED')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnTranscript/DNA'; // FIXME; how will we fake sorting on DNA, while in fact we'll sort on something else? Objects.php?
                    }
                    break;
            }

            // The custom columns.
            foreach ($this->aColumns as $sColID => $aCol) {
                if (strpos($sColID, $sObject . '/') === 0) {
                    $this->aColumnsViewList[$sColID] =
                         array(
                                'view' => array($aCol['head_column'], $aCol['width']),
                                'db'   => array('`' . $aCol['id'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
                              );
                }
            }
        }



        // Gather the custom link information. It's just easier to load all custom links, instead of writing code that checks for the appropiate objects.
        $aLinks = $_DB->query('SELECT l.*, GROUP_CONCAT(c2l.colid SEPARATOR ";") AS colids FROM ' . TABLE_LINKS . ' AS l INNER JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid) GROUP BY l.id')->fetchAllAssoc();
        foreach ($aLinks as $aLink) {
            $aLink['regexp_pattern'] = '/' . str_replace(array('{', '}'), array('\{', '\}'), preg_replace('/\[\d\]/', '(.*)', $aLink['pattern_text'])) . '/';
            $aLink['replace_text'] = preg_replace('/\[(\d)\]/', '\$$1', $aLink['replace_text']);
            $aCols = explode(';', $aLink['colids']);
            foreach ($aCols as $sColID) {
                if (isset($this->aColumns[$sColID])) {
                    $this->aColumns[$sColID]['custom_links'][] = $aLink['id'];
                }
            }
            $this->aCustomLinks[$aLink['id']] = $aLink;
        }

        // Not including parent constructor, because these table settings will make it freak out.
        //parent::__construct();
        // Therefore, row links need to be created by us (which is done above).
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        foreach ($this->aColumns as $sCol => $aCol) {
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
        return $zData;
    }
}
?>
