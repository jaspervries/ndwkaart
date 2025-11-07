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

/*
* initialize global variables
*/
var map; //map object
var layers = ['drip', 'msi', 'incidents']; //definition list of dynamic layers
var staticlayers = ['driptable'] //definition list of static layers
var activemaplayers = [];

var selectedTileLayer = 0;
var tileLayers = [
	{
		name: 'BRT Achtergrondkaart',
		layer: L.tileLayer('https://service.pdok.nl/brt/achtergrondkaart/wmts/v2_0/standaard/EPSG:3857/{z}/{x}/{y}.png', {
			minZoom: 6,
			maxZoom: 19,
			bounds: [[50.5, 3.25], [54, 7.6]],
			attribution: 'Kaartgegevens &copy; <a href="https://www.kadaster.nl">Kadaster</a> | <a href="https://www.verbeterdekaart.nl">Verbeter de kaart</a>'
		})
	},
	{
		name: 'Luchtfoto',
		layer: L.tileLayer('https://service.pdok.nl/hwh/luchtfotorgb/wmts/v1_0/Actueel_orthoHR/EPSG:3857/{z}/{x}/{y}.jpeg', {
			minZoom: 6,
			maxZoom: 21,
			bounds: [[50.5, 3.25], [54, 7.6]],
			attribution: 'Kaartgegevens &copy; <a href="https://www.kadaster.nl">Kadaster</a>'
		})
	},
	{
		name: 'OpenStreetMap',
		layer: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		})
	}
];

var data = {}; //data cache
var markers = {}; //markers currently on the map
var onloadCookie;
var timeout_handle = {};
var timeout_handle_source = {};

/*
Note: "layer" when used in comments and custum functions means an actual layer with multiple assets rather than Leaflet's definition where a layer is a single marker etc.
*/

/*
* initialize the map
*/
function initMap() {
	//create map
	map = new L.Map('map');
	//attach event handler
	//for onload, event handler must be attached before setting initial map center and zoom
	map.on('moveend', function() {
		setMapCookie();
		updateMapLayers();
	});
	map.on('load', function() {
		for (var i = 0; i < layers.length; i++) {
			//draw map layers
			getData(layers[i]);
		}
		for (var i = 0; i < staticlayers.length; i++) {
			//draw map layers
			getData(staticlayers[i], true);
		}
	});
	//set initial map center and zoom
	//check if zoom is a number, otherwise cookie isn't set
	if ((typeof onloadCookie !== "undefined") && ($.isNumeric(onloadCookie[1]))) {
		//get and use center and zoom from cookie
		map.setView(onloadCookie[0], onloadCookie[1]);
	}
	else {
		//set initial map view
		map.setView(new L.LatLng(52, 4.2),9);
	}
	//set tile layer
	setMapTileLayer(selectedTileLayer);
	//modify some map controls
	map.zoomControl.setPosition('bottomright');
	L.control.scale().addTo(map);
}

/*
* Set the map tileset
*/
function setMapTileLayer(tile_id) {
	for (var i = 0; i < tileLayers.length; i++) {
		if (i == tile_id) {
			map.addLayer(tileLayers[i].layer);
		}
		else {
			map.removeLayer(tileLayers[i].layer);
		}
	}
	selectedTileLayer = tile_id;
	setMapCookie();
}

/*
* Update map layers
*/
function updateMapLayers() {
	for (var i = 0; i < staticlayers.length; i++) {
		//draw static map layers
		drawLayer(staticlayers[i], true);
	}
	for (var i = 0; i < layers.length; i++) {
		//draw map layers
		drawLayer(layers[i]);
	}
}

