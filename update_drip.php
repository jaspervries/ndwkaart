<?php
/*
	ndwkaart - matrixbordenkaart
	Copyright (C) 2025-2026 Jasper Vries

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

//change to script dir
chdir(__DIR__);

$debug = FALSE;
$time_start = microtime(TRUE);
include_once('log.inc.php');
write_log('update drip started', $debug);
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

//get publicationtime
$qry = "SELECT `value` FROM `registry` WHERE
`key` = 'drip_publicationtime'";
$res = mysqli_query($db['link'], $qry);
$last_publicationtime = 0;
if (mysqli_num_rows($res)) {
    $data = mysqli_fetch_row($res);
    $last_publicationtime = $data[0]; 
}

$output = array();

//gzdecode if necessary
//get data
$datex = @file_get_contents($datasource['drip']);
if ($gzdecode == TRUE) $datex = @gzdecode($datex);
//process XML
if ($datex !== FALSE) {
	try {
		// Load into SimpleXML
		$xml = @simplexml_load_string($datex);

		// Get declared namespaces (prefix => URI)
		$docNamespaces = $xml->getDocNamespaces(true);

		// Register namespaces for XPath queries
		foreach ($docNamespaces as $prefix => $uri) {
			if ($prefix === '') continue; // default namespace has empty prefix; skip
			$xml->registerXPathNamespace($prefix, $uri);
		}
		
		$publicationTime = (string) $xml->xpath("//mc:payload[@xsi:type='vms:VmsPublication']/com:publicationTime")[0];
		$publicationTime = preg_replace('/\.\d+/', '', $publicationTime); //remove fractional seconds, only 6 decimals supported by strtotime and we don't need them
		if ($publicationTime != $last_publicationtime) {

			// Find all vmsController nodes via XPath
			$controllers = [];
			$controllers = $xml->xpath('//vms:vmsControllerStatus');
			if ($controllers === false) $controllers = [];

			foreach ($controllers as $ctrl) {
				// id and version
				$vmsControllerReference = $ctrl->xpath('.//vms:vmsControllerReference');
				$id = isset($vmsControllerReference[0]['id']) ? (string)$vmsControllerReference[0]['id'] : null;
				$version = isset($vmsControllerReference[0]['version']) ? (string)$vmsControllerReference[0]['version'] : null;
				
				// Find all vmsStatus in vmsControllerStatus nodes via XPath
				$vmses = [];
				$vmses = $ctrl->xpath('.//vms:vmsStatus[not(ancestor::vms:vmsStatus)]');
				if ($vmses === false) $vmses = [];

				foreach ($vmses as $vms) {

					// Use XPath for children in their namespaces to be robust
					$vmsIndex = null;
					$statusUpdateTime = null;
					$workingStatus = null; //blank, working, notWorking
					$timeLastSet = null;
					$imageData = null;
					$imageFormat = null;
					$text = [];

					// Attributes usually available as array access
					$vmsIndex = isset($vms['vmsIndex']) ? (string)$vms['vmsIndex'] : null;

					// vms:statusUpdateTime
					$sutNodes = $vms->xpath(".//vms:statusUpdateTime");
					if ($sutNodes && count($sutNodes) > 0) $statusUpdateTime = trim((string)$sutNodes[0]);

					// vms:workingStatus
					$wNodes = $vms->xpath(".//vms:workingStatus");
					if ($wNodes && count($wNodes) > 0) $workingStatus = trim((string)$wNodes[0]);

					$tNodes = $vms->xpath(".//vms:timeLastSet");
					if ($tNodes && count($tNodes) > 0) $timeLastSet = trim((string)$tNodes[0]);

					$idNodes = $vms->xpath(".//vms:imageData");
					if ($idNodes && count($idNodes) > 0) $imageData = trim((string)$idNodes[0]);

					$itNodes = $vms->xpath(".//vms:imageFormat");
					if ($itNodes && count($itNodes) > 0) $imageFormat = trim((string)$itNodes[0]);

					//textlines
					$textLines = [];
					$textLines = $vms->xpath('.//vms:textLine[not(ancestor::vms:textLine)]');
					if ($textLines === false) $textLines = [];
					foreach ($textLines as $textLine) {
						$innertextLine = $textLine->xpath('.//vms:textLine');
						if ($innertextLine && count($innertextLine) > 0) $text[] = trim((string)$innertextLine[1]);
					}

					//$text = implode(',', $text);
					//echo "id: $id, version: $version, index: $vmsIndex, statusUpdateTime: $statusUpdateTime, workingStatus: $workingStatus, timeLastSet: $timeLastSet, imageFormat: $imageFormat, text: $text\n";
					
					//get vms record from table
					$qry = "SELECT `vmsUnitRecord_id` FROM `driptable`
					WHERE `vmsUnitRecord_id` = '".mysqli_real_escape_string($db['link'], $id)."'
					AND `vmsIndex` = '".mysqli_real_escape_string($db['link'], $vmsIndex)."'
					LIMIT 1";
					$res = mysqli_query($db['link'], $qry);
					if (mysqli_num_rows($res)) {
						$data = mysqli_fetch_assoc($res);
						//add working status
						$output_this = array('w' => (int) ($workingStatus == 'notWorking') ? 0 : 1);
						//check if image and image is png
						if ($imageFormat == 'png') {
							//check if file exists
							$md5 = md5($imageData);
							//echo $md5 . PHP_EOL;
							$filename = 'images/drip/' . substr($md5, 0, 1) . '/' . substr($md5, 1, 1) . '/' . $md5 . '.png';
							$contents = TRUE;
							if (!is_file($filename)) {
								//store file
								$contents = base64_decode($imageData);
								if ($contents) {
									file_put_contents($filename, $contents);
								}
							}
							//prepare output
							$output_this['i'] = (string) $md5;
						}
						//otherwise textline
						else {
							if (!empty($text) && is_array($text)) {
								$output_this['t'] = $text;
							}
						}
						//time last set
						$output_this['u'] = (string) $timeLastSet;
						//add to output
						$output[(string) $id . '_' . $vmsIndex] = $output_this;

					}
					else {
						write_log('Not in vmsTable: ' . $id . ', version: ' . $version . ', vmsIndex: ' . $vmsIndex);
					}
				}
			}
			//update publicationtime in database
			if (!empty($output)) {
				$qry = "INSERT INTO `registry` SET
				`key` = 'drip_publicationtime',
				`value` = '".mysqli_real_escape_string($db['link'], $publicationTime)."'
				ON DUPLICATE KEY UPDATE 
				`value` = '".mysqli_real_escape_string($db['link'], $publicationTime)."'";
				mysqli_query($db['link'], $qry);
			}
		}
		
	}
	catch (Exception $e) {
		write_log('XML exception:'.$e, $debug);
	}
}

//prepare json
if (!empty($output)) {
	$json = array();
	
	//add creation timestamp and servertime-placeholder
	$json = array ('created' => strtotime($publicationTime), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $output);
	
	//write json
	$json = json_encode($json);
	file_put_contents('json/drip.json', $json);
	
	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'drip_created',
	`value` = '".strtotime($publicationTime)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".strtotime($publicationTime)."'";
	mysqli_query($db['link'], $qry);

	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'drip_update',
	`value` = '".round($time_start)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".round($time_start)."'";
	mysqli_query($db['link'], $qry);
	
}

//calculate processing time
$processing_time = round((microtime(TRUE) - $time_start), 1);
write_log('Processing time: ' . $processing_time . ' seconds', $debug);
?>