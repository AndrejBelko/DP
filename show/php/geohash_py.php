<?php
require_once('config.php');
session_start();
//if (!isset($_SESSION["username"]) || $_SESSION["loggedin"] !== true) {
//    header("Location: login.php");
//    exit;
//}

$dbName = $_POST['dbName'];
$path = json_encode($_POST['pattern']);
$min = intval($_POST['start']);
$max = intval($_POST['end']);
$gap = intval($_POST['gap']);
$matches = intval($_POST['match']);
$type = $_POST['type'];
if($_POST['dataset'] == null){
    $user_id = $_SESSION['user_id'];
} else{
    try {
        $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
    $sql = "SELECT * FROM users WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":username", $_POST['dataset'], PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch();
    $user_id = intval($row["id"]);
}

$input = "$path $min $max $gap $matches $dbName $type $user_id 2>&1";

$output = shell_exec("python3 /home/data/search/geohash.py " . $input);
echo $output;
 
?>
