<?php
class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("https://localhost/svn/LOVD3");
  }

  public function testMyTestCase()
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
}
?>