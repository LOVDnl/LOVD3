<?php

define('ROOT_PATH', realpath(__DIR__ . '/../../'));
set_include_path(get_include_path() . PATH_SEPARATOR . ROOT_PATH);

require_once 'src/class/objects.php';

define('DUMMY', 'DUMMY');
class MockLOVDObject extends LOVD_Object
{
    var $sTable = 'DUMMY';
}

class LOVD_ObjectTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider providerGetTableAndFieldNameFromSelect
     */
    public function testGetTableAndFieldNameFromSelect($sFRFieldname, $sSelectStmt, $sTablename,
                                                       $sFieldname)
    {
        $oStub = new MockLOVDObject();
        $oStub->aSQLViewList['SELECT'] = $sSelectStmt;

        $testMethod = new ReflectionMethod('MockLOVDObject', 'getTableAndFieldNameFromSelect');
        $testMethod->setAccessible(true);
        list($sOutTablename, $sOutFieldname) = $testMethod->invoke($oStub, $sFRFieldname);
        $this->assertEquals($sTablename, $sOutTablename,
                            'Invalid table name detected in "' . $sSelectStmt . '"');
        $this->assertEquals($sFieldname, $sOutFieldname,
                            'Invalid field name detected in "' . $sSelectStmt . '"');
    }

    public function providerGetTableAndFieldNameFromSelect() {
        return array(
            array('fieldname', 's.*', 's', 'fieldname'),
            array('fieldname', '*', null, 'fieldname'),
            array('fieldname', 'table.fieldname', 'table', 'fieldname'),
            array('alias', 'fieldname AS alias', null, 'fieldname'),
            array('alias', 'table.fieldname AS alias', 'table', 'fieldname'),
            array('alias', 'table.fieldname as alias', 'table', 'fieldname'),
            array('fieldname', 'a.*, b.*, table.fieldname, c.*', 'table', 'fieldname'),
            array('fieldname', '*, table.fieldname', 'table', 'fieldname')
        );
    }
}