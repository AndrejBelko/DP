<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}


$username = $_SESSION['username'];
$relativeFilePath = $_GET['file'] ?? '';
$filePath = "/home/data/import/files/uploads/" . $username . "/" . $relativeFilePath;

if (!file_exists($filePath)) {
    echo json_encode([]);
    exit;
}

$gpsData = [];
$header = true;
if (($handle = fopen($filePath, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($header) {
            $header = FALSE;
            continue;
        }
        $latitude = $data[4]; // Adjust the index as per your CSV format
        $longitude = $data[5]; // Adjust the index as per your CSV format
        $time = strtotime($data[3]);
        $speed = $data[7];
        $height = $data[6];
        $gpsData[] = [$latitude, $longitude, $time, $speed, $height];
    }
    fclose($handle);
}

echo json_encode($gpsData);
?>