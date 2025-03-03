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

if (isset($_GET['user_id']) && isset($_GET['track_ids'])) {
    try {
        $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Sanitize the 'route' parameter
        $user_id = (int) htmlspecialchars($_GET['user_id']); // Cast to int for safety

        if ($_SESSION['user_id'] != $user_id) {
            header("Location: profile.php");
            exit;
        }

        $track_ids = $_GET['track_ids']; // Get the array

        // Loop through the array
        foreach ($track_ids as $track_id) {

            $track_id = (int) $track_id;

            // Prepare and execute DELETE statements for the database
            $sql = "DELETE FROM tracks WHERE track_id = :track_id and user_id = :user_id";
            $stmt = $db->prepare($sql);

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':track_id', $track_id, PDO::PARAM_INT);
            $stmt->execute();

            $sql = "DELETE FROM path WHERE track_id = :track_id and user_id = :user_id";
            $stmt = $db->prepare($sql);

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':track_id', $track_id, PDO::PARAM_INT);
            $stmt->execute();

//        $filePath1 = '/home/data/import/files/db/'. $username1 . '/' . $username1 . '_track.csv';
//        deleteCSV($filePath1);
//        createCSV($filePath1,$db, "tracks");
//
            $filePath2 = '/home/data/import/files/db/' . $username1 . '/' . $username1 . '_path.csv';
            deleteCSV($filePath2);
            createCSV($filePath2,$db, "path");
//
//        // Execute external Python script
            if (file_exists($filePath2)){
                $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username1);
                exec($command, $output, $return_var);
            } else{
                unlink('/var/www/html/coverage/'.$username1.'.geojson');
            }
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    } finally {
        unset($stmt); // Clean up
        unset($db);   // Close the connection
        // Uncomment for redirection after testing
//            header("Location: profile.php");
//            exit;
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
        fputcsv($fileHandle, array_keys($data[0]), ';');

        // Write the data rows
        foreach ($data as $row) {
            // Check if the 'timestamp' field exists and format it
            if (isset($row['timestamp'])) {
                $dateTime = new DateTime($row['timestamp']);
                $row['timestamp'] = $dateTime->format('Y-m-d\TH:i:s\Z'); // Format to 'YYYY-MM-DDTHH:MM:SSZ'
            }

            // Write the row to the CSV
            fputcsv($fileHandle, $row, ';');
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
