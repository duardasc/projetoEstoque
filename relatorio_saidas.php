<?php
include 'conexao.php';

// Verificar se a requisição é para detalhes de venda (AJAX)
if (isset($_GET['detalhes']) && $_GET['detalhes'] == 'true' && isset($_GET['venda_id'])) {
    $vendaId = $_GET['venda_id'];

    // Atualize o código que consulta os detalhes da venda
$sqlDetalhes = "SELECT p.codigo AS produto_codigo, p.nome AS produto_nome, i.quantidade, p.preco, (i.quantidade * p.preco) AS total_produto
FROM itens_saida i
JOIN produtos p ON i.produto_id = p.id
WHERE i.saida_id = :vendaId";

    $stmtDetalhes = $conn->prepare($sqlDetalhes);
    $stmtDetalhes->bindParam(':vendaId', $vendaId, PDO::PARAM_INT);
    $stmtDetalhes->execute();
    $detalhesVenda = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);

    if ($detalhesVenda) {
        echo "<table border='1'>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Preço Unitário (R$)</th>
                        <th>Total (R$)</th>
                    </tr>
                </thead>
                <tbody>";
        foreach ($detalhesVenda as $item) {
            echo "<tr>
                    <td>" . htmlspecialchars($item['produto_nome']) . "</td>
                    <td>" . htmlspecialchars($item['quantidade']) . "</td>
                    <td>R$ " . number_format($item['preco'], 2, ',', '.') . "</td>
                    <td>R$ " . number_format($item['total_produto'], 2, ',', '.') . "</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>Detalhes da venda não encontrados.</p>";
    }
    
    exit;  // Encerrar o script após exibir os detalhes para evitar o carregamento do resto da página
}

// Capturar botão selecionado
$mostrarVendas = isset($_GET['mostrar']) && $_GET['mostrar'] == 'vendas';

// Capturar termo de busca
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Consultar vendas e compras com filtro de data opcional
$sqlVendas = "SELECT s.id AS venda_id, s.data_saida AS data_venda, 
                     SUM(i.quantidade * p.preco) AS preco_total,
                     d.nome AS destino_nome, c.nome AS cliente_nome
              FROM saidas s
              JOIN itens_saida i ON s.id = i.saida_id
              JOIN produtos p ON i.produto_id = p.id
              JOIN clientes c ON s.cliente_id = c.id
              JOIN destino d ON s.destino_id = d.id
              WHERE d.nome LIKE :searchTerm";

// Adicionando filtros de data se fornecidos
if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $sqlVendas .= " AND s.data_saida >= :dataInicio";
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $sqlVendas .= " AND s.data_saida <= :dataFim";
}

$sqlVendas .= " GROUP BY s.id, s.data_saida, d.nome, c.nome";

$stmtVendas = $conn->prepare($sqlVendas);
$searchTermParam = "%$searchTerm%";
$stmtVendas->bindParam(':searchTerm', $searchTermParam, PDO::PARAM_STR);

// Vincular parâmetros de data se fornecidos
if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $stmtVendas->bindParam(':dataInicio', $_GET['data_inicio'], PDO::PARAM_STR);
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $stmtVendas->bindParam(':dataFim', $_GET['data_fim'], PDO::PARAM_STR);
}

$stmtVendas->execute();
$resultVendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);


// Seção de Vendas
if ($mostrarVendas): ?>
    <div class="hdois"><h3>Relatório de Vendas</h3></div>


    <div class="select-container">
        <form method="GET" action="">
            <input type="hidden" name="mostrar" value="vendas">
            <select name="ano" onchange="this.form.submit()">
                <option value="">Selecione o ano</option>
                <?php
                $anos = $conn->query("SELECT DISTINCT YEAR(data_saida) AS ano FROM saidas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($anos as $ano) {
                    echo "<option value=\"$ano\"" . ($ano == (isset($_GET['ano']) ? $_GET['ano'] : '') ? ' selected' : '') . ">$ano</option>";
                }
                ?>
            </select>
        </form>
    </div>
    <div class="chart-container">
        <div class="hdois"><h4>Quantidade de Vendas e Preço Total por Mês</h4></div>
        <canvas id="vendasPrecoChart"></canvas>
    </div>
    <div class="search-container">
        <form method="GET" action="">
            <input type="hidden" name="mostrar" value="vendas">
            <input type="text" name="search" placeholder="Pesquisar por destinatário" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <input type="submit" value="Buscar">
        </form>
    </div>


        <!-- Formulário de Filtro de Data -->
