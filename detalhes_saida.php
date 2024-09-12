<?php
include 'conexao.php';

// Captura o ID da saída
$saida_id = isset($_GET['saida_id']) ? intval($_GET['saida_id']) : 0;


if ($saida_id > 0) {
    // Consulta principal para obter os detalhes da saída
    $sql = "SELECT s.id, s.data_saida, c.nome AS nome_cliente, d.nome AS nome_destino, i.produto_id, p.nome AS nome_produto, p.codigo AS codigo_produto, i.quantidade, p.preco
            FROM saidas s
            JOIN destino d ON s.destino_id = d.id
            JOIN itens_saida i ON s.id = i.saida_id
            JOIN produtos p ON i.produto_id = p.id
            JOIN clientes c ON s.cliente_id = c.id
            WHERE s.id = :saida_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':saida_id', $saida_id, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($resultado) {
        $totalPreco = 0;
        echo "<p><strong>ID da Saída:</strong> " . htmlspecialchars($resultado[0]['id']) . "</p>";
        echo "<p><strong>Data da Saída:</strong> " . htmlspecialchars($resultado[0]['data_saida']) . "</p>";
        echo "<p><strong>Usuário:</strong> " . htmlspecialchars($resultado[0]['nome_cliente']) . "</p>";
        echo "<p><strong>Destinatário:</strong> " . htmlspecialchars($resultado[0]['nome_destino']) . "</p>";

        echo "<h3>Produtos:   DEIXA BONITO</h3>";
        echo "<table border='1'>";
        echo "<thead><tr><th>Código do Produto</th><th>Nome do Produto</th><th>Quantidade</th><th>Preço Unitário</th><th>Preço Total</th></tr></thead>";
        echo "<tbody>";
        foreach ($resultado as $item) {
            $precoTotal = $item['quantidade'] * $item['preco'];
            $totalPreco += $precoTotal;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['codigo_produto']) . "</td>";
            echo "<td>" . htmlspecialchars($item['nome_produto']) . "</td>";
            echo "<td>" . htmlspecialchars($item['quantidade']) . "</td>";
            echo "<td>R$ " . number_format($item['preco'], 2, ',', '.') . "</td>";
            echo "<td>R$ " . number_format($precoTotal, 2, ',', '.') . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";

        echo "<p><strong>Preço Total da Saída:</strong> R$ " . number_format($totalPreco, 2, ',', '.') . "</p>";
    } else {
        echo "Detalhes da saída não encontrados.";
    }
} else {
    echo "ID da saída inválido.";
}

?>
