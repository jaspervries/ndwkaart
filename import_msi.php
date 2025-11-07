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

//truncate tables
$qry = "TRUNCATE TABLE `msi`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}
$qry = "TRUNCATE TABLE `msi_missing`";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//load from exported shapefile
$qry = "LOAD DATA LOCAL INFILE 'content/msi.csv'
REPLACE
INTO TABLE `msi`
FIELDS TERMINATED BY ','
IGNORE 1 ROWS
(`lon`, `lat`, @ignore, `uuid`, `road`, `carriageway`, `lane`, `km`, @ignore, `bearing`, @ignore)
";
mysqli_query($db['link'], $qry);
if (mysqli_errno($db['link'])) {
	write_log(mysqli_error($db['link']));
}

//find MSI missing from shapefile and load those into addl. table
//process data
$data = @file_get_contents($datasource['msi']);
//gzdecode if necessary
if ($gzdecode == true) $data = gzdecode($data);
if ($data !== FALSE) {
	try {			
		$data = @simplexml_load_string($data);
		if ($data !== FALSE) {
			$data = $data->children('SOAP', true)->children('ndw', true)->children()->variable_message_sign_events; //read soap envelope
			foreach ($data->event as $event) {
				if (!empty($event->lanelocation)) {
					//get state
					$uuid = $event->sign_id->uuid;
					//check if nonexistent
					$qry = "SELECT `uuid` FROM `msi` WHERE `uuid` = '".mysqli_real_escape_string($db['link'], $uuid)."'";
					$res = mysqli_query($db['link'], $qry);
					if (mysqli_num_rows($res) == 0) {
						//if missing, add to missing table
						$road = $event->lanelocation->road;
						$carriageway = $event->lanelocation->carriageway;
						$lane = $event->lanelocation->lane;
						$km = $event->lanelocation->km;
						$qry = "INSERT INTO `msi_missing` SET
						`uuid` = '".mysqli_real_escape_string($db['link'], $uuid)."',
						`road` = '".mysqli_real_escape_string($db['link'], $road)."',
						`carriageway` = '".mysqli_real_escape_string($db['link'], $carriageway)."',
						`lane` = '".mysqli_real_escape_string($db['link'], $lane)."',
						`km` = '".mysqli_real_escape_string($db['link'], $km)."'";
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
		write_log('XML exception:'.$e);
	}
}

//calculate processing time
write_log('Processing time: ' . round((microtime(TRUE) - $time_start), 1) . ' seconds');

?>