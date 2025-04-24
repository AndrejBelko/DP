let table = new DataTable('#myTable', {
    paging: true,
    columnDefs: [
        { orderable: false, targets: [0, 5, 6, 7, 8] }
    ],
    order: [[1, 'asc']],
    responsive: true,
    autoWidth: false
});

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

document.getElementById("token").addEventListener('click', function () {
    const errorToast = new bootstrap.Toast(document.getElementById('tokenToast'));
    errorToast.show();
});

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

function parseSpeed(data) {

    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "speed": parseFloat(line[3])};
    });
}

function parseHeight(data) {

    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "height": parseFloat(line[4])};
    });
}

function parseHeartRate(data) {
    return data.map(line => {
        return {"time": parseInt(line[2]) * 1000, "hr": parseFloat(line[5])};
    });
}

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