<?php
/*
*    openrouteserver - Open source NDW route configurator en server
*    Copyright (C) 2014,2017 Jasper Vries
*
*    This program is free software; you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation; either version 2 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License along
*    with this program; if not, write to the Free Software Foundation, Inc.,
*    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

//check for commandline
if (!($argc >= 1)) {
	echo 'Must be run from CLI';
	exit;
}

$time_start = microtime(TRUE);
include_once('log.inc.php');
write_log('started');
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);
set_time_limit(0); 
ini_set("memory_limit","1G");

$qry = "UPDATE `traveltime` SET
`current` = 0";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//get data
write_log('update MST');
$datex = @file_get_contents($datasource['mst']);
if ($gzdecode == TRUE) $datex = gzdecode($datex);
//process XML
if ($datex !== FALSE) {
	try {			
		$datex = simplexml_load_string($datex);
		if ($datex !== FALSE) {
			$datex = $datex->children('SOAP', true)->Body->children(); //read soap envelope
			//get measurement data
			foreach ($datex->d2LogicalModel->payloadPublication->measurementSiteTable->measurementSiteRecord as $measurementSiteRecord) {
				
				if ($measurementSiteRecord->measurementSpecificCharacteristics->measurementSpecificCharacteristics->specificMeasurementValueType == 'travelTimeInformation') {
					$segmentlength = 0;
					$coordinates = '';
					$segmentid = $measurementSiteRecord['id'];
					$segmentname = $measurementSiteRecord->measurementSiteName->values->value;
					$equipment = $measurementSiteRecord->measurementEquipmentTypeUsed->values->value;
					$lat_min = 999;
					$lat_max = 0;
					$lon_min = 999;
					$lon_max = 0;
					
					foreach ($measurementSiteRecord->measurementSiteLocation->locationContainedInItinerary as $locationContainedInItinerary) {
						foreach ($locationContainedInItinerary->location->supplementaryPositionalDescription->affectedCarriagewayAndLanes as $affectedCarriagewayAndLanes) {
							if ($affectedCarriagewayAndLanes->lengthAffected > 0) {
								$segmentlength += $affectedCarriagewayAndLanes->lengthAffected;
							}
						}
						$coordinates .= $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->latitude.','.$locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->longitude.'|';
						$coordinates .= $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->latitude.','.$locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->longitude.'|';
						
						$lat_min = min($lat_min, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->latitude, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->latitude);
						$lat_max = max($lat_max, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->latitude, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->latitude);
						$lon_min = min($lon_min, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->longitude, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->longitude);
						$lon_max = max($lon_max, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesStartPoint->pointCoordinates->longitude, $locationContainedInItinerary->location->linearExtension->linearByCoordinatesExtension->linearCoordinatesEndPoint->pointCoordinates->longitude);
					}
					if ($segmentlength > 0) {
						$coordinates = trim($coordinates, '|');
						//insert in database
						$qry = "INSERT INTO `traveltime` SET
						`id` = '".mysqli_real_escape_string($db['link'], $segmentid)."',
						`name` = '".mysqli_real_escape_string($db['link'], $segmentname)."',
						`current` = 1,
						`length` = '".mysqli_real_escape_string($db['link'], $segmentlength)."',
						`path` = '".mysqli_real_escape_string($db['link'], $coordinates)."',
						`lat_min` = '".mysqli_real_escape_string($db['link'], $lat_min)."',
						`lat_max` = '".mysqli_real_escape_string($db['link'], $lat_max)."',
						`lon_min` = '".mysqli_real_escape_string($db['link'], $lon_min)."',
						`lon_max` = '".mysqli_real_escape_string($db['link'], $lon_max)."',
						`equipment` = '".mysqli_real_escape_string($db['link'], $equipment)."'
						ON DUPLICATE KEY UPDATE
						`name` = '".mysqli_real_escape_string($db['link'], $segmentname)."',
						`current` = 1,
						`length` = '".mysqli_real_escape_string($db['link'], $segmentlength)."',
						`path` = '".mysqli_real_escape_string($db['link'], $coordinates)."',
						`lat_min` = '".mysqli_real_escape_string($db['link'], $lat_min)."',
						`lat_max` = '".mysqli_real_escape_string($db['link'], $lat_max)."',
						`lon_min` = '".mysqli_real_escape_string($db['link'], $lon_min)."',
						`lon_max` = '".mysqli_real_escape_string($db['link'], $lon_max)."',
						`equipment` = '".mysqli_real_escape_string($db['link'], $equipment)."'";
						mysqli_query($db['link'], $qry);
						if (mysqli_errno($db['link'])) {
							write_log(mysqli_error($db['link']));
						}
					}
				}
			}
		}
	}
	catch (Exception $e) {
		write_log('XML exception: '.$e);
	}
}
else {
	write_log('no data');
}
write_log('MST complete');

//KML
if (is_file('content/traveltime.kml')) {
	write_log('update KML');
	$kml = @file_get_contents('content/traveltime.kml');
	if ($kml !== FALSE) {
		try {
			$kml = simplexml_load_string($kml);
			foreach($kml->Document->Folder as $folder) {
				//doesn't support subfolders, there shouldn't be any
				foreach ($folder->Placemark as $placemark) {
					$id = $placemark->name;
					$coordinates = $placemark->LineString->coordinates;
					$coordinates = trim($coordinates);
					//google earth coordinates are other way round, needs switching
					$coordinates = preg_split('/\s+/', $coordinates);
					$new_coordinates = array();
					$lat_min = 999;
					$lat_max = 0;
					$lon_min = 999;
					$lon_max = 0;
					foreach ($coordinates as $coordinate) {
						preg_match('/(\d+\.\d+){1},(\d+\.\d+){1}/', $coordinate, $matches);
						$new_coordinates[] = $matches[2] . ',' . $matches[1];
						$lat_min = min($lat_min, $matches[2]);
						$lat_max = max($lat_max, $matches[2]);
						$lon_min = min($lon_min, $matches[1]);
						$lon_max = max($lon_max, $matches[1]);
					}
					$new_coordinates = join('|', $new_coordinates);
					$qry = "UPDATE `traveltime` SET
					`path` = '".mysqli_real_escape_string($db['link'], $new_coordinates)."',
					`path_source` = 'shape',
					`lat_min` = '".mysqli_real_escape_string($db['link'], $lat_min)."',
					`lat_max` = '".mysqli_real_escape_string($db['link'], $lat_max)."',
					`lon_min` = '".mysqli_real_escape_string($db['link'], $lon_min)."',
					`lon_max` = '".mysqli_real_escape_string($db['link'], $lon_max)."'
					WHERE
					`id` = '".mysqli_real_escape_string($db['link'], $id)."'";
					mysqli_query($db['link'], $qry);
					if (mysqli_errno($db['link'])) {
						write_log(mysqli_error($db['link']));
					}
				}
			}
		}
		catch (Exception $e) {
		write_log('XML exception: '.$e);
		}
	}
	write_log('KML complete');
}
else {
	write_log('Cannot import KML because file content/traveltime.kml is not there');
}

//delete outdated entries
$qry = "DELETE FROM `traveltime` WHERE
`current` = 0";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//calculate processing time
write_log('Processing time: ' . round((microtime(TRUE) - $time_start), 1) . ' seconds');
?>