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
    $this->type("name=import", "/www/svn/LOVD3/trunk/tests/test_data_files/InsertImport.txt");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Done importing!", $this->getText("id=lovd_sql_progress_message_done"));
  }
}
?>