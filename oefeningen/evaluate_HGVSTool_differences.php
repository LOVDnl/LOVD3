<?php
// Fill in both arrays, then run this script.


function lovd_getGetInfoTestDataSets ()
{
    return array(
        // Prefixes.
        array('g.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('c.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('m.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('n.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.-123dup', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(
                'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "-123" which describes a position in the 5\' UTR, is invalid when using the "g" prefix.',
            ),
        )),
        array('g.*123dup', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(
                'EFALSEUTR' => 'Only coding transcripts (c. prefix) have a UTR region. Therefore, position "*123" which describes a position in the 3\' UTR, is invalid when using the "g" prefix.',
            ),
        )),
        array('m.123+4_124-20dup', array(
            'position_start' => 123,
            'position_end' => 124,
            'position_start_intron' => 4,
            'position_end_intron' => -20,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(
                'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Therefore, this variant description with a position in an intron is invalid when using the "m" prefix.',
            ),
        )),
        array('g.123000-125000dup', array(
            'position_start' => 123000,
            'position_end' => 123000,
            'position_start_intron' => -125000,
            'position_end_intron' => -125000,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(
                'EFALSEINTRONIC' => 'Only transcripts (c. or n. prefixes) have introns. Therefore, this variant description with a position in an intron is invalid when using the "g" prefix. Did you perhaps try to indicate a range? If so, please use an underscore (_) to indicate a range.',
            ),
        )),

        // Substitutions.
        array('g.123A>C', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.123.>.', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(),
            'errors' => array(
                'EWRONGTYPE' => 'This substitution does not seem to contain any data. Please provide bases that were replaced.',
            ),
        )),
        array('g.123_124A>C', array(
            'position_start' => 123,
            'position_end' => 124,
            'type' => 'subst',
            'warnings' => array(),
            'errors' => array(
                'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
            ),
        )),
        array('g.123A>GC', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(
                'WWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
            ),
            'errors' => array(),
        )),
        array('g.123.>C', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(),
            'errors' => array(
                'EWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe an insertion?',
            ),
        )),
        array('g.123AA>G', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(
                'WWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
            ),
            'errors' => array(),
        )),
        array('g.123A>.', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(
                'WWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe a deletion?',
            ),
            'errors' => array(),
        )),
        array('g.123_124AA>GC', array(
            'position_start' => 123,
            'position_end' => 124,
            'type' => 'subst',
            'warnings' => array(
                'WWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
            ),
            'errors' => array(
                'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
            ),
        )),
        array('g.123_124AAA>GC', array(
            'position_start' => 123,
            'position_end' => 124,
            'type' => 'subst',
            'warnings' => array(
                'WWRONGTYPE' =>
                    'A substitution should be a change of one base to one base. Did you mean to describe a deletion-insertion?',
            ),
            'errors' => array(
                'ETOOMANYPOSITIONS' => 'Too many positions are given; a substitution is used to only indicate single-base changes and therefore should have only one position.'
            ),
        )),
        array('g.123A>Ciets', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'subst',
            'warnings' => array(
                'WSUFFIXGIVEN' => 'Nothing should follow "A>C".',
            ),
            'errors' => array(),
        )),

        // Duplications.
        array('g.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.123_170dup', array(
            'position_start' => 123,
            'position_end' => 170,
            'type' => 'dup',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.123_125dupACG', array(
            'position_start' => 123,
            'position_end' => 125,
            'type' => 'dup',
            'warnings' => array(
                'WSUFFIXGIVEN' => 'Nothing should follow "dup".'
            ),
            'errors' => array(),
        )),

        // Deletions.
        array('g.1_300del', array(
            'position_start' => 1,
            'position_end' => 300,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1delA', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXGIVEN' => 'Nothing should follow "del".',
            ),
            'errors' => array(),
        )),

        // Insertions.
        array('g.1_2insA', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2insN', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2insN[10]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2ins(50)', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins5_10', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2ins[NC_123456.1:g.1_10]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2insN[5_10]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines. Please rewrite "N[5_10]" to "N[(5_10)]".',
            ),
            'errors' => array(),
        )),
        array('g.1_2insN[(5_10)]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2insN[(10_5)]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines. Please rewrite "N[(10_5)]" to "N[(5_10)]".',
            ),
            'errors' => array(),
        )),
        array('g.1_2insN[(10_10)]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines. Please rewrite "N[(10_10)]" to "N[10]".',
            ),
            'errors' => array(),
        )),
        array('g.1insA', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONMISSING' =>
                    'An insertion must be provided with the two positions between which the insertion has taken place.',
            ),
        )),
        array('g.1_1insA', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' => 'This variant description contains two positions that are the same. Please verify your description and try again.',
            ),
        )),
        array('g.1_2ins', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'ESUFFIXMISSING' => 'The inserted sequence must be provided for insertions or deletion-insertions.',
            ),
        )),
        array('g.(1_2)insA', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
            ),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('c.123+10_123+11insA', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 10,
            'position_end_intron' => 11,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('c.(123+10_123+11)insA', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 10,
            'position_end_intron' => 11,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
            ),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_10)insA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('c.123+10_123+20insA', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 10,
            'position_end_intron' => 20,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'An insertion must have taken place between two neighboring positions. If the exact ' .
                    'location is unknown, please indicate this by placing parentheses around the positions.',
            ),
        )),
        array('c.(123+10_123+20)insA', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 10,
            'position_end_intron' => 20,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_10)_20insA', array(
            'position_start' => 10,
            'position_end' => 20,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' => 'Insertions should not be given more than two positions.',
            ),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.1_10insA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'An insertion must have taken place between two neighboring positions. If the exact ' .
                    'location is unknown, please indicate this by placing parentheses around the positions.',
            ),
        )),
        array('c.123+1_124-1insA', array(
            'position_start' => 123,
            'position_end' => 124,
            'position_start_intron' => 1,
            'position_end_intron' => -1,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'An insertion must have taken place between two neighboring positions. If the exact ' .
                    'location is unknown, please indicate this by placing parentheses around the positions.',
            ),
        )),
        array('c.(123+1_124-1)insA', array(
            'position_start' => 123,
            'position_end' => 124,
            'position_start_intron' => 1,
            'position_end_intron' => -1,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),

        // Deletion-insertions.
        array('g.1_5delinsACT', array(
            'position_start' => 1,
            'position_end' => 5,
            'type' => 'delins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1delinsA', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'delins',
            'warnings' => array(
                'WWRONGTYPE' => 'A deletion-insertion of one base to one base should be described as a substitution.',
            ),
            'errors' => array(),
        )),
        array('g.1_5delins10_20', array(
            'position_start' => 1,
            'position_end' => 5,
            'type' => 'delins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_5delins20_10', array(
            'position_start' => 1,
            'position_end' => 5,
            'type' => 'delins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "delins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.100_200delins[NC_000001.10:g.100_200]', array(
            'position_start' => 100,
            'position_end' => 200,
            'type' => 'delins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('c.100_200delins[NG_000123.1:g.100_200]', array(
            'position_start' => 100,
            'position_end' => 200,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'delins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('c.100_200delins[LRG_123:g.100_200inv]', array(
            'position_start' => 100,
            'position_end' => 200,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'delins',
            'warnings' => array(),
            'errors' => array(),
        )),

        // Repeat sequences.
        array('g.1ACT[20]', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('c.1ACT[20]', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('c.1AC[20]', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                'WINVALIDREPEATLENGTH' => 'A repeat sequence of coding DNA should always have a length of (a multiple of) 3.',
            ),
            'errors' => array(),
        )),
        array('g.1AC[20]', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('g.1AC[20]GT[10]', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),

// 2) The lovd_fixHGVS() tests are currently broken. Now that lovd_getVariantInfo() is even more intelligent,
// fix these tests. I believe some code can now be removed from lovd_fixHGVS().
        // 3) con wordt niet herkend en we krijgen dus geen info terug. Dat is stom.
            // 4) space moet foutmelding geven, en niet stilletjes geaccepteerd worden. (er is hier een issue over).


//php > var_dump(lovd_getVariantLength(lovd_getVariantInfo("g.(100_?)inv")));
//int(1)
//php > var_dump(lovd_getVariantLength(lovd_getVariantInfo("g.100_?inv")));
//int(4294967196)

// CACNA1F_9662399_Strom-1998
//1106G®A
//
//
//MERTK_30851773_Bhatia-2019.pdf
//c.1647T4G
//
//
//
//MERTK_19403518_Charbel%20Issa-2009.pdf
// c.2189+1G.T
//
//
// NYX_11062472_Pusch-2000
// 1040T→C
//
// 1122–1457 del 326 bp
//
// CACNA1F_12111638_Wutz-2002
// 220T?C
//


//AIPL1_20702822_Jacobson-2011
//p.IVS2–2A G
//c.216G A
//c.364G C

//CRB1_32351147_Liu-2020
//C!T


        // Recognize "1122–1457 del 326 bp"??

// Wasn't the m. prefix specifically for the situation where the start position is larger than the end position? If so, we don't support this. Does VV? (no, opened an issue.)
//
// the “position(s)_deleted” should be listed from 5’ to 3’, e.g. 123_126 not 126_123.
// exception:
// when a circular genomic reference sequnce is used (“o.” and “m.” prefix) nucleotide positions may be listed from 3’ to 5’ when the deletion includes both the last and first nucleotides of the reference sequence
        // (we don't even support o. prefixes).



            // If the suffix will get any more complex, it's time to pull that apart.
            // One function will then parse the suffix and return an object. (length object or sequence object or variant object or array of objects, etc)
            // The rest of the code will then check if that object fits the variant, etc. There's currently too many pieces of code that handle variant suffixes,
            //   with complex if()s and regular expressions.



        // Mosaicism and chimerism.
        array('g.123=/A>G', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'mosaic',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.123=//A>G', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'chimeric',
            'warnings' => array(),
            'errors' => array(),
        )),

        // Wild type sequence (no changes).
        array('g.=', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '=',
            'warnings' => array(),
            'errors' => array(
                'EMISSINGPOSITIONS' => 'When using "=", please provide the position(s) that are unchanged.',
            ),
        )),
        array('g.123=', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => '=',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.123A=', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => '=',
            'warnings' => array(
                'WBASESGIVEN' => 'When using "=", please remove the original sequence before the "=".',
            ),
            'errors' => array(),
        )),

        // Unknown variants.
        array('c.?', array(
            'position_start' => 0,
            'position_end' => 0,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => NULL,
            'warnings' => array(),
            'errors' => array(),
        )),
        array('c.123?', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => NULL,
            'warnings' => array(),
            'errors' => array(),
        )),

        // Unsure variants.
        array('c.(123A>T)', array(
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'subst',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.(1_2insN[(50_60)])', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.((1_5)insN[(50_60)])', array(
            'position_start' => 1,
            'position_end' => 5,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('c.(123+1_124-1)insN[(50_60)]', array(
            'position_start' => 123,
            'position_end' => 124,
            'position_start_intron' => 1,
            'position_end_intron' => -1,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.((1_2insA)', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WUNBALANCEDPARENTHESES' => 'The variant description contains unbalanced parentheses.'
            ),
            'errors' => array(),
        )),

        // Positions with question marks.
        array('g.?del', array(
            'position_start' => 1,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
            ),
        )),
        array('g.1_?del', array(
            'position_start' => 1,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
            ),
        )),
        array('g.?_100del', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.'
            ),
        )),
        array('g.?_?del', array(
            'position_start' => 1,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions ?_? to ?.',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_?)del', array(
            'position_start' => 1,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?) to ?.',
            ),
            'errors' => array(
                'ESUFFIXMISSING' => 'The length must be provided for variants which took place within an uncertain range.',
            ),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_5)_10del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(5_?)_10del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_5)_?del', array(
            'position_start' => 5,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(5_?)_?del', array(
            'position_start' => 5,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_? to (5_?).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_?)_10del', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_10 to ?_10.',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_?)_(10_?)del', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(10_?) to ?_(10_?).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_?)_(?_10)del', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(?_10) to (?_10).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.5_(10_?)del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.5_(?_10)del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.?_(10_?)del', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.?_(?_10)del', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions ?_(?_10) to (?_10).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.5_(?_?)del', array(
            'position_start' => 5,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions 5_(?_?) to 5_?.',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(5_?)_(?_?)del', array(
            'position_start' => 5,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_(?_?) to (5_?).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_5)_(?_?)del', array(
            'position_start' => 5,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_5)_(?_?) to (?_5)_?.',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_5)_(10_?)del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(5_?)_(?_10)del', array(
            'position_start' => 5,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (5_?)_(?_10) to (5_10).',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(?_?)_(?_?)del', array(
            'position_start' => 1,
            'position_end' => 4294967295,
            'type' => 'del',
            'warnings' => array(
                'WTOOMUCHUNKNOWN' => 'This variant description contains redundant question marks. Please rewrite the positions (?_?)_(?_?) to ?.',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINPOSITIONS' => 'This variant description contains uncertain positions.',
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),

        // Challenging positions.
        array('g.(100_200)_(400_500)del', array(
            'position_start' => 200,
            'position_end' => 400,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(100_200)_(200_500)del', array(
            'position_start' => 200,
            'position_end' => 200,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.100_(400_500)del', array(
            'position_start' => 100,
            'position_end' => 400,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(100_200)_500del', array(
            'position_start' => 200,
            'position_end' => 500,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.1_1del', array (
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'This variant description contains two positions that are the same. Please verify your description and try again.'
            ),
            'errors' => array(),
        )),
        array('g.2_1del', array (
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
            ),
            'errors' => array(),
        )),
        array('c.*2_1del', array (
            'position_start' => 1,
            'position_end' => 1000002,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
            ),
            'errors' => array(),
        )),
        array('c.(*50_500)_(100_1)del', array (
            'position_start' => 100,
            'position_end' => 1000050,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('c.(500_*50)_(1_100)del', array (
            'position_start' => 100,
            'position_end' => 1000050,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'The positions are not given in the correct order. Please verify your description and try again.'
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('c.123-5_123-10del', array (
            'position_start' => 123,
            'position_end' => 123,
            'position_start_intron' => -10,
            'position_end_intron' => -5,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONFORMAT' => 'The intronic positions are not given in the correct order. Please verify your description and try again.'
            ),
            'errors' => array(),
        )),
        array('c.10000000_10000001del', array(
            'position_start' => 8388607,
            'position_end' => 8388607,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: start, end.',
            ),
            'errors' => array(),
        )),
        array('c.10000000+10000000000_10000001-10000000000del', array(
            'position_start' => 8388607,
            'position_end' => 8388607,
            'position_start_intron' => 2147483647,
            'position_end_intron' => -2147483648,
            'type' => 'del',
            'warnings' => array(
                'WPOSITIONSLIMIT' => 'Positions are beyond the possible limits of their type: start, start in intron, end, end in intron.',
            ),
            'errors' => array(),
        )),

        // Challenging insertions.
        array('g.1_2ins(5_10)', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins[A]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins[NC_123456.1:g.1_10;A;123_125;TGCG]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2ins[1_2;A]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('g.1_2insNC123456.1:g.1_10', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins340', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" does not follow HGVS guidelines.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins[123', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The part after "ins" contains unbalanced square brackets.',
            ),
            'errors' => array(),
        )),
        array('g.1_2ins[A[20];TGAAG[35];N[10]]', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'ins',
            'warnings' => array(),
            'errors' => array(),
        )),

        // Other affected sequences as suffixes.
        array('g.1delA', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXGIVEN' => 'Nothing should follow "del".',
            ),
            'errors' => array(),
        )),
        array('g.1_10delAAAAA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range longer than the given length of the variant.' .
                    ' Please adjust the positions if the variant length is certain, or remove the variant length.',
            ),
            'errors' => array(),
        )),
        array('g.1_10delAAAAAAAAAA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXGIVEN' => 'Nothing should follow "del".'
            ),
            'errors' => array(),
        )),
        array('g.(1_10)delAAAAAAAAAA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range equally long as the given length of the variant. Please remove the variant length and parentheses if the positions are certain, or adjust the positions or variant length.',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.1_10delAAAAAAAAAAAAAAA', array(
            'position_start' => 1,
            'position_end' => 10,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range shorter than the given length of the variant.' .
                    ' Please adjust the positions if the variant length is certain, or remove the variant length.',
            ),
            'errors' => array(),
        )),
        array('g.(1_100)del', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'ESUFFIXMISSING' => 'The length must be provided for variants which took place within an uncertain range.',
            ),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)delA', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del50', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "50" to "N[50]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del(30)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30)" to "N[30]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)delN[30]', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del(100)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(100)" to "N[100]".',
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range equally long as the given length of the variant. Please remove the variant length and parentheses if the positions are certain, or adjust the positions or variant length.',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del(30_30)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30_30)" to "N[30]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del(30_50)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30_50)" to "N[(30_50)]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)delN[(30_50)]', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)del(50_30)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(50_30)" to "N[(30_50)]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_100)delN[30_50]', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "N[30_50]" to "N[(30_50)]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(100_200)_(400_500)delEX5', array(
            'position_start' => 200,
            'position_end' => 400,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. ' .
                    'If you didn\'t mean to specify a variant length, please remove the part after "del".',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(100_200)_(400_500)del300', array(
            'position_start' => 200,
            'position_end' => 400,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "300" to "N[300]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_200)_(400_500)del(300)', array(
            'position_start' => 200,
            'position_end' => 400,
            'type' => 'del',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(300)" to "N[300]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IUNCERTAINRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.1inv(30)', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'inv',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30)" to "N[30]".',
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range shorter than the given length of the variant.' .
                    ' Please adjust the positions if the variant length is certain, or remove the variant length.',
            ),
            'errors' => array(
                'EPOSITIONFORMAT' => 'Inversions require a length of at least two bases.',
            ),
        )),
        array('g.1_100inv(30)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'inv',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30)" to "N[30]".',
                'WSUFFIXINVALIDLENGTH' =>
                    'The positions indicate a range longer than the given length of the variant.' .
                    ' Please adjust the positions if the variant length is certain, or remove the variant length.',
            ),
            'errors' => array(),
        )),
        array('g.(1_100)inv(30)', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'inv',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30)" to "N[30]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.(1_2)inv(30)', array(
            'position_start' => 1,
            'position_end' => 2,
            'type' => 'inv',
            'warnings' => array(
                'WSUFFIXFORMAT' => 'The length of the variant is not formatted following the HGVS guidelines. Please rewrite "(30)" to "N[30]".',
                'WSUFFIXINVALIDLENGTH' => 'The positions indicate a range smaller than the given length of the variant. Please adjust the positions or variant length.',
            ),
            'errors' => array(
                'EPOSITIONFORMAT' =>
                    'The two positions do not indicate a range longer than two bases. Please remove the parentheses if the positions are certain.',
            ),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),
        array('g.1ACT[20]A', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
            ),
            'errors' => array(),
        )),
        array('g.(1_100)ACT[20]A', array(
            'position_start' => 1,
            'position_end' => 100,
            'type' => 'repeat',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
                'WSUFFIXGIVEN' => 'Nothing should follow "ACT[20]".',
            ),
            'errors' => array(),
            'messages' => array(
                'IPOSITIONRANGE' => 'This variant description contains uncertain positions.',
            ),
        )),

        // Methylation-related changes.
        array('g.123|met=', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'met',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('g.123|gom', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'met',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('g.123|lom', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'met',
            'warnings' => array(
                'WNOTSUPPORTED' => 'Although this variant is a valid HGVS description, this syntax is currently not supported for mapping and validation.',
            ),
            'errors' => array(),
        )),
        array('g.123lom', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'EPIPEMISSING' => 'Please place a "|" between the positions and the variant type (lom).',
            ),
        )),
        array('g.123||bsrC', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'met',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'This not a valid HGVS description, please verify your input after "|".',
            ),
        )),

        // Descriptions that are currently unsupported.
        array('g.123^124A>C', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => '^',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' =>
                    'Currently, variant descriptions using "^" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('g.123A>C^124G>C', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => '^',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' =>
                    'Currently, variant descriptions using "^" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('g.123A>C;124A>C', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => ';',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' =>
                    'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
            ),
        )),
        array('g.[123A>C;124A>C]', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => ';',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
            ),
        )),
        array('g.[123A>C(;)124A>C]', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => ';',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
            ),
        )),
        array('g.[123A>C];[124A>C]', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => ';',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions of combined variants are not yet supported. This does not necessarily mean the description is not valid HGVS. Please submit your variants separately.',
            ),
        )),
        array('g.1_qterdel', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions using "qter" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('g.1_cendel', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions using "cen" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('g.pter_1000000del', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions using "pter" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('LRG_123:g.pter_1000000del', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' =>
                    'Currently, variant descriptions using "pter" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
                'EWRONGREFERENCE' =>
                    'The variant is missing a chromosomal reference sequence required for pter, cen, or qter positions.',
            ),
        )),
        array('n.5-2::10-3', array(
            'position_start' => 0,
            'position_end' => 0,
            'type' => '',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'Currently, variant descriptions using "::" are not yet supported. This does not necessarily mean the description is not valid HGVS.',
            ),
        )),
        array('g.123|bsrC', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'met',
            'warnings' => array(),
            'errors' => array(
                'ENOTSUPPORTED' => 'This not a valid HGVS description, please verify your input after "|".',
            ),
        )),

        // Descriptions holding reference sequences.
        array('NM_123456.1:c.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('NM_123456.1:c.1-1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => -1,
            'position_end_intron' => -1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' =>
                    'The variant is missing a genomic reference sequence required to verify the intronic positions.',
            ),
        )),
        array('NC_123456.1(NM_123456.1):g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' => 'The given reference sequence (NC_123456.1(NM_123456.1)) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
            ),
        )),
        array('NC_123456.1(NM_123456.1):c.1-1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => -1,
            'position_end_intron' => -1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('ENST12345678911.1:c.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('LRG_123:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('LRG_123t1:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' => 'The given reference sequence (LRG_123t1) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
            ),
        )),
        array('LRG_123t1:c.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('LRG_123t1:n.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('LRG_123:c.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' => 'The given reference sequence (LRG_123) does not match the DNA type (c). For c. variants, please use a coding transcript reference sequence.',
            ),
        )),
        array('NR_123456.1:n.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('NM_123456.1:n.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'position_start_intron' => 0,
            'position_end_intron' => 0,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' => 'The given reference sequence (NM_123456.1) does not match the DNA type (n). For n. variants, please use a non-coding transcript reference sequence.',
            ),
        )),

        array('NM_123456.1:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EWRONGREFERENCE' => 'The given reference sequence (NM_123456.1) does not match the DNA type (g). For g. variants, please use a genomic reference sequence.',
            ),
        )),
        array('NC_123456.1:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),
        array('ENSG12345678911.1:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(),
        )),

        array('NC_12345.1:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EREFERENCEFORMAT' => 'The reference sequence could not be recognised. Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.',
            ),
        )),
        array('NC_123456:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EREFERENCEFORMAT' => 'The reference sequence used is missing the required version number. NCBI RefSeq and Ensembl IDs require version numbers when used in variant descriptions.',
            ),
        )),
        array('LRG:g.1del', array(
            'position_start' => 1,
            'position_end' => 1,
            'type' => 'del',
            'warnings' => array(),
            'errors' => array(
                'EREFERENCEFORMAT' => 'The reference sequence could not be recognised. Supported reference sequence IDs are from NCBI Refseq, Ensembl, and LRG.',
            ),
        )),

        // Other errors or problems.
        array('G.123dup', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'dup',
            'warnings' => array(
                'WWRONGCASE' => 'This not a valid HGVS description, due to characters being in the wrong case. Please check the use of upper- and lowercase characters.',
            ),
            'errors' => array(),
        )),
        array('g. 123_124insA', array(
            'position_start' => 123,
            'position_end' => 124,
            'type' => 'ins',
            'warnings' => array(
                'WWHITESPACE' => 'This variant description contains one or more whitespace characters (spaces, tabs, etc). Please remove these.',
            ),
            'errors' => array(),
        )),
        array(' g.123del', array(
            'position_start' => 123,
            'position_end' => 123,
            'type' => 'del',
            'warnings' => array(
                'WWHITESPACE' => 'This variant description contains one or more whitespace characters (spaces, tabs, etc). Please remove these.',
            ),
            'errors' => array(),
        )),
    );
}



