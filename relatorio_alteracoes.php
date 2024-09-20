<?php
include 'conexao.php';

// Capturar botão selecionado
$mostrarAlteracoes = isset($_GET['mostrar']) && $_GET['mostrar'] == 'alteracoes';

// Definindo o número de registros por página
$registrosPorPagina = 10; // Limite de 10 registros por página
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $registrosPorPagina;

try {
    // Consulta SQL para contar o total de registros
    $sqlContagem = "SELECT COUNT(*) FROM alteracoes_produtos";
    $stmtContagem = $conn->prepare($sqlContagem);
    $stmtContagem->execute();
    $totalRegistros = $stmtContagem->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Consulta SQL para recuperar as informações da tabela alteracoes_produtos
    $sql = "
        SELECT ap.id, ap.motivo, ap.data_hora, p.codigo AS produto_codigo, c.nome AS cliente_nome, a.acao_tipo
        FROM alteracoes_produtos ap
        JOIN produtos p ON ap.produto_id = p.id
        JOIN clientes c ON ap.cliente_id = c.id
        JOIN acoes a ON ap.acao_id = a.id
        ORDER BY ap.data_hora DESC
        LIMIT :limit OFFSET :offset"; // Adicionando LIMIT e OFFSET

    // Preparar e executar a consulta
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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

    <!-- Navegação da paginação -->
    <div class="pagination">
        <?php if ($paginaAtual > 1): ?>
            <a href="?mostrar=alteracoes&pagina=<?php echo $paginaAtual - 1; ?>">« Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?mostrar=alteracoes&pagina=<?php echo $i; ?>" class="<?php echo $i == $paginaAtual ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="?mostrar=alteracoes&pagina=<?php echo $paginaAtual + 1; ?>">Próximo »</a>
        <?php endif; ?>
    </div>

<?php endif; ?>

</body>
</html>
<style>
        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            padding: 5px 10px;
            margin: 0 5px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: #000;
        }

        .pagination a.active {
            background-color: #ff69b4; /* Cor de fundo rosa */
            color: white; /* Cor do texto */
            border: 1px solid #ff69b4; /* Borda rosa */
        }
    </style>