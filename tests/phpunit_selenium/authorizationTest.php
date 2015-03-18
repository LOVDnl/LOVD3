<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-12-19
 * Modified    : 2015-03-18:14:49:34
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

class authorization_tests extends PHPUnit_Extensions_SeleniumTestCase
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
        $this->uncheck("name=lock_uninstall");
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
    public function testCreateUserManager()
    {
        $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Manager");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
        $this->type("name=username", "manager");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Manager");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }
    public function testCreateUserCurator()
    {
        $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Curator");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
        $this->type("name=username", "curator");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Submitter");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }
    public function testCreateUserCollaborator()
    {
        $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Collaborator");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
        $this->type("name=username", "collaborator");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Submitter");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }
    public function testCreateUserSubmitter()
    {
        $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Submitter");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
        $this->type("name=username", "submitter");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Submitter");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }
    public function testCreateUserOwner()
    {
        $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Owner");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
        $this->type("name=username", "owner");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->select("name=level", "Submitter");
        $this->click("name=send_email");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
    }
    public function testCreateGeneIVD()
    {
        $this->open("/svn/LOVD3/trunk/src/genes?create");
        $this->type("name=hgnc_id", "IVD");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("50000");
        $this->addSelection("name=active_transcripts[]", "label=transcript variant 1 (NM_002225.3)");
        $this->click("name=show_hgmd");
        $this->click("name=show_genecards");
        $this->click("name=show_genetests");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the gene information entry!", $this->getText("css=table[class=info]"));
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
    public function testCreateIndividualDiagnosedWithIVA()
    {
        $this->open("/svn/LOVD3/trunk/src/submit");
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/individuals[\s\S]create$/',$this->getLocation()));
        $this->type("name=Individual/Lab_ID", "12345IVA");
        $this->click("link=PubMed");
        $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=Individual/Remarks", "No Remarks");
        $this->type("name=Individual/Remarks_Non_Public", "Still no remarks");
        $this->addSelection("name=active_diseases[]", "label=IVA (isovaleric acidemia)");
        $this->select("name=owned_by", "label=Test Owner");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
    }
    public function testAddScreeningToIVAIndividual()
    {
        $this->open("/svn/LOVD3/trunk/src/submit/individual/00000001");
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/screenings[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
        $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
        $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->check("name=variants_found");
        $this->select("name=owned_by", "label=Test Owner");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
    }
    public function testAddVariantLocatedWithinGeneToIVAIndividual()
    {
        $this->open("/svn/LOVD3/trunk/src/variants?create&reference=Transcript&geneid=IVD&target=0000000001");
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.345G>T");
        $this->click("css=button.mapVariant");
        sleep(3);
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Met115Ile)", $this->getExpression($ProteinChange));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
        $this->assertEquals("g.40702876G>T", $this->getExpression($GenomicDnaChange));
        $this->select("name=00001_effect_reported", "label=Effect unknown");
        $this->select("name=00001_effect_concluded", "label=Effect unknown");
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "0.05");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=Test Owner");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
    }
    public function testAddPhenotypeInfoToIVAIndividual()
    {
        $this->open("/svn/LOVD3/trunk/src/phenotypes?create&target=00000001");
        $this->type("name=Phenotype/Additional", "Phenotype Details");
        $this->select("name=Phenotype/Inheritance", "label=Unknown");
        $this->select("name=owned_by", "label=Test Owner");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
    }
    public function testMakeUserCuratorIVD()
    {
        $this->open("/svn/LOVD3/trunk/src/genes/IVD?authorize");
        $this->click("link=Test Curator");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
    public function testMakeUserCollaboratorIVD()
    {
        $this->open("/svn/LOVD3/trunk/src/genes/IVD?authorize");
        $this->click("link=Test Collaborator");
        $this->click("xpath=(//input[@name='allow_edit[]'])[3]");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
    public function testAuthorization()
    {
        $this->open("/svn/LOVD3/trunk/tests/unit_tests/authorization.php");
        $this->assertEquals("Complete, all successful", $this->getText("css=pre"));
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
