<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-06-05
 * Modified    : 2019-09-03
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
use \Facebook\WebDriver\WebDriverExpectedCondition;

class FalseInsertImportTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testFalseInsertImport()
    {
        $this->driver->get(ROOT_URL . "/src/import");
        $this->enterValue(WebDriverBy::name("import"), ROOT_PATH . "../tests/test_data_files/FalseInsertImport.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="mode"]/option[text()="Add only, treat all data as new"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::name("simulate"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Import file']"));
        $element->click();

        $element = $this->driver->findElement(WebDriverBy::linkText("Show 6 warnings"));
        $element->click();

        $bodyText = $this->driver->findElement(WebDriverBy::tagName("body"))->getText();

        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): The field [\s\S]col_order[\s\S] must contain a positive integer, "abc" does not match\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]col_order[\s\S], which needs to be numeric, between 0 and 255\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]standard[\s\S], which should be 0 or 1\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Select option #3 "yes\(\)[\s\S]* = Consanguineous parents" not understood\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): The [\s\S]Regular expression pattern[\s\S] field does not seem to contain valid PHP Perl compatible regexp syntax\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Not allowed to create new HGVS standard columns\. Change the value for [\s\S]hgvs[\s\S] to 0\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 15\): Gene "ARSE" \(arylsulfatase E \(chondrodysplasia punctata 1\)\) does not exist in the database\. Currently, it is not possible to import genes into LOVD using this file format\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 21\): Transcript "00002" does not match the same gene and\/or the same NCBI ID as in the database\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Another disease already exists with the same name![\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 29\): Another disease already exists with this OMIM ID at line 27\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 29\): Another disease already exists with the same name at line 27\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 37\): ID "IVD|2" already defined at line 36\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 38\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 38\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 45\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 46\): The [\s\S]Panel ID[\s\S] can not link to itself; this field is used to indicate to which panel this individual belongs\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 46\): Panel ID "00000004" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 47\): Panel ID "00000001" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 48\): Panel size of Individual "00000006" must be lower than the panel size of Individual "00000002"\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 49\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 49\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): The [\s\S]fatherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): The [\s\S]motherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 51\): The fatherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 51\): The motherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 54\): The fatherid "00000011" you entered does not refer to a male individual\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 54\): The motherid "00000010" you entered does not refer to a female individual\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 60\): ID "1|1" already defined at line 59\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 61\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 61\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 67\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 68\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 75\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 82\): ID "2|IVD" already defined at line 81\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 83\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 83\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 85\): ID "2|IVD" already defined at line 81\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 90\): The field \'position_g_start\' must contain a positive integer, "abc" does not match\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 96\): ID "1|1" already defined at line 95\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 97\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 97\): The field \'position_c_start\' must contain an integer, "abc" does not match\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): Transcript "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): The gene belonging to this variant entry is yet to be inserted into the database\. First create the gene and set up the custom columns, then import the variants\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 105\): ID "3|1" already defined at line 104\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 106\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 106\): Genomic Variant "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/', $bodyText));
        $this->assertTrue((bool)preg_match('/Warning: There is already a Individual column with column ID Age_of_death\. This column is not imported!/', $bodyText));
        $this->assertTrue((bool)preg_match('/Warning \(Diseases, line 26\): There is already a disease with disease name Healthy individual \/ control\. This disease is not imported!/', $bodyText));
        $this->assertTrue((bool)preg_match('/Warning \(Diseases, line 27\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported!/', $bodyText));
        $this->assertTrue((bool)preg_match('/Warning \(Diseases, line 29\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported!/', $bodyText));
        $this->assertTrue((bool)preg_match('/Warning \(Phenotypes, line 68\): The disease belonging to this phenotype entry is yet to be inserted into the database\. Perhaps not all this phenotype entry[\s\S]s custom columns will be enabled for this disease!/', $bodyText));
    }
}
