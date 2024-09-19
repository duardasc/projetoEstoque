<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$erros = [];

// Obtém o ID do usuário da sessão
$usuario_id = $_SESSION['user_id'];  // Ajuste conforme o formato da sessão

// Processamento do formulário de saída de produtos
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $destino_id = isset($_POST["destino_id"]) ? $_POST["destino_id"] : null;
    $produtos = isset($_POST["produto_id"]) ? $_POST["produto_id"] : [];
    $quantidades = isset($_POST["quantidade"]) ? $_POST["quantidade"] : [];

    if ($destino_id && !empty($produtos)) {
        try {
            // Inicia a transação
            $conn->beginTransaction();

            // Verifica se todas as quantidades estão disponíveis
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
            $sqlSaida = "INSERT INTO saidas (data_saida, cliente_id, destino_id) VALUES (NOW(), :cliente_id, :destino_id)";
            $stmtSaida = $conn->prepare($sqlSaida);
            $stmtSaida->bindParam(':cliente_id', $usuario_id); // Define o cliente_id com o ID do usuário da sessão
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

$sqlDestino = "SELECT id, nome, cpf_cnpj, inscricao_estadual, cep, estado, cidade, bairro, logradouro, numero, telefone, email FROM destino";
$stmtDestino = $conn->query($sqlDestino);
$destino = $stmtDestino->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/painel_lateral.php'; ?>

<div class="container-saida">
    <h2>Registrar Saída de Produtos</h2>

    <form method="post" class="product-form">
        <label for="destino_pesquisa">Destinatário:</label>
        <input type="text" id="destino_pesquisa" placeholder="Digite o destinatário" autocomplete="off" required>
        <input type="hidden" id="destino_id" name="destino_id" required>
        <ul id="destino_lista" class="autocomplete-list-destino"></ul>

        <div id="produtos-container">
          
            <!-- Primeira linha de produto -->
            <div class="produto-linha">
                  <!-- Botão de fechar para remover o produto -->
            <button type="button" class="fechar-produto" style="display: none;"
                onclick="removerProduto(this)">x</button>

                <div class="produto-nome-container">
                    <label for="produto_pesquisa_1">Produto:</label>
                    <input type="text" class="produto_pesquisa" placeholder="Digite o código ou nome" autocomplete="off"
                        required>
                    <input type="hidden" name="produto_id[]" class="produto_id">
                    <ul class="autocomplete-list-produto produto_lista"></ul>
                </div>
                <div class="produto-quantidade-container">
                    <label for="quantidade_1">Quantidade:</label>
                    <input type="number" name="quantidade[]" class="quantidade" min="1" required>
                    <div id="error-messages">
                    </div>




                </div>

            </div>
        </div>
        <!-- Botão para adicionar produtos -->
        <button type="button" class="botao-adicionar-produto" onclick="adicionarProduto()">Adicionar Produto</button>


        <input type="submit" value="Registrar Saída" class="submit-btn">


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
    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const produtos = <?php echo json_encode($produtos); ?>;
        const destino = <?php echo json_encode($destino); ?>;

        // Função para adicionar um novo produto ao formulário
        window.adicionarProduto = function () {
            const produtosContainer = document.getElementById('produtos-container');
            const produtoLinhas = produtosContainer.querySelectorAll('.produto-linha');

            
            const novaLinha = document.createElement('div');
            novaLinha.className = 'produto-linha';

            novaLinha.innerHTML = ` 
       <button type="button" class="fechar-produto" onclick="removerProduto(this)">x</button>
        <div class="produto-nome-container">
            <label for="produto_pesquisa_1">Produto:</label>
            <input type="text" class="produto_pesquisa" placeholder="Digite o código ou nome" autocomplete="off" required>
            <input type="hidden" name="produto_id[]" class="produto_id">
            <ul class="autocomplete-list-produto produto_lista"></ul>
        </div>
        <div class="produto-quantidade-container">
            <label for="quantidade_1">Quantidade:</label>
            <input type="number" name="quantidade[]" class="quantidade" min="1" required>
            <div id="error-messages"></div>
        </div>
    `;

            produtosContainer.appendChild(novaLinha);
            adicionarEventosAutocompleteProduto(novaLinha);

            // Exibe o botão de remover em todas as linhas
            if (produtoLinhas.length >= 1) {
                document.querySelectorAll('.fechar-produto').forEach(botao => {
                    botao.style.display = 'inline-block';
                });
            }
        }

        // Função para remover produto
        window.removerProduto = function (button) {
            const linhaProduto = button.closest('.produto-linha');
            linhaProduto.remove();

            // Se houver apenas um produto restante, esconde os botões de remover
            const produtoLinhas = document.querySelectorAll('.produto-linha');
            if (produtoLinhas.length === 1) {
                document.querySelectorAll('.fechar-produto').forEach(botao => {
                    botao.style.display = 'none';
                });
            }
        }

        // Função de autocomplete para produtos
        function adicionarEventosAutocompleteProduto(linha) {
            const inputProduto = linha.querySelector('.produto_pesquisa');
            const listaProduto = linha.querySelector('.produto_lista');

            inputProduto.addEventListener('input', () => {
                listaProduto.innerHTML = '';
                const valor = inputProduto.value.toLowerCase();
                if (valor === '') {
                    listaProduto.style.display = 'none';
                    return;
                }
                listaProduto.style.display = 'block';

                // Filtra os produtos baseados na letra inicial ou código
                const produtosFiltrados = produtos.filter(produto =>
                    produto.nome.toLowerCase().startsWith(valor) ||
                    produto.codigo.toLowerCase().startsWith(valor)
                );

                produtosFiltrados.forEach(produto => {
                    const item = document.createElement('li');
                    item.textContent = `${produto.nome} - ${produto.codigo}`;
                    item.addEventListener('click', () => {
                        inputProduto.value = produto.nome;
                        linha.querySelector('.produto_id').value = produto.id;
                        listaProduto.innerHTML = '';
                        listaProduto.style.display = 'none';
                    });
                    listaProduto.appendChild(item);
                });
            });

            // Esconde a lista ao clicar fora do input
            document.addEventListener('click', (event) => {
                if (!inputProduto.contains(event.target) && !listaProduto.contains(event.target)) {
                    listaProduto.style.display = 'none';
                }
            });

            // Esconde a lista ao sair do campo de input
            inputProduto.addEventListener('blur', () => {
                setTimeout(() => {
                    listaProduto.style.display = 'none';
                }, 100);  // Timeout para permitir o clique nos itens da lista
            });
        }

        // Adiciona eventos de autocomplete ao input de destinatário
        const inputDestino = document.getElementById('destino_pesquisa');
        const listaDestino = document.getElementById('destino_lista');

        inputDestino.addEventListener('input', () => {
            listaDestino.innerHTML = '';
            const valor = inputDestino.value.toLowerCase();
            if (valor === '') {
                listaDestino.style.display = 'none';
                return;
            }
            listaDestino.style.display = 'block';
            const destinosFiltrados = destino.filter(dest =>
                dest.nome.toLowerCase().startsWith(valor)
            );
            destinosFiltrados.forEach(dest => {
                const item = document.createElement('li');
                item.textContent = `${dest.nome}`;
                item.addEventListener('click', () => {
                    inputDestino.value = dest.nome;
                    document.getElementById('destino_id').value = dest.id;
                    listaDestino.innerHTML = '';
                    listaDestino.style.display = 'none';
                });
                listaDestino.appendChild(item);
            });
        });

        // Esconde a lista de destinatário ao clicar fora
        document.addEventListener('click', (event) => {
            if (!inputDestino.contains(event.target) && !listaDestino.contains(event.target)) {
                listaDestino.style.display = 'none';
            }
        });

        // Esconde a lista ao sair do campo de input
        inputDestino.addEventListener('blur', () => {
            setTimeout(() => {
                listaDestino.style.display = 'none';
            }, 100);  // Timeout para permitir o clique nos itens da lista
        });

        // Inicializa o autocomplete para a primeira linha de produtos
        adicionarEventosAutocompleteProduto(document.querySelector('.produto-linha'));
    });


