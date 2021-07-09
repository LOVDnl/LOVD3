<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2021-07-07
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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





class LOVD_Gene extends LOVD_Object
{
    // This class extends the basic Object class and it handles the Genes.
    var $sObject = 'Gene';





    function __construct ()
    {
        // Default constructor.
        global $_AUTH, $_DB, $_SETT;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT g.*, ' .
                               'GROUP_CONCAT(DISTINCT g2d.diseaseid ORDER BY g2d.diseaseid SEPARATOR ";") AS _active_diseases ' .
                               'FROM ' . TABLE_GENES . ' AS g ' .
                               'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                               'WHERE g.id = ? ' .
                               'GROUP BY g.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'g.*, g.id_entrez AS id_pubmed_gene,
                                            IF(g.show_genetests AND g.id_entrez, g.id_entrez, 0) AS show_genetests,
                                            IF(g.show_orphanet AND g.id_hgnc, g.id_hgnc, 0) AS show_orphanet, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IFNULL(d.id_omim, 0), ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT t.id, ";", t.id_ncbi ORDER BY t.id_ncbi SEPARATOR ";;") AS __transcripts, ' .
                                           'MAX(t.position_g_mrna_start < t.position_g_mrna_end) AS sense, ' .
                                           'LEAST(MIN(t.position_g_mrna_start), MIN(t.position_g_mrna_end)) AS position_g_mrna_start, ' .
                                           'GREATEST(MAX(t.position_g_mrna_start), MAX(t.position_g_mrna_end)) AS position_g_mrna_end, ' .
                                           'GROUP_CONCAT(DISTINCT u2g.userid, ";", ua.name, ";", u2g.allow_edit, ";", show_order ORDER BY (u2g.show_order > 0) DESC, u2g.show_order SEPARATOR ";;") AS __curators, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'uu.name AS updated_by_, ' .
                                           '(SELECT COUNT(DISTINCT vog.id) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = g.id AND vog.statusid >= ' . STATUS_MARKED . ') AS variants, ' .
                                           (!$_SETT['customization_settings']['genes_VE_show_unique_variant_counts']? '' :
                                               '(SELECT COUNT(DISTINCT vog.`VariantOnGenome/DBID`) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE t.geneid = g.id AND vog.statusid >= ' . STATUS_MARKED . ') AS uniq_variants, ') .
                                           '"" AS count_individuals, ' . // Temporary value, prepareData actually runs this query.
                                           '(SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS hidden_vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS hidden_vot ON (hidden_vog.id = hidden_vot.id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (hidden_vot.transcriptid = t.id) WHERE t.geneid = g.id AND hidden_vog.statusid < ' . STATUS_MARKED . ') AS hidden_variants';
        $this->aSQLViewEntry['FROM']     = TABLE_GENES . ' AS g ' .
                                           'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (u2g.userid = ua.id' . ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' : ' AND u2g.show_order > 0') . ') ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (g.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (g.edited_by = ue.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uu ON (g.updated_by = uu.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) ';
        $this->aSQLViewEntry['GROUP_BY'] = 'g.id';

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'g.*, ' .
                                          'g.id AS geneid, ' .
                                          // FIXME; Can we get this order correct, such that diseases without abbreviation nicely mix with those with? Right now, the diseases without symbols are in the back.
                                          'GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, ' .
                                          'COUNT(DISTINCT t.id) AS transcripts' .
                                          (!$_SETT['customization_settings']['genes_VL_show_variant_counts']? '' :
                                              // Speed optimization by skipping variant counts.
                                              ', ' .
                                              'COUNT(DISTINCT vog.id) AS variants, ' .
                                              'COUNT(DISTINCT vog.`VariantOnGenome/DBID`) AS uniq_variants');

        $this->aSQLViewList['FROM']     = TABLE_GENES . ' AS g ' .
                                          'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) ' .
                                          (!$_SETT['customization_settings']['genes_VL_show_variant_counts']? '' :
                                              // Speed optimization by skipping variant counts.
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid) ' .
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vot.id = vog.id' . ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']? '' : ' AND vog.statusid >= ' . STATUS_MARKED) . ') ') .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id)';
        $this->aSQLViewList['GROUP_BY'] = 'g.id';


        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'TableHeader_General' => 'General information',
                        'id' => 'Gene symbol',
                        'name' => 'Gene name',
                        'chromosome' => 'Chromosome',
                        'chrom_band' => 'Chromosomal band',
                        'imprinting_' => 'Imprinted',
                        'refseq_genomic_' => 'Genomic reference',
                        'refseq_UD_' => array('Mutalyzer genomic reference', LEVEL_ADMIN),
                        'refseq_transcript_' => 'Transcript reference',
                        'exon_tables' => 'Exon/intron information',
                        'diseases_' => 'Associated with diseases',
                        'reference' => 'Citation reference(s)',
                        'refseq_url_' => 'Refseq URL',
                        'curators_' => 'Curators',
                        'collaborators_' => array('Collaborators', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'variants_' => 'Total number of public variants reported',
                        'uniq_variants_' => 'Unique public DNA variants reported',
                        'count_individuals_' => 'Individuals with public variants',
                        'hidden_variants_' => 'Hidden variants',
                        'allow_download_' => array('Allow public to download linked information', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'download_' => 'Download all this gene\'s data',
                        'note_index' => 'Notes',
                        'created_by_' => array('Created by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'created_date_' => 'Date created',
                        'edited_by_' => array('Last edited by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_date_' => array('Date last edited', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'updated_by_' => array('Last updated by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'updated_date_' => 'Date last updated',
                        'version_' => 'Version',
                        'TableEnd_General' => '',
                        'HR_1' => '',
                        'TableStart_Graphs' => '',
                        'TableHeader_Graphs' => 'Graphical displays and utilities',
                        'graphs' => 'Graphs',
                        'rf_checker_' => 'Reading frame checker',
                        'ucsc' => 'UCSC Genome Browser',
                        'ensembl' => 'Ensembl Genome Browser',
                        'ncbi' => 'NCBI Sequence Viewer',
                        'TableEnd_Graphs' => '',
                        'HR_2' => '',
                        'TableStart_Links' => '',
                        'TableHeader_Links' => 'Links to other resources',
                        'url_homepage_' => 'Homepage URL',
                        'url_external_' => 'External URL',
                        'id_hgnc_' => 'HGNC',
                        'id_entrez_' => 'Entrez Gene',
                        'id_pubmed_gene_' => 'PubMed articles',
                        'id_omim_' => 'OMIM - Gene',
                        'disease_omim_' => 'OMIM - Diseases',
                        'show_hgmd_' => 'HGMD',
                        'show_genecards_' => 'GeneCards',
                        'show_genetests_' => 'GeneTests',
                        'show_orphanet_' => 'Orphanet',
                      );
        if (!$_SETT['customization_settings']['genes_show_meta_data']) {
            // Hide date and user fields.
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date_']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['edited_date_']);
            unset($this->aColumnsViewEntry['updated_by_']);
            unset($this->aColumnsViewEntry['updated_date_']);
            unset($this->aColumnsViewEntry['version_']);
        }
        if (!$_SETT['customization_settings']['genes_VE_show_unique_variant_counts']) {
            unset($this->aColumnsViewEntry['uniq_variants_']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'geneid' => array(
                    'view' => false, // Copy of the gene's ID for the search terms in the screening's viewEntry.
                    'db'   => array('g.id', 'ASC', true)),
                'id_' => array(
                    'view' => array('Symbol', 100),
                    'db'   => array('g.id', 'ASC', true)),
                'name' => array(
                    'view' => array('Gene', 300),
                    'db'   => array('g.name', 'ASC', true)),
                'chromosome' => array(
                    'view' => array('Chr', 50),
                    'db'   => array('g.chromosome', 'ASC', true)),
                'chrom_band' => array(
                    'view' => array('Band', 70),
                    'db'   => array('g.chrom_band', false, true)),
                'transcripts' => array(
                    'view' => array('Transcripts', 90, 'style="text-align : right;"'),
                    'db'   => array('transcripts', 'DESC', 'INT_UNSIGNED')),
                'variants' => array(
                    'view' => array('Variants', 70, 'style="text-align : right;"'),
                    'db'   => array('variants', 'DESC', 'INT_UNSIGNED')),
                'uniq_variants' => array(
                    'view' => array('Unique variants', 70, 'style="text-align : right;"'),
                    'db'   => array('uniq_variants', 'DESC', 'INT_UNSIGNED')),
                'updated_date_' => array(
                    'view' => array('Last updated', 110),
                    'db'   => array('g.updated_date', 'DESC', true)),
                'diseases_' => array(
                    'view' => array('Associated with diseases', 200),
                    'db'   => array('diseases_', false, 'TEXT')),
            );

        if (!$_SETT['customization_settings']['genes_show_meta_data']) {
            // Hide date field.
            unset($this->aColumnsViewList['updated_date_']);
        }
        if (!$_SETT['customization_settings']['genes_VL_show_variant_counts']) {
            // Hide variant columns and updated_date.
            unset($this->aColumnsViewList['variants']);
            unset($this->aColumnsViewList['uniq_variants']);
        }

        if (LOVD_plus) {
            // Add transcript information for the gene panel's "Manage genes" gene viewlist.
            // Unfortunately, we can't limit this for the genes VL on the gene panel page,
            //  because we also want it to work on the AJAX viewlist, so we can't use lovd_getProjectFile(),
            //  but neither can we use the sViewListID, because we're in the constructor.
            $_DB->query('SET group_concat_max_len = 10240'); // Make sure you can deal with long transcript lists.
            $this->aSQLViewList['SELECT'] .= ', IFNULL(CONCAT("<OPTION value=&quot;&quot;>-- select --</OPTION>", GROUP_CONCAT(CONCAT("<OPTION value=&quot;", t.id, "&quot;>", t.id_ncbi, "</OPTION>") ORDER BY t.id_ncbi SEPARATOR "")), "<OPTION value=&quot;&quot;>-- no transcripts available --</OPTION>") AS transcripts_HTML';
        }

        $this->sSortDefault = 'id_';

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        parent::__construct();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.
        global $_DB;

        // No mandatory fields, since all the gene data is in $_SESSION.

        if (isset($aData['workID'])) {
            unset($aData['workID']);
        }

        parent::checkFields($aData, $zData, $aOptions);

        if (ACTION == 'create') {
            if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE id = ?', array($zData['id']))->fetchColumn()) {
                lovd_errorAdd('', 'Unable to add gene. This gene symbol already exists in the database!');
            } elseif ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENES . ' WHERE id_hgnc = ?', array($zData['id_hgnc']))->fetchColumn()) {
                lovd_errorAdd('', 'Unable to add gene. A gene with this HGNC ID already exists in the database!');
            }
        }

        if (lovd_getProjectFile() != '/import.php' && !in_array($aData['refseq_genomic'], $zData['genomic_references'])) {
            lovd_errorAdd('refseq_genomic' ,'Please select a proper NG, NC, or LRG accession number in the \'NCBI accession number for the genomic reference sequence\' selection box.');
        }

        if (!empty($aData['refseq']) && empty($aData['refseq_url'])) {
            lovd_errorAdd('refseq', 'You have selected that there is a human-readable reference sequence. Please fill in the "Human-readable reference sequence location" field. Otherwise, select \'No\' for the "This gene has a human-readable reference sequence" field.');
        }

        if ($aData['disclaimer'] == 2 && empty($aData['disclaimer_text'])) {
            lovd_errorAdd('disclaimer_text', 'If you wish to use an own disclaimer, please fill in the "Text for own disclaimer" field. Otherwise, select \'No\' for the "Include disclaimer" field.');
        }

        // Numeric values
        $aCheck =
                 array(
                        'header_align' => 'Header aligned to',
                        'footer_align' => 'Footer aligned to',
                      );

        foreach ($aCheck as $key => $val) {
            if ($aData[$key] && !is_numeric($aData[$key])) {
                lovd_errorAdd($key, 'The \'' . $val . '\' field has to contain a numeric value.');
            }
        }

        // URL values
        if ($aData['url_homepage'] && !lovd_matchURL($aData['url_homepage'])) {
            lovd_errorAdd('url_homepage', 'The \'Homepage URL\' field does not seem to contain a correct URL.');
        }
        if ($aData['refseq_url'] && !lovd_matchURL($aData['refseq_url'], true)) {
            lovd_errorAdd('refseq_url', 'The \'Human-readable reference sequence location\' field does not seem to contain a correct URL.');
        }

        // List of external links.
        if ($aData['url_external']) {
            $aExternalLinks = explode("\r\n", $aData['url_external']);
            foreach ($aExternalLinks as $n => $sLink) {
                if (!lovd_matchURL($sLink) && (!preg_match('/^[^<>]+ <([^< >]+)>$/', $sLink, $aRegs) || !lovd_matchURL($aRegs[1]))) {
                    lovd_errorAdd('url_external', 'External link #' . ($n + 1) . ' (' . htmlspecialchars($sLink) . ') not understood.');
                }
            }
        }

        // XSS attack prevention. Deny input of HTML.
        // Ignore the 'External links' field.
        unset($aData['url_external'], $aData['disclaimer_text'], $aData['header'], $aData['footer'], $aData['note_index'], $aData['note_listing']);
        lovd_checkXSS($aData);
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            if (lovd_getProjectFile() == '/import.php') {
                // During import the refseq_genomic is required, else the import
                // starts complaining that the selected refseq_genomic is not valid
                // Therefore we set the refseq_genomic in the aFormData property
                // before the getForm() is returned.
                global $zData;
                $aSelectRefseqGenomic = array_combine(array($zData['refseq_genomic']), array($zData['refseq_genomic']));

                $this->aFormData['refseq_genomic'] = array('Genomic reference sequence', '', 'select', 'refseq_genomic', 1, $aSelectRefseqGenomic, false, false, false);
            }
            return parent::getForm();
        }

        global $_DB, $zData, $_SETT;

        // Get list of diseases.
        $aDiseasesForm = $_DB->query('SELECT id, IF(CASE symbol WHEN "-" THEN "" ELSE symbol END = "", name, CONCAT(symbol, " (", name, ")")) FROM ' . TABLE_DISEASES . ' WHERE id > 0 ORDER BY (symbol != "" AND symbol != "-") DESC, symbol, name')->fetchAllCombine();
        $nDiseases = count($aDiseasesForm);
        if (!$nDiseases) {
            $aDiseasesForm = array('' => 'No disease entries available');
            $nDiseasesFormSize = 1;
        } else {
            $aDiseasesForm = array_combine(array_keys($aDiseasesForm), array_map('lovd_shortenString', $aDiseasesForm, array_fill(0, $nDiseases, 75)));
            $nDiseasesFormSize = ($nDiseases < 15? $nDiseases : 15);
        }

        // References sequences (genomic and transcripts).
        if (lovd_getProjectFile() == '/import.php') {
            $aSelectRefseqGenomic = array_combine(array($zData['refseq_genomic']), array($zData['refseq_genomic']));
        } else {
            $aSelectRefseqGenomic = array_combine($zData['genomic_references'], $zData['genomic_references']);
        }
        $aTranscriptNames = array();
        $aTranscriptsForm = array();
        if (!empty($zData['transcripts'])) {
            foreach ($zData['transcripts'] as $sTranscript) {
                // Until revision 679 the transcript version was not used in the index and removed with preg_replace.
                // Can not figure out why version is not included. Therefore, for now we will do without preg_replace.
                if (!isset($aTranscriptNames[$sTranscript])) {
                    $aTranscriptsForm[$sTranscript] = lovd_shortenString($zData['transcriptNames'][$sTranscript], 50);
                    $aTranscriptsForm[$sTranscript] .= str_repeat(')', substr_count($aTranscriptsForm[$sTranscript], '(')) . ' (' . $sTranscript . ')';
                }
            }
            asort($aTranscriptsForm);
        } else {
            $aTranscriptsForm = array('' => 'No transcripts available');
        }

        $nTranscriptsFormSize = count($aTranscriptsForm);
        $nTranscriptsFormSize = ($nTranscriptsFormSize < 10? $nTranscriptsFormSize : 10);

        $aSelectRefseq = array(
                                'c' => 'Coding DNA',
                                'g' => 'Genomic'
                              );
        $aSelectDisclaimer = array(
                                0 => 'No',
                                1 => 'Use standard LOVD disclaimer',
                                2 => 'Use own disclaimer (enter below)'
                                  );
        $aSelectHeaderFooter = array(
                                -1 => 'Left',
                                 0 => 'Center',
                                 1 => 'Right'
                                    );

        // Custom links for the Reference field.
        $aCustomLinks = $_DB->query('
                    SELECT name, pattern_text, description
                    FROM ' . TABLE_LINKS . ' WHERE name IN (?, ?)',
            array('PubMed', 'DOI'))->fetchAllAssoc();
        $sCustomLinks = '';
        foreach ($aCustomLinks as $aLink) {
            $sToolTip = str_replace(array("\r\n", "\r", "\n"), '<BR>', 'Click to insert:<BR>' . $aLink['pattern_text'] . '<BR><BR>' . addslashes(htmlspecialchars($aLink['description'])));
            $sCustomLinks .= ($sCustomLinks? ', ' : '') . '<A href="#" onmouseover="lovd_showToolTip(\'' . $sToolTip . '\');" onmouseout="lovd_hideToolTip();" onclick="lovd_insertCustomLink(this, \'' . $aLink['pattern_text'] . '\'); return false">' . $aLink['name'] . '</A>';
        }
        $sCustomLinks = '(Active custom link' . (count($aCustomLinks) == 1? '' : 's') . ' : ' . $sCustomLinks . ')';

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '35%', '14', '65%'),
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        array('Full gene name', '', 'print', $zData['name'], 50),
                        array('Official gene symbol', '', 'print', $zData['id']),
                        array('Chromosome', '', 'print', $zData['chromosome']),
                        array('Chromosomal band', '', 'text', 'chrom_band', 10),
                        array('Imprinting', 'Please note:<BR>Maternally imprinted (expressed from the paternal allele)<BR>Paternally imprinted (expressed from the maternal allele)', 'select', 'imprinting', 1, $_SETT['gene_imprinting'], false, false, false),
                        array('Date of creation (optional)', 'Format: YYYY-MM-DD. If left empty, today\'s date will be used.', 'text', 'created_date', 10),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases (optional)</B>'),
                        'hr',
                        array('This gene has been linked to these diseases', 'Listed are all disease entries currently configured in LOVD.', 'select', 'active_diseases', $nDiseasesFormSize, $aDiseasesForm, false, true, false),
                        array('', '', 'note', 'Diseases not in this list are not yet configured in this LOVD.<BR>Do you want to <A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'diseases?create&amp;in_window\', \'DiseasesCreate\', 800, 550); return false;">configure more diseases</A>?'),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Reference sequences (mandatory)</B>'),
                        array('', '', 'note', 'Collecting variants requires a proper reference sequence. Without a genomic and a transcript reference sequence the variants in this LOVD database cannot be interpreted properly or mapped to the genome.'),
                        'hr',
    'refseq_genomic' => array('Genomic reference sequence', '', 'select', 'refseq_genomic', 1, $aSelectRefseqGenomic, false, false, false),
                        array('', '', 'note', 'Select the genomic reference sequence (NG, NC, LRG accession number). Only the references that are available to LOVD are shown.'),
    'transcripts' =>    array('Transcript reference sequence(s)', 'Select transcript references (NM accession numbers).', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, false),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Links to information sources (optional)</B>'),
                        array('', '', 'note', 'Here you can add links that will be displayed on the gene\'s LOVD gene homepage.'),
                        'hr',
                        array('Homepage URL', '', 'text', 'url_homepage', 40),
                        array('', '', 'note', 'If you have a separate homepage about this gene, you can specify the URL here. Format: complete URL, including "http://".'),
                        array('External links', '', 'textarea', 'url_external', 55, 3),
                        array('', '', 'note', 'Here you can provide links to other resources on the internet that you would like to link to. One link per line, format: complete URLs or "Description &lt;URL&gt;".'),
                        array('HGNC ID', '', 'print', $zData['id_hgnc']),
                        array('Entrez Gene (Locuslink) ID', '', 'print', ($zData['id_entrez']? $zData['id_entrez'] : 'Not Available')),
                        array('OMIM Gene ID', '', 'print', ($zData['id_omim']? $zData['id_omim'] : 'Not Available')),
                        array('Provide link to HGMD', 'Do you want a link to this gene\'s entry in the Human Gene Mutation Database added to the homepage?', 'checkbox', 'show_hgmd'),
                        array('Provide link to GeneCards', 'Do you want a link to this gene\'s entry in the GeneCards database added to the homepage?', 'checkbox', 'show_genecards'),
                        array('Provide link to GeneTests', 'Do you want a link to this gene\'s entry in the GeneTests database added to the homepage?', 'checkbox', 'show_genetests'),
                        array('Provide link to Orphanet', 'Do you want a link to this gene\'s entry in the Orphanet database added to the homepage?', 'checkbox', 'show_orphanet'),
                        array('This gene has a human-readable reference sequence', '', 'select', 'refseq', 1, $aSelectRefseq, 'No', false, false),
                        array('', '', 'note', 'Although GenBank files are the official reference sequence, they are not very readable for humans. If you have a human-readable format of your reference sequence online, please select the type here.'),
                        array('Human-readable reference sequence location', '', 'text', 'refseq_url', 40),
                     // FIXME: Link incorrect!!!
   'refseqparse_new' => array('', '', 'note', 'If you are going to use our <A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'scripts/refseq_parser.php\', \'RefSeqParser\', 800, 500); return false;">Reference Sequence Parser</A> to create a human-readable reference sequence, the result will be located at "' . lovd_getInstallURL() . 'refseq/' . $zData['id'] . '_codingDNA.html".'),
  'refseqparse_edit' => array('', '', 'note', 'If you used our <A href="#" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'scripts/refseq_parser.php?symbol=' . $zData['id'] . '\', \'RefSeqParser\', 800, 500); return false;">Reference Sequence Parser</A> to create a human-readable reference sequence, the result is located at "' . lovd_getInstallURL() . 'refseq/' . $zData['id'] . '_codingDNA.html".'),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Customizations (optional)</B>'),
                        array('', '', 'note', 'You can use the following fields to customize the gene\'s LOVD gene homepage.'),
                        'hr',
                        array('Citation reference(s)', '', 'textarea', 'reference', 30, 3),
                        array('', '', 'note', $sCustomLinks),
                        array('Include disclaimer', '', 'select', 'disclaimer', 1, $aSelectDisclaimer, false, false, false),
                        array('', '', 'note', 'If you want a disclaimer added to the gene\'s LOVD gene homepage, select your preferred option here.'),
                        array('Text for own disclaimer<BR>(HTML enabled)', '', 'textarea', 'disclaimer_text', 55, 3),
                        array('', '', 'note', 'Only applicable if you choose to use your own disclaimer (see option above).'),
                        array('Page header<BR>(HTML enabled)', '', 'textarea', 'header', 55, 3),
                        array('', '', 'note', 'Text entered here will appear above all public gene-specific pages.'),
                        array('Header aligned to', '', 'select', 'header_align', 1, $aSelectHeaderFooter, false, false, false),
                        array('Page footer<BR>(HTML enabled)', '', 'textarea', 'footer', 55, 3),
                        array('', '', 'note', 'Text entered here will appear below all public gene-specific pages.'),
                        array('Footer aligned to', '', 'select', 'footer_align', 1, $aSelectHeaderFooter, false, false, false),
                        array('Notes for the LOVD gene homepage<BR>(HTML enabled)', '', 'textarea', 'note_index', 55, 3),
                        array('', '', 'note', 'Text entered here will appear in the General Information box on the gene\'s LOVD gene homepage.'),
                        array('Notes for the variant listings<BR>(HTML enabled)', '', 'textarea', 'note_listing', 55, 3),
                        array('', '', 'note', 'Text entered here will appear below the gene\'s variant listings.'),
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>Security settings</B>'),
                        array('', '', 'note', 'Using the following settings you can control some security settings of LOVD.'),
                        'hr',
                        array('Allow public to download variant entries', '', 'checkbox', 'allow_download'),
                        'hr',
                        'skip',
                  );
        if (ACTION == 'edit') {
            $this->aFormData['transcripts'] = array('Transcriptomic reference sequence(s)', '', 'note', 'To add, remove or edit transcriptomic reference sequences for this gene, please see the gene\'s detailed view.');
            unset($this->aFormData['refseqparse_new']);
        } else {
            unset($this->aFormData['refseqparse_edit']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH, $_CONF, $_DB, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['updated_date_'] = substr($zData['updated_date'], 0, 10);
        } else {
            $zData['imprinting_'] = $_SETT['gene_imprinting'][$zData['imprinting']];

            // FIXME; zou dit een external source moeten zijn?
            $zData['refseq_genomic_'] = '<A href="' . (substr($zData['refseq_genomic'], 0, 3) == 'LRG'? 'ftp://ftp.ebi.ac.uk/pub/databases/lrgex/' . $zData['refseq_genomic'] . '.xml' : 'https://www.ncbi.nlm.nih.gov/nuccore/' . $zData['refseq_genomic']) . '" target="_blank">' . $zData['refseq_genomic'] . '</A>';
            $zData['refseq_UD_'] = '<A href="' . str_replace('services', 'Reference/', $_CONF['mutalyzer_soap_url']) . $zData['refseq_UD'] . '.gb" target="_blank">' . $zData['refseq_UD'] . '</A>';

            // Transcript links and exon/intron info table. Check if files exist, and build link. Otherwise, remove field.
            $zData['refseq_transcript_'] = '';
            $zData['exon_tables'] = '';
            $zData['rf_checker_'] = '';
            foreach ($zData['transcripts'] as $aTranscript) {
                list($nTranscriptID, $sNCBI) = $aTranscript;
                $zData['refseq_transcript_'] .= (!$zData['refseq_transcript_']? '' : ', ') . '<A href="transcripts/' . $nTranscriptID . '">' . $sNCBI . '</A>';
                $sExonTableFile = ROOT_PATH . 'refseq/' . $zData['id'] . '_' . $sNCBI . '_table.html';
                if (is_readable($sExonTableFile)) {
                    $zData['exon_tables'] .= (!$zData['exon_tables']? '' : ', ') . '<A href="' . $sExonTableFile . '" target="_blank">' . $sNCBI . ' exon/intron table</A>';

                    // Assume presence of exon table file in *.txt format. Show link to reading
                    // frame checker.
                    $zData['rf_checker_'] .= (!$zData['rf_checker_']? '' : ', ') . '<A href="#" onclick="lovd_openWindow(\'scripts/readingFrameChecker.php?gene=' . $zData['id'] . '&transcript=' . $sNCBI . '\', \'readingframechecker\', 800, 500); return false;">' . $sNCBI . '</A>';
                }
            }
            if (!$zData['refseq_transcript_']) {
                unset($this->aColumnsViewEntry['refseq_transcript_']);
            }
            if (!$zData['exon_tables']) {
                unset($this->aColumnsViewEntry['exon_tables']);
            }
            if ($zData['rf_checker_']) {
                $zData['rf_checker_'] = 'The Reading-frame checker generates a prediction of the effect of whole-exon changes. Active for: ' . $zData['rf_checker_'] . '.';
            }

            // Associated with diseases...
            $zData['diseases_'] = '';
            $zData['disease_omim_'] = '';
            foreach($zData['diseases'] as $aDisease) {
                list($nID, $nOMIMID, $sSymbol, $sName) = $aDisease;
                // Link to disease entry in LOVD.
                $zData['diseases_'] .= (!$zData['diseases_']? '' : ', ') . '<A href="diseases/' . $nID . '">' . $sSymbol . '</A>';
                if ($nOMIMID) {
                    // Add link to OMIM for each disease that has an OMIM ID.
                    $zData['disease_omim_'] .= (!$zData['disease_omim_']? '' : '<BR>') . '<A href="' . lovd_getExternalSource('omim', $nOMIMID, true) . '" target="_blank">' . $sSymbol . ($sSymbol == $sName? '' : ' (' . $sName . ')') . '</A>';
                }
            }

            if (isset($zData['reference'])) {
                $aCustomLinks = $_DB->query('
                    SELECT pattern_text, replace_text
                    FROM ' . TABLE_LINKS . ' WHERE name IN (?, ?)',
                    array('PubMed', 'DOI'))->fetchAllAssoc();
                foreach ($aCustomLinks as $aLink) {
                    $sRegexpPattern = '/' . str_replace(array('{', '}'), array('\{', '\}'), preg_replace('/\[\d\]/', '([^:]*)', $aLink['pattern_text'])) . '/';
                    $sReplaceText = preg_replace('/\[(\d)\]/', '\$$1', $aLink['replace_text']);
                    $zData['reference'] = preg_replace($sRegexpPattern . 'U', $sReplaceText, $zData['reference']);
                }
            }

            if ($_AUTH['level'] >= LEVEL_CURATOR || !empty($zData['allow_download'])) {
                $zData['download_'] = '<A href="download/all/gene/' . $zData['id'] . '">' .
                    'Download all data</a>';
            } else {
                unset($this->aColumnsViewEntry['download_']);
            }

            $zData['allow_download_']   = '<IMG src="gfx/mark_' . $zData['allow_download'] . '.png" alt="" width="11" height="11">';

            // Human readable RefSeq link.
            if ($zData['refseq_url']) {
                $zData['refseq_url_'] = '<A href="' . $zData['refseq_url'] . '" target="_blank">' . ($zData['refseq'] == 'c'? 'Coding DNA' : 'Genomic') . ' reference sequence</A>';
            }

            // Curators and collaborators.
            $zData['curators_'] = $zData['collaborators_'] = '';
            $aCurators = $aCollaborators = array();
            foreach ($zData['curators'] as $aVal) {
                if ($aVal) { // Should always be true, since genes should always have a curator!
                    list($nUserID, $sName, $bAllowEdit, $nOrder) = $aVal;
                    if ($bAllowEdit) {
                        $aCurators[$nUserID] = array($sName, $nOrder);
                    } else {
                        $aCollaborators[$nUserID] = $sName;
                    }
                }
            }
            asort($aCollaborators); // Sort collaborators by name.

            $nCurators = count($aCurators);
            $nCollaborators = count($aCollaborators);

            // Curator string.
            $i = 0;
            foreach ($aCurators as $nUserID => $aUser) {
                $i ++;
                list($sName, $nOrder) = $aUser;
                // 2013-06-05; 3.0-06; There should be no link for users not logged in; they can't access these anyways.
                if ($_AUTH) {
                    // Use links, hidden curators possibly in the list (depends on exact user level).
                    $zData['curators_'] .= ($i == 1? '' : ($i == $nCurators? ($i == 2? '' : ',') . ' and ' : ', ')) . ($nOrder? '<B><A href="users/' . $nUserID . '">' . $sName . '</A></B>' : '<I><A href="users/' . $nUserID . '">' . $sName . '</A> (hidden)</I>');
                } else {
                    // Don't use links, and we never see hidden users anyways.
                    $zData['curators_'] .= ($i == 1? '' : ($i == $nCurators? ($i == 2? '' : ',') . ' and ' : ', ')) . '<B>' . $sName . '</B>';
                }
            }
            $this->aColumnsViewEntry['curators_'] .= ' (' . $nCurators . ')';

            if ($_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']) {
                // Collaborator string.
                $i = 0;
                foreach ($aCollaborators as $nUserID => $sName) {
                    $i ++;
                    $zData['collaborators_'] .= ($i == 1? '' : ($i == $nCollaborators? ($i == 2? '' : ',') . ' and ' : ', ')) . '<A href="users/' . $nUserID . '">' . $sName . '</A>';
                }
                $this->aColumnsViewEntry['collaborators_'][0] .= ' (' . $nCollaborators . ')';
            }

            // Links on the stats numbers that lead to their views.
            $zData['variants_'] = 0;
            if ($zData['variants']) {
                $zData['variants_'] = '<A href="variants/' . $zData['id'] . '?search_var_status=%3D%22Marked%22%7C%3D%22Public%22">' . $zData['variants'] . '</A>';
            }

            $zData['uniq_variants_'] = 0;
            if (!empty($zData['uniq_variants']) && $_SETT['customization_settings']['genes_VE_show_unique_variant_counts']) {
                $zData['uniq_variants_'] = '<A href="variants/' . $zData['id'] . '/unique?search_var_status=%3D%22Marked%22%7C%3D%22Public%22">' . $zData['uniq_variants'] . '</A>';
            }

            // The individual count can only be found by adding up all distinct individual's panel_size.
            // 2013-10-11; 3.0-08; This query was first done using GROUP_CONCAT incorporated in the ViewEntry query. However, since the results were sometimes too long for MySQL, resulting in incorrect numbers and notices, this query is better represented as a separate query.
            $zData['count_individuals'] = (int) $_DB->query('SELECT SUM(panel_size) FROM (SELECT DISTINCT i.id, i.panel_size FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id) INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE i.panelid IS NULL AND vog.statusid >= ' . STATUS_MARKED . ' AND t.geneid = ?)i', array($zData['id']))->fetchColumn();
            $zData['count_individuals_'] = 0;
            if ($zData['count_individuals']) {
                $zData['count_individuals_'] = '<A href="individuals/' . $zData['id'] . '">' . $zData['count_individuals'] . '</A>';
            }

            $zData['hidden_variants_'] = $zData['hidden_variants'];
            if ($zData['hidden_variants'] && $_AUTH['level'] >= LEVEL_CURATOR) {
                $zData['hidden_variants_'] = '<A href="variants/' . $zData['id'] . '?search_var_status=%3D%22Pending%22%7C%3D%22Non%20public%22">' . $zData['hidden_variants'] . '</A>';
            }

            $zData['note_index'] = html_entity_decode($zData['note_index']);

            $zData['created_date_'] = preg_replace('/ 00:00:00.*$/', '', $zData['created_date_']);
            if ($zData['updated_date']) {
                $zData['version_'] = '<B>' . $zData['id'] . date(':ymd', strtotime($zData['updated_date_'])) . '</B>';
            } else {
                unset($this->aColumnsViewEntry['version_']);
                if ($_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) {
                    // Also unset the empty updated_date field; users lower than collaborator don't see the updated_by field, either.
                    unset($this->aColumnsViewEntry['updated_date_']);
                }
            }
            if ($_AUTH['level'] < $_SETT['user_level_settings']['see_nonpublic_data']) {
                // Public, change date timestamps to human readable format.
                $zData['created_date_'] = date('F d, Y', strtotime($zData['created_date_']));
                $zData['updated_date_'] = date('F d, Y', strtotime($zData['updated_date_']));
            }

            // Graphs & utilities.
            if ($zData['variants']) {
                $zData['graphs'] = '<A href="' . CURRENT_PATH . '/graphs" class="hide">Graphs displaying summary information of all variants in the database</A> &raquo;';
                $sURLBedFile = rawurlencode(str_replace('https://', 'http://', ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL())) . 'api/rest/variants/' . $zData['id'] . '?format=text/bed');
                $sURLUCSC = 'http://genome.ucsc.edu/cgi-bin/hgTracks?clade=mammal&amp;org=Human&amp;db=' . $_CONF['refseq_build'] . '&amp;position=chr' . $zData['chromosome'] . ':' . ($zData['position_g_mrna_start'] - 50) . '-' . ($zData['position_g_mrna_end'] + 50) . ($zData['sense']? '' : '&amp;complement_hg19=1') . '&amp;hgt.customText=' . $sURLBedFile;
                $zData['ucsc'] = 'Show variants in the UCSC Genome Browser (<A href="' . $sURLUCSC . '" target="_blank">full view</A>, <A href="' . $sURLUCSC . rawurlencode('&visibility=4') . '" target="_blank">compact view</A>)';
                if ($_CONF['refseq_build'] == 'hg18') {
                    $sURLEnsembl = 'http://may2009.archive.ensembl.org/Homo_sapiens/Location/View?r=' . $zData['chromosome'] . ':' . ($zData['position_g_mrna_start'] - 50) . '-' . ($zData['position_g_mrna_end'] + 50) . ';data_URL=';
                } elseif ($_CONF['refseq_build'] == 'hg19') {
                    $sURLEnsembl = 'http://grch37.ensembl.org/Homo_sapiens/Location/View?r=' . $zData['chromosome'] . ':' . ($zData['position_g_mrna_start'] - 50) . '-' . ($zData['position_g_mrna_end'] + 50) . ';contigviewbottom=url:';
                } else {
                    $sURLEnsembl = 'http://www.ensembl.org/Homo_sapiens/Location/View?r=' . $zData['chromosome'] . ':' . ($zData['position_g_mrna_start'] - 50) . '-' . ($zData['position_g_mrna_end'] + 50) . ';contigviewbottom=url:';
                }
                // The weird addition in the end is to fake a proper name in Ensembl.
                // $sURLEnsembl .= $sURLBedFile . rawurlencode('&name=/' . $zData['id'] . ' variants');
                // The name can not be configured anymore, anything I tried, failed.
                $sURLEnsembl .= $sURLBedFile;
                $zData['ensembl'] = 'Show variants in the Ensembl Genome Browser (<A href="' . $sURLEnsembl . '=labels" target="_blank">full view</A>, <A href="' . $sURLEnsembl . '=normal" target="_blank">compact view</A>)';
                $zData['ncbi'] = 'Show distribution histogram of variants in the <A href="https://www.ncbi.nlm.nih.gov/projects/sviewer/?id=' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$zData['chromosome']] . '&amp;v=' . ($zData['position_g_mrna_start'] - 100) . ':' . ($zData['position_g_mrna_end'] + 100) . '&amp;content=7&amp;url=' . $sURLBedFile . '" target="_blank">NCBI Sequence Viewer</A>';

            } else {
                if (!$zData['rf_checker_']) {
                    // Remove the displays/utilities info table when there are also no reading
                    // frame checker results.
                    unset($this->aColumnsViewEntry['TableStart_Graphs'],
                          $this->aColumnsViewEntry['TableHeader_Graphs'],
                          $this->aColumnsViewEntry['TableEnd_Graphs'],
                          $this->aColumnsViewEntry['HR_2']);
                }
                unset($this->aColumnsViewEntry['graphs'],
                      $this->aColumnsViewEntry['ucsc'],
                      $this->aColumnsViewEntry['ensembl'],
                      $this->aColumnsViewEntry['ncbi']);
            }

            // URLs for "Links to other resources".
            $zData['url_homepage_'] = ($zData['url_homepage']? '<A href="' . $zData['url_homepage'] . '" target="_blank">' . $zData['url_homepage'] . '</A>' : '');
            $zData['url_external_'] = '';
            if ($zData['url_external']) {
                $aLinks = explode("\r\n", $zData['url_external']);

                foreach ($aLinks as $sLink) {
                    if (preg_match('/^(.+) &lt;(.+)&gt;$/', $sLink, $aRegs)) {
                        $zData['url_external_'] .= ($zData['url_external_']? '<BR>' : '') . '<A href="' . $aRegs[2] . '" target="_blank">' . $aRegs[1] . '</A>';
                    } else {
                        $zData['url_external_'] .= ($zData['url_external_']? '<BR>' : '') . '<A href="' . $sLink . '" target="_blank">' . $sLink . '</A>';
                    }
                }
            }

            $aExternal = array('id_omim', 'id_hgnc', 'id_entrez', 'id_pubmed_gene', 'show_hgmd', 'show_genecards', 'show_genetests', 'show_orphanet');
            foreach ($aExternal as $sColID) {
                list($sType, $sSource) = explode('_', $sColID, 2);
                if (!empty($zData[$sColID])) {
                    // For IDs and the GeneTests link, use the IDs for the URL, otherwise use the gene symbol;
                    //  for IDs, use the IDs in the visible part of the link, otherwise use the gene symbol.
                    // FIXME: Note that id_pubmed_gene now uses the gene symbol in the visible part of the link (code below this block);
                    //  it would be good if we'd standardize that.
                    $zData[$sColID . '_'] = '<A href="' .
                        lovd_getExternalSource($sSource,
                            ($sType == 'id' || $sSource == 'genetests' || $sSource == 'orphanet'? $zData[$sColID] :
                                rawurlencode($zData['id'])), true) . '" target="_blank">' .
                        ($sType == 'id'? $zData[$sColID] : rawurlencode($zData['id'])) . '</A>';
                } else {
                    $zData[$sColID . '_'] = '';
                }
            }
            // The link to PubMed articles showed the Entrez Gene ID, which might be misinterpreted as a number of articles. Replaced by Gene Symbol.
            $zData['id_pubmed_gene_'] = str_replace($zData['id_entrez'] . '</A>', $zData['id'] . '</A>', $zData['id_pubmed_gene_']);

            // Disclaimer.
            $sYear = substr($zData['created_date'], 0, 4);
            $sYear = ((int) $sYear && $sYear < date('Y')? $sYear . '-' . date('Y') : date('Y'));
            $aDisclaimer = array(0 => 'No', 1 => 'Standard LOVD disclaimer', 2 => 'Own disclaimer');
            $zData['disclaimer_']      = $aDisclaimer[$zData['disclaimer']];
            $zData['disclaimer_text_'] = (!$zData['disclaimer']? '' : ($zData['disclaimer'] == 2? html_entity_decode($zData['disclaimer_text']) :
                'The contents of this LOVD database are the intellectual property of the respective submitter(s) and curator(s) of the individual records. Individual data entries may indicate which data license applies to that specific record. When no license is listed, no permissions are granted. Any unauthorized use, copying, storage, or distribution of this material without written permission from the curator(s) will lead to copyright infringement with possible ensuing litigation. Copyright &copy; ' . $sYear . '. All Rights Reserved. For further details, refer to Directive 96/9/EC of the European Parliament and the Council of March 11 (1996) on the legal protection of databases.<BR><BR>We have used all reasonable efforts to ensure that the information displayed on these pages and contained in the databases is of high quality. We make no warranty, express or implied, as to its accuracy or that the information is fit for a particular purpose, and will not be held responsible for any consequences arising from any inaccuracies or omissions. Individuals, organizations, and companies that use this database do so on the understanding that no liability whatsoever, either direct or indirect, shall rest upon the data submitter(s), curator(s), or any of their employees or agents for the effects of any product, process, or method that may be produced or adopted by any part, notwithstanding that the formulation of such product, process or method may be based upon information here provided.'));

            // Unset fields that will not be shown if they're empty.
            foreach (array('note_index', 'refseq_url_', 'url_homepage_',
                    'url_external_' , 'id_entrez_', 'id_pubmed_gene_',
                    'id_omim_', 'disease_omim_', 'show_hgmd_',
                    'show_genecards_', 'show_genetests_', 'show_orphanet_',
                    'rf_checker_') as $key) {
                if (empty($zData[$key])) {
                    unset($this->aColumnsViewEntry[$key]);
                }
            }
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        global $zData;

        $_POST['chrom_band'] = $zData['chrom_band'];
        $_POST['disclaimer'] = '1';
    }
}
?>
