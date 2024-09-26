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

// Consulta para os produtos mais vendidos
$sqlProdutosMaisVendidos = "SELECT p.nome AS produto_nome, SUM(i.quantidade) AS total_vendido
                            FROM itens_saida i
                            JOIN produtos p ON i.produto_id = p.id
                            GROUP BY p.nome
                            ORDER BY total_vendido DESC
                            LIMIT 5"; // Limitar a 5 produtos mais vendidos

$stmtProdutosMaisVendidos = $conn->prepare($sqlProdutosMaisVendidos);
$stmtProdutosMaisVendidos->execute();
$resultProdutosMaisVendidos = $stmtProdutosMaisVendidos->fetchAll(PDO::FETCH_ASSOC);

$produtos = [];
$quantidadesVendidas = [];

foreach ($resultProdutosMaisVendidos as $row) {
    $produtos[] = $row['produto_nome'];
    $quantidadesVendidas[] = $row['total_vendido'];
}



// Seção de Vendas
if ($mostrarVendas): ?>


    
    <div class="chart-container">
        <div class="chart-vendas"><div class="select-container">
        <form method="GET" action="">
            <input type="hidden" name="mostrar" value="vendas">
            <div><h4>Quantidade de Vendas e Preço Total por Mês</h4></div>

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
        <canvas id="vendasPrecoChart"></canvas>
        </div>
    </div>

    <h4>Produtos Mais Vendidos</h4>
    <canvas id="produtosMaisVendidosChart"></canvas>

    <div class="hdois"><h3>Informações das Saídas</h3></div>

    <div class="search-container" style="position: relative;">
    <button id="btnFiltro" onclick="toggleFiltro()" >
        <i class="fas fa-filter" id="toggleIcon"></i> Filtro
    </button>

    <form method="GET" action="" >
        <input type="hidden" name="mostrar" value="vendas">
        <input type="text" name="search" placeholder="Pesquisar por destinatário" value="<?php echo htmlspecialchars($searchTerm); ?>">
        <input type="submit" value="Pesquisar">
    </form>
</div>

<!-- Formulário de Filtro de Data -->
<div id="filter-date-container" >
    <form method="GET" action="">
        <input type="hidden" name="mostrar" value="vendas">

        <label for="data_inicio">Data Início:</label>
        <input type="date" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? htmlspecialchars($_GET['data_inicio']) : ''; ?>">
        <br>
        <label for="data_fim">Data Fim:</label>
        <input type="date" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? htmlspecialchars($_GET['data_fim']) : ''; ?>">
        <br>
        <button type="submit" value="Filtrar">Filtrar</button>
        <button type="button" onclick="limparFiltro()">Limpar Filtro</button>
    </form>
</div>
<script>
    function limparFiltro() {
        // Redireciona para a página sem parâmetros de data e de pesquisa
        window.location.href = '?mostrar=vendas';
    }
</script>

    <table >
        <thead>
            <tr>
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


function toggleFiltro() {
        var filtroContainer = document.getElementById("filter-date-container");
        filtroContainer.style.display = filtroContainer.style.display === "none" || filtroContainer.style.display === "" ? "block" : "none";
    }

    function limparFiltro() {
        // Redireciona para a página sem parâmetros de data e de pesquisa
        window.location.href = '?mostrar=vendas';
    }


    const ctxProdutos = document.getElementById('produtosMaisVendidosChart').getContext('2d');
new Chart(ctxProdutos, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($produtos); ?>,
        datasets: [{
            label: 'Quantidade Vendida',
            data: <?php echo json_encode($quantidadesVendidas); ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
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


    </script>
<?php endif; ?>


<style>

#btnFiltro {
    background-color: #dd5684; /* Cor rosa */
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
}

#btnFiltro:hover {
    background-color: #c3446f; /* Cor rosa escura */
}

#filter-date-container {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        border-radius: 5px;
        display: none; /* Inicialmente escondido */
        position: absolute;  
        text-align: center;
        justify-self: center;
        background-color: white; 
        padding: 0.6%; 
        line-height: 2.3rem;
        border: 1px solid #ccc;
        z-index: 100; /* Garante que fique acima de outros elementos */
        left: 24.5rem;

        button{
            background-color: #dd5684; /* Cor rosa */
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    margin: 2%;
        }

        button:hover {
    background-color: #c3446f; /* Cor rosa escura */
}
    }

    .chart-vendas{
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4%;
  background: #fbfbfb;
  height: 20rem;
  border-radius: 8px;
  box-shadow: 0 0 10px rgb(0 0 0 / 13%);
  width: 40%;
  margin: 0 auto;
  justify-content: space-around;
  margin-top: 3rem;
  align-items: center;
  justify-content: center;

  h4{
    margin-bottom: 6%;
  }
}


</style>