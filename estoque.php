<?php
include 'conexao.php'; // Inclui a conexão com o banco de dados


session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


// Capturar o ID do cliente logado
$cliente_id = $_SESSION['user_id']; // Use o mesmo nome da variável da sessão do login


// Capturar termo de busca
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';



// Consultar fornecedores
$sqlFornecedores = "SELECT id, nome FROM fornecedores"; // Corrigido para adicionar o ponto e vírgula
$stmtFornecedores = $conn->prepare($sqlFornecedores);
$stmtFornecedores->execute(); // Adicionado para executar a consulta
$fornecedores = $stmtFornecedores->fetchAll(PDO::FETCH_ASSOC);



// Capturar termo de busca original
$searchTermOriginal = isset($_GET['search']) ? $_GET['search'] : '';

// Preparar o termo de busca para o SQL (com os caracteres %)
$searchTerm = $searchTermOriginal;
if ($searchTerm) {
  $searchTerm = "%$searchTerm%";
}

// Consultar estoque para tabela
$sqlEstoque = "SELECT p.id, p.nome AS nome_produto, p.categoria, p.marca, p.quantidade, p.codigo, p.preco, p.minimo, f.nome AS nome_fornecedor
               FROM produtos p
               JOIN fornecedores f ON p.fornecedor_id = f.id
               WHERE p.ativo = TRUE";

if ($searchTermOriginal) { // Utilize $searchTermOriginal aqui para verificar se há uma busca
  $sqlEstoque .= " AND (p.codigo LIKE :searchTerm OR p.nome LIKE :searchTerm)";
}

$stmtEstoque = $conn->prepare($sqlEstoque);
if ($searchTermOriginal) { // Utilize $searchTermOriginal aqui para vincular o termo de pesquisa
  $stmtEstoque->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR); // Usando $searchTerm com os curingas
}

$stmtEstoque->execute();
$resultEstoque = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);



$acaoTipo = 'deletar'; // Tipo de ação que você está registrando

// Função para obter o ID da ação
function getAcaoId($conn, $acaoTipo) {
    $sql = "SELECT id FROM acoes WHERE acao_tipo = :acao_tipo";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':acao_tipo', $acaoTipo, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}



/// Processar a exclusão do produto
if (isset($_POST['confirmar'])) {
  $produto_id = $_POST['produto_id'];
  $motivo = $_POST['motivo'];
  $acao_id = getAcaoId($conn, 'deletar'); // Obter o ID da ação de exclusão
  
  // Iniciar transação
  $conn->beginTransaction();
  
  try {
      // Atualizar o status do produto para inativo
      $sqlExcluir = "UPDATE produtos SET ativo = FALSE WHERE id = :produto_id";
      $stmtExcluir = $conn->prepare($sqlExcluir);
      $stmtExcluir->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
      $stmtExcluir->execute();

      // Gravar a exclusão na tabela "alteracoes_produtos"
      $sqlAlteracao = "INSERT INTO alteracoes_produtos (produto_id, cliente_id, motivo, data_hora, acao_id) VALUES (:produto_id, :cliente_id, :motivo, NOW(), :acao_id)";
      $stmtAlteracao = $conn->prepare($sqlAlteracao);
      $stmtAlteracao->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
      $stmtAlteracao->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
      $stmtAlteracao->bindParam(':motivo', $motivo, PDO::PARAM_STR);
      $stmtAlteracao->bindParam(':acao_id', $acao_id, PDO::PARAM_INT);

      $stmtAlteracao->execute();

      // Confirmar transação
      $conn->commit();

      // Redirecionar após exclusão
      header('Location: ' . $_SERVER['PHP_SELF'] );
      exit;
  } catch (PDOException $e) {
      // Reverter transação em caso de erro
      $conn->rollBack();
      echo "<p>Erro ao excluir o produto: " . $e->getMessage() . "</p>";
  }
}

