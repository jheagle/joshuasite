<?php

header('Content-Type: application/javascript');
$googleRepo = 'https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js';
$jQueryLib = file_get_contents($googleRepo);
if ($jQueryLib === false) {
    $jQueryLib = file_get_contents('./jquery.min.js');
}
echo $jQueryLib;


