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

class submitter_tests extends PHPUnit_Extensions_SeleniumTestCase
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
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
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
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=email_address", "noreply@LOVD.nl");
        $this->select("name=refseq_build", "label=hg19 / GRCh37");
        $this->click("name=send_stats");
        $this->click("name=include_in_listing");
        $this->click("name=lock_uninstall");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("css=button");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/setup[\s\S]newly_installed$/',$this->getLocation()));
    }
    public function testCreateGeneGJB()
    {
        $this->click("id=tab_genes");
        $this->waitForPageToLoad("30000");
        $this->click("link=Create a new gene entry");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes[\s\S]create$/',$this->getLocation()));
        $this->type("name=hgnc_id", "GJB1");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->addSelection("name=active_transcripts[]", "label=transcript variant 1 (NM_001097642.2)");
        $this->check("name=show_hgmd");
        $this->check("name=show_genecards");
        $this->check("name=show_genetests");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the gene information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/',$this->getLocation()));
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
    public function testLoginAsSubmitter()
    {
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
        $this->type("name=username", "submitter");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testCreateUserSubmitter2()
    {
        $this->open("/LOVD3_development/trunk/src/users?create&no_orcid");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
    public function testCreateDiseaseCMT()
    {
        $this->open("/LOVD3_development/trunk/src/diseases?create");
        $this->assertEquals("To access this area, you need at least Curator clearance.", $this->getText("css=table[class=info]"));
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->click("link=Create a new disease information entry");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases[\s\S]create$/',$this->getLocation()));
        $this->type("name=symbol", "CMT");
        $this->type("name=name", "Charcot Marie Tooth Disease");
        $this->type("name=id_omim", "302800");
        $this->addSelection("name=genes[]", "label=GJB1 (gap junction protein, beta 1, 32kDa)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases\/00001$/',$this->getLocation()));
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
        $this->type("name=username", "submitter");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testCreateIndividualDiagnosedWithCMT()
    {
        $this->click("id=tab_submit");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/',$this->getLocation()));
        $this->type("name=Individual/Lab_ID", "12345CMT");
        $this->click("link=PubMed");
        $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=Individual/Remarks", "No Remarks");
        $this->addSelection("name=active_diseases[]", "label=CMT (Charcot Marie Tooth Disease)");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddPhenotypeInfoToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->type("name=Phenotype/Additional", "Additional phenotype information");
        $this->select("name=Phenotype/Inheritance", "label=Familial");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddScreeningToCMTIndividual()
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
        $this->addSelection("name=genes[]", "label=GJB1 (gap junction protein, beta 1, 32kDa)");
        $this->check("name=variants_found");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantLocatedWithinGeneToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr/td[2]/b");
        $this->click("link=GJB1");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1&target=0000000001$/',$this->getLocation()));
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.34G>T");
        $this->click("css=button.mapVariant");
        sleep(10);
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Gly12Cys)", $this->getExpression($ProteinChange));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[9].value");
        $this->assertEquals("g.70443591G>T", $this->getExpression($GenomicDnaChange));
        $this->select("name=00001_effect_reported", "label=Effect unknown");
        $this->select("name=allele", "label=Maternal (confirmed)");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "0.003");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddVariantOnlyDescribedOnGenomicLevelToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr[2]/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000001$/',$this->getLocation()));
        $this->select("name=allele", "label=Maternal (confirmed)");
        $this->select("name=chromosome", "label=X");
        $this->type("name=VariantOnGenome/DNA", "g.70443591G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testConfirmVariantToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Are you sure you are done with submitting the variants found with this screening[\s\S][\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=Single Base Extension");
        $this->addSelection("name=Screening/Technique[]", "label=Single-Strand DNA Conformation polymorphism Analysis (SSCP)");
        $this->addSelection("name=Screening/Technique[]", "label=SSCA, fluorescent (SSCP)");
        $this->addSelection("name=genes[]", "label=GJB1 (gap junction protein, beta 1, 32kDa)");
        $this->check("name=variants_found");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002[\s\S]confirmVariants$/',$this->getLocation()));
        $this->click("id=check_0000000001");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully confirmed the variant entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testAddSeatlleseqFileToCMTIndividual()
    {
        $this->open("/LOVD3_development/trunk/src/variants/upload?create&target=0000000002");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
    public function testAddVcfFileToCMTIndividual()
    {
        $this->open("/LOVD3_development/trunk/src/variants/upload?create&target=0000000002");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
    public function testFinishIndividualDiagnosedWithCMT()
    {
        $this->open("/LOVD3_development/trunk/src/submit/screening/0000000002");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/',$this->getLocation()));
    }
    public function testAddSummaryVariantLocatedWithinGene()
    {
        $this->open("/LOVD3_development/trunk/src/variants?create&reference=Transcript&geneid=GJB1");
        $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
    }
    public function testAddSummaryVariantOnlyDescribedOnGenomicLevel()
    {
        $this->open("/LOVD3_development/trunk/src/variants?create&reference=Genome");
        $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
    }
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->open("/LOVD3_development/trunk/src/variants/upload?create&type=SeattleSeq");
        $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
    }
    public function testAddSummaryVariantVcfFile()
    {
        $this->open("/LOVD3_development/trunk/src/variants/upload?create&type=VCF");
        $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
    }
    public function testLoginAsAdmin()
    {
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
    }
    public function testCurateSubmittedData()
    {
        $this->click("id=tab_variants");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants$/',$this->getLocation()));
        $this->click("link=0000000001");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000001$/',$this->getLocation()));
        $this->assertEquals("Pending", $this->getText("//tr[13]/td/span"));
        $this->click("id=viewentryOptionsButton_Variants");
        $this->click("link=Publish (curate) variant entry");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Public", $this->getText("//tr[13]/td/span"));
        $this->click("link=00000001");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/',$this->getLocation()));
        $this->assertEquals("Pending", $this->getText("//tr[8]/td"));
        $this->click("id=viewentryOptionsButton_Individuals");
        $this->click("link=Publish (curate) individual entry");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Public", $this->getText("//tr[8]/td"));
        $this->click("id=tab_variants");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants$/',$this->getLocation()));
        $this->click("link=0000000002");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000002$/',$this->getLocation()));
        $this->assertEquals("Pending", $this->getText("//tr[13]/td/span"));
        $this->click("id=viewentryOptionsButton_Variants");
        $this->click("link=Publish (curate) variant entry");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Public", $this->getText("//tr[13]/td/span"));
    }
    public function testPostFinishAddVariantOnlyDescribedOnGenomicLevelToCMTIndividual()
    {
        $this->open("/LOVD3_development/trunk/src/logout");
        $this->open("/LOVD3_development/trunk/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
        $this->type("name=username", "submitter");
        $this->type("name=password", "test1234");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->open("/LOVD3_development/trunk/src/");
        $this->click("id=tab_screenings");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings$/',$this->getLocation()));
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
        $this->select("name=allele", "label=Maternal (confirmed)");
        $this->select("name=chromosome", "label=X");
        $this->type("name=VariantOnGenome/DNA", "g.40702876G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
    }
    public function testPostFinishAddVariantLocatedWithinGeneToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000003$/',$this->getLocation()));
        $this->click("id=tab_screenings");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings$/',$this->getLocation()));
        $this->click("css=#0000000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Screenings");
        $this->click("link=Add variant to screening");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
        $this->click("//table[2]/tbody/tr/td[2]/b");
        $this->click("css=td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1&target=0000000002$/',$this->getLocation()));
        $this->uncheck("name=ignore_00001");
        $this->type("name=00001_VariantOnTranscript/Exon", "2");
        $this->type("name=00001_VariantOnTranscript/DNA", "c.251T>A");
        $this->click("css=button.mapVariant");
        sleep(6);
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Val84Asp)", $this->getExpression($ProteinChange));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[9].value");
        $this->assertEquals("g.70443808T>A", $this->getExpression($GenomicDnaChange));
        $this->select("name=00001_effect_reported", "label=Effect unknown");
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "0.09");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
    }
    public function testPostFinishAddScreeningToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000004$/',$this->getLocation()));
        $this->click("id=tab_individuals");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals$/',$this->getLocation()));
        $this->click("css=td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Individuals");
        $this->click("link=Add screening to individual");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
        $this->addSelection("name=Screening/Template[]", "label=Protein");
        $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
        $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
        $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
        $this->addSelection("name=genes[]", "label=GJB1 (gap junction protein, beta 1, 32kDa)");
        $this->check("name=variants_found");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
    public function testPostFinishAddPhenotypeInfoToCMTIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/',$this->getLocation()));
        $this->click("id=tab_individuals");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals$/',$this->getLocation()));
        $this->click("css=td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/',$this->getLocation()));
        $this->click("id=viewentryOptionsButton_Individuals");
        $this->click("link=Add phenotype information to individual");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/',$this->getLocation()));
        $this->type("name=Phenotype/Additional", "Additional phenotype information");
        $this->select("name=Phenotype/Inheritance", "label=Familial");
        $this->click("css=input[type=\"submit\"]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes\/0000000002$/',$this->getLocation()));
    }
    public function testDeleteGeneGJB()
    {
        $this->open("/LOVD3_development/trunk/src/genes/GJB1?delete");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
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
