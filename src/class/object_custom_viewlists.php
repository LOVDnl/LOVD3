<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-08-15
 * Modified    : 2018-01-02
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
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





class LOVD_CustomViewList extends LOVD_Object {
    // This class extends the basic Object class and it handles pre-configured custom viewLists.
    var $sObject = 'Custom_ViewList';
    var $nOtherID = 0; // Some objects (like DistanceToVar) need an additional ID.
    var $aColumns = array();
    var $aCustomLinks = array();
    var $nCount = 0; // Necessary for tricking Objects::getCount() that is run in viewList().





    function __construct ($aObjects = array(), $sOtherID = '')
    {
        // Default constructor.
        global $_AUTH, $_CONF, $_DB, $_SETT;

        if (!is_array($aObjects)) {
            $aObjects = explode(',', $aObjects);
        }
        $this->sObjectID = implode(',', $aObjects);
        // Receive OtherID or Gene.
        if (ctype_digit($sOtherID)) {
            $sGene = '';
            $this->nOtherID = $sOtherID;
        } else {
            $sGene = $sOtherID;
        }


        // FIXME: Disable this part when not using any of the custom column data types...
        // Collect custom column information, all active columns (possibly restricted per gene).
        // FIXME; This join is not always needed (it's done for VOT columns, but sometimes they are excluded, or the join is not necessary because of the user level), exclude when not needed to speed up the query?
        //   Also, the select of public_view makes no sense of VOTs are restricted by gene.
        // Note: objects inheriting LOVD_custom implement selection of
        //  viewable columns in buildViewList().
        $sSQL = 'SELECT c.id, c.width, c.head_column, c.description_legend_short, c.description_legend_full, c.mysql_type, c.form_type, c.select_options, c.col_order, GROUP_CONCAT(sc.geneid, ":", sc.public_view SEPARATOR ";") AS public_view FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) LEFT OUTER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = ac.colid) ' .
                    'WHERE ' . ($_AUTH['level'] >= ($sGene? LEVEL_COLLABORATOR : LEVEL_MANAGER)? '' : '((c.id NOT LIKE "VariantOnTranscript/%" AND c.public_view = 1) OR sc.public_view = 1) AND ') . '(c.id LIKE ?' . str_repeat(' OR c.id LIKE ?', count($aObjects)-1) . ') ' .
                    (!$sGene? 'GROUP BY c.id ' :
                      // If gene is given, only shown VOT columns active in the given gene! We'll use an UNION for that, so that we'll get the correct width and order also.
                      'AND c.id NOT LIKE "VariantOnTranscript/%" GROUP BY c.id ' . // Exclude the VOT columns from the normal set, we'll load them below.
                      'UNION ' .
                      'SELECT c.id, sc.width, c.head_column, c.description_legend_short, c.description_legend_full, c.mysql_type, c.form_type, c.select_options, sc.col_order, CONCAT(sc.geneid, ":", sc.public_view) AS public_view FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (c.id = sc.colid) WHERE sc.geneid = ? ' .
                      ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'AND sc.public_view = 1 ')) .
                    'ORDER BY col_order';
        $aSQL = array();
        foreach ($aObjects as $sObject) {
            $aSQL[] = $sObject . '/%';
        }
        if ($sGene) {
            $aSQL[] = $sGene;
        }
        if ($sOtherID) {
            $this->nID = $sOtherID; // We need the AJAX script to have the same restrictions!!!
        }

        // Increase the max GROUP_CONCAT() length, so that lists of many many genes still have all genes mentioned here (22.000 genes take 193.940 bytes here).
        $_DB->query('SET group_concat_max_len = 200000');
        $q = $_DB->query($sSQL, $aSQL);
        while ($z = $q->fetchAssoc()) {
            $z['custom_links'] = array();
            $z['form_type'] = explode('|', $z['form_type']);
            $z['select_options'] = explode("\r\n", $z['select_options']); // What do we use this for?
            if (substr($z['id'], 0,19) == 'VariantOnTranscript') {
                $z['public_view'] = explode(';', rtrim(preg_replace('/([A-Za-z0-9-]+:0;|:1)/', '', $z['public_view'] . ';'), ';'));
            }
            if (is_null($z['public_view'])) {
                $z['public_view'] = array();
            }
            $this->aColumns[$z['id']] = $z;
        }
        if ($_AUTH) {
            $_AUTH['allowed_to_view'] = array_merge($_AUTH['curates'], $_AUTH['collaborates']);
        }

        // Boolean indicating whether row_id is already in SELECT statement somewhere.
        $bSetRowID = false;
        $aSQL = $this->aSQLViewList;

