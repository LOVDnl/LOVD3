<?php
class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("https://localhost/");
  }

  public function testMyTestCase()
  {
    $this->open("/svn/LOVD3/trunk/src/import");
    $this->type("name=import", "/www/svn/LOVD3/trunk/tests/test_data_files/TestImport.txt");
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
}
?>