/*
* get and refresh data for a layer on the map
*/
function getData(layer, static=false) {
	var created = 0;
	if ((typeof data[layer] !== 'undefined')) {
		created = data[layer].created;
	}
	
	$.getJSON('data.php', {lyr:layer, bounds:map.getBounds().toBBoxString(), zoom:map.getZoom(), created:created})
	.done(function(json) {
		if (json != false) {
			data[layer] = json;
			drawLayer(layer);
			if (static == false) {
				dataSourceAge(layer, json.created, created);
			}
			console.log(layer + ' updated');
		}
		else {
			console.log('no ' + layer + ' update');
		}
		//static layers need not be updated frequently
		var timeout = 59000;
		if (json == false) {
			timeout = 15000;
		}
		if (static == true) {
			//update static layers only daily
			timeout = 86400000;
		}
		//set next update
		clearTimeout(timeout_handle[layer]);
		timeout_handle[layer] = setTimeout(getData, timeout, layer, false);
	});
}

//background call to update source data
function updateSource(layer, static) {
	console.log(layer + ' source update started');
	$.getJSON('update.php', {lyr:layer})
	.done(function(json) {
		//decide timeout
		var timeout = 60;
		if ((typeof json.nextrun !== 'undefined') && (json.nextrun > 0) && (json.nextrun < 300)) {
			timeout = json.nextrun;
		}
		//debug console output
		if (typeof json.processingtime !== 'undefined') {
			console.log(layer + ' source updated in ' + json.processingtime + ' seconds, next update in ' + timeout);
			//trigger data refresh
			getData(layer, static);
		}
		else {
			console.log(layer + ' source not updated, next update in ' + timeout);
		}
		//set next update
		clearTimeout(timeout_handle_source[layer]);
		timeout_handle_source[layer] = setTimeout(updateSource, timeout * 1000, layer, static);
	});
}

