<?php
include 'conexao.php';

if (isset($_GET['query'])) {
    $query = $_GET['query'];
    $sql = "SELECT id, nome, codigo FROM produtos WHERE ativo = 1 AND (nome LIKE :query OR codigo LIKE :query)";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':query', '%' . $query . '%');
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($produtos);
}
?>