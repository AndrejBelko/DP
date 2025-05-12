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

if (isset($_GET['track_ids'])) {
    try {
        $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Sanitize the 'route' parameter
        $user_id = $_SESSION['user_id']; // Cast to int for safety
        $track_ids = $_GET['track_ids']; // Get the array

        // Loop through the array
        // Konverzia track_id na int (pre bezpečnosť)
        $track_ids = array_map('intval', $track_ids);

        // Placeholder pre IN (...)
        $placeholders = implode(',', array_fill(0, count($track_ids), '?'));

        // DELETE z `tracks`, `path` a `files` tabuliek
        $tables = ['tracks', 'path', 'files'];

        foreach ($tables as $table) {
            $sql = "DELETE FROM $table WHERE track_id IN ($placeholders) AND user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([...$track_ids, $user_id]);
        }

        // CSV súbor (cesta a meno)
        $filePath2 = '/home/data/import/files/db/' . $username1 . '/' . $username1 . '_path.csv';

        // Zmazanie a vytvorenie CSV
        deleteCSV($filePath2);
        createCSV($filePath2, $db, $user_id);

        // Spustenie Python skriptu alebo zmazanie GeoJSON
        if (file_exists($filePath2)) {
            $command = escapeshellcmd("python3 /var/www/html/geohash_area.py " . $username1);
            exec($command, $output, $return_var);
        } else {
            unlink('/var/www/html/coverage/' . $username1 . '.geojson');
            unlink('/var/www/html/center/' . $username1 . '.json');
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

/**
 * Creates a CSV export of the `path` table for a specific user.
 *
 * @param string $filePath Full path to the output CSV file.
 * @param PDO $db PDO database connection object.
 * @param int $user_id ID of the user whose data is being exported.
 * @throws DateMalformedStringException
 */
function createCSV($filePath, $db, $user_id)
{
    $sql = "SELECT * FROM path where user_id = :user_id"; // Replace 'tracks' with your table name
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

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

/**
 * Deletes a file if it exists.
 *
 * @param string $filePath Path to the file to be deleted.
 */
function deleteCSV($filePath)
{
    if (file_exists($filePath)) {
        // Delete the file
        if (!unlink($filePath)) {
            die("Error: Unable to delete existing file.");
        }
    }
}
?>
