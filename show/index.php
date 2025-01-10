<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING);
require_once('php/config.php');
session_start();

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}


$sql = "
SELECT 
    SCHEMA_NAME 
FROM INFORMATION_SCHEMA.SCHEMATA 
WHERE SCHEMA_NAME NOT IN (
    SELECT meno 
    FROM hashcode.pouzivatel
)
  and (SCHEMA_NAME NOT LIKE  '%mysql%'
  and SCHEMA_NAME NOT LIKE  '%information_schema%'  
  and SCHEMA_NAME NOT LIKE  '%performance_schema%' 
  and SCHEMA_NAME NOT LIKE  '%sys%' 
  and SCHEMA_NAME NOT LIKE  '%hashcode%')
";

$stmt = $db->prepare($sql);

$stmt->execute();

if ($stmt->execute()) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $schemaNames = array_column($rows, 'SCHEMA_NAME');
}

unset($stmt);
unset($db);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.png"/>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="Bio inspired tracks comparison" name="description">
    <meta content="mComputing.eu, Maros Cavojsky" name="author">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        ::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            border-radius: 10px;
            background: #ccc;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
    </style>

    <?php
    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    $files = scandir("center");
    $datasets = array();
    foreach ($files as $key => $value) {
        if (endsWith($value, ".json")) {
            array_push($datasets, json_decode(file_get_contents("center/$value"), true));
        }
    }

    $queryDB = (isset($_GET['db']) && $_GET['db'] != "") ? $_GET['db'] : $_SESSION['username'];
    $DBinfo = array();

    foreach ($datasets as $key => $value) {
        if (isset($_SESSION["username"]) && $queryDB == $value['dbname']) {
            if (file_exists("center/$queryDB.json")) {
                $DBinfo = $value;
                break;
            }
        } else if (!isset($_SESSION["username"])) {
            if (isset($_GET['db'])) {
                $name = $_GET['db'];
                if ($name == $value['dbname']) {
                    $DBinfo = $value;
                    $queryDB = $value['dbname'];
                    break;
                }
            } else {
                if (in_array($value['dbname'], $schemaNames)) {
                    $DBinfo = $value;
                    $queryDB = $value['dbname'];
                    break;
                }
            }
        }
    }
    if (empty($DBinfo)) {
        $value = array(
            "center" => array(
                "lat" => 48.151965,
                "lon" => 17.072995
            ),
            "title" => " ",
            "dbname" => " ",
            "attribution" => ""
        );
        $DBinfo = $value;
        $queryDB = $value['dbname'];
    }
    ?>


    <title>COhaveSearch - <?php echo $DBinfo['title']; ?></title>

    <link href="css/toastr.css" rel="stylesheet"/>
    <script src="js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
          integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
            integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
            crossorigin=""></script>
    <script src="js/jquery.min.js"></script>
    <link rel="stylesheet" href="css/L.Control.Sidebar.css"/>
    <script src="js/L.Control.Sidebar.js"></script>
    <script src="https://canvasjs.com/assets/script/jquery-1.11.1.min.js"></script>
    <script src="https://canvasjs.com/assets/script/jquery.canvasjs.min.js"></script>
    <script src="js/toastr.js"></script>

    <link href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.css">
    <script src="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.js"></script>
    <script src="https://npmcdn.com/leaflet-geometryutil"></script>
    <script src="js/geohash.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">

    <style>
        html, body {
            height: 100%;
        }

        body {
            text-align: center;
        }

        #map {
            height: calc(100% - 65px);
            width: 100%;
            z-index: 1;
        }

        .navbar {
            z-index: 2;
        }

        .alg-form input {
            width: 65px !important;
        }

        html * {
            /* font-size: 14px; */
            color: #2020131;
            font-family: 'Roboto', sans-serif;

        }

        .btn:focus, .btn:active:focus, .btn.active:focus,
        .btn.focus, .btn:active.focus, .btn.active.focus {
            outline: none;
        }

        header {
            height: 65px;
        }

    </style>
</head>

