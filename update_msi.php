<?php
/*
	ndwkaart - matrixbordenkaart
	Copyright (C) 2018, 2025 Jasper Vries

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
write_log('update msi started', $debug);
require('sources.cfg.php');
require('gzdecode.fct.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

//get uuid
$qry = "SELECT `value` FROM `registry` WHERE
`key` = 'msi_uuid'";
$res = mysqli_query($db['link'], $qry);
$msi_last_uuid = 0;
if (mysqli_num_rows($res)) {
    $data = mysqli_fetch_row($res);
    $msi_last_uuid = $data[0]; 
}

$available_states = array('speedlimit', 'lane_open', 'lane_closed_ahead', 'lane_closed', 'restriction_end'); //blank, unknown, discontinued

//process data
$data = @file_get_contents($datasource['msi']);
$output = array();
$lanes = array();

//gzdecode if necessary
if ($gzdecode == true) $data = @gzdecode($data);
if ($data !== FALSE) {
	try {			
		$data = @simplexml_load_string($data);
		if ($data !== FALSE) {
			$data = $data->children('SOAP', true)->children('ndw', true)->children()->variable_message_sign_events; //read soap envelope
			//check meta uuid
			if ($data->meta->msg_id->uuid != $msi_last_uuid) {
				foreach ($data->event as $event) {
					if (!empty($event->display)) {
						//get state
						$state = $event->display->children()->getName();
						if (in_array($state, $available_states)) {
							//uuid
							$uuid = $event->sign_id->uuid;
							//get location details for uuid
							$qry = "SELECT ROUND(`lat`,6) as `lat`, ROUND(`lon`,6) as `lon`, `road`, `carriageway`, `lane`, `km`, `bearing` FROM `msi`
							WHERE `uuid` = '".mysqli_real_escape_string($db['link'], $uuid)."'
							LIMIT 1";
							$res = mysqli_query($db['link'], $qry);
							if (mysqli_num_rows($res)) {
								$data2 = mysqli_fetch_assoc($res);
								$output_id = $data2['road'].$data2['carriageway'].round($data2['km']*1000);
								if (!array_key_exists($output_id, $output)) {
									//add output
									$output[$output_id] = array(
										't' => $data2['road'] . ' ' . $data2['carriageway'] . ' ' . $data2['km'],
										'lat' => (float) $data2['lat'],
										'lon' => (float)$data2['lon'],
										'rot' => (int) $data2['bearing']
									);
									//add number of lanes
									$qry2 = "SELECT `lane` FROM `msi`
									WHERE `road` = '".mysqli_real_escape_string($db['link'], $data2['road'])."'
									AND `carriageway` = '".mysqli_real_escape_string($db['link'], $data2['carriageway'])."'
									AND `km` = '".mysqli_real_escape_string($db['link'], $data2['km'])."'";
									$res2 = mysqli_query($db['link'], $qry2);
									while ($data3 = mysqli_fetch_assoc($res2)) {
										$lanes[$output_id][$data3['lane']] = 'b';
									}
									
								}
								//timestamp
								$ts = $event->ts_state;
								//state
								if ($state == 'lane_open') {
									$state = 'g';
								}
								elseif ($state == 'lane_closed') {
									$state = 'x';
								}
								elseif ($state == 'restriction_end') {
									$state = 'e';
								}
								//lane closed ahead
								elseif ($state == 'lane_closed_ahead') {
									$merge = $event->display->$state->children()->getName();
									if ($merge == 'merge_right') {
										$state = 'r';
									}
									elseif ($merge == 'merge_left') {
										$state = 'l';
									}
									else {
										$state = 'b';
									}
								}
								//speedlimit
								elseif ($state == 'speedlimit') {
									$speedlimit = $event->display->$state;
									if (in_array($speedlimit, array('30', '50', '60', '70', '80', '90'))) {
										$state = (string) ($speedlimit/10);
									}
									elseif ($speedlimit == '100') {
										$state = '1';
									}
									elseif ($speedlimit == '120') {
										$state = '2';
									}
									else {
										$state = 'b';
									}
								}
								else {
									$state = 'b';
								}
								$attr = $event->display->children()->attributes();
								/*
								//flashing
								if ($attr['flashing'] == 'true') {
									$flashing = 1;
								}
								else {
									$flashing = 0;
								}
								*/
								//red ring
								if ($attr['red_ring'] == 'true') {
									if ($state == '8') {
										$state = 't';
									}
									elseif ($state == '1') {
										$state = 'u';
									}
								}
								//prepare output
								$lanes[$output_id][$data2['lane']] = $state;
							}
						}
					}
				}
				//update uuid in database
				if (!empty($output)) {
					$qry = "INSERT INTO `registry` SET
					`key` = 'msi_uuid',
					`value` = '".mysqli_real_escape_string($db['link'], $data->meta->msg_id->uuid)."'
					ON DUPLICATE KEY UPDATE 
					`value` = '".mysqli_real_escape_string($db['link'], $data->meta->msg_id->uuid)."'";
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
if (!empty($output)) {
	$json = array();
	
	foreach ($output as $output_id => $location) {
		//combine states for carriageway
		$states = $lanes[$output_id];
		ksort($states);
		$state = '';
		for ($i=1; $i<=9; $i++) {
			if (!empty($states[$i])) {
				$state .= $states[$i];
			}
			else {
				$state .= '-';
			}
		}
		$location['i'] = str_replace('-', '', $state);
		$json[] = $location;
	}
	//add creation timestamp and servertime-placeholder
	$json = array ('created' => round($time_start), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $json);
	
	//write json
	$json = json_encode($json);
	file_put_contents('json/msi.json', $json);

	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'msi_created',
	`value` = '".round($time_start)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".round($time_start)."'";
	mysqli_query($db['link'], $qry);
	
	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'msi_update',
	`value` = '".round($time_start)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".round($time_start)."'";
	mysqli_query($db['link'], $qry);
}

//calculate processing time
$processing_time = round((microtime(TRUE) - $time_start), 1);
write_log('Processing time: ' . $processing_time . ' seconds', $debug);

?>