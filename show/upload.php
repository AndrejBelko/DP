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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $headers = getallheaders();

        $apikey = isset($headers["x-apikey"]) ? $headers["x-apikey"] : "";
        if ($apikey==""){
            throw new Exception("Api key required",400);
        }

        if ($apikey!="mvx0dtrEknr53uEozm1Czf8oCvnxyIPpkB1Up2p6PK"){
            throw new Exception("Api invalid",401);
        }

        $folder = isset($headers["x-folder"]) ? $headers["x-folder"] : "";
        if ($folder==""){
            throw new Exception("Folder required",400);
        }

        // $target_dir = "/home/data/import/uploads/".$folder."/";
        $target_dir = "/home/data/import/uploads/mobile/";
        $target_file = $target_dir . basename($_FILES["file"]["name"]);

        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                throw new Exception("Failed to create directory.", 500);
            }
        }

        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            throw new Exception("Failed to upload.", 500);
        }


        $username = "";

        if (preg_match('/id-(.*?)\//', $headers["x-folder"], $matches)) {
            $username = $matches[1]; // This will contain '16112023pro'
        }

        $sql = "SELECT id FROM pouzivatel WHERE meno = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            throw new Exception("Failed to upload.", 500);
        }

        $row = $stmt->fetch();

        $command = "python3 track_to_database.py $target_file $username 0 $username" . " dataset 2>&1";
        exec($command, $output, $return_var);

        $command = escapeshellcmd("python3 geohash_area.py " . $username);
        exec($command, $output, $return_var);

//    $gpsAccuracy = $_POST['gps_accuracy'];
//    $searchRadius = $_POST['search_radius'];
//    $turnPenalty = $_POST['turn_penalty_factor'];
//    $walk = $_POST['type'];

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
            'file' => $target_file // Ensure $gpx_file is defined
        ];

        // Convert the input array to JSON
        $jsonInput = json_encode($input, JSON_PRETTY_PRINT);

        $nodeScript = "node /var/www/html/upload.js '$jsonInput'"; // Pass the uploaded file path to Node.js script

        // Execute the Node.js script
        exec($nodeScript, $output, $return_var);

        echo json_encode(array("status" => "success"));


    } catch (Exception $exception) {
        http_response_code($exception->getCode());
        echo json_encode(array("status" => $exception->getMessage()));
    }
}

?>