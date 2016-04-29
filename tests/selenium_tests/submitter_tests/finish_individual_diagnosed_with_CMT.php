<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class FinishIndividualDiagnosedWithCMTTest extends LOVDSeleniumBaseTestCase
{
    public function testFinishIndividualDiagnosedWithCMT()
    {
        $this->open(ROOT_URL . "/src/submit/screening/0000000002");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000002$/', $this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/', $this->getLocation()));
    }
}
