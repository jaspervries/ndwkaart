<?php
/*
	ndwkaart - matrixbordenkaart
	Copyright (C) 2018, 2025-2026 Jasper Vries

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

$datasource['drip'] = 'https://opendata.ndw.nu/dynamische_route_informatie_paneel.xml.gz';
//$datasource['drip'] = 'voorbeeldfiles/dynamische_route_informatie_paneel.xml.gz';

$datasource['assetwebsite'] = 'voorbeeldfiles/assets_202510241414.json';

$datasource['srti'] = 'https://opendata.ndw.nu/veiligheidsgerelateerde_berichten_srti.xml.gz';
//$datasource['srti'] = 'voorbeeldfiles/veiligheidsgerelateerde_berichten_srti.xml.gz';

$datasource['sit'] = 'https://opendata.ndw.nu/actueel_beeld.xml.gz';
?>