if (isset($_POST['editar'])) {
  $produto_id = $_POST['produto_id'];
  $nome = $_POST['nome'];
  $minimo = $_POST['minimo'];
  $categoria = $_POST['categoria'];
  $marca = $_POST['marca'];
  $preco = $_POST['preco'];
  $fornecedor_id = !empty($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : null; // Verifique se o fornecedor_id está definido e não está vazio

  $motivo = $_POST['motivo'];
  $acao_id = getAcaoId($conn, 'editar'); // Obter o ID da ação de edição
  
  // Iniciar transação
  $conn->beginTransaction();
  
  try {
      // Atualizar dados do produto, incluindo o fornecedor_id se ele estiver definido
      $sqlAtualizar = "UPDATE produtos SET nome = :nome, categoria = :categoria, marca = :marca, minimo = :minimo, preco = :preco" . 
                      ($fornecedor_id !== null ? ", fornecedor_id = :fornecedor_id" : "") . 
                      " WHERE id = :produto_id";
      $stmtAtualizar = $conn->prepare($sqlAtualizar);
      $stmtAtualizar->bindParam(':nome', $nome, PDO::PARAM_STR);
      $stmtAtualizar->bindParam(':minimo', $minimo, PDO::PARAM_INT);
      $stmtAtualizar->bindParam(':categoria', $categoria, PDO::PARAM_STR);
      $stmtAtualizar->bindParam(':marca', $marca, PDO::PARAM_STR);
      $stmtAtualizar->bindParam(':preco', $preco, PDO::PARAM_STR);
      if ($fornecedor_id !== null) {
          $stmtAtualizar->bindParam(':fornecedor_id', $fornecedor_id, PDO::PARAM_INT); // Corrigido para passar o fornecedor_id
      }
      $stmtAtualizar->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
      $stmtAtualizar->execute();

      // Gravar alteração na tabela "alteracoes_produtos"
      $sqlAlteracao = "INSERT INTO alteracoes_produtos (produto_id, cliente_id, motivo, data_hora, acao_id) VALUES (:produto_id, :cliente_id, :motivo, NOW(), :acao_id)";
      $stmtAlteracao = $conn->prepare($sqlAlteracao);
      $stmtAlteracao->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
      $stmtAlteracao->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
      $stmtAlteracao->bindParam(':motivo', $motivo, PDO::PARAM_STR);
      $stmtAlteracao->bindParam(':acao_id', $acao_id, PDO::PARAM_INT);
      $stmtAlteracao->execute();

      // Confirmar transação
      $conn->commit();

      header('Location: ' . $_SERVER['PHP_SELF'] );
      exit;
  } catch (PDOException $e) {
      // Reverter transação em caso de erro
      $conn->rollBack();
      echo "<p>Erro ao editar o produto: " . $e->getMessage() . "</p>";
  }
}

// Defina o número de resultados por página
$resultsPerPage = 10;

// Obtenha o número total de produtos
$sqlCount = "SELECT COUNT(*) FROM produtos WHERE ativo = TRUE";
$totalResults = $conn->query($sqlCount)->fetchColumn();
$totalPages = ceil($totalResults / $resultsPerPage);

// Calcule a página atual
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, min($totalPages, $currentPage));

// Calcule o deslocamento
$offset = ($currentPage - 1) * $resultsPerPage;

// Modifique a consulta SQL para incluir LIMIT e OFFSET
$sqlEstoque .= " LIMIT :limit OFFSET :offset";
$stmtEstoque = $conn->prepare($sqlEstoque);
if ($searchTermOriginal) {
  $stmtEstoque->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
}
$stmtEstoque->bindParam(':limit', $resultsPerPage, PDO::PARAM_INT);
$stmtEstoque->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmtEstoque->execute();
$resultEstoque = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>
  <title>Relatórios</title>
  <link rel="stylesheet" href="estilos/global.css">
  
</head>

<body>
<?php include 'includes/painel_lateral.php'; ?>

  <section class="relatoriosresultado">
  <di class="container">

   
<div class="hdois"><h3>Informações do Estoque</h3></div>
<div class="search-container">
  <form method="GET" action="" id="pesquisa">
    <input type="hidden" name="mostrar" value="estoque">
    <input type="text" name="search" placeholder="Pesquisar por código ou nome" value="<?php echo htmlspecialchars($searchTermOriginal); ?>">
    <input type="submit" value="Pesquisar">
  </form>
</div>


      <!-- Verifica se há produtos encontrados -->
      <?php if (empty($resultEstoque)): ?>
        <p class="error-message">Nenhum produto encontrado para "<?php echo htmlspecialchars($searchTerm); ?>".</p>
      <?php else: ?>
        <table >
          <thead>
            <tr>
              <th>Código</th>
              <th>Nome</th>
              <th>Categoria</th>
              <th>Marca</th>
              <th>Quant. em Estoque</th>
              <th>Quant. mínima</th>
              <th>Preço Unitário</th>
              <th>Fornecedor</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resultEstoque as $row): ?>

              
              <tr>
                <td><?php echo htmlspecialchars($row['codigo'] ?: 'Código não disponível'); ?></td>
                <td><?php echo htmlspecialchars($row['nome_produto']); ?></td>
                <td><?php echo htmlspecialchars($row['categoria']); ?></td>
                <td><?php echo htmlspecialchars($row['marca']); ?></td>
                <td><?php echo htmlspecialchars($row['quantidade']); ?></td>
                <td><?php echo htmlspecialchars($row['minimo']); ?></td>
                <td>R$ <?php echo htmlspecialchars($row['preco']); ?> </td>
                <td><?php echo htmlspecialchars($row['nome_fornecedor']); ?></td>
                <td>
                <form method="POST" action="">
                
                <input type="hidden" name="produto_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                <button type="button" id="botaoeditar" onclick="openEditModal(
    <?php echo htmlspecialchars($row['id']); ?>,
    '<?php echo htmlspecialchars($row['nome_produto']); ?>',
    '<?php echo htmlspecialchars($row['minimo']); ?>',
    '<?php echo htmlspecialchars($row['categoria']); ?>',
    '<?php echo htmlspecialchars($row['marca']); ?>',
    '<?php echo htmlspecialchars($row['preco']); ?>',
    '<?php echo htmlspecialchars($row['nome_fornecedor']); ?>' // Pass the supplier name as an additional parameter
)">Editar</button>
                    <input type="button" id="botaoexcluir" value="Excluir" onclick="openDeleteModal(<?php echo htmlspecialchars($row['id']); ?>)">

          </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        
  <div class="pagination">
  <?php if ($currentPage > 1): ?>
    <a href="?mostrar=estoque&page=<?php echo $currentPage - 1; ?>">« Anterior</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?mostrar=estoque&page=<?php echo $i; ?>" <?php if ($i == $currentPage) echo 'class="active"'; ?>><?php echo $i; ?></a>
  <?php endfor; ?>

  <?php if ($currentPage < $totalPages): ?>
    <a href="?mostrar=estoque&page=<?php echo $currentPage + 1; ?>">Próxima »</a>
  <?php endif; ?>
