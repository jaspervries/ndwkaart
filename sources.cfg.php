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

//data source config
$gzdecode = TRUE;

//data source locations
$datasource['msi'] = 'https://opendata.ndw.nu/Matrixsignaalinformatie.xml.gz';
//$datasource['msi'] = 'voorbeeldfiles/Matrixsignaalinformatie.xml.gz';

$datasource['mst'] = 'http://opendata.ndw.nu/measurement_current.xml.gz';
$datasource['mst'] = 'voorbeeldfiles/measurement_current.xml.gz';

$datasource['driptable'] = 'https://opendata.ndw.nu/LocatietabelDRIPS.xml.gz';
//$datasource['driptable'] = 'voorbeeldfiles/LocatietabelDRIPS.xml.gz';

$datasource['assetwebsite'] = 'voorbeeldfiles/assets_202510241414.json';

$datasource['drip'] = 'https://opendata.ndw.nu/DRIPS.xml.gz';
//$datasource['drip'] = 'voorbeeldfiles/DRIPS.xml.gz';

$datasource['incidents'] = 'https://opendata.ndw.nu/incidents.xml.gz';
//$datasource['incidents'] = 'voorbeeldfiles/incidents.xml.gz';

?>