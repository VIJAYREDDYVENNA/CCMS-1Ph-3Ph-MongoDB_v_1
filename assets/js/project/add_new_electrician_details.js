$(document).ready(function () {
    let group_name = localStorage.getItem("GroupNameValue") || "ALL";
    $("#pre-loader").css('display', 'block');
    fetchDeviceList(group_name);
    fetchDeviceList1(group_name);

    fetchElectrician_details(group_name);
    fetchElectricians(group_name);
    let group_list = document.getElementById('group-list');

    group_list.addEventListener('change', function () {
        let group_name = group_list.value;
        if (group_name !== "" && group_name !== null) {
            // console.log(group_name);
            $("#pre-loader").css('display', 'block');
            $("#response-message").html(""); // Clear the response message
            fetchDeviceList(group_name);
            fetchDeviceList1(group_name);

            fetchElectrician_details(group_name);
            fetchElectricians(group_name);
        }
    });
});


function fetchDeviceList(group_name) {
    $.ajax({
        type: "POST",
        url: '../add_new_electrician_devices/code/fetch_multiple_devices.php',
        data: { GROUP_ID: group_name },
        dataType: "json",
        success: function (data) {
            let selectElement = $("#multi_selection_device_id");
            selectElement.empty();
            if (Array.isArray(data)) {
                data.forEach(device => {
                    selectElement.append(`<option value="${device.D_ID}">${device.D_NAME}</option>`);
                });
                $("#selected_count").text("0");
            } else {
                console.error("Invalid data format received.");
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error: ", status, error);
        }
    });
}

function fetchDeviceList1(group_name) {
    $.ajax({
        type: "POST",
        url: '../add_new_electrician_devices/code/fetch_multiple_devices.php',
        data: { GROUP_ID: group_name },
        dataType: "json",
        success: function (data) {
            let selectElement = $("#multi_selection_device_id1");
            selectElement.empty();
            if (Array.isArray(data)) {
                data.forEach(device => {
                    selectElement.append(`<option value="${device.D_ID}">${device.D_NAME}</option>`);
                });
                $("#selected_count1").text("0");
            } else {
                console.error("Invalid data format received.");
            }
            // Ensure the count is updated after populating
            // updateSelectedCount();
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error: ", status, error);
        }
    });
}


var interval_Id;
//setTimeout(refresh_data, 50);

// interval_Id=setInterval(refresh_data, 60000);
function refresh_data() {
// $("#pre-loader").css('display', 'block');
	document.getElementById('pre-loader').style.display = 'block';

    let group_name = document.getElementById('group-list').value;
    if (group_name !== "" && group_name !== null) {
        fetchDeviceList(group_name);
        fetchDeviceList1(group_name);
        fetchElectrician_details(group_name)
        fetchElectricians(group_name);
    }
    // $("#pre-loader").css('display', 'none');
}

function submitElectricianForm1() {
    let selectedElectrician = $("#electrician_list").val();
    let selectedDevices = $("#multi_selection_device_id1").val() || [];
    let group_name = document.getElementById('group-list').value;



    if (!selectedElectrician || selectedDevices.length === 0) {
        $("#response-message-new").html('<div class="text-danger">Please select an electrician and devices.</div>');
        return;
    }

    // Retrieve phone number from the selected option's data attribute
    let electricianPhone = $("#electrician_list option:selected").data("phone");

    $.ajax({
        type: "POST",
        url: "../add_new_electrician_devices/code/update_existing_electrician_devices.php",
        data: {
            electrician_name: selectedElectrician,
            electrician_phone: electricianPhone,
            group_id: group_name,
            device_ids: selectedDevices
        },
        dataType: "json",
        success: function (response) {
            if (response.status === "success") {
                $("#response-message-new").html('<div class="text-success">Electrician and devices assigned successfully.</div>');
                // Reset the form or update the UI as needed
                $("#selected_count1").text("0");
                $("#select_all1").prop("checked", false);
                // Optionally, refresh data or clear selection
                refresh_data();
            } else {
                $("#select_all1").prop("checked", false);
                $("#response-message-new").html('<div class="text-danger">' + response.message + '</div>');
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error:", status, error);
            $("#response-message-new").html('<div class="text-danger">An error occurred. Please try again.</div>');
        }
    });
}

function submitElectricianForm() {
    let electricianName = $("#Electrician-name").val().trim();
    let electricianPhone = $("#Electrician-phone").val().trim();
    let selectedDevices = $("#multi_selection_device_id").val() || [];
    let group_name = document.getElementById('group-list').value;

    // if (group_name === "ALL") {
    //     $("#response-message").html('<div class="text-danger">Please select devices from a specific group.</div>');
    //     return;
    // }

    if (electricianName === "" || electricianPhone === "" || selectedDevices.length === 0) {
        $("#response-message").html('<div class="text-danger">All fields are required.</div>');
        return;
    }

    $.ajax({
        type: "POST",
        url: "../add_new_electrician_devices/code/update_electrician_devices.php",
        data: {
            electrician_name: electricianName,
            electrician_phone: electricianPhone,
            group_id: group_name,
            device_ids: selectedDevices
        },
        dataType: "json",
        success: function (response) {
            if (response.status === "success") {
                $("#response-message").html('<div class="text-success">Electrician and devices added successfully.</div>');
                $("#new-Electrician-data")[0].reset();
                $("#selected_count").text("0");
                $("#select_all").prop("checked", false);
                // $("#selected_count1").text("0");
                refresh_data();

            } else {
                $("#select_all").prop("checked", false);
                $("#response-message").html('<div class="text-danger">' + response.message + '</div>');
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error:", status, error);
            $("#response-message").html('<div class="text-danger">An error occurred. Please try again.</div>');
        }
    });
}


function fetchElectrician_details(group_name) {
    $.ajax({
        type: "POST",
        url: "../add_new_electrician_devices/code/fetch_electricians.php",
        data: { group_id: group_name },
        dataType: "json",
        success: function (data) {
            let selectElement = $("#electrician_list");
            selectElement.empty();
            selectElement.append('<option value="">Select Electrician</option>');

            if (Array.isArray(data)) {
                data.forEach(electrician => {
                    // Add name and phone number as data attributes
                    selectElement.append(
                        `<option value="${electrician.name}" 
                                data-phone="${electrician.phone}">
                            ${electrician.name} (${electrician.phone})
                        </option>`
                    );
                });
                updateElectricionListTable(data);

            } else {
                console.error("Invalid data format received.");
            }

        },
        error: function (xhr, status, error) {
            console.error("Error fetching electricians:", error);
        }
    });
}

$("#electrician_list").change(function () {
    let electrician_name = $(this).val();
    if (electrician_name) {
        fetchElectricianDevices(electrician_name);
    }
});


function updateElectricionListTable(data) {
    let tableHTML = `<table class="table text-center table-bordered w-100 SimListSearch">
        <thead class="sticky-top bg-white">
            <tr>
                <th class="table-header1-row-1" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; padding: 0; text-align: center; overflow: hidden;">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <input type="checkbox" id="select_all_electricians_list" style="transform: scale(0.9);" />
                        <span>All</span>
                    </div>
                </th>
                <th class="table-header1-row-1">Electrician Name</th>
                <th class="table-header1-row-1">Phone</th>
                <th class="table-header1-row-1">Actions</th>
            </tr>
        </thead>
        <tbody>`;

    if (Array.isArray(data)) {
        data.forEach(electrician => {
            tableHTML += `<tr>
                <td style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; padding: 0; text-align: center; overflow: hidden;">
                    <input type="checkbox" class="row-checkbox-list" value="${electrician.id}" style="transform: scale(0.9);" />
                </td>
                <td>${electrician.name}</td>
                <td>${electrician.phone}</td>
                <td>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <button class="btn btn-danger btn-sm remove-access" onclick="removeElectrician('${electrician.id}', '${electrician.name}', '${electrician.phone}')">Remove</button>
                    </div>
                </td>
            </tr>`;
        });
    } else {
        tableHTML += `<tr><td colspan="4" class="text-center">No electricians assigned to this group.</td></tr>`;
    }

    tableHTML += `</tbody></table>`;
    document.getElementById("electricianList").innerHTML = tableHTML;

    setupCheckboxListenersList();
}

function toggleRemoveAllButtonList() {
    const selectedCount = document.querySelectorAll(".row-checkbox-list:checked").length;
    document.getElementById("remove_button").disabled = selectedCount === 0;
}

function setupCheckboxListenersList() {
    const selectAll = document.getElementById("select_all_electricians_list");
    const checkboxes = document.querySelectorAll(".row-checkbox-list");

    selectAll.addEventListener("change", function () {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        toggleRemoveAllButtonList();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener("change", () => {
            selectAll.checked = document.querySelectorAll(".row-checkbox-list:checked").length === checkboxes.length;
            toggleRemoveAllButtonList();
        });
    });
}

// function removeElectrician(electricianId, electricianName, electricianPhone) {
//     if (confirm("Are you sure you want to remove  electrician and his access from Devices?")) {
//         fetch("../add_new_electrician_devices/code/remove_electrician.php", {
//             method: "POST",
//             headers: { "Content-Type": "application/x-www-form-urlencoded" },
//             body: `electrician_id=${encodeURIComponent(electricianId)}&electricianName=${encodeURIComponent(electricianName)}&electricianPhone=${encodeURIComponent(electricianPhone)}`
//         })
//             .then(response => response.json())
//             .then(data => {
//                 alert(data.message);
//                 refresh_data();
//                 toggleRemoveAllButtonList(); // Disable the button after refresh

//             })
//             .catch(error => console.error("Error removing electrician:", error));
//     }
// }

let selectedElectrician = {};

function removeElectrician(electricianId, electricianName, electricianPhone) {
    selectedElectrician = { electricianId, electricianName, electricianPhone };
    const removeModal = new bootstrap.Modal(document.getElementById('removeElectricianModal'));
    removeModal.show();
}

document.getElementById('confirmRemoveElectrician').addEventListener('click', function () {
    const { electricianId, electricianName, electricianPhone } = selectedElectrician;

    fetch("../add_new_electrician_devices/code/remove_electrician.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `electrician_id=${encodeURIComponent(electricianId)}&electricianName=${encodeURIComponent(electricianName)}&electricianPhone=${encodeURIComponent(electricianPhone)}`
    })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            refresh_data();
            toggleRemoveAllButtonList();
            const removeModal = bootstrap.Modal.getInstance(document.getElementById('removeElectricianModal'));
            removeModal.hide();
        })
        .catch(error => console.error("Error removing electrician:", error));
});



