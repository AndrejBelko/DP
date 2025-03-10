<?php

require_once('config.php');
session_start();

header("Content-Type: application/json; charset=UTF-8");
//$_POST['pattern'] = ["wx4g28p","wx4g0y9","wx4g0jk"];

$dbName = $_POST['dbName'];
$type = intval($_POST['type']);

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

$sql = "SELECT id FROM users WHERE username = :username";
$stmt = $db->prepare($sql);
$stmt->bindParam(":username", $dbName, PDO::PARAM_STR);
$stmt->execute();
$row = $stmt->fetch();
$user_id = $row["id"];

if (isset($_SESSION["username"]) && $_SESSION["loggedin"] === true) {
    if ($dbName === $_SESSION["username"]) {
        $user_id = intval($_SESSION['user_id']);
    }
}

$input = "$type $user_id 2>&1";

$output = shell_exec("python3 /home/data/search/show_all_tracks.py " . $input);
echo $output;
 
?>
