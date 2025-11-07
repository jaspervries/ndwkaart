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
				foreach ($datex->d2LogicalModel->payloadPublication->vmsUnit as $vmsUnit) {
					/*$vmsUnit->vmsUnitReference['id']; //key
					$vmsUnit->vmsUnitReference['version'];*/
					
					foreach ($vmsUnit->vms as $vms) {
						/*$vms['vmsIndex']; //key
						$vms->vms->vmsWorking;
						$vms->vms->vmsMessage->vmsMessage->timeLastSet;
						$vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->binary;
						$vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->encoding;
						$vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->mimeType;
						$vms->vms->vmsMessage->vmsMessage->textPage->vmsText->vmsTextLine;*/
						//get vms record from table
						$qry = "SELECT `vmsUnitRecord_id` FROM `driptable`
						WHERE `vmsUnitRecord_id` = '".mysqli_real_escape_string($db['link'], $vmsUnit->vmsUnitReference['id'])."'
						AND `vmsIndex` = '".mysqli_real_escape_string($db['link'], $vms['vmsIndex'])."'
						LIMIT 1";
						$res = mysqli_query($db['link'], $qry);
						if (mysqli_num_rows($res)) {
							$data = mysqli_fetch_assoc($res);
							//add working status
							$output_this = array('w' => (int) ($vms->vms->vmsWorking == 'true') ? 1 : 0);
							//check if image and image is png
							if (isset($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->binary)
								&& isset ($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->encoding)
								&& ($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->encoding == 'base64')
								&& isset ($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->mimeType)
								&& ($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->mimeType == 'image/png')
							) {
								//check if file exists
								$md5 = md5($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->binary);
								//echo $md5 . PHP_EOL;
								$filename = 'images/drip/' . substr($md5, 0, 1) . '/' . substr($md5, 1, 1) . '/' . $md5 . '.png';
								$contents = TRUE;
								if (!is_file($filename)) {
									//store file
									$contents = base64_decode($vms->vms->vmsMessage->vmsMessage->vmsMessageExtension->vmsMessageExtension->vmsImage->imageData->binary);
									if ($contents) {
										file_put_contents($filename, $contents);
									}
								}
								//prepare output
								$output_this['i'] = (string) $md5;
							}
							//otherwise textline
							else {
								if (isset($vms->vms->vmsMessage->vmsMessage->textPage->vmsText)) {
									$vmstext = array();
									foreach ($vms->vms->vmsMessage->vmsMessage->textPage->vmsText->vmsTextLine as $vmsTextLine) {
										$vmstext[] = (string) $vmsTextLine->vmsTextLine->vmsTextLine;
									}
									$output_this['t'] = $vmstext;
								}
							}
							//add to output
							$output[(string) $vmsUnit->vmsUnitReference['id'] . '_' . $vms['vmsIndex']] = $output_this;

						}
					}
				}
				//update publicationtime in database
				if (!empty($output)) {
					$qry = "INSERT INTO `registry` SET
					`key` = 'drip_publicationtime',
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
if (!empty($output)) {
	$json = array();

	//add creation timestamp and servertime-placeholder
	$json = array ('created' => strtotime($datex->d2LogicalModel->payloadPublication->publicationTime), 'now' => 'PLACEHOLDER_TIME_NOW', 'data' => $output);
	
	//write json
	$json = json_encode($json);
	file_put_contents('json/drip.json', $json);
	
	//update registry
	$qry = "INSERT INTO `registry` SET
	`key` = 'drip_created',
	`value` = '".strtotime($datex->d2LogicalModel->payloadPublication->publicationTime)."'
	ON DUPLICATE KEY UPDATE 
	`value` = '".strtotime($datex->d2LogicalModel->payloadPublication->publicationTime)."'";
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