function RemoveAllElectricions() {
    // FIXED: Remove parseInt() to keep the full ObjectId string
    const selectedIds = Array.from(document.querySelectorAll(".row-checkbox-list:checked"))
        .map(cb => cb.value); // Keep as string, don't convert to integer

    if (selectedIds.length === 0) {
        alert("Please select at least one electrician to remove.");
        return;
    }

    console.log("Selected IDs:", selectedIds); // Debug log to verify IDs

    if (confirm(`Are you sure you want to remove ${selectedIds.length} selected electrician(s)?`)) {
        fetch("../add_new_electrician_devices/code/remove_electrician.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `electrician_ids=${encodeURIComponent(JSON.stringify(selectedIds))}`
        })
            .then(res => res.json())
            .then(data => {
                console.log("Server response:", data); // Debug log
                alert(data.message);
                if (data.status === "success") {
                    refresh_data();
                    toggleRemoveAllButtonList(); // Disable the button after refresh
                }
            })
            .catch(err => {
                console.error("Error removing electricians:", err);
                alert("An error occurred while removing electricians. Please try again.");
            });
    }
}

function fetchElectricianDevices(electrician_name) {
    $.ajax({
        type: "POST",
        url: "../add_new_electrician_devices/code/fetch_electrician_devices.php",
        data: { electrician_name: electrician_name },
        dataType: "json",
        success: function (data) {
            let tableContent = `
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Device ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            if (Array.isArray(data)) {
                data.forEach(device => {
                    tableContent += `
                        <tr>
                            <td>${device.device_id}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="removeDevice(${device.id})">Remove</button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tableContent += `<tr><td colspan="2">No devices found.</td></tr>`;
            }
            tableContent += `</tbody></table>`;
            $("#electrician_devices").html(tableContent);
        },
        error: function (xhr, status, error) {
            console.error("Error fetching devices:", error);
        }
    });
}

