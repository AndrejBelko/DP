<?php

#ngrok.com presmerovanie localhostu

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('config.php');
session_start();

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if file was uploaded
    if (isset($_FILES['files']) || isset($_FILES['trexfiles'])) {
        $uploads_dir = '/home/data/import/uploads/';
        if (isset($_FILES['files'])){
            $tmp_name = $_FILES["files"]["tmp_name"];
            $name = strtolower(basename($_FILES["files"]["name"]));
        } else{
            $tmp_name = $_FILES["trexfiles"]["tmp_name"];
            $name = strtolower(basename($_FILES["trexfiles"]["name"]));
        }

        // Ensure the directory exists
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);  // Create directory if not exists with proper permissions
        }

        if (isset($_FILES['trexfiles']) && pathinfo($name, PATHINFO_EXTENSION) != 'gpx'){
            echo "Please upload a valid GPX file.";
            exit;
        } else if(isset($_FILES['files']) && strtolower(pathinfo($name, PATHINFO_EXTENSION)) != 'csv'){
            echo "Please upload a valid csv file.";
            exit;
        }

        // Store the uploaded GPX file in the target directory
        $gpx_file = $uploads_dir . $name;
        if (move_uploaded_file($tmp_name, $gpx_file)) {

            if (isset($_FILES['trexfiles'])){
                // Create CSV file name by replacing .gpx with .csv
                $csv_name = pathinfo($name, PATHINFO_FILENAME) . '.csv';
                $csv_file = $uploads_dir . $csv_name;  // Set the CSV output path with same name

                echo $csv_file;
                // Call the Python script to convert GPX to CSV
                $command = escapeshellcmd("python3 gpx_to_csv.py $gpx_file $csv_file");
                shell_exec($command);
            } else{
                // Create CSV file name by replacing .gpx with .csv
                $csv_name = pathinfo($name, PATHINFO_FILENAME) . '.csv';
                $csv_file = $uploads_dir . $csv_name;  // Set the CSV output path with same name
            }


            // Check if the CSV file was generated
            if (file_exists($csv_file)) {

                // Insert file information into the database
                $sql = "SELECT id FROM pouzivatel WHERE meno = :username";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(":username", $_SESSION["username"], PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch();
                $pouzivatel_id = $row["id"];

                $sql = "INSERT INTO subor (pouzivatel_id, nazov, cesta) VALUES (:pouzivatel_id, :nazov, :cesta)";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(":pouzivatel_id", $pouzivatel_id, PDO::PARAM_INT);
                $stmt->bindParam(":nazov", $csv_name, PDO::PARAM_STR);  // Save the CSV file name
                $stmt->bindParam(":cesta", $csv_file, PDO::PARAM_STR);  // Store the CSV file path

                $stmt->execute();

                $username = $_SESSION['username'];

                $command = escapeshellcmd("python3 track_to_database.py $csv_file $username 0 $username" . " dataset" );

                $output = shell_exec($command . " 2>&1");
                echo $output;


                $command = escapeshellcmd("python3 geohash_area.py ". $username);
                exec($command, $output, $return_var);

//                $gpsAccuracy = $_POST['gps_accuracy'];
//                $searchRadius = $_POST['search_radius'];
//                $turnPenalty = $_POST['turn_penalty_factor'];
//                $walk = $_POST['type'];

                $params = [
                    'type' => 'Walk',
                    'gps_accuracy' => "5", // Ensure $gpsAccuracy is defined
                    'search_radius' => "50", // Ensure $searchRadius is defined
                    'turn_penalty_factor' => "200" // Ensure $turnPenalty is defined
                ];

                // Convert the parameters array to JSON
                $parametersJson = json_encode($params, JSON_PRETTY_PRINT);

                // Define the input array for the container request
                $input = [
                    'container' => "valhalla", // Container name
                    'username' => $username, // Ensure $username is defined
                    'parameters' => json_decode($parametersJson), // Decode back to array to ensure it's properly structured
                    'file' => $gpx_file // Ensure $gpx_file is defined
                ];

                // Convert the input array to JSON
                $jsonInput = json_encode($input, JSON_PRETTY_PRINT);

                $nodeScript = "node /var/www/html/upload.js '$jsonInput'"; // Pass the uploaded file path to Node.js script

                // Execute the Node.js script
                exec($nodeScript, $output, $return_var);

                // Check if JSON encoding was successful

            } else {
                echo "Error: CSV file not generated.<br>";
            }
        } else {
            echo "Failed to move the uploaded file.<br>";
        }
    } else {
        echo "No file uploaded or an error occurred.<br>";
    }
}


$username1 = $_SESSION["username"];

