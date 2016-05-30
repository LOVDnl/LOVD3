<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantSeattleseqFileTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantSeattleseqFile()
    {
        $element = $this->driver->findElement(WebDriverBy::linkText("Submit new data"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->driver->getCurrentURL()));
        $this->chooseOkOnNextConfirmation();
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("variant_file"), ROOT_PATH . "/tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="hg_build"]/option[text()="hg19"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="dbSNP_column"]/option[text()="VariantOnGenome/Reference"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="autocreate"]/option[text()="Create genes and transcripts"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Upload SeattleSeq file']"));
        $element->click();
        for ($second = 0; ; $second++) {
            if ($second >= 300) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::xpath("//input[@value='Continue »']"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }

        $this->assertContains("138 variants were imported, 1 variant could not be imported.", $this->driver->findElement(WebDriverBy::id("lovd__progress_message"))->getText());
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
    }
}
