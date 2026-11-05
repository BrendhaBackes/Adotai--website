<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require '../api/db_conn.php';

$nome = 'Teste PHP'; $tel = '41999999999'; $email = 'teste_php@exemplo.com';
$hash = password_hash('12345678', PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO usuario (nome, status_conta, telefone, email, senha, data_criacao_conta)
     VALUES (?, 'Ativa', ?, ?, ?, CURDATE())"
);
$stmt->bind_param('ssss', $nome, $tel, $email, $hash);
$stmt->execute();
$id = $conn->insert_id;
echo "Inserted with id $id<br>";

// remove the test row
$stmt = $conn->prepare('DELETE FROM usuario WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
echo "Test row deleted. Writing works!";

?>  