<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left side navigation links -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active fs-5" aria-current="page" href="index.php">Mapa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="php/profile.php">Profil</a>
                    </li>
                    <!--                    <li class="nav-item">-->
                    <!--                        <a class="nav-link fs-5" href="upload.php">Nahranie nových trajektórií</a>-->
                    <!--                    </li>-->
                    <!--                    <li class="nav-item">-->
                    <!--                        <a class="nav-link fs-5" href="pdf.php">Príručka</a>-->
                    <!--                    </li>-->
                    <?php if (isset($_SESSION['username']) && $_SESSION['loggedin'] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/logout.php">Odhlásiť sa</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/register.php">Registrovať sa</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/login.php">Prihlásiť sa</a>
                        </li>
                    <?php endif; ?>
                </ul>

            </div>
        </div>
    </nav>
</header>


<div id='map'></div>

<div class="container" id="sidebar">
    <div class="row">
        <div class="col-12">
            <a class="btn-close float-end" onclick="sidebar.hide()"></a>
            <form class="mt-3">
                <h4>Dataset: <?php echo $DBinfo['title']; ?></h4>
                <h6>
                    <strong>Boxes drawn: <span id="psize">0</span></strong>
                </h6>

                <!-- Form Section -->
                <div class="row g-3 my-3">
                    <div class="col-md-6">
                        <label for="gsStart" class="form-label">Start ≤</label>
                        <input type="number" id="gsStart" class="form-control" placeholder="1" value="3" min="1">
                    </div>
                    <div class="col-md-6">
                        <label for="gsEnd" class="form-label">End &ge;</label>
                        <input type="number" id="gsEnd" class="form-control" placeholder="1" value="2">
                    </div>
                </div>
                <div class="row g-3 my-3">
                    <div class="col-md-6">
                        <label for="gsMatch" class="form-label">Matches &ge;</label>
                        <input type="number" id="gsMatch" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="gaps" class="form-label">Gaps &le;</label>
                        <input type="number" id="gaps" class="form-control">
                    </div>
                </div>

                <div class="text-center my-3">
                    <button type="button" class="btn btn-success" onclick="findPaths()">Apply</button>
                </div>
            </form>

            <hr>
        </div>
    </div>

    <!-- Toggle Switch -->
    <div class="row">
        <div class="col-12 text-center my-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleSwitch">
                <label class="form-check-label" for="toggleSwitch">Mapmatch</label>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div class="row">
        <div class="col-12">
            <div id="resultsbox" style="display: none;">
                <p>
                    Found <span id="totalfound"></span> -
                    <button class="btn btn-secondary btn-sm" onclick="showAll()">Show all</button>
                </p>
                <div style="height: fit-content; overflow-y:auto;">
                    <table class="table table-hover table-striped" id="datatable">
                        <tbody id="results"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-auto py-3">
        <div class="text-center">
            <?php
            if (isset($_SESSION["username"])) {
                foreach ($datasets as $key => $value) {
                    if ($value['dbname'] == $_SESSION['username'] || in_array($value['dbname'], $schemaNames)) {
                        echo "<a href='?db=" . $value['dbname'] . "'>" . $value['title'] . "</a>";
                        if ($key < count($datasets)) {
                            echo " | ";
                        }
                    }
                }
            } else {
                foreach ($schemaNames as $value) {
                    echo "<a href='?db=" . $value . "'>" . $value . "</a> |";
                }
            }
            ?>
            <p class="small">Algorithm &copy; <a href="https://mcomputing.eu" class="text-decoration-none">mcomputing.eu</a></p>
            <a href="php/nwa.php" class="text-decoration-none">Needleman–Wunsch</a> |
            <a href="php/swa.php" class="text-decoration-none">Smith–Waterman</a>
        </div>
    </footer>
</div>

<div class="container" id="sidebarRight">
    <div class="row">
        <div class="col-12">
            <a class="btn-close float-end" onclick="sidebarRight.hide()"></a>
        </div>
        <div class="row">
            <div class="col-lg-12" id="chartContainer" style="height: 300px; width: 90%; margin-top: 5%;"></div>
        </div>
        <div class="row">
            <div class="col-lg-12" id="chart2Container" style="height: 300px; width: 90%; margin-top: 5%;"></div>
        </div>
        <div style="position: relative; bottom: 0%; margin-top:10%">
            <div class="col-lg-12">
                <h1 style="font-size: 18px;">Click on individual columns to show or hide selected values on the map</h1>
            </div>
        </div>
    </div>
</div>


<script>
    var chart = new CanvasJS.Chart("chartContainer", {
        theme: "light2", // "light1", "light2", "dark1", "dark2"
        animationEnabled: true,
        zoomEnabled: true,
        title: {
            text: "Trajectories passing through box",
            fontFamily: "Roboto, sans-serif",
            fontWeight: "lighter",
            fontSize: 22
        },
        axisX: {
            title: "Box number",
            valueFormatString: "#",
            interval: 1
        },
        axisY: {
            title: "Number of trajectories",
            valueFormatString: "#",
            interval: 10
        },
        data: [{
            type: "column",
            color: "#1959d1",
            click: function (e) {
                showSelectedColumnPerPoint(e.dataPoint.x);
            },
            dataPoints: dps
        }]
    });

    var chart2 = new CanvasJS.Chart("chart2Container", {
        theme: "light2", // "light1", "light2", "dark1", "dark2"
        animationEnabled: true,
        zoomEnabled: true,
        title: {
            text: "Trajectories including amount of boxes",
            fontFamily: "Roboto, sans-serif",
            fontWeight: "lighter",
            fontSize: 22
        },
        axisX: {
            title: "Box count",
            valueFormatString: "#",
            interval: 1
        },
        axisY: {
            title: "Number of trajectories",
            valueFormatString: "#",
            interval: 10
        },
        data: [{
            type: "column",
            color: "#1959d1",
            click: function (e) {
                showSelectedColumn(e.dataPoint.x);
            },
            dataPoints: dps2
        }]
    });


    var queryDB = "<?php echo $queryDB; ?>";
    console.log(queryDB)
    var map = L.map('map').setView([<?php echo $DBinfo['center']['lat'] . ", " . $DBinfo['center']['lon'];?>], 13);

    var gdata;
    var resultGroup = [];
    var isDrawingPattern = false;
    var geoPattern = null, geoResult = null, geoAllResult = null;
    var distance = 0;
    var lastLat = 0;
    var lastLon = 0;
    var isFirstToDisplay = 0;
    var lastpos = null;
    var graphButton;
    var interpolated = [];
    var boxes = [];
    var graph_x_axis = [];
    var graph_y_axis = [];
    var graph2_x_axis = [];
    var graph2_y_axis = [];
    var dps = [];
    var dps2 = [];
    var svg = [];
    var testSvg = [];
    var gdataPrintResult = [];
    var gdataPrintResultPerPoint = [];
    var fieldsTicked = [];
    var fieldsTickedPerPoint = [];
    var all_trajectories = true;
    var mapmatched = '0';
    var line_colors = [
        '#056fe8', '#fa05fa', '#f2f202',
        '#02f246', '#9a05eb', '#f7c600',
        '#f08902', '#94793e', '#787369'
    ];
    const toggleSwitch = document.getElementById('toggleSwitch');

    var patternStyle = {
        "color": "#e20fcd",
        "weight": 5
    };

    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": false,
        "progressBar": true,
        "positionClass": "toast-top-center",
        "preventDuplicates": true,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }

    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoibWFyb3NjIiwiYSI6ImNrb3B4b2QxeTBweG0ycWw0bTBiYWVwcWgifQ.g79td3RKqhZ9DEOLF9nGlA', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
            'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>, <?php echo $DBinfo['attribution']; ?>',
        id: 'mapbox/streets-v12',
        tileSize: 512,
        zoomOffset: -1
    }).addTo(map);

    var sidebar = L.control.sidebar('sidebar', {
        position: 'left'
    });

    var sidebarRight = L.control.sidebar('sidebarRight', {
        position: 'right'
    });

    map.addControl(sidebar);
    map.addControl(sidebarRight);

    L.easyButton('fa-repeat', function (btn, map) {
        clearSearch();
        // location.reload();
        showAllPaths()
    }).addTo(map);

    L.easyButton('fa-list', function (btn, map) {
        sidebar.toggle();
    }).addTo(map);

    graphButton = L.easyButton('fa-bar-chart', function (btn, map) {
        sidebarRight.toggle();
    });

    //SVG string to DOM element
    function render_xml(id, xml_string) {
        var doc = new DOMParser().parseFromString(xml_string, 'application/xml');
        var el = document.getElementById(id)
        el.appendChild(
            el.ownerDocument.importNode(doc.documentElement, true)
        )
    }

    function showSelectedColumn(point) {
        var gdataPrint = [];
        var foundMatch = 0;
        fieldsTickedPerPoint = [];

        for (var i = 0; i < chart2.options.data[0].dataPoints.length; i++) {
            if (chart2.options.data[0].dataPoints[i].x == point) {
                if (chart2.options.data[0].dataPoints[i].color == "#538efc") {
                    chart2.options.data[0].dataPoints[i].color = "#1959d1";
                } else {
                    chart2.options.data[0].dataPoints[i].color = "#538efc";
                }
            }
        }
        chart2.render();

        for (var i = 0; i < chart.options.data[0].dataPoints.length; i++) {
            chart.options.data[0].dataPoints[i].color = "#1959d1";
        }
        chart.render();

        for (var i in fieldsTicked) {
            if (fieldsTicked[i] == point && foundMatch == 0) {
                fieldsTicked.splice(i, 1);
                foundMatch = 1;
            }
        }
        if (foundMatch == 0) {
            fieldsTicked.push(point)
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }

        $(".result-item").hide();

        for (var j in fieldsTicked) {
            for (var i in gdata) {
                if (gdata[i][5].length == fieldsTicked[j]) {
                    gdataPrint.push(gdata[i]);
                    $("#" + gdata[i][0]).show();
                    $("#totalfound").html(gdataPrint.length);
                }
            }
        }

        if (gdataPrint.length == 0) {
            $(".result-item").show();
            geoAllResult.addTo(map);
            $("#totalfound").html(gdata.length);
        }

        geojson = {
            "type": "FeatureCollection",
            "features": gdataPrint.map(function myFunction(item) {
                return JSON.parse(item[6]);
            })
        };

        gdataPrintResult = L.geoJSON(geojson, {
            style: function (feature, layer) {
                return {weight: 4, opacity: 0.6, color: line_colors[Math.floor(Math.random() * 4)], fillOpacity: 0.6};
            }
        });
        gdataPrintResult.addTo(map);
    }

    function showSelectedColumnPerPoint(point) {
        var gdataPrint = [];
        var foundMatch = 0;
        fieldsTicked = [];

        for (var i = 0; i < chart.options.data[0].dataPoints.length; i++) {
            if (chart.options.data[0].dataPoints[i].x == point) {
                if (chart.options.data[0].dataPoints[i].color == "#538efc") {
                    chart.options.data[0].dataPoints[i].color = "#1959d1";
                } else {
                    chart.options.data[0].dataPoints[i].color = "#538efc";
                }
            }
        }
        chart.render();

        for (var i = 0; i < chart2.options.data[0].dataPoints.length; i++) {
            chart2.options.data[0].dataPoints[i].color = "#1959d1";
        }
        chart2.render();

        for (var i in fieldsTickedPerPoint) {
            if (fieldsTickedPerPoint[i] == point && foundMatch == 0) {
                fieldsTickedPerPoint.splice(i, 1);
                foundMatch = 1;
            }
        }
        if (foundMatch == 0) {
            fieldsTickedPerPoint.push(point)
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }

        $(".result-item").hide();
        for (var j in fieldsTickedPerPoint) {
            for (var i in gdata) {
                for (var k in gdata[i][5]) {
                    if (gdata[i][5][k] == fieldsTickedPerPoint[j]) {
                        gdataPrint.push(gdata[i]);
                        $("#" + gdata[i][0]).show();
                        $("#totalfound").html(gdataPrint.length);
                    }
                }
            }
        }

        if (gdataPrint.length == 0) {
            $(".result-item").show();
            geoAllResult.addTo(map);
            $("#totalfound").html(gdata.length);
        }

        geojson = {
            "type": "FeatureCollection",
            "features": gdataPrint.map(function myFunction(item) {
                return JSON.parse(item[6]);
            })
        };

        gdataPrintResultPerPoint = L.geoJSON(geojson, {
            style: function (feature, layer) {
                return {weight: 4, opacity: 0.6, color: line_colors[Math.floor(Math.random() * 4)], fillOpacity: 0.6};
            }
        });
        gdataPrintResultPerPoint.addTo(map);
    }

    function clearSearch() {
        sidebarRight.hide();
        graphButton.removeFrom(map);
        for (var i in boxes) {
            boxes[i].removeFrom(map);
        }
        boxes = [];
        interpolated = [];
        dps = [];
        dps2 = [];
        $("#psize").html("0");
        $("#resultsbox").hide();
        $("#chartContainer").hide()
        $("#chart2Container").hide()
        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
    }

    function addToPath(latlng) {
        var hash = encodeGeoHash(latlng.lat, latlng.lng, 7);
        interpolated.push(hash);
        $("#psize").html(interpolated.length);
        var box = decodeGeoHash(hash);
        var rect = L.rectangle([[box['latitude'][0], box['longitude'][0]], [box['latitude'][1], box['longitude'][1]]], {
            color: "#eb3a05",
            weight: 2,
            fillOpacity: 0
        });
        rect.bindPopup("Box " + (boxes.length + 1) + " <br> <a href='#' onclick='removeBox(" + boxes.length + ")'>Remove</a>");
        boxes.push(rect);
        rect.addTo(map);


        $("#gsMatch").val(Math.max(1, Math.floor(boxes.length * 0.8)));
        $("#gaps").val(Math.round(boxes.length * 0.2));
    }

    var checkbox = document.getElementById('toggleSwitch');
    checkbox.addEventListener('change', function () {
        if (checkbox.checked) {
            mapmatched = '1'
        } else {
            mapmatched = '0'
        }
        findPaths()
        showAllPaths()
    });


    function onMapClick(e) {
        addToPath(e.latlng);
        all_trajectories = false;
        findPaths();
    }

    function onMapDoubleClick(e) {

    }

    function onMapMouseMove(e) {

    }

    map.on('click', onMapClick);

    function drawTrackScaledWithoutZoom(coordinates, svgWidth, svgHeight, i) {
        // Find the minimum and maximum values for x and y coordinates.
        let minX = coordinates[0][0], maxX = coordinates[0][0];
        let minY = coordinates[0][1], maxY = coordinates[0][1];
        coordinates.forEach(coord => {
            if (coord[0] < minX) minX = coord[0];
            if (coord[0] > maxX) maxX = coord[0];
            if (coord[1] < minY) minY = coord[1];
            if (coord[1] > maxY) maxY = coord[1];
        });

        // Calculate the width and height of the viewable area.
        const width = maxX - minX;
        const height = maxY - minY;

        // Calculate the scaling factor for the polyline.
        const xScale = svgWidth / width;
        const yScale = svgHeight / height;
        const scale = Math.min(xScale, yScale);

        // Calculate the points for the SVG polyline by scaling the polyline coordinates.
        const scaledPoints = coordinates.map(coord => {
            const x = (coord[0] - minX) * scale;
            const y = (coord[1] - minY) * scale;
            return `${x},${y}`;
        }).join(' ');

        // Create an SVG element and set its dimensions.
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', svgWidth);
        svg.setAttribute('height', svgHeight);

        // Create a polyline element and set its attributes.
        const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        polyline.setAttribute('points', scaledPoints);
        polyline.setAttribute('stroke', '#1959d1');
        polyline.setAttribute('stroke-width', '2');
        polyline.setAttribute('fill', 'none');

        // Append the polyline element to the SVG element and the SVG element to the DOM.
        divname = "mapArea" + i;
        svg.appendChild(polyline);
        document.getElementById(divname).replaceChildren(svg);
    }

    function showResults() {

        graphButton.addTo(map);
        $("#resultsbox").show();
        $("#chartContainer").show();
        $("#chart2Container").show();
        fieldsTicked = [];
        fieldsTickedPerPoint = [];

        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }

        var x = "";
        graph_array = [];
        graph2_array = [[], []];
        graph_x_axis = [];
        graph_y_axis = [];
        graph2_x_axis = [];
        graph2_y_axis = [];
        point = 1;
        count = 0;
        match_number = 0;
        match_count = 0;

        for (var i in gdata) {
            //x+= "<tr><td><div id='mapArea"+i+"' style='width:80px;height:80px;' onclick=\"showTrack("+i+")\"></div></td><td>"+gdata[i][1]+"</td><td>"+gdata[i][2]+"</td><td>"+gdata[i][3]+"</td><td>"+gdata[i][4]+"</td></tr>";
            //x+= "<tr><td><div id='mapArea"+i+"' style='width:80px;height:80px;' onclick=\"showTrack("+i+")\"></div></td><td><div onclick=\"showTrack("+i+")\">Track ID: <b>"+gdata[i][0]+"</b><br>Matched fields: <b>"+gdata[i][1]+"</b> ("+gdata[i][5]+")<br>Starting box: <b>"+gdata[i][2]+"</b><br>Ending box: <b>"+gdata[i][3]+"</b><br>Gaps: <b>"+gdata[i][4]+"</b><br></div></td></tr>"
            x += "<tr class='result-item' id='" + gdata[i][0] + "'><td><div id='mapArea" + i + "' style='width:80px;height:80px;' onclick=\"showTrack(" + i + ")\"></div></td><td><div onclick=\"showTrack(" + i + ")\"><h5><b>" + new Date(gdata[i][7]).toISOString().split('T')[0] + "</b></h5>Path stars on box <b>" + gdata[i][2] + "</b> and ends on box <b>" + gdata[i][3] + "</b><br>In total, <b>" + gdata[i][1] + "</b> fields matched, with <b>" + gdata[i][4] + "</b> gaps<br>Matched fields: <b>" + gdata[i][5] + "</b></div></td></tr>";
            graph2_array[1].push(i);
            graph2_array[0].push(gdata[i][1]);
            for (var j in gdata[i][5]) {
                graph_array.push(gdata[i][5][j]);
            }
        }

        graph_array.sort();

        for (var i in graph_array) {
            if (graph_array[i] == point) {
                count = count + 1;
                //console.log(graph_array[i]);
            } else {
                graph_x_axis.push(point);
                graph_y_axis.push(count);
                count = 0;
                point = point + 1;
            }
        }

        for (var i in graph2_array[0]) {
            if (i == 0) {
                match_number = graph2_array[0][i];
                match_count++;
            } else {
                if (match_number == graph2_array[0][i]) {
                    match_count++;
                } else {
                    graph2_x_axis.push(match_number);
                    graph2_y_axis.push(match_count);
                    match_number = graph2_array[0][i];
                    match_count = 1;
                }
            }
        }

        count = count + 1;
        graph_x_axis.push(point);
        graph_y_axis.push(count);

        graph2_x_axis.push(match_number);
        graph2_y_axis.push(match_count);

        for (var i in graph_x_axis) {
            dps.push({
                x: graph_x_axis[i],
                y: graph_y_axis[i]
            });
        }
        ;

        for (var i in graph2_x_axis) {
            dps2.push({
                x: graph2_x_axis[i],
                y: graph2_y_axis[i]
            });
        }
        $("#totalfound").html(gdata.length);
        $("#results").html(x);

        for (i in gdata) {
            drawTrackScaledWithoutZoom(JSON.parse(gdata[i][6]).geometry.coordinates, 80, 80, i);
        }
        chart.options.data[0].dataPoints = dps;
        chart2.options.data[0].dataPoints = dps2;
        chart.render();
        chart2.render();
        dps = [];
        dps2 = [];
        isFirstToDisplay = 0;
    }

    function showTrack(id) {
        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
        geoResult = L.geoJSON(JSON.parse(gdata[id][6]), {
            style: function (feature) {
                return {fill: false, fillOpacity: 0.6, stroke: true};
            }
        });
        geoResult.addTo(map);
    }

    function showAll() {
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
        geoAllResult.addTo(map);
    }

    function removeBox(id) {
        boxes[id].removeFrom(map);
        boxes.splice(id, 1);
        interpolated.splice(id, 1);
        $("#gsMatch").val(Math.max(1, Math.floor(boxes.length * 0.8)));
        $("#gaps").val(Math.round(boxes.length * 0.2));
        findPaths();
    }

    function findPaths() {
        if (interpolated.length < 2) {
            return;
        }

        $.ajax({
            method: "POST",
            url: "php/geohash_py.php",
            dataType: "json",
            data: {
                "dbName": queryDB,
                "type": mapmatched,
                "pattern": interpolated,
                "match": $("#gsMatch").val(),
                "start": $("#gsStart").val(),
                "end": Math.max(0, interpolated.length + 1 - parseInt($("#gsEnd").val())),
                "gap": $("#gaps").val()
            }
        })
            .done(function (json) {
                console.log(json);
                gdata = json;
                showResults();
                if (geoAllResult != null) {
                    map.removeLayer(geoAllResult);
                }
                geojson = {
                    "type": "FeatureCollection",
                    "features": json.map(function myFunction(item) {
                        var x = JSON.parse(item[6]);
                        x.properties['id'] = item[0];
                        return x;
                    })
                };

                geoAllResult = L.geoJSON(geojson, {
                    style: function (feature, layer) {
                        return {
                            weight: 4,
                            opacity: 0.6,
                            color: line_colors[Math.floor(Math.random() * 4)],
                            fillOpacity: 0.6
                        };
                    },

                    onEachFeature: function (feature, layer) {
                        layer.on('mouseover', function () {
                            geoAllResult.setStyle({weight: 4, opacity: 0.25, fillOpacity: 0.25});
                            this.setStyle({
                                weight: 8,
                                opacity: 1,
                                fillOpacity: 0.6
                            });
                            this.bringToFront();
                            $(".result-item").hide();
                            $("#" + this.feature.properties.id).show();
                        });
                        layer.on('mouseout', function () {
                            geoAllResult.setStyle({weight: 4, opacity: 0.6, fillOpacity: 0.6});
                            this.setStyle({
                                weight: 4,
                                opacity: 0.6,
                                fillOpacity: 0.6
                            });
                            $(".result-item").show();
                        });
                    }
                });

                geoAllResult.addTo(map);

                for (var i in boxes) {
                    boxes[i].removeFrom(map);
                    boxes[i].bindPopup("Box " + (parseInt(i) + 1) + " <br> <a href='#' onclick='removeBox(" + i + ")'>Remove</a>");
                    boxes[i].addTo(map);
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Request failed:", textStatus, errorThrown);
                console.log("Response text:", jqXHR.responseText);
            });
        svg = [];
        testSvg = [];
        all_trajectories = true;
    }

    function showAllPaths() {
        console.log(mapmatched)
        if (queryDB !== " ") {
            $.ajax({
                method: "POST",
                url: "php/show_all_tracks.php",
                dataType: "json",
                data: {
                    "dbName": queryDB,
                    "type": mapmatched
                }
            })
                .done(function (json) {
                    // console.log(json);
                    gdata = json;
                    showResults1();
                    if (geoAllResult != null) {
                        map.removeLayer(geoAllResult);
                    }
                    geojson = {
                        "type": "FeatureCollection",
                        "features": json.map(function myFunction(item) {
                            var x = JSON.parse(item[1]);
                            console.log(x)
                            x.properties['id'] = item[0];
                            return x;
                        })
                    };

                    geoAllResult = L.geoJSON(geojson, {
                        style: function (feature, layer) {
                            return {
                                weight: 4,
                                opacity: 0.6,
                                color: line_colors[Math.floor(Math.random() * 4)],
                                fillOpacity: 0.6
                            };
                        },

                        onEachFeature: function (feature, layer) {
                            layer.on('mouseover', function () {
                                geoAllResult.setStyle({weight: 4, opacity: 0.25, fillOpacity: 0.25});
                                this.setStyle({
                                    weight: 8,
                                    opacity: 1,
                                    fillOpacity: 0.6
                                });
                                this.bringToFront();
                                $(".result-item").hide();
                                $("#" + this.feature.properties.id).show();
                            });
                            layer.on('mouseout', function () {
                                geoAllResult.setStyle({weight: 4, opacity: 0.6, fillOpacity: 0.6});
                                this.setStyle({
                                    weight: 4,
                                    opacity: 0.6,
                                    fillOpacity: 0.6
                                });
                                $(".result-item").show();
                            });
                        }
                    });

                    // geoAllResult.addTo(map); //displaying on map

                    for (var i in boxes) {
                        boxes[i].removeFrom(map);
                        boxes[i].bindPopup("Box " + (parseInt(i) + 1) + " <br> <a href='#' onclick='removeBox(" + i + ")'>Remove</a>");
                        boxes[i].addTo(map);
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    console.error("Request failed:", textStatus, errorThrown);
                    console.log("Response text:", jqXHR.responseText);
                });
            svg = [];
            testSvg = [];
            all_trajectories = true;
        }
    }

    if (queryDB !== " "){
        $.getJSON("coverage/<?php echo $DBinfo['dbname']?>.geojson", function (data) {
            L.geoJSON(data).addTo(map);
        });
    }

    function showResults1() {

        graphButton.addTo(map);
        $("#resultsbox").show();
        $("#chartContainer").show();
        $("#chart2Container").show();
        fieldsTicked = [];
        fieldsTickedPerPoint = [];

        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }

        var x = "";
        graph_array = [];
        graph2_array = [[], []];
        graph_x_axis = [];
        graph_y_axis = [];
        graph2_x_axis = [];
        graph2_y_axis = [];
        point = 1;
        count = 0;
        match_number = 0;
        match_count = 0;

        for (var i in gdata) {
            x += "<tr class='result-item' id='" + gdata[i][0] + "'><td><div id='mapArea" + i + "' style='width:80px;height:80px;' onclick=\"showTrack1(" + i + ")\"></div></td><td><div onclick=\"showTrack1(" + i + ")\"><h6><b>" + new Date(gdata[i][4]).toISOString().split('T')[0] + "</b></h6>Mapmatched: <b>" + gdata[i][2] + "</b><br>Type: <b>" + gdata[i][3] + "</b><br> Length: <b>" + gdata[i][5] + " m</div></td></tr>";
        }

        $("#totalfound").html(gdata.length);
        $("#results").html(x);

        for (i in gdata) {
            drawTrackScaledWithoutZoom(JSON.parse(gdata[i][1]).geometry.coordinates, 80, 80, i);
        }

        // chart.render();
        // chart2.render();
        dps = [];
        dps2 = [];
        isFirstToDisplay = 0;
    }

    function showTrack1(id) {
        if (geoAllResult != null) {
            map.removeLayer(geoAllResult);
        }
        if (geoResult != null) {
            map.removeLayer(geoResult);
        }
        if (gdataPrintResult != null) {
            map.removeLayer(gdataPrintResult);
        }
        if (gdataPrintResultPerPoint != null) {
            map.removeLayer(gdataPrintResultPerPoint);
        }
        geoResult = L.geoJSON(JSON.parse(gdata[id][1]), {
            style: function (feature) {
                return {fill: false, fillOpacity: 0.6, stroke: true};
            }
        });
        geoResult.addTo(map);
    }

    showAllPaths();

</script>
</script>
<script src="js/jquery.tablesorter.min.js"></script>
<link rel="stylesheet" href="css/theme.blue.css"/>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="js/ie10-viewport-bug-workaround.js"></script>

</body>
</html>