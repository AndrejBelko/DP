<?php

#ngrok.com presmerovanie localhostu

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('config.php');
session_start();

if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

$username1 = $_SESSION['username'];
$err = false;

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $infomsg = "";
    // Check if file was uploaded
    if (isset($_FILES['files']) || isset($_FILES['trexfiles'])) {
        $uploads_dir = '/home/data/import/files/uploads/' . $_SESSION["username"] . "/";
        $input_files = $_FILES['files'] ?? $_FILES['trexfiles'];

        $tmp_names = $input_files['tmp_name'];
        $names = array_map(function ($name) {
            return strtolower(basename($name));
        }, $input_files['name']);

        // Ensure the directory exists
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);  // Create directory if not exists with proper permissions
        }

        for ($x = 0; $x < sizeof($names); $x++) {
            $filename = $names[$x];
            $tmp_name = $tmp_names[$x];
            if (isset($_FILES['trexfiles']) && pathinfo($filename, PATHINFO_EXTENSION) != 'gpx') {
                $infomsg .= $filename . "is not a GPX file.\n";
                break;
            } else if (isset($_FILES['files']) && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) != 'csv') {
                $infomsg .= $filename . "is not a CSV file.\n";
                break;
            }

            // Store the uploaded GPX file in the target directory
            $gpx_file = $uploads_dir . $filename;
            if (move_uploaded_file($tmp_name, $gpx_file)) {

                if (isset($_FILES['trexfiles'])) {
                    // Create CSV file name by replacing .gpx with .csv
                    $csv_name = pathinfo($filename, PATHINFO_FILENAME) . '.csv';
                    $csv_file = $uploads_dir . $csv_name;  // Set the CSV output path with same name

                    // Call the Python script to convert GPX to CSV
                    $command = escapeshellcmd("python3 /var/www/html/gpx_to_csv.py $gpx_file $csv_file");
                    shell_exec($command);
                    $file_source = 'TRex';
                } else {
                    // Create CSV file name by replacing .gpx with .csv
                    $csv_name = pathinfo($filename, PATHINFO_FILENAME) . '.csv';
                    $csv_file = $uploads_dir . $csv_name;  // Set the CSV output path with same name
                    $file_source = 'Smartwatch';
                }


                // Check if the CSV file was generated
                if (file_exists($csv_file)) {

                    $rowCount = 0;
                    if (($handle = fopen($csv_file, "r")) !== false) {
                        while (($data = fgetcsv($handle)) !== false) {
                            $rowCount++;
                            if ($rowCount > 2) { // Stop early if we have more than 2 rows
                                break;
                            }
                        }
                        if ($rowCount === 2) {
                            fclose($handle);
                            $infomsg .= "Failed to process file: " . $csv_name . "\n";
                            continue;
                        }
                    }
                    echo $rowCount;

                    // Insert file information into the database
                    $sql = "SELECT id FROM users WHERE username = :username";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(":username", $_SESSION["username"], PDO::PARAM_STR);
                    $stmt->execute();
                    $row = $stmt->fetch();
                    $pouzivatel_id = $row["id"];

                    $sql = "SELECT max(track_id) as 'track_id' FROM path WHERE user_id = :user_id";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(":user_id", $pouzivatel_id, PDO::PARAM_STR);
                    $stmt->execute();
                    $row_track = $stmt->fetch();
                    $track_id = strval($row_track["track_id"] + 1);

                    $sql = "INSERT INTO files (user_id, track_id, name, path, file_source) VALUES (:pouzivatel_id, :track_id, :nazov, :cesta, :zdroj)";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(":pouzivatel_id", $pouzivatel_id, PDO::PARAM_INT);
                    $stmt->bindParam(":track_id", $track_id, PDO::PARAM_INT);
                    $stmt->bindParam(":nazov", $csv_name, PDO::PARAM_STR);  // Save the CSV file name
                    $stmt->bindParam(":cesta", $csv_file, PDO::PARAM_STR);  // Store the CSV file path
                    $stmt->bindParam(":zdroj", $file_source, PDO::PARAM_STR);  // Store the CSV file path

                    $stmt->execute();

                    $username = $_SESSION['username'];
                    $type = isset($_POST['trajectoryType']) ? 'Drive' : 'Walk';

                    $command = escapeshellcmd("python3 /var/www/html/track_to_database.py $filename $csv_file $username $pouzivatel_id 0 $type $track_id" . " dataset");
                    $output = shell_exec($command . " 2>&1");
                    // echo $output;
                    $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username);
                    exec($command, $output, $return_var);

//                $gpsAccuracy = $_POST['gps_accuracy'];
//                $searchRadius = $_POST['search_radius'];
//                $turnPenalty = $_POST['turn_penalty_factor'];
//                $walk = $_POST['type'];

                    // Define parameters
                    $params = [
                        'type' => $type,
                        'gps_accuracy' => "5", // Ensure $gpsAccuracy is defined
                        'search_radius' => "50", // Ensure $searchRadius is defined
                        'turn_penalty_factor' => "300" // Ensure $turnPenalty is defined
                    ];

                    // Convert the parameters array to JSON
                    $parametersJson = json_encode($params, JSON_PRETTY_PRINT);

                    // Define the input array for the Python script
                    $input = [
                        'container' => $valhalla_container, // Container name
                        'username' => $username, // Ensure $username is defined
                        'parameters' => json_decode($parametersJson, true), // Decode back to array
                        'file' => $gpx_file, // Ensure $gpx_file is defined
                        'filename' => $filename,
                        'user_id' => $pouzivatel_id,
                        'track_id' => $track_id
                    ];

                    // Convert the input array to JSON
                    $jsonInput = json_encode($input, JSON_PRETTY_PRINT);

                    // Define the Python script command
                    $pythonScript = "python3 /var/www/html/mapmatch.py '$jsonInput'";

                    // Execute the Python script
                    $output = shell_exec($pythonScript . " 2>&1");
                    //echo $output;

                    $command = escapeshellcmd("python3 /var/www/html/interpolate.py $csv_file");
                    $output = shell_exec($command . " 2>&1");
                    // echo $output;

                } else {
                    $infomsg = "Error: CSV file not generated.";
                }
            } else {
                $infomsg = "Failed to move the uploaded file.";
            }
        }
    } elseif ($_POST['secToken']) {
        $sql = "UPDATE users SET token = :token WHERE id = :id";

        // Retrieve values from POST or other sources
        $token = $_POST['secToken'];
        $id = $_SESSION['user_id']; // Ensure this is the ID of the user you want to update

        // Prepare and execute the statement
        $stmt = $db->prepare($sql);

        // Bind parameters to the SQL query
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT); // Bind the id to the parameter

        // Execute the query
        $stmt->execute();

    } elseif ($_POST['action']){
        $track_ids = $_POST['track_ids']; // Get selected track IDs
        $action = $_POST['action'];

        if (!empty($track_ids)) {
            if ($action === "download_orig") {
                $queryString = http_build_query(['track_ids' => $track_ids]);
                header("Location: download.php?mapmatched=0&$queryString&user_id=$_SESSION[user_id]");
            } elseif ($action === "download_mm") {
                $queryString = http_build_query(['track_ids' => $track_ids]);
                header("Location: download.php?mapmatched=1&$queryString&user_id=$_SESSION[user_id]");
            } elseif ($action === "delete") {
                // Convert array to query string
                $queryString = http_build_query(['track_ids' => $track_ids]);
                header("Location: delete.php?$queryString&user_id=$_SESSION[user_id]");
            }
        } else {
            $infomsg = "No file selected or an error occurred.";
        }

    } elseif(isset($_POST['form_action'])){

        $sql = "SELECT id FROM users WHERE username = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":username", $_SESSION["username"], PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        $pouzivatel_id = $row["id"];

        $sql = "DELETE FROM path WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":user_id", $pouzivatel_id, PDO::PARAM_STR);
        $stmt->execute();

        $sql = "DELETE FROM tracks WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":user_id", $pouzivatel_id, PDO::PARAM_STR);
        $stmt->execute();

        $sql = "SELECT path, file_source FROM files WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":user_id", $pouzivatel_id, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetchAll();

        for ($x = 0; $x < sizeof($row); $x++) {
            $path = $row[$x]["path"];
            $filename = basename($path);

            // Create CSV file name by replacing .gpx with .csv
            $csv_name = pathinfo($filename, PATHINFO_FILENAME) . '.csv';
            $csv_file = $path;  // Set the CSV output path with same name
            $file_source = $row[$x]["file_souce"];


            // Check if the CSV file was generated
            if (file_exists($csv_file)) {

                $rowCount = 0;
                if (($handle = fopen($csv_file, "r")) !== false) {
                    while (($data = fgetcsv($handle)) !== false) {
                        $rowCount++;
                        if ($rowCount > 2) { // Stop early if we have more than 2 rows
                            break;
                        }
                    }
                    if ($rowCount === 2) {
                        fclose($handle);
                        $infomsg .= "Failed to process file: " . $csv_name . "\n";
                        continue;
                    }
                }

                $sql = "SELECT max(track_id) as 'track_id' FROM path WHERE user_id = :user_id";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(":user_id", $pouzivatel_id, PDO::PARAM_STR);
                $stmt->execute();
                $row_track = $stmt->fetch();
                $track_id = strval($row_track["track_id"] + 1);

                $username = $_SESSION['username'];
                $type = isset($_POST['trajectoryType']) ? 'Drive' : 'Walk';

                $command = escapeshellcmd("python3 /var/www/html/track_to_database.py $filename $csv_file $username $pouzivatel_id 0 $type $track_id" . " dataset");
                $output = shell_exec($command . " 2>&1");
                // echo $output;
                $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username);
                exec($command, $output, $return_var);

//                $gpsAccuracy = $_POST['gps_accuracy'];
//                $searchRadius = $_POST['search_radius'];
//                $turnPenalty = $_POST['turn_penalty_factor'];
//                $walk = $_POST['type'];

                // Define parameters
                $params = [
                    'type' => $type,
                    'gps_accuracy' => "5", // Ensure $gpsAccuracy is defined
                    'search_radius' => "50", // Ensure $searchRadius is defined
                    'turn_penalty_factor' => "300" // Ensure $turnPenalty is defined
                ];

                // Convert the parameters array to JSON
                $parametersJson = json_encode($params, JSON_PRETTY_PRINT);

                // Define the input array for the Python script
                $input = [
                    'container' => $valhalla_container, // Container name
                    'username' => $username, // Ensure $username is defined
                    'parameters' => json_decode($parametersJson, true), // Decode back to array
                    'file' => $path, // Ensure $gpx_file is defined
                    'filename' => $filename,
                    'user_id' => $pouzivatel_id,
                    'track_id' => $track_id
                ];

                // Convert the input array to JSON
                $jsonInput = json_encode($input, JSON_PRETTY_PRINT);

                // Define the Python script command
                $pythonScript = "python3 /var/www/html/mapmatch.py '$jsonInput'";

                // Execute the Python script
                $output = shell_exec($pythonScript . " 2>&1");
                //echo $output;

                $command = escapeshellcmd("python3 /var/www/html/interpolate.py $csv_file");
                $output = shell_exec($command . " 2>&1");
                // echo $output;

            } else {
                $infomsg = "Error: CSV file not generated.";
            }
        }

    }else {
        $infomsg = "No file uploaded or an error occurred.";
    }
}