function removeDevice(device_id) {
    if (confirm("Are you sure you want to remove this device?")) {
        $.ajax({
            type: "POST",
            url: "../add_new_electrician_devices/code/remove_device.php",
            data: { device_id: device_id },
            success: function (response) {
                alert(response);
                $("#electricion_list").change();
            },
            error: function (xhr, status, error) {
                console.error("Error removing device:", error);
            }
        });
    }
}

// document.addEventListener("DOMContentLoaded", function () {
//     const selectAllCheckbox = document.getElementById("select_all1");
//     const deviceSelect = document.getElementById("multi_selection_device_id1");
//     const selectedCountSpan = document.getElementById("selected_count1");

//     // Make sure the select has the multiple attribute set
//     deviceSelect.setAttribute("multiple", "multiple");

//     // Track scroll position
//     let lastScrollTop = 0;

//     // Function to update the selected count
//     function updateSelectedCount() {
//         const selectedOptions = Array.from(deviceSelect.options).filter(option => option.selected);
//         selectedCountSpan.textContent = selectedOptions.length;

//         // If all options are selected manually, check the select all box
//         selectAllCheckbox.checked = selectedOptions.length === deviceSelect.options.length && deviceSelect.options.length > 0;
//     }

//     // Save scroll position before any interaction
//     deviceSelect.addEventListener("mousedown", function() {
//         lastScrollTop = deviceSelect.scrollTop;
//     });

//     // Custom click handler for individual selections that preserves multiple selection
//     deviceSelect.addEventListener("click", function(e) {
//         if (e.target.tagName === "OPTION") {
//             // The browser's default implementation handles the selection toggling

//             // Restore scroll position after the browser has processed the click
//             setTimeout(() => {
//                 deviceSelect.scrollTop = lastScrollTop;
//                 updateSelectedCount();
//             }, 0);
//         }
//     });

//     // Handle selection changes via keyboard (up/down arrows)
//     deviceSelect.addEventListener("keyup", function() {
//         updateSelectedCount();
//     });

//     // Event listener for Select All checkbox
//     selectAllCheckbox.addEventListener("change", function () {
//         // Save scroll position
//         lastScrollTop = deviceSelect.scrollTop;

//         const options = deviceSelect.options;
//         for (let i = 0; i < options.length; i++) {
//             options[i].selected = this.checked;
//         }