</script>


<style>
    /* styles.css */

    /* Estilização geral da página */
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .container-saida {
        width: 45%;
        max-width: 70%;
        margin: 20px auto;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    h2 {
        color: #e91e63;
        margin-bottom: 5vh;
    }

    /* Estilização do formulário */
    .product-form {
        display: flex;
        flex-direction: column;
    }

    label {
        font-weight: bold;
        font-size: 0.75rem;
        color: #555;
        font-weight: 700;
        position: relative;
        top: 0.5rem;
        margin: 0 0 0 7px;
        padding: 0 3px;
        background: #fff;
        width: fit-content;
        
    }

    input[type="text"],
    input[type="number"] {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        position: static;
        margin-bottom: 4%;
    }

    input[type="text"]:focus,
    input[type="number"]:focus {
        border-color: #e91e63;
        outline: none;
    }

    

    .submit-btn {
        background-color: #e91e63;
        color: #fff;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top:5vh;
    }

    .submit-btn:hover {
        background-color: #d81b60;
    }

    .botao-adicionar-produto {
        background-color: #f5f5f5;
        color: #555;
        border: solid 1px #bbb;
        padding: 1%;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1vw;
        margin-top: 1vh;
        width: 10vw;
    }

    .produto-linha {
    position: relative; /* Garante que o botão de fechar seja posicionado relativamente a esta linha */
    justify-content: space-between;

    display: flex;
    flex-direction: row;
    margin-bottom: 10px;
    input {
            width: 18vw;
        }
}

.fechar-produto {
    position: absolute; 
    right: 0px; 
   bottom:60px;
    color: #e91e63;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1vw;
    font-weight: bold;
}

.fechar-produto:hover {
    background-color: #e91e63;
    color: #f5f5f5;
}



    .fechar-produto:hover {
        background-color: #d32f2f;
    }


    .botao-adicionar-produto:hover {
        background-color: #bbb;
    }

    /* Estilização das listas de autocomplete */
    .autocomplete-list-destino,
    .autocomplete-list-produto {
        position: absolute;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
        max-height: 200px;
        overflow-y: auto;
        list-style: none;
        padding: 0;
        margin: 0;
        z-index: 1000;
    }

    .autocomplete-list-destino {
        margin-top: 8vh;
    }


    .autocomplete-list-produto {
        margin-top: -1.8vh;
    }




    .autocomplete-list-destino li {
        padding: 10px;
        cursor: pointer;
    }

    .autocomplete-list-produto li {
        padding: 10px;
        cursor: pointer;
    }

    .autocomplete-list-destino li:hover {
        background-color: #f5f5f5;
    }

    .autocomplete-list-produto li:hover {
        background-color: #f5f5f5;
    }


    /* Estilização dos erros e mensagens de sucesso */
    .alert {
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* Responsividade */
    @media (max-width: 600px) {
        .container-saida {
            padding: 10px;
        }

        .submit-btn,
        .botao-adicionar-produto {
            font-size: 14px;
            padding: 8px 12px;
        }
    }
</style>