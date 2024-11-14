<?php

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

$username1 = $_SESSION["username"];

$sql = "SELECT meno, email, heslo FROM pouzivatel WHERE meno = :username";
$stmt = $db->prepare($sql);
$stmt->bindParam(":username", $username1, PDO::PARAM_STR);
$stmt->execute();
$row = $stmt->fetch();

try {
    $db = new PDO("mysql:host=$hostname;dbname=$username1", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

$sql = "SELECT * FROM tracks";
$stmt1 = $db->prepare($sql);
$stmt1->execute();
$row = $stmt1->fetchAll();
//var_dump($row);

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
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="upload.php">
                            Nahranie nových trajektórií
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="pdf.php">
                            Príručka
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="logout.php">
                            Odhlásiť sa
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
<?php
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Mapmatched</th><th>Photo</th></tr>";

// 4. Loop through each row and create a new table row
foreach($row as $row_tmp){
    echo "<tr>";
    echo "<td>" . $row_tmp['route']. "</td>";
    echo "<td>" . $row_tmp['mapmatched'] . "</td>";
    echo "</tr>";
}

// 5. End of HTML table
echo "</table>";

?>
</body>
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
</html>
