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

if (isset($_GET['user_id']) && isset($_GET['track_ids']) && isset($_GET['mapmatched'])) {
    try {
        $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Sanitize the 'user_id' parameter
        $user_id = (int) htmlspecialchars($_GET['user_id']); // Cast to int for safety
        // Security check: Ensure the session user is the same
        if ($_SESSION['user_id'] != $user_id) {
            header("Location: profile.php");
            exit;
        }
        $track_ids = $_GET['track_ids']; // Get the array of track IDs
        $mapmatched = (int) htmlspecialchars($_GET['mapmatched']); // Cast to int for safety
        $zipFileName = "routes_" . time() . ".zip"; // Unique ZIP file name
        $zipFilePath = "/tmp/$zipFileName"; // Store ZIP in a temporary location
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== TRUE) {
            die("Error: Unable to create ZIP file.");
        }
        foreach ($track_ids as $track_id) {
            $track_id = (int) $track_id; // Ensure it's an integer
            echo $track_id;

            // Fetch the geometry data from the database
            $sql = "SELECT * FROM files WHERE track_id = :track_id AND user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':track_id', $track_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                continue; // Skip this track if not found
            }
            if ($mapmatched === 1){
                $file_path = str_replace("uploads", "mapmatched", $row['path']);
            } else{
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
//        header("Location: profile.php");
//        exit;
    }


} else {
    echo "No route specified for download.";
}
?>
