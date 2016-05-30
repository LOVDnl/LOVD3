<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddScreeningToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddScreeningToIVAIndividual()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_individuals"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/IVD$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::cssSelector("#00000002 > td.ordered"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Individuals"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add screening to individual"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="RNA (cDNA)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="Protein"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for Comparative Genomic Hybridisation"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for resequencing"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for SNP typing"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genes[]"]/option[text()="IVD (isovaleryl-CoA dehydrogenase)"]'));
        $option->click();
        $this->check(WebDriverBy::name("variants_found"));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create screening information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the screening entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000004$/', $this->driver->getCurrentURL()));
    }
}