/*
* draw a layer on the map
*/
function drawLayer(layer) {
	//check if markers object exists and if not create it
	//for this purpose everything is a marker
	if (!Array.isArray(markers[layer])) {
		markers[layer] = [];
	}
	if (layer == 'msi') {
		/*
		msi layer
		this layer doesn't always contain all markers
		to prevent a flicker effect, on each refresh a new layer is drawn and the old layer destroyed
		a Leaflet layergroup may be useful here, but doesn't appear to add much value at this point
		*/
		var imgsize = 16; //12 or 16 (px, also change image.php accordingly and purge cache)
		//add new markers
		var newmarkers = [];
		//check if layer is active
		if ((activemaplayers[layers.indexOf(layer)] == true) && (typeof data[layer] !== 'undefined')) {
			$.each(data[layer].data, function(i, item) {
				//only draw what is in zoom
				if (map.getBounds().contains(L.latLng(item.lat, item.lon))) {	
					//draw marker
					var marker = L.marker([item.lat, item.lon], {
						icon: L.icon({iconUrl: 'image.php?t=msi&i=' + item.i, iconSize: [(item.i.length * (imgsize+1)), imgsize], iconAnchor: [0, 6], popupAnchor: [0, -6] }),
						rotationAngle: item.rot,
						rotationOrigin: 'left center',
						zIndexOffset: 1000,
						riseOnHover: true,
						title: item.t
					}).addTo(map);
					//push marker to array
					newmarkers.push(marker);
				}
			});	
		}
		//remove all old markers
		for (var i = markers[layer].length - 1; i >= 0; i--) {
			//remove marker
			markers[layer][i].removeFrom(map);
		}
		markers[layer] = newmarkers;
	}
	else if (layer == 'driptable') {
		/*
		drip table layer
		*/
		//add new markers
		var newmarkers = [];
		//check if layer is active
		if ((activemaplayers[layers.indexOf('drip')] == true) && (typeof data[layer] !== 'undefined')) {
			$.each(data[layer].data, function(i, item) {
				//only draw what is in zoom
				if (map.getBounds().contains(L.latLng(item.lat, item.lon))) {	
					//draw marker
					var marker = L.marker([item.lat, item.lon], {
						icon: L.icon({iconUrl: ((item.rot == null) ? 'images/picto16/drip.png' : 'images/picto16/driparrow.png'), iconSize: [16, 16] }),
						rotationAngle: item.rot,
						rotationOrigin: 'center',
						riseOnHover: true,
						title: item.dsc
					})
					.on('click', function(e) {
						openMapPopup(e, item);
					})
					.addTo(map);
					//push marker to array
					newmarkers.push(marker);
				}
			});	
		}
		//remove all old markers
		for (var i = markers[layer].length - 1; i >= 0; i--) {
			//remove marker
			markers[layer][i].removeFrom(map);
		}
		markers[layer] = newmarkers;
	}
	else if (layer == 'incidents') {
		/*
		incidents layer
		this layer doesn't always contain all markers
		to prevent a flicker effect, on each refresh a new layer is drawn and the old layer destroyed
		a Leaflet layergroup may be useful here, but doesn't appear to add much value at this point
		*/
		//add new markers
		var newmarkers = [];
		//check if layer is active
		if ((activemaplayers[layers.indexOf(layer)] == true) && (typeof data[layer] !== 'undefined')) {
			$.each(data[layer].data, function(i, item) {
				//only draw what is in zoom
				if (map.getBounds().contains(L.latLng(item.lat, item.lon))) {	
					//decide icon
					var iconurl = 'images/incidents/unknown.png';
					if (item.type == 'Accident') {
						iconurl = 'images/incidents/' + item.type.toLowerCase() + '.png';
					}
					else if ((item.type == 'VehicleObstruction') && (item.vehicleObstructionType == 'brokenDownVehicle')) {
						iconurl = 'images/incidents/' + item.vehicleObstructionType.toLowerCase() + '.png';
					}
					//draw marker
					var marker = L.marker([item.lat, item.lon], {
						icon: L.icon({iconUrl: iconurl, iconSize: [24,24]}),
						zIndexOffset: 2000,
						riseOnHover: true,
						title: item.type
					}).on('click', function(e) {
						openPopupIncidents(e, item);
					}).addTo(map);
					//push marker to array
					newmarkers.push(marker);
				}
			});	
		}
		//remove all old markers
		for (var i = markers[layer].length - 1; i >= 0; i--) {
			//remove marker
			markers[layer][i].removeFrom(map);
		}
		markers[layer] = newmarkers;
	}
	else if (layer == 'traveltime') {
		/*
		traveltime layer
		this layer draws polylines when moving the map
		colors are drawn separately and updated at interval
		*/
		//add new polylines
		/*var newmarkers = [];
		$.each(data[layer], function(i, item) {
			//draw polyline
			var path = item.p.split('|');
			for (i = 0; i < path.length; i++) {
				path[i] = path[i].split(',');
			}
			var marker = L.polyline([path], {
				color: '#999'
			}).addTo(map);
			marker.on('click', function() {
				console.log(item.i);
			});
			//push marker to array
			newmarkers.push(marker);
		});	
		//remove all old markers
		for (var i = markers[layer].length - 1; i >= 0; i--) {
			//remove marker
			markers[layer][i].removeFrom(map);
		}
		markers[layer] = newmarkers;*/
	}
}


