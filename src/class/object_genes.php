<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2011-10-11
 * For LOVD    : 3.0-alpha-05
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





class LOVD_Gene extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Gene';





    function LOVD_Gene ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT g.*, ' .
                               'GROUP_CONCAT(DISTINCT g2d.diseaseid ORDER BY g2d.diseaseid SEPARATOR ";") AS _active_diseases ' .
                               'FROM ' . TABLE_GENES . ' AS g ' .
                               'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                               'WHERE g.id = ? ' .
                               'GROUP BY g.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'g.*, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IFNULL(d.id_omim, " "), ";", d.symbol, ";", d.name ORDER BY d.symbol SEPARATOR ";;") AS __diseases, ' .
                                           'COUNT(t.id) AS transcripts,' .
                                           'GROUP_CONCAT(DISTINCT u2g.userid, ";", ua.name, ";", u2g.allow_edit, ";", show_order ORDER BY (u2g.show_order > 0) DESC, u2g.show_order SEPARATOR ";;") AS __curators, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'uu.name AS updated_by_, ' .
                                           'COUNT(DISTINCT vot.id) AS variants';
        $this->aSQLViewEntry['FROM']     = TABLE_GENES . ' AS g ' .
                                           'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (g2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (u2g.userid = ua.id' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : ' AND u2g.show_order > 0') . ')' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (g.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (g.edited_by = ue.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uu ON (g.updated_by = uu.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (t.id = vot.transcriptid)';
        $this->aSQLViewEntry['GROUP_BY'] = 'g.id';

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'g.*, ' .
                                          'g.id AS geneid, ' .
                                          'GROUP_CONCAT(DISTINCT d.symbol ORDER BY g2d.diseaseid SEPARATOR ", ") AS diseases_, ' .
                                          'COUNT(DISTINCT t.id) AS transcripts, ' .
                                          '(SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (t.id = vot.transcriptid) WHERE t.geneid = g.id) AS variants';
        $this->aSQLViewList['FROM']     = TABLE_GENES . ' AS g ' .
                                          'LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) ' .
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
                        'refseq_genomic_' => 'Genomic reference',
                        'diseases_' => 'Associated with diseases',
                        'reference' => 'Citation reference(s)',
                        'url_homepage' => 'Homepage URL',
                        'url_external' => 'External URL',
                        'allow_download_' => 'Allow public to download all variant entries',
                        'allow_index_wiki_' => 'Allow data to be indexed by WikiProfessional',
                        'note_index' => 'Notes for the LOVD gene homepage',
                        'note_listing' => 'Notes for the variant listings',
                        'refseq' => 'Refseq',
                        'refseq_url' => 'Refseq URL',
                        'disclaimer_' => array('Disclaimer', LEVEL_COLLABORATOR),
                        'header_' => 'Header',
                        'footer_' => 'Footer',
                        'curators_' => 'Curators',
                        'collaborators_' => array('Collaborators', LEVEL_COLLABORATOR),
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                        'updated_by_' => array('Last updated by', LEVEL_COLLABORATOR),
                        'updated_date_' => array('Date last update', LEVEL_COLLABORATOR),
                        'TableEnd_General' => '',
                        'HR_1' => '',
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

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
                 array(
                        // Copy of the gene's ID for the search terms in the screening's viewEntry.
                        'geneid' => array(
                                    'view' => array('Symbol', 70),
                                    'db'   => array('geneid', 'ASC', 'TEXT')),
                        'id_' => array(
                                    'view' => array('Symbol', 70),
                                    'db'   => array('g.id', 'ASC', true)),
                        'name' => array(
                                    'view' => array('Gene', 300),
                                    'db'   => array('g.name', 'ASC', true)),
                        'chromosome' => array(
                                    'view' => array('Chrom.', 70),
                                    'db'   => array('g.chromosome', 'ASC', true)),
                        'chrom_band' => array(
                                    'view' => array('Band', 70),
                                    'db'   => array('g.chrom_band', false, true)),
                        'transcripts' => array(
                                    'view' => array('Transcripts', 90),
                                    'db'   => array('transcripts', 'DESC', 'INT_UNSIGNED')),
                        'variants' => array(
                                    'view' => array('Variants', 70),
                                    'db'   => array('variants', 'DESC', 'INT_UNSIGNED')),
                        'updated_date_' => array( 
                                    'view' => array('Last updated', 110), 
                                    'db'   => array('g.updated_date', 'DESC', true)),
                        //'uniq_variants' => array(
                        //            'view' => array('Unique variants', 100),
                        //            'db'   => array('uniq_variants', 'DESC', 'INT_UNSIGNED')),
                        'diseases_' => array(
                                    'view' => array('Associated with diseases', 200),
                                    'db'   => array('diseases_', false, 'TEXT')),
                      );
        $this->sSortDefault = 'id_';

        parent::LOVD_Object();
    }





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $zData; // FIXME; this could be done more elegantly.

        // No mandatory fields, since all the gene data is in $_SESSION.

        if (isset($aData['workID'])) {
            unset($aData['workID']);
        }

        parent::checkFields($aData);

        if (!in_array($aData['refseq_genomic'], $zData['genomic_references'])) {
            lovd_errorAdd('refseq_genomic' ,'Please select a proper NG, NC, LRG accession number in the \'NCBI accession number for the genomic reference sequence\' selection box.');
        }

        // FIXME; misschien heb je geen query nodig en kun je via de getForm() data ook bij de lijst komen.
        //   De parent checkFields vraagt de getForm() namelijk al op.
        $qDiseases = lovd_queryDB_Old('SELECT id FROM ' . TABLE_DISEASES);
        $aDiseases = array();
        while ($r = mysql_fetch_row($qDiseases)) {
            $aDiseases[] = $r[0];
        }
        if (isset($aData['active_diseases']) && is_array($aData['active_diseases'])) {
            foreach ($aData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $aDiseases)) {
                    lovd_errorAdd('active_diseases', htmlspecialchars($nDisease) . ' is not a valid disease');
                }
            }
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
        $aCheck =
                 array(
                        'url_homepage' => 'Homepage URL',
                        'refseq_url' => 'Human-readable reference sequence location',
                      );

        foreach ($aCheck as $key => $val) {
            if ($aData[$key] && !lovd_matchURL($aData[$key])) {
                lovd_errorAdd($key, 'The \'' . $val . '\' field does not seem to contain a correct URL.');
            }
        }

        // List of external links.
        if ($aData['url_external']) {
            $aExternalLinks = explode("\r\n", trim($aData['url_external']));
            foreach ($aExternalLinks as $n => $sLink) {
                if (!lovd_matchURL($sLink) && (!preg_match('/^[^<>]+ <?([^< >]+)>?$/', $sLink, $aRegs) || !lovd_matchURL($aRegs[1]))) {
                    lovd_errorAdd('url_external', 'External link #' . ($n + 1) . ' (' . htmlspecialchars($sLink) . ') not understood.');
                }
            }
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_CONF, $zData;

        // Get list of diseases.
        $aDiseasesForm = array();
        $qData = lovd_queryDB_Old('SELECT id, CONCAT(symbol, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        if (!$nData) {
            $aDiseasesForm = array('' => 'No disease entries available');
        }
        while ($r = mysql_fetch_row($qData)) {
            $aDiseasesForm[$r[0]] = $r[1];
        }
        $nFieldSize = (count($aDiseasesForm) < 20? count($aDiseasesForm) : 20);

        // References sequences (genomic and transcripts).
        $aSelectRefseqGenomic = array_combine($zData['genomic_references'], $zData['genomic_references']);
        $aTranscriptNames = array();
        $aTranscriptsForm = array();
        if (!empty($zData['transcripts'])) {
            foreach ($zData['transcripts'] as $sTranscript) {
                if (!isset($aTranscriptNames[preg_replace('/\.\d+/', '', $sTranscript)])) {
                    $aTranscriptsForm[$sTranscript] = lovd_shortenString($zData['transcriptNames'][preg_replace('/\.\d+/', '', $sTranscript)], 50);
                    $aTranscriptsForm[$sTranscript] .= str_repeat(')', substr_count($aTranscriptsForm[$sTranscript], '(')) . ' (' . $sTranscript . ')';
                }
            }
            asort($aTranscriptsForm);
        } else {
            $aTranscriptsForm = array('' => 'No transcripts available');
        }
        
        $nTranscriptsFormSize = (count($aTranscriptsForm) < 10? count($aTranscriptsForm) : 10);

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

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        array('Full gene name', '', 'print', $zData['name'], 50),
                        'hr',
                        array('Official gene symbol', '', 'print', $zData['id']),
                        'hr',
                        array('Chromosome', '', 'print', $zData['chromosome']),
                        'hr',
                        array('Chromosomal band', '', 'text', 'chrom_band', 10),
                        'hr',
                        array('Date of creation (optional)', 'Format: YYYY-MM-DD. If left empty, today\'s date will be used.', 'text', 'created_date', 10),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        'hr',
                        array('This gene has been linked to these diseases', '', 'select', 'active_diseases', $nFieldSize, $aDiseasesForm, false, true, false),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Reference sequences (mandatory)</B>'),
                        array('', '', 'note', 'Collecting variants requires a proper reference sequence. Without a genomic and a transcript reference sequence the variants in this LOVD database cannot be interpreted properly or mapped to the genome.'),
                        'hr',
                        array('Genomic reference sequence', '', 'select', 'refseq_genomic', 1, $aSelectRefseqGenomic, false, false, false),
                        array('', '', 'note', 'Select the genomic reference sequence (NG, NC, LRG accession number). Only the references that are available to LOVD are shown'),
                        'hr',
    'transcripts' =>    array('Transcript reference sequence(s)', '', 'select', 'active_transcripts', $nTranscriptsFormSize, $aTranscriptsForm, false, true, false),
'transcript_info' =>    array('', '', 'note', 'Select transcript references (NM accession numbers). You can select multiple transcripts by holding "Ctrl" on a PC or "Command" on a Mac and clicking all wanted transcripts.'),
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
                        array('HGNC ID', '', 'print', $zData['id_hgnc']),
                        'hr',
                        array('Entrez Gene (Locuslink) ID', '', 'print', ($zData['id_entrez']? $zData['id_entrez'] : 'Not Available')),
                        'hr',
                        array('OMIM Gene ID', '', 'print', ($zData['id_omim']? $zData['id_omim'] : 'Not Available')),
                        'hr',
                        array('Provide link to HGMD', 'Do you want a link to this gene\'s entry in the Human Gene Mutation Database added to the homepage?', 'checkbox', 'show_hgmd'),
                        'hr',
                        array('Provide link to GeneCards', 'Do you want a link to this gene\'s entry in the GeneCards database added to the homepage?', 'checkbox', 'show_genecards'),
                        'hr',
                        array('Provide link to GeneTests', 'Do you want a link to this gene\'s entry in the GeneTests database added to the homepage?', 'checkbox', 'show_genetests'),
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
                        array('', '', 'note', '(Active custom link : <A href="#" onmouseover="lovd_showToolTip(\'Click to insert:<BR>{PMID:[1]:[2]}<BR><BR>Links to abstracts in the PubMed database.<BR>[1] = The name of the author(s).<BR>[2] = The PubMed ID.\');" onmouseout="lovd_hideToolTip();" onclick="lovd_insertCustomLink(this, \'{PMID:[1]:[2]}\'); return false">Pubmed</A>)'),
                        'hr',
                        array('Include disclaimer', '', 'select', 'disclaimer', 1, $aSelectDisclaimer, false, false, false),
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
                        array('Allow my public variant and individual data to be indexed by WikiProfessional', '', 'checkbox', 'allow_index_wiki'),
                        'hr',
                        'skip',
                  );
        if (ACTION == 'edit') {
            unset($this->aFormData['transcripts']);
            $this->aFormData['transcript_info'] = array('Transcriptomic reference sequence(s)', '', 'note', '<B>Transcriptomic references (NM accession numbers) can only be modified in the transcripts page!!!</B>');
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['updated_date_'] = substr($zData['updated_date'], 0, 10);
        } else {
            $zData['allow_download_']   = '<IMG src="gfx/mark_' . $zData['allow_download'] . '.png" alt="" width="11" height="11">';
            $zData['allow_index_wiki_'] = '<IMG src="gfx/mark_' . $zData['allow_index_wiki'] . '.png" alt="" width="11" height="11">';

            // FIXME; zou dit een external source moeten zijn?
            $zData['refseq_genomic_'] = (substr($zData['refseq_genomic'], 0, 3) == 'LRG'? '<A href="ftp://ftp.ebi.ac.uk/pub/databases/lrgex/' . $zData['refseq_genomic'] . '.xml">' : '<A href="http://www.ncbi.nlm.nih.gov/nuccore/' . $zData['refseq_genomic'] . '">')  . $zData['refseq_genomic'] . '</A>';

            $sYear = substr($zData['created_date'], 0, 4);
            $sYear = ((int) $sYear && $sYear < date('Y')? $sYear . '-' . date('Y') : date('Y'));
            $aDisclaimer = array(0 => 'No', 1 => 'Standard LOVD disclaimer', 2 => 'Own disclaimer');
            $zData['disclaimer_']      = $aDisclaimer[$zData['disclaimer']];
            $zData['disclaimer_text_'] = (!$zData['disclaimer']? '' : ($zData['disclaimer'] == 2? $zData['disclaimer_text'] :
                'The contents of this LOVD database are the intellectual property of the respective curator(s). Any unauthorised use, copying, storage or distribution of this material without written permission from the curator(s) will lead to copyright infringement with possible ensuing litigation. Copyright &copy; ' . $sYear . '. All Rights Reserved. For further details, refer to Directive 96/9/EC of the European Parliament and the Council of March 11 (1996) on the legal protection of databases.<BR><BR>We have used all reasonable efforts to ensure that the information displayed on these pages and contained in the databases is of high quality. We make no warranty, express or implied, as to its accuracy or that the information is fit for a particular purpose, and will not be held responsible for any consequences arising out of any inaccuracies or omissions. Individuals, organisations and companies which use this database do so on the understanding that no liability whatsoever either direct or indirect shall rest upon the curator(s) or any of their employees or agents for the effects of any product, process or method that may be produced or adopted by any part, notwithstanding that the formulation of such product, process or method may be based upon information here provided.'));
            
            // FIXME; Voor zover ik weet doen de header en de footer nog niks.    
            $aAlign = array(-1 => 'left', 0 => 'center', 1 => 'right');
            $this->aColumnsViewEntry['header_'] = 'Header (aligned to the ' . $aAlign[$zData['header_align']] . ')';
            $this->aColumnsViewEntry['footer_'] = 'Footer (aligned to the ' . $aAlign[$zData['footer_align']] . ')';
            $zData['header_'] = $zData['header'];
            $zData['footer_'] = $zData['footer'];

            $zData['diseases_'] = '';
            $zData['disease_omim_'] = '';
            foreach($zData['diseases'] as $aDisease) {
                list($nID, $nOMIMID, $sSymbol, $sName) = $aDisease;
                // Link to disease entry in LOVD
                $zData['diseases_'] .= (!$zData['diseases_']? '' : ', ') . '<A href="diseases/' . $nID . '">' . $sSymbol . '</A>';
                // Link to external source disease entry
                $zData['disease_omim_'] .= (!$zData['disease_omim_']? '' : '<BR>') . ($nOMIMID != ' '? '<A href="' . lovd_getExternalSource('omim', $nOMIMID, true) . '" target="_blank">' . $sName . ' (' . $sSymbol . ')</A>' : $sName . ' (' . $sSymbol . ')');
            }

            if (isset($zData['reference'])) {
                // FIXME; is 't niet beter de PubMed custom link data uit de database te halen? Als ie ooit wordt aangepast, gaat dit fout.
                $zData['reference'] = preg_replace('/\{PMID:(.*):(.*)\}/U', '<A href="http://www.ncbi.nlm.nih.gov/pubmed/$2" target="_blank">$1</A>', $zData['reference']);
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
            sort($aCollaborators); // Sort collaborators by name.

            $nCurators = count($aCurators);
            $nCollaborators = count($aCollaborators);

            // Curator string.
            $i = 0;
            foreach ($aCurators as $nUserID => $aUser) {
                $i ++;
                list($sName, $nOrder) = $aUser;
                $zData['curators_'] .= ($i == 1? '' : ($i == $nCurators? ' and ' : ', ')) . ($nOrder? '<B><A href="users/' . $nUserID . '">' . $sName . '</A></B>' : '<I><A href="users/' . $nUserID . '">' . $sName . '</A> (hidden)</I>');
            }
            $this->aColumnsViewEntry['curators_'] .= ' (' . $nCurators . ')';

            if ($_AUTH['level'] >= LEVEL_COLLABORATOR) {
                // Collaborator string.
                $i = 0;
                foreach ($aCollaborators as $nUserID => $sName) {
                    $i ++;
                    $zData['collaborators_'] .= ($i == 1? '' : ($i == $nCollaborators? ' and ' : ', ')) . '<A href="users/' . $nUserID . '">' . $sName . '</A>';
                }
                $this->aColumnsViewEntry['collaborators_'][0] .= ' (' . $nCollaborators . ')';
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
