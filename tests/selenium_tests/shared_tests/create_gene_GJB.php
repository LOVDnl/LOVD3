<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateGeneGJBTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateGeneGJB()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_genes"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Create a new gene entry"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes[\s\S]create$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("hgnc_id"), "GJB1");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
//        $this->addSelection(WebDriverBy::name("active_transcripts[]"), "value=NM_001097642.2");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_transcripts[]"]/option[@value="NM_001097642.2"]'));
        $option->click();
        $this->check(WebDriverBy::name("show_hgmd"));
        $this->check(WebDriverBy::name("show_genecards"));
        $this->check(WebDriverBy::name("show_genetests"));
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create gene information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the gene information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        $this->waitUntil(WebDriverExpectedCondition::titleContains("View GJB1 gene"));
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/', $this->driver->getCurrentURL()));
    }
}
