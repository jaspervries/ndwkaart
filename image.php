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

if ($_GET['t'] == 'msi') {
	//check if image exists
	$imagefile = 'images/msi/'.$_GET['i'].'.png';
	//render file
	if (!file_exists($imagefile)) {
		$imgsize = 16; //12 or 16 (px)
		$len = strlen($_GET['i']);
		$image = imagecreatetruecolor(($len * ($imgsize+1)), $imgsize);
		$transp = imagecolorallocatealpha($image, 0, 0, 255, 127);
		imagefill($image, 0, 0, $transp);
		//add individual sprites
		for ($i = 0; $i < $len; $i++) {
			$add = imagecreatefrompng('images/msi_source_'.$imgsize.'/'.substr($_GET['i'], $i, 1).'.png');
			imagecopy($image, $add, ($i * ($imgsize+1) + 1), 0, 0, 0, $imgsize, $imgsize);
			imagedestroy($add);
		}
		//store image
		imagesavealpha($image, true);
		imagepng($image, $imagefile, 9);
	}
	//serve file
	$expires = 2592000;
	header('Cache-Control: max-age=' . $expires);
	header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $expires) . ' GMT');
	header('Content-Type: image/png');
	echo file_get_contents($imagefile);
}
else {
	header('HTTP/1.0 404 Not Found');
	exit;
}
?>