/*
* Load marker's popup content
*/
function openMapPopup(e, item) {
	//TODO: update popup contents when it is open
	var popup = L.popup({ maxWidth: 512 })
    .setLatLng(e.latlng);
	var popupcontentheader = '<h1>'+ ((item.cd.length > 0) ? item.cd : item.dsc) +'</h1>'
		+ '<p>'
		+ ((item.nm.length > 0) ? 'Naam: ' + item.nm + '<br>' : '')
		+ ((item.as.length > 0) ? 'Wegbeheerder: ' + item.as + '<br>' : '')
		+ ((item.tp.length > 0) ? 'Type DRIP: ' + item.tp + '<br>' : '');
	var popupcontentfooter = '<span class=xsmall>NDW id: ' + item.id + '</span>'
		+ '</p>';
	
	//popup with image
	if ((item.id in data.drip.data) && (typeof data.drip.data[item.id].i !== 'undefined')) {
		//get image width and height
		var imageurl = 'images/drip/'+ data.drip.data[item.id].i.substring(0, 1) +'/'+ data.drip.data[item.id].i.substring(1,2) +'/'+ data.drip.data[item.id].i +'.png';
		var theImage = new Image();
		theImage.src = imageurl;
		theImage.onload = function() {
			popup.setContent(
				popupcontentheader 
				+ 'Status: ' + ((data.drip.data[item.id].w == 1) ? 'operationeel' : 'niet operationeel') + '<br>'
				+ '<img id="popupimage" src="' + imageurl + '" width="' + theImage.width + '" height="' + theImage.height + '"><br>'
				+ popupcontentfooter)
			.openOn(map);
		};
	}
	//tekstdrips
	else if ((item.id in data.drip.data) && (typeof data.drip.data[item.id].t !== 'undefined')) {
		var textlines = '';
		data.drip.data[item.id].t.forEach(function(line) {
			console.log(line);
			if (textlines.length > 0) {
				textlines = textlines + '<br>';
			}
			textlines = textlines + line;
		});
		popup.setContent(
			popupcontentheader 
			+ 'Status: ' + ((data.drip.data[item.id].w == 1) ? 'operationeel' : 'niet operationeel') + '<br>'
			+ '<span class="textdrip">' + textlines + '</span><br>'
			+ popupcontentfooter)
		.openOn(map);
	}
	else if (item.id in data.drip.data) {
		popup.setContent(
			popupcontentheader 
			+ 'Status: ' + ((data.drip.data[item.id].w == 1) ? 'operationeel' : 'niet operationeel') + '<br>'
			+ 'Geen afbeelding<br>'
			+ popupcontentfooter)
		.openOn(map);
	}
	//item not in data.drip.data
	else {
		popup.setContent(
			popupcontentheader 
			+ 'Geen actuele informatie beschikbaar<br>'
			+ popupcontentfooter)
		.openOn(map);
	}
}

function openPopupIncidents(e, item) {
	//TODO: update popup contents when it is open
	var popup = L.popup()
    .setLatLng(e.latlng);
	var popupcontent = '<h1>Incident</h1><p>';
	popupcontent += 'Type: ' + item.type + '<br>';
	$.each(item, function(key, val) {
		if ((key != 'type') && (key != 'lat') && (key != 'lon')) {
			popupcontent += key + ': ' + val + '<br>';
		}
	});
	popupcontent += '</p>';
	
	popup.setContent(popupcontent)
	.openOn(map);
}

/*
* display information about data age
*/
function dataSourceAge(layer, created, previouscreated) {
	//add a warning if a datasource is outdated; when multiple sources are outdated, a warning for each source will be shown
	//in worst case it can fill the screen, but then you probably don't want to use the application anyways.
	var date = new Date(created * 1000);
	if (((Date.now()/1000) - created) > 300) { //5 minutes
		//check if warning exists
		if ($('#data_warnings li#' + layer).length === 0) {
			//add warning
			if ((previouscreated == 0) && (created > 0)) {
				$('#data_warnings').append('<li id="' + layer + '">Datastroom <i>' + layer + '</i> wordt bijgewerkt. Huidig beeld ' + date.toString() +  '</li>');
			}
			else if (previouscreated == 0) {
				$('#data_warnings').append('<li id="' + layer + '">Datastroom <i>' + layer + '</i> wordt bijgewerkt.</li>');
			}
			else {
				$('#data_warnings').append('<li id="' + layer + '">Datastroom <i>' + layer + '</i> is meer dan 5 minuten oud.</li>');
			}
		}
		//(note: if warning exists it will be retained)
	}
	else {
		//remove warning
		$('#data_warnings li#' + layer).remove();
	}
	//update finger message
	$('label[for=map-layer-' + layer + '] .lastupdate').html('(' + date.getHours() + ':' + ('0' + date.getMinutes()).substr(-2) + ':' + ('0' + date.getSeconds()).substr(-2) + ')');
}