if (!$err) {
    $user_id = intval($_SESSION['user_id']);  // Get the user_id from the session
    $sql = "SELECT t.*, f.file_source as file_source 
        FROM tracks t 
        JOIN files f ON t.user_id = f.user_id AND t.track_id = f.track_id
        WHERE t.user_id = :user_id";

    $stmt1 = $db->prepare($sql);
    $stmt1->bindParam(":user_id", $user_id, PDO::PARAM_INT);  // Use PARAM_INT for numeric values
    $stmt1->execute();
    $row = $stmt1->fetchAll();

}

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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js" integrity="sha512-STof4xm1wgkfm7heWqFJVn58Hm3EtS31XFaagaa8VMReCXAkQnJZ+jEy8PCC/iT18dFy95WcExNHFTqLyp72eQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-1.13.7/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/b-print-2.4.2/date-1.5.1/r-2.5.0/sc-2.3.0/sb-1.6.0/sp-2.2.0/sl-1.7.0/datatables.min.css" rel="stylesheet">

    <script src="https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-1.13.7/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/b-print-2.4.2/date-1.5.1/r-2.5.0/sc-2.3.0/sb-1.6.0/sp-2.2.0/sl-1.7.0/datatables.min.js"></script>

    <link href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../js/core.js"></script>
    <script src="../js/charts.js"></script>
    <script src="../js/animated.js"></script>
    <script src="../js/geohash.js"></script>

    <style>


        .chartdiv {
            width: 100%;
            height: 400px;
        }


        @media (max-width: 768px) {
            .table-wrapper {
                transform: scale(0.8); /* Scale down to 80% */
            }
        }

        @media (max-width: 480px) {
            .table-wrapper {
                transform: scale(0.6); /* Scale down to 60% */
            }
        }

        #upload-hodinky, #upload-trex, .table-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%; /* Ensures the centering spans the full width */
            margin: 0 auto; /* Center the container itself horizontally if needed */
        }

        form {
            display: flex;
            flex-direction: column; /* Stack elements vertically */
            align-items: center; /* Center elements horizontally */
            gap: 10px; /* Add spacing between child elements */
        }

        .form-check {
            display: flex;
            align-items: center; /* Align checkbox and label vertically */
            gap: 5px; /* Add spacing between the checkbox and label */
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
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link fs-5" aria-current="page" href="../index.php">Map</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fs-5" href="profile.php">
                            Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="logout.php">
                            Log out
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="nav-link fs-5">
                            Welcome, <?php echo $_SESSION['username'] ?>
                        </div>
                    </li>
                    <li class="nav-item">
                        <div class="nav-link fs-5" id="token" data-bs-toggle="modal" data-bs-target="#exampleModal">
                            Security token
                        </div>
                    </li>
                    <li>
                        <div class="modal-body">
                            <form action="profile.php" method="POST" id="formReloadDB1">
                                <input type="hidden" name="form_action" value="form1">
                                <!-- Add other form fields if needed -->
                            </form>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary" form="formReloadDB1">Reload DB</button>
                        </div>

                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<div class="container">
    <!-- Buttons for actions aligned on one line with margin between -->
    <div class="d-flex justify-content-center mt-2">
        <!-- "Check All" Button -->
        <button class="mt-4 mb-3 btn btn-primary btn-sm btn-collapse m-3" data-bs-toggle="modal" data-bs-target="#watchUpload">
            <strong>Upload from smartwatch (.csv)</strong>
        </button>
        <button class="mt-4 mb-3 btn btn-primary btn-sm m-3" data-bs-toggle="modal" data-bs-target="#trexUpload">
            <strong>Upload from TRex (.gpx)</strong>
        </button>
    </div>
    <form id="actionForm" method="POST" action="profile.php">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-10 mx-auto">
                    <div class="table-wrapper">
                        <div class="d-flex justify-content-start mt-2">
                            <!-- "Check All" Button -->
                            <button type="button" id="checkAllBtn" class="btn btn-primary btn-sm m-3" onclick="checkAllCheckboxes()">Select All</button>
                            <button type="submit" name="action" value="download_orig" class="btn btn-success m-3">Download Selected Original</button>
                            <button type="submit" name="action" value="download_mm" class="btn btn-success m-3">Download Selected Mapmatched</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger m-3">Delete Selected</button>
                        </div>
                        <table id="myTable" class="table table-striped table-bordered mt-5">
                            <thead class="table-dark">
                            <tr>
                                <th>Select</th>
                                <th>ID</th>
                                <th>Filename</th>
                                <th>Timestamp</th>
                                <th>File source</th>
                                <th>Type</th>
                                <th>Original</th>
                                <th>Mapmatched</th>
                                <th>Info</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            if (!$err) {
                                for ($i = 0; $i < count($row); $i += 2) {
                                    $row_tmp = $row[$i]; // Access every second element
                                    echo "<tr>";
                                    echo "<td><input class= 'checkbox' type='checkbox' name='track_ids[]' value='" . $row_tmp['track_id'] . "'></td>";
                                    echo "<td>" . $row_tmp['track_id'] . "</td>";
                                    echo "<td>" . $row_tmp['filename'] . "</td>";
                                    echo "<td>" . $row_tmp['timestamp'] . "</td>";
                                    echo "<td>" . $row_tmp['file_source'] . "</td>";
                                    if ($row_tmp['type'] === 'Walk') {
                                        echo "<td>" . '<i class="bi bi-person"></i>' . "</td>";
                                    } elseif ($row_tmp['type'] === 'Drive') {
                                        echo "<td>" . '<i class="bi bi-car-front"></i>' . "</td>";
                                    } else {
                                        echo $row_tmp['type']; // Fallback for other types
                                    }
                                    echo "<td>
                                    <a href='delete.php?" . http_build_query(['track_ids' => [$row_tmp['track_id']]]) . "&user_id=". urlencode($_SESSION['user_id']) ."' class='btn btn-sm btn-danger'><i class='bi bi-trash'></i></a>
                                    <a href='download.php?" . http_build_query(['track_ids' => [$row_tmp['track_id']]]) . "&user_id=". urlencode($_SESSION['user_id']) ."&mapmatched=0' class='btn btn-sm btn-info'><i class='bi bi-download'></i></a>
                                  </td>";

                                    if (isset($row[$i + 1])) {
                                        $next_row = $row[$i + 1];
                                        echo "<td>
                                        <a href='delete.php?" . http_build_query(['track_ids' => [$row_tmp['track_id']]]) . "&user_id=". urlencode($_SESSION['user_id']) ."' class='btn btn-sm btn-danger'><i class='bi bi-trash'></i></a>
                                        <a href='download.php?" . http_build_query(['track_ids' => [$row_tmp['track_id']]]) . "&user_id=". urlencode($_SESSION['user_id']) ."&mapmatched=1' class='btn btn-sm btn-info'><i class='bi bi-download'></i></a>
                                      </td>";
                                    } else {
                                        echo "<td>—</td>"; // Placeholder if there is no `$i + 1`
                                    }

                                    echo "<td>
                                    <div class='btn btn-sm btn-info' onclick='loadGPSData(\"" . $row_tmp['filename'] . "\")'><i class='bi bi-info'></i></div>
                                  </td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div id="speedchart" class="chartdiv"></div>
    <div id="heightchart" class="chartdiv"></div>
</div>
<!-- Toast Container -->
<div class="toast-container p-3 top-0 start-50 translate-middle-x">
    <div id="errorToast" class="toast bg-danger" role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-autohide="true">
        <div class="toast-header">
            <strong class="me-auto">Error</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <?php echo $infomsg; ?>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container p-3 top-0 start-50 translate-middle-x">
    <div id="successToast" class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-autohide="true">
        <div class="toast-header">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Načítanie prebehlo úspešne.
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Security token</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal -->
            <div class="modal-body">
                <form action="profile.php" method="post" id="formSecToken">
                    <label for="secToken">Provide your security token:</label>
                    <input type="text" id="secToken" name="secToken" class="form-control" placeholder="123XYZ">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" form="formSecToken">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="watchUpload" tabindex="-1" aria-labelledby="exampleWatchLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleWatchLabel">Upload from smartwatch</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal -->
            <div class="modal-body">
                <div id="upload-hodinky" class="text-center justify-content-center mb-2">
                    <form action="profile.php" method="post" enctype="multipart/form-data">
                        <input style="width: 250px;" type="file" name="files[]" multiple class="form-control">
                        <div class="form-group mb-3 d-flex align-items-center">
                            <label for="trajectoryType" class="form-label me-3">Trajectory type</label>
                            <div class="form-check form-switch">
                                <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="trajectoryType"
                                        name="trajectoryType"
                                        value="walk">
                                <label class="form-check-label" for="trajectoryType">Car Ride</label>
                            </div>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="trexUpload" tabindex="-1" aria-labelledby="exampleTrexLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleTrexLabel">Upload from TRex</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal -->
            <div class="modal-body">
                <div id="upload-trex" class="text-center justify-content-center mb-2">
                    <form action="profile.php" method="post" enctype="multipart/form-data">

                        <div class="form-group">
                            <input style="width: 250px;" type="file" name="trexfiles[]" multiple class="form-control">
                        </div>
                        <div class="form-group mb-3 d-flex align-items-center">
                            <label for="trajectoryType" class="form-label me-3">Trajectory type</label>
                            <div class="form-check form-switch">
                                <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="trajectoryType"
                                        name="trajectoryType"
                                        value="walk">
                                <label class="form-check-label" for="trajectoryType">Car Ride</label>
                            </div>
                        </div>
                        <button type="submit" name="trexsubmit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS (with Popper.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($infomsg)) : ?>
    <script>
        // Show the toast if $errmsg is not empty
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));
        errorToast.show();
    </script>
<?php elseif ($infomsg === ""): ?>
    <script>
        // Show the toast if $errmsg is not empty
        const errorToast = new bootstrap.Toast(document.getElementById('successToast'));
        errorToast.show();
    </script>
<?php endif; ?>
</div>

<!-- Include Bootstrap JS (optional, for advanced interactions) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<script>
    let table = new DataTable('#myTable', {
        paging: true,
        columnDefs: [
            { orderable: false, targets: [0, 5, 6, 7, 8] } // Disable sorting on columns 1, 5, 6, 7, 8
        ],
        order: [[1, 'asc']], // Keep ordering on column 2 (index 1)
    });


    console.log(typeof $.fn.DataTable);
    // Function to check or uncheck all checkboxes
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
</script>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>

<script>

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
