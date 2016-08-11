<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class DeleteGeneGJBTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testDeleteGeneGJB()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes\/0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("tab_genes"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Genes"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Delete gene entry"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1[\s\S]delete$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Delete gene information entry']"));
        $element->click();
        
        $this->assertEquals("You are about to delete 1 transcript(s) and related information on 2 variant(s) on those transcripts. Please fill in your password one more time to confirm the removal of gene GJB1.", $this->driver->findElement(WebDriverBy::xpath("//*/table[@class='info'][2]"))->getText());
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Delete gene information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully deleted the gene information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("View all genes"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes$/', $this->driver->getCurrentURL()));
    }
}
