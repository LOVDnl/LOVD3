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
    $this->type("name=import", "/www/svn/LOVD3/trunk/tests/test_data_files/FalseUpdateImport.txt");
    $this->select("name=mode", "label=Update existing data");
    $this->click("name=simulate");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->click("link=Show 21 warnings");
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Will not edit Column "Individual\/Age_of_death", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Incorrect value for field [\s\S]col_order[\s\S], which needs to be numeric, between 0 and 255\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Incorrect value for field [\s\S]standard[\s\S], which should be 0 or 1\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Select option #1 "[\s\S] = Unknown no = Non-consanguineous parents yes\(\)[\s\S]* = Consanguineous parents" not understood\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): The [\s\S]Regular expression pattern[\s\S] field does not seem to contain valid PHP Perl compatible regexp syntax\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 8\): Access denied for update on field "hgvs": Not allowed to change the HGVS standard status of any column\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Columns, line 9\): ID "Individual\/Age_of_death" already defined at line 8\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Will not edit Gene "IVD", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Access denied for update on field "name": Not allowed to change the gene name\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 14\): Access denied for update on field "chromosome": Not allowed to change the chromosome\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes, line 15\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Will not edit Transcript "00001", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Transcript "00001" does not match the same gene and\/or the same NCBI ID as in the database\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 20\): Access denied for update on field "id_ncbi": Not allowed to change the id_ncbi[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Transcripts, line 21\): ID "00001" already defined at line 20\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Will not edit Disease "00000", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Another disease already exists with this OMIM ID at line 26\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 27\): Another disease already exists with the same name at line 26\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Will not edit Disease "00002", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Another disease already exists with the same name![\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Diseases, line 28\): Import file contains OMIM ID for disease Majeed syndrome, while OMIM ID is missing in database\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Genes_To_Diseases, line 34\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 39\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): Will not edit Individual "00000002", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): The [\s\S]Panel ID[\s\S] can not link to itself; this field is used to indicate to which panel this individual belongs\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 40\): Panel size of Individual "00000002" must be lower than the panel size of Individual "00000002"\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Will not edit Individual "00000003", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Panel ID "00000001" refers to an individual, not a panel \(group of individuals\)\. If you want to configure that individual as a panel, set its [\s\S]Panel size[\s\S] field to a value higher than 1\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): The [\s\S]fatherid[\s\S] can not link to itself; this field is used to indicate which individual in the database is the parent of the given individual\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): The fatherid "00000003" you entered does not refer to a male individual\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 41\): Individual "00000022" does not exist in the database and is not defined \(properly\) in this import file\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 42\): The motherid "00000002" refers to an panel \(group of individuals\), not an individual\. If you want to configure that panel as an individual, set its [\s\S]Panel size[\s\S] field to value 1\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals, line 43\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Individuals_To_Diseases, line 49\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Will not edit Phenotype "0000000001", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Disease "00004" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 54\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Phenotypes, line 55\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 60\): Will not edit Screening "0000000001", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 60\): Individual "00000022" does not exist in the database and is not defined in this import file\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings, line 61\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Genes, line 67\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Will not edit Variants_On_Genome "0000000001", to many fields are changed\. The following fields are changed in the import file: [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Invalid value in the [\s\S]position_g_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 72\): Access denied for update on field "position_g_start": Not allowed to change the position_g_start\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Genome, line 73\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 78\): Invalid value in the [\s\S]position_c_start[\s\S] field: "abc" is not a numerical value\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 78\): Access denied for update on field "position_c_start": Not allowed to change the position_c_start\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 79\): ID "1|1" already defined at line 78\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Variants_On_Transcripts, line 80\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Error \(Screenings_To_Variants, line 86\): This line refers to non-existing entry\. During update no new inserts can be done\.[\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Columns, line 8\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Columns, line 8\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Genes, line 14\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Genes, line 14\): Updated date field is set by LOVD\. [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Transcripts, line 20\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Transcripts, line 20\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 26\): There is already a disease with disease name isovaleric acidemia\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 27\): There is already a disease with disease name isovaleric acidemia\. This disease is not imported! [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 28\): Edited by field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning \(Diseases, line 28\): Edited date field is set by LOVD [\s\S]*$/',$this->getBodyText()));
    $this->assertTrue((bool)preg_match('/^[\s\S]*Warning: It is currently not possible to do an update on section Genes_To_Diseases via an import [\s\S]*$/',$this->getBodyText()));
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
}
?>