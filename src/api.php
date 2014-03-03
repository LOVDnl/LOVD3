<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-11-08
 * Modified    : 2014-03-03
 * For LOVD    : 3.0-10
 *
 * Supported URIs:
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}/{{ ID }}
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}/unique
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_position=c.1234
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_position=c.1234+56_2345-67 (c.1234%2B56_2345-67)
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_position=g.12345678
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_position=g.1234_5678&position_match=exact|exclusive|partial
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_Variant%2FDNA=c.1234C>G (c.1234C%3EG)
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?search_Variant%2FDBID=DMD_01234
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?format=text/bed
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?format=text/bed&visibility=2
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?format=text/bed&PMID={{ PMID }}
 *  3.0-beta-10  /api/rest.php/variants/{{ GENE }}?format=text/bed&PMID={{ PMID }}&visibility=2
 *  3.0-beta-10  /api/rest.php/genes
 *  3.0-beta-10  /api/rest.php/genes/{{ GENE }}
 *  3.0-beta-10  /api/rest.php/genes?search_symbol=DMD
 *  3.0-beta-10  /api/rest.php/genes?search_position=chrX
 *  3.0-beta-10  /api/rest.php/genes?search_position=chrX:3200000
 *  3.0-beta-10  /api/rest.php/genes?search_position=chrX:3200000_4000000&position_match=exact|exclusive|partial
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

/*
// DMD_SPECIFIC : remove block.
// I believe these are all Status codes I need to implement in the future. Those with asterisks are not yet implemented.
    HTTP/1.0 200 OK
*   HTTP/1.0 201 Created
    HTTP/1.0 400 Bad Request // The parameters passed to the service did not match as expected. The exact error is returned in the XML response.
*   HTTP/1.0 403 Forbidden // With 401 we are required to send more, now we're not.
    HTTP/1.0 404 Not Found // ID that does not exist?
*   HTTP/1.0 405 Method Not Allowed // Don't forget an Allow header with allowed methods. Use this if the method is not allowed for *this* resource.
*   HTTP/1.0 409 Conflict // After a PUT???
*   HTTP/1.0 410 Gone // If we know it was there, but not anymore (if we don't know: 404)
*   HTTP/1.0 500 Internal Server Error
    HTTP/1.0 501 Not Implemented // This is the appropriate response when the server does not recognize the request method and is not capable of supporting it for *any* resource.
*   HTTP/1.0 503 Service Unavailable // TEMPORARY: The implication is that this is a temporary condition which will be alleviated after some delay. If known, the length of the delay MAY be indicated in a Retry-After header.
*/

// Will currently only allow GET.
if (!in_array($_SERVER['REQUEST_METHOD'], array('GET'))) {
    header('HTTP/1.0 501 Not Implemented');
}

// Parse URL to see what we need to do.
list(,,$sDataType, $sSymbol, $nID) = array_pad($_PE, 5, '');

if (!$sDataType) { // No data type given.
    header('HTTP/1.0 400 Bad Request');
    die('Too few parameters.');
} elseif (!in_array($sDataType, array('variants', 'genes'))) { // Wrong data type given.
    header('HTTP/1.0 400 Bad Request');
    die('Requested data type not known.');
} elseif ($sDataType == 'variants' && !$sSymbol) { // Variants, but no gene selected.
    header('HTTP/1.0 400 Bad Request');
    die('Too few parameters.');
}
// Now we've got $sDataType, $sSymbol, $nID, FORMAT filled in, if data is available.





// Check if gene exists.
if ($sSymbol) {
    $sSymbol = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($sSymbol))->fetchColumn();
    if (!$sSymbol) {
        header('HTTP/1.0 404 Not Found');
        die('This gene does not exist.');
    }
}





// Need some libraries.
require ROOT_PATH . 'inc-lib-api.php';

