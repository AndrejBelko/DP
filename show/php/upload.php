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

        $username = "";
        $filename = basename($_FILES["file"]["name"]);
        $type = $headers["x-type"];

        if (preg_match('/id-(.*?)\//', $headers["x-folder"], $matches)) {
            $username = $matches[1]; // This will contain '16112023pro'
        }

        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() != 1) {
            $usersql = "INSERT INTO users (username, email, password, token) VALUES (:username, :email, :password, :token)";

            $email = "sample@mail.com";
            $hashed_password = password_hash("tajneheslo123.", PASSWORD_BCRYPT);
            $token = "token123";
            $userstmt = $db->prepare($usersql);

            $userstmt->bindParam(":username", $username, PDO::PARAM_STR);
            $userstmt->bindParam(":email", $email, PDO::PARAM_STR);
            $userstmt->bindParam(":token", $token, PDO::PARAM_STR);
            $userstmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);

            $userstmt->execute();
        }

        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch();
        $pouzivatel_id = $row["id"];
        $token = $row['token'];

        $secret_token = isset($headers["x-token"]) ? $headers["x-token"] : "";
        if ($secret_token!=$token){
            throw new Exception("Secret token does not match.",401);
        }

        // $target_dir = "/home/data/import/uploads/".$folder."/";
        $target_dir = "/home/data/import/files/uploads/" . $username . "/";
        $target_file = $target_dir . $filename;

        if (strpos($filename, 'location')){
            $file_source = 'Mobile';
        } else {
            $file_source = 'Smartwatch';
        }

        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                throw new Exception("Failed to create directory.", 500);
            }
        }

        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            throw new Exception("Failed to upload.", 500);
        }

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
        $stmt->bindParam(":nazov", $filename, PDO::PARAM_STR);  // Save the CSV file name
        $stmt->bindParam(":cesta", $target_file, PDO::PARAM_STR);  // Store the CSV file path
        $stmt->bindParam(":zdroj", $file_source, PDO::PARAM_STR);  // Store the CSV file path

        $stmt->execute();

        $command = "python3 /var/www/html/track_to_database.py $filename $target_file $username $pouzivatel_id 0 $type $track_id" . " dataset 2>&1";
        exec($command, $output, $return_var);

        $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username);
        exec($command, $output, $return_var);

//    $gpsAccuracy = $_POST['gps_accuracy'];
//    $searchRadius = $_POST['search_radius'];
//    $turnPenalty = $_POST['turn_penalty_factor'];
//    $walk = $_POST['type'];

        // Define parameters
        $params = [
            'type' => $type,
            'gps_accuracy' => "5", // Ensure $gpsAccuracy is defined
            'search_radius' => "50", // Ensure $searchRadius is defined
            'turn_penalty_factor' => "200" // Ensure $turnPenalty is defined
        ];

        // Convert the parameters array to JSON
        $parametersJson = json_encode($params, JSON_PRETTY_PRINT);

        // Define the input array for the Python script
        $input = [
            'container' => $valhalla_container, // Container name
            'username' => $username, // Ensure $username is defined
            'parameters' => json_decode($parametersJson, true), // Decode back to array
            'file' => $target_file, // Ensure $gpx_file is defined
            'filename' => $filename,
            'user_id' => $pouzivatel_id,
            'track_id' => $track_id
        ];

        // Convert the input array to JSON
        $jsonInput = json_encode($input, JSON_PRETTY_PRINT);

        // Define the Python script command
        $pythonScript = "python3 /var/www/html/mapmatch.py '$jsonInput'";

        // Execute the Python script
        exec($pythonScript, $output, $return_var);

        echo json_encode(array("status" => "success"));


    } catch (Exception $exception) {
        http_response_code($exception->getCode());
        echo json_encode(array("status" => $exception->getMessage()));
    }
}

?>