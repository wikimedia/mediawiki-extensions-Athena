<?php
$string = file_get_contents("page_data/mindworksDeleted.json");
$json_a = json_decode($string, true);
echo(count($json_a));