/*
* Set the cookie to remember map center, zoom, style and active layers
*/
function setMapCookie() {
	Cookies.set('onk_map', [map.getCenter(), map.getZoom(), selectedTileLayer, activemaplayers], {expires: 1000});
}

/*
* initialize layer GUI
*/
function initLayerGUI() {
    //get map layers
	$.each(layers, function(key, layer) {
		if (typeof activemaplayers[key] !== 'undefined') {
			activemaplayers[key] = false;
		}
		
		$('#map-layers').append('<li><input type="checkbox" name="map-layer-' + layer + '" id="map-layer-' + layer + '"><label for="map-layer-' + layer + '">' + layer + ' <span class="lastupdate" title="tijdstip laatste update"></span></label></li>');
		//set active layers
		if ((typeof onloadCookie !== 'undefined') && (typeof onloadCookie[3] !== 'undefined')) {
			if (onloadCookie[3][key] >= 1) {
				activemaplayers[key] = true;
				$('#map-layer-' + layer).prop('checked', true);
			}
			else {
				 activemaplayers[key] = false;
			}
		}
		else {
			activemaplayers[key] = true;
			$('#map-layer-' + layer).prop('checked', true);
		}
	});

	$('#map-layers input[type=checkbox]').change( function() {
		var layer = this.id.substr(10);
		//get key from layers
		var key = layers.indexOf(layer);
		//set activemaplayers
		var enableState = $(this).prop('checked');
		activemaplayers[key] = enableState;
		updateMapLayers();
		setMapCookie();
    });
	updateMapLayers();
}

/*
* Get maps tileset on page load
*/
function getMapTileLayer() {
	//get map style
	if ((typeof onloadCookie !== 'undefined') && (typeof onloadCookie[2] == 'number')) {
		selectedTileLayer = onloadCookie[2];
	}
	//set correct radio button
	$('#map-tile-' + selectedTileLayer).prop('checked', true);
}

/*
* draw tilelayer GUI
*/
function drawTileLayerGUI() {
	$.each(tileLayers, function(id, options) {
		$('#map-tile').append('<li><input type="radio" name="map-tile" id="map-tile-' + id + '"><label for="map-tile-' + id + '">' + options.name + '</label></li>');
	});
	$('#map-tile input[type=radio]').change( function() {
		var tile_id = this.id.substr(9);
		setMapTileLayer(parseInt(tile_id));
		$(this).prop('checked');
	});
}

/*
* document.ready
*/
$(function() {
	onloadCookie = Cookies.getJSON('onk_map');
	drawTileLayerGUI();
	getMapTileLayer();
	initLayerGUI();
	initMap();
	for (var i = 0; i < layers.length; i++) {
		//draw map layers
		updateSource(layers[i]);
	}
	for (var i = 0; i < staticlayers.length; i++) {
		//draw map layers
		updateSource(staticlayers[i], true);
	}
	//shade mapoptions
	$('#map-options-container fieldset legend').click(function() {
		if ($(this).next().is(':visible')) {
			$(this).next().hide();
			$(this).parent().addClass('hidden');
		}
		else {
			$(this).next().show();
			$(this).parent().removeClass('hidden');
		}
		
	});
	//default hide map tile selection
	$('#map-tile').hide();
	$('#map-tile').parent().addClass('hidden');

	//helptext
	$('#help').click(function() {
		if ($('#helptext').is(':visible')) {
			$('#helptext').hide();
		}
		else {
			$('#helptext').show();
		}
		
	})
	$('#helptext').click(function() {
		$(this).hide();
	});
});
