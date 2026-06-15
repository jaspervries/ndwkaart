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

if (!isset($layer)) {
	$layer = 'sit';
}

$debug = FALSE;
$time_start = microtime(TRUE);
include_once('log.inc.php');
write_log('update ' . $layer . ' started', $debug);
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

//get publicationtime
$qry = "SELECT `value` FROM `registry` WHERE
`key` = '" . $layer . "_publicationtime'";
$res = mysqli_query($db['link'], $qry);
$last_publicationtime = 0;
if (mysqli_num_rows($res)) {
    $data = mysqli_fetch_row($res);
    $last_publicationtime = $data[0]; 
}

$output = array();

//gzdecode if necessary
//get data
$datex = @file_get_contents($datasource[$layer]);
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
		
		$publicationTime = $xml->xpath("//mc:payload[@xsi:type='sit:SituationPublication']/com:publicationTime");
		$publicationTime = preg_replace('/\.\d+/', '', $publicationTime[0]); //remove fractional seconds, only 6 decimals supported by strtotime and we don't need them

		//check publicationtime
		if ($publicationTime != $last_publicationtime) {

			// Find all situation nodes via XPath
			$situations = [];
			$situations = $xml->xpath('//sit:situation');
			if ($situations === false) $situations = [];

			foreach ($situations as $situation) {
				//id
				$id = (string)$situation['id'];

				$output_this = [];

				//overallStartTime
				$Nodes = $situation->xpath(".//com:overallStartTime");
				if ($Nodes && count($Nodes) > 0) $output_this['overallStartTime'] = trim((string)$Nodes[0]);

				//location/latitude
				$Nodes = $situation->xpath(".//sit:locationReference[@xsi:type='loc:PointLocation']/loc:pointByCoordinates/loc:pointCoordinates/loc:latitude");
				if ($Nodes && count($Nodes) > 0) $output_this['lat'] = (float)$Nodes[0];

				//location/longitude
				$Nodes = $situation->xpath(".//sit:locationReference[@xsi:type='loc:PointLocation']/loc:pointByCoordinates/loc:pointCoordinates/loc:longitude");
				if ($Nodes && count($Nodes) > 0) $output_this['lon'] = (float)$Nodes[0];

				//if there is no location, don't bother as we cannot display it anyways
				if (isset($output_this['lat']) && isset($output_this['lon'])) {

					//location/bearing
					$Nodes = $situation->xpath(".//sit:locationReference[@xsi:type='loc:PointLocation']/loc:pointByCoordinates/loc:bearing");
					if ($Nodes && count($Nodes) > 0) $output_this['bearing'] = (int)$Nodes[0];

					//location/carriageway
					$Nodes = $situation->xpath(".//sit:locationReference[@xsi:type='loc:PointLocation']/loc:supplementaryPositionalDescription/loc:carriageway/loc:carriageway");
					if ($Nodes && count($Nodes) > 0) $output_this['carriageway'] = (string)$Nodes[0];

					//situationRecord/type
					$situationRecord = $situation->xpath(".//sit:situationRecord");
					$type = (string)$situationRecord[0]->attributes('xsi', true)['type'];
					$output_this['type'] = substr($type, 4, strlen($type) - 4);

					//subtype depending on type
					switch ($type) {
						case 'sit:WeatherRelatedRoadConditions':
							$subtypeobj = 'sit:weatherRelatedRoadConditionType';
							break;
						case 'sit:NonWeatherRelatedRoadConditions':
							$subtypeobj = 'sit:nonWeatherRelatedRoadConditionType';
							break;
						case 'sit:GeneralObstruction':
							$subtypeobj = 'sit:obstructionType';
							break;
						case 'sit:EnvironmentalObstruction':
							$subtypeobj = 'sit:environmentalObstructionType';
							break;
						case 'sit:AnimalPresenceObstruction':
							$subtypeobj = 'sit:animalPresenceType';
							break;
						case 'sit:VehicleObstruction':
							$subtypeobj = 'sit:vehicleObstructionType';
							break;
						case 'sit:Accident':
							$subtypeobj = 'sit:accidentType';
							break;
						case 'sit:MaintenanceWorks':
							$subtypeobj = 'sit:roadMaintenanceType';
							break;
						case 'sit:PoorEnvironmentConditions':
							$subtypeobj = 'sit:poorEnvironmentType';
							break;
						case 'sit:GeneralNetworkManagement':
							$subtypeobj = 'sit:generalNetworkManagementType';
							break;
						case 'sit:RoadOrCarriagewayOrLaneManagement':
							$subtypeobj = 'sit:roadOrCarriagewayOrLaneManagementType';
							break;
						case 'sit:SpeedManagement':
							$subtypeobj = 'sit:speedManagementType';
							break;
						default:
							$subtypeobj = null;
					}

					if ($subtypeobj) {
						$Nodes = $situation->xpath(".//" . $subtypeobj);
						if ($Nodes && count($Nodes) > 0) $output_this['subtype'] = trim((string)$Nodes[0]);
					}

					//mobilityOfObstruction
					$Nodes = $situation->xpath(".//sit:mobilityOfObstruction/sit:mobilityType");
					if ($Nodes && count($Nodes) > 0) $output_this['mobilityOfObstruction'] = trim((string)$Nodes[0]);

					//generalPublicComment
					$Nodes = $situation->xpath(".//sit:generalPublicComment/sit:comment/com:values/com:value");
					if ($Nodes && count($Nodes) > 0) $output_this['generalPublicComment'] = trim((string)$Nodes[0]);

					//operatorActionStatus
					$Nodes = $situation->xpath(".//sit:operatorActionStatus");
					if ($Nodes && count($Nodes) > 0) $output_this['operatorActionStatus'] = trim((string)$Nodes[0]);

					//temporarySpeedLimit
					$Nodes = $situation->xpath(".//sit:temporarySpeedLimit");
					if ($Nodes && count($Nodes) > 0) $output_this['temporarySpeedLimit'] = trim((string)$Nodes[0]);

					//add to output
					$output[$id] = $output_this;
				}
			}
			//update publicationtime in database
			//$output can be empty if there are no incidents
			if (!empty($publicationTime)) {
				$qry = "INSERT INTO `registry` SET
				`key` = '" . $layer . "_publicationtime',
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
if ($publicationTime != $last_publicationtime) {
	$json = array();

	//add creation timestamp and servertime-placeholder
	$json = array ('created' => strtotime($publicationTime), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $output);
	
	//write json
	$json = json_encode($json);
	file_put_contents('json/' . $layer . '.json', $json);
	
	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = '" . $layer . "_created',
	`value` = '".strtotime($publicationTime)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".strtotime($publicationTime)."'";
	mysqli_query($db['link'], $qry);

	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = '" . $layer . "_update',
	`value` = '".round($time_start)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".round($time_start)."'";
	mysqli_query($db['link'], $qry);
	
}

//calculate processing time
$processing_time = round((microtime(TRUE) - $time_start), 1);
write_log('Processing time: ' . $processing_time . ' seconds', $debug);
?>