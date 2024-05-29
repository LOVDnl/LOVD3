<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-06-05
 * Modified    : 2024-05-27
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

class ImportDataFileFailedTest extends LOVDSeleniumWebdriverBaseTestCase
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

        // To make sure we have always the same results, we have to run the variant mapper at least once. It'll trigger
        //  the variant mapper to double-check transcripts and fill in Mutalyzer IDs for the ones that are missing one.
        // We can't be sure if the mapper has already run during the test, so make sure it does.
        do {
            $this->driver->get(ROOT_URL . '/src/ajax/map_variants.php');
            // We get failures sometimes in the download verification test,
            //  because the mapping apparently did not complete.
            // For now, log the output that we get. Maybe there's a pattern.
            $sBody = rtrim($this->driver->findElement(WebDriverBy::tagName('body'))->getText());
        } while (substr($sBody, 0, 5) != '0 99 ');
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/import');
        $this->enterValue('import', ROOT_PATH . '../tests/test_data_files/ImportInsertFailed.txt');
        $this->selectValue('mode', 'Add only, treat all data as new');
        $this->check('simulate');
        $this->submitForm('Import file');

        $this->waitForElement(WebDriverBy::xpath('//div[@class="err"]'));
        $this->driver->findElement(WebDriverBy::linkText('Show 6 warnings'))->click();

        $this->assertEquals(
'Error (Columns, line 9): The field \'col_order\' must contain a positive integer, "abc" does not match.
Error (Columns, line 9): Incorrect value for field \'col_order\', which needs to be numeric, between 0 and 255.
Error (Columns, line 9): Incorrect value for field \'standard\', which should be 0 or 1.
Error (Columns, line 9): Select option #3 "yes()* = Consanguineous parents" not understood.
Error (Columns, line 9): The \'Regular expression pattern\' field does not seem to contain valid PHP Perl compatible regexp syntax.
Error (Columns, line 9): Not allowed to create new HGVS standard columns. Change the value for \'hgvs\' to 0.
Error (Genes, line 16): Gene "ARSE" (arylsulfatase E (chondrodysplasia punctata 1)) does not exist in the database. Currently, it is not possible to import genes into LOVD using this file format.
Error (Transcripts, line 23): Transcript "00002" does not match the same gene and/or the same NCBI ID as in the database.
Error (Diseases, line 31): Another disease already exists with the same name!
Error (Diseases, line 31): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database.
Error (Diseases, line 32): Another disease already exists with this OMIM ID at line 30.
Error (Diseases, line 32): Another disease already exists with the same name at line 30.
Error (Genes_To_Diseases, line 41): ID "IVD|2" already defined at line 40.
Error (Genes_To_Diseases, line 42): Gene "DAAM1" does not exist in the database.
Error (Genes_To_Diseases, line 42): Disease "00022" does not exist in the database and is not defined in this import file.
Error (Individuals, line 50): Individual "00000022" does not exist in the database and is not defined (properly) in this import file.
When referring to panels that are also defined in the import file, make sure they are defined above the individuals referring to them. Therefore, make sure that in the import file individual "00000022" is defined above individual "00000003".
Error (Individuals, line 51): The \'Panel ID\' can not link to itself; this field is used to indicate to which panel this individual belongs.
Error (Individuals, line 51): Panel ID "00000004" refers to an individual, not a panel (group of individuals). If you want to configure that individual as a panel, set its \'Panel size\' field to a value higher than 1.
Error (Individuals, line 52): Panel ID "00000001" refers to an individual, not a panel (group of individuals). If you want to configure that individual as a panel, set its \'Panel size\' field to a value higher than 1.
Error (Individuals, line 53): Panel size of Individual "00000006" must be lower than the panel size of Individual "00000002".
Error (Individuals, line 54): Individual "00000022" does not exist in the database and is not defined (properly) in this import file.
When referring to parents that are also defined in the import file, make sure they are defined above the children referring to them. Therefore, make sure that in the import file individual "00000022" is defined above individual "00000007".
Error (Individuals, line 54): Individual "00000022" does not exist in the database and is not defined (properly) in this import file.
When referring to parents that are also defined in the import file, make sure they are defined above the children referring to them. Therefore, make sure that in the import file individual "00000022" is defined above individual "00000007".
Error (Individuals, line 55): The \'fatherid\' can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual.
Error (Individuals, line 55): The \'motherid\' can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual.
Error (Individuals, line 56): The fatherid "00000002" refers to an panel (group of individuals), not an individual. If you want to configure that panel as an individual, set its \'Panel size\' field to value 1.
Error (Individuals, line 56): The motherid "00000002" refers to an panel (group of individuals), not an individual. If you want to configure that panel as an individual, set its \'Panel size\' field to value 1.
Error (Individuals, line 59): The fatherid "00000011" you entered does not refer to a male individual.
Error (Individuals, line 59): The motherid "00000010" you entered does not refer to a female individual.
Error (Individuals_To_Diseases, line 66): ID "1|1" already defined at line 65.
Error (Individuals_To_Diseases, line 67): Individual "00000022" does not exist in the database and is not defined in this import file.
Error (Individuals_To_Diseases, line 67): Disease "00022" does not exist in the database and is not defined in this import file.
Error (Phenotypes, line 74): Disease "00022" does not exist in the database and is not defined in this import file.
Error (Phenotypes, line 75): Individual "00000022" does not exist in the database and is not defined in this import file.
Error (Screenings, line 83): Individual "00000022" does not exist in the database and is not defined in this import file.
Error (Screenings_To_Genes, line 91): ID "2|IVD" already defined at line 90.
Error (Screenings_To_Genes, line 92): Gene "DAAM1" does not exist in the database.
Error (Screenings_To_Genes, line 92): Screening "0000000022" does not exist in the database and is not defined in this import file.
Error (Variants_On_Genome, line 99): The field \'position_g_start\' must contain a positive integer, "abc" does not match.
Error (Variants_On_Genome, line 99): Variant start position is larger than variant end position.
Error (Variants_On_Transcripts, line 106): ID "1|1" already defined at line 105.
Error (Variants_On_Transcripts, line 107): The field \'position_c_start\' must contain an integer, "abc" does not match.
Error (Variants_On_Transcripts, line 107): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file.
Error (Variants_On_Transcripts, line 107): Variant start position is larger than variant end position.
Error (Variants_On_Transcripts, line 108): Transcript "00022" does not exist in the database and is not defined in this import file.
Error (Variants_On_Transcripts, line 108): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file.
Error (Variants_On_Transcripts, line 108): Variant start position is larger than variant end position.
Error (Variants_On_Transcripts, line 108): The gene belonging to this variant entry is yet to be inserted into the database. First create the gene and set up the custom columns, then import the variants.
Error (Screenings_To_Variants, line 116): ID "3|1" already defined at line 115.
Error (Screenings_To_Variants, line 117): Screening "0000000022" does not exist in the database and is not defined in this import file.
Error (Screenings_To_Variants, line 117): Genomic Variant "0000000022" does not exist in the database and is not defined in this import file.
Hide 6 warnings
Warning: There is already a Individual column with column ID Age_of_death. This column is not imported!
Warning (Diseases, line 29): There is already a disease with disease name Healthy individual / control. This disease is not imported!
Warning (Diseases, line 30): There is already a disease with disease name isovaleric acidemia and/or OMIM ID 243500. This disease is not imported!
Warning (Diseases, line 32): There is already a disease with disease name isovaleric acidemia and/or OMIM ID 243500. This disease is not imported!
Warning (Phenotypes, line 75): The disease belonging to this phenotype entry is yet to be inserted into the database. Perhaps not all this phenotype entry\'s custom columns will be enabled for this disease!
Warning: the following column has been ignored from the Variants_On_Genome data on line 97, because it is not in the database: VariantOnGenome/Frequency (lost 2 values).',
            $this->driver->findElement(WebDriverBy::xpath('//div[@class="err"]'))->getText());
    }
}
?>