$sql = "SELECT meno, email, heslo FROM pouzivatel WHERE meno = :username";
$stmt = $db->prepare($sql);
$stmt->bindParam(":username", $username1, PDO::PARAM_STR);
$stmt->execute();
$row = $stmt->fetch();
$err = false;
try {
    $db = new PDO("mysql:host=$hostname;dbname=$username1", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $err = true;
}

if (!$err) {
    $sql = "SELECT * FROM tracks";
    $stmt1 = $db->prepare($sql);
    $stmt1->execute();
    $row = $stmt1->fetchAll();
}

unset($stmt);
unset($db);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Profile</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>


    <link href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.css">
    <script src="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.js"></script>
    <script src="https://npmcdn.com/leaflet-geometryutil"></script>
    <script src="js/geohash.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active fs-5" aria-current="page" href="index.php">Mapa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="profile.php">
                            Profil
                        </a>
                    </li>
<!--                    <li class="nav-item">-->
<!--                        <a class="nav-link fs-5" href="pdf.php">-->
<!--                            Príručka-->
<!--                        </a>-->
<!--                    </li>-->
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="logout.php">
                            Odhlásiť sa
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="nav-link fs-5">
                            Vitaj, <?php echo $_SESSION['username'] ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
    <div class="container mt-5">
        <div class="row">
            <!-- Left Column: Table -->
            <div class="col-md-8">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>Original</th>
                        <th>Mapmatched</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php

                        if (!$err){
                            for ($i = 0; $i < count($row); $i += 2) {
                                $row_tmp = $row[$i]; // Access every second element
                                echo "<tr>";
                                echo "<td>" . $row_tmp['route'] . "</td>";
                                echo "<td>" . $row_tmp['timestamp'] . "</td>";
                                echo "<td>
            <a href='delete.php?route=" . urlencode($row_tmp['route']) . "' class='btn btn-sm btn-danger'><i class='bi bi-trash'></i></a>
            <a href='download.php?route=" . urlencode($row_tmp['route']) . "' class='btn btn-sm btn-info'><i class='bi bi-download'></i></a>
          </td>";
                                // Ensure `$i + 1` exists before accessing it
                                if (isset($row[$i + 1])) {
                                    $next_row = $row[$i + 1]; // Access the next element for `$i + 1`
                                    echo "<td>
                <a href='delete.php?route=" . urlencode($next_row['route']) . "' class='btn btn-sm btn-danger'><i class='bi bi-trash'></i></a>
                <a href='download.php?route=" . urlencode($next_row['route']) . "' class='btn btn-sm btn-info'><i class='bi bi-download'></i></a>
              </td>";
                                } else {
                                    echo "<td>—</td>"; // Placeholder if there is no `$i + 1`
                                }
                                echo "</tr>";
                            }
                        }

                        echo "</table>";

                        ?>
                    </tbody>
                </table>
            </div>
            <!-- Right Column: Links -->
            <div class="col-md-4">
                <div class="d-flex flex-column">
                    <p class="mt-4"><a href="#" onclick="showElement('upload-hodinky')"><strong>Nahraj udaje z hodiniek</strong></a>
                    </p>
                    <div style="display: none" id="upload-hodinky">
                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Zadaj Rok-Mesiac</label>
                                <input style="width: 150px;" type="text" name="yearmonth" class="form-control"
                                       value="<?php echo date('Y-m'); ?>">
                            </div>
                            <div class="form-group">
                                <input style="width: 250px;" type="file" name="files" multiple class="form-control">
                            </div>
                            <button type="submit" name="submit" class="btn btn-primary">Nahraj</button>
                        </form>

                    </div>

                    <p class="mt-4"><a href="#" onclick="showElement('upload-trex')"><strong>Nahraj udaje z T-REXu</strong></a>
                    </p>
                    <div style="display: none" id="upload-trex">
                        <form action="profile.php" method="post" enctype="multipart/form-data">

                            <div class="form-group">
                                <input style="width: 250px;" type="file" name="trexfiles" multiple class="form-control">
                            </div>
                            <button type="submit" name="trexsubmit" class="btn btn-primary">Nahraj</button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Include Bootstrap JS (optional, for advanced interactions) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<script>
    function drawTrackScaledWithoutZoom(coordinates, svgWidth, svgHeight, i){
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

    function showResults1(){
        var x = "";
        for (var i in gdata){
            x+= "<tr class='result-item' id='"+gdata[i][0]+"'><td><div id='mapArea"+i+"' style='width:80px;height:80px;' onclick=\"showTrack1("+i+")\"></div></td><td><div onclick=\"showTrack("+i+")\"><h5><b>"+gdata[i][0]+"</b></div></td></tr>";

        }
        for(i in gdata){
            drawTrackScaledWithoutZoom(JSON.parse(gdata[i][1]).geometry.coordinates, 80, 80, i);
        }
        // chart.options.data[0].dataPoints = dps;
        // chart2.options.data[0].dataPoints = dps2;
        chart.render();
        chart2.render();
        dps = [];
        dps2 = [];
        isFirstToDisplay = 0;
    }
</script>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function showElement(id) {
        $("#trex-uploaded").hide()
        $("#upload-trex").hide();
        $("#upload-hodinky").hide();
        //$("#upload-logger").hide();
        $("#files-by-month").hide();
        //$("#files-by-columbus").hide();
        $("#files-by-year-month").hide();
        $("#splited-hodinky").hide();

        $("#" + id).show();
    }

    function showList(id) {
        $("#" + id).toggle();
    }
</script>
<script>

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


    function processData(jsonData) {
        drawChart("speedchart", parseSpeed(jsonData), "Rychlost [km/h]", "speed", "Rychlost ", " km/h");
        drawChart("heightchart", parseHeight(jsonData), "Vyska [m]", "height", "Vyska ", " m");

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

</script>

</html>
