let error_message = document.getElementById('error-message');
let error_message_text = document.getElementById('error-message-text');
let success_message = document.getElementById('success-message');
let success_message_text = document.getElementById('success-message-text');

const error_toast = bootstrap.Toast.getOrCreateInstance(error_message);
const success_toast = bootstrap.Toast.getOrCreateInstance(success_message);


let device_id = localStorage.getItem("SELECTED_ID");
if (!device_id) {
	device_id = document.getElementById('device_id').value;
}

let device_id_list = document.getElementById('device_id');
device_id_list.addEventListener('change', function () {
	device_id = document.getElementById('device_id').value;
	refresh_data();
});

setTimeout(refresh_data, 50);
setInterval(refresh_data, 20000);
function refresh_data() {
	if (typeof update_frame_time === "function") {
		device_id = document.getElementById('device_id').value;
		update_frame_time(device_id);
	}
}

/*function cancel_update(parameter) {
	if (confirm(`Are you sure you want to Cancel the ${parameter} Update ?`)) {
		$("#pre-loader").css('display', 'block');
		$.ajax({
			type: "POST",
			url: '../settings/code/pending-actions.php',
			traditional: true,
			data: { D_ID: device_id, KEY: parameter, CANCEL_PARAMTER: parameter },
			dataType: "json",
			success: function (response) {
				$("#pre-loader").css('display', 'none');

				$("#pending-action-table").html("");
				$("#pending-action-table").html(response);
			},
			error: function (jqXHR, textStatus, errorThrown) {
				$("#pre-loader").css('display', 'none');
				$("#pending-action-table").html("");
				error_message_text.textContent = "Error getting the data";
				error_toast.show();

			}
		});
	}

}
*/
function submitcommands() {
	let command = document.getElementById("command").value.trim();
	let deviceId = document.getElementById("device_id").value;
	document.getElementById("response-message").innerHTML = "";

	if (command === "" || deviceId === "") {
		document.getElementById("response-message").innerHTML = 
		"<div class='alert alert-danger'>Please enter a command and select a device.</div>";
		return;
	}

	let xhr = new XMLHttpRequest();
	xhr.open("POST", "../settings/code/process-command.php", true);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

	xhr.onreadystatechange = function() {
		if (xhr.readyState === 4) {
			let responseDiv = document.getElementById("response-message");
			if (xhr.status === 200) {
				try {
					let res = JSON.parse(xhr.responseText);

					if (res.status === "success") {
						responseDiv.innerHTML = "<div class='alert alert-success'>" + res.message + "</div>";
					} else {
						responseDiv.innerHTML = "<div class='alert alert-danger'>" + res.message + "</div>";
					}

				} catch (e) {
					responseDiv.innerHTML = "<div class='alert alert-danger'>Invalid response format.</div>";
				}
			} else {
				responseDiv.innerHTML = "<div class='alert alert-danger'>Error submitting command.</div>";
			}
		}
	};

	let params = "command=" + encodeURIComponent(command) + "&device_id=" + encodeURIComponent(deviceId);
	xhr.send(params);
}



