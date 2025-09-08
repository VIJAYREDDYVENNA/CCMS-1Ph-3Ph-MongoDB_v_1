let error_message = document.getElementById('error-message');
let error_message_text = document.getElementById('error-message-text');
let success_message = document.getElementById('success-message');
let success_message_text = document.getElementById('success-message-text');

const error_toast = bootstrap.Toast.getOrCreateInstance(error_message);
const success_toast = bootstrap.Toast.getOrCreateInstance(success_message);

var group_name = localStorage.getItem("GroupNameValue")
if (group_name == "" || group_name == null) {
    group_name = "ALL";
}
$("#pre-loader").css('display', 'block');
add_sim_list(group_name);

let group_list = document.getElementById('group-list');

group_list.addEventListener('change', function () {
    let group_name = group_list.value;
    if (group_name !== "" && group_name !== null) {
        $("#pre-loader").css('display', 'block');
        add_sim_list(group_name);
    }
});

function refresh_data() {
    let group_name = group_list.value;
    if (group_name !== "" && group_name !== null) {
        add_sim_list(group_name);
    }
}

let currentPage = 1;
let itemsPerPage = document.getElementById('items-per-page').value;

document.getElementById('items-per-page').addEventListener('change', function () {
    itemsPerPage = this.value;
    currentPage = 1;  // Reset to first page
    add_sim_list(group_list.value, currentPage, itemsPerPage);
});

function add_sim_list(group_id, currentPage = 1, itemsPerPage = 100) {
    if (group_id !== "" && group_id !== null) {
        $.ajax({
            type: "POST",
            url: '../sim-card-details/code/sim-card-list.php',
            traditional: true,
            data: {
                GROUP_ID: group_id,
                page: currentPage,
                items_per_page: itemsPerPage
            },
            dataType: "json",
            success: function (response) {
                const sim_list_table = document.getElementById('sim_list_table');
                sim_list_table.innerHTML = ''; // Clear the table
                
                const totalRecords = response.total_records || 0;
                const totalPages = Math.ceil(totalRecords / itemsPerPage);
                
                // Update pagination controls
                updatePaginationControls(totalPages, currentPage);
                
                // Update record count display
                updateSimRecordCount(currentPage, itemsPerPage, totalRecords);

                if (response.data && response.data.length > 0) {
                    // Loop through the data and append rows
                    for (var i = 0; i < response.data.length; i++) {
                        var newRow = document.createElement('tr');
                        newRow.innerHTML =
                            '<td>' + (response.data[i].D_ID || 'N/A') + '</td>' +
                            '<td>' + (response.data[i].CCID || 'N/A') + '</td>' +
                            '<td>' + (response.data[i].IMEI || 'N/A') + '</td>' +
                            '<td>' + (response.data[i].FW || 'N/A') + '</td>' +
                            '<td>' + (response.data[i].PCB || 'N/A') + '</td>' +
                            '<td class="col-size-1">' + (response.data[i].DATE_TIME || 'N/A') + '</td>';
                        sim_list_table.appendChild(newRow);
                    }
                } else {
                    // Show the error message row when no data is found
                    var newRow = document.createElement('tr');
                    newRow.innerHTML = '<td class="text-danger" colspan="6">Device List not found</td>';
                    sim_list_table.appendChild(newRow);
                }
                $("#pre-loader").css('display', 'none');
            },
            error: function (xhr, textStatus, errorThrown) {
                console.log('Error:', xhr.responseText);
                error_message_text.textContent = "Error getting the data: " + textStatus;
                error_toast.show();
                $("#pre-loader").css('display', 'none');
            },
            failure: function () {
                error_message_text.textContent = "Failed to get the data";
                error_toast.show();
                $("#pre-loader").css('display', 'none');
            }
        });
    }
}

// Function to update SIM card record count display
function updateSimRecordCount(currentPage, itemsPerPage, totalRecords) {
    const recordCountElement = document.getElementById('sim-record-count');
    
    if (recordCountElement && totalRecords > 0) {
        const startRecord = (currentPage - 1) * itemsPerPage + 1;
        const endRecord = Math.min(currentPage * itemsPerPage, totalRecords);
        recordCountElement.textContent = `${startRecord}-${endRecord} of ${totalRecords}`;
    } else if (recordCountElement) {
        recordCountElement.textContent = '0-0 of 0';
    }
}

function updatePaginationControls(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';  // Clear the pagination controls

    if (totalPages <= 1) {
        return; // No pagination needed for 1 or 0 pages
    }

    // First button
    if (currentPage > 1) {
        pagination.innerHTML += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">First</a></li>`;
    }

    // Previous button
    let prevDisabled = (currentPage == 1) ? 'disabled' : '';
    pagination.innerHTML += `<li class="page-item ${prevDisabled}"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a></li>`;

    // Page numbers (show max 5 pages)
    const maxPagesToShow = 5;
    const windowSize = Math.floor(maxPagesToShow / 2);
    let startPage = Math.max(1, currentPage - windowSize);
    let endPage = Math.min(totalPages, currentPage + windowSize);

    // Adjust range if we're near the beginning or end
    if (currentPage - windowSize < 1) {
        endPage = Math.min(totalPages, endPage + (windowSize - (currentPage - 1)));
    }
    if (currentPage + windowSize > totalPages) {
        startPage = Math.max(1, startPage - (currentPage + windowSize - totalPages));
    }

    for (let i = startPage; i <= endPage; i++) {
        let activeClass = (i == currentPage) ? 'active' : '';
        pagination.innerHTML += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
    }

    // Next button
    let nextDisabled = (currentPage == totalPages) ? 'disabled' : '';
    pagination.innerHTML += `<li class="page-item ${nextDisabled}"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a></li>`;

    // Last button
    if (currentPage < totalPages) {
        pagination.innerHTML += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages})">Last</a></li>`;
    }
}

function changePage(page) {
    const totalPages = Math.ceil((document.getElementById('sim-record-count').textContent.split(' of ')[1] || 0) / itemsPerPage);
    
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        add_sim_list(group_list.value, currentPage, itemsPerPage);
    }
}