<?php
header("Content-Type: application/json; charset=UTF-8");
//$_POST['pattern'] = ["wx4g28p","wx4g0y9","wx4g0jk"];
$dbName = $_POST['dbName'];
$path = json_encode($_POST['pattern']);
$min = intval($_POST['start']);
$max = intval($_POST['end']);
$gap = intval($_POST['gap']);
$matches = intval($_POST['match']);
$type = $_POST['type'];

$input = "$path $min $max $gap $matches $dbName $type 2>&1";
//echo "python3 /home/data/search/geohash.py $input";

//$output = shell_exec("C:\Users\maros\.virtualenvs\zobrazenie-SYSuuEtd\Scripts\python.exe .\geohash.py $input");

$output = shell_exec("python3 /home/data/search/geohash.py " . $input);
echo $output;
 
?>
