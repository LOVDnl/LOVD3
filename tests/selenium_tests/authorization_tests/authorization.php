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
    $this->open("/svn/LOVD3/trunk/tests/unit_tests/authorization.php");
    $this->assertEquals("Complete, all successful", $this->getText("css=pre"));
  }
}
?>