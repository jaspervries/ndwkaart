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

//function to write log file
if (!function_exists('write_log')) { function write_log ($string = '', $echo = FALSE) {
	global $argv;
	if (!empty($string)) {
		if (file_exists('log.txt')) {
			$hdl = fopen('log.txt', 'r');
			$oldlog = fread($hdl, 10000000);
			fclose($hdl);
		}
		else $oldlog = '';
		$log = date('Y-m-d H:i:s') . "\t";
		//$log .= $argv[0] . "\t"; //script name
		$log .= $string. PHP_EOL;
		if ($echo == TRUE) {
			echo $log;
		}
		$log .= $oldlog;
		$hdl = fopen('log.txt', 'w');
		fwrite($hdl, $log);
		fclose($hdl);
	}
}}
?>