<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class FinishIndividualDiagnosedHealthyTest extends LOVDSeleniumBaseTestCase
{
    public function testFinishIndividualDiagnosedHealthy()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/', $this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->getText("css=table[class=info]")));
    }
}