//         // Restore scroll position
//         setTimeout(() => {
//             deviceSelect.scrollTop = lastScrollTop;
//             updateSelectedCount();
//         }, 0);
//     });

//     // Fix for fetchDeviceList1 function to work with this implementation
//     window.fetchDeviceList1 = function(group_name) {
//         $.ajax({
//             type: "POST",
//             url: '../add_new_electrician_devices/code/fetch_multiple_devices.php',
//             data: { GROUP_ID: group_name },
//             dataType: "json",
//             success: function (data) {
//                 let selectElement = $("#multi_selection_device_id1");
//                 selectElement.empty();
//                 if (Array.isArray(data)) {
//                     data.forEach(device => {
//                         selectElement.append(`<option value="${device.D_ID}">${device.D_NAME}</option>`);
//                     });
//                     $("#selected_count1").text("0");
//                     updateSelectedCount();
//                 } else {
//                     console.error("Invalid data format received.");
//                 }
//             },
//             error: function (xhr, status, error) {
//                 console.error("AJAX Error: ", status, error);
//             }
//         });
//     };

//     // Initialize the count
//     updateSelectedCount();
// });

// document.addEventListener("DOMContentLoaded", function () {
//     function fetchElectricians(deviceId) {
//         if (deviceId) {
//             fetch("../add_new_electrician_devices/code/fetch_electricians_by_device.php", {
//                 method: "POST",
//                 headers: { "Content-Type": "application/x-www-form-urlencoded" },
//                 body: `device_id=${deviceId}`
//             })
//                 .then(response => response.json())
//                 .then(data => updateElectricianTable(data))
//                 .catch(error => console.error("Error fetching electricians:", error));
//         } else {
//             document.getElementById("electrician_Names").innerHTML = "";
//         }
//     }

//     function updateElectricianTable(data) {
//         let tableHTML = `<table class="table table-bordered">
//             <thead>
//                 <tr>
//                     <th>Electrician Name</th>
//                     <th>Phone</th>
//                     <th>Actions</th>
//                 </tr>
//             </thead>
//             <tbody>`;

//         if (Array.isArray(data) && data.length > 0) {
//             data.forEach(electrician => {
//                 tableHTML += `<tr>
//                     <td>${electrician.name}</td>
//                     <td>${electrician.phone}</td>
//                     <td>
//                         <button class="btn btn-danger btn-sm remove-access" data-id="${electrician.id}">Remove Access</button>
//                         <button class="btn btn-primary btn-sm edit-electrician" data-id="${electrician.id}" data-bs-toggle="modal" data-bs-target="#editElectricianModal">Edit</button>
//                     </td>
//                 </tr>`;
//             });
//         } else {
//             tableHTML += `<tr><td colspan="3" class="text-center">No electricians assigned to this device.</td></tr>`;
//             tableHTML += `<tr>
//                 <td colspan="3" class="text-center">
//                     <button class="btn btn-primary btn-sm edit-electrician" data-id="new" data-bs-toggle="modal" data-bs-target="#editElectricianModal">Edit / Add Electrician</button>
//                 </td>
//             </tr>`;
//         }

//         tableHTML += `</tbody></table>`;
//         document.getElementById("electrician_Names").innerHTML = tableHTML;
//     }

//     function removeElectricianAccess(electricianId) {
//         if (confirm("Are you sure you want to remove access for this electrician?")) {
//             fetch("../add_new_electrician_devices/code/remove_electrician_access.php", {
//                 method: "POST",
//                 headers: { "Content-Type": "application/x-www-form-urlencoded" },
//                 body: `electrician_id=${electricianId}`
//             })
//                 .then(response => response.json())
//                 .then(data => {
//                     alert(data.message);
//                     fetchElectricians(document.getElementById("device_id").value);
//                 })
//                 .catch(error => console.error("Error removing electrician:", error));
//         }
//     }

//     function loadAvailableElectricians() {
//         fetch("../add_new_electrician_devices/code/fetch_all_electricians.php")
//             .then(response => response.json())
//             .then(response => {
//                 let dropdown = document.getElementById("electricianDropdown");
//                 dropdown.innerHTML = '<option value="">Select an Electrician</option>';

//                 if (response.status === "success" && Array.isArray(response.data) && response.data.length > 0) {
//                     response.data.forEach(electrician => {
//                         let option = document.createElement("option");
//                         option.value = electrician.id;
//                         option.textContent = `${electrician.name} (${electrician.phone})`;
//                         dropdown.appendChild(option);
//                     });
//                 } else {
//                     dropdown.innerHTML = '<option value="">No electricians available</option>';
//                 }
//             })
//             .catch(error => {
//                 console.error("Error loading electricians:", error);
//                 document.getElementById("electricianDropdown").innerHTML = '<option value="">Error loading electricians</option>';
//             });
//     }

