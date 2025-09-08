function initMap() {}

var loc_lat = "17.890307";
var loc_long = "79.863593";
let zoom_level = 7;
var user_map = "";
var group = "";
var modal_event = 0;

let group_list_map = document.getElementById('group-list');
var group_name = localStorage.getItem("GroupNameValue");
if (group_name == "" || group_name == null) {
	group_name = group_list_map.value;
}
gps_initMaps(group_name);

group_list_map.addEventListener('change', function () {
	group_name = group_list_map.value;
	gps_initMaps(group_name);
});

function refreshMap() {
	let group_list_map = document.getElementById('group-list');
	group_name = group_list_map.value;
	gps_initMaps(group_name);
}

function gps_initMaps(group_name) {
	$("#loader").css('display', 'block');
	$.ajax({
		type: "POST",
		url: '../devices/code/gis-locations.php',
		traditional: true,
		data: { GROUP_ID: group_name },
		dataType: "json",
		success: function (data) {
			$("#loader").css('display', 'none');
			on_success(data[0], data[1]);
		},
		failure: function (response) {
			alert(response.responseText);
		},
		error: function (response) {
			alert(response.responseText);
		}
	});
}

function on_success(data, location) {
	var json = data;
	var locations = [];
	var subinfoWindow = new google.maps.InfoWindow();

	for (var i = 0; i < json.length; i++) {
		locations.push([json[i].va, json[i].l1, json[i].l2, json[i].icon, json[i].id]);
	}

    // Build bounds from valid points
	let bounds = new google.maps.LatLngBounds();
   /* for (let i = 0; i < locations.length; i++) {
        let lat = Number(locations[i][1]);
        let lng = Number(locations[i][2]);

        if (lat === 0 && lng === 0) {
            // SKIP invalid coords, don't stop the loop
            continue;
        }
        bounds.extend(new google.maps.LatLng(lat, lng));
    }*/


	for (let i = 0; i < locations.length; i++) {
		let lat = Number(locations[i][1]);
		let lng = Number(locations[i][2]);

		if (!isValidLatLng(lat, lng)) {
			continue; 
		}
		bounds.extend(new google.maps.LatLng(lat, lng));
	}

    // Fallback center if no valid points
	if (bounds.isEmpty()) {
		loc_lat = "17.890307";
		loc_long = "79.863593";
	} else {
		var center = bounds.getCenter();
		loc_lat = center.lat();
		loc_long = center.lng();
	}

	var map = new google.maps.Map(document.getElementById('map'), {
		center: new google.maps.LatLng(loc_lat, loc_long),
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		gestureHandling: 'cooperative'
	});

    // Fit to bounds (with padding) or fallback zoom
	const pad = { top: 40, right: 40, bottom: 40, left: 40 };
	if (!bounds.isEmpty()) {
		map.fitBounds(bounds, pad);
        // After tiles/layout settle, re-fit once to prevent drift
		google.maps.event.addListenerOnce(map, 'idle', function () {
			map.fitBounds(bounds, pad);
		});
        // Optional: cap over-zoom when bounds collapse to a single point
		google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
			if (map.getZoom() > 16) map.setZoom(16);
		});
	} else {
		map.setZoom(zoom_level);
	}

	var infowindow = new google.maps.InfoWindow();
	var markers = [];

	var image_red = 'https://maps.gstatic.com/mapfiles/ms2/micons/red-dot.png';
	var image_green = 'https://maps.gstatic.com/mapfiles/ms2/micons/green-dot.png';
	var image_yellow = 'https://maps.gstatic.com/mapfiles/ms2/micons/yellow-dot.png';
	var image_blue = 'https://maps.gstatic.com/mapfiles/ms2/micons/blue-dot.png';
	var image_purple = 'https://maps.gstatic.com/mapfiles/ms2/micons/purple-dot.png';

	for (let i = 0; i < locations.length; i++) {
		let lat = Number(locations[i][1]);
		let lng = Number(locations[i][2]);
		if (lat === 0 && lng === 0) continue;

		let icon = image_red;
		if (locations[i][3] == "1") icon = image_green;
		else if (locations[i][3] == "2") icon = image_yellow;
		else if (locations[i][3] == "3") icon = image_blue;
		else if (locations[i][3] == "4") icon = image_purple;

		const marker = new google.maps.Marker({
			position: new google.maps.LatLng(lat, lng),
			map: map,
			icon: icon
		});

		google.maps.event.addListener(marker, 'click', (function (marker, i) {
			return function () {
				infowindow.setContent(locations[i][0]);
				infowindow.open(map, marker);
				if (subinfoWindow) subinfoWindow.close();
			};
		})(marker, i));

		markers.push(marker);
	}

	google.maps.event.addListener(map, 'click', function () {
		if (infowindow) infowindow.close();
		if (subinfoWindow) subinfoWindow.close();
	});

	function populateDropdown() {
		const dropdown = document.getElementById('locationsDropdown');
		$("#locationsDropdown").empty();

		const option = document.createElement('option');
		option.value = "";
		option.textContent = "Find Device Location";
		dropdown.appendChild(option);

		locations.forEach((location, index) => {
			const opt = document.createElement('option');
			opt.value = index.toString();
			opt.textContent = location[4];
			dropdown.appendChild(opt);
		});

		dropdown.addEventListener('change', function () {
			const selectedIndex = parseInt(this.value, 10);
			if (!isNaN(selectedIndex)) {
				highlightMarker(selectedIndex);
			}
		});
	}

	function highlightMarker(index) {
		markers.forEach((marker, i) => {
			if (i === index) {
				marker.setAnimation(google.maps.Animation.BOUNCE);
				map.setCenter(marker.getPosition());
				map.setZoom(16);

				infowindow.setContent(locations[i][0]);
				infowindow.open(map, marker);
				if (subinfoWindow) subinfoWindow.close();

				setTimeout(function () {
					marker.setAnimation(null);
				}, 2000);
			} else {
				marker.setAnimation(null);
			}
		});
	}

	populateDropdown();

    // Re-fit on window resize so it doesn't sag downward
	google.maps.event.addDomListener(window, "resize", function () {
		if (!bounds.isEmpty()) map.fitBounds(bounds, pad);
	});

	function isValidLatLng(lat, lng) {
    return (
        !isNaN(lat) &&
        !isNaN(lng) &&
        lat >= -90 && lat <= 90 &&
        lng >= -180 && lng <= 180 &&
        !(lat === 0 && lng === 0) // eliminate default 0,0
    );
}

}
