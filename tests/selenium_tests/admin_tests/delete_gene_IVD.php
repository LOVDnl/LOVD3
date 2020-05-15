<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class DeleteGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testDeleteGeneIVD()
    {
        $this->driver->get(ROOT_URL . "/src/phenotypes/0000000003");
        $element = $this->driver->findElement(WebDriverBy::id("tab_genes"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Genes"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Delete gene entry"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/IVD[\s\S]delete$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Delete gene information entry']"));
        $element->click();
        $this->assertRegExp('/^You are about to delete \d+ transcript\(s\) and related information on \d+ variant\(s\) on those transcripts. Please fill in your password one more time to confirm the removal of gene IVD\./',
            $this->driver->findElement(WebDriverBy::xpath("//*/table[@class='info'][2]"))->getText());
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Delete gene information entry']"));
        $element->click();
        $this->assertEquals("Successfully deleted the gene information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::titleContains("All genes"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes$/', $this->driver->getCurrentURL()));
    }
}