function lovd_getFixHGVSTestData ()
{
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
        array('c.?', 'c.?'),
        array('c.123?', 'c.123?'),
        array('c.(1_100)del(20)', 'c.(1_100)delN[20]'),



        // FIXABLE VARIANTS.
        // Missing prefixes that will be added.
        array('123dup', 'g.123dup'),
        array('(123dup)', 'g.(123dup)'),
        array('.123dup', 'g.123dup'),
        array('123-5dup', 'c.123-5dup'),

        // Wrong prefix, the size of the positions indicates it's a range,
        //  and the range is fixed to a single position.
        array('g.140712592-140712592C>T', 'g.140712592C>T'),

        // Whitespace and other copy/paste errors.
        array('g. 123_124insA', 'g.123_124insA'),
        array(' g.123del', 'g.123del'),
        array('c.–123del', 'c.-123del'),
        array('c.–123del', 'c.-123del'),
        array('c.123—5del', 'c.123-5del'),

        // Lowercase nucleotides and other case issues.
        array('C.123C>a', 'c.123C>A'),
        array('C.123a>u', 'c.123A>T'),
        array('g.123insactg', 'g.123insACTG'),
        array('g.123delinsgagagauu', 'g.123delinsGAGAGATT'),
        array('g.123a>g', 'g.123A>G'),
        array('g.100_101ins[nc_000010.1:g.100_200;aaaa;n[10]]', 'g.100_101ins[NC_000010.1:g.100_200;AAAA;N[10]]'),
        array('lrg_123t1:c.100del', 'LRG_123t1:c.100del'),

        // U given instead of T.
        array('g.123insAUG', 'g.123insATG'),

        // Variant types should be something else.
        array('g.100_200con400_500', 'g.100_200delins400_500'),
        array('g.123conNC_000001.10:100_200', 'g.123delins[NC_000001.10:g.100_200]'),
        array('g.123A>GC', 'g.123delinsGC'),
        array('g.123A>AA', 'g.123dup'),
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

        // Superfluous suffixes.
        array('c.123delA', 'c.123del'),
        array('c.123delAA', 'c.123delAA'), // Unfixable.
        array('g.123del1', 'g.123del'),
        array('g.123del2', 'g.123del2'), // Unfixable.
        array('c.123_124delA', 'c.123_124delA'), // Unfixable.
        array('c.123_124delAA', 'c.123_124del'),
        array('g.123_124del1', 'g.123_124del1'), // Unfixable.
        array('g.123_124del2', 'g.123_124del'),

        // Wrongly formatted suffixes.
        array('c.1_2ins[A]', 'c.1_2insA'),
        array('c.1_2ins[N]', 'c.1_2insN'),
        array('c.1_2ins(A)', 'c.1_2insA'),
        array('c.1_2ins(20)', 'c.1_2insN[20]'),
        array('c.1_2ins(20_50)', 'c.1_2insN[(20_50)]'),
        array('c.1_2ins(50_20)', 'c.1_2insN[(20_50)]'),
        array('c.1_2ins[NC_000001.10:100_(300_200);400_500]',
              'c.1_2ins[NC_000001.10:g.100_(200_300);400_500]'),
        array('c.1_2ins[NC_000001.10:100_(300_200);(400_500)]',
              'c.1_2ins[NC_000001.10:g.100_(200_300);N[(400_500)]]'),
        array('c.1_2ins[NC_000001.10(100_200)_300]',
              'c.1_2ins[NC_000001.10:g.(100_200)_300]'),
        array('g.((1_5)ins(50))', 'g.((1_5)insN[50])'),
        array('g.1_2ins[ACT;(20)]', 'g.1_2ins[ACT;N[20]]'),
        array('g.(100_200)del50', 'g.(100_200)delN[50]'),
        array('g.(100_200)del(60_50)', 'g.(100_200)delN[(50_60)]'),


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

        // Variants with reference sequences, testing various fixes.
        array('NC_123456.10:(123delA)', 'NC_123456.10:g.(123del)'),
        array('NC_123456.10:g.123_234conaaa', 'NC_123456.10:g.123_234delinsAAA'),

        // Where we can still improve
        //  (still results in an invalid description - more work needed,
        //   or variants currently not supported and returned as-is).
        array('g.(100_200)[ins50]', 'g.(100_200)[ins50]'),
        array('g.123delAinsG', 'g.123delAinsG'), // Should be g.123A>G.
        // Real problem is a typo in the last position; could we recognize this?
        array('g.(150138199_150142492)_(150145873_15147218)del',
              'g.(15147218_150142492)_(150138199_150145873)del'),
        array('g.123^124A>C', 'g.123^124A>C'),
        array('g.123A>C^124G>C', 'g.123A>C^124G>C'),
        array('g.123A>C;124A>C', 'g.123A>C;124A>C'),
        array('g.[123A>C;124A>C]', 'g.[123A>C;124A>C]'),
        array('g.[123A>C(;)124A>C]', 'g.[123A>C(;)124A>C]'),
        array('g.[123A>C];[124A>C]', 'g.[123A>C];[124A>C]'),



        // UNFIXABLE VARIANTS.
        array('', ''),
        array('g.1delinsA', 'g.1delinsA'),
        array('c.1AC[20]', 'c.1AC[20]'),
        array('c.1_2A>G', 'c.1_2A>G'),
        array('g.123A>Ciets', 'g.123A>Ciets'),
        array('g.=', 'g.='),
        array('c.1insA', 'c.1insA'),
        array('c.1_2ins', 'c.1_2ins'),
        array('c.1_10insA', 'c.1_10insA'),
        array('c.1_20insBLA', 'c.1_20insBLA'),
        array('c.1_100insA', 'c.1_100insA'),
        array('c.1_100del(10)', 'c.1_100delN[10]'),
        array('g.1_100inv(30)', 'g.1_100invN[30]'),
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





function writeTestFile ()
{
    $aTestArray = array_unique(array_map('array_shift', array_merge(lovd_getFixHGVSTestData(), lovd_getGetInfoTestDataSets())));

    foreach($aTestArray as $i => $sVariant) {
        // We assume the checkers will FIRST check the syntax, and
        //  follow by the actual variant check.

        // Note: substr() doesn't throw a notice when $sVariant[0] doesn't exist.
        if (substr($sVariant, 0, 1) == 'g') {
            $sReference = 'LRG_199';
        } elseif (substr($sVariant, 0, 1) == 'm') {
            $sReference = 'NC_012920.1'; // Circular reference.
        } elseif (substr($sVariant, 0, 1) == 'n') {
            $sReference = 'LRG_199t1';
        } elseif (substr($sVariant, 0, 1) == 'c') {
            $sReference = 'LRG_199t1';

        } else {
            if (preg_match('/^NM/', $sVariant)){
                $sReference = 'NM_004006.2';
            } elseif
                (preg_match('/^NC.*\(NM/', $sVariant)){
                $sReference = 'NM_004006.2';
            } elseif
                (preg_match('/^NR/', $sVariant)){
                $sReference = 'NR_015380.1';
            } elseif
                (preg_match('/^ENST/', $sVariant)){
                $sReference = 'ENST00000357033.8';
            } elseif
                (preg_match('/^ENSG/', $sVariant)){
                $sReference = 'ENSG00000198947.15';

            } else {
                    // If no prefix nor a reference sequence was given, we add a reference
                    // sequence which works with as many DNA types as possible.
                    $sReference = 'LRG_199t1';
            }

            $sVariant = preg_replace('/.*:/', '', $sVariant);
        }

        $aTestArray[$i] = $sReference . ':' . $sVariant;
    }

    // This sort works better (more logical) than the sort in bash.
    sort($aTestArray);

    // Select the unique variants and store.
    if (!file_put_contents('testVariantsUnique.txt', implode("\n", array_unique($aTestArray)))) {
        // Can't create file.
        die('Could not create file.' . "\n\n");
    } else {
        die('Successfully created testVariantsUnique.txt.' . "\n\n");
    }
}



writeTestFile();
?>
