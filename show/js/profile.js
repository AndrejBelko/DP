/**
 * Initializes the DataTable with custom settings.
 * Disables ordering for specific columns and sets default sort order.
 */
let table = new DataTable('#myTable', {
    paging: true,
    columnDefs: [
        { orderable: false, targets: [0, 5, 6, 7, 8] }
    ],
    order: [[1, 'asc']],
    responsive: true,
    autoWidth: false
});

/**
 * Toggles all checkboxes with the class 'checkbox'.
 * If all are checked, unchecks them; otherwise, checks them all.
 * Also updates the button text accordingly.
 */
function checkAllCheckboxes() {
    var checkboxes = document.querySelectorAll('.checkbox');
    var checkAllBtn = document.getElementById('checkAllBtn');

    // Check if all checkboxes are selected
    var allChecked = Array.from(checkboxes).every(function(checkbox) {
        return checkbox.checked;
    });

    // Toggle checkboxes
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = !allChecked;
    });

    // Change button text depending on the action
    checkAllBtn.textContent = allChecked ? 'Check All' : 'Uncheck All';
}

/**
 * Displays a Bootstrap toast message when the element with ID 'token' is clicked.
 */
document.getElementById("token").addEventListener('click', function () {
    const errorToast = new bootstrap.Toast(document.getElementById('tokenToast'));
    errorToast.show();
});

/**
 * Loads GPS data from the server via AJAX using the provided filename.
 * On success, the data is passed to processData().
 *
 * @param {string} filename - The name of the file to load.
 */
function loadGPSData(filename) {

    fetch("load_data.php?file=" + encodeURIComponent(filename))
        .then(response => response.json())
        .then(data => {
            console.log(data)
            if (data.length > 0) {
                processData(data);
            }
        })
        .catch(error => console.error('Error:', error));
}

/**
 * Parses speed data from the raw input.
 *
 * @param {Array} data - Array of data lines, each a sub-array.
 * @returns {Array} Parsed array of objects with time and speed fields.
 */
function parseSpeed(data) {
    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "speed": parseFloat(line[3])};
    });
}

/**
 * Parses altitude (height) data from the raw input.
 *
 * @param {Array} data - Array of data lines, each a sub-array.
 * @returns {Array} Parsed array of objects with time and height fields.
 */
function parseHeight(data) {
    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "height": parseFloat(line[4])};
    });
}

/**
 * Parses heart rate data from the raw input.
 *
 * @param {Array} data - Array of data lines, each a sub-array.
 * @returns {Array} Parsed array of objects with time and heart rate fields.
 */
function parseHeartRate(data) {
    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "hr": parseFloat(line[5])};
    });
}

/**
 * Processes the JSON data from the server, separates and filters the metrics,
 * and triggers rendering of charts for speed, height, and heart rate.
 *
 * @param {Array} jsonData - Parsed JSON array of GPS-related data.
 */
function processData(jsonData) {
    const speedData = parseSpeed(jsonData).filter(d => !isNaN(d.speed));
    const heightData = parseHeight(jsonData).filter(d => !isNaN(d.height));
    const hrData = parseHeartRate(jsonData).filter(d => !isNaN(d.hr));

    if (speedData.length > 0) {
        drawChart("speedchart", speedData, "Speed [km/h]", "speed", "Speed ", " km/h");
    } else{
        document.getElementById("speedchart").style.display = "none";
    }

    if (heightData.length > 0) {
        drawChart("heightchart", heightData, "Altitude [m]", "height", "Altitude ", " m");
    } else{
        document.getElementById("heightchart").style.display = "none";
    }

    if (hrData.length > 0) {
        drawChart("hrchart", hrData, "Heart rate [bpm]", "hr", "Bpm ", " bpm");
    } else{
        document.getElementById("hrchart").style.display = "none";
    }
}

/**
 * Draws a time-series chart using amCharts library.
 *
 * @param {string} idelement - The ID of the DOM element to render the chart in.
 * @param {Array} data - Array of data points with time and a metric (e.g. speed, height).
 * @param {string} name - Title of the chart and metric.
 * @param {string} field - The data field to use for the Y-axis.
 * @param {string} prefix - Prefix string for tooltips.
 * @param {string} suffix - Suffix string for tooltips and Y-axis label.
 */
function drawChart(idelement, data, name, field, prefix, suffix) {
    // Create chart instance
    const chart = am4core.create(idelement, am4charts.XYChart);
    chart.paddingRight = 20;
    chart.dateFormatter.inputDateFormat = "x";

    var scrollbar = new am4charts.XYChartScrollbar();
    // Create axes
    const dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    const valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.title.text = name + " - " + suffix;

    // Create series
    const series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.dateX = "time";
    series.dataFields.valueY = field;
    series.yAxis = valueAxis;
    series.tooltipText = prefix + "{valueY.value}" + suffix;
    series.name = name;
    series.strokeWidth = 2;
    scrollbar.series.push(series);


    // Set up cursor
    chart.cursor = new am4charts.XYCursor();
    chart.cursor.xAxis = dateAxis;
    chart.cursor.behavior = "none";

    // Sort data by timestamp
    data.sort((a, b) => a.time - b.time);

    // Set data for the chart
    chart.data = data;

    chart.scrollbarX = scrollbar;

    chart.legend = new am4charts.Legend();

    // Add chart title
    const title = chart.titles.create();
    title.text = name;
    title.fontSize = 20;
    title.marginBottom = 20;
    title.marginTop = 20;

    // Apply theme
    chart.colors.step = 2;
    chart.exporting.menu = new am4core.ExportMenu();
    chart.exporting.menu.align = "right";
    chart.exporting.menu.verticalAlign = "top";

    // Apply theme
    chart.theme = am4themes_animated;
}