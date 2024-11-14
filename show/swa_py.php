<?php

$input = json_encode($_POST['pattern'])." ".json_encode($_POST['search'])." ".$_POST['swa_match']." ".$_POST['swa_mismatch']." ".$_POST['swa_gap']
." ".$_POST['min_score']." ".$_POST['distance'];

$output = shell_exec("python3 /home/data/search/swa.py ".$input);
echo $output;
 
?>