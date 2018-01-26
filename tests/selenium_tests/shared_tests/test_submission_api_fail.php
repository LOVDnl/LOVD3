<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-27
 * Modified    : 2017-12-06
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
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

require_once 'LOVDSeleniumBaseTestCase.php';


class SubmissionApiFailTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSubmissionApiFail()
    {
        // Test trying to perform a submission through the API while using
        // an invalid authorization token.

        // Requite inc-init.php to get global status array.
        list(,$aStatus) = getLOVDGlobals();

        $sSubmissionFile = ROOT_PATH . '../tests/test_data_files/submission_api_request_content.json';
        $aSub = json_decode(file_get_contents($sSubmissionFile), true);

        // Set some fields in the request body to make it almost acceptable (except for token)
        $aSub['lsdb']['@id'] = md5($aStatus['signature']);
        foreach ($aSub['lsdb']['source']['contact']['db_xref'] as $k => $xref) {
            if ($xref['@source'] == 'lovd_auth_token') {
                // Set dummy token.
                $aSub['lsdb']['source']['contact']['db_xref'][$k]['@accession'] = 'nonsense_token';
            } elseif ($xref['@source'] == 'lovd') {
                // Set user id of admin user.
                $aSub['lsdb']['source']['contact']['db_xref'][$k]['@accession'] = '1';
            }
        }

        // Send POST request with submission.
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => json_encode($aSub),
                'ignore_errors' => true,
            )
        );
        $context  = stream_context_create($options);
        $sResult = file_get_contents(ROOT_URL . '/src/api/submissions', false, $context);
        $aResult = json_decode($sResult, true);

        // Check for authentication error in message body.
        $this->assertContains('Authentication denied', $aResult['errors'][0]);

        // Check for correct HTTP response code in returned headers.
        // Note: $http_response_header is magically filled by
        // file_get_contents().
        $this->assertContains('401 Unauthorized', $http_response_header[0]);
    }
}
