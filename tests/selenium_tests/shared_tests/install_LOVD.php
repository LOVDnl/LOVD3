<?php

require_once 'LOVDSeleniumBaseTestCase.php';

class InstallLOVDTest extends LOVDSeleniumBaseTestCase
{
    public function testInstallLOVD()
    {

        // Check if an XDebug session needs to be started, and if so, add the
        // XDebug get parameter.
        // Note: this has to be done once per session. Starting XDebug in
        //       setUp() is not possible as the session may not have
        //       initialized yet. The current method is the common starting
        //       point for most selenium tests.
        global $bXDebugStatus;
        if (XDEBUG_ENABLED && isset($bXDebugStatus) && !$bXDebugStatus) {
            $this->open(ROOT_URL . '/src/install/?XDEBUG_SESSION_START=test');
            $bXDebugStatus = true;
        } else {
            $this->open(ROOT_URL . "/src/install");
        }

        $this->assertContains("install", $this->getBodyText());
        $this->isElementPresent("//input[@value='Start >>']");
        $this->click("//input[@value='Start >>']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1$/', $this->getLocation()));
        $this->type("name=name", "LOVD3 Admin");
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=department", "Human Genetics");
        $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
        $this->type("name=email", "test@lovd.nl");
        $this->type("name=telephone", "+31 (0)71 526 9438");
        $this->type("name=username", "admin");
        $this->type("name=password_1", "test1234");
        $this->type("name=password_2", "test1234");
        $this->select("name=countryid", "label=Netherlands");
        $this->type("name=city", "Leiden");
        $this->click("//input[@value='Continue »']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=1&sent=true$/', $this->getLocation()));
        $this->click("//input[@value='Next >>']");
        $this->waitForPageToLoad("200000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=2$/', $this->getLocation()));
        $this->click("//input[@value='Next >>']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3$/', $this->getLocation()));
        $this->type("name=institute", "Leiden University Medical Center");
        $this->type("name=email_address", "noreply@LOVD.nl");
        $this->select("name=refseq_build", "label=hg19 / GRCh37");
        $this->click("name=send_stats");
        $this->click("name=include_in_listing");
        $this->uncheck("name=lock_uninstall");
        $this->click("//input[@value='Continue »']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=3&sent=true$/', $this->getLocation()));
        $this->click("//input[@value='Next >>']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/install\/[\s\S]step=4$/', $this->getLocation()));
        $this->click("css=button");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/setup[\s\S]newly_installed$/', $this->getLocation()));
    }
}
