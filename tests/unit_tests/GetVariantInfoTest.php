<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-18
 * Modified    : 2020-05-06
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/


require_once 'src/inc-lib-init.php';

class GetVariantInfoTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider dataProviderGetVariantInfo
     */
    public function testGetVariantInfo ($sInput, $aOutput)
    {
        // Test lovd_getVariantInfo with data from
        // dataProviderGetVariantInfo().
        $this->assertEquals(lovd_getVariantInfo($sInput), $aOutput);
    }


    public static function dataProviderGetVariantInfo ()
    {
        // Data provider for testGetVariantInfo().
        // Fixme: extend below with more complex variant descriptions.
        return array(
            array('g.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(),
            )),
            array('c.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup',
                'warnings' => array(),
            )),
            array('m.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'type' => 'dup',
                'warnings' => array(),
            )),
            array('n.123dup', array(
                'position_start' => 123,
                'position_end' => 123,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'dup',
                'warnings' => array(),
            )),
            array('g.(?_112043201)_(112181937_?)del', array(
                'position_start' => 112043201,
                'position_end' => 112181937,
                'type' => 'del',
                'warnings' => array(),
            )),
            array('g.100612527_100612529delinsAA', array(
                'position_start' => 100612527,
                'position_end' => 100612529,
                'type' => 'delins',
                'warnings' => array(),
            )),
            array('g.100612529_100612527delinsAA', array(
                'position_start' => 100612527,
                'position_end' => 100612529,
                'type' => 'delins',
                'warnings' => array(
                    'WPOSITIONSSWAPPED' => 'Variant end position is higher than variant start position.',
                ),
            )),
            array('g.100612529_100612527delinsAA', array(
                'position_start' => 100612527,
                'position_end' => 100612529,
                'type' => 'delins',
                'warnings' => array(
                    'WPOSITIONSSWAPPED' => 'Variant end position is higher than variant start position.',
                ),
            )),
            array('c.10000000_10000000del', array(
                'position_start' => 8388607,
                'position_end' => 8388607,
                'position_start_intron' => 0,
                'position_end_intron' => 0,
                'type' => 'del',
                'warnings' => array(
                    'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: position_start, position_end.',
                ),
            )),
        );
    }
}
