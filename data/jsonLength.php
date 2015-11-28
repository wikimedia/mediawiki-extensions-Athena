<?php
$string = file_get_contents("appunti.json");
$json_a = json_decode($string, true);
echo(count($json_a));
