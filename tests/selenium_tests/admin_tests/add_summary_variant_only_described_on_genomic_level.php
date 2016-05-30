<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantOnlyDescribedOnGenomicLevelTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantOnlyDescribedOnGenomicLevel()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000168$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::linkText("Submit new data"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->driver->getCurrentURL()));
        $this->chooseOkOnNextConfirmation();
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->driver->get(ROOT_URL . "/src/variants?create&reference=Genome");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="chromosome"]/option[text()="15"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/DNA"), "g.40702976G>T");
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[21520333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "11/10000");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        $element->click();
        $this->assertContains("/src/variants/0000000169", $this->driver->getCurrentURL());
    }
}
