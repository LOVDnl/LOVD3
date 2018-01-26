<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class FinishIndividualDiagnosedWithCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testFinishIndividualDiagnosedWithCMT()
    {
        $this->driver->get(ROOT_URL . "/src/submit/screening/0000000002");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]/b"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));

        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Individual"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/', $this->driver->getCurrentURL()));
    }
}
