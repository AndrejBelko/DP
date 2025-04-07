<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$relativeFilePath = $_GET['file'] ?? '';

if (strtolower(pathinfo($relativeFilePath, PATHINFO_EXTENSION)) != 'csv'){
    $relativeFilePath = pathinfo($relativeFilePath, PATHINFO_FILENAME) . '.csv';
}

$filePath = "/home/data/import/files/uploads/" . $username . "/" . $relativeFilePath;

if (!file_exists($filePath)) {
    echo json_encode([]);
    exit;
}

$gpsData = [];
$header = null;
if (($handle = fopen($filePath, "r")) !== FALSE) {
    // Read the header row first
    if (($headerRow = fgetcsv($handle, 1000, ",")) !== FALSE) {

        // Normalize header names: lowercase and trim
        $normalizedHeader = array_map(function($col) {
            return strtolower(trim($col));
        }, $headerRow);

        // Create a mapping of normalized column names to indexes
        $columnMap = array_flip($normalizedHeader);

        // Define possible variations for required columns
        $requiredColumns = [
            "latitude" => ["latitude", "lat", "latitude n/s"],
            "longitude" => ["longitude", "lon", "longitude e/w"],
            "time" => ["time", "timestamp", "date"],
            "speed" => ["speed", "velocity"],
            "height" => ["height", "altitude"],
            "hr" => ["hr"]
        ];

        // Match required columns to actual columns in the file
        $mappedColumns = [];
        foreach ($requiredColumns as $key => $possibleNames) {
            foreach ($possibleNames as $name) {
                if (isset($columnMap[$name])) {
                    $mappedColumns[$key] = $columnMap[$name];
                    break;
                }
            }
        }

        // Read the rest of the rows
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $latitude = $data[$mappedColumns["latitude"]];
            $longitude = $data[$mappedColumns["longitude"]];
            if (strtotime($data[$mappedColumns["time"]]) !== false) {
                // Handles ISO 8601 or other standard formats
                $time = strtotime($data[$mappedColumns["time"]]);
            } elseif (strlen($data[$mappedColumns["time"]]) > 10) {
                // Unix timestamp in milliseconds
                $timestampInSeconds = $data[$mappedColumns["time"]] / 1000;
                $time = $timestampInSeconds; // Use directly or convert to readable date
            } elseif (strlen($data[$mappedColumns["time"]]) === 6) {
                $time = strtotime($data[$mappedColumns["time"]]);
            } elseif (strlen($data[$mappedColumns["time"]]) === 5){
                $time = strtotime('0'. $data[$mappedColumns["time"]]);
            }
            $hr = $data[$mappedColumns["hr"]];
            $speed = $data[$mappedColumns["speed"]];
            $height = $data[$mappedColumns["height"]];
            $gpsData[] = [$latitude, $longitude, $time, $speed, $height, $hr];
        }
    }
    fclose($handle);
}

echo json_encode($gpsData);
?>
