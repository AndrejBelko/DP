<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your database configuration
require_once('config.php');

// Start the session
session_start();

if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

$username1 = $_SESSION["username"];

if (isset($_GET['route'])) {
    // Sanitize the 'route' parameter
    $route = (int) htmlspecialchars($_GET['route']); // Cast to int for safety

    try {
        // Connect to the database
        $db = new PDO("mysql:host=$hostname;dbname=$username1", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch the geometry data from the database
        $sql = "SELECT * FROM tracks WHERE route = :route";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':route', $route, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            die("Error: Route not found in the database.");
        }

        // Decode the GeoJSON data
        $geojson = json_decode($row['track'], true);

        if (!isset($geojson['geometry']['coordinates'])) {
            die("Error: Invalid GeoJSON data.");
        }

        $coordinates = $geojson['geometry']['coordinates'];

        // Create a CSV file
        $fileName = "route_$route.csv";
        $filePath = "/tmp/$fileName"; // Use a temporary directory
        $fileHandle = fopen($filePath, 'w');
        if (!$fileHandle) {
            die("Error: Unable to create CSV file.");
        }

        // Write the header row
        fputcsv($fileHandle, ['Longitude', 'Latitude']);

        // Write the coordinates to the CSV file
        foreach ($coordinates as $coordinate) {
            if (is_array($coordinate) && count($coordinate) === 2) {
                fputcsv($fileHandle, $coordinate);
            }
        }

        // Close the file
        fclose($fileHandle);

        // Serve the file for download
        if (file_exists($filePath)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            ob_clean();
            flush();
            readfile($filePath);

            // Optionally delete the file after download
            unlink($filePath);
            exit;
        } else {
            die("Error: File could not be created.");
        }

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    } finally {
        unset($stmt); // Clean up
        unset($db);   // Close the connection
        header("Location: profile.php");
        exit;
    }
} else {
    echo "No route specified for download.";
}
?>
