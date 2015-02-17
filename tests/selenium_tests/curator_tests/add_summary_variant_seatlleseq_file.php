<?php
class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("https://localhost/svn/LOVD3/trunk/src/install/");
  }

  public function testMyTestCase()
  {
    $this->open("/svn/LOVD3/trunk/src/variants/upload?create");
    $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
  }
}
?>