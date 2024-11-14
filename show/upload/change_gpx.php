<?php
function change_gpx_to_csv($gpx_path, $csv_path)
{
// Load the GPX file
    $gpx = simplexml_load_file($gpx_path);

// Define namespaces to access extended data
    $namespaces = $gpx->getNamespaces(true);

// Open a file in write mode ('w') to store the CSV data
    $csvFile = fopen($csv_path, "w");

// Write column headers to the CSV file, including Heartbeat
    fputcsv($csvFile, ['latitude', 'longitude', 'elevation', 'time', 'speed', 'cadence', 'heartbeat']);

// Loop through each track point in the GPX file
    foreach ($gpx->trk->trkseg->trkpt as $trkpt) {
        // Extract latitude, longitude, elevation, and time
        $lat = (string)$trkpt['lat'];
        $lon = (string)$trkpt['lon'];
        $ele = (string)$trkpt->ele;
        $time = (string)$trkpt->time;

        // Initialize speed, cadence, and heartbeat
        $speed = $cadence = $heartbeat = '0'; // Default values in case they are not present

        // Check if extensions are present and extract speed, cadence, and heartbeat
        if (isset($trkpt->extensions)) {
            $ext = $trkpt->extensions->children($namespaces['ns3']);
            $cadence = isset($ext->cad) ? (string)$ext->cad : '0';
            $heartbeat = isset($ext->hr) ? (string)$ext->hr : '0';
            $speed = isset($ext->speed) ? (string)$ext->speed : '0';

        }

        // Write the track point data to the CSV file
        fputcsv($csvFile, [$lat, $lon, $ele, $time, $speed, $cadence, $heartbeat]);
    }

// Close the CSV file
    fclose($csvFile);
}

?>