// Depending on the requested data type, we need to segment the code here.
if ($sDataType == 'variants') {
    // Check if the DNA and DBID fields are actually there (should always be the case except in modified LOVD instances).
    require ROOT_PATH . 'class/object_genome_variants.php';
    require ROOT_PATH . 'class/object_transcript_variants.php';
    $_DATA = array();
    $_DATA['Genome'] = new LOVD_GenomeVariant();
    $_DATA['Transcript'] = new LOVD_TranscriptVariant($sSymbol);

    if ((!$_DATA['Transcript']->colExists('VariantOnTranscript/DNA') || !$_DATA['Genome']->colExists('VariantOnGenome/DBID'))) {
        header('HTTP/1.0 503 Service Unavailable');
        die('This gene does not have the VariantOnTranscript/DNA or the VariantOnGenome/DBID fields enabled, crucial for the API.');
    }

    $bUnique = ($nID == 'unique');
    if ($bUnique) {
        $nID = false;
    } elseif ($nID && !preg_match('/^[0-9]+$/', $nID)) {
        header('HTTP/1.0 404 Not Found');
        die(ucfirst(substr($sDataType, 0, -1)) . ' ID does not exist.');
    }

    // Get chromosome, reference sequence, and other data.
    // LOVD3 has multiple transcripts maybe, so we just grab the first one.
    // This is actually not really useful for BED files...
    list($sChromosome, $nRefSeqID, $sRefSeq, $nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $bSense) =
        $_DB->query('SELECT g.chromosome, t.id, t.id_ncbi, t.position_c_mrna_start, t.position_c_mrna_end, t.position_c_cds_end, (t.position_g_mrna_start < t.position_g_mrna_end) AS sense
                     FROM ' . TABLE_GENES . ' as g LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid)
                     WHERE g.id = ? ORDER BY t.id ASC LIMIT 1',
            array($sSymbol))->fetchRow();

    if (FORMAT == 'text/bed') {
        // We're exporting a BED file for a Genome Browser.
        $sBuild = $_CONF['refseq_build'];
        if ($sRefSeq && isset($_SETT['human_builds'][$sBuild]) && $sBuild != '----') {
            // If requested, show only variants from a certain PMID.
            $nPMID = (empty($_GET['PMID']) || !ctype_digit($_GET['PMID'])? 0 : $_GET['PMID']);
            // If PMID is requested, check in which columns the PMID custom link is active. Through the same query we can join to TABLE_ACTIVE_COLS so we are sure the column can be used in a query.
            $aPMIDCols = array();
            $bJoinWithPatient = false;
            if ($nPMID) {
                $aCols = $_DB->query('SELECT DISTINCT ac.colid FROM ' . TABLE_COLS2LINKS . ' AS c2l INNER JOIN ' . TABLE_ACTIVE_COLS . ' AS ac ON (c2l.colid = ac.colid) WHERE c2l.linkid = 1')->fetchAllColumn();
                foreach ($aCols as $sCol) {
                    if (strpos($sCol, 'Individual/') === 0) {
                        $bJoinWithPatient = true;
                    }
                    $aPMIDCols[] = $sCol;
                }
            }
            $bQueryPMID = ($nPMID && count($aPMIDCols));
            $sQ = 'SELECT LEAST(vog.position_g_start, vog.position_g_end), GREATEST(vog.position_g_start, vog.position_g_end), vog.type, vot.`VariantOnTranscript/DNA`
                   FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)' .
                   (!$bJoinWithPatient? '' : ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ') . '
                   WHERE t.geneid = "' . $sSymbol . '" AND vog.statusid >= ' . STATUS_MARKED . ' AND vog.position_g_start != 0 AND vog.position_g_start IS NOT NULL' .
                   (!$bQueryPMID? '' : ' AND (`' . implode('` LIKE "%:' . $nPMID . '}%" OR `', $aPMIDCols) . '` LIKE "%:' . $nPMID . '}%") ') . '
                   GROUP BY vog.`VariantOnGenome/DNA` ORDER BY vog.position_g_start, vog.position_g_end';
        } else {
            // Not mappable!
            header('HTTP/1.0 503 Service Unavailable');
            die('This gene does not have a NM reference sequence associated to it, crucial for mapping variants to the genome.');
        }

    } else {
        // First build query.
        $sQ = 'SELECT vog.id, vot.position_c_start, vot.position_c_start_intron, vot.position_c_end, vot.position_c_end_intron, vog.position_g_start, vog.position_g_end, vot.`VariantOnTranscript/DNA`, vog.`VariantOnGenome/DBID`, SUM(IFNULL(i.panel_size, 1)) AS Times
               FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_VARIANTS . ' AS vog USING (id) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id AND i.statusid >= ' . STATUS_MARKED . ')
               WHERE vot.transcriptid = ' . $nRefSeqID . ' AND vog.statusid >= ' . STATUS_MARKED;
        $bSearching = false;
        if ($nID) {
            $sFeedType = 'entry';
            $sQ .= ' AND vog.id = "' . $nID . '"';
        } else {
            $sFeedType = 'feed';
            // Ok, are we searching then?
            $aSearchableFields = array('position', 'Variant/DNA', 'Variant/DBID');
            foreach ($aSearchableFields as $sField) {
                if (!empty($_GET['search_' . $sField])) {
                    $bSearching = true;
                    if ($sField == 'position') {
                        if ($sRefSeq && (preg_match('/^(g)\.([0-9]+)(_([0-9]+))?$/', $_GET['search_' . $sField], $aRegs) || preg_match('/^(c)\.([*-]?[0-9]+([+-][du]?[0-9]+)?)(_([*-]?[0-9]+([+-][du]?[0-9]+)?))?$/', $_GET['search_' . $sField], $aRegs))) {
                            // $aRegs numbering:        1    2       3 4                                                                   1    2           3                  4 5           6
                            // Mapping is only possible if there is a Reference Sequence.
                            if ($aRegs[1] == 'g') {
                                if (empty($aRegs[3])) {
                                    // No range. Absolute location.
                                    $aRegs[4] = $aRegs[2];
                                }
                                // Very important in genomic positions: genes on antisense will have positions like g.5678_1234 in the database!!!
                                $nMin = min($aRegs[2], $aRegs[4]);
                                $nMax = max($aRegs[2], $aRegs[4]);
                                if (!empty($_GET['position_match'])) {
                                    if ($_GET['position_match'] == 'exclusive') {
                                        // Mutation should be completely in the range.
                                        $sQ .= ' AND vog.position_g_start ' . ($bSense? '>= ' . $nMin : '<= ' . $nMax) . ' AND vog.position_g_end ' . ($bSense? '<= ' . $nMax : '>= ' . $nMin);
                                        continue;
                                    } elseif ($_GET['position_match'] == 'partial') {
                                        $sQ .= ' AND (vog.position_g_start BETWEEN ' . $nMin . ' AND ' . $nMax . ' OR vog.position_g_end BETWEEN ' . $nMin . ' AND ' . $nMax . ' OR (vog.position_g_start ' . ($bSense? '<= ' . $nMin : '>= ' . $nMax) . ' AND vog.position_g_end ' . ($bSense? '>= ' . $nMax : '<= ' . $nMin) . '))';
                                        continue;
                                    }
                                }
                                // Exact match, directly requested through $_GET['position_match'] or argument not given/recognized.
                                $sQ .= ' AND position_' . $aRegs[1] . '_start = "' . ($bSense? $nMin : $nMax) . '" AND position_' . $aRegs[1] . '_end = "' . ($bSense? $nMax : $nMin) . '"';
                            } else {
                                $aStart = lovd_convertDNAPositionToDB($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $aRegs[2]);
                                if (empty($aRegs[4])) {
                                    $aEnd = $aStart;
                                } else {
                                    $aEnd = lovd_convertDNAPositionToDB($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $aRegs[5]);
                                }
                                $sQ .= ' AND position_' . $aRegs[1] . '_start = "' . $aStart[0] . '" AND position_' . $aRegs[1] . '_start_intron = "' . $aStart[1] . '" AND position_' . $aRegs[1] . '_end = "' . $aEnd[0] . '" AND position_' . $aRegs[1] . '_end_intron = "' . $aEnd[1] . '"';
                            }
                        } else {
                            // This does a first match; trying to find the position at the start of the DNA field. Later this match will be made more accurate!
                            $sQ .= ' AND REPLACE(REPLACE(REPLACE(REPLACE(vot.`VariantOnTranscript/DNA`, "[", ""), "(", ""), ")", ""), "?", "") LIKE "' . $_GET['search_' . $sField] . '%"';
                        }
                    } elseif ($sField == 'Variant/DNA') {
                        // This matches regardless of the characters (, ) and ?.
                        $sQ .= ' AND REPLACE(REPLACE(REPLACE(vot.`VariantOnTranscript/DNA`, "(", ""), ")", ""), "?", "") = "' . str_replace(array('(', ')', '?'), ' ', $_GET['search_' . $sField]) . '"';
                    } elseif ($sField == 'Variant/DBID') {
                        $sQ .= ' AND vog.`VariantOnGenome/DBID` LIKE "' . $_GET['search_' . $sField] . '%"';
                    } else {
                        $sQ .= ' AND vot.`' . $sField . '` = "' . $_GET['search_' . $sField] . '"';
                    }
                }
            }
        }
        if ($bUnique) {
            $sQ .= ' GROUP BY vot.`VariantOnTranscript/DNA`, vog.`VariantOnGenome/DBID`';
        } else {
            $sQ .= ' GROUP BY vog.id';
        }
        $sQ .= ' ORDER BY vog.position_g_start, vog.position_g_end, `VariantOnGenome/DNA`';
    }



} elseif ($sDataType == 'genes') {
    // Listing or simple request on gene symbol.
    // First build query.
    $sQ = 'SELECT g.id, g.name, g.chromosome, g.chrom_band, (t.position_g_mrna_start < t.position_g_mrna_end) AS sense, LEAST(MIN(t.position_g_mrna_start), MIN(t.position_g_mrna_end)) AS position_g_mrna_start, GREATEST(MAX(t.position_g_mrna_start), MAX(t.position_g_mrna_end)) AS position_g_mrna_end, g.refseq_genomic, GROUP_CONCAT(DISTINCT t.id_ncbi ORDER BY t.id_ncbi) AS id_ncbi, g.id_entrez, g.created_date, g.updated_date, u.name AS created_by, GROUP_CONCAT(DISTINCT cur.name SEPARATOR ", ") AS curators
           FROM ' . TABLE_GENES . ' AS g LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) LEFT JOIN ' . TABLE_USERS . ' AS u ON (g.created_by = u.id) LEFT JOIN ' . TABLE_CURATES . ' AS u2g ON (g.id = u2g.geneid AND u2g.allow_edit = 1) LEFT JOIN ' . TABLE_USERS . ' AS cur ON (u2g.userid = cur.id)
           WHERE 1=1';

    $bSearching = false;
    if ($sSymbol) {
        $sFeedType = 'entry';
        $sQ .= ' AND g.id = "' . $sSymbol . '"';
    } else {
        $sFeedType = 'feed';
        // Ok, are we searching then?
        $aSearchableFields = array('symbol', 'position');
        foreach ($aSearchableFields as $sField) {
            if (!empty($_GET['search_' . $sField])) {
                $bSearching = true;
                if ($sField == 'symbol') {
                    $sQ .= ' AND g.id = "' . $_GET['search_' . $sField] . '"';
                } elseif ($sField == 'position' && preg_match('/^chr([0-9]{1,2}|[MXY])(:[0-9]{1,9}(_[0-9]+)?)?$/', $_GET['search_' . $sField], $aRegs)) {
                    // $aRegs numbering:                             1                2           3
                    @list(, $sChromosome, $sPositionStart, $sPositionEnd) = $aRegs;
                    $sPositionStart = @substr($sPositionStart, 1); // Strip off the : at the beginning.
                    $sPositionEnd   = @substr($sPositionEnd, 1); // Strip off the _ at the beginning.
                    $sQ .= ' AND g.chromosome = "' . $sChromosome . '"';
                    if ($sPositionStart) {
                        if (!$sPositionEnd) {
                            // No range. Absolute location.
                            // We can't use LEAST(MIN(position_g_mrna_start), MIN(position_g_mrna_end)) since that's an invalid use of a group function.
                            // That could be fixed by using a subquery, but I'm doing it like this. Now it's a restriction on the transcripts, not on the gene.
                            // So based on the position, the query may return less transcripts than actually in the database!!!
                            $sQ .= ' AND LEAST(t.position_g_mrna_start, t.position_g_mrna_end) <= ' . $sPositionStart . ' AND GREATEST(t.position_g_mrna_start, t.position_g_mrna_end) >= ' . $sPositionStart;
                        } else {
                            // Actually, $sPositionStart still was the range.
                            list($sPositionStart, $sPositionEnd) = explode('_', $sPositionStart);
                            // Very important in genomic positions: transcripts on antisense will have positions like g.5678_1234 in the database!!!
                            $nPositionMin = min($sPositionStart, $sPositionEnd);
                            $nPositionMax = max($sPositionStart, $sPositionEnd);
                            if (!empty($_GET['position_match'])) {
                                if ($_GET['position_match'] == 'exclusive') {
                                    // Transcript (see note in previous query extension) should be completely in the range.
                                    $sQ .= ' AND LEAST(t.position_g_mrna_start, t.position_g_mrna_end) >= ' . $nPositionMin . ' AND GREATEST(t.position_g_mrna_start, t.position_g_mrna_end) <= ' . $nPositionMax;
                                    continue;
                                } elseif ($_GET['position_match'] == 'partial') {
                                    // Transcript (see note in previous query extension) should be at least partially in the range.
                                    $sQ .= ' AND (t.position_g_mrna_start BETWEEN ' . $nPositionMin . ' AND ' . $nPositionMax . ' OR t.position_g_mrna_end BETWEEN ' . $nPositionMin . ' AND ' . $nPositionMax . ' OR (LEAST(t.position_g_mrna_start, t.position_g_mrna_end) <= ' . $nPositionMin . ' AND GREATEST(t.position_g_mrna_start, t.position_g_mrna_end) >= ' . $nPositionMax . '))';
                                    continue;
                                }
                            }
                            // Exact match, directly requested through $_GET['position_match'] or argument not given/recognized.
                            // See note in previous query extension.
                            $sQ .= ' AND LEAST(t.position_g_mrna_start, t.position_g_mrna_end) = ' . $nPositionMin . ' AND GREATEST(t.position_g_mrna_start, t.position_g_mrna_end) = ' . $nPositionMax;
                        }
                    }
                }
            }
        }
    }
    $sQ .= ' GROUP BY g.id ORDER BY g.id';
}
$aData = $_DB->query($sQ)->fetchAllAssoc();
$n = count($aData);

