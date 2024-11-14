<?php

$input = json_encode($_POST['pattern'])." ".json_encode($_POST['search'])." ".$_POST['nwa_match']." ".$_POST['nwa_mismatch']." ".$_POST['nwa_gap']
." ".$_POST['alpha']." ".$_POST['beta']." ".$_POST['distance'];

$output = shell_exec("python3 /home/data/search/nwa.py ".$input);
echo $output;
 
?>