<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-06-23
 * Modified    : 2020-05-28
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;

class ImportDataForUpdateFailedTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/import');
        $this->enterValue('import', ROOT_PATH . '../tests/test_data_files/ImportUpdateFailed.txt');
        $this->selectValue('mode', 'Update existing data (in beta)');
        $this->check('simulate');
        $this->submitForm('Import file');

        $this->waitForElement(WebDriverBy::xpath('//div[@class="err"]'));
        $this->driver->findElement(WebDriverBy::linkText('Show 39 warnings'))->click();

        $this->assertEquals(
'Error (Columns, line 8): Will not update column Individual/Age_of_death, too many fields are different from the database (col_order, width, standard, head_column, description_form, description_legend_short, description_legend_full, mysql_type, form_type, select_options, preg_pattern). There is a maximum of 1 difference to prevent accidental updates.
Error (Columns, line 8): Can\'t update hgvs for column entry Individual/Age_of_death: Not allowed to change the HGVS standard status of any column. Value is currently "0" and value in the import file is "1".
Error (Columns, line 8): The field \'col_order\' must contain a positive integer, "abc" does not match.
Error (Columns, line 8): Incorrect value for field \'col_order\', which needs to be numeric, between 0 and 255.
Error (Columns, line 8): Incorrect value for field \'standard\', which should be 0 or 1.
Error (Columns, line 8): Select option #3 "yes()* = Consanguineous parents" not understood.
Error (Columns, line 8): The \'Regular expression pattern\' field does not seem to contain valid PHP Perl compatible regexp syntax.
Error (Columns, line 9): ID "Individual/Age_of_death" already defined at line 8.
Error (Genes, line 15): Will not update gene IVD, too many fields are different from the database (chrom_band, refseq_genomic). There is a maximum of 1 difference to prevent accidental updates.
Error (Genes, line 15): Can\'t update name for gene entry IVD: Not allowed to change the gene name. Value is currently "isovaleryl-CoA dehydrogenase" and value in the import file is "isovaleryl-CoA dehydrogenase1".
Error (Genes, line 15): Can\'t update chromosome for gene entry IVD: Not allowed to change the chromosome. Value is currently "15" and value in the import file is "151".
Error (Genes, line 15): Can\'t update refseq_UD for gene entry IVD: Not allowed to change the Mutalyzer UD refseq ID. Value is currently "UD_144371086438" and value in the import file is "UD_142663684045".
Error (Genes, line 15): Please select a valid entry from the \'refseq_genomic\' selection box, \'NG_011986.1\' is not a valid value. Please choose from these options: \'NG_011986.2\'.
Error (Genes, line 15): The \'chromosome\' field is limited to 2 characters, you entered 3.
Error (Genes, line 16): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Transcripts, line 22): Can\'t update id_ncbi for transcript entry 00001: Not allowed to change the NCBI ID. Value is currently "NM_002225.3" and value in the import file is "NM_999999.3".
Error (Transcripts, line 22): Can\'t update position_g_mrna_start for transcript entry 00001: Not allowed to change the genomic start position. Value is currently "40697686" and value in the import file is "40405485".
Error (Transcripts, line 22): Can\'t update position_g_mrna_end for transcript entry 00001: Not allowed to change the genomic end position. Value is currently "40713512" and value in the import file is "40421313".
Error (Transcripts, line 22): Transcript "00001" does not match the same gene and/or the same NCBI ID as in the database.
Error (Transcripts, line 23): Can\'t update position_g_mrna_start for transcript entry 00001: Not allowed to change the genomic start position. Value is currently "40697686" and value in the import file is "40405485".
Error (Transcripts, line 23): Can\'t update position_g_mrna_end for transcript entry 00001: Not allowed to change the genomic end position. Value is currently "40713512" and value in the import file is "40421313".
Error (Transcripts, line 23): ID "00001" already defined at line 22.
Error (Diseases, line 29): Will not update disease 00000, too many fields are different from the database (symbol, name, id_omim). There is a maximum of 1 difference to prevent accidental updates.
Error (Diseases, line 29): Another disease already exists with this OMIM ID!
Error (Diseases, line 29): Another disease already exists with the same name!
Error (Genes_To_Diseases, line 38): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Individuals, line 44): Individual "00000022" does not exist in the database and is not defined (properly) in this import file.
When referring to panels that are also defined in the import file, make sure they are defined above the individuals referring to them. Therefore, make sure that in the import file individual "00000022" is defined above individual "00000001".
Error (Individuals, line 45): Will not update individual 00000002, too many fields are different from the database (panelid, panel_size). There is a maximum of 1 difference to prevent accidental updates.
Error (Individuals, line 45): The \'Panel ID\' can not link to itself; this field is used to indicate to which panel this individual belongs.
Error (Individuals, line 45): Panel size of Individual "00000002" must be lower than the panel size of Individual "00000002".
Error (Individuals, line 46): Will not update individual 00000003, too many fields are different from the database (fatherid, motherid, panelid). There is a maximum of 1 difference to prevent accidental updates.
Error (Individuals, line 46): Panel ID "00000001" refers to an individual, not a panel (group of individuals). If you want to configure that individual as a panel, set its \'Panel size\' field to a value higher than 1.
Error (Individuals, line 46): The \'fatherid\' can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual.
Error (Individuals, line 46): Individual "00000022" does not exist in the database and is not defined (properly) in this import file.
When referring to parents that are also defined in the import file, make sure they are defined above the children referring to them. Therefore, make sure that in the import file individual "00000022" is defined above individual "00000003".
Error (Individuals, line 47): Will not update individual 00000004, too many fields are different from the database (fatherid, motherid, panelid). There is a maximum of 1 difference to prevent accidental updates.
Error (Individuals, line 47): The fatherid "00000003" you entered does not refer to a male individual.
Error (Individuals, line 47): The motherid "00000002" refers to an panel (group of individuals), not an individual. If you want to configure that panel as an individual, set its \'Panel size\' field to value 1.
Error (Individuals, line 48): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Individuals_To_Diseases, line 55): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Phenotypes, line 61): Can\'t update diseaseid for phenotype entry 0000000001: Not allowed to change the disease. Value is currently "00001" and value in the import file is "00004".
Error (Phenotypes, line 61): Can\'t update individualid for phenotype entry 0000000001: Not allowed to change the individual. Value is currently "00000001" and value in the import file is "00000022".
Error (Phenotypes, line 61): Disease "00004" does not exist in the database and is not defined in this import file.
Error (Phenotypes, line 61): Individual "00000022" does not exist in the database and is not defined in this import file.
Error (Phenotypes, line 62): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Screenings, line 68): Can\'t update individualid for screening entry 0000000001: Not allowed to change the individual. Value is currently "00000001" and value in the import file is "00000022".
Error (Screenings, line 68): Individual "00000022" does not exist in the database and is not defined in this import file.
Error (Screenings, line 69): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Screenings_To_Genes, line 76): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Variants_On_Genome, line 82): Can\'t update position_g_start for variant entry 0000000001: Not allowed to change the genomic start position. Value is currently "40702876" and value in the import file is "abc".
Error (Variants_On_Genome, line 82): The field \'position_g_start\' must contain a positive integer, "abc" does not match.
Error (Variants_On_Genome, line 82): Variant start position is larger than variant end position.
Error (Variants_On_Genome, line 83): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Variants_On_Transcripts, line 89): Can\'t update position_c_start for variant entry 0000000001: Not allowed to change the start position. Value is currently "345" and value in the import file is "abc".
Error (Variants_On_Transcripts, line 89): The field \'position_c_start\' must contain an integer, "abc" does not match.
Error (Variants_On_Transcripts, line 89): Variant start position is larger than variant end position.
Error (Variants_On_Transcripts, line 91): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Error (Screenings_To_Variants, line 98): This line refers to a non-existing entry. When the import mode is set to update, no new inserts can be done.
Hide 39 warnings
Warning (Columns, line 8): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Columns, line 8): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00000".
Warning (Columns, line 8): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning (Columns, line 9): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Genes, line 15): Created by field is set by LOVD. Value is currently "00002" and the value in the import file is "00001".
Warning (Genes, line 15): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Genes, line 15): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Genes, line 15): Updated by field is set by LOVD. Value is currently "00002" and the value in the import file is "00001".
Warning (Genes, line 15): Updated date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Transcripts, line 22): Created by field is set by LOVD. Value is currently "00002" and the value in the import file is "00001".
Warning (Transcripts, line 22): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Transcripts, line 22): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Transcripts, line 22): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning (Transcripts, line 23): Created by field is set by LOVD. Value is currently "00002" and the value in the import file is "00001".
Warning (Transcripts, line 23): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Diseases, line 29): Created by field is set by LOVD. Value is currently "00000" and the value in the import file is "00001".
Warning (Diseases, line 29): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Diseases, line 30): Created by field is set by LOVD. Value is currently "00002" and the value in the import file is "00001".
Warning (Diseases, line 30): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Diseases, line 30): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Diseases, line 30): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning (Diseases, line 31): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Diseases, line 31): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Diseases, line 31): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning: It is currently not possible to do an update on section Genes_To_Diseases via an import.
Warning (Individuals, line 44): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Individuals, line 44): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning: It is currently not possible to do an update on section Individuals_To_Diseases via an import.
Warning (Phenotypes, line 61): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Phenotypes, line 61): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Phenotypes, line 61): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning (Screenings, line 68): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Screenings, line 68): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning (Screenings, line 68): Edited date field is set by LOVD. Value is currently empty and the value in the import file is "0000-00-00 00:00:00".
Warning: It is currently not possible to do an update on section Screenings_To_Genes via an import.
Warning: the following column has been ignored from the Variants_On_Genome data on line 81, because it is not in the database: VariantOnGenome/Frequency (lost 2 values).
Warning (Variants_On_Genome, line 82): Created date field is set by LOVD. Value is currently "0000-00-00 00:00:00" and the value in the import file is "0000-00-00 00:00:00".
Warning (Variants_On_Genome, line 82): Edited by field is set by LOVD. Value is currently empty and the value in the import file is "00001".
Warning: It is currently not possible to do an update on section Screenings_To_Variants via an import.',
            preg_replace(
                '/\b[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\b/',
                '0000-00-00 00:00:00',
                $this->driver->findElement(WebDriverBy::xpath('//div[@class="err"]'))->getText()));
    }
}
?>