//     document.addEventListener("click", function (event) {
//         if (event.target.classList.contains("edit-electrician")) {
//             let electricianId = event.target.dataset.id;
//             document.getElementById("editElectricianId").value = electricianId;
//             loadAvailableElectricians();
//         }
//         if (event.target.classList.contains("remove-access")) {
//             let electricianId = event.target.dataset.id;
//             removeElectricianAccess(electricianId);
//         }
//     });

//     document.getElementById("updateElectrician").addEventListener("click", function () {
//         let deviceId = document.getElementById("device_id").value;
//         let newElectricianId = document.getElementById("electricianDropdown").value;
//         let groupName = document.getElementById("group-list").value;

//         if (!deviceId || !newElectricianId) {
//             alert("Please select an electrician and device.");
//             return;
//         }

//         if (!confirm("Are you sure you want to update the electrician?")) {
//             return;
//         }

//         fetch("../add_new_electrician_devices/code/update_electrician.php", {
//             method: "POST",
//             headers: { "Content-Type": "application/x-www-form-urlencoded" },
//             body: `device_id=${deviceId}&new_electrician_id=${newElectricianId}&group_id=${groupName}`
//         })
//             .then(response => response.json())
//             .then(data => {
//                 alert(data.message);
//                 if (data.status === "success") {
//                     fetchElectricians(deviceId);
//                     new bootstrap.Modal(document.getElementById("editElectricianModal")).hide();
//                 }
//             })
//             .catch(error => {
//                 console.error("Error updating electrician:", error);
//                 alert("Failed to update electrician. Please try again.");
//             });
//     });

//     document.getElementById("device_id").addEventListener("change", function () {
//         fetchElectricians(this.value);
//     });

//     let defaultDeviceId = document.getElementById("device_id").value;
//     if (defaultDeviceId) {
//         fetchElectricians(defaultDeviceId);
//     }
// });


// function fetchElectricians(group_id) {

//     // let group_id = document.getElementById('group-list').value; // Correct variable name

//     if (group_id) {
//         $.ajax({
//             type: "POST",
//             url: "../add_new_electrician_devices/code/fetch_electricians_by_device.php",
//             data: { group_id: group_id },
//             dataType: "json",
//             success: function (data) {
//                 updateElectricianTable(data); // Correct function call
//             },
//             error: function (xhr, status, error) {
//                 console.error("Error fetching electricians:", error);
//             }
//         });
//     } else {
//         // document.getElementById("electrician_Names").innerHTML = "";
//     }
// }

// function updateElectricianTable(data) {
//     let tableHTML = `<table class="table text-center table-bordered  w-100 SimListSearch">
//                             <thead>
//                                 <tr>
//                                     <th class="table-header1-row-1">Device-ID</th>
//                                     <th class="table-header1-row-1">Electrician Name</th>
//                                     <th class="table-header1-row-1">Phone</th>
//                                     <th class="table-header1-row-1">Actions</th>

//                                 </tr>
//                             </thead>
//             <tbody>`;

//     // Create an array of device_ids from electricians to filter unassigned devices
//     let electricianDeviceIds = data.electricians.map(electrician => electrician.device_id);

//     // Render electricians table
//     if (Array.isArray(data.electricians) && data.electricians.length > 0) {
//         data.electricians.forEach(electrician => {
//             tableHTML += `<tr>
//                     <td>${electrician.device_id}</td>
//                     <td>${electrician.name}</td>
//                     <td>${electrician.phone}</td>
//                     <td>
//                         <!-- Responsive buttons -->
//                         <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
//                             <button class="btn btn-danger btn-sm w-100 w-sm-auto remove-access" onclick="removeElectricianAccess(${electrician.id})">Remove</button>
//                             <button class="btn btn-primary btn-sm w-100 w-sm-auto edit-electrician" 
//                                     data-id="${electrician.device_id}" 
//                                     data-bs-toggle="modal" 
//                                     data-bs-target="#editElectricianModal">
//                                 Edit
//                             </button>
//                         </div>
//                     </td>
//                 </tr>`;
//         });
//     } else {
//         // tableHTML += `<tr><td colspan="4" class="text-center">No electricians assigned to this group.</td></tr>`;
//     }

//     // Filter unassigned devices to exclude those that already have an electrician
//     // if (Array.isArray(data.unassigned_devices) && data.unassigned_devices.length > 0) {
//     //     // Filter out devices that are already in the electricians list
//     //     let filteredUnassignedDevices = data.unassigned_devices.filter(device => !electricianDeviceIds.includes(device.device_id));

//     //     // Render unassigned devices if there are any left after filtering
//     //     if (filteredUnassignedDevices.length > 0) {
//     //         filteredUnassignedDevices.forEach(device => {
//     //             tableHTML += `<tr>
//     //                     <td>${device.device_id}</td>
//     //                     <td></td> <!-- Empty name for unassigned devices -->
//     //                     <td></td> <!-- Empty phone for unassigned devices -->
//     //                     <td>
//     //                         <button class="btn btn-success btn-sm w-100 w-sm-auto edit-electrician" data-id="${device.device_id}" data-bs-toggle="modal" data-bs-target="#editElectricianModal">Add Electrician</button>
//     //                     </td>
//     //                 </tr>`;
//     //         });
//     //     } else {
//     //         tableHTML += `<tr><td colspan="4" class="text-center">No unassigned devices available.</td></tr>`;
//     //     }
//     // }