if ($n) {
    header('HTTP/1.0 200 OK');
} elseif (FORMAT != 'text/bed') {
    // We don't want 404s in de text/bed format, ever. It should just return a BED file with a header, but no variants.
    header('HTTP/1.0 404 Not Found');
    if ($nID) {
        // Really requested a (variant) ID. Goodbye.
        die(ucfirst($sDataType) . ' ID does not exist.');
    }
}

if ($sDataType == 'variants' && FORMAT == 'text/bed') {
    // We're exporting a BED file for a Genome Browser.
    // This code structure is getting pretty bad, by the way.
    $aVariantTypeColors =
             array(
                    'substr' => '204,0,255',
                    '>'      => '204,0,255', // This one can be removed later.
                    'del'    => '0,0,255',
                    'ins'    => '0,153,0',
                    'dup'    => '255,153,0',
                    ''       => '0,0,0', // Backup, for non-matching variants.
                  );

    // Print header.
    header('Content-type: text/plain; charset=UTF-8');
    print('track name="Variants in the LOVD ' . $sSymbol . ' database' . (!$nPMID? '' : ' (PMID:' . $nPMID . ')') . '" description="Variants in LOVD ' . $sSymbol . ' db' . (!$nPMID? '' : ' (PMID:' . $nPMID . ')') . '" visibility=' . (!empty($_GET['visibility']) && is_numeric($_GET['visibility'])? $_GET['visibility'] : 3) . ' itemRgb="On" db="' . $sBuild . '" url="' . ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'variants.php?select_db=' . $sSymbol . '&action=search_all&trackid=$$' . '"' . "\n\n");

    foreach ($aData as $r) {
        list($nPositionStart, $nPositionEnd, $sVariantType, $sDNA) = array_values($r);
        if (!isset($aVariantTypeColors[$sVariantType])) {
            $sVariantType = '';
        }
        $sVariantTypeColor = $aVariantTypeColors[$sVariantType];

        // Print the data.
        print('chr' . $sChromosome . "\t" . ($nPositionStart-1) . "\t" . $nPositionEnd . "\t" . $sSymbol . ':' . preg_replace('/\s+/', '', $sDNA) . "\t" . '0' . "\t" . ($bSense? '+' : '-') . "\t" . ($nPositionStart-1) . "\t" . $nPositionEnd . "\t" . $sVariantTypeColor . "\n");
    }
    exit;
}