        // If possible, determine row_id SQL from combination of object types.
        $sRowIDSQL = null;
        if (in_array('VariantOnGenome', $aObjects) && (in_array('VariantOnTranscript', $aObjects) ||
                in_array('VariantOnTranscriptUnique', $aObjects))) {
            // Use "vog.id:vot.transcriptid" as row_id, fall back to "vog.id" if there is no VOT entry.
            $aSQL['SELECT'] = 'CONCAT(CAST(MIN(vog.id) AS UNSIGNED), IFNULL(CONCAT(":", CAST(MIN(vot.transcriptid) AS UNSIGNED)), "")) AS row_id';
            $bSetRowID = true;
        } elseif (in_array('Transcript', $aObjects)) {
            $aSQL['SELECT'] = 'MIN(t.id) AS row_id';
            $bSetRowID = true;
        }

        // Loop requested data types, and keep columns in order indicated by request.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'Gene':
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= (!$aSQL['SELECT'] ? '' : ', ') . 'MIN(g.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_GENES . ' AS g';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES)->fetchColumn();
                    }
                    break;

                case 'Transcript':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT'] ? '' : ', ') . 'MIN(t.id) AS tid, ' .
                        'MIN(t.geneid) AS geneid, MIN(t.name) AS name, MIN(t.id_ncbi) AS id_ncbi, MIN(t.id_protein_ncbi) AS id_protein_ncbi';
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= ', MIN(t.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_TRANSCRIPTS . ' AS t';
                        // MAX(id) gets nowhere nearly the correct number of results when data has been removed or archived, but this number
                        //  anyway doesn't need to be accurate at all, and a COUNT(*) can be too slow on large systems running InnoDB.
                        $this->nCount = $_DB->query('SELECT MAX(id) FROM ' . TABLE_TRANSCRIPTS)->fetchColumn();
                    } else {
                        $aSQL['FROM'] .= ' INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (';
                        $nKeyG   = array_search('Gene', $aObjects);
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyG !== false && $nKeyG < $nKey) {
                            // Earlier, Gene was used, join to that.
                            $aSQL['FROM'] .= 'g.id = t.geneid)';
                            // A view with gene info and transcript info will be grouped on the transcripts.
                            $aSQL['GROUP_BY'] = 't.id';
                        } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.transcriptid = t.id)';
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    break;

                case 'DistanceToVar':
                    $nKeyT = array_search('Transcript', $aObjects);
                    if ($nKeyT !== false && $nKeyT < $nKey && $this->nOtherID) {
                        // Earlier, Transcript was used, join to that.
                        // First, retrieve information of variant.
                        list($nPosStart, $nPosEnd) = $_DB->query('SELECT position_g_start, position_g_end FROM ' . TABLE_VARIANTS . ' WHERE id = ?', array($this->nOtherID))->fetchRow();
                        // Specific modifications for this overview; distance between variant and transcript in question.
                        if ($nPosStart && $nPosEnd) {
                            // 2014-08-11; 3.0-12; Transcripts on the reverse strand did not display the correctly calculated distance.
                            $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'IF(MAX(t.position_g_mrna_start) < MAX(t.position_g_mrna_end), IF(MAX(t.position_g_mrna_start) > ' . $nPosEnd . ', MAX(t.position_g_mrna_start) - ' . $nPosEnd . ', IF(MAX(t.position_g_mrna_end) < ' . $nPosStart . ', ' . $nPosStart . ' - MAX(t.position_g_mrna_start), 0)), IF(MAX(t.position_g_mrna_end) > ' . $nPosEnd . ', MAX(t.position_g_mrna_end) - ' . $nPosEnd . ', IF(MAX(t.position_g_mrna_start) < ' . $nPosStart . ', ' . $nPosStart . ' - MAX(t.position_g_mrna_end), 0))) AS distance_to_var';
                        } else {
                            $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . '"?" AS distance_to_var';
                        }
                    }
                    break;

                case 'VariantOnGenome':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'MIN(vog.id) AS vogid, MIN(vog.chromosome) AS chromosome, MIN(a.name) AS allele_' . (!in_array('VariantOnTranscript', $aObjects)? ', MIN(eg.name) AS vog_effect' : '') .
                                       (in_array('Individual', $aObjects) || in_array('VariantOnTranscriptUnique', $aObjects)? '' : ', MIN(uo.name) AS owned_by_, CONCAT_WS(";", MIN(uo.id), MIN(uo.name), MIN(uo.email), MIN(uo.institute), MIN(uo.department), IFNULL(MIN(uo.countryid), "")) AS _owner') . (in_array('VariantOnTranscriptUnique', $aObjects)? '' : ', MIN(dsg.id) AS var_statusid, MIN(dsg.name) AS var_status');
                    $nKeyVOTUnique = array_search('VariantOnTranscriptUnique', $aObjects);
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= ', MIN(vog.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_VARIANTS . ' AS vog';
                        // MAX(id) gets nowhere nearly the correct number of results when data has been removed or archived, but this number
                        //  anyway doesn't need to be accurate at all, and a COUNT(*) can be too slow on large systems running InnoDB.
                        $this->nCount = $_DB->query('SELECT MAX(id) FROM ' . TABLE_VARIANTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
                        $aSQL['ORDER_BY'] = 'MIN(vog.chromosome) ASC, MIN(vog.position_g_start)';
                    } elseif ($nKeyVOTUnique !== false && $nKeyVOTUnique < $nKey) {
                        // For the unique variant view a GROUP_CONCAT must be done for the variantOnGenome fields.
                        foreach ($this->getCustomColsForCategory('VariantOnGenome') as $sCol => $aCol) {
                            // Here all VariantOnGenome columns are grouped with GROUP_CONCAT. In prepareData(),
                            // these fields are exploded and the elements are counted, limiting the grouped values
                            // to a certain length. To recognize the separate items, ;; is used as a separator.
                            // The NULLIF() is used to not show empty values. GROUP_CONCAT handles NULL values well (ignores them), but not empty values (includes them).
                            $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT NULLIF(`' . $sCol . '`, "") SEPARATOR ";;") AS `' . $sCol . '`';
                        }
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id)';
                    } else {
                        // 2016-07-20; 3.0-17; Added FORCE INDEX because MySQL optimized the Full data view
                        // differently, resulting in immense temporary tables filling up the disk.
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS . ' AS vog FORCE INDEX FOR JOIN (PRIMARY) ON (';
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.id = vog.id)';
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }

                    // Add any missing custom cols.
                    if (($sCustomCols = $this->getCustomColQuery($sObject, $aSQL['SELECT'])) != '') {
                        $aSQL['SELECT'] .= ', ' . $sCustomCols;
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id)';
                    if (!in_array('VariantOnTranscript', $aObjects)) {
                        $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS eg ON (vog.effectid = eg.id)';
                    }
                    if (!in_array('Individual', $aObjects)) {
                        $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id)';
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsg ON (vog.statusid = dsg.id)';
                    // If user level not high enough, hide lines with hidden variants!
                    if ($_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) {
                        // Construct list of user IDs for current user and users who share access with him.
                        $aOwnerIDs = array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL));
                        $sOwnerIDsSQL = join(', ', $aOwnerIDs);

                        $aSQL['WHERE'] .= (!$aSQL['WHERE']? '' : ' AND ') . '(vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by IN (' . $sOwnerIDsSQL . ')') . ')';
                    }
                    break;

                case 'VariantOnTranscript':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'MIN(vot.id) AS votid, MIN(vot.transcriptid) AS transcriptid, MAX(vot.position_c_start) AS position_c_start, MAX(vot.position_c_start_intron) AS position_c_start_intron, MAX(vot.position_c_end) AS position_c_end, MAX(vot.position_c_end_intron) AS position_c_end_intron, MIN(et.name) as vot_effect';
                    $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= ', MIN(vot.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';
                        // MAX(id) on the VOT table gets nowhere nearly the correct number of results, but this number
                        //  anyway doesn't need to be accurate at all, and a COUNT(*) can be too slow on large systems running InnoDB.
                        $this->nCount = $_DB->query('SELECT MAX(id) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vot.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
                    } elseif ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                        // Previously, VOG was used. We will join VOT with VOG, using GROUP_CONCAT.
                        // SELECT will be different: we will GROUP_CONCAT the whole lot, per column.
                        // Sort GROUP_CONCAT() based on transcript name. We'll have to join Transcripts for that.
                        //   That will break if somebody wants to join transcripts themselves, but why would somebody want that?
                        $sGCOrderBy = 't.geneid, t.id_ncbi';
                        foreach ($this->getCustomColsForCategory('VariantOnTranscript') as $sCol => $aCol) {
                            $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT ' . ($sCol != 'VariantOnTranscript/DNA'? '`' . $sCol . '`' : 'CONCAT(t.id_ncbi, ":", `' . $sCol . '`)') . ' ORDER BY ' . $sGCOrderBy . ' SEPARATOR ", ") AS `' . $sCol . '`';
                        }
                        // If we're joining to Scr2Var, we're showing the Individual- and Screening-specific views, and we want to show a gene as well.
                        //   We can't use _geneid below, because LOVD will explode that into an array.
                        if (array_search('Scr2Var', $aObjects) !== false) {
                            $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT t.geneid ORDER BY ' . $sGCOrderBy . ' SEPARATOR ", ") AS genes';
                        }
                        // Security checks in this file's prepareData() need geneid to see if the column in question is set to non-public for one of the genes.
                        $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS _geneid';
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (';
                        // Earlier, VOG was used, join to that.
                        $aSQL['FROM'] .= 'vog.id = vot.id)';
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)';
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (';
                        $nKeyT   = array_search('Transcript', $aObjects);
                        if ($nKeyT !== false && $nKeyT < $nKey) {
                            // Earlier, T was used, join to that.
                            $aSQL['FROM'] .= 't.id = vot.transcriptid)';
                            // Nice, but if we're showing transcripts and variants on transcripts in one viewList, we'd only want to see the transcripts that HAVE variants.
                            $aSQL['WHERE'] .= (!$aSQL['WHERE']? '' : ' AND ') . 'vot.id IS NOT NULL';
                            // Then also make sure we group on the VOT's ID, unless we're already grouping on something.
                            if (!$aSQL['GROUP_BY']) {
                                // t.geneid needs to be included because we order on this as well (otherwise, we could have used t.id).
                                $aSQL['GROUP_BY'] = 't.geneid, vot.id';
                            }
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }

                    // Add any missing custom columns.
                    if (($sCustomCols = $this->getCustomColQuery($sObject, $aSQL['SELECT'])) != '') {
                        $aSQL['SELECT'] .= ', ' . $sCustomCols;
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id)';
                    break;

                case 'VariantOnTranscriptUnique':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'MIN(vot.id) AS votid, MIN(vot.transcriptid), ' . // To ensure other table's id columns don't interfere.
                                      'vot.position_c_start, vot.position_c_start_intron, ' .
                                      'vot.position_c_end, vot.position_c_end_intron';
                    // To group variants together that belong together (regardless of minor textual differences, we replace parentheses, remove the "c.", and trim for question marks.
                    // This notation will be used to group on, and search on when navigating from the unique variant view to the full variant view.
                    $aSQL['SELECT'] .= ', TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", ""))) AS vot_clean_dna_change' .
                                       ', GROUP_CONCAT(DISTINCT et.name SEPARATOR ", ") AS vot_effect' .
                                       ', GROUP_CONCAT(DISTINCT NULLIF(uo.name, "") SEPARATOR ", ") AS owned_by_' .
                                       ', GROUP_CONCAT(DISTINCT CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) SEPARATOR ";;") AS __owner';
                    // dsg.id GROUP_CONCAT is ascendingly ordered. This is done for the color marking.
                    // In prepareData() the lowest var_statusid is used to determine the coloring.
                    $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT NULLIF(dsg.id, "") ORDER BY dsg.id ASC SEPARATOR ", ") AS var_statusid, GROUP_CONCAT(DISTINCT NULLIF(dsg.name, "") SEPARATOR ", ") AS var_status' .
                                       ', COUNT(`VariantOnTranscript/DNA`) AS vot_reported';
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= ', MIN(vot.id) AS row_id';
                        $bSetRowID = true;
                    }
                    $aSQL['FROM'] = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';

                    // $this->nCount = $_DB->query('SELECT COUNT(DISTINCT `VariantOnTranscript/DNA`) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchColumn();
                    // No longer calculate the number of results; this number anyway doesn't need to be correct at all,
                    //  and this query is too slow on large systems running InnoDB.
                    $this->nCount = $_DB->query('SELECT 1 FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' LIMIT 1')->fetchColumn();

                    $aSQL['GROUP_BY'] = '`position_c_start`, `position_c_start_intron`, `position_c_end`, `position_c_end_intron`, vot_clean_dna_change'; // Necessary for GROUP_CONCAT(), such as in Screening.

                    foreach ($this->getCustomColsForCategory('VariantOnTranscript') as $sCol => $aCol) {
                        // Here all VariantOnTranscript columns are grouped with GROUP_CONCAT. In prepareData(),
                        // these fields are exploded and the elements are counted, limiting the grouped values
                        // to a certain length. To recognize the separate items, ;; is used as a separator.
                        // The NULLIF() is used to not show empty values. GROUP_CONCAT handles NULL values well (ignores them), but not empty values (includes them).
                        $aSQL['SELECT'] .= ', GROUP_CONCAT(DISTINCT NULLIF(`' . $sCol . '`, "") SEPARATOR ";;") AS `' . $sCol . '`';
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS et ON (vot.effectid = et.id)';
                    break;

                case 'Screening':
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= (!$aSQL['SELECT'] ? '' : ', ') . 'MIN(s.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'MIN(s.id) AS sid';
                        $aSQL['FROM'] = TABLE_SCREENINGS . ' AS s';
                        // MAX(id) gets nowhere nearly the correct number of results when data has been removed or archived, but this number
                        //  anyway doesn't need to be accurate at all, and a COUNT(*) can be too slow on large systems running InnoDB.
                        $this->nCount = $_DB->query('SELECT MAX(id) FROM ' . TABLE_SCREENINGS)->fetchColumn();
                        $aSQL['ORDER_BY'] = 's.id';
                    } else {
                        // SELECT will be different: we will GROUP_CONCAT the whole lot, per column.
                        $sGCOrderBy = (isset($this->aColumns['Screening/Date'])? '`Screening/Date`' : 'id');
                        foreach ($this->getCustomColsForCategory('Screening') as $sCol => $aCol) {
                            $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT `' . $sCol . '` ORDER BY s.' . $sGCOrderBy . ' SEPARATOR ";") AS `' . $sCol . '`';
                        }
                        // Add any missing custom columns.
                        if (($sCustomCols = $this->getCustomColQuery($sObject, $aSQL['SELECT'])) != '') {
                            $aSQL['SELECT'] .= ', ' . $sCustomCols;
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

                case 'Scr2Var':
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= (!$aSQL['SELECT'] ? '' : ', ') . 'MIN(s2v.id) AS row_id';
                        $bSetRowID = true;
                    }
                    if ($aSQL['FROM']) {
                        // Not allowed to be the first data table in query, because entries are usually grouped by the first table.
                        $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                            // Earlier, VOG was used, join to that.
                            $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)';
                            // This combination only happens when we're joining VOG to Scr2Var to VOT, to show variants in a screening or individual.
                            // Then grouping on the s2v's variant ID is faster, because we're searching on the s2v.screeningid and like this we keep
                            // the group by and the where in the same table, greatly increasing the speed of the query.
                            $aSQL['GROUP_BY'] = 's2v.variantid'; // Necessary for GROUP_CONCAT().
                        } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vot.id = s2v.variantid)';
                        }
                        // We have no fallback, so it won't join if we messed up somewhere!
                    }
                    break;

                case 'Individual':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'MIN(i.id) AS iid, MIN(i.panel_size) AS panel_size, MIN(i.owned_by) AS owned_by, GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, MIN(uo.name) AS owned_by_, CONCAT_WS(";", MIN(uo.id), MIN(uo.name), MIN(uo.email), MIN(uo.institute), MIN(uo.department), IFNULL(MIN(uo.countryid), "")) AS _owner, MIN(dsi.id) AS ind_statusid, MIN(dsi.name) AS ind_status';
                    if (!$bSetRowID) {
                        $aSQL['SELECT'] .= ', MIN(i.id) AS row_id';
                        $bSetRowID = true;
                    }
                    // Add any missing custom columns.
                    if (($sCustomCols = $this->getCustomColQuery($sObject, $aSQL['SELECT'])) != '') {
                        $aSQL['SELECT'] .= ', ' . $sCustomCols;
                    }
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_INDIVIDUALS . ' AS i';
                        // MAX(id) gets nowhere nearly the correct number of results when data has been removed or archived, but this number
                        //  anyway doesn't need to be accurate at all, and a COUNT(*) can be too slow on large systems running InnoDB.
                        $this->nCount = $_DB->query('SELECT MAX(id) FROM ' . TABLE_INDIVIDUALS)->fetchColumn();
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
                        // If user level not high enough, hide hidden individuals (from the join, don't hide the line)!
                        if ($_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) {
                            // Construct list of user IDs for current user and users who share access with him.
                            $aOwnerIDs = array_merge(array($_AUTH['id']), lovd_getColleagues(COLLEAGUE_ALL));
                            $sOwnerIDsSQL = join(', ', $aOwnerIDs);

                            $aSQL['FROM'] .= ' AND (i.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR i.created_by = "' . $_AUTH['id'] . '" OR i.owned_by IN (' . $sOwnerIDsSQL . ')') . ')';
                        }
                        $aSQL['FROM'] .= ')';
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' .
                                     TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) LEFT OUTER JOIN ' .
                                     TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id)';
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (i.owned_by = uo.id)';
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_DATA_STATUS . ' AS dsi ON (i.statusid = dsi.id)';
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
                case 'Gene':
                    $sPrefix = 'g.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                             'chromosome' => array(
                                 'view' => false,
                                 'db'   => array('g.chromosome', 'ASC', true)),
                              ));
                    break;

                case 'Transcript':
                    $sPrefix = 't.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                             'tid' => array(
                                 'view' => false,
                                 'db'   => array('t.id', 'ASC', true)),
                             'geneid' => array(
                                 'view' => array('Gene', 100),
                                 'db'   => array('t.geneid', 'ASC', true)),
                             'name' => array(
                                 'view' => array('Name', 300),
                                 'db'   => array('t.name', 'ASC', true)),
                             'id_ncbi' => array(
                                 'view' => array('NCBI ID', 120),
                                 'db'   => array('t.id_ncbi', 'ASC', true)),
                             'id_protein_ncbi' => array(
                                 'view' => array('NCBI Protein ID', 120),
                                 'db'   => array('t.id_protein_ncbi', 'ASC', true)),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'geneid';
                    }
                    // The custom ViewList with transcripts and variants also names the id_ncbi field differently.
                    if ($nKey == 0 && in_array('VariantOnTranscript', $aObjects)) {
                        // Object [0] is Transcripts, [1] is VOT; this is the in_gene view.
                        $this->aColumnsViewList['id_ncbi']['view'][0] = 'Transcript';
                    }
                    break;

                case 'DistanceToVar':
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                             'distance_to_var' => array(
                                 'view' => array('Distance (bp)', 90, 'style="text-align : right;"'),
                                 'db'   => array('distance_to_var', 'ASC', false)),
                              ));
                    // Always force default sorting...
                    $this->sSortDefault = 'distance_to_var';
                    break;

                case 'VariantOnGenome':
                    $sPrefix = 'vog.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                // NOTE: there are more columns defined a little further below.
                                'chromosome' => array(
                                        'view' => array('Chr', 50),
                                        'db'   => array('vog.chromosome', 'ASC', true)),
                                'allele_' => array(
                                        'view' => array('Allele', 120),
                                        'db'   => array('a.name', 'ASC', true),
                                        'legend' => array('On which allele is the variant located? Does not necessarily imply inheritance!',
                                                          'On which allele is the variant located? Does not necessarily imply inheritance! \'Paternal\' (confirmed or inferred), \'Maternal\' (confirmed or inferred), \'Parent #1\' or #2 for compound heterozygosity without having screened the parents, \'Unknown\' for heterozygosity without having screened the parents, \'Both\' for homozygozity.')),
                                'vog_effect' => array(
                                        'view' => array('Effect', 70),
                                        'db'   => array('eg.name', 'ASC', true),
                                        'legend' => array('The variant\'s effect on a protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; values ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                            'The variant\'s effect on a protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'+*\' affects function, not associated with individual\'s disease phenotype, \'#\' affects function, not associated with any known disease phenotype, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown, \'.\' effect not classified.')),
                              ));
                    if (in_array('VariantOnTranscript', $aObjects) || in_array('VariantOnTranscriptUnique', $aObjects)) {
                        unset($this->aColumnsViewList['vog_effect']);
                    }
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnGenome/DNA';
                    }
                    $this->sRowLink = 'variants/{{zData_vogid}}#{{zData_transcriptid}}';
                    break;

                case 'VariantOnTranscript':
                    $sPrefix = 'vot.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => false,
                                        'db'   => array('vot.transcriptid', 'ASC', true)),
                                'position_c_start' => array(
                                        'view' => false,
                                        'db'   => array('vot.position_c_start', 'ASC', true)),
                                'position_c_start_intron' => array(
                                        'view' => false,
                                        'db'   => array('vot.position_c_start_intron', 'ASC', true)),
                                'position_c_end' => array(
                                        'view' => false,
                                        'db'   => array('vot.position_c_end', 'ASC', true)),
                                'position_c_end_intron' => array(
                                        'view' => false,
                                        'db'   => array('vot.position_c_end_intron', 'ASC', true)),
                                'vot_clean_dna_change' => array(
                                        'view' => false,
                                        'db'   => array('TRIM(BOTH "?" FROM TRIM(LEADING "c." FROM REPLACE(REPLACE(`VariantOnTranscript/DNA`, ")", ""), "(", "")))', 'ASC', 'TEXT')),
                                'genes' => array(
                                        'view' => array('Gene', 100),
                                        'db'   => array('t.geneid', 'ASC', true)),
                                'vot_effect' => array(
                                        'view' => array('Effect', 70),
                                        'db'   => array('et.name', 'ASC', true),
                                        'legend' => array('The variant\'s effect on the protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; values ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                            'The variant\'s effect on the protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'+*\' affects function, not associated with individual\'s disease phenotype, \'#\' affects function, not associated with any known disease phenotype, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown, \'.\' effect not classified.')),
                              ));
                    // Only show the gene symbol when we have Scr2Var included, because these are the Individual- and Screening-specific views.
                    // FIXME: Perhaps it would be better to always show this column with VOT, but then hide it in all views that don't need it.
                    if (array_search('Scr2Var', $aObjects) === false) {
                        unset($this->aColumnsViewList['genes']);
                    }
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnTranscript/DNA';
                    }
                    break;

                case 'VariantOnTranscriptUnique':
                    $sPrefix = 'vot.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => false,
                                        'db'   => array('vot.transcriptid', 'ASC', true)),
                                'vot_effect' => array(
                                        'view' => array('Effect', 70),
                                        'db'   => array('et.name', 'ASC', true),
                                        'legend' => array('The variant\'s effect on the protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; values ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                            'The variant\'s effect on the protein\'s function, in the format \'S/C\' where S is the value reported by the submitter and C is the value concluded by the curator; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'+*\' affects function, not associated with individual\'s disease phenotype, \'#\' affects function, not associated with any known disease phenotype, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown, \'.\' effect not classified.')),
                                'vot_reported' => array(
                                        'view' => array('Reported', 70, 'style="text-align : right;"'),
                                        'db'   => array('vot_reported', 'ASC', 'INT_UNSIGNED'),
                                        'legend' => array('The number of times this variant has been reported.',
                                                          'The number of times this variant has been reported in the database.')),
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
                                    'sid' => array(
                                            'view' => array('Screening ID', 110, 'style="text-align : right;"'),
                                            'db'   => array('s.id', 'ASC', true)),
                                  ));
                        $this->sSortDefault = 'id';
                    }
                    break;

                case 'Scr2Var':
                    $sPrefix = 's2v.';
                    // No fixed columns, is only used to filter variants based on screening ID.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'screeningid' => array(
                                        'view' => false,
                                        'db'   => array('s2v.screeningid', false, true)),
                              ));
                    break;

                case 'Individual':
                    $sPrefix = 'i.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                             // NOTE: there are more columns defined a little further below.
                             'diseases_' => array(
                                            'view' => array('Disease', 175),
                                            'db'   => array('diseases_', 'ASC', true)),
                              ));
                    if (!$this->sSortDefault) {
                        $this->sSortDefault = 'id';
                    }
                    break;
            }



            // The custom columns.
            foreach ($this->aColumns as $sColID => $aCol) {
                if (strpos($sColID, str_replace('Unique', '', $sObject) . '/') === 0) {
                    $bAlignRight = preg_match('/^(DEC|FLOAT|(TINY|SMALL|MEDIUM|BIG)?INT)/', $aCol['mysql_type']);

                    $this->aColumnsViewList[$sColID] =
                         array(
                                'view' => array($aCol['head_column'], $aCol['width'], ($bAlignRight? ' align="right"' : '')),
                                'db'   => array($sPrefix . '`' . $aCol['id'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
                                'legend' => array($aCol['description_legend_short'], $aCol['description_legend_full']),
                                'allowfnr' => true,
                              );
                }
            }



            // Some fixed columns are supposed to be shown AFTER this objects's custom columns, so we'll need to go through the objects again.
            switch ($sObject) {
                case 'VariantOnGenome':
                    // More fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                        array(
                            // NOTE: there are more columns defined a little further up.
                            'owned_by_' => array(
                                'view' => array('Owner', 160),
                                'db'   => array('uo.name', 'ASC', true)),
                            'owner_countryid' => array(
                                'view' => false,
                                'db'   => array('uo.countryid', 'ASC', true)),
                            'var_status' => array(
                                'view' => array('Var. status', 70),
                                'db'   => array('dsg.name', false, true)),
                        ));
                    if (in_array('Individual', $aObjects)) {
                        unset($this->aColumnsViewList['owned_by_']);
                    }
                    if ($_AUTH['level'] < LEVEL_COLLABORATOR) {
                        // Unset status column for non-collaborators. We're assuming here, that lovd_isAuthorized() only gets called for gene-specific overviews.
                        unset($this->aColumnsViewList['var_status']);
                    }
                    // 2015-10-09; 3.0-14; Add genome build name to the VOG/DNA field.
                    $this->aColumnsViewList['VariantOnGenome/DNA']['view'][0] .= ' (' . $_CONF['refseq_build'] . ')';
                    break;

                case 'Individual':
                    // More fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                        array(
                            // NOTE: there are more columns defined a little further up.
                            'panel_size' => array(
                                'view' => array('Panel size', 70, 'style="text-align : right;"'),
                                'db'   => array('i.panel_size', 'DESC', true)),
                            'owned_by_' => array(
                                'view' => array('Owner', 160),
                                'db'   => array('uo.name', 'ASC', true)),
                            'ind_status' => array(
                                'view' => array('Ind. status', 70),
                                'db'   => array('dsi.name', false, true)),
                        ));
                    if ($_AUTH['level'] < LEVEL_COLLABORATOR) {
                        // Unset status column for non-collaborators. We're assuming here, that lovd_isAuthorized() only gets called for gene-specific overviews.
                        unset($this->aColumnsViewList['ind_status']);
                    }
                    break;
            }
        }



        // Gather the custom link information. It's just easier to load all custom links, instead of writing code that checks for the appropriate objects.
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





    private function getCustomColsForCategory ($sCategory)
    {
        $nCatLength = strlen($sCategory);
        $aOut = array();
        foreach ($this->aColumns as $sCol => $aCol) {
            if (substr($sCol, 0, $nCatLength) == $sCategory) {
                $aOut[$sCol] = $aCol;
            }
        }
        return $aOut;
    }




    private function getCustomColQuery ($sCategory, $sSelectQuery='')
    {
        // Return string with custom column names for object type $sCategory
        // that can be used in the SELECT listing of an SQL query.
        // $sSelectQuery can be given as an existing SELECT clause to avoid
        // duplication of column names are already listed.
        $sSelectFields = array();
        foreach (array_keys($this->getCustomColsForCategory($sCategory)) as $sName) {
            if (strpos($sSelectQuery, $sName) === false) {
                $sSelectFields[] = 'MIN(`' . $sName . '`) AS `' . $sName . '`';
            }
        }
        return join(', ', $sSelectFields);
    }





    function prepareData ($zData = '', $sView = 'list', $sViewListID = '')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH, $_SETT;

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        // Replace rs numbers with dbSNP links.
        if (!empty($zData['VariantOnGenome/dbSNP'])) {
            $zData['VariantOnGenome/dbSNP'] = preg_replace('/(rs\d+)/', '<SPAN' . ($sView != 'list'? '' : ' onclick="cancelParentEvent(event);"') . '><A href="https://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=' . "$1" . '" target="_blank">' . "$1" . '</A></SPAN>', $zData['VariantOnGenome/dbSNP']);
        }

        foreach ($this->aColumns as $sCol => $aCol) {
            if ($_AUTH['level'] < LEVEL_MANAGER && !$this->nID && substr($sCol, 0, 19) == 'VariantOnTranscript') {
                // Not a special authorized person, no gene selected, VOT column.
                // A column that has been disabled for this gene, may still show its value to collaborators and higher.
                if ((!$_AUTH || !in_array($zData['geneid'], $_AUTH['allowed_to_view'])) && ((is_array($zData['geneid']) && count(array_diff($zData['geneid'], $aCol['public_view']))) || (!is_array($zData['geneid']) && !in_array($zData['geneid'], $aCol['public_view'])))) {
                    $zData[$sCol] = '';
                }
            }
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

        if ($sView == 'list') {
            // "Clean" the GROUP_CONCAT columns for double values.
            foreach ($zData as $sCol => $sVal) {
                if (strpos($sCol, 'Screening/') === 0) {
                    $zData[$sCol] = implode(', ', array_unique(explode(';', $sVal)));
                }
                if (strpos($sViewListID, 'CustomVL_VOTunique') === 0 && (strpos($sCol, 'VariantOnGenome/') === 0 || strpos($sCol, 'VariantOnTranscript/') === 0)) {
                    // In the GROUP_CONCAT query a double semicolon (;;) is used as a separator, so it can be recognized here.
                    $aElements = explode(';;', $sVal);
                    $nElements = count($aElements);
                    $sNewElement = '';
                    $nCount = 0;

                    // VariantOnGenome and VariantOnTranscript columns with more then 200 characters are cut off.
                    // A string is added which states how many more unique items are available.
                    foreach ($aElements as $nKey => $sElement) {
                        if ((strlen(strip_tags($sNewElement)) + strlen(strip_tags($sElement))) <= $_SETT['unique_view_max_string_length']) {
                            $sNewElement .= ($sNewElement === ''? '' : ', ') . $sElement;
                            $nCount ++;
                        }
                    }
                    $nNotPrinted = $nElements - $nCount;
                    if ($nNotPrinted > 0) {
                        $sNewElement .= ($sNewElement === ''? '' : ', ') . '<I>' . $nNotPrinted . ' more item' . ($nNotPrinted == 1? '' : 's') . '</I>';
                    }
                    $zData[$sCol] = $sNewElement;
                }
            }
        }

        return $zData;
    }
}
?>
