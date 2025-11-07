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

//check for commandline
if (!($argc >= 1)) {
	echo 'Must be run from CLI';
	exit;
}

$time_start = microtime(TRUE);
$sleep = 0;
include_once('log.inc.php');
write_log('started');
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);


//assetwebsite
$qry = "DROP TABLE IF EXISTS `assetwebsite_new`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}
$qry = "CREATE TABLE `assetwebsite_new` LIKE `assetwebsite`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//get data
write_log('fetch assetwebsite');
$json = @file_get_contents($datasource['assetwebsite']);
if ($json !== FALSE) {
	try {	
		$json = json_decode($json, FALSE, 512, JSON_INVALID_UTF8_SUBSTITUTE);
		foreach ($json as $item) {
			//only import DRIP type
			if ($item->assettypename == 'DRIP') {
				//insert in database
				$qry = "INSERT INTO `assetwebsite_new` SET
				`assetid` = '".mysqli_real_escape_string($db['link'], $item->assetid)."',
				`code` = '".mysqli_real_escape_string($db['link'], $item->code)."',
				`naam` = '".mysqli_real_escape_string($db['link'], $item->naam)."',
				`aansturing` = '".mysqli_real_escape_string($db['link'], $item->aansturing)."',
				`longitude` = '".mysqli_real_escape_string($db['link'],  $item->longitude)."',
				`latitude` = '".mysqli_real_escape_string($db['link'], $item->latitude)."',
				`location` = ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $item->longitude)." ".mysqli_real_escape_string($db['link'], $item->latitude).")'),
				`heading` = '".mysqli_real_escape_string($db['link'], $item->heading)."',
				`type` = '".mysqli_real_escape_string($db['link'], $item->type)."'";
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
else {
	write_log('no data, kept previous');
}
//find number of rows in current and new table
$installnewtable = TRUE;
$qry = "SELECT (SELECT COUNT(*) FROM `assetwebsite`), (SELECT COUNT(*) FROM `assetwebsite_new`)";
$res = mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}
else {
	$row = mysqli_fetch_row($res);
	//if new table is less, only allow 10% difference
	if (($row[0] > $row[1]) && ($row[1] / $row[0] < 0.9)) {
		write_log('new table rows too less. old ' . $row[0] . ' new ' . $row[1]);
		$installnewtable = FALSE;
	}
}

//install new table
if ($installnewtable == TRUE) {
	$qry = "DROP TABLE IF EXISTS `assetwebsite_old`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}
	$qry = "ALTER TABLE `assetwebsite` RENAME `assetwebsite_old`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}
	$qry = "ALTER TABLE `assetwebsite_new` RENAME `assetwebsite`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}
}

//NDW
//create new table
$qry = "DROP TABLE IF EXISTS `driptable_new`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}
$qry = "CREATE TABLE `driptable_new` LIKE `driptable`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//get data
write_log('update driptable');
$output = array();
$datex = @file_get_contents($datasource['driptable']);
if ($gzdecode == TRUE) $datex = gzdecode($datex);
//process XML
if ($datex !== FALSE) {
	try {			
		$datex = simplexml_load_string($datex);
		if ($datex !== FALSE) {
			$datex = $datex->children('SOAP', true)->Body->children(); //read soap envelope
			//TODO: $datex->d2LogicalModel->payloadPublication->publicationTime
			//get measurement data
			foreach ($datex->d2LogicalModel->payloadPublication->vmsUnitTable->vmsUnitRecord as $vmsUnitRecord) {
				
				if (!empty($vmsUnitRecord['id'])) {
					/*$vmsUnitRecord['id']; //key
					$vmsUnitRecord['version'];*/

					foreach ($vmsUnitRecord->vmsRecord as $vmsRecord) {
						/*$vmsRecord['vmsIndex']; //key
						$vmsRecord->vmsRecord->vmsDescription->values->value;
						$vmsRecord->vmsRecord->vmsPhysicalMounting;
						$vmsRecord->vmsRecord->vmsType;
						$vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude;
						$vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude;
						$vmsRecord->vmsRecord->vmsLocation->supplementaryPositionalDescription->affectedCarriagewayAndLanes->carriageway;
						$bearing; //te bepalen uit dichtstbijzijnde DRIP van assetwebsite*/

						//check if latitude and longitude are available
						if (isset($vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude) && isset($vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude)) {

							//insert in database
							$qry = "INSERT INTO `driptable_new` SET
							`vmsUnitRecord_id` = '".mysqli_real_escape_string($db['link'], $vmsUnitRecord['id'])."',
							`vmsUnitRecord_version` = '".mysqli_real_escape_string($db['link'], $vmsUnitRecord['version'])."',
							`vmsIndex` = '".mysqli_real_escape_string($db['link'], $vmsRecord['vmsIndex'])."',
							`vmsDescription` = '".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsDescription->values->value)."',
							`vmsPhysicalMounting` = ". (
								isset($vmsRecord->vmsRecord->vmsPhysicalMounting)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsPhysicalMounting) . "'"
									: 'NULL'
								) .",
							`vmsType` = ". (
								isset($vmsRecord->vmsRecord->vmsType)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsType) . "'"
									: 'NULL'
								) .",
							`longitude` = '".mysqli_real_escape_string($db['link'],  $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude)."',
							`latitude` = '".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude)."',
							`location` = ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude)." ".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude).")'),
							`carriageway` = ". (
								isset($vmsRecord->vmsRecord->vmsLocation->supplementaryPositionalDescription->affectedCarriagewayAndLanes->carriageway)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->supplementaryPositionalDescription->affectedCarriagewayAndLanes->carriageway) . "'"
									: 'NULL'
								) .",
							`bearing` = NULL";
							/*ON DUPLICATE KEY UPDATE
							`vmsUnitRecord_version` = '".mysqli_real_escape_string($db['link'], $vmsUnitRecord['version'])."',
							`vmsDescription` = '".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsDescription->values->value)."',
							`vmsPhysicalMounting` = ". (
								isset($vmsRecord->vmsRecord->vmsPhysicalMounting)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsPhysicalMounting) . "'"
									: 'NULL'
								) .",
							`vmsType` = ". (
								isset($vmsRecord->vmsRecord->vmsType)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsType) . "'"
									: 'NULL'
								) .",
							`longitude` = '".mysqli_real_escape_string($db['link'],  $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude)."',
							`latitude` = '".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude)."',
							`location` = ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude)." ".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude).")'),
							`carriageway` = ". (
								isset($vmsRecord->vmsRecord->vmsLocation->supplementaryPositionalDescription->affectedCarriagewayAndLanes->carriageway)
									? "'" . mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->supplementaryPositionalDescription->affectedCarriagewayAndLanes->carriageway) . "'"
									: 'NULL'
								) .",
							`bearing` = NULL";*/
							//echo $qry; exit;
							mysqli_query($db['link'], $qry);
							if (mysqli_errno($db['link'])) {
								write_log(mysqli_error($db['link']));
							}
							else {
								//get details from assetwebsite
								$use_assetwebsite = FALSE;
								$qry = "SELECT `code`, `naam`, `aansturing`, `latitude` ,`longitude`, `location`, `heading`, `type`, ST_Distance_Sphere(`location`, ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude)." ".mysqli_real_escape_string($db['link'], $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude).")')) AS `distance` 
								FROM `assetwebsite`
								ORDER BY `distance` ASC
								LIMIT 1";
								$res = mysqli_query($db['link'], $qry);
								if (mysqli_num_rows($res)) {
									$data = mysqli_fetch_assoc($res);
									if ($data['distance'] <= 50) {
										$use_assetwebsite = TRUE;
									}
								}

								//add to output
								if ($use_assetwebsite == TRUE) {
									//use assetwebsite data
									$output[] = array(
										'id' => (string) $vmsUnitRecord['id'] . '_' . $vmsRecord['vmsIndex'],
										'dsc' => (string) $vmsRecord->vmsRecord->vmsDescription->values->value,
										'lon' => (float) $data['longitude'],
										'lat' => (float) $data['latitude'],
										'rot' => $data['heading'],
										'cd' => $data['code'],
										'nm' => $data['naam'],
										'as' => $data['aansturing'],
										'tp' => $data['type']
									);
								}
								else {
									//no assetwebsite match found
									$output[] = array(
										'id' => (string) $vmsUnitRecord['id'] . '_' . $vmsRecord['vmsIndex'],
										'dsc' => (string) $vmsRecord->vmsRecord->vmsDescription->values->value,
										'lon' => (float) $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->longitude,
										'lat' => (float) $vmsRecord->vmsRecord->vmsLocation->locationForDisplay->latitude,
										'rot' => null,
										'cd' => '',
										'nm' => '',
										'as' => '',
										'tp' => ''
									);
								}
							}
						}
						else {
							write_log('latlng missing for ' . $vmsUnitRecord['id'] . ' (' . $vmsRecord->vmsRecord->vmsDescription->values->value . ')');
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

//find number of rows in current and new table
$installnewtable = TRUE;
$qry = "SELECT (SELECT COUNT(*) FROM `driptable`), (SELECT COUNT(*) FROM `driptable_new`)";
$res = mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}
else {
	$row = mysqli_fetch_row($res);
	//if new table is less, only allow 10% difference
	if (($row[0] > $row[1]) && ($row[1] / $row[0] < 0.9)) {
		write_log('new table rows too less. old ' . $row[0] . ' new ' . $row[1]);
		$installnewtable = FALSE;
	}
}

//install new table
if ($installnewtable == TRUE) {
	$qry = "DROP TABLE IF EXISTS `driptable_old`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}
	$qry = "ALTER TABLE `driptable` RENAME `driptable_old`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}
	$qry = "ALTER TABLE `driptable_new` RENAME `driptable`";
	mysqli_query($db['link'], $qry);
	if (mysqli_errno($db['link'])) {
		write_log(mysqli_error($db['link']));
	}

	//prepare json
	if (!empty($output)) {
		$json = array();

		//add creation timestamp and servertime-placeholder
		$json = array ('created' => round($time_start), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $output);
		
		//write json
		$json = json_encode($json);
		file_put_contents('json/driptable.json', $json);
		
		//update registry
		$qry = "INSERT INTO `registry` SET
		`key` = 'driptable_created',
		`value` = '".round($time_start)."'
		ON DUPLICATE KEY UPDATE 
		`value` = '".round($time_start)."'";
		mysqli_query($db['link'], $qry);
		
	}

	write_log('new driptable installed');
}

//calculate processing time
write_log('Processing time: ' . round((microtime(TRUE) - $time_start), 1) . ' seconds');

?>