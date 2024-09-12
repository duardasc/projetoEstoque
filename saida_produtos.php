
<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit();
}

$erros = [];

// Processamento do formulário de saída de produtos


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $cliente_id = isset($_POST["cliente_id"]) ? $_POST["cliente_id"] : null;
  $destino_id = isset($_POST["destino_id"]) ? $_POST["destino_id"] : null;
  $data_saida = isset($_POST["data_saida"]) ? $_POST["data_saida"] : null;
  $produtos = isset($_POST["produto_id"]) ? $_POST["produto_id"] : [];
  $quantidades = isset($_POST["quantidade"]) ? $_POST["quantidade"] : [];

  if ($cliente_id && $destino_id && $data_saida && !empty($produtos)) {
      try {
          // Inicia a transação
          $conn->beginTransaction();

          // Primeiro, verifica se todas as quantidades estão disponíveis
          foreach ($produtos as $index => $produto_id) {
              $quantidade = $quantidades[$index];
              $sqlProduto = "SELECT id, codigo, quantidade FROM produtos WHERE id = :produto_id AND ativo = 1";
              $stmtProduto = $conn->prepare($sqlProduto);
              $stmtProduto->bindParam(':produto_id', $produto_id);
              $stmtProduto->execute();
              $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

              if (!$produto) {
                  $erros[] = "Produto com o código não encontrado ou não está ativo.";
                  continue;
              }

              if ($produto['quantidade'] < $quantidade) {
                  $erros[] = "Quantidade insuficiente em estoque para o produto com o código: " . $produto['codigo'] . ". Disponível: " . $produto['quantidade'] . ", Solicitado: " . $quantidade . ".";
              }
          }

          // Se houver erros, não prossegue com a inserção
          if (!empty($erros)) {
              $_SESSION['erros'] = $erros;
              header('Location: saida_produtos.php'); // Redireciona para evitar o reenvio do formulário
              exit();
          }

          // Inserir a saída no banco de dados
          $sqlSaida = "INSERT INTO saidas (data_saida, cliente_id, destino_id) VALUES (:data_saida, :cliente_id, :destino_id)";
          $stmtSaida = $conn->prepare($sqlSaida);
          $stmtSaida->bindParam(':data_saida', $data_saida);
          $stmtSaida->bindParam(':cliente_id', $cliente_id);
          $stmtSaida->bindParam(':destino_id', $destino_id);

          if (!$stmtSaida->execute()) {
              throw new Exception("Erro ao inserir na tabela 'saidas'.");
          }
          
          $saida_id = $conn->lastInsertId();

          // Inserir múltiplos produtos na tabela 'itens_saida'
          $sqlItem = "INSERT INTO itens_saida (saida_id, produto_id, quantidade) VALUES (:saida_id, :produto_id, :quantidade)";
          foreach ($produtos as $index => $produto_id) {
              $quantidade = $quantidades[$index];

              // Inserir o produto no 'itens_saida'
              $stmtItem = $conn->prepare($sqlItem);
              $stmtItem->bindParam(':saida_id', $saida_id);
              $stmtItem->bindParam(':produto_id', $produto_id);
              $stmtItem->bindParam(':quantidade', $quantidade);

              if (!$stmtItem->execute()) {
                  throw new Exception("Erro ao inserir na tabela 'itens_saida'.");
              }

              // Atualizar o estoque
              $sqlUpdate = "UPDATE produtos SET quantidade = quantidade - :quantidade WHERE id = :produto_id";
              $stmtUpdate = $conn->prepare($sqlUpdate);
              $stmtUpdate->bindParam(':quantidade', $quantidade);
              $stmtUpdate->bindParam(':produto_id', $produto_id);

              if (!$stmtUpdate->execute()) {
                  throw new Exception("Erro ao atualizar o estoque para o produto com o código: " . $produto['codigo']);
              }
          }

          // Confirma a transação se não houver erros
          $conn->commit();
          $_SESSION['success'] = "Saída de produtos registrada com sucesso!";
          header('Location: saida_produtos.php'); // Redireciona para evitar o reenvio do formulário
          exit();
      } catch (Exception $e) {
          // Reverte a transação em caso de erro
          $conn->rollback();
          $_SESSION['erros'] = ["Erro ao registrar a saída de produtos: " . $e->getMessage()];
          header('Location: saida_produtos.php'); // Redireciona para evitar o reenvio do formulário
          exit();
      }
  } else {
      $_SESSION['erros'] = ["Erro: Por favor, preencha todos os campos obrigatórios."];
      header('Location: saida_produtos.php'); // Redireciona para evitar o reenvio do formulário
      exit();
  }
}

