<?php
include 'conexao.php';

// Capturar botão selecionado
$mostrarCompras = isset($_GET['mostrar']) && $_GET['mostrar'] == 'compras';

// Capturar termo de busca
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Consulta SQL para buscar as compras
$sqlCompras = "SELECT 
                  e.id AS compra_id, 
                  e.data_entrada AS data_compra, 
                  p.nome AS nome_produto, 
                  p.codigo, 
                  f.nome AS nome_fornecedor,  -- Nome do fornecedor
                  e.quantidade, 
                  p.preco AS preco_unitario, 
                  (e.quantidade * p.preco) AS preco_total, 
                  c.nome AS cliente_nome
              FROM 
                  entradas e
              JOIN 
                  produtos p ON e.produto_id = p.id
              JOIN 
                  fornecedores f ON p.fornecedor_id = f.id  -- JOIN com fornecedores
              JOIN 
                  clientes c ON e.cliente_id = c.id";

if ($searchTerm) {
    $sqlCompras .= " WHERE p.codigo LIKE :searchTerm";
}

$stmtCompras = $conn->prepare($sqlCompras);
if ($searchTerm) {
    $stmtCompras->bindValue(':searchTerm', '%' . $searchTerm . '%', PDO::PARAM_STR);
}
$stmtCompras->execute();
$resultCompras = $stmtCompras->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($mostrarCompras): ?>
    <div class="hdois"><h3>Relatório de Compras</h3></div>
    <div class="select-container">
        <form method="GET" action="">
            <input type="hidden" name="mostrar" value="compras">
            <select name="ano" onchange="this.form.submit()">
                <option value="">Selecione o ano</option>
                <?php
                $anos = $conn->query("SELECT DISTINCT YEAR(data_entrada) AS ano FROM entradas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($anos as $ano) {
                    echo "<option value=\"$ano\"" . ($ano == (isset($_GET['ano']) ? $_GET['ano'] : '') ? ' selected' : '') . ">$ano</option>";
                }
                ?>
            </select>
        </form>
    </div>
    <div class="chart-container">
        <div class="hdois"><h4>Quantidade de Compras e Preço Total por Mês</h4></div>
        <canvas id="comprasPrecoChart"></canvas>
    </div>
    <div class="search-container">
        <form method="GET" action="">
            <input type="hidden" name="mostrar" value="compras">
            <input type="text" name="search" placeholder="Pesquisar por código do produto" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <input type="submit" value="Buscar">
        </form>
    </div>
    <table border="1">
        <thead>
            <tr>
                <th>ID da Compra</th>
                <th>Data da Compra</th>
                <th>Produto</th>
                <th>Código</th>
                <th>Quantidade</th>
                <th>Preço Unitário</th>
                <th>Preço Total</th>
                <th>Fornecedor</th>  
                <th>Usuário</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultCompras as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['compra_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['data_compra']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_produto']); ?></td>
                    <td><?php echo htmlspecialchars($row['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($row['quantidade']); ?></td>
                    <td>R$ <?php echo number_format($row['preco_unitario'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($row['preco_total'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_fornecedor']); ?></td>  
                    <td><?php echo htmlspecialchars($row['cliente_nome']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    $anoFiltro = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
    
    // Consulta para quantidade de compras por mês
    $sqlQuantidadeCompras = "SELECT MONTH(e.data_entrada) AS mes, SUM(e.quantidade) AS total_compras
                            FROM entradas e
                            WHERE YEAR(e.data_entrada) = :ano
                            GROUP BY MONTH(e.data_entrada)
                            ORDER BY MONTH(e.data_entrada)";
    $stmtQuantidadeCompras = $conn->prepare($sqlQuantidadeCompras);
    $stmtQuantidadeCompras->bindParam(':ano', $anoFiltro, PDO::PARAM_INT);
    $stmtQuantidadeCompras->execute();
    $resultQuantidadeCompras = $stmtQuantidadeCompras->fetchAll(PDO::FETCH_ASSOC);

    // Consulta para preço total por mês
    $sqlPrecoTotal = "SELECT MONTH(e.data_entrada) AS mes, SUM(e.quantidade * p.preco) AS total_preco
                      FROM entradas e
                      JOIN produtos p ON e.produto_id = p.id
                      WHERE YEAR(e.data_entrada) = :ano
                      GROUP BY MONTH(e.data_entrada)
                      ORDER BY MONTH(e.data_entrada)";
    $stmtPrecoTotal = $conn->prepare($sqlPrecoTotal);
    $stmtPrecoTotal->bindParam(':ano', $anoFiltro, PDO::PARAM_INT);
    $stmtPrecoTotal->execute();
    $resultPrecoTotal = $stmtPrecoTotal->fetchAll(PDO::FETCH_ASSOC);

    $meses = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $quantidadesCompras = array_fill(0, 12, 0);
    $totalPrecos = array_fill(0, 12, 0);

    foreach ($resultQuantidadeCompras as $row) {
        $quantidadesCompras[$row['mes'] - 1] = $row['total_compras'];
    }

    foreach ($resultPrecoTotal as $row) {
        $totalPrecos[$row['mes'] - 1] = $row['total_preco'];
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('comprasPrecoChart').getContext('2d');
        
        // Verifique se o gráfico já existe e se existe, destrua-o antes de criar um novo
        if (window.myChart) {
            window.myChart.destroy();
        }

        window.myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($meses); ?>,
                datasets: [
                    {
                        label: 'Quantidade de Compras',
                        type: 'bar',
                        data: <?php echo json_encode($quantidadesCompras); ?>,
                        backgroundColor: 'rgba(255, 182, 193, 0.4)',
                        borderColor: 'rgba(245, 135, 212, 1)',
                        borderWidth: 1.5
                    },
                    {
                        label: 'Preço Total (R$)',
                        type: 'bar',
                        data: <?php echo json_encode($totalPrecos); ?>,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.3)',
                        borderWidth: 2,
                        yAxisID: 'y2'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (context.parsed.y !== null) {
                                    label += ': ' + context.parsed.y;
                                    if (context.dataset.type === 'line') {
                                        label += ' R$';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantidade de Compras'
                        }
                    },
                    y2: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Preço Total (R$)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
<?php endif; ?>

<style>
    .chart-container {
        width: 100%;
        max-height: 500px; /* Altura máxima definida para o gráfico */
        position: relative;
        overflow: hidden; /* Garante que o gráfico não transborde */
    }

    #comprasPrecoChart {
        width: 100% !important;
        height: 100% !important;
    }
</style>
