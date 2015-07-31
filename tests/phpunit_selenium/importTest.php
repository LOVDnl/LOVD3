<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-12-19
 * Modified    : 2015-07-31:14:40:39
 * For LOVD    : 3.0-12
 *
 * Copyright   : 2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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

class import_tests extends PHPUnit_Extensions_SeleniumTestCase
{
    protected $captureScreenshotOnFailure = TRUE;
    protected $screenshotPath = '/home/dasscheman/svn/LOVD3_development/trunk/tests/test_results/error_screenshots';
    protected $screenshotUrl = 'http://localhost/svn/LOVD3_development/trunk/tests/test_results/error_screenshots';
  
    protected function setUp()
    {
        $this->setHost('localhost');
        $this->setPort(4444);
        $this->setBrowser("firefox");
        $this->setBrowserUrl("http://localhost/svn/LOVD3_development");
        $this->shareSession(true);
    }
    public function testInstallLOVD()
    {
        $this->open("/svn/LOVD3_development/trunk/src/install/");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=1$/',$this->getLocation()));
        $this->type("name=name", "LOVD3 Admin");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "d.asscheman@lumc.nl");
        $this->type("name=telephone", "+31 (0)71 526 9438");
        $this->type("name=username", "admin");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=1&sent=true$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=2$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=3$/',$this->getLocation()));
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=email_address", "noreply@LOVD.nl");
        $this->click("name=send_stats");
        $this->click("name=include_in_listing");
        $this->click("name=lock_uninstall");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=3&sent=true$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=4$/',$this->getLocation()));
        $this->click("css=button");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/setup[\s\S]newly_installed$/',$this->getLocation()));
    }
    public function testCreateGeneIVD()
    {
        $this->open("/svn/LOVD3_development/trunk/src/logout");
        $this->open("/svn/LOVD3_development/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/svn/LOVD3_development/trunk/src/genes?create");
        $this->type("name=hgnc_id", "IVD");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->addSelection("name=active_transcripts[]", "label=transcript variant 1 (NM_002225.3)");
        $this->click("name=show_hgmd");
        $this->click("name=show_genecards");
        $this->click("name=show_genetests");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the gene information entry!", $this->getText("css=table[class=info]"));
    }
    public function testCreateGenderColumn()
    {
        $this->open("/svn/LOVD3_development/trunk/src/columns/Individual/Gender");
        $this->click("id=viewentryOptionsButton_Columns");
        $this->click("link=Enable column");
        $this->waitForPageToLoad("30000");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testCreateDiseaseIVA()
    {
        $this->open("/svn/LOVD3_development/trunk/src/diseases?create");
        $this->type("name=symbol", "IVA");
        $this->type("name=name", "isovaleric acidemia");
        $this->type("name=id_omim", "243500");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
        $this->open("/svn/LOVD3_development/trunk/src/diseases?create");
        $this->type("name=symbol", "IVA2");
        $this->type("name=name", "isovaleric acidemia TWEE");
        $this->type("name=id_omim", "243522");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
    }
    public function testInsertImport()
    {
        $this->open("/svn/LOVD3_development/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/InsertImport.txt");
        $this->select("name=mode", "label=Add only, treat all data as new");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Done importing!", $this->getText("id=lovd_sql_progress_message_done"));
    }
    public function testFalseInsertImport()
    {
        $this->open("/svn/LOVD3_development/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/FalseInsertImport.txt");
        $this->select("name=mode", "label=Add only, treat all data as new");
        $this->click("name=simulate");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("link=Show 5 warnings");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]col_order[\s\S], which needs to be numeric, between 0 and 255\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]standard[\s\S], which should be 0 or 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Select option #1 "[\s\S] = Unknown no = Non-consanguineous parents yes\(\)[\s\S]* = Consanguineous parents" not understood\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): The [\s\S]Regular expression pattern[\s\S] field does not seem to contain valid PHP Perl compatible regexp syntax\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Not allowed to create new HGVS standard columns\. Change the value for [\s\S]hgvs[\s\S] to 0\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 15\): Gene "ARSE" \(arylsulfatase E \(chondrodysplasia punctata 1\)\) does not exist in the database\. Currently, it is not possible to import genes into LOVD using this file format\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 21\): Transcript "00002" does not match the same gene and\/or the same NCBI ID as in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Another disease already exists with the same name![\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 29\): Another disease already exists with this OMIM ID at line 27\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 29\): Another disease already exists with the same name at line 27\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 37\): ID "IVD|2" already defined at line 36\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 38\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 38\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 45\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 46\): The [\s\S]Panel ID[\s\S] can not link to itself; this field is used to indicate to which panel this individual belongs\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 46\): Panel ID "00000004" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 47\): Panel ID "00000001" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 48\): Panel size of Individual "00000006" must be lower than the panel size of Individual "00000002"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 49\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 49\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): Individual "00000008" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): The [\s\S]fatherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): Individual "00000008" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 50\): The [\s\S]motherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 51\): The fatherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 51\): The motherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 54\): The fatherid "00000011" you entered does not refer to a male individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 54\): The motherid "00000010" you entered does not refer to a female individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 60\): ID "1|1" already defined at line 59\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 61\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 61\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 67\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 68\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 75\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 82\): ID "2|IVD" already defined at line 81\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 83\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 83\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 85\): ID "2|IVD" already defined at line 81\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 90\): Invalid value in the [\s\S]position_g_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 96\): ID "1|1" already defined at line 95\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 97\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 97\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): Transcript "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 98\): The gene belonging to this variant entry is yet to be inserted into the database\. First create the gene and set up the custom columns, then import the variants\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 105\): ID "3|1" already defined at line 104\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 106\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 106\): Genomic Variant "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: There is already a Individual column with column ID Age_of_death\. This column is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 26\): There is already a disease with disease name Healthy individual \/ control\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 27\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 29\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Phenotypes, line 68\): The disease belonging to this phenotype entry is yet to be inserted into the database\. Perhaps not all this phenotype entry[\s\S]s custom columns will be enabled for this disease![\s\S]*$/',$this->getBodyText()));
    }
    public function testFalseUpdateImport()
    {
        $this->open("/svn/LOVD3_development/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/FalseUpdateImport.txt");
        $this->select("name=mode", "label=Update existing data (in beta)");
        $this->click("name=simulate");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("link=Show 21 warnings");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Will not update column Individual\/Age_of_death, too many fields are different from the database \(col_order, width, hgvs, standard, head_column, description_form, description_legend_short, description_legend_full, mysql_type, form_type, select_options, preg_pattern\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Can[\s\S]t update hgvs for column entry Individual\/Age_of_death: Not allowed to change the HGVS standard status of any column\. Value is currently "0" and value in the import file is "1"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Can[\s\S]t update created_date for column entry Individual\/Age_of_death: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:07"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Incorrect value for field [\s\S]col_order[\s\S], which needs to be numeric, between 0 and 255\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Incorrect value for field [\s\S]standard[\s\S], which should be 0 or 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Select option #1 "[\s\S] = Unknown no = Non-consanguineous parents yes\(\)[\s\S]* = Consanguineous parents" not understood\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): The [\s\S]Regular expression pattern[\s\S] field does not seem to contain valid PHP Perl compatible regexp syntax\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): ID "Individual\/Age_of_death" already defined at line 8\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Will not update gene IVD, too many fields are different from the database \(name, chromosome\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Can[\s\S]t update chromosome for gene entry IVD: Not allowed to change the chromosome\. Value is currently "15" and value in the import file is "151"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Can[\s\S]t update created_date for gene entry IVD: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:21"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Can[\s\S]t update name for gene entry IVD: Not allowed to change the gene name\. Value is currently "isovaleryl-CoA dehydrogenase" and value in the import file is "isovaleryl-CoA dehydrogenase1"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 15\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Can[\s\S]t update id_ncbi for transcript entry 00001: Not allowed to change the NCBI ID\. Value is currently "NM_002225\.3" and value in the import file is "NM_999999\.3"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Can[\s\S]t update created_date for transcript entry 00001: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:21"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Transcript "00001" does not match the same gene and\/or the same NCBI ID as in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 21\): ID "00001" already defined at line 20\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Will not update disease 00000, too many fields are different from the database \(symbol, name, id_omim\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Can[\s\S]t update created_by for disease entry 00000: Created by field is set by LOVD Value is currently "00000" and value in the import file is "00001"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Can[\s\S]t update created_date for disease entry 00000: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:23"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Another disease already exists with this OMIM ID at line 26\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Another disease already exists with the same name at line 26\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Will not update disease 00002, too many fields are different from the database \(symbol, name, id_omim\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Can[\s\S]t update created_date for disease entry 00002: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-03 10:29:37"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Another disease already exists with the same name![\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 34\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 39\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): Will not update individual 00000002, too many fields are different from the database \(panelid, panel_size\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): The [\s\S]Panel ID[\s\S] can not link to itself; this field is used to indicate to which panel this individual belongs\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): Panel size of Individual "00000002" must be lower than the panel size of Individual "00000002"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Will not update individual 00000003, too many fields are different from the database \(fatherid, motherid, panelid\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Panel ID "00000001" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): The [\s\S]fatherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): The fatherid "00000003" you entered does not refer to a male individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 42\): The motherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 43\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 49\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Will not update phenotype 0000000001, too many fields are different from the database \(diseaseid, individualid\)\. There is a maximum of 1 difference to prevent accidental updates\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Can[\s\S]t update diseaseid for phenotype entry 0000000001: Not allowed to change the disease\. Value is currently "00001" and value in the import file is "00004"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Can[\s\S]t update individualid for phenotype entry 0000000001: Not allowed to change the individual\. Value is currently "00000001" and value in the import file is "00000022"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Can[\s\S]t update created_date for phenotype entry 0000000001: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:32"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Disease "00004" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 55\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 60\): Can[\s\S]t update individualid for screening entry 0000000001: Not allowed to change the individual\. Value is currently "00000001" and value in the import file is "00000022"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 60\): Can[\s\S]t update created_date for screening entry 0000000001: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:37"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 60\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 61\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 67\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Can[\s\S]t update position_g_start for variant entry 0000000001: Not allowed to change the genomic start position\. Value is currently "40702876" and value in the import file is "abc"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Can[\s\S]t update created_date for variant entry 0000000001: Created date field is set by LOVD Value is currently "[\s\S]*" and value in the import file is "2015-06-02 15:42:48"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Invalid value in the [\s\S]position_g_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 73\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 78\): Can[\s\S]t update position_c_start for variant entry 0000000001: Not allowed to change the start position\. Value is currently "345" and value in the import file is "abc"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 78\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 79\): ID "1|1" already defined at line 78\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 80\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 86\): This line refers to a non-existing entry\. When the import mode is set to update, no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Columns, line 8\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Columns, line 8\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Genes, line 14\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Genes, line 14\): Updated date field is set by LOVD\. [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Transcripts, line 20\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Transcripts, line 20\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 26\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 27\): There is already a disease with disease name isovaleric acidemia and\/or OMIM ID 243500\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 28\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 28\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Genes_To_Diseases via an import[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Individuals, line 39\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Individuals, line 39\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Individuals_To_Diseases via an import [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Phenotypes, line 54\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Phenotypes, line 54\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Screenings, line 60\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Screenings, line 60\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Screenings_To_Genes via an import [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Variants_On_Genome, line 72\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Screenings_To_Variants via an import [\s\S]*$/',$this->getBodyText()));
    }
    public function testSecondInsertImport()
    {
        $this->open("/svn/LOVD3_development/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/SecondInsertImport.txt");
        $this->select("name=mode", "label=Add only, treat all data as new");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Done importing!", $this->getText("id=lovd_sql_progress_message_done"));
    }
    public function testUpdateImport()
    {
        $this->open("/svn/LOVD3_development/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/UpdateImport.txt");
        $this->select("name=mode", "label=Update existing data (in beta)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Genes_To_Diseases via an import[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Individuals_To_Diseases via an import[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Screenings_To_Genes via an import[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Screenings_To_Variants via an import[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*The following sections are modified and updated in the database: Columns, Diseases, Individuals, Phenotypes, Screenings, Variants_On_Genome, Variants_On_Transcripts\.$/',$this->getText("id=lovd_sql_progress_message_done")));
    }
    public function testUninstallLOVD()
    {
        $this->open("/svn/LOVD3_development/trunk/src/logout");
        $this->open("/svn/LOVD3_development/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/svn/LOVD3_development/trunk/src/uninstall");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("LOVD successfully uninstalled!\nThank you for having used LOVD!", $this->getText("css=div[id=lovd__progress_message]"));
    }
}
?>
