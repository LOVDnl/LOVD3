<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-07
 * Modified    : 2024-11-01
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Loes Werkman <L.Werkman@LUMC.nl>
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

// This test refuses to execute unless configured in the phpunit.xml and by using --configuration.
// Also renaming it to *Test.php doesn't help, and direct calls to this files return immediately without output.
// Can not find a reason for this.

require_once 'src/inc-lib-init.php'; // For the dependency on lovd_getVariantInfo().
require_once 'src/inc-lib-variants.php'; // For lovd_fixHGVS().

class FixHGVSTest extends PHPUnit\Framework\TestCase
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
            // VARIANTS THAT DON'T NEED FIXING.
            // Note, some variants that don't need fixing are actually listed
            //  below in the section "Fixable variants", near descriptions they
            //  are related to.
            array('g.123dup','g.123dup'),
            array('g.123A>C', 'g.123A>C'),
            array('g.123del', 'g.123del'),
            array('g.1_300del', 'g.1_300del'),
            array('g.1_2insA', 'g.1_2insA'),
            array('g.1_2ins5_10', 'g.1_2ins5_10'),
            array('g.1_2ins[NC_123456.1:g.1_10]', 'g.1_2ins[NC_123456.1:g.1_10]'),
            array('g.1_5delinsACT', 'g.1_5delinsACT'),
            array('g.1ACT[20]', 'g.1ACT[20]'),
            array('g.123=', 'g.123='),
            array('c.0', 'c.0'),
            array('c.0?', 'c.0?'),
            array('c.?', 'c.?'),
            array('c.123?', 'c.123?'),



            // FIXABLE VARIANTS.
            // Missing or broken prefixes that will be fixed.
            array('123dup', 'c.123dup'),
            array('123456dup', 'g.123456dup'),
            array('(123dup)', 'c.(123dup)'),
            array('.123dup', 'c.123dup'),
            array('c123dup', 'c.123dup'),
            array('c,123dup', 'c.123dup'),
            array('c..123dup', 'c.123dup'),
            array('123-5dup', 'c.123-5dup'),
            array('NC_123456.1(NM_123456.1):1del', 'NC_123456.1(NM_123456.1):c.1del'),

            // Wrong prefix, the size of the positions indicates it's a range,
            //  and the range is fixed to a single position.
            array('g.140712592-140712592C>T', 'g.140712592C>T'),

            // Whitespace, other typos, and copy/paste errors.
            array('g. 123_124insA', 'g.123_124insA'),
            array(' g.123del', 'g.123del'),
            array(':g.123del', 'g.123del'),
            array('c.–123del', 'c.-123del'),
            array('c.123—5del', 'c.123-5del'),
            array('c,123del', 'c.123del'),
            array('c.A123C', 'c.123A>C'),
            array('c.a123u', 'c.123A>T'),
            array('c.a123uu', 'c.123delinsTT'),
            array('c.ua123uu', 'c.124A>T'),
            array('c.216G A', 'c.216G>A'), // " " seen in AIPL1_20702822_Jacobson-2011.pdf ("c.216G A")
            array('c.1106G®A', 'c.1106G>A'), // "®" seen in CACNA1F_9662399_Strom-1998.pdf ("1106G®A")
            array('c.220T?C', 'c.220T>C'), // "?" seen in CACNA1F_12111638_Wutz-2002.pdf ("220T?C")
            array('c.1576C!T', 'c.1576C>T'), // "!" seen in CRB1_32351147_Liu-2020.pdf ("C!T")
            array('c.2189+1G.T', 'c.2189+1G>T'), // "." seen in MERTK_19403518_Charbel%20Issa-2009.pdf ("c.2189+1G.T")
            array('c.1647T4G', 'c.1647T>G'), // "4" seen in MERTK_30851773_Bhatia-2019.pdf ("c.1647T4G")
            array('c.1040T→C', 'c.1040T>C'), // "→" seen in NYX_11062472_Pusch-2000.pdf ("1040T→C")

            // Lowercase nucleotides and other case issues.
            array('C.123C>a', 'c.123C>A'),
            array('C.123a>u', 'c.123A>T'),
            array('G.123dup', 'g.123dup'),
            array('g.123DUP', 'g.123dup'),
            array('g.123insactg', 'g.123insACTG'),
            array('g.123delinsgagagauu', 'g.123delinsGAGAGATT'),
            array('g.123_130delgagagatt', 'g.123_130del'),
            array('g.123_130delgagagauu', 'g.123_130del'),
            array('g.123_130deln[8]', 'g.123_130del'),
            array('g.123a>g', 'g.123A>G'),
            array('g.100_101ins[nc_000010.1:g.100_200;aaaa;n[10]]', 'g.100_101ins[NC_000010.1:g.100_200;AAAA;N[10]]'),
            array('lrg_123t1:c.100del', 'LRG_123t1:c.100del'),

            // U given instead of T.
            array('g.123insAUG', 'g.123insATG'),
            array('c.123A>U', 'c.123A>T'),

            // Variant types should be something else.
            array('g.100_200con400_500', 'g.100_200delins400_500'),
            array('g.123conNC_000001.10:100_200', 'g.123delins[NC_000001.10:g.100_200]'),
            array('g.123A>A', 'g.123='),
            array('g.123A>GC', 'g.123delinsGC'),
            array('g.123A>AA', 'g.123dup'),
            array('g.123AA>G', 'g.123_124delinsG'),
            array('g.123AA>AC', 'g.124A>C'),
            array('g.123AA>GA', 'g.123A>G'),
            array('g.123AA>TT', 'g.123_124inv'),
            array('g.123AA>GC', 'g.123_124delinsGC'),
            array('g.123AA>AAAA', 'g.123_124dup'),
            array('g.123AA>AGCA', 'g.123_124insGC'),
            array('c.123+1AA>GC', 'c.123+1_123+2delinsGC'),
            array('c.123-1AA>GC', 'c.123-1_123delinsGC'),
            array('g.123_124AA>AA', 'g.123_124='),
            array('g.123_124AA>AC', 'g.124A>C'),
            array('g.123_124AA>GA', 'g.123A>G'),
            array('g.123_124AA>GC', 'g.123_124delinsGC'),
            array('g.123_124AAA>GC', 'g.123_124AAA>GC'), // Unfixable.
            array('g.123A>.', 'g.123del'),
            array('g.123AA>.', 'g.123_124del'),
            array('g.123delAinsG', 'g.123A>G'),
            array('g.123delAAinsGA', 'g.123A>G'),
            array('g.123delainst', 'g.123A>T'),
            array('g.123delainsu', 'g.123A>T'),

            // Wild type requires no bases.
            array('c.123T=', 'c.123='),
            array('c.123t=', 'c.123='),
            array('c.123_124TG=', 'c.123_124='),
            array('c.(123_124TG=)', 'c.(123_124=)'),

            // Methylation-related changes.
            array('g.123|met=', 'g.123|met='),
            array('g.123lom', 'g.123|lom'),
            array('g.123||bsrC', 'g.123|bsrC'),

            // Double parentheses.
            array('g.((123_234))del(50)', 'g.(123_234)delN[50]'),
            array('g.((123_234)_(345_456)del', 'g.(123_234)_(345_456)del'),
            array('g.(123_234)_(345_456))del', 'g.(123_234)_(345_456)del'),

            // Misplaced parentheses.
            array('(c.(123_125)insA)', 'c.((123_125)insA)'),

            // Redundant parentheses.
            array('c.1_2ins(A)', 'c.1_2insA'),
            array('c.(1_2)insA', 'c.1_2insA'),
            array('c.(123+10_123+11)insA', 'c.123+10_123+11insA'),
            array('c.(1_2)inv', 'c.1_2inv'),
            array('c.(100)A>G', 'c.100A>G'),

            // Superfluous suffixes.
            array('c.123delA', 'c.123del'),
            array('c.123dela', 'c.123del'),
            array('c.123del[A]', 'c.123del'),
            array('c.123del(A)', 'c.123del'),
            array('c.123delAA', 'c.123delAA'), // Unfixable.
            array('g.123del1', 'g.123del'),
            array('g.123del2', 'g.123del2'), // Unfixable.
            array('c.123_124delA', 'c.123_124delA'), // Unfixable.
            array('c.123_124delAA', 'c.123_124del'),
            array('g.123_124del1', 'g.123_124del1'), // Unfixable.
            array('g.123_124del2', 'g.123_124del'),
            array('g.123_124del(2)', 'g.123_124del'),
            array('g.123_124delN[2]', 'g.123_124del'),
            array('g.123delAinsGG', 'g.123delinsGG'),
            array('g.123delAAinsGG', 'g.123_124delinsGG'),

            // Wrongly formatted suffixes.
            array('c.1_2ins[A]', 'c.1_2insA'),
            array('c.1_2ins[N]', 'c.1_2insN'),
            array('c.1_2ins(20)', 'c.1_2insN[20]'),
            array('c.1_2ins[(20)]', 'c.1_2insN[20]'),
            array('c.1_2ins(20_50)', 'c.1_2insN[(20_50)]'),
            array('c.1_2ins(50_20)', 'c.1_2insN[(20_50)]'),
            array('g.1_2insA[5_10]', 'g.1_2insA[(5_10)]'),
            array('g.1_2insN[5_10]', 'g.1_2insN[(5_10)]'),
            array('g.1_2insA[(10_5)]', 'g.1_2insA[(5_10)]'),
            array('g.1_2insN[(10_5)]', 'g.1_2insN[(5_10)]'),
            array('g.1_2insA[(10_10)]', 'g.1_2insA[10]'),
            array('g.1_2insN[(10_10)]', 'g.1_2insN[10]'),
            array('g.1_2insNC123456.1:g.1_10', 'g.1_2ins[NC_123456.1:g.1_10]'),
            array('c.1_2ins[NC_000001.10:100_(300_200);400_500]',
                  'c.1_2ins[NC_000001.10:g.100_(200_300);400_500]'),
            array('c.1_2ins[NC_000001.10:100_(300_200);(400_500)]',
                  'c.1_2ins[NC_000001.10:g.100_(200_300);N[(400_500)]]'),
            array('c.1_2ins[NC_000001.10(100_200)_300]',
                  'c.1_2ins[NC_000001.10:g.(100_200)_300]'),
            array('g.((1_5)ins(50))', 'g.((1_5)insN[50])'),
            array('g.1_2ins[ACT;(20)]', 'g.1_2ins[ACT;N[20]]'),
            array('g.(100_200)delN[(50)]', 'g.(100_200)delN[50]'),
            array('g.(100_200)del50', 'g.(100_200)delN[50]'),
            array('g.(100_200)del(50_50)', 'g.(100_200)delN[50]'),
            array('g.(100_200)del(60_50)', 'g.(100_200)delN[(50_60)]'),
            array('g.1_5delins20_10', 'g.1_5delins10_20'),
            array('g.1AC[20]GT', 'g.1AC[20]GT[1]'),

            // Question marks.
            // Note, that some of these variants do *not* need fixing and
            //  have *no* redundant question marks.
            array('g.?del', 'g.?del'),
            array('g.1_?del', 'g.1_?del'),
            array('g.?_100del', 'g.?_100del'),
            array('g.?_?del', 'g.?del'),
            array('g.(?_?)del', 'g.?del'),

            array('g.(?_5)_10del', 'g.(?_5)_10del'),
            array('g.(5_?)_10del', 'g.(5_?)_10del'),
            array('g.(?_5)_?del', 'g.(?_5)_?del'),
            array('g.(5_?)_?del', 'g.(5_?)del'),

            array('g.(?_?)_10del', 'g.?_10del'),
            array('g.(?_?)_(10_?)del', 'g.?_(10_?)del'),
            array('g.(?_?)_(?_10)del', 'g.(?_10)del'),

            array('g.5_(10_?)del', 'g.5_(10_?)del'),
            array('g.5_(?_10)del', 'g.5_(?_10)del'),
            array('g.?_(10_?)del', 'g.?_(10_?)del'),
            array('g.?_(?_10)del', 'g.(?_10)del'),

            array('g.5_(?_?)del', 'g.5_?del'),
            array('g.(5_?)_(?_?)del', 'g.(5_?)del'),
            array('g.(?_5)_(?_?)del', 'g.(?_5)_?del'),

            array('g.(?_5)_(10_?)del', 'g.(?_5)_(10_?)del'),
            array('g.(5_?)_(?_10)del', 'g.(5_10)del'),
            array('g.(5_?)_(?_10)del(3)', 'g.(5_10)delN[3]'),

            array('g.(?_?)_(?_?)del', 'g.?del'),
            array('g.?_?insAAA', 'g.?_?insAAA'), // Negative control.
            array('g.?_(?_?)insAAA', 'g.?_?insAAA'),

            // Combining sorting and solving redundant question marks.
            array('g.(10_?)_(?_5)del', 'g.(5_10)del'),
            array('c.(10+1_?)_(?_5-1)del', 'c.(5-1_10+1)del'),

            // Swaps positions when needed.
            array('g.2_1dup', 'g.1_2dup'),
            array('g.(5_1)_10dup', 'g.(1_5)_10dup'),
            array('g.1_(7_5)dup', 'g.1_(5_7)dup'),
            array('g.(7_5)_1dup', 'g.1_(5_7)dup'),
            array('g.(200_100)_(50_?)del', 'g.(?_50)_(100_200)del'),
            array('g.(?_300)_(200_100)del', 'g.(100_200)_(300_?)del'),
            array('c.5+1_5-1dup', 'c.5-1_5+1dup'),
            array('c.*2_1del', 'c.1_*2del'),
            array('c.(*50_500)_(100_1)del', 'c.(1_100)_(500_*50)del'),
            array('c.(500_*50)_(1_100)del', 'c.(1_100)_(500_*50)del'),

            // Other position-related things.
            array('c.-010+01del', 'c.-10+1del'),

            // Variants with reference sequences, testing various fixes.
            array('NC_123456.10:(123delA)', 'NC_123456.10:g.(123del)'),
            array('NC_123456.10:g.123_234conaaa', 'NC_123456.10:g.123_234delinsAAA'),

            // Issues with reference sequences.
            array('NC_12345.1:g.1del', 'NC_012345.1:g.1del'),
            array('NM_123456.1(NC_123456.1):c.100del', 'NC_123456.1(NM_123456.1):c.100del'),
            array('NM_123456.1(GENE):c.100del', 'NM_123456.1:c.100del'),
            array('NM_123456.1(GENE_v001):c.100del', 'NM_123456.1:c.100del'),
            array('GENE(NM_123456.1):c.100del', 'NM_123456.1:c.100del'),
            array('NM123456.1:c.100del', 'NM_123456.1:c.100del'),
            array('NM-123456.1:c.100del', 'NM_123456.1:c.100del'),
            array('NM_00123456.1:c.100del', 'NM_123456.1:c.100del'),
            array('NM_00123456789.1:c.100del', 'NM_123456789.1:c.100del'),
            array('LRG123t1:c.100del', 'LRG_123t1:c.100del'),
            array('LRG123t1:c.100del', 'LRG_123t1:c.100del'),
            array('ENSG_12345678911.1:g.1del', 'ENSG12345678911.1:g.1del'),
            array('ENSG1234567890.1:g.1del', 'ENSG01234567890.1:g.1del'),

            // Where we can still improve
            //  (still results in an invalid description - more work needed,
            //   or variants currently not supported and returned as-is).
            array('g.(100_200)[ins50]', 'g.(100_200)[ins50]'),
            // Real problem is a typo in the last position; could we recognize this?
            array('g.(150138199_150142492)_(150145873_15147218)del',
                  'g.(15147218_150142492)_(150138199_150145873)del'),
            array('g.123^124A>C', 'g.123^124A>C'),
            array('g.123A>C^124G>C', 'g.123A>C^124G>C'),
            array('g.123A>C;124A>C', 'g.123A>C;124A>C'),
            array('g.[123A>C;124A>C]', 'g.[123A>C;124A>C]'),
            array('g.[123A>C(;)124A>C]', 'g.[123A>C(;)124A>C]'),
            array('g.[123A>C];[124A>C]', 'g.[123A>C];[124A>C]'),

            // Multiple issues fixed in once.
            array('C123A', 'c.123C>A'),
            array('1:1234567:A:C', 'g.1234567A>C'),
            array('1:1234567:AA:CC', 'g.1234567_1234568delinsCC'),
            array('X-1234567-AA-ATA', 'g.1234567_1234568insT'),



            // UNFIXABLE VARIANTS.
            array('', ''),
            array('g.0', 'g.0'),
            array('g.1delinsA', 'g.1delinsA'),
            array('c.1AC[20]', 'c.1AC[20]'),
            array('c.1_2A>G', 'c.1_2A>G'),
            array('g.123A>C<unknown>', 'g.123A>C<unknown>'),
            array('g.1del<unknown>', 'g.1del<unknown>'),
            array('g.=', 'g.='),
            array('c.1insA', 'c.1insA'),
            array('g.1_2ins10', 'g.1_2ins10'),
            array('c.0_1del', 'c.0_1del'),
            array('g.0_1del', 'g.0_1del'),
            array('c.1_2ins', 'c.1_2ins'),
            array('c.10+0del', 'c.10+0del'),
            array('c.1_10insA', 'c.1_10insA'),
            array('c.1_20insBLA', 'c.1_20insBLA'),
            array('c.1_100insA', 'c.1_100insA'),
            array('c.1_100del(10)', 'c.1_100del(10)'),
            array('g.1_100inv(30)', 'g.1_100inv(30)'),
            array('g.123-5dup', 'g.123-5dup'),
            array('m.123-5dup', 'm.123-5dup'),
            array('g.*1_*2del', 'g.*1_*2del'),
            array('g.123.>.', 'g.123.>.'),
            array('g.123.>C', 'g.123.>C'),
            array('c.(-100_-74ins)ins(69_111)', 'c.(-100_-74ins)ins(69_111)'), // Used to cause an infinite recursion.
            array('g.(200_100)?', 'g.(100_200)?'),
            array('g.(?_100?_200_?)dup', 'g.(?_100?_200_?)dup'),
        );
    }
}
?>
