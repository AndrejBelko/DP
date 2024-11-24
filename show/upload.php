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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if file was uploaded
    if (isset($_FILES['gpxfile']) && $_FILES['gpxfile']['error'] == 0) {
        $uploads_dir = '/home/data/import/uploads/';
        $tmp_name = $_FILES["gpxfile"]["tmp_name"];
        $name = basename($_FILES["gpxfile"]["name"]);

        // Ensure the directory exists
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);  // Create directory if not exists with proper permissions
        }

        // Ensure it is a GPX file
        if (pathinfo($name, PATHINFO_EXTENSION) != 'gpx') {
            echo "Please upload a valid GPX file.";
            exit;
        }

        // Store the uploaded GPX file in the target directory
        $gpx_file = $uploads_dir . $name;
        if (move_uploaded_file($tmp_name, $gpx_file)) {
            echo "The file " . htmlspecialchars($name) . " has been uploaded successfully.<br>";

            // Create CSV file name by replacing .gpx with .csv
            $csv_name = pathinfo($name, PATHINFO_FILENAME) . '.csv';
            $csv_file = $uploads_dir . $csv_name;  // Set the CSV output path with same name

            echo $csv_file;
            // Call the Python script to convert GPX to CSV
            $command = escapeshellcmd("python3 gpx_to_csv.py $gpx_file $csv_file");
            shell_exec($command);

            // Check if the CSV file was generated
            if (file_exists($csv_file)) {
                echo "CSV file generated successfully.<br>";

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

                echo "File information saved to the database.<br>";

                // Optionally serve the CSV file or show a link to download it
                echo "<a href='$csv_file' download>Download the CSV file</a>";

                // Clean up: delete temporary files
//                unlink($gpx_file);  // Uncomment if you want to remove the GPX file after processing
                $username = $_SESSION['username'];

                $command = escapeshellcmd("python3 track_to_database.py $csv_file $username 0 $username" . " dataset"  );
                shell_exec($command);

                $command = escapeshellcmd("python3 geohash_area.py ". $username);
                exec($command, $output, $return_var);

                $gpsAccuracy = $_POST['gps_accuracy'];
                $searchRadius = $_POST['search_radius'];
                $turnPenalty = $_POST['turn_penalty_factor'];
                $walk = $_POST['type'];

                $params = [
                    'type' => 'Walk',
                    'gps_accuracy' => $gpsAccuracy, // Ensure $gpsAccuracy is defined
                    'search_radius' => $searchRadius, // Ensure $searchRadius is defined
                    'turn_penalty_factor' => $turnPenalty // Ensure $turnPenalty is defined
                ];

// Convert the parameters array to JSON
                $parametersJson = json_encode($params, JSON_PRETTY_PRINT);

// Define the input array for the container request
                $input = [
                    'container' => "gps_valhalla", // Container name
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPX to CSV Converter</title>


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

<h1>Upload GPX File</h1>
<form action="upload.php" method="POST" enctype="multipart/form-data">
    <input id="fileInput" type="file" name="gpxfile" accept=".gpx">
    <br><br>
    <input type="checkbox" name="type"> Chôdza
    <br><br>
    <span class="label-span">
            <div class="help-marker">
                <div class="help-window">
                    <p>Set the number to indicate the accuracy in meters.</p>
                </div>
            </div> GPS accuracy: <label for="gps_accuracy">5</label>m
        </span>
    <input type="range" id="gps_accuracy" name="gps_accuracy" min="1" max="20" value="5">

    <span class="label-span">
            <div class="help-marker">
                <div class="help-window">
                    <p>Specify the search radius (in meters) within which to search road
                        candidates for each measurement. Note that performance
                        may decrease with a higher search radius value.</p>
                </div>
            </div> Search radius: <label for="search_radius">50</label>m
        </span>
    <input type="range" id="search_radius" name="search_radius" min="1" max="100" value="50">

    <span class="label-span">
            <div class="help-marker">
                <div class="help-window">
                    <p>Penalize turns from one road segment to next. For a pedestrian
                        map-match, you may see a back-and-forth motion along the streets of your path. Try increasing
                        the turn penalty factor to 500 to smooth out jittering of points. Note that if GPS accuracy is
                        already good, increasing this will have a negative affect on your results.</p>
                </div>
            </div>
            Turn penalty: <label for="turn_penalty_factor">200</label>
        </span>
    <input type="range" id="turn_penalty_factor" name="turn_penalty_factor" min="0" max="500" value="200">
    <br><br>
    <input id="upload-button" type="submit" value="Upload and Convert to CSV">
</form>
<!--<script src="upload.js"></script>-->
</body>
</html>