<!-- Formulário de Filtro de Data -->
<div class="filter-date-container">
    <form method="GET" action="">
        <input type="hidden" name="mostrar" value="vendas">
        
        <label for="data_inicio">Data Início:</label>
        <input type="date" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? htmlspecialchars($_GET['data_inicio']) : ''; ?>">
        
        <label for="data_fim">Data Fim:</label>
        <input type="date" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? htmlspecialchars($_GET['data_fim']) : ''; ?>">
        
        <input type="submit" value="Filtrar">
        <button type="button" onclick="limparFiltro()">Limpar Filtro</button>
    </form>
</div>

<script>
    function limparFiltro() {
        // Redireciona para a página sem parâmetros de data e de pesquisa
        window.location.href = '?mostrar=vendas';
    }
</script>

    <table border="1">
        <thead>
            <tr>
                <th>ID da Venda</th>
                <th>Data da Venda</th>
                <th>Total da Venda (R$)</th>
                <th>Destinatário</th>
                <th>Cliente</th>
                <th>Detalhes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultVendas as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['venda_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_venda']); ?></td>
                    <td>R$ <?php echo number_format($row['preco_total'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($row['destino_nome']); ?></td>
                    <td><?php echo htmlspecialchars($row['cliente_nome']); ?></td>
                    <td><button type="button" onclick="mostrarDetalhes(<?php echo htmlspecialchars($row['venda_id']); ?>)">Ver Detalhes</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Modal para Detalhes da Venda -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h3>Detalhes da Venda</h3>
            <div id="detalhesVenda"></div>
        </div>
    </div>

    <?php
    $anoFiltro = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

    // Consulta para quantidade de vendas por mês
    $sqlQuantidadeVendas = "SELECT MONTH(s.data_saida) AS mes, SUM(i.quantidade) AS total_vendas
                            FROM saidas s
                            JOIN itens_saida i ON s.id = i.saida_id
                            WHERE YEAR(s.data_saida) = :ano
                            GROUP BY MONTH(s.data_saida)
                            ORDER BY MONTH(s.data_saida)";
    $stmtQuantidadeVendas = $conn->prepare($sqlQuantidadeVendas);
    $stmtQuantidadeVendas->bindParam(':ano', $anoFiltro, PDO::PARAM_INT);
    $stmtQuantidadeVendas->execute();
    $resultQuantidadeVendas = $stmtQuantidadeVendas->fetchAll(PDO::FETCH_ASSOC);

    // Consulta para preço total por mês
    $sqlPrecoTotal = "SELECT MONTH(s.data_saida) AS mes, SUM(i.quantidade * p.preco) AS total_preco
                      FROM saidas s
                      JOIN itens_saida i ON s.id = i.saida_id
                      JOIN produtos p ON i.produto_id = p.id
                      WHERE YEAR(s.data_saida) = :ano
                      GROUP BY MONTH(s.data_saida)
                      ORDER BY MONTH(s.data_saida)";
    $stmtPrecoTotal = $conn->prepare($sqlPrecoTotal);
    $stmtPrecoTotal->bindParam(':ano', $anoFiltro, PDO::PARAM_INT);
    $stmtPrecoTotal->execute();
    $resultPrecoTotal = $stmtPrecoTotal->fetchAll(PDO::FETCH_ASSOC);

    $meses = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $quantidadesVendas = array_fill(0, 12, 0);
    $totalPrecos = array_fill(0, 12, 0);

    foreach ($resultQuantidadeVendas as $row) {
        $mesIndex = $row['mes'] - 1; // Ajustar o índice do mês
        if ($mesIndex >= 0 && $mesIndex < 12) {
            $quantidadesVendas[$mesIndex] = $row['total_vendas'];
        }
    }

    foreach ($resultPrecoTotal as $row) {
        $mesIndex = $row['mes'] - 1; // Ajustar o índice do mês
        if ($mesIndex >= 0 && $mesIndex < 12) {
            $totalPrecos[$mesIndex] = $row['total_preco'];
        }
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('vendasPrecoChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($meses); ?>,
                datasets: [
                    {
                        label: 'Preço Total (R$)',
                        data: <?php echo json_encode($totalPrecos); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Quantidade de Vendas',
                        data: <?php echo json_encode($quantidadesVendas); ?>,
                        type: 'line',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: false,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function mostrarDetalhes(vendaId) {
    fetch(`detalhes_saida.php?saida_id=${vendaId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalhesVenda').innerHTML = html;
            document.getElementById('modalDetalhes').style.display = 'block';
        });
}

function fecharModal() {
    document.getElementById('modalDetalhes').style.display = 'none';
}

    </script>
<?php endif; ?>
