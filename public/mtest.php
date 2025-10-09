<?php
$mysqli = mysqli_init();
$ok = mysqli_real_connect($mysqli, 'localhost', 'rides', 'UltraStrong!2025#Abc', null, null, '/var/run/mysqld/mysqld.sock');
var_dump($ok);
if (!$ok) { echo " | ".mysqli_connect_errno()." ".mysqli_connect_error(); exit; }
$r = $mysqli->query("SELECT USER(), @@socket");
var_dump($r->fetch_row());