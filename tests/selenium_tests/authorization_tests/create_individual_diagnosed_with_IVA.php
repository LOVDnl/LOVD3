<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateIndividualDiagnosedWithIVATest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateIndividualDiagnosedWithIVA()
    {
        $this->driver->get(ROOT_URL . "/src/submit");
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("Individual/Lab_ID"), "12345IVA");
        $this->enterValue(WebDriverBy::name("Individual/Reference"), "{PMID:Fokkema et al (2011):21520333}");
        $this->enterValue(WebDriverBy::name("Individual/Remarks"), "No Remarks");
        $this->enterValue(WebDriverBy::name("Individual/Remarks_Non_Public"), "Still no remarks");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_diseases[]"]/option[text()="IVA (isovaleric acidemia)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[contains(text(), "Test Owner")]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create individual information entry']"));
        $element->click();

        $this->assertEquals("Successfully created the individual information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
