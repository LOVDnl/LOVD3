<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-12-19
 * Modified    : 2015-10-08:14:28:10
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

class admin_tests extends PHPUnit_Extensions_SeleniumTestCase
{
    protected $captureScreenshotOnFailure = TRUE;
    protected $screenshotPath = '/home/dasscheman/svn/LOVD3_development/trunk/tests/test_results/error_screenshots';
    protected $screenshotUrl = 'http://localhost/LOVD3_development/trunk/tests/test_results/error_screenshots';
  
    protected function setUp()
    {
        $this->setHost('localhost');
        $this->setPort(4444);
        $this->setBrowser("firefox");
        $this->setBrowserUrl("http://localhost/LOVD3_development/trunk");
        $this->shareSession(true);
    }
    public function testInstallLOVD()
    {
        $this->open("/LOVD3_development/trunk/src/install/");
        $this->assertContains("/src/install/", $this->getLocation());
        $this->assertContains("install", $this->getBodyText());
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1$/',$this->getLocation()));
        $this->type("name=name", "LOVD3 Admin");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "test@lovd.nl");
        $this->type("name=telephone", "+31 (0)71 526 9438");
        $this->type("name=username", "admin");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1&sent=true$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=2$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3$/',$this->getLocation()));
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=email_address", "noreply@LOVD.nl");
        $this->select("name=refseq_build", "label=hg19 / GRCh37");
        $this->click("name=send_stats");
        $this->click("name=include_in_listing");
        $this->uncheck("name=lock_uninstall");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3&sent=true$/',$this->getLocation()));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=4$/',$this->getLocation()));
        $this->click("css=button");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/setup[\s\S]newly_installed$/',$this->getLocation()));
    }
    public function testCreateGeneIVD()
    {
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/LOVD3_development/trunk/src/genes?create");
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
    public function testCreateUserManager()
    {
        $this->open("/LOVD3_development/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Manager");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "d.asscheman@lumc.nl");
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
        $this->open("/LOVD3_development/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Curator");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "d.asscheman@lumc.nl");
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
    public function testMakeUserCuratorIVD()
    {
        $this->open("/LOVD3_development/trunk/src/genes/IVD?authorize");
        $this->click("link=Test Curator");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
    public function testCreateUserSubmitter()
    {
        $this->open("/LOVD3_development/trunk/src/users?create&no_orcid");
        $this->type("name=name", "Test Submitter");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "d.asscheman@lumc.nl");
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
    public function testCreateDiseaseIVA()
    {
        $this->open("/LOVD3_development/trunk/src/diseases?create");
        $this->type("name=symbol", "IVA");
        $this->type("name=name", "isovaleric acidemia");
        $this->type("name=id_omim", "243500");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
    }
    public function testCreateIndividualDiagnosedWithHealtyControl()
    {
        $this->open("/LOVD3_development/trunk/src/submit");
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/',$this->getLocation()));
        $this->type("name=Individual/Lab_ID", "12345HealtyCtrl");
        $this->click("link=PubMed");
        $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=Individual/Remarks", "No Remarks");
        $this->type("name=Individual/Remarks_Non_Public", "Still no remarks");
        $this->addSelection("name=active_diseases[]", "label=Healty/Control (Healthy individual / control)");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddPhenotypeInfoToHealtyIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->selectWindow("null");
        $this->type("name=Phenotype/Age", "35y");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddScreeningToHealtyIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
        $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
        $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->check("name=variants_found");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantLocatedWithinGeneToHealtyIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr/td[2]/b");
        $this->click("//tr[@id='IVD']/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000001$/',$this->getLocation()));
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.456T>G");
        $this->click("css=button.mapVariant");
        sleep(3);
        for ($second = 0; ; $second++) {
                if ($second >= 60) $this->fail("timeout");
                try {
                        if ($this->isElementPresent("css=img[alt=\"Prediction OK!\"]")) break;
                } catch (Exception $e) {}
                sleep(1);
        }
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertTrue((bool)preg_match('/^p\.\(Tyr152[\s\S]*\)$/',$this->getExpression($ProteinChange)));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
        $this->assertEquals("g.40702987T>G", $this->getExpression($GenomicDnaChange));
        $this->select("name=00001_effect_reported", "label=Effect unknown");
        $this->select("name=00001_effect_concluded", "label=Effect unknown");
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "0.05");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddSeatlleseqFileToHealtyIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq&target=0000000001$/',$this->getLocation()));
        $this->type("name=variant_file", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=autocreate", "label=Create genes and transcripts");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        // Importing seatlleseq can take some time, therefore the pause for 200 seconds.
        sleep(200);
        for ($second = 0; ; $second++) {
                if ($second >= 60) $this->fail("timeout");
                try {
                        if ($this->isElementPresent("css=input[type=\"button\"]")) break;
                } catch (Exception $e) {}
                sleep(1);
        }

        $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("css=input[type=\"button\"]");
        $this->waitForPageToLoad("30000");
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantOnlyDescribedOnGenomicLevelToHealtyIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000001$/',$this->getLocation()));
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->select("name=chromosome", "label=15");
        $this->type("name=VariantOnGenome/DNA", "g.40702876G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testFinishIndividualDiagnosedHealty()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    }
    public function testCreateIndividualDiagnosedWithIVA()
    {
        $this->open("/LOVD3_development/trunk/src/submit");
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/',$this->getLocation()));
        $this->type("name=Individual/Lab_ID", "12345IVA");
        $this->click("link=PubMed");
        $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=Individual/Remarks", "No Remarks");
        $this->type("name=Individual/Remarks_Non_Public", "Still no remarks");
        $this->addSelection("name=active_diseases[]", "label=IVA (isovaleric acidemia)");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddPhenotypeInfoToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000002$/',$this->getLocation()));
        $this->type("name=Phenotype/Additional", "additional information");
        $this->select("name=Phenotype/Inheritance", "label=Familial");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddScreeningToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
        $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
        $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->check("name=variants_found");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantLocatedWithinGeneToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr/td[2]/b");
        $this->click("css=td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000002$/',$this->getLocation()));
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.345G>T");
        $this->click("css=button.mapVariant");
        sleep(10);
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
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantOnlyDescribedOnGenomicLevelToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000002$/',$this->getLocation()));
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->select("name=chromosome", "label=15");
        $this->type("name=VariantOnGenome/DNA", "g.40702876G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testConfirmVariantToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Are you sure you are done with submitting the variants found with this screening[\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=Single Base Extension");
        $this->addSelection("name=Screening/Technique[]", "label=Single-Strand DNA Conformation polymorphism Analysis (SSCP)");
        $this->addSelection("name=Screening/Technique[]", "label=SSCA, fluorescent (SSCP)");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->check("name=variants_found");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000003[\s\S]confirmVariants$/',$this->getLocation()));
        $this->click("id=check_0000000141");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully confirmed the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVcfFileToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000003$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=VCF&target=0000000003$/',$this->getLocation()));
        $this->type("name=variant_file", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/ShortVCFfilev1.vcf");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=genotype_field", "label=Use Phred-scaled genotype likelihoods (PL)");
        $this->check("name=allow_mapping");
        $this->check("name=allow_create_genes");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("css=input[type=\"button\"]");
        $this->waitForPageToLoad("30000");
        sleep(400);
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->assertEquals("0 99 There are no variants to map in the database", $this->getText("css=body"));
    }
    public function testFinishIndividualDiagnosedWithIVA()
    {
        $this->open("/LOVD3_development/trunk/src/submit/screening/0000000002");
        $this->click("//tr[3]/td[2]/b");
        for ($second = 0; ; $second++) {
                if ($second >= 60) $this->fail("timeout");
                try {
                        if ($this->isElementPresent("css=table[class=info]")) break;
                } catch (Exception $e) {}
                sleep(1);
        }

        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    }
    public function testAddSummaryVariantLocatedWithinGene()
    {
        $this->click("link=Submit new data");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->click("//tr[@id='ARSD']/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=ARSD$/',$this->getLocation()));
        $this->uncheck("name=ignore_00002");
        $this->uncheck("name=ignore_00003");
        $this->type("name=00002_VariantOnTranscript/Exon", "3");
        $this->type("name=00003_VariantOnTranscript/Exon", "3");
        $this->type("name=00002_VariantOnTranscript/DNA", "c.62T>C");
        $this->click("css=button.mapVariant");
        sleep(3);
        for ($second = 0; ; $second++) {
                if ($second >= 60) $this->fail("timeout");
                try {
                        if ($this->isElementPresent("css=img[alt=\"Prediction OK!\"]")) break;
                } catch (Exception $e) {}
                sleep(1);
        }
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Leu21Pro)", $this->getExpression($ProteinChange));
        $this->select("name=00002_effect_reported", "label=Probably affects function");
        $this->select("name=00002_effect_concluded", "label=Probably does not affect function");
        $RnaChangeTwo = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChangeTwo)));
        $ProteinChangeTwo = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Leu21Pro)", $this->getExpression($ProteinChangeTwo));
        $this->select("name=00003_effect_reported", "label=Probably affects function");
        $this->select("name=00003_effect_concluded", "label=Probably does not affect function");
        $this->select("name=allele", "label=Maternal (confirmed)");
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[19].value");
        $this->assertEquals("g.2843789A>G", $this->getExpression($GenomicDnaChange));
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "55/18000");
        $this->select("name=effect_reported", "label=Affects function");
        $this->select("name=effect_concluded", "label=Affects function");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000281$/',$this->getLocation()));
    }
    public function testAddSummaryVariantOnlyDescribedOnGenomicLevel()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000281$/',$this->getLocation()));
        $this->click("link=Submit new data");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->open("/LOVD3_development/trunk/src/variants?create&reference=Genome");
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->select("name=chromosome", "label=15");
        $this->type("name=VariantOnGenome/DNA", "g.40702976G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000282$/',$this->getLocation()));
    }
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->click("link=Submit new data");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq$/',$this->getLocation()));
        $this->type("name=variant_file", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=autocreate", "label=Create genes and transcripts");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        // Importing seatlleseq can take some time, therefore the pause for 200 seconds.
        sleep(200);
        for ($second = 0; ; $second++) {
                if ($second >= 60) $this->fail("timeout");
                try {
                        if ($this->isElementPresent("css=input[type=\"submit\"]")) break;
                } catch (Exception $e) {}
                sleep(1);
        }

        $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    }
    public function testAddSummaryVariantVcfFile()
    {
        $this->click("link=Submit new data");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->open("/LOVD3_development/trunk/src/variants/upload?create&type=VCF");
        $this->type("name=variant_file", "/home/dasscheman/svn/LOVD3_development/trunk/tests/test_data_files/ShortVCFfilev1.vcf");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=genotype_field", "label=Use Phred-scaled genotype likelihoods (PL)");
        $this->check("name=allow_mapping");
        $this->check("name=allow_create_genes");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        sleep(200);
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->open("/LOVD3_development/trunk/src/ajax/map_variants.php");
        $this->assertEquals("0 99 There are no variants to map in the database", $this->getText("css=body"));
    }
    public function testPostFinishAddVariantOnlyDescribedOnGenomicLevelToIVAIndividual()
    {
        $this->open("/LOVD3_development/trunk/src");
        $this->click("id=tab_screenings");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/IVD$/',$this->getLocation()));
        $this->click("css=#0000000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Screenings");
        $this->click("link=Add variant to screening");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000002$/',$this->getLocation()));
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->select("name=chromosome", "label=15");
        $this->type("name=VariantOnGenome/DNA", "g.40702876G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000559$/',$this->getLocation()));
    }
    public function testPostFinishAddVariantLocatedWithinGeneToIVAIndividual()
    {
        $this->click("id=tab_screenings");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/IVD$/',$this->getLocation()));
        $this->click("css=#0000000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Screenings");
        $this->click("link=Add variant to screening");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr[1]/td[2]/b");
        $this->click("css=td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000002$/',$this->getLocation()));
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.345G>T");
        $this->click("css=button.mapVariant");
        sleep(10);
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
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000560$/',$this->getLocation()));
    }
    public function testPostFinishAddScreeningToIVAIndividual()
    {
        $this->click("id=tab_individuals");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/IVD$/',$this->getLocation()));
        $this->click("css=#00000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000002$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Individuals");
        $this->click("link=Add screening to individual");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
        $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
        $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
        $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
        $this->check("name=variants_found");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000004$/',$this->getLocation()));
    }
    public function testPostFinishAddPhenotypeInfoToIVAIndividual()
    {
        $this->click("id=tab_individuals");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/IVD$/',$this->getLocation()));
        $this->click("css=#00000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000002$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Individuals");
        $this->click("link=Add phenotype information to individual");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000002$/',$this->getLocation()));
        $this->type("name=Phenotype/Additional", "Additional phenotype information");
        $this->select("name=Phenotype/Inheritance", "label=Familial");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes\/0000000003$/',$this->getLocation()));
    }
    public function testDeleteGeneIVD()
    {
        $this->open("/LOVD3_development/trunk/src/phenotypes/0000000003");
        $this->click("id=tab_genes");
        $this->waitForPageToLoad("30000");
        $this->click("id=viewentryOptionsButton_Genes");
        $this->click("link=Delete gene entry");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/IVD[\s\S]delete$/',$this->getLocation()));
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully deleted the gene information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes$/',$this->getLocation()));
    }
    public function testUninstallLOVD()
    {
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/LOVD3_development/trunk/src/uninstall");
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
