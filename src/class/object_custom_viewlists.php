<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-15
 * Modified    : 2012-01-27
 * For LOVD    : 3.0-beta-01
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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





class LOVD_CustomViewList extends LOVD_Object {
    // This class extends the basic Object class and it handles pre-configured custom viewLists.
    var $sObject = 'Custom_ViewList';
    var $aColumns = array();
    var $aCustomLinks = array();
    var $nCount = 0; // Necessary for tricking Objects::getCount() that is run in viewList().





    function __construct ($aObjects = array(), $sGene = '')
    {
        // Default constructor.
        global $_DB, $_AUTH;

        if (!is_array($aObjects)) {
            $aObjects = explode(',', $aObjects);
        }
        $this->sObjectID = implode(',', $aObjects);



        // Collect custom column information, all active columns (possibly restricted per gene).
        $sSQL = 'SELECT c.id, c.width, c.head_column, c.mysql_type, c.col_order FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) ' .
                    'WHERE ' . ($_AUTH['level'] >= LEVEL_MANAGER? '' : 'c.public_view = 1 AND ') . '(c.id LIKE ?' . str_repeat(' OR c.id LIKE ?', count($aObjects)-1) . ') ' .
                    (!$sGene? '' :
                      // If gene is given, only shown VOT columns active in the given gene! We'll use an UNION for that, so that we'll get the correct width and order also.
                      'AND c.id NOT LIKE "VariantOnTranscript/%" ' .
                      'UNION ' .
                      'SELECT c.id, sc.width, c.head_column, c.mysql_type, sc.col_order FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (c.id = sc.colid) WHERE sc.geneid = ? ' .
                      ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'AND sc.public_view = 1 ')) .
                    'ORDER BY col_order';
        $aSQL = array();
        foreach ($aObjects as $sObject) {
            $aSQL[] = $sObject . '/%';
        }
        if ($sGene) {
            $aSQL[] = $sGene;
        }
        $q = $_DB->query($sSQL, $aSQL);
        while ($z = $q->fetchAssoc()) {
            $z['custom_links'] = array();
            $this->aColumns[$z['id']] = $z;
        }



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
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    break;

                case 'VariantOnGenome':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vog.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= ', vog.id AS row_id'; // To ensure other table's id columns don't interfere.
                        $aSQL['FROM'] = TABLE_VARIANTS . ' AS vog';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
                        $aSQL['ORDER_BY'] = 'vog.chromosome ASC, vog.position_start';
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (';
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.id = vog.id)';
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    // If no collaborator, hide lines with hidden variants!
                    if ($_AUTH['level'] < LEVEL_COLLABORATOR) {
                        $aSQL['WHERE'] .= (!$aSQL['WHERE']? '' : ' AND ') . 'vog.statusid >= ' . STATUS_MARKED;
                    }
                    break;

                case 'VariantOnTranscript':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vot.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= ', vot.id AS row_id'; // To ensure other table's id columns don't interfere.
                        $aSQL['FROM'] = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vot.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
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
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    break;

