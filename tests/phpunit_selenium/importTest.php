<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-12-19
 * Modified    : 2015-06-04:15:56:42
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
    protected $screenshotPath = '/home/dasscheman/svn/LOVD3/trunk/tests/test_results/error_screenshots';
    protected $screenshotUrl = 'trunk/tests/test_results/error_screenshots';
  
    protected function setUp()
    {
        $this->setHost('localhost');
        $this->setPort(4444);
        $this->setBrowser("firefox");
        $this->setBrowserUrl("http://localhost/svn/LOVD3/");
        $this->shareSession(true);
    }
    public function testInstallLOVD()
    {
        $this->open("/svn/LOVD3/trunk/src/install/");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/install\/[\s\S]step=1$/',$this->getLocation()));
        $this->type("name=name", "LOVD3 Admin");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
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
        $this->type("name=proxy_host", "localhost");
        $this->type("name=proxy_port", "3128");
        $this->type("name=proxy_username", "test");
        $this->type("name=proxy_password", "test");
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
        $this->open("/svn/LOVD3/trunk/src/logout");
        $this->open("/svn/LOVD3/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/svn/LOVD3/trunk/src/genes?create");
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
        $this->open("/svn/LOVD3/trunk/src/columns/Individual/Gender");
        $this->click("id=viewentryOptionsButton_Columns");
        $this->click("link=Enable column");
        $this->waitForPageToLoad("30000");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testCreateDiseaseIVA()
    {
        $this->open("/svn/LOVD3/trunk/src/diseases?create");
        $this->type("name=symbol", "IVA");
        $this->type("name=name", "isovaleric acidemia");
        $this->type("name=id_omim", "243500");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
    }
    public function testCreateData()
    {
        $this->open("/svn/LOVD3/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3/trunk/tests/test_data_files/StartImportData.txt");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testTestImport()
    {
        $this->open("/svn/LOVD3/trunk/src/import");
        $this->type("name=import", "/home/dasscheman/svn/LOVD3/trunk/tests/test_data_files/TestImport.txt");
        $this->click("name=simulate");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("link=Show 5 warnings");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]col_order[\s\S], which needs to be numeric, between 0 and 255\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Incorrect value for field [\s\S]standard[\s\S], which should be 0 or 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Select option #3 "yes\(\)[\s\S]* = Consanguineous parents" not understood\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): The [\s\S]Regular expression pattern[\s\S] field does not seem to contain valid PHP Perl compatible regexp syntax\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): Not allowed to create new HGVS standard columns\. Change the value for [\s\S]hgvs[\s\S] to 0\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 57\): Gene "ARSE" \(arylsulfatase E \(chondrodysplasia punctata 1\)\) does not exist in the database\. Currently, it is not possible to import genes into LOVD using this file format\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 63\): Transcript "00002" does not match the same gene and\/or the same NCBI ID as in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 70\): Another disease already exists with the same name![\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 70\): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 71\): Another disease already exists with this OMIM ID at line 69\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 71\): Another disease already exists with the same name at line 69\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 79\): ID "IVD|2" already defined at line 78\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 80\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 80\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 88\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 89\): The [\s\S]Panel ID[\s\S] can not link to itself; this field is used to indicate to which panel this individual belongs\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 89\): Panel ID "00000004" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 90\): Panel ID "00000001" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 91\): Panel size of Individual "00000006" must be lower than the panel size of Individual "00000002"\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 92\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 92\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 93\): Individual "00000008" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 93\): The [\s\S]fatherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 93\): Individual "00000008" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 93\): The [\s\S]motherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 94\): The fatherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 94\): The motherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 97\): The fatherid "00000011" you entered does not refer to a male individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 97\): The motherid "00000010" you entered does not refer to a female individual\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 103\): ID "1|1" already defined at line 102\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 104\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 104\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 111\): Disease "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 112\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 119\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 128\): ID "2|IVD" already defined at line 127\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 129\): Gene "DAAM1" does not exist in the database\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 129\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 131\): ID "2|IVD" already defined at line 127\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 136\): Invalid value in the [\s\S]position_g_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 143\): ID "1|1" already defined at line 142\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 144\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 144\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 145\): Transcript "00022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 145\): Genomic Variant "0000000003" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 145\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 145\): The gene belonging to this variant entry is yet to be inserted into the database\. First create the gene and set up the custom columns, then import the variants\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 152\): ID "3|1" already defined at line 151\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 153\): Screening "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 153\): Genomic Variant "0000000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: There is already a Individual column with column ID Age_of_death\. This column is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: There is already a disease with disease name Healthy individual \/ control\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: There is already a disease with disease name isovaleric acidemia\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
        $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Phenotypes, line 112\): The disease belonging to this phenotype entry is yet to be inserted into the database\. Perhaps not all this phenotype entry[\s\S]s custom columns will be enabled for this disease![\s\S]*$/',$this->getBodyText()));
    }
    public function testUninstallLOVD()
    {
        $this->open("/svn/LOVD3/trunk/src/logout");
        $this->open("/svn/LOVD3/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/svn/LOVD3/trunk/src/uninstall");
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
