<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-12-15
 * Modified    : 2010-12-21
 * For LOVD    : 3.0-pre-11
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

        // SQL code for loading an entry for an edit form.
		//$this->sSQLLoadEntry = 'SELECT d.*, COUNT(p2v.variantid) AS variants FROM ' . TABLE_DBS . ' AS d LEFT OUTER JOIN ' . TABLE_PAT2VAR . ' AS p2v USING (symbol)';
		
		// SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'g.*, GROUP_CONCAT(DISTINCT d.symbol ORDER BY g2d.diseaseid SEPARATOR ", ") AS diseases_, GROUP_CONCAT(DISTINCT d.symbol, "_", d.name, "_", d.id_omim ORDER BY g2d.diseaseid SEPARATOR ", ") AS disease_omim_, uc.name AS created_by_, ue.name AS edited_by_, uu.name AS updated_by, count(DISTINCT vot.id) AS variants';
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
                        'symbol' => 'Gene symbol',
                        'name' => 'Gene name',
                        'chrom_location' => 'Chromosome location',
                        'reference' => 'Reference',
                        'refseq_genomic' => 'Reference location',
                        'url_homepage' => 'Homepage URL',
                        'url_external' => 'External URL',
                        'allow_download' => 'Allow public to download all variant entries',
                        'allow_index_wiki' => 'Allow data to be indexed by WikiProfessional',
                        'note_index' => 'Notes for the LOVD gene homepage',
                        'note_listing' => 'Notes for the variant listings',
                        'genbank' => 'Has a genbank file',
                        'genbank_uri' => 'Gebank URI',
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
                        'id_hgnc' => 'HGNC',
                        'id_entrez' => 'Entrez Gene',
                        'id_omim' => 'OMIM - Gene',
                        'disease_omim_' => 'OMIM - Diseases',
                        'show_hgmd' => 'HGMD',
                        'show_genecards' => 'GeneCards',
                        'show_genetests' => 'GeneTests',
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
                        'symbol' => array(
                                    'view' => array('Symbol', 70),
                                    'db'   => array('g.symbol', 'ASC', true)),
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
        $this->sSortDefault = 'symbol';

        parent::Object();
	}




	/*
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
	*/
	


	/*
	function getForm ()
    {
        // Build the form.

        // Get list of diseases, to connect gene to disease.
        $aData = array();
        $qData = mysql_query('SELECT id, CONCAT(id, " (", name, ")") FROM ' . TABLE_DISEASES . ' ORDER BY id');
        $nData = mysql_num_rows($qData);
        $nFieldSize = ($nData < 20? $nData : 20);
        while ($r = mysql_fetch_row($qData)) {
            $aData[$r[0]] = $r[1];
        }

        // Array which will make up the form table.
        $this->aFormData =
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>Gene information</B>'),
                        array('Gene abbreviation', '', 'text', 'symbol', 15),
                        array('Gene name', '', 'text', 'name', 40),
                        array('OMIM ID', '', 'text', 'id_omim', 10),
                        'skip',
                        array('', '', 'print', '<B>Relation to diseases</B>'),
                        array('This gene has been linked to these diseases', '', 'select', 'active_genes', $nFieldSize, $aData, false, true, false),
                  );

        return parent::getForm();
    }
	*/
	
	
	
	
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
            $zData['symbol'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['symbol'] . '</A>';
        } else {
            $zData['allow_download']        = '<IMG src="gfx/mark_' . $zData['allow_download'] . '.png" alt="" width="11" height="11">';
            $zData['allow_index_wiki']               = '<IMG src="gfx/mark_' . $zData['allow_index_wiki'] . '.png" alt="" width="11" height="11">';
            $zData['genbank']               = '<IMG src="gfx/mark_' . $zData['genbank'] . '.png" alt="" width="11" height="11">';
            $zData['disclaimer']               = '<IMG src="gfx/mark_' . $zData['disclaimer'] . '.png" alt="" width="11" height="11">';
            $zData['header_align']               = '<IMG src="gfx/mark_' . $zData['header_align'] . '.png" alt="" width="11" height="11">';
            $zData['footer_align']               = '<IMG src="gfx/mark_' . $zData['footer_align'] . '.png" alt="" width="11" height="11">';
            if (!empty($zData['id_omim'])) {
                $zData['id_omim'] = '<A href="' . lovd_getExternalSource('omim', $zData['id_omim'], true) . '" target="_blank">' . $zData['id_omim'] . '</A>';
            }
            if (!empty($zData['id_hgnc'])) {
                $zData['id_hgnc'] = '<A href="' . lovd_getExternalSource('hgnc', $zData['id_hgnc'], true) . '" target="_blank">' . $zData['id_hgnc'] . '</A>';
            }
            if (!empty($zData['id_entrez'])) {
                $zData['id_entrez'] = '<A href="' . lovd_getExternalSource('entrez', $zData['id_entrez'], true) . '" target="_blank">' . $zData['id_entrez'] . '</A>';
            }
            if (!empty($zData['disease_omim_'])) {
                $aDiseases = explode(", ", $zData['disease_omim_']);
                $zData['disease_omim_'] = "";
                foreach ($aDiseases as $sDisease) {
                    $aDisease = explode("_", $sDisease);
                    $zData['disease_omim_'] .= '<A href="' . lovd_getExternalSource('omim', $aDisease[2], true) . '" target="_blank">' . $aDisease[1] . '(' . $aDisease[0] . ')</A><BR>';
                }
            }
            if ($zData['show_hgmd']) {
                $zData['show_hgmd'] = '<A href="' . lovd_getExternalSource('hgmd', rawurlencode($zData['id']), true) . '" target="_blank">' . rawurlencode($zData['id']) . '</A>';
            }
            if ($zData['show_genecards']) {
                $zData['show_genecards'] = '<A href="' . lovd_getExternalSource('genecards', rawurlencode($zData['id']), true) . '" target="_blank">' . rawurlencode($zData['id']) . '</A>';
            }
            if ($zData['show_genetests']) {
                $zData['show_genetests'] = '<A href="' . lovd_getExternalSource('genetests', rawurlencode($zData['id']), true) . '" target="_blank">' . rawurlencode($zData['id']) . '</A>';
            }
        }

        return $zData;
    }

}
?>