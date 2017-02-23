<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2016-12-05
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Kris Potempa <dont.contact.me@on.this>
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


    function curl_get_contents($url)
    {
        $ch = curl_init($url);
        
        if (FALSE === $ch)
            throw new Exception('failed to initialize');
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        #TODO: Throw up on this.
        #curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        
        # Note that by default curl doesn't provide a certificate list so this might die here. Still, better safe than sorry.
        # https://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-certificatecertificate-verify-failed-ca-is-ok
        if (FALSE === $data)
            throw new Exception(curl_error($ch), curl_errno($ch));
        
        curl_close($ch);
        return $data;
    }

    function swap_if_second_smaller(&$x,&$y) 
    {
        if ($y < $x)
        {
            $tmp=$x;
            $x=$y;
            $y=$tmp;
        }
    }
    
    
    #https://mutalyzer.nl/json/getTranscriptsAndInfo?genomicReference=UD_132084538585
    function get_mutalyzer_transcript_info($gene, $genomicReference)
    {
        $target_url = "https://mutalyzer.nl/json/getTranscriptsAndInfo?genomicReference=$genomicReference";
        $content = curl_get_contents($target_url);
        
        $response = json_decode($content, TRUE);
        $target_prefix = $gene . '_';
        $output = array();
        $target_key = FALSE;
        
        foreach ($response as $key => $value)
        {
            if (strncmp($value['name'], $target_prefix, strlen($target_prefix)) === 0)
            {
                $target_key = $key;
                break;
            }
        }
        
        if ($target_key === FALSE)
            return FALSE;
            
        $target_resp = $response[$target_key];                
        
        $output['utr3'] = array('start' => $target_resp['chromCDSStart'], 'stop' => $target_resp['chromTransStart']);
        swap_if_second_smaller($output['utr3']['start'], $output['utr3']['stop']);
        
        $output['utr5'] = array('start' => $target_resp['chromTransEnd'], 'stop' => $target_resp['chromCDSStop']);
        swap_if_second_smaller($output['utr5']['start'], $output['utr5']['stop']);
        
        $output['exons'] = array();
        
        foreach ($target_resp['exons'] as $exon)
        {
            $exon_out = array('start' => $exon['chromStart'], 'stop' => $exon['chromStop']);
            swap_if_second_smaller($exon_out['start'], $exon_out['stop']); 
            $output['exons'][] = $exon_out;
        }
          
        #It's a disgrace. Sad!                                                                                  
        $start_val = array();
        foreach ($output['exons'] as $key => $row)
        {
            $start_val[$key] = $row['start'];
        }
        array_multisort($start_val, SORT_ASC, $output['exons']);
        
        return $output;    
    }
    
    function get_transcript_gene($transcript)
    {
        global $_DB;
        $transcript_quoted = $_DB->quote($transcript);
        $gene_id_query = 'SELECT geneId FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ' . $transcript_quoted . ' LIMIT 1';
        
        $geneName = $_DB->query($gene_id_query)->fetchColumn();
        if (!$geneName)
            return FALSE;
            
        return $geneName;
    }
    
    function get_refseq_from_gene($gene)
    {
        global $_DB;
        $gene_quoted = $_DB->quote($gene);
        $gene_id_query = 'SELECT refseq_UD FROM ' . TABLE_GENES . ' WHERE id = ' . $gene_quoted . ' LIMIT 1';
        
        $refseq_UD = $_DB->query($gene_id_query)->fetchColumn();
        if (!$refseq_UD)
            return FALSE;
            
        return $refseq_UD;
    }
    
	function get_variants($transcript)
    {
        global $_DB;
        $transcript_quoted = $_DB->quote($transcript);
            $query = "SELECT " . TABLE_TRANSCRIPTS . ".id, " . TABLE_VARIANTS . ".id, " . TABLE_VARIANTS . ".type, "
                           . TABLE_VARIANTS . ".position_g_start, " . TABLE_VARIANTS . ".position_g_end, " . TABLE_VARIANTS_ON_TRANSCRIPTS . ".`VariantOnTranscript/Protein`
                    FROM
                      " . TABLE_TRANSCRIPTS . "
                    JOIN " . TABLE_VARIANTS_ON_TRANSCRIPTS . " ON " . TABLE_TRANSCRIPTS .".id = " . TABLE_VARIANTS_ON_TRANSCRIPTS . ".transcriptid
                    JOIN " . TABLE_VARIANTS . " ON " . TABLE_VARIANTS . ".id=" . TABLE_VARIANTS_ON_TRANSCRIPTS . ".id
                    WHERE
                      " . TABLE_TRANSCRIPTS . ".id_ncbi = " . $transcript_quoted;
        
         $result = array();
        
        foreach ($_DB->query($query)->fetchAll() as $variant)
        {
            $partial_result = array('start' => (int)$variant['position_g_start'],
                                   'stop' => (int)$variant['position_g_end'],
                                   'type' => $variant['type'],
                                   'url' => (int)$variant[1] . '#' . (int)$variant[0],
                                   'frameshift' => (strpos($variant['VariantOnTranscript/Protein'], 'fs*') !== false) ? True : False);
            
            if (!$partial_result['start'] or !$partial_result['type'])
                continue;
            
            swap_if_second_smaller($partial_result['start'], $partial_result['stop']);
            $result[] = $partial_result;
        }
		
		#It's a disgrace. Sad!                                                                                  
        $start_val = array();
        foreach ($result as $key => $row)
        {
            $start_val[$key] = $row['start'];
        }
        array_multisort($start_val, SORT_ASC, $result);
        
        return $result;
    }
    
    
    if (!isset($_GET['transcript']))
        die();
        
    
    $transcript = $_GET['transcript'];
    $gene = get_transcript_gene($transcript);
    
    if (!$gene)
        die();
        
    $refseq_UD = get_refseq_from_gene($gene);
    $output = get_mutalyzer_transcript_info($gene, $refseq_UD);
    
    #echo(json_encode($partial_output))
    $output['changes'] = get_variants($transcript);
    echo(json_encode($output));
    
?>