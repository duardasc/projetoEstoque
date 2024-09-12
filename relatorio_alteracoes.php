<?php

include 'conexao.php';

// Capturar botão selecionado
$mostrarAlteracoes = isset($_GET['mostrar']) && $_GET['mostrar'] == 'alteracoes';


// Inclua o arquivo de conexão com o banco de dados
require_once 'conexao.php'; // Certifique-se de que o caminho do arquivo de conexão esteja correto

try {
    // Consulta SQL para recuperar todas as informações da tabela alteracoes_produtos
    $sql = "
        SELECT ap.id, ap.motivo, ap.data_hora, p.codigo AS produto_codigo, c.nome AS cliente_nome, a.acao_tipo
        FROM alteracoes_produtos ap
        JOIN produtos p ON ap.produto_id = p.id
        JOIN clientes c ON ap.cliente_id = c.id
        JOIN acoes a ON ap.acao_id = a.id
        ORDER BY ap.data_hora DESC"; // Ordena por data_hora de forma decrescente

    // Preparar e executar a consulta
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    // Recupera os resultados
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erro ao buscar os dados: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Alterações de Produtos</title>
    <link rel="stylesheet" href="style.css"> <!-- Opcional: inclua seu arquivo CSS -->
   
</head>
<body>

<?php if ($mostrarAlteracoes): ?>

    <div class="hdois"><h2>Relatório de Alterações de Produtos</h2></div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Motivo</th>
            <th>Data e Hora</th>
            <th>Produto</th>
            <th>Usuário</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($resultados)): ?>
            <?php foreach ($resultados as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['motivo']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_hora']); ?></td>
                    <td><?php echo htmlspecialchars($row['produto_codigo']); ?></td>
                    <td><?php echo htmlspecialchars($row['cliente_nome']); ?></td>
                    <td><?php echo htmlspecialchars($row['acao_tipo']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Nenhuma alteração encontrada.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>
</body>
</html>
