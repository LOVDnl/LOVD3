<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2011-01-19
 * For LOVD    : 3.0-pre-15
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class Gene extends Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Gene';





    function Gene ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'g.*, GROUP_CONCAT(DISTINCT d.id, ";", d.id_omim, ";", d.symbol, ";", d.name ORDER BY d.symbol SEPARATOR ";;") AS diseases, uc.name AS created_by_, ue.name AS edited_by_, uu.name AS updated_by, count(DISTINCT vot.id) AS variants';
        $this->aSQLViewEntry['FROM']     = TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) LEFT JOIN ' . TABLE_USERS . ' AS uc ON (g.created_by = uc.id) LEFT JOIN ' . TABLE_USERS . ' AS ue ON (g.edited_by = ue.id) LEFT JOIN ' . TABLE_USERS . ' AS uu ON (g.updated_by = uu.id) LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)';
//        $this->aSQLViewEntry['GROUP_BY'] = 'd.id';

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'g.*, GROUP_CONCAT(DISTINCT d.symbol ORDER BY g2d.diseaseid SEPARATOR ", ") AS diseases_, count(DISTINCT vot.id) AS variants';
        $this->aSQLViewList['FROM']     = TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)';
        $this->aSQLViewList['GROUP_BY'] = 'g.id';

        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
                 array(
                        'TableHeader_General' => 'General information',
                        'id' => 'Gene symbol',
                        'name' => 'Gene name',
                        'chrom_location' => 'Chromosome location',
                        'reference' => 'Reference',
                        'refseq_genomic' => 'Reference location',
                        'url_homepage' => 'Homepage URL',
                        'url_external' => 'External URL',
                        'allow_download_' => 'Allow public to download all variant entries',
                        'allow_index_wiki_' => 'Allow data to be indexed by WikiProfessional',
                        'note_index' => 'Notes for the LOVD gene homepage',
                        'note_listing' => 'Notes for the variant listings',
                        'refseq' => 'Refseq',
                        'refseq_url' => 'Refseq URL',
                        'disclaimer' => 'Disclaimer',
                        'disclaimer_text' => 'Disclaimer Text',
                        'header' => 'Header',
                        'header_align' => 'Page header (aligned to the left)',
                        'footer' => 'Footer',
                        'footer_align' => 'Page header (aligned to the left)',
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',
                        'edited_by_' => 'Last edited by',
                        'edited_date' => 'Date last edited',
                        'updated_by' => 'Last updated by',
                        'updated_date' => 'Date last update',
                        'TableEnd_General' => '',
                        'HR_1' => '',
                        'TableStart_Additional' => '',
                        'TableHeader_Additional' => 'Additional information',
                        'variants' => 'Total number of variants',
                        'diseases_' => 'Associated with diseases',
                        'TableEnd_Additional' => '',
                        'HR_2' => '',
                        'TableStart_Links' => '',
                        'TableHeader_Links' => 'Links to other resources',
                        'id_hgnc_' => 'HGNC',
                        'id_entrez_' => 'Entrez Gene',
                        'id_omim_' => 'OMIM - Gene',
                        'disease_omim_' => 'OMIM - Diseases',
                        'show_hgmd_' => 'HGMD',
                        'show_genecards_' => 'GeneCards',
                        'show_genetests_' => 'GeneTests',
                      );

        // Because the disease information is publicly available, remove some columns for the public.
        if ($_AUTH && $_AUTH['level'] < LEVEL_COLLABORATOR) {
            unset($this->aColumnsViewEntry['created_by_']);
            unset($this->aColumnsViewEntry['created_date']);
            unset($this->aColumnsViewEntry['edited_by_']);
            unset($this->aColumnsViewEntry['edited_date']);
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        'id' => array(
                                    'view' => array('Symbol', 70),
                                    'db'   => array('g.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Gene', 300),
                                    'db'   => array('g.name', 'ASC', true)),
                        'chrom_location' => array(
                                    'view' => array('Chrom.', 70),
                                    'db'   => array('g.chrom_location', false, true)),
                        'variants' => array(
                                    'view' => array('Variants', 70),
                                    'db'   => array('variants', 'ASC', true)),
                        'diseases_' => array(
                                    'view' => array('Associated with diseases', 200),
                                    'db'   => array('diseases_', false, true)),
                      );
        $this->sSortDefault = 'id';

        parent::Object();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        if (ACTION == 'edit') {
            global $zData; // FIXME; this could be done more elegantly.
        }

        // Mandatory fields.
        $this->aCheckMandatory =
                 array(
                        'symbol',
                        'name',
                      );
        parent::checkFields($aData);

        // Disease symbol must be unique.
        if (!empty($aData['symbol'])) {
            // Enforced in the table, but we want to handle this gracefully.
            $sSQL = 'SELECT id FROM ' . TABLE_GENES . ' WHERE symbol = ?';
            $aSQL = array($aData['symbol']);
            if (ACTION == 'edit') {
                $sSQL .= ' AND id != ?';
                $aSQL[] = $zData['id'];
            }
            if (mysql_num_rows(lovd_queryDB($sSQL, $aSQL))) {
                lovd_errorAdd('name', 'There is already a gene entry with this abbreviation. Please choose another one.');
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_CONF;

        // Get list of genes
        $aData = array();
        $qData = mysql_query('SELECT id, CONCAT(symbol, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        $nFieldSize = ($nData < 20? $nData : 20);
        while ($r = mysql_fetch_row($qData)) {
            $aData[$r[0]] = $r[1];
        }

        $aTranscripts = mutalyzer_SOAP_module_call("getTranscriptsByGeneName", array("build" => $_CONF['refseq_build'], "name" => $_POST['symbol']));
        $aTranscriptsForm = array();
        foreach ($aTranscripts as $sTranscript) {
            $aTranscriptsForm[$sTranscript] = $sTranscript;
        }
        asort($aTranscriptsForm);
        $nTranscriptsFormSize = count($aTranscriptsForm);
        $nTranscriptsFormSize = ($nTranscriptsFormSize < 10? $nTranscriptsFormSize : 10);

        $aSelectRefseq = array(
                                'c' => 'Coding DNA',
                                'g' => 'Genomic'
                              );
        $aSelectDisclaimer = array(
                                1 => 'Use standard LOVD disclaimer',
                                2 => 'Use own disclaimer (enter below)'
                                  );
        $aSelectHeaderFooter = array(
                                -1 => 'Left',
                                0  => 'Center',
                                -2 => 'Right'
                              );

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        array('Full gene name', '', 'text', 'name', 40),
                        'hr',
                        array('Official gene symbol', '', 'text', 'symbol', 10),
                        array('', '', 'note', 'The gene symbol is used by LOVD to reference to this gene and can\'t be changed later on. To create multiple databases for one gene, append \'_\' and an indentifier, i.e. \'DMD_point\' and \'DMD_deldup\' for the DMD gene.'),
                        'hr',
                        array('Chromosomal location', '', 'text', 'chrom_location', 10),
                        array('', '', 'note', 'Example: Xp21.2'),
                        'hr',
                        array('Date of creation (optional)', '', 'text', 'created_date', 10),
                        array('', '', 'note', 'Format: YYYY-MM-DD. If left empty, today\'s date will be used.'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        'hr',
                        array('This gene has been linked to these diseases', '', 'select', 'active_diseases', $nFieldSize, $aData, false, true, false),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Reference sequences</B>'),
                        array('', '', 'note', 'Collecting variants requires a proper reference sequence.'),
                        'hr',
                        array('', '', 'note', '<B>Find a proper place for this text here!!!</B><BR>Without a (genomic) reference sequence the variants in this LOVD database cannot be interpreted properly. A valid genomic reference sequence can be used to map your variants to a genomic location, as well as creating a human-readable reference sequence format and linking to the mutation check Mutalyzer module.'),
                        array('', '', 'note', 'If you wish to use a NCBI GenBank record, fill in the GenBank accession number. If you have uploaded your GenBank file to Mutalyzer and have received a Mutalyzer UD identifier, fill in this identifier.'),
                        'hr',
                        'skip',
                        array('', '', 'note', '<B>The following three fields are for the mapping of the variants to the genomic reference sequence. They are mandatory, as variants without properly configured reference sequences, cannot be interpreted properly.</B>'),
                        'hr',
                        array('NCBI accession number for the genomic reference sequence', '', 'text', 'refseq_genomic', 15),
                        array('', '', 'note', 'Fill in the NCBI GenBank ID of the genomic reference sequence (NG or NC accession numbers), such as "NG_012232.1" or "NC_000023.10". Always include the version number as well!'),
                        'hr',
                        array('NCBI accession number for the transcript reference sequence', '', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, false),
                        array('', '', 'note', 'Fill in the NCBI GenBank ID of the transcript reference sequence (NM/NR accession numbers), such as "NM_004006.2". Always include the version number as well!'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Links to information sources (optional)</B>'),
                        array('', '', 'note', 'Here you can add links that will be displayed on the gene\'s LOVD gene homepage.'),
                        'hr',
                        array('Homepage URL', '', 'text', 'url_homepage', 40),
                        array('', '', 'note', 'If you have a separate homepage about this gene, you can specify the URL here. Format: complete URL, including "http://".'),
                        'hr',
                        array('External links', '', 'textarea', 'url_external', 55, 3),
                        array('', '', 'note', 'Here you can provide links to other resources on the internet that you would like to link to. One link per line, format: complete URLs or "Description <URL>".'),
                        'hr',
                        array('HGNC ID', '', 'text', 'id_hgnc', 10),
                        'hr',
                        array('Entrez Gene (Locuslink) ID', '', 'text', 'id_entrez', 10),
                        'hr',
                        array('OMIM Gene ID', '', 'text', 'id_omim', 10),
                        'hr',
                        array('Provide link to HGMD', '', 'checkbox', 'show_hgmd'),
                        array('', '', 'note', 'Do you want a link to this gene\'s entry in the Human Gene Mutation Database added to the homepage?'),
                        'hr',
                        array('Provide link to GeneCards', '', 'checkbox', 'show_genecards'),
                        array('', '', 'note', 'Do you want a link to this gene\'s entry in the GeneCards database added to the homepage?'),
                        'hr',
                        array('Provide link to GeneTests', '', 'checkbox', 'show_genetests'),
                        array('', '', 'note', 'Do you want a link to this gene\'s entry in the GeneTests database added to the homepage?'),
                        'hr',
                        array('This gene has a human-readable reference sequence', '', 'select', 'refseq', 1, $aSelectRefseq, 'No', false, false),
                        array('', '', 'note', 'Although GenBank files are the official reference sequence, they are not very readable for humans. If you have a human-readable format of your reference sequence online, please select the type here.'),
                        'hr',
                        array('Human-readable reference sequence location', '', 'text', 'refseq_url', 40),
                        array('', '', 'note', 'If you used our Reference Sequence Parser to create a human-readable reference sequence, the result is located at "http://chromium.liacs.nl/LOVD2/refseq/GENESYMBOL_codingDNA.html".'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Customizations (optional)</B>'),
                        array('', '', 'note', 'You can use the following fields to customize the gene\'s LOVD gene homepage.'),
                        'hr',
                        array('Citation reference(s)', '', 'textarea', 'reference', 30, 3),
                        array('', '', 'note', '(Active custom link : <A href="#" onclick="javascript:lovd_openWindow(\'' . ROOT_PATH . 'links.php?view=1&amp;col=Gene/Reference\', \'LinkView\', \'800\', \'200\'); return false;">PubMed</A>)'),
                        'hr',
                        array('Include disclaimer', '', 'select', 'disclaimer', 1, $aSelectDisclaimer, 'No', false, false),
                        array('', '', 'note', 'If you want a disclaimer added to the gene\'s LOVD gene homepage, select your preferred option here.'),
                        'hr',
                        array('Text for own disclaimer', '', 'textarea', 'disclaimer_text', 55, 3),
                        array('', '', 'note', 'Only applicable if you choose to use your own disclaimer (see option above).'),
                        'hr',
                        array('Page header', '', 'textarea', 'header', 55, 3),
                        array('', '', 'note', 'Text entered here will appear above all public gene-specific pages.'),
                        array('Header aligned to', '', 'select', 'header_align', 1, $aSelectHeaderFooter, false, false, false),
                        'hr',
                        array('Page footer', '', 'textarea', 'footer', 55, 3),
                        array('', '', 'note', 'Text entered here will appear below all public gene-specific pages.'),
                        array('Footer aligned to', '', 'select', 'footer_align', 1, $aSelectHeaderFooter, false, false, false),
                        'hr',
                        array('Notes for the LOVD gene homepage', '', 'textarea', 'note_index', 55, 3),
                        array('', '', 'note', 'Text entered here will appear in the General Information box on the gene\'s LOVD gene homepage.'),
                        'hr',
                        array('Notes for the variant listings', '', 'textarea', 'note_listing', 55, 3),
                        array('', '', 'note', 'Text entered here will appear below the gene\'s variant listings.'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Security settings</B>'),
                        array('', '', 'note', 'Using the following settings you can control some security settings of LOVD.'),
                        'hr',
                        array('Allow public to download variant entries', '', 'checkbox', 'allow_download'),
                        'hr',
                        array('Allow my public variant and patient data to be indexed by WikiProfessional', '', 'checkbox', 'allow_index_wiki'),
                        'hr',
                        'skip',
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
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'genes/' . rawurlencode($zData['id']);
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            $zData['allow_download_']   = '<IMG src="gfx/mark_' . $zData['allow_download'] . '.png" alt="" width="11" height="11">';
            $zData['allow_index_wiki_'] = '<IMG src="gfx/mark_' . $zData['allow_index_wiki'] . '.png" alt="" width="11" height="11">';
// FIXME; Deze zijn niet correct; hier moet even iets anderes voor verzonnen worden.
//            $zData['disclaimer']       = '<IMG src="gfx/mark_' . $zData['disclaimer'] . '.png" alt="" width="11" height="11">';
//            $zData['header_align']     = '<IMG src="gfx/mark_' . $zData['header_align'] . '.png" alt="" width="11" height="11">';
//            $zData['footer_align']     = '<IMG src="gfx/mark_' . $zData['footer_align'] . '.png" alt="" width="11" height="11">';

            $zData['diseases_'] = $zData['disease_omim_'] = '';
            if (!empty($zData['diseases'])) {
                $aDiseases = explode(';;', $zData['diseases']);
                foreach ($aDiseases as $sDisease) {
                    list($nID, $nOMIMID, $sSymbol, $sName) = explode(';', $sDisease);
                    $zData['diseases_'] .= (!$zData['diseases_']? '' : ', ') . '<A href="diseases/' . $nID . '">' . $sSymbol . '</A>';
                    $zData['disease_omim_'] .= (!$zData['disease_omim_']? '' : '<BR>') . '<A href="' . lovd_getExternalSource('omim', $nOMIMID, true) . '" target="_blank">' . $sName . ' (' . $sSymbol . ')</A>';
                }
            }

            $aExternal = array('id_omim', 'id_hgnc', 'id_entrez', 'show_hgmd', 'show_genecards', 'show_genetests');
            foreach ($aExternal as $sColID) {
                list($sType, $sSource) = explode('_', $sColID);
                if (!empty($zData[$sColID])) {
                    $zData[$sColID . '_'] = '<A href="' . lovd_getExternalSource($sSource, ($sType == 'id'? $zData[$sColID] : rawurlencode($zData['id'])), true) . '" target="_blank">' . ($sType == 'id'? $zData[$sColID] : rawurlencode($zData['id'])) . '</A>';
                } else {
                    $zData[$sColID . '_'] = '';
                }
            }
        }

        return $zData;
    }
}
?>
