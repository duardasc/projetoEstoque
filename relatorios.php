<?php
include 'conexao.php'; // Inclui a conexão com o banco de dados


session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


// Capturar o ID do cliente logado
$cliente_id = $_SESSION['user_id']; // Use o mesmo nome da variável da sessão do login


// Capturar botão selecionado
$mostrarEstoque = isset($_GET['mostrar']) ? $_GET['mostrar'] == 'estoque' : true;

// Capturar termo de busca
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';



// Consultar fornecedores
$sqlFornecedores = "SELECT id, nome FROM fornecedores"; // Corrigido para adicionar o ponto e vírgula
$stmtFornecedores = $conn->prepare($sqlFornecedores);
$stmtFornecedores->execute(); // Adicionado para executar a consulta
$fornecedores = $stmtFornecedores->fetchAll(PDO::FETCH_ASSOC);


// Consultar estoque para tabela
$sqlEstoque = "SELECT p.id, p.nome AS nome_produto, p.categoria, p.marca, p.quantidade, p.codigo, p.preco, p.minimo, f.nome AS nome_fornecedor
               FROM produtos p
               JOIN fornecedores f ON p.fornecedor_id = f.id
               WHERE p.ativo = TRUE";






// Consultar dados para o gráfico de pizza (excluindo categorias com estoque zerado)
$sqlGraficoPizza = "SELECT p.categoria AS produto_categoria, SUM(p.quantidade) AS total_quantidade
                    FROM produtos p
                    WHERE p.ativo = TRUE
                    GROUP BY p.categoria
                    HAVING SUM(p.quantidade) > 0";
$stmtGraficoPizza = $conn->prepare($sqlGraficoPizza);
$stmtGraficoPizza->execute();

$categorias = [];
$quantidades = [];

while ($row = $stmtGraficoPizza->fetch(PDO::FETCH_ASSOC)) {
  $categorias[] = $row['produto_categoria'];
  $quantidades[] = $row['total_quantidade'];
}

 

// Consultar a quantidade total de itens e itens com estoque zerado
$sqlTotalEstoque = "SELECT SUM(quantidade) AS total_quantidade FROM produtos WHERE ativo = TRUE";
$sqlZerados = "SELECT COUNT(*) AS itens_zerados FROM produtos WHERE quantidade = 0 AND ativo = TRUE";

// Consultar itens abaixo do estoque mínimo
$sqlEstoqueMinimo = "SELECT COUNT(*) AS itens_abaixo_minimo FROM produtos WHERE quantidade < minimo AND ativo = TRUE";

$resultTotalEstoque = $conn->query($sqlTotalEstoque);
$resultZerados = $conn->query($sqlZerados);
$resultEstoqueMinimo = $conn->query($sqlEstoqueMinimo);

$totalEstoque = $resultTotalEstoque->fetchColumn();
$itensZerados = $resultZerados->fetchColumn();
$itensEstoqueMinimo = $resultEstoqueMinimo->fetchColumn();


?>

<!DOCTYPE html>
<html>

<head>
  <title>Relatórios</title>
  <link rel="stylesheet" href="estilos/relatorio.css">
  <link rel="stylesheet" href="estilos/global.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
</head>

<body>
<?php include 'includes/painel_lateral.php'; ?>

  <section class="relatoriosresultado">
  <div class="container">
    <div class="hdois"><h2>Relatórios</h2></div>

    <div class="botoes-mostra">
      <a href="?mostrar=estoque"><button>Estoque</button></a>
      <a href="?mostrar=vendas"><button>Vendas</button></a>
      <a href="?mostrar=compras"><button>Compras</button></a>
      <a href="?mostrar=alteracoes"><button>Alterações</button></a>
    </div>

    <!-- Seção de Estoque -->
    <?php if ($mostrarEstoque): ?>
     

      <div class="chart-info-container">
  <div class="chart-container-pizza">
    <canvas id="pizzaChart"></canvas>
    <p><i>Estoque baseado nas categorias de produtos</i></p>
  </div>

  <div class="box-info-box">
    <div class="info-box">
      <div class="info-box-title">Total em Estoque</div>
      <?php echo htmlspecialchars($totalEstoque); ?> unidades
    </div>
    <div class="info-box">
      <div class="info-box-title">Abaixo do estoque mínimo</div>
      <?php echo htmlspecialchars($itensEstoqueMinimo); ?> itens
    </div>
    <div class="info-box">
      <div class="info-box-title">Estoque Zerado</div>
      <?php echo htmlspecialchars($itensZerados); ?> itens
    </div>
  </div>
</div>





<!-- Gráfico de pizza -->
    <script> 
      const ctx = document.getElementById('pizzaChart').getContext('2d');
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode($categorias); ?>,
          datasets: [{
            label: 'Quantidade em Estoque por Categoria',
            data: <?php echo json_encode($quantidades); ?>,
            backgroundColor: ['#df4d4d', '#5662dd', '#efc841', '#ae56dd', '#dd56b5', '#56dd65', '#dd8656'],
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: true,
            }
          }
        }
      });
    </script>

    <script>
      const ctxPizza = document.getElementById('pizzaChart').getContext('2d');
      const pizzaChart = new Chart(ctxPizza, {
        type: 'pie',
        data: {
          labels: <?php echo json_encode($categorias); ?>,
          datasets: [{
            label: 'Quantidade de Produto por Categoria',
            data: <?php echo json_encode($quantidades); ?>,
            backgroundColor: [
              'rgba(255, 99, 132, 0.1)',
              'rgba(54, 162, 235, 0.1)',
              'rgba(255, 206, 86, 0.1)',
              'rgba(75, 192, 192, 0.1)',
              'rgba(153, 102, 255, 0.2)',
              'rgba(255, 159, 64, 0.2)'
            ],
            borderColor: [
              'rgba(255, 99, 132, 1)',
              'rgba(54, 162, 235, 1)',
              'rgba(255, 206, 86, 1)',
              'rgba(75, 192, 192, 1)',
              'rgba(153, 102, 255, 1)',
              'rgba(255, 159, 64, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: false,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              padding: 20
            },
            tooltip: {
              callbacks: {
                label: function (context) {
                  let label = context.label || '';
                  if (context.parsed !== null) {
                    label += ': ' + context.parsed + ' unidades';
                  }
                  return label;
                }
              }
            }
          }
        }
      });
    </script>

   
  <?php endif; ?>

  <?php include 'relatorio_saidas.php'; ?>

  <?php include 'relatorio_alteracoes.php'; ?>

  
  <?php include 'relatorio_entradas.php'; ?>
  </section>

</body>
</html>