// Start feed class.
require ROOT_PATH . 'class/feeds.php';
if ($sFeedType == 'feed') {
    if ($sDataType == 'variants') {
        $sTitle = ($bSearching? ($n? 'R' : 'No r') . 'esults for your query of' : 'Listing of all public variants in') . ' the ' . $sSymbol . ' gene database';
    } elseif ($sDataType == 'genes') {
        // This overview needs some more time to be generated.
        set_time_limit(60);
        $sTitle = ($bSearching? ($n? 'R' : 'No r') . 'esults for your query of' : 'Listing of all genes in') . ' the database';
    }
    $sLink = ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'api/rest.php/' . $sDataType . ($sSymbol? '/' . $sSymbol : '') . (empty($bUnique)? '' : '/unique');
    $sID   = 'tag:' . $_SERVER['HTTP_HOST'] . ',' . $_STAT['installed_date'] . ':' . $_STAT['signature'] . '/REST_api';
} else {
    $sTitle = $sLink = $sID = '';
}
$_FEED = new Feed($sFeedType, $sTitle, $sLink, $sID, 'atom');

// Now we will create entries in the feed with the fetched data.
if ($sDataType == 'variants') {
    foreach ($aData as $zData) {
        // Prepare other fields to be included.
        $sTitle = substr($sSymbol, 0, strpos($sSymbol . '_', '_')) . ':' . htmlspecialchars($zData['VariantOnTranscript/DNA']);
        if ($sFeedType == 'feed') {
            $sSelfURL = ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'api/rest.php/variants/' . $sSymbol . '/' . $zData['id'];
        } else {
            $sSelfURL = '';
        }
        // We're assuming here that the start of the DBID field will always be the ID, like the column's default RegExp forces.
        $zData['VariantOnGenome/DBID'] = preg_replace('/^(\w+).*$/', "$1", $zData['VariantOnGenome/DBID']);
        $sAltURL               = ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'variants/' . $sSymbol . '/' . $sRefSeq . '?search_VariantOnGenome%2FDBID=' . rawurlencode($zData['VariantOnGenome/DBID']);
        $zData['created_date'] = '1970-01-01 00:00:00';
        $zData['updated_date'] = '1970-01-01 00:00:00';
        $sID                   = 'tag:' . $_SERVER['HTTP_HOST'] . ',' . substr($zData['created_date'], 0, 10) . ':' . $sSymbol . '/' . $zData['id'];
        $zData['created_by']   = 'Unknown'; // We need to get this data from two tables... too much work?
        $sContributors         = 'Unknown'; // We need to get this data from two tables... too much work?
        if ($sRefSeq && $zData['position_c_start']) {
            $sDNAStart = lovd_convertDNAPositionToHR($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $zData['position_c_start'], $zData['position_c_start_intron']);
            if ($zData['position_c_start'] == $zData['position_c_end']) {
                $sDNAEnd = $sDNAStart;
            } else {
                $sDNAEnd = lovd_convertDNAPositionToHR($nPositionMRNAStart, $nPositionMRNAEnd, $nPositionCDSEnd, $zData['position_c_end'], $zData['position_c_end_intron']);
            }
            $sPosition_mRNA    = $sRefSeq . ':c.' . $sDNAStart . ($zData['position_c_end'] == $zData['position_c_start']? '' : '_' . $sDNAEnd);
            $sPosition_genomic = 'chr' . $sChromosome . ':' . $zData['position_g_start'] . ($zData['position_g_end'] == $zData['position_g_start']? '' : '_' . $zData['position_g_end']);
        } else {
            $sPosition_mRNA    = lovd_variantToPosition($zData['VariantOnTranscript/DNA']);
            $sPosition_genomic = 'chr' . $sChromosome . ':?';
        }
        // Really not quite the best solution, but it kind of works. $n is decreased and if there are no more matches, it will give a 404 anyway. Still has a misleading Feed title, though!
        // FIXME; do we want to fix that by implementing a $_FEED->setTitle()?
        if (!$sRefSeq && $bSearching && !empty($_GET['search_position']) && $_GET['search_position'] != $sPosition_mRNA) {
            // This was a false positive! (only when there is no Reference Sequence LOVD will try the DNA field to find the position) Partial match that should not have been reported. Byeeeeeeee...
            $n --; // Does not really matter at this point.
            continue;
        }
        $sContent = 'symbol:' . $sSymbol . "\n" .
                    ($bUnique? '' :
                    'id:' . $zData['id'] . "\n") .
                    'position_mRNA:' . $sPosition_mRNA . "\n" .
                    'position_genomic:' . $sPosition_genomic . "\n" .
                    'Variant/DNA:' . htmlspecialchars($zData['VariantOnTranscript/DNA']) . "\n" .
                    'Variant/DBID:' . $zData['VariantOnGenome/DBID'] . "\n" .
                    'Times_reported:' . $zData['Times'];
        $_FEED->addEntry($sTitle, $sSelfURL, $sAltURL, $sID, $zData['created_by'], $zData['created_date'], $sContributors, $zData['updated_date'], '', 'text', $sContent);
    }



} elseif ($sDataType == 'genes') {
    foreach ($aData as $zData) {
        // Prepare other fields to be included.
        $sTitle = $zData['id'];
        if ($sFeedType == 'feed') {
            $sSelfURL = ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'api/rest.php/genes/' . $zData['id'];
        } else {
            $sSelfURL = '';
        }
        $sChromosome         = $zData['chromosome'];
        $sAltURL             = ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()) . 'genes/' . $zData['id'];
        $sID                 = 'tag:' . $_SERVER['HTTP_HOST'] . ',' . substr($zData['created_date'], 0, 10) . ':' . $zData['id'];
        $sContributors       = '';
        $sContributors .= ($sContributors? ', ' : '') . htmlspecialchars($zData['curators']);
        $sContent = 'id:' . $zData['id'] . "\n" .
                    'entrez_id:' . $zData['id_entrez'] . "\n" .
                    'symbol:' . $zData['id'] . "\n" .
                    'name:' . $zData['name'] . "\n" .
                    'chromosome_location:' . $zData['chromosome'] . $zData['chrom_band'] . "\n" .
                    // In LOVD3, we couldn't get the start and end positions in the correct order because of the multiple transcripts, so they are always in sense. Switch them if necessary.
                    'position_start:chr' . $sChromosome . ':' . ($zData['sense']? $zData['position_g_mrna_start'] : $zData['position_g_mrna_end']) . "\n" .
                    'position_end:chr' . $sChromosome . ':' . ($zData['sense']? $zData['position_g_mrna_end'] : $zData['position_g_mrna_start']) . "\n" .
                    'refseq_genomic:' . $zData['refseq_genomic'] . "\n" .
                    'refseq_mrna:' . $zData['id_ncbi'] . "\n" .
                    'refseq_build:' . $_CONF['refseq_build'];
        $_FEED->addEntry($sTitle, $sSelfURL, $sAltURL, $sID, $zData['created_by'], $zData['created_date'], $sContributors, $zData['updated_date'], '', 'text', $sContent);
    }
}

if (!$n) {
    // This happens if searching on position and there is a partial match. MySQL returns a false positive which has been filtered out now.
    header('HTTP/1.0 404 Not Found'); // This will replace the previous 200 OK status!
}

$_FEED->publish();
?>