//     tableHTML += `</tbody></table>`;
//     document.getElementById("electricianTable").innerHTML = tableHTML;
// }
let currentPage = 1;
let itemsPerPage = 20; // Default 20 items per page
let totalCount = 0; // Total count from server for pagination

// SERVER-SIDE PAGINATION: Fetches only the requested page data from database
function fetchElectricians(group_id, page = 1, limit = itemsPerPage) {
    if (group_id) {
        // Show loading indicator
        $("#pre-loader").css('display', 'block');
        
        $.ajax({
            type: "POST",
            url: "../add_new_electrician_devices/code/fetch_electricians_by_device.php",
            data: { 
                group_id: group_id,
                page: page,           // Which page to fetch (1, 2, 3...)
                limit: limit          // How many records per page (20, 50, 100...)
            },
            dataType: "json",
            success: function (data) {
                // Server returns ONLY the current page's data (e.g., records 21-40 for page 2)
                totalCount = data.total_count || 0; // Total records in database
                currentPage = page;
                
                updateElectricianTable(data);
                setupPagination();
                
                $("#pre-loader").css('display', 'none');
            },
            error: function (xhr, status, error) {
                console.error("Error fetching electricians:", error);
                $("#pre-loader").css('display', 'none');
            }
        });
    }
}

// Setup pagination controls based on total count from server
function setupPagination() {
    const totalPages = Math.ceil(totalCount / itemsPerPage);

    const paginationEl = document.getElementById('pagination');
    let paginationHTML = '';

    // Previous button
    paginationHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPreviousPage(); return false;">Previous</a>
        </li>
    `;

    // Page numbers (show max 5 page numbers)
    const maxPages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);

    if (endPage - startPage + 1 < maxPages) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <li class="page-item ${currentPage === i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
            </li>
        `;
    }

    // Next button
    paginationHTML += `
        <li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToNextPage(); return false;">Next</a>
        </li>
    `;

    paginationEl.innerHTML = paginationHTML;

    // Setup items per page dropdown change handler
    setupItemsPerPageHandler();
    
    // Update display info
    updateDisplayInfo();
}

// Handle items per page dropdown change - fetches new data from server
function setupItemsPerPageHandler() {
    const itemsPerPageSelect = document.getElementById('items-per-page');
    if (itemsPerPageSelect) {
        // Remove existing listeners to prevent duplicates
        itemsPerPageSelect.removeEventListener('change', handleItemsPerPageChange);
        itemsPerPageSelect.addEventListener('change', handleItemsPerPageChange);
    }
}

function handleItemsPerPageChange(event) {
    itemsPerPage = parseInt(event.target.value);
    currentPage = 1; // Reset to page 1 when changing items per page
    
    // Get current group and fetch new data from server
    const currentGroupId = localStorage.getItem("GroupNameValue") || "ALL";
    fetchElectricians(currentGroupId, currentPage, itemsPerPage);
}

// Navigate to specific page - fetches that page's data from server
function goToPage(pageNumber) {
    const totalPages = Math.ceil(totalCount / itemsPerPage);
    
    if (pageNumber >= 1 && pageNumber <= totalPages) {
        const currentGroupId = localStorage.getItem("GroupNameValue") || "ALL";
        // This will fetch records (pageNumber-1)*itemsPerPage to pageNumber*itemsPerPage
        fetchElectricians(currentGroupId, pageNumber, itemsPerPage);
    }
    return false;
}

// Go to next page - fetches next set of records from server
function goToNextPage() {
    const totalPages = Math.ceil(totalCount / itemsPerPage);
    if (currentPage < totalPages) {
        goToPage(currentPage + 1);
    }
    return false;
}

// Go to previous page - fetches previous set of records from server  
function goToPreviousPage() {
    if (currentPage > 1) {
        goToPage(currentPage - 1);
    }
    return false;
}

