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

include_once('log.inc.php');
require('config.cfg.php');
$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

$layers = array('msi', 'drip', 'incidents');

if (in_array($_GET['lyr'], $layers)) {
    $json_update = array('nextrun' => 15);
    //get lastrun from registry
    $qry = "SELECT `value` FROM `registry` WHERE
    `key` = '" . $_GET['lyr'] . "_lastrun'";
    $res = mysqli_query($db['link'], $qry);
    $lastrun = 0;
    if (mysqli_num_rows($res)) {
        $data = mysqli_fetch_row($res);
        $lastrun = $data[0]; 
    }

    //check if update is needed
    $time_start = time();
    if ($time_start - $lastrun >= 59) {
        
        //update registry
        $qry = "INSERT INTO `registry` SET
        `key` = '" . $_GET['lyr'] . "_lastrun',
        `value` = '".time()."'
        ON DUPLICATE KEY UPDATE 
        `value` = '".time()."'";
        mysqli_query($db['link'], $qry);

        //update
        include('update_' . $_GET['lyr'] . '.php');
        //if updated, nextrun
        if (!empty($output)) {
            $json_update['nextrun'] = 59;
        }
        if (is_numeric($processing_time)) {
             $json_update['processingtime'] = $processing_time;
        }
    }

    header('Content-type: application/json');
	echo json_encode($json_update);
}

?>