                case 'Screening':
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 's.*';
                        $aSQL['FROM'] = TABLE_SCREENINGS . ' AS s';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS)->fetchColumn();
                        $aSQL['ORDER_BY'] = 's.id';
                    } else {
                        // SELECT will be different: we will GROUP_CONCAT the whole lot, per column.
                        $sGCOrderBy = (isset($this->aColumns['Screening/Date'])? '`Screening/Date`' : 'id');
                        foreach ($this->aColumns as $sCol => $aCol) {
                            if (substr($sCol, 0, 9) == 'Screening') {
                                $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT `' . $sCol . '` ORDER BY s.' . $sGCOrderBy . ' SEPARATOR ";") AS `' . $sCol . '`';
                            }
                        }
                        $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        $nKeyI   = array_search('Individual', $aObjects);
                        if ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                            // Earlier, VOG was used, join to that.
                            $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)';
                        } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)';
                        } elseif ($nKeyI !== false && $nKeyI < $nKey) {
                            // Earlier, I was used, join to that.
                            $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid)';
                        }
                        // We have no fallback, so it won't join if we messed up somewhere!
                    }
                    break;

                case 'Individual':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'i.*, GROUP_CONCAT(DISTINCT d.symbol ORDER BY d.symbol SEPARATOR ", ") AS diseases_';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_INDIVIDUALS . ' AS i';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS)->fetchColumn();
                        $aSQL['ORDER_BY'] = 'i.id';
                        // If no manager, hide lines with hidden individuals (not specific to a gene)!
                        if ($_AUTH['level'] < LEVEL_MANAGER) {
                            $aSQL['WHERE'] .= (!$aSQL['WHERE']? '' : ' AND ') . 'i.statusid >= ' . STATUS_MARKED;
                        }
                    } else {
                        $nKeyS   = array_search('Screening', $aObjects);
                        $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyS === false || $nKeyS > $nKey) {
                            // S was not used yet, join to something else first!
                            if ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                                // Earlier, VOG was used, join to that.
                                $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)';
                            } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                                // Earlier, VOT was used, join to that.
                                $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id)';
                            }
                            // We have no fallback, so it won't join if we messed up somewhere!
                        }
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id';
                        // If no collaborator, hide hidden individuals (from the join, don't hide the line)!
                        if ($_AUTH['level'] < LEVEL_COLLABORATOR) {
                            $aSQL['FROM'] .= ' AND i.statusid >= ' . STATUS_MARKED;
                        }
                        $aSQL['FROM'] .= ')';
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' .
                                     TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' .
                                     TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id)';
                    break;
            }
        }
        if (!$aSQL['SELECT'] || !$aSQL['FROM']) {
            // Apparently, not implemented or no objects given.
            lovd_displayError('ObjectError', 'CustomViewLists::__construct() requested with non-existing or missing object(s) \'' . htmlspecialchars(implode(',', $aObjects)) . '\'.');
        }
        $this->aSQLViewList = $aSQL;



        if ($this->sObjectID == 'Transcript,VariantOnTranscript,VariantOnGenome') {
            // The joining of the tables needed for this view are in this order, but I want a different order on display.
            $aObjects = array('Transcript', 'VariantOnGenome', 'VariantOnTranscript');
        }



        // Now build $this->aColumnsViewList, from the order given by $aObjects and TABLE_COLS.col_order.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'Transcript':
                    $sPrefix = 't.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'geneid' => array(
                                        'view' => array('Gene', 70),
                                        'db'   => array('t.geneid', 'ASC', 'TEXT')),
                                'id_ncbi' => array(
                                        'view' => array('Transcript', 120),
                                        'db'   => array('t.id_ncbi', 'ASC', 'TEXT')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'geneid';
                    }
                    break;

                case 'VariantOnGenome':
                    $sPrefix = 'vog.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'chromosome' => array(
                                        'view' => array('Chr', 50),
                                        'db'   => array('vog.chromosome', 'ASC', 'TEXT')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnGenome/DNA';
                    }
                    $this->sRowLink = 'variants/{{zData_row_id}}#{{zData_transcriptid}}';
                    break;

                case 'VariantOnTranscript':
                    $sPrefix = 'vot.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => array('TranscriptID', 50),
                                        'db'   => array('vot.transcriptid', 'ASC', 'INT_UNSIGNED')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnTranscript/DNA';
                    }
                    break;

                case 'Screening':
                    $sPrefix = 's.';
                    // No fixed columns.
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        // The fixed columns, only when first table.
                        $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                             array(
                                    'id' => array(
                                            'view' => array('Screening ID', 110),
                                            'db'   => array('s.id', 'ASC', 'INT_UNSIGNED')),
                                  ));
                        $this->sSortDefault = 'id';
                    }
                    break;

                case 'Individual':
                    $sPrefix = 'i.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'diseases_' => array(
                                            'view' => array('Disease', 175),
                                            'db'   => array('diseases_', false, true)),
                              ));
                    if (!$this->sSortDefault) {
                        $this->sSortDefault = 'id';
                    }
                    break;
            }

            // The custom columns.
            foreach ($this->aColumns as $sColID => $aCol) {
                if (strpos($sColID, $sObject . '/') === 0) {
                    $this->aColumnsViewList[$sColID] =
                         array(
                                'view' => array($aCol['head_column'], $aCol['width']),
                                'db'   => array($sPrefix . '`' . $aCol['id'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
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

        if ($sView == 'list') {
            // "Clean" the GROUP_CONCAT columns for double values.
            foreach ($zData as $sCol => $sVal) {
                if (strpos($sCol, 'Screening/') === 0) {
                    $zData[$sCol] = implode(', ', array_unique(explode(';', $sVal)));
                }
            }
        }

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
