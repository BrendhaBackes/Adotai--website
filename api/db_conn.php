<?php
$servername = "127.0.0.1";
$port       = 3306;
$username   = "root";
$password   = "";
$dbname     = "adotai_site";

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>