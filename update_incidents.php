<?php
/*
	ndwkaart - matrixbordenkaart
	Copyright (C) 2025 Jasper Vries

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
write_log('update incidents started', $debug);
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

//get publicationtime
$qry = "SELECT `value` FROM `registry` WHERE
`key` = 'incidents_publicationtime'";
$res = mysqli_query($db['link'], $qry);
$last_publicationtime = 0;
if (mysqli_num_rows($res)) {
    $data = mysqli_fetch_row($res);
    $last_publicationtime = $data[0]; 
}

$output = array();

//gzdecode if necessary
//get data
$datex = @file_get_contents($datasource['incidents']);
if ($gzdecode == TRUE) $datex = gzdecode($datex);
//process XML
if ($datex !== FALSE) {
	try {			
		$datex = simplexml_load_string($datex);
		if ($datex !== FALSE) {
			$datex = $datex->children('SOAP', true)->Body->children(); //read soap envelope
			//check publicationtime
			if ($datex->d2LogicalModel->payloadPublication->publicationTime != $last_publicationtime) {
				//get measurement data
				foreach ($datex->d2LogicalModel->payloadPublication->situation as $situation) {
					/*$situation['id']; //key, unused */
					foreach ($situation->situationRecord as $situationRecord) {
						/*$situationRecord['id']; //key
						$situationRecord->attributes('xsi', true)['type']
						$situationRecord->validity->validityStatus;
						$situationRecord->validity->validityTimeSpecification->overallStartTime;
						$situationRecord->groupOfLocations->locationForDisplay->latitude;
						$situationRecord->groupOfLocations->locationForDisplay->longitude;
						$situationRecord->mobilityOfObstruction->mobilityType;
						$situationRecord->vehicleObstructionType;*/
						//type
						$type = (string) $situationRecord->attributes('xsi', true)['type'];
						//prepare output
						$output_this = array(
							'type' => $type,
							'overallStartTime' => (string) $situationRecord->validity->validityTimeSpecification->overallStartTime,
							'lat' => (float) $situationRecord->groupOfLocations->locationForDisplay->latitude,
							'lon' => (float) $situationRecord->groupOfLocations->locationForDisplay->longitude
						);
						if ($type == 'VehicleObstruction') {
							$output_this['mobilityOfObstruction'] = (string) $situationRecord->mobilityOfObstruction->mobilityType;
							$output_this['vehicleObstructionType'] = (string) $situationRecord->vehicleObstructionType;
						}
						elseif ($type == 'Accident') {
							$output_this['accidentType'] = (string) $situationRecord->accidentType;
						}
						elseif ($type == 'GeneralObstruction') {
							$output_this['mobilityOfObstruction'] = (string) $situationRecord->mobilityOfObstruction->mobilityType;
							$output_this['obstructionType'] = (string) $situationRecord->accidentType;
						}
						//add to output
						$output[(string) $situationRecord['id']] = $output_this;
					}
				}
				//update publicationtime in database
				//$output can be empty if there are no incidents
				if (!empty($datex->d2LogicalModel->payloadPublication->publicationTime)) {
					$qry = "INSERT INTO `registry` SET
					`key` = 'incidents_publicationtime',
					`value` = '".mysqli_real_escape_string($db['link'], $datex->d2LogicalModel->payloadPublication->publicationTime)."'
					ON DUPLICATE KEY UPDATE 
					`value` = '".mysqli_real_escape_string($db['link'], $datex->d2LogicalModel->payloadPublication->publicationTime)."'";
					mysqli_query($db['link'], $qry);
				}
			}
		}
	}
	catch (Exception $e) {
		write_log('XML exception:'.$e, $debug);
	}
}

//prepare json
if (isset($datex->d2LogicalModel->payloadPublication->publicationTime)) {
	$json = array();

	//add creation timestamp and servertime-placeholder
	$json = array ('created' => strtotime($datex->d2LogicalModel->payloadPublication->publicationTime), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $output);
	
	//write json
	$json = json_encode($json);
	file_put_contents('json/incidents.json', $json);
	
	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'incidents_created',
	`value` = '".strtotime($datex->d2LogicalModel->payloadPublication->publicationTime)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".strtotime($datex->d2LogicalModel->payloadPublication->publicationTime)."'";
	mysqli_query($db['link'], $qry);

	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'incidents_update',
	`value` = '".round($time_start)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".round($time_start)."'";
	mysqli_query($db['link'], $qry);
	
}

//calculate processing time
$processing_time = round((microtime(TRUE) - $time_start), 1);
write_log('Processing time: ' . $processing_time . ' seconds', $debug);
?>