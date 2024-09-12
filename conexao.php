<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "estoque";
$port = "3306"; 

try {
  $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "";
} catch(PDOException $e) {
  echo "Erro na conexão: " . $e->getMessage();
}
