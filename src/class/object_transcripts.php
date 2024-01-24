<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-20
 * Modified    : 2024-01-24
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_Transcript extends LOVD_Object
{
    // This class extends the basic Object class and it handles the Transcripts.
    var $sObject = 'Transcript';





    function __construct ()
    {
        global $_AUTH, $_SETT;

        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT t.* ' .
                               'FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                               'WHERE id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 't.*, ' .
                                           'g.name AS gene_name, ' .
                                           'g.chromosome, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'COUNT(DISTINCT vot.id) AS variants';
        $this->aSQLViewEntry['FROM']     = TABLE_TRANSCRIPTS . ' AS t ' .
                                           'LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (t.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (t.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 't.id';

        // SQL code for viewing the list of transcripts
        $this->aSQLViewList['SELECT']   = 't.*, ' .
                                          'g.chromosome' .
                                          (!$_SETT['customization_settings']['transcripts_VL_show_variant_counts']? '' :
                                              // Speed optimization by skipping variant counts.
                                              ', COUNT(DISTINCT ' . ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? 'vot.id' : 'vog.id') . ') AS variants');
        $this->aSQLViewList['FROM']     = TABLE_TRANSCRIPTS . ' AS t ' .
                                          'LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) ' .
                                          (!$_SETT['customization_settings']['transcripts_VL_show_variant_counts']? '' :
                                              // Speed optimization by skipping variant counts.
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)' .
                                              // If user is less than a collaborator, only show public variants and
                                              //  variants owned/created by them.
                                              ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' :
                                                  ' LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog' .
                                                  '   ON (vot.id = vog.id AND (vog.statusid >= ' . STATUS_MARKED .
                                                  (!$_AUTH? '' :
                                                      ' OR vog.created_by = "' . $_AUTH['id'] . '"' .
                                                      ' OR vog.owned_by = "' . $_AUTH['id'] . '"'
                                                  ) . '))'
                                              )
                                          );
        $this->aSQLViewList['GROUP_BY'] = 't.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'name' => 'Transcript name',
                        'gene_name_' => 'Gene name',
                        'chromosome' => 'Chromosome',
                        'id_ncbi_' => 'Transcript - NCBI ID',
                        'id_ensembl' => 'Transcript - Ensembl ID',
                        'id_protein_ncbi' => 'Protein - NCBI ID',
                        'id_protein_ensembl' => 'Protein - Ensembl ID',
                        'id_protein_uniprot' => 'Protein - Uniprot ID',
                        'exon_table' => 'Exon/intron information',
                        'remarks' => 'Remarks',
                        'created_by_' => array('Created by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'created_date_' => 'Date created',
                        'edited_by_' => array('Last edited by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_date_' => 'Date last edited',
                      );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'id_' => array(
                    'view' => array('ID', 50, 'style="text-align : right;"'),
                    'db'   => array('t.id', 'ASC', true)),
                'chromosome' => array(
                    'view' => array('Chr', 40),
                    'db'   => array('g.chromosome', 'ASC', true)),
                'geneid' => array(
                    'view' => array('Gene ID', 100),
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
                'variants' => array(
                    'view' => array('Variants', 70, 'style="text-align : right;"'),
                    'db'   => array('variants', 'DESC', 'INT_UNSIGNED')),
            );
        if (!$_SETT['customization_settings']['transcripts_VL_show_variant_counts']) {
            // Speed up view by removing the variants column.
            unset($this->aColumnsViewList['variants']);
        }

        $this->sSortDefault = 'geneid';

        // Because the disease information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        parent::__construct();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.

        parent::checkFields($aData, $zData, $aOptions);

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS($aData);
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                           array('POST', '', '', '', '35%', '14', '65%'),
                           array('', '', 'print', '<B>General information</B>'),
                           'hr',
                           array('Transcript Ensembl ID', '', 'text', 'id_ensembl', 10),
                           array('Protein Ensembl ID', '', 'text', 'id_protein_ensembl', 10),
                           array('Protein Uniprot ID', '', 'text', 'id_protein_uniprot', 10),
                           array('Remarks', '', 'textarea', 'remarks', 50, 5),
                           'hr',
                           'skip',
                  );

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        global $_SETT;
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView != 'list') {
            $zData['gene_name_'] = '<A href="genes/' . rawurlencode($zData['geneid']) . '">' . $zData['geneid'] . '</A> (' . $zData['gene_name'] . ')';

            $sNCBILink = $zData['id_ncbi'];
            // For mitochondrial genes we have to change the NCBI URL.
            if (isset($_SETT['mito_genes_aliases'][$zData['geneid']])) {
                $sNCBILink = str_replace('(' . $_SETT['mito_genes_aliases'][$zData['geneid']] . '_v001)', '', $zData['id_ncbi']);
            }
            $zData['id_ncbi_'] = '<A href="https://www.ncbi.nlm.nih.gov/nuccore/' . $sNCBILink . '" target="_blank">' . $zData['id_ncbi'] . '</A>';

            // Exon/intron info table. Check if files exist, and build link. Otherwise, remove field.
            $sExonTable = '';
            $sExonTableFileHTML = ROOT_PATH . 'refseq/' . $zData['geneid'] . '_' . $zData['id_ncbi'] . '_table.html';
            $sExonTableFileTXT = ROOT_PATH . 'refseq/' . $zData['geneid'] . '_' . $zData['id_ncbi'] . '_table.txt';
            if (is_readable($sExonTableFileHTML)) {
                $sExonTable .= (!$sExonTable? '' : ', ') . '<A href="' . $sExonTableFileHTML . '" target="_blank">HTML</A>';
            }
            if (is_readable($sExonTableFileTXT)) {
                $sExonTable .= (!$sExonTable? '' : ', ') . '<A href="' . $sExonTableFileTXT . '" target="_blank">Txt</A>';
            }
            if ($sExonTable) {
                $zData['exon_table'] = 'Exon/intron information table: ' . $sExonTable;
            } else {
                unset($this->aColumnsViewEntry['exon_table']);
            }
        }

        return $zData;
    }





    /**
     * This method returns transcripts and info from mutalyzer.
     * Note that transcripts that are already in the LOVD database are skipped.
     * @param string $sRefseqUD Genomic reference.
     * @param string $sSymbol HGNC gene symbol.
     * @param string $sGeneName HGNC gene name.
     * @param float $nProgress Variable is passed by reference and used to keep up the progress of the progress bar.
     * If the progress bar is initialized before this method is called, you can keep track of the progress with this variable.
     * The progress bar will start at zero when this variable is not set.
     * @return array $aTranscriptInfo Transcript information from mutalyzer.
     **/
    public function getTranscriptPositions ($sRefseqUD, $sSymbol, $sGeneName, &$nProgress = 0.0)
    {
        global $_BAR, $_SETT, $_DB;

        $aTranscripts = array(
            'id' => array(),
            'name' => array(),
            'mutalyzer' => array(),
            'positions' => array(),
            'protein' => array(),
            'added' => array(),
        );

        $sAliasSymbol = $sSymbol;
        $aTranscripts['added'] = $_DB->q('SELECT id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ? ORDER BY id_ncbi', array($sSymbol))->fetchAllColumn();
        if (isset($_SETT['mito_genes_aliases'][$sSymbol])) {
            // For mitochondrial genes, an alias must be used to get the transcripts and info.
            // List of aliases are hard-coded in inc-init.php.
            $sAliasSymbol = $_SETT['mito_genes_aliases'][$sSymbol];
        }

        $aTranscripts['info'] = lovd_callMutalyzer('getTranscriptsAndInfo', array('genomicReference' => $sRefseqUD, 'geneName' => $sAliasSymbol));
        if (empty($aTranscripts['info']) || !empty($aTranscripts['info']['faultcode'])) {
            // No transcripts found.
            $aTranscripts['info'] = array();
            return $aTranscripts;
        }

        $nTranscripts = count($aTranscripts['info']);
        foreach ($aTranscripts['info'] as $aTranscript) {
            $nProgress += ((100 - $nProgress)/$nTranscripts);
            $_BAR->setMessage('Collecting ' . $aTranscript['id'] . ' info...');

            if (isset($_SETT['mito_genes_aliases'][$sSymbol])) {
                // For mitochondrial genes, we won't be able to get any proper transcript information. Fake one.
                // FIXME: This code only works, if there is just one transcript. Can we assume there is only one?
                //   Perhaps it's better to use the same array construction as for normal genes, which is shorter, faster, and more flexible.
                $sRefseqNM = $sRefseqUD . '(' . $sAliasSymbol . '_v001)';
                if (in_array($sRefseqNM, $aTranscripts['added'])) {
                    // Transcript already exists; continue to the next transcript.
                    continue;
                }
                $aTranscripts['id'] = array($sRefseqNM);
                $aTranscripts['protein'] = array($sRefseqNM => '');
                // Until revision 679 the transcript version was not used in the index. The version number was removed with preg_replace.
                // Can not figure out why version is not included. Therefore, for now we will do without preg_replace.
                $aTranscripts['mutalyzer'] = array($sRefseqNM => '001');
                $aTranscripts['name'] = array($sRefseqNM => 'transcript variant 1'); // FIXME: Perhaps indicate this transcript is a fake one, reconstructed from the CDS?
                $aTranscripts['positions'] = array($sRefseqNM =>
                    array(
                        // For mitochondrial genes we used the NC to call getTranscriptAndInfo, therefore we can use the gTransStart and gTransEnd.
                        'chromTransStart' => (isset($aTranscript['gTransStart'])? $aTranscript['gTransStart'] : 0),
                        'chromTransEnd' => (isset($aTranscript['gTransEnd'])? $aTranscript['gTransEnd'] : 0),
                        'cTransStart' => (isset($aTranscript['cTransStart'])? $aTranscript['cTransStart'] : 0),
                        'cTransEnd' => (isset($aTranscript['sortableTransEnd'])? $aTranscript['sortableTransEnd'] : 0),
                        'cCDSStop' => (isset($aTranscript['cCDSStop'])? $aTranscript['cCDSStop'] : 0),
                    )
                );
            } else {
                if (in_array($aTranscript['id'], $aTranscripts['added'])) {
                    // Transcript already exists; continue to the next transcript.
                    continue;
                }
                $aTranscripts['id'][] = $aTranscript['id'];
                // Until revision 679 the transcript version was not used in the index. The version number was removed with preg_replace.
                // Can not figure out why version is not included. Therefore, for now we will do without preg_replace.
                $aTranscripts['name'][$aTranscript['id']] = str_replace($sGeneName . ', ', '', $aTranscript['product']);
                $aTranscripts['mutalyzer'][$aTranscript['id']] = str_replace($sSymbol . '_v', '', $aTranscript['name']);
                $aTranscripts['positions'][$aTranscript['id']] =
                    array(
                        'chromTransStart' => (isset($aTranscript['chromTransStart'])? $aTranscript['chromTransStart'] : 0),
                        'chromTransEnd' => (isset($aTranscript['chromTransEnd'])? $aTranscript['chromTransEnd'] : 0),
                        'cTransStart' => $aTranscript['cTransStart'],
                        'cTransEnd' => $aTranscript['sortableTransEnd'],
                        'cCDSStop' => $aTranscript['cCDSStop'],
                    );
                $aTranscripts['protein'][$aTranscript['id']] = (empty($aTranscript['proteinTranscript']['id'])? '' : $aTranscript['proteinTranscript']['id']);
            }
            $_BAR->setProgress($nProgress);
        }
        return $aTranscripts;
    }





    /**
     * This method turns off the MAPPING_DONE flag for a variant within the range of a transcript.
     * Automatic mapping will pick them up again.
     * @param string $sChromosome Search for variants which are on this chromosome.
     * @param array $aTranscript Array with transcript information, including the positions.
     **/
    public function turnOffMappingDone ($sChromosome, $aTranscript)
    {
        global $_DB;

        $q = $_DB->q('UPDATE ' . TABLE_VARIANTS . '
                         SET mapping_flags = mapping_flags & ~' . MAPPING_DONE . '
                         WHERE chromosome = ? AND (
                           (position_g_start BETWEEN ? AND ?) OR
                           (position_g_end BETWEEN ? AND ?) OR
                           (position_g_start < ? AND position_g_end > ?))',
                         array($sChromosome,
                               $aTranscript['position_g_mrna_start'],
                               $aTranscript['position_g_mrna_end'],
                               $aTranscript['position_g_mrna_start'],
                               $aTranscript['position_g_mrna_end'],
                               $aTranscript['position_g_mrna_start'],
                               $aTranscript['position_g_mrna_end']));
        if ($q->rowCount()) {
            // If we have changed variants, turn on mapping immediately.
            $_SESSION['mapping']['time_complete'] = 0;
        }
    }
}
?>
