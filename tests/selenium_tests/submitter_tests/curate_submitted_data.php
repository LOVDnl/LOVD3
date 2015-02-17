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
    $this->click("id=tab_variants");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants$/',$this->getLocation()));
    $this->click("link=0000000001");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/0000000001$/',$this->getLocation()));
    $this->assertEquals("Pending", $this->getText("//tr[13]/td/span"));
    $this->click("id=viewentryOptionsButton_Variants");
    $this->click("link=Publish (curate) variant entry");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Public", $this->getText("//tr[13]/td/span"));
    $this->click("link=00000001");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/individuals\/00000001$/',$this->getLocation()));
    $this->assertEquals("Pending", $this->getText("//tr[8]/td"));
    $this->click("id=viewentryOptionsButton_Individuals");
    $this->click("link=Publish (curate) individual entry");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Public", $this->getText("//tr[8]/td"));
    $this->click("id=tab_variants");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants$/',$this->getLocation()));
    $this->click("link=0000000002");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/0000000002$/',$this->getLocation()));
    $this->assertEquals("Pending", $this->getText("//tr[13]/td/span"));
    $this->click("id=viewentryOptionsButton_Variants");
    $this->click("link=Publish (curate) variant entry");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Public", $this->getText("//tr[13]/td/span"));
  }
}
?>