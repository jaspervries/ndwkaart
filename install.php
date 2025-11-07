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

//create config
if (!is_file('config.cfg.php')) {
	$config = '<?php
/*
 * ndwkaart configuration file
*/

//Database details
$cfg_db[\'host\'] = \'localhost\';
$cfg_db[\'user\'] = \'root\';
$cfg_db[\'pass\'] = \'\';
$cfg_db[\'db\'] = \'onk\';
?>
';
	file_put_contents('config.cfg.php', $config);
	echo 'created config file'.PHP_EOL;
	echo 'PLEASE EDIT CONFIG FILE AND RUN INSTALL AGAIN!'.PHP_EOL;
	exit;
}

require('config.cfg.php');

$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass']);


$qry = "CREATE DATABASE IF NOT EXISTS `".$cfg_db['db']."`
COLLATE 'latin1_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'database created or exists'.PHP_EOL;
else echo 'did not create database'.PHP_EOL;

$db['link'] = mysqli_connect($cfg_db['host'], $cfg_db['user'], $cfg_db['pass'], $cfg_db['db']);

$qry = "CREATE TABLE IF NOT EXISTS `registry`
(
	`key` VARCHAR(32) NOT NULL,
	`value` VARCHAR(128) NOT NULL,
	PRIMARY KEY (`key`)
)
ENGINE `MyISAM`,
CHARACTER SET 'latin1', 
COLLATE 'latin1_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `registry` created or exists'.PHP_EOL;
else echo 'did not create table `registry`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

$qry = "CREATE TABLE IF NOT EXISTS `msi`
(
	`lat` DECIMAL(15,13) NOT NULL,
	`lon` DECIMAL(14,13) NOT NULL,
	`uuid` VARCHAR(36) NOT NULL,
	`road` VARCHAR(4) NOT NULL,
	`carriageway` VARCHAR(1) NOT NULL,
	`lane` INT(1) UNSIGNED NOT NULL,
	`km` DECIMAL(6,3) NOT NULL,
	`bearing` INT(3) UNSIGNED NOT NULL,
	PRIMARY KEY (`uuid`)
)
ENGINE `MyISAM`,
CHARACTER SET 'latin1', 
COLLATE 'latin1_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `msi` created or exists'.PHP_EOL;
else echo 'did not create table `msi`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

$qry = "CREATE TABLE IF NOT EXISTS `msi_missing`
(
	`uuid` VARCHAR(36) NOT NULL,
	`road` VARCHAR(4) NOT NULL,
	`carriageway` VARCHAR(1) NOT NULL,
	`lane` INT(1) UNSIGNED NOT NULL,
	`km` DECIMAL(6,3) NOT NULL,
	PRIMARY KEY (`uuid`)
)
ENGINE `MyISAM`,
CHARACTER SET 'latin1', 
COLLATE 'latin1_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `msi` created or exists'.PHP_EOL;
else echo 'did not create table `msi`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

$qry = "CREATE TABLE IF NOT EXISTS `traveltime`
(
	`id` VARCHAR(255) NOT NULL,
	`name` TINYTEXT NULL,
	`current` BOOLEAN DEFAULT 0,
	`path` TEXT NULL,
	`path_source` ENUM('mst', 'shape') NOT NULL DEFAULT 'mst',
	`lat_min` DOUBLE,
	`lat_max` DOUBLE,
	`lon_min` DOUBLE,
	`lon_max` DOUBLE,
	`length` MEDIUMINT UNSIGNED NOT NULL,
	`equipment` VARCHAR(64) NULL,
	`freeflow` MEDIUMINT SIGNED DEFAULT -1,
	`maxspeed` MEDIUMINT SIGNED DEFAULT -1,
	`traveltime` MEDIUMINT SIGNED DEFAULT -1,
	`measurementtime` INT UNSIGNED DEFAULT 0,
	`lastupdate` INT UNSIGNED DEFAULT 0,
	PRIMARY KEY (`id`)
)
ENGINE `MyISAM`,
CHARACTER SET 'latin1', 
COLLATE 'latin1_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `traveltime` created or exists'.PHP_EOL;
else echo 'did not create table `traveltime`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

$qry = "CREATE TABLE IF NOT EXISTS `driptable`
(
	`vmsUnitRecord_id` VARCHAR(255) NOT NULL,
	`vmsUnitRecord_version` MEDIUMINT UNSIGNED NOT NULL,
	`vmsIndex` TINYINT UNSIGNED NOT NULL,
	`longitude` DECIMAL(14,13) NOT NULL,
	`latitude` DECIMAL(15,13) NOT NULL,
	`location` POINT NULL,
	`bearing` INT(3) UNSIGNED DEFAULT NULL,
	`vmsDescription` TINYTEXT NULL,
	`vmsPhysicalMounting` TINYTEXT NULL,
	`vmsType` TINYTEXT NULL,
	`carriageway` TINYTEXT NULL,
	`code` TINYTEXT NULL,
	`naam` TINYTEXT NULL,
	`aansturing` TINYTEXT NULL,
	UNIQUE KEY (`vmsUnitRecord_id`, `vmsIndex`)
)
ENGINE `MyISAM`,
CHARACTER SET 'utf8mb4', 
COLLATE 'utf8mb4_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `driptable` created or exists'.PHP_EOL;
else echo 'did not create table `driptable`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

$qry = "CREATE TABLE IF NOT EXISTS `assetwebsite`
(
	`assetid` INT UNSIGNED NOT NULL PRIMARY KEY,
	`assettypename` VARCHAR(255) NOT NULL,
	`code` TINYTEXT NULL,
	`naam` TINYTEXT NULL,
	`aansturing` TINYTEXT NULL,
	`latitude` DECIMAL(15,13) NOT NULL,
	`longitude` DECIMAL(14,13) NOT NULL,
	`location` POINT NULL,
	`heading` INT(3) UNSIGNED DEFAULT NULL,
	`type` TINYTEXT NULL
)
ENGINE `MyISAM`,
CHARACTER SET 'utf8mb4', 
COLLATE 'utf8mb4_general_ci'";
if (mysqli_query($db['link'], $qry)) echo 'table `assetwebsite` created or exists'.PHP_EOL;
else echo 'did not create table `assetwebsite`'.PHP_EOL;
echo mysqli_error($db['link']).PHP_EOL;

//create directories
$directories = array('json', 'images/msi', 'images/drip');
foreach ($directories as $dir) {
	if (@mkdir($dir)) {
		echo 'created directory ' . $dir . PHP_EOL;
	}
	else {
		echo 'did not create directory ' . $dir . PHP_EOL;
	}
}

//create drip dir structure
if (!is_dir('images/drip')) {
	$subdirs = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
	foreach ($subdirs as $subdir) {
		$subdir = 'images/drip/'.$subdir;
		if (!is_dir($subdir)) {
			mkdir($subdir);
			foreach ($subdirs as $subsubdir) {
				$subsubdir = $subdir.'/'.$subsubdir;
				if (!is_dir($subsubdir)) {
					mkdir($subsubdir);
				}
			}
		}
	}
	echo '* images/drip submappen aangemaakt' . PHP_EOL;
}
?>