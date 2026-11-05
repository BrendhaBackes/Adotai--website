<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli('localhost', 'root', '', 'adotai_site', 3306);
    $conn->set_charset('utf8mb4');
    echo "Connected to the database!<br>";
    echo "MySQL server version: " . $conn->server_info;
    $result = $conn->query('SELECT id, nome, email FROM usuario');
    echo "<br><br>Users found: " . $result->num_rows . "<br>";
    while ($row = $result->fetch_assoc()) {
        echo $row['id'] . ' - ' . $row['nome'] . ' - ' . $row['email'] . '<br>';
    }
} catch (mysqli_sql_exception $e) {
    echo "Connection failed: " . $e->getMessage();
}




?>