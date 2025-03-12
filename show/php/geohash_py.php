<?php
require_once('config.php');
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

$dbName = $_POST['dbName'];
$path = json_encode($_POST['pattern']);
$min = intval($_POST['start']);
$max = intval($_POST['end']);
$gap = intval($_POST['gap']);
$matches = intval($_POST['match']);
$type = $_POST['type'];
$user_id = $_SESSION['user_id'];

$input = "$path $min $max $gap $matches $dbName $type $user_id 2>&1";
//echo "python3 /home/data/search/geohash.py $input";

//$output = shell_exec("C:\Users\maros\.virtualenvs\zobrazenie-SYSuuEtd\Scripts\python.exe .\geohash.py $input");

$output = shell_exec("python3 /home/data/search/geohash.py " . $input);
echo $output;
 
?>