// Update table with current page data (received from server)
function updateElectricianTable(data) {
    let tableHeader = `
        <thead>
            <tr>
                <th class="table-header1-row-1" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; padding: 0; text-align: center; overflow: hidden;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <input type="checkbox" id="select_all_electricians"  />
                        <span>All</span>
                    </div>
                </th>
                <th class="table-header1-row-1">Device-ID</th>
                <th class="table-header1-row-1">Electrician Name</th>
                <th class="table-header1-row-1">Phone</th>
                <th class="table-header1-row-1">Actions</th>
            </tr>
        </thead>`;

    let tableBody = '<tbody>';

    // Render current page electricians (only 20 records from server)
    if (Array.isArray(data.electricians) && data.electricians.length > 0) {
        data.electricians.forEach(electrician => {
            tableBody += `
                <tr>
                    <td style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; padding: 0; text-align: center; overflow: hidden;">
                        <input type="checkbox" class="row-checkbox" value="${electrician.id}" />
                    </td>
                    <td>${electrician.device_id}</td>
                    <td>${electrician.name}</td>
                    <td>${electrician.phone}</td>
                    <td>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                            <button class="btn btn-danger btn-sm w-100 w-sm-auto remove-access" 
                                onclick="removeElectricianAccess('${electrician.id}')">Remove</button>
                            <button class="btn btn-primary btn-sm w-100 w-sm-auto edit-electrician" 
                                data-id="${electrician.device_id}" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editElectricianModal">
                                Edit
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    } else {
        tableBody += '<tr><td colspan="5" class="text-center">No electricians found for this page.</td></tr>';
    }

    tableBody += '</tbody>';
    document.getElementById("electricianTable").innerHTML = tableHeader + tableBody;

    // Attach event listeners after table update
    setupCheckboxListeners();
}

// OPTIMIZED: Debounced display info update
function updateDisplayInfo() {
    // Clear previous timeout to debounce rapid updates
    if (window.displayInfoTimeout) {
        clearTimeout(window.displayInfoTimeout);
    }
    
    window.displayInfoTimeout = setTimeout(() => {
        const start = totalCount === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(currentPage * itemsPerPage, totalCount);

        const rangeInfo = document.getElementById('range-info');
        if (rangeInfo) {
            rangeInfo.textContent = `${start}-${end} of ${totalCount}`;
        }
    }, 50); // Small delay to batch rapid updates
}

// Initialize items per page from dropdown
function initializeItemsPerPage() {
    const itemsPerPageSelect = document.getElementById('items-per-page');
    if (itemsPerPageSelect) {
        itemsPerPage = parseInt(itemsPerPageSelect.value) || 20;
    }
}

// Backward compatibility functions (keeping old function names)
function changePage(pageNumber) {
    return goToPage(pageNumber);
}

// Document ready
$(document).ready(function () {
    // Initialize items per page value
    initializeItemsPerPage();
    
    console.log("Pagination initialized - Server-side pagination enabled");
    console.log(`Default: ${itemsPerPage} items per page`);
});








function setupCheckboxListeners() {
    const selectAllCheckbox = document.getElementById("select_all_electricians"); // Updated ID
    const rowCheckboxes = document.querySelectorAll(".row-checkbox");

    selectAllCheckbox.addEventListener("change", function () {
        rowCheckboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
        toggleRemoveAllButton();
    });

    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", function () {
            selectAllCheckbox.checked = document.querySelectorAll(".row-checkbox:checked").length === rowCheckboxes.length;
            toggleRemoveAllButton();
        });
    });
}

function toggleRemoveAllButton() {
    const selectedCount = document.querySelectorAll(".row-checkbox:checked").length;
    document.getElementById("removeAllBtn").disabled = selectedCount === 0;
}

function removeSelectedElectricians() {
    const selectedCheckboxes = document.querySelectorAll(".row-checkbox:checked");
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

    if (selectedIds.length === 0) return;

    if (confirm("Are you sure you want to remove the selected electricians?")) {
        fetch("../add_new_electrician_devices/code/remove_electrician_access.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `electrician_ids=${JSON.stringify(selectedIds)}`
        })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                document.getElementById("removeAllBtn").disabled = true;

                // Uncheck all checkboxes after deletion
                document.querySelectorAll(".row-checkbox:checked").forEach(cb => cb.checked = false);
                refresh_data(); // Refresh the table after deletion

                // Hide or disable the Remove All button

            })
            .catch(error => console.error("Error removing electricians:", error));
    }
}


// function removeElectrician(electricianId) {
//     // console.log(electricianId);
//     if (confirm("Are you sure you want to remove access for this electrician?")) {
//         fetch("../add_new_electrician_devices/code/remove_electrician.php", {
//             method: "POST",
//             headers: { "Content-Type": "application/x-www-form-urlencoded" },
//             body: `electrician_id=${electricianId}`
//         })
//             .then(response => response.json())
//             .then(data => {
//                 alert(data.message);
//                 var group_id = document.getElementById('group-list').value;
//                 refresh_data();
//                 // fetchElectricians(group_id);
//             })
//             .catch(error => console.error("Error removing electrician:", error));
//     }
// }



function removeElectricianAccess(electricianId) {
    // console.log(electricianId);
    if (confirm("Are you sure you want to remove access for this electrician?")) {
        fetch("../add_new_electrician_devices/code/remove_electrician_access.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `electrician_id=${electricianId}`
        })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                var group_id = document.getElementById('group-list').value;
                refresh_data();
                // fetchElectricians(group_id);
            })
            .catch(error => console.error("Error removing electrician:", error));
    }
}

function loadAvailableElectricians() {
    fetch("../add_new_electrician_devices/code/fetch_all_electricians.php")
        .then(response => response.json())
        .then(response => {
            let dropdown = document.getElementById("electricianDropdown");
            dropdown.innerHTML = '<option value="">Select an Electrician</option>';

            if (response.status === "success" && Array.isArray(response.data) && response.data.length > 0) {
                response.data.forEach(electrician => {
                    let option = document.createElement("option");
                    option.value = electrician.id;
                    option.textContent = `${electrician.name} (${electrician.phone})`;
                    dropdown.appendChild(option);
                });
            } else {
                dropdown.innerHTML = '<option value="">No electricians available</option>';
            }
        })
        .catch(error => {
            console.error("Error loading electricians:", error);
            document.getElementById("electricianDropdown").innerHTML = '<option value="">Error loading electricians</option>';
        });
}

document.addEventListener("click", function (event) {
    if (event.target.classList.contains("edit-electrician")) {
        let deviceId = event.target.dataset.id; // Get device_id from the button
        document.getElementById("editElectricianId").value = deviceId; // Store device_id in hidden input
        document.getElementById("deviceIdDisplay").innerText = deviceId; // Show device_id in the modal (optional)

        loadAvailableElectricians(); // Call to load electricians for selection
    }
});


document.getElementById("updateElectrician").addEventListener("click", function () {
    let deviceId = document.getElementById("editElectricianId").value; // Get device_id from hidden input
    let newElectricianId = document.getElementById("electricianDropdown").value;
    let groupName = document.getElementById("group-list").value;

    if (!deviceId || !newElectricianId) {
        alert("Please select an electrician and device.");
        return;
    }

    if (!confirm("Are you sure you want to update the electrician?")) {
        return;
    }

    fetch("../add_new_electrician_devices/code/update_electrician.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `device_id=${deviceId}&new_electrician_id=${newElectricianId}&group_id=${groupName}`
    })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === "success") {
                var group_id = document.getElementById('group-list').value;
                fetchElectricians(group_id);
                // Show success message or alert here
                // alert("Electrician details updated successfully.");
            }
        })
        .catch(error => {
            console.error("Error updating electrician:", error);
            alert("Failed to update electrician. Please try again.");
        });
});


// let group_list = document.getElementById('group-list');

// group_list.addEventListener('change', function () {
//     var group_id = document.getElementById('group-list').value;

//     fetchElectricians(group_id);
// });
// fetchElectricians();

// let defaultDeviceId = document.getElementById("device_id").value;
// if (defaultDeviceId) {
//     fetchElectricians(defaultDeviceId);
// }

// Function to filter the table based on search input
// Function to filter the table based on the search input
var interval_Id_1 = interval_Id;

function filterTable() {
    clearInterval(interval_Id);

    let searchTerm = document.getElementById("searchBar").value.toLowerCase(); // Get the search term
    let table = document.getElementById("electricianTable");
    let rows = table.getElementsByTagName("tr");
     let group_list = document.getElementById('group-list');
    let group_id = group_list.value;
    console.log(searchTerm+" "+group_id);
    if (group_id) {
        $.ajax({
            type: "POST",
            url: "../add_new_electrician_devices/code/serach_electrician.php",
            data: { group_id: group_id,searchTerm:searchTerm },
            dataType: "json",
            success: function (data) {
                electriciansData = data.electricians || []; // Store data globally
                currentPage = 1; // Reset to first page on new data
                updateElectricianTable(data);
                setupPagination();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching electricians:", error);
            }
        });
    }
}
// function filterTable() {
//     clearInterval(interval_Id);

//     let searchTerm = document.getElementById("searchBar").value.toLowerCase(); // Get the search term
//     let table = document.getElementById("electricianTable");
//     let rows = table.getElementsByTagName("tr");

//     // Skip if there's no search term or no rows
//     if (!rows.length) return;

//     // Loop through all table rows and hide those that don't match the search term
//     for (let i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
//         let cells = rows[i].getElementsByTagName("td");

//         // Make sure we have enough cells before accessing them
//         if (cells.length < 3) continue;

//         // cells[1] is Device-ID, cells[2] is Electrician Name
//         let deviceID = cells[1].textContent.toLowerCase();
//         let electricianName = cells[2].textContent.toLowerCase();

//         // Check if the search term matches either the device ID or the electrician name
//         if (
//             deviceID.indexOf(searchTerm) > -1 ||  // If search term matches device ID
//             electricianName.indexOf(searchTerm) > -1 // If search term matches electrician name
//         ) {
//             rows[i].style.display = ""; // Show the row
//         } else {
//             rows[i].style.display = "none"; // Hide the row
//         }
//     }
// }




document.getElementById("searchBar").addEventListener("input", function () {
    // Call the search function on every input change

    // Restart the interval if input is cleared
    if (this.value.trim() === "") {
        // clearInterval(interval_Id); // Clear any existing interval
        let group_list = document.getElementById('group-list');

        let group_name = group_list.value;

        refresh_data(group_name);

        // interval_Id1 = setInterval(refresh_data, 60000); // Restart interval

    }
});



