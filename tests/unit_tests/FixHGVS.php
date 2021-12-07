<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-07
 * Modified    : 2020-11-17
 * For LOVD    : 3.0-26
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

require_once 'src/inc-lib-init.php'; // For the dependency on lovd_getVariantInfo().
require_once 'src/inc-lib-variants.php'; // For lovd_fixHGVS().

class FixHGVSTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider dataProviderFixHGVS
     */
    public function testFixHGVS ($sInput, $sOutput)
    {
        // Test lovd_fixHGVS() with data from
        // dataProviderFixHGVS().
        $this->assertEquals($sOutput, lovd_fixHGVS($sInput));
    }


    public static function dataProviderFixHGVS ()
    {
        // Data provider for testFixHGVS().
        return array(
            // > VARIANTS WHICH DON'T NEED FIXING.
            array('g.123dup','g.123dup'),
            array('g.1_300del', 'g.1_300del'),
            array('g.1_2insA', 'g.1_2insA'),
            array('g.1_2ins(50)', 'g.1_2ins(50)'),
            array('g.1_2ins5_10', 'g.1_2ins5_10'),
            array('g.1_2ins[NC_123456.1:g.1_10]', 'g.1_2ins[NC_123456.1:g.1_10]'),
            array('g.1_5delinsACT', 'g.1_5delinsACT'),
            array('g.1_2ACT[20]', 'g.1_2ACT[20]'),
            array('g.123=', 'g.123='),
            array('c.?', 'c.?'),
            array('c.123?', 'c.123?'),
            array('g.((1_5)ins(50))', 'g.((1_5)ins(50))'),
            array('c.(1_100)del(20)', 'c.(1_100)del(20)'),


            // > FIXABLE VARIANTS.

            // Missing prefixes.
            array('123dup', 'g.123dup'),
            array('.123dup', 'g.123dup'),
            array('123-5dup', 'c.123-5dup'),

            // Wrong prefixes.
            array('g.123-5dup', 'c.123-5dup'),
            array('m.123-5dup', 'c.123-5dup'), // Fixme; take another look.
            array('g.*1_*2del', 'c.*1_*2del'),

            // Added whitespace.
            array('g. 123_124insA', 'g.123_124insA'),
            array(' g.123del', 'g.123del'),

            // Lowercase nucleotides.
            array('g.123insactg', 'g.123insACTG'),
            array('g.123a>g', 'g.123A>G'),

            // U given instead of T.
            array('g.123insAUG', 'g.123insATG'),

            // Conversions and substitutions which should be delins variants.
            array('g.123conACTG', 'g.123delinsACTG'),
            array('g.123A>C', 'g.123A>C'),
            array('g.123A>GC', 'g.123delinsGC'),

            // Added bases for wildtype.
            array('c.123T=', 'c.123='),
            array('c.123_124TG=', 'c.123_124='),

            // Floating parentheses.
            array('g.((123_234)_(345_456)del', 'g.(123_234)_(345_456)del'),
            array('g.(123_234)_(345_456))del', 'g.(123_234)_(345_456)del'),

            // Misplaced parentheses.
            array('(c.(123_125)insA)', 'c.((123_125)insA)'),

            // Redundant parentheses.
            array('c.1_2ins(A)', 'c.1_2insA'),

            // Wrongly placed suffixes.
            array('c.123delA', 'c.123del'),

            // Wrongly formatted suffixes.
            array('c.1_2ins[A]', 'c.1_2insA'),

            // Redundant question marks.
            array('g.?del', 'g.?del'),
            array('g.1_?del', 'g.1_?del'),
            array('g.?_100del', 'g.?_100del'),
            array('g.?_?del', 'g.?del'),
            array('g.(?_?)del', 'g.?del'),

            array('g.(?_5)_10del', 'g.(?_5)_10del'),
            array('g.(5_?)_10del', 'g.(5_?)_10del'),
            array('g.(5_?)_?del', 'g.(5_?)del'),
            array('g.(?_?)_10del', 'g.?_10del'),

            array('g.5_(10_?)del', 'g.5_(10_?)del'),
            array('g.5_(?_10)del', 'g.5_(?_10)del'),
            array('g.?_(?_10)del', 'g.(?_10)del'),
            array('g.5_(?_?)del', 'g.5_?del'),

            array('g.(?_5)_(10_?)del', 'g.(?_5)_(10_?)del'),
            array('g.(5_?)_(?_10)del', 'g.(5_?)_(?_10)del'),
            array('g.(5_?)_(?_10)del(3)', 'g.(5_10)del(3)'),

            array('g.(?_?)_(?_?)del', 'g.?del'),

            // Swaps positions when needed.
            array('g.2_1dup', 'g.1_2dup'),
            array('g.(5_1)_10dup', 'g.(1_5)_10dup'),
            array('g.1_(7_5)dup', 'g.1_(5_7)dup'),
            array('g.(7_5)_1dup', 'g.1_(5_7)dup'),
            array('c.5+1_5-1dup', 'c.5-1_5+1dup'),


            // > UNFIXABLE VARIANTS.
            array('g.1delinsA', 'g.1delinsA'), // Fixme; take another look.
            array('c.1_2AC[20]', 'c.1_2AC[20]'),
            array('c.1_2A>G', 'c.1_2A>G'),
            array('g.=', 'g.='),
            array('c.1insA', 'c.1insA'),
            array('c.1_2ins', 'c.1_2ins'),
            array('c.1_10insA', 'c.1_10insA'),
            array('c.(1_2)insA', 'c.(1_2)insA'),
            array('c.1_20insBLA', 'c.1_20insBLA'),
            array('c.1_100insA', 'c.1_100insA'),
            array('c.1_100del(10)', 'c.1_100del'), // Fixme; take another look!!
        );
    }
}
?>