</div>
       
      <?php endif; ?>
    </div>



   <!-- Modal de exclusão -->
   <div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <p>Por favor, informe o motivo da exclusão do produto:</p>
        <form id="deleteForm" method="post" action="">
            <input type="hidden" id="productId" name="produto_id">
            <div>
                <textarea name="motivo" rows="4" cols="50" required></textarea>
            </div>
            <div>
                <button type="submit" id="confirmardelete" name="confirmar">Confirmar Exclusão</button>
            </div>
        </form>
    </div>
</div>



     <!-- Modal para edição -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <div class="hdois"><h2>Editar Produto</h2></div>
      <form method="POST" action="">
        <input type="hidden" name="produto_id" id="editProdutoId">
        <label for="editNome">Nome:</label>
        <input type="text" name="nome" id="editNome" required>

        <label for="editMinimo">Estoque mínimo:</label>
        <input type="number" name="minimo" id="editMinimo" required>

        <label for="editCategoria">Categoria:</label>
        <input type="text" name="categoria" id="editCategoria" required>

        <label for="editMarca">Marca:</label>
        <input type="text" name="marca" id="editMarca" required>

<label for="fornecedor_pesquisa">Fornecedor:</label>
<input type="text" id="fornecedor_pesquisa" placeholder="Digite o nome do fornecedor" autocomplete="off" required>
<input type="hidden" id="fornecedor_id" name="fornecedor_id" required>
<ul id="fornecedor_lista" class="autocomplete-list"></ul>

<label for="editPreco">Preço:</label>
    <input type="number" name="preco" step="0.01" id="editPreco" required>

        <label for="editMotivo">Motivo da Alteração:</label>
        <textarea name="motivo" id="editMotivo" required></textarea>

        <input type="submit" id="confirmaredit" name="editar" value="Salvar Alterações">   
      </form>
    </div>
  </div>



  <!-- Script para abrir/fechar o modal -->
  <script>
// Função para consultar fornecedores


function openEditModal(produtoId, nome, minimo, categoria, marca, preco, fornecedorNome) {
    document.getElementById('editProdutoId').value = produtoId;
    document.getElementById('editNome').value = nome;
    document.getElementById('editMinimo').value = minimo;
    document.getElementById('editCategoria').value = categoria;
    document.getElementById('editMarca').value = marca;
    document.getElementById('editPreco').value = preco;
    document.getElementById('fornecedor_pesquisa').value = fornecedorNome;
    document.getElementById('editModal').style.display = 'block';
}






  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  function openDeleteModal(produtoId) {
    document.getElementById('productId').value = produtoId;
    document.getElementById('deleteModal').style.display = 'block';
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
  }
  </script>









    <script>
document.addEventListener('DOMContentLoaded', () => {
    const fornecedores = <?php echo json_encode($fornecedores); ?>;

    function atualizarLista(listaElement, dados, query, inputHidden, inputText) {
        listaElement.innerHTML = '';
        if (query === '') {
            listaElement.style.display = 'none'; // Esconde a lista se o campo estiver vazio
            return;
        }
        listaElement.style.display = 'block'; // Mostra a lista se o campo tiver algum valor
        const filtro = dados.filter(item => item.nome.toLowerCase().includes(query.toLowerCase()));
        filtro.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.nome;
            li.addEventListener('click', function() {
                inputText.value = item.nome;
                inputHidden.value = item.id;
                listaElement.innerHTML = '';
            });
            listaElement.appendChild(li);
        });
    }

    const fornecedorPesquisa = document.getElementById('fornecedor_pesquisa');
    const fornecedorLista = document.getElementById('fornecedor_lista');
    const fornecedorIdInput = document.getElementById('fornecedor_id');

    fornecedorPesquisa.addEventListener('input', function() {
        const query = fornecedorPesquisa.value;
        atualizarLista(fornecedorLista, fornecedores, query, fornecedorIdInput, fornecedorPesquisa);
    });

    document.addEventListener('click', function(event) {
        if (!event.target.matches('#fornecedor_pesquisa')) {
            fornecedorLista.style.display = 'none'; // Esconde a lista ao clicar fora
        }
    });
});



</script>


  </section>

</body>
</html>
