<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your database configuration
require_once('config.php');

// Start the session if needed
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

        // Determine the second ID to delete based on parity of the route
        $secondaryRoute = ($route % 2 === 0) ? $route + 1 : $route - 1;

        // Prepare and execute DELETE statements for the database
        $sql = "DELETE FROM tracks WHERE route = :route";
        $stmt = $db->prepare($sql);

        $stmt->bindParam(':route', $route, PDO::PARAM_INT);
        $stmt->execute();

        $stmt->bindParam(':route', $secondaryRoute, PDO::PARAM_INT);
        $stmt->execute();

        $sql = "DELETE FROM path WHERE track = :track";
        $stmt = $db->prepare($sql);

        $stmt->bindParam(':track', $route, PDO::PARAM_INT);
        $stmt->execute();

        $stmt->bindParam(':track', $secondaryRoute, PDO::PARAM_INT);
        $stmt->execute();

        $filePath1 = '/home/data/import/files/db/'. $username1 . '/' . $username1 . '_track.csv';
        deleteCSV($filePath1);
        createCSV($filePath1,$db, "tracks");

        $filePath2 = '/home/data/import/files/db/' . $username1 . '/' . $username1 . '_path.csv';
        deleteCSV($filePath2);
        createCSV($filePath2,$db, "path");

        // Execute external Python script
        if (file_exists($filePath2)){
            $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username1);
            exec($command, $output, $return_var);
        } else{
            unlink('/var/www/html/coverage/'.$username1.'.geojson');
        }

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    } finally {
        unset($stmt); // Clean up
        unset($db);   // Close the connection
        // Uncomment for redirection after testing
         header("Location: profile.php");
         exit;
    }
} else {
    echo "No route specified for deletion.";
}


function createCSV($filePath, $db, $table)
{
    $sql = "SELECT * FROM ".$table; // Replace 'tracks' with your table name
    $stmt = $db->query($sql);

    // Fetch all rows as an associative array
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($data) {
        // Open the file for writing
        $fileHandle = fopen($filePath, 'w');
        if ($fileHandle === false) {
            die('Error: Unable to open file for writing.');
        }

        // Write the header row (column names)
        fputcsv($fileHandle, array_keys($data[0]));

        // Write the data rows
        foreach ($data as $row) {
            fputcsv($fileHandle, $row);
        }

        // Close the file
        fclose($fileHandle);

    }
}

function deleteCSV($filePath)
{
    if (file_exists($filePath)) {
        // Delete the file
        if (!unlink($filePath)) {
            die("Error: Unable to delete existing file.");
        } else {
            echo "Existing file deleted successfully.<br>";
        }
    }
}
?>
