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
$datex = @file_get_contents($datasource['drip']);
if ($gzdecode == TRUE) $datex = gzdecode($datex);
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

		// Find all vmsController nodes via XPath
		$controllers = [];
		$controllers = $xml->xpath('//vms:vmsController');
		if ($controllers === false) $controllers = [];

		foreach ($controllers as $ctrl) {
			// Attributes usually available as array access
			$id = isset($ctrl['id']) ? (string)$ctrl['id'] : null;
			$version = isset($ctrl['version']) ? (string)$ctrl['version'] : null;

			// Find all vms in vmsController nodes via XPath
			$vmses = [];
			$vmses = $ctrl->xpath('.//vms:vms[not(ancestor::vms:vms)]');
			if ($vmses === false) $vmses = [];

			foreach ($vmses as $vms) {

				// Use XPath for children in their namespaces to be robust
				$vmsIndex = null;
				$description = null;
				$bearing = null;
				$latitude = null;
				$longitude = null;
				$physicalSupport = null;
				$vmsType = null;
				$carriageway = null;

				// Attributes usually available as array access
				$vmsIndex = isset($vms['vmsIndex']) ? (string)$vms['vmsIndex'] : null;

				// description com:value — search descendants (keep within this controller->vms)
				$valNodes = $vms->xpath(".//com:value");
				if ($valNodes && count($valNodes) > 0) $description = trim((string)$valNodes[0]);

				// loc:bearing, loc:latitude, loc:longitude
				$bNodes = $vms->xpath(".//loc:bearing");
				if ($bNodes && count($bNodes) > 0) $bearing = trim((string)$bNodes[0]);

				$latNodes = $vms->xpath(".//loc:latitude");
				if ($latNodes && count($latNodes) > 0) $latitude = trim((string)$latNodes[0]);

				$lonNodes = $vms->xpath(".//loc:longitude");
				if ($lonNodes && count($lonNodes) > 0) $longitude = trim((string)$lonNodes[0]);

				// vms:physicalSupport
				$psNodes = $vms->xpath(".//vms:physicalSupport");
				if ($psNodes && count($psNodes) > 0) $physicalSupport = trim((string)$psNodes[0]);

				// vms:vmsType
				$typeNodes = $vms->xpath(".//vms:vmsType");
				if ($typeNodes && count($typeNodes) > 0) $vmsType = trim((string)$typeNodes[0]);

				// loc:carriageway — assume one inner carriageway inside the outer node
				$outer = $vms->xpath(".//loc:carriageway");
				if ($outer && count($outer) > 0) {
					// try to get the nested carriageway value first
					$inner = $outer[0]->xpath(".//loc:carriageway");
					if ($inner && count($inner) > 0) {
						$carriageway = trim((string)$inner[0]);
					} else {
						// fallback to outer node's text
						$carriageway = trim((string)$outer[0]);
					}
				}

				//echo "id: $id, version: $version, index:$vmsIndex, value: $description, bearing: $bearing, lat: $latitude, lon: $longitude, physicalSupport: $physicalSupport, vmsType: $vmsType, carriageway: $carriageway\n";

				//check if latitude and longitude are available
				if (isset($latitude) && isset($longitude)) {

					//insert in database
					$qry = "INSERT INTO `driptable_new` SET
					`vmsUnitRecord_id` = '".mysqli_real_escape_string($db['link'], $id)."',
					`vmsUnitRecord_version` = '".mysqli_real_escape_string($db['link'], $version)."',
					`vmsIndex` = '".mysqli_real_escape_string($db['link'], $vmsIndex)."',
					`vmsDescription` = '".mysqli_real_escape_string($db['link'], $description)."',
					`vmsPhysicalMounting` = ". (
						isset($physicalSupport)
							? "'" . mysqli_real_escape_string($db['link'], $physicalSupport) . "'"
							: 'NULL'
						) .",
					`vmsType` = ". (
						isset($vmsType)
							? "'" . mysqli_real_escape_string($db['link'], $vmsType) . "'"
							: 'NULL'
						) .",
					`longitude` = '".mysqli_real_escape_string($db['link'],  $longitude)."',
					`latitude` = '".mysqli_real_escape_string($db['link'], $latitude)."',
					`location` = ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $longitude)." ".mysqli_real_escape_string($db['link'], $latitude).")'),
					`carriageway` = ". (
						isset($carriageway)
							? "'" . mysqli_real_escape_string($db['link'], $carriageway) . "'"
							: 'NULL'
						) .",
					`bearing` = '".mysqli_real_escape_string($db['link'],  $bearing)."'";
					//echo $qry; exit;
					mysqli_query($db['link'], $qry);
					if (mysqli_errno($db['link'])) {
						write_log(mysqli_error($db['link']));
					}
					else {
						//get details from assetwebsite
						$use_assetwebsite = FALSE;
						$qry = "SELECT `code`, `naam`, `aansturing`, `latitude` ,`longitude`, `location`, `heading`, `type`, ST_Distance_Sphere(`location`, ST_PointFromText('POINT(".mysqli_real_escape_string($db['link'], $longitude)." ".mysqli_real_escape_string($db['link'], $latitude).")')) AS `distance` 
						FROM `assetwebsite`
						ORDER BY `distance` ASC
						LIMIT 1";
						$res = mysqli_query($db['link'], $qry);
						if (mysqli_errno($db['link'])) {
							write_log(mysqli_error($db['link']));
						}
						elseif (mysqli_num_rows($res)) {
							$data = mysqli_fetch_assoc($res);
							if ($data['distance'] <= 50) {
								$use_assetwebsite = TRUE;
							}
						}

						//add to output
						if ($use_assetwebsite == TRUE) {
							//use assetwebsite data
							$output[] = array(
								'id' => (string) $id . '_' . $vmsIndex,
								'dsc' => (string) $description,
								'lon' => (float) $data['longitude'],
								'lat' => (float) $data['latitude'],
								'rot' => $data['heading'],
								'ps' => (string) $physicalSupport,
								'vt' => (string) $vmsType,
								'cw' => (string) $carriageway,
								'cd' => $data['code'],
								'nm' => $data['naam'],
								'as' => $data['aansturing'],
								'tp' => $data['type']
							);
						}
						else {
							//no assetwebsite match found
							$output[] = array(
								'id' => (string) $id . '_' . $vmsIndex,
								'dsc' => (string) $description,
								'lon' => (float) $longitude,
								'lat' => (float) $latitude,
								'rot' => (int) $bearing,
								'ps' => (string) $physicalSupport,
								'vt' => (string) $vmsType,
								'cw' => (string) $carriageway,
								'cd' => '',
								'nm' => '',
								'as' => '',
								'tp' => ''
							);
						}
					}
				}
				else {
					write_log('latlng missing for ' . $id . ' (' . $description . ')');
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