// Consultas SQL para buscar dados
$sqlProdutos = "SELECT id, nome, codigo FROM produtos WHERE ativo = 1"; 
$stmtProdutos = $conn->query($sqlProdutos);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

$sqlClientes = "SELECT id, nome FROM clientes";
$stmtClientes = $conn->query($sqlClientes);
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

$sqlDestino = "SELECT id, nome, cpf_cnpj, inscricao_estadual, cep, estado, cidade, bairro, logradouro, numero, telefone, email FROM destino";
$stmtDestino = $conn->query($sqlDestino);
$destino = $stmtDestino->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-saida">
  <h2>Registrar Saída de Produtos</h2>

  <form method="post" class="product-form">
    <label for="cliente_pesquisa">Usuário:</label>
    <input type="text" id="cliente_pesquisa" placeholder="Digite o nome do usuário" autocomplete="off" required>
    <input type="hidden" id="cliente_id" name="cliente_id" required>
    <ul id="cliente_lista" class="autocomplete-list"></ul>

    <label for="destino_pesquisa">Destinatário:</label>
    <input type="text" id="destino_pesquisa" placeholder="Digite o destinatário" autocomplete="off" required>
    <input type="hidden" id="destino_id" name="destino_id" required>
    <ul id="destino_lista" class="autocomplete-list"></ul>

    <label for="data_saida">Data da Venda:</label>
    <input type="date" id="data_saida" name="data_saida" value="<?php echo date('Y-m-d'); ?>" required>

    <div id="produtos-container">
        <!-- Primeira linha de produto -->
        <div class="produto-linha">
            <label for="produto_pesquisa_1">Produto:</label>
            <input type="text" class="produto_pesquisa" placeholder="Digite o código ou nome" autocomplete="off" required>
            <input type="hidden" name="produto_id[]" class="produto_id">
            <ul class="autocomplete-list produto_lista"></ul>

            <label for="quantidade_1">Quantidade:</label>
            <input type="number" name="quantidade[]" class="quantidade" min="1" required>
            <div id="error-messages">
      <?php
      if (isset($_SESSION['erros'])) {
          $erros = $_SESSION['erros'];
          unset($_SESSION['erros']); // Limpa a variável de sessão após exibição
          echo '<div class="alert alert-danger">';
          echo '<ul>';
          foreach ($erros as $erro) {
              echo '<li>' . $erro . '</li>';
          }
          echo '</ul>';
          echo '</div>';
      }

      if (isset($_SESSION['success'])) {
          echo '<div class="alert alert-success">';
          echo $_SESSION['success'];
          echo '</div>';
          unset($_SESSION['success']); // Limpa a variável de sessão após exibição
      }
      ?>
    </div>
        </div>
    </div>

    <button type="button" class="botao-adicionar-produto" onclick="adicionarProduto()">Adicionar Produto</button>

    <input type="submit" value="Registrar Saída" class="submit-btn">
</form>

