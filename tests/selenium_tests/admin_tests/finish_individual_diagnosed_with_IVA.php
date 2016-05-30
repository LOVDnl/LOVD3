<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class FinishIndividualDiagnosedWithIVATest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testMyTestCase()
    {
        $this->driver->get(ROOT_URL . "/src/submit/screening/0000000002");
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]/b"));
        $element->click();
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::cssSelector("table[class=info]"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }

        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
    }
}
