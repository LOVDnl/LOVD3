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
}
?>