</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const produtos = <?php echo json_encode($produtos); ?>;
    const clientes = <?php echo json_encode($clientes); ?>;
    const destino = <?php echo json_encode($destino); ?>;

    // Função para adicionar um novo produto ao formulário
    window.adicionarProduto = function() {
        const produtosContainer = document.getElementById('produtos-container');
        const novaLinha = document.createElement('div');
        novaLinha.className = 'produto-linha';
        
        novaLinha.innerHTML = `
            <label>Produto:</label>
            <input type="text" class="produto_pesquisa" placeholder="Digite o código ou nome do produto" autocomplete="off" required>
            <input type="hidden" name="produto_id[]" class="produto_id">
            <ul class="autocomplete-list produto_lista"></ul>

            <label>Quantidade:</label>
            <input type="number" name="quantidade[]" class="quantidade" min="1" required>
        `;

        produtosContainer.appendChild(novaLinha);
        adicionarEventosAutocompleteProduto(novaLinha);
    }

    function adicionarEventosAutocompleteProduto(linha) {
        const produtoPesquisa = linha.querySelector('.produto_pesquisa');
        const produtoLista = linha.querySelector('.produto_lista');
        const produtoIdInput = linha.querySelector('.produto_id');

        produtoPesquisa.addEventListener('input', function() {
            const query = produtoPesquisa.value;
            atualizarLista(produtoLista, produtos, query, produtoIdInput, produtoPesquisa);
        });
    }

    // Função de autocomplete para produtos, clientes e destinos
    function atualizarLista(listaElement, dados, query, inputHidden, inputText) {
        listaElement.innerHTML = '';
        if (query) {
            const filtro = dados.filter(item => item.nome.toLowerCase().includes(query.toLowerCase()) || item.codigo && item.codigo.toLowerCase().includes(query.toLowerCase()));
            filtro.forEach(item => {
                const li = document.createElement('li');
                li.textContent = `${item.codigo ? item.codigo + ' - ' : ''}${item.nome}`;
                li.addEventListener('click', function() {
                    inputText.value = item.nome;
                    inputHidden.value = item.id;
                    listaElement.innerHTML = '';
                });
                listaElement.appendChild(li);
            });
        }
        // Se o campo de pesquisa estiver vazio, a lista deve estar oculta
        listaElement.style.display = query ? 'block' : 'none';
    }

    // Iniciar autocompletes na primeira linha de produto
    adicionarEventosAutocompleteProduto(document.querySelector('.produto-linha'));

   // Evento para busca de clientes
    const clientePesquisa = document.getElementById('cliente_pesquisa');
    const clienteLista = document.getElementById('cliente_lista');
    const clienteIdInput = document.getElementById('cliente_id');

    clientePesquisa.addEventListener('input', function() {
        const query = clientePesquisa.value;
        atualizarLista(clienteLista, clientes, query, clienteIdInput, clientePesquisa);

        // Verifica se o campo está vazio e limpa a lista
        if (!query) {
            clienteLista.innerHTML = '';
        }
    });

    // Evento para busca de destino
    const destinoPesquisa = document.getElementById('destino_pesquisa');
    const destinoLista = document.getElementById('destino_lista');
    const destinoIdInput = document.getElementById('destino_id');

    destinoPesquisa.addEventListener('input', function() {
        const query = destinoPesquisa.value;
        atualizarLista(destinoLista, destino, query, destinoIdInput, destinoPesquisa);

        // Verifica se o campo está vazio e limpa a lista
        if (!query) {
            destinoLista.innerHTML = '';
        }
    });

    // Evento para busca de produtos em cada linha
    document.querySelectorAll('.produto_pesquisa').forEach(input => {
        input.addEventListener('input', function() {
            const query = input.value;
            const lista = input.nextElementSibling;
            const hiddenInput = input.nextElementSibling.nextElementSibling;
            atualizarLista(lista, produtos, query, hiddenInput, input);

            // Verifica se o campo está vazio e limpa a lista
            if (!query) {
                lista.innerHTML = '';
            }
        });
    });

    // Fechar lista ao clicar fora
    document.addEventListener('click', function(event) {
        if (!event.target.matches('.produto_pesquisa')) {
            document.querySelectorAll('.produto_lista').forEach(list => list.innerHTML = '');
        }
        if (!event.target.matches('#cliente_pesquisa')) {
            clienteLista.innerHTML = '';
        }
        if (!event.target.matches('#destino_pesquisa')) {
            destinoLista.innerHTML = '';
        }
    });
});



</script>
