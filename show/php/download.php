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

if (isset($_GET['track_ids']) && isset($_GET['mapmatched'])) {
    try {
        $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $user_id = $_SESSION['user_id']; // Cast to int for safety
        $track_ids = $_GET['track_ids']; // Get the array of track IDs
        $mapmatched = (int) htmlspecialchars($_GET['mapmatched']); // Cast to int for safety

        $zipFileName = "routes_" . time() . ".zip"; // Unique ZIP file name
        $zipFilePath = "/tmp/$zipFileName"; // Store ZIP in a temporary location
        $zip = new ZipArchive();

        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== TRUE) {
            die("Error: Unable to create ZIP file.");
        }

        // Konverzia na pole integerov pre istotu
        $track_ids = array_map('intval', $track_ids);

        // Priprav placeholders pre IN (...) časť
        $placeholders = implode(',', array_fill(0, count($track_ids), '?'));

        $sql = "SELECT * FROM files WHERE track_id IN ($placeholders) AND user_id = ?";
        $stmt = $db->prepare($sql);

        // Spojenie všetkých track_ids a user_id do jedného poľa
        $params = array_merge($track_ids, [$user_id]);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($mapmatched === 1) {
                $file_path = str_replace("uploads", "mapmatched", $row['path']);
            } else {
                $file_path = $row['path'];
            }

            $zip->addFile($file_path, basename($row['path']));
        }


        // Close ZIP file
        $zip->close();

        // Serve the ZIP file for download
        if (file_exists($zipFilePath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
            header('Content-Length: ' . filesize($zipFilePath));
            ob_clean();
            flush();
            readfile($zipFilePath);

            // Delete the ZIP file after download
            unlink($zipFilePath);
            exit;
        } else {
            die("Error: ZIP file could not be created.");
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    } finally {
        unset($stmt);
        unset($db);
        header("Location: profile.php");
        exit;
    }


} else {
    echo "No route specified for download.";
}
?>
