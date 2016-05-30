<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateDiseaseIVATest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateDiseaseIVA()
    {
        $this->driver->get(ROOT_URL . "/src/diseases?create");
        $this->enterValue(WebDriverBy::name("symbol"), "IVA");
        $this->enterValue(WebDriverBy::name("name"), "isovaleric acidemia");
        $this->enterValue(WebDriverBy::name("id_omim"), "243500");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genes[]"]/option[text()="IVD (isovaleryl-CoA dehydrogenase)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create disease information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the disease information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
