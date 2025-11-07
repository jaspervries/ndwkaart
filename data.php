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

//disable caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

require('config.cfg.php');

$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

$layers = array('msi', 'drip', 'driptable', 'incidents');

if (in_array($_GET['lyr'], $layers)) {
	//check created
	$senddata = TRUE;
	if (is_numeric($_GET['created']) && ($_GET['created'] > 0)) {
		//get created
		$qry = "SELECT `value` FROM `registry` WHERE
		`key` = '" . $_GET['lyr'] . "_created'";
		$res = mysqli_query($db['link'], $qry);
		if (mysqli_num_rows($res)) {
			$data = mysqli_fetch_row($res);
			if ($_GET['created'] == $data[0]) {
				$senddata = FALSE;
			}
		}
	}

	//data
	if ($senddata == TRUE) {
		$json = file_get_contents('json/' . $_GET['lyr'] . '.json');
		$json = str_replace('"PLACEHOLDER_TIME_NOW"', time(), $json);
	}
	else {
		$json = json_encode(FALSE);
	}

	header('Content-type: application/json');
	echo $json;
}


/*
if ($_GET['lyr'] == 'msi') {
	header('Content-type: application/json');
	$json = file_get_contents('json/msi.json');
	$json = str_replace('"PLACEHOLDER_TIME_NOW"', time(), $json);
	echo $json;
}
elseif ($_GET['lyr'] == 'driptable') {
	header('Content-type: application/json');
	$json = file_get_contents('json/driptable.json');
	$json = str_replace('"PLACEHOLDER_TIME_NOW"', time(), $json);
	echo $json;
}
elseif ($_GET['lyr'] == 'drip') {
	header('Content-type: application/json');
	$json = file_get_contents('json/drip.json');
	$json = str_replace('"PLACEHOLDER_TIME_NOW"', time(), $json);
	echo $json;
}*/
elseif ($_GET['lyr'] == 'traveltime') {
	//check bounds
	$bounds = explode(',', $_GET['bounds']);
	if (count($bounds) != 4) exit;
	foreach ($bounds as $bound) {
		if (!is_numeric($bound)) exit;
	}
	//select paths
	$qry = "SELECT `id`, `path` FROM `traveltime`
	WHERE ((`lat_min` < ".$bounds[3]."
	AND `lat_max` > ".$bounds[1].")
	OR (`lon_min` < ".$bounds[2]."
	AND `lon_max` > ".$bounds[0]."))
	AND `equipment` = 'fcd'";
	$res = mysqli_query($db['link'], $qry);
	if (mysqli_num_rows($res)) {
		$data = array();
		while ($row = mysqli_fetch_row($res)) {
			$data[] = array('i' => $row[0], 'p' => $row[1]);
		}
	}
	else {
		exit;
	}
	$json = array ('data' => $data);
	header('Content-type: application/json');
	echo json_encode($json);
}
else {
	header('HTTP/1.0 404 Not Found');
}
exit;
?>