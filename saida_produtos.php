<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$erros = [];
$usuario_id = $_SESSION['user_id'];

// Tratamento da pesquisa AJAX para produtos
if (isset($_GET['query'])) {
    $query = $_GET['query'];
    $sql = "SELECT id, nome, codigo FROM produtos WHERE ativo = 1 AND (nome LIKE :query OR codigo LIKE :query)";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':query', $query . '%');
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($produtos);
    exit();
}

// Tratamento da pesquisa AJAX para destinatários
if (isset($_GET['destino_query'])) {
    $query = $_GET['destino_query'];
    $sql = "SELECT id, nome FROM destino WHERE nome LIKE :query";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':query', $query . '%');
    $stmt->execute();
    $destinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($destinos);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $destino_id = $_POST["destino_id"] ?? null;
    $produtos = $_POST["produto_id"] ?? [];
    $quantidades = $_POST["quantidade"] ?? [];

    if ($destino_id && !empty($produtos)) {
        try {
            $conn->beginTransaction();

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

            if (!empty($erros)) {
                $_SESSION['erros'] = $erros;
                header('Location: saida_produtos.php');
                exit();
            }

            $sqlSaida = "INSERT INTO saidas (data_saida, cliente_id, destino_id) VALUES (NOW(), :cliente_id, :destino_id)";
            $stmtSaida = $conn->prepare($sqlSaida);
            $stmtSaida->bindParam(':cliente_id', $usuario_id);
            $stmtSaida->bindParam(':destino_id', $destino_id);

            if (!$stmtSaida->execute()) {
                throw new Exception("Erro ao inserir na tabela 'saidas'.");
            }

            $saida_id = $conn->lastInsertId();

            $sqlItem = "INSERT INTO itens_saida (saida_id, produto_id, quantidade) VALUES (:saida_id, :produto_id, :quantidade)";
            foreach ($produtos as $index => $produto_id) {
                $quantidade = $quantidades[$index];

                $stmtItem = $conn->prepare($sqlItem);
                $stmtItem->bindParam(':saida_id', $saida_id);
                $stmtItem->bindParam(':produto_id', $produto_id);
                $stmtItem->bindParam(':quantidade', $quantidade);

                if (!$stmtItem->execute()) {
                    throw new Exception("Erro ao inserir na tabela 'itens_saida'.");
                }

                $sqlUpdate = "UPDATE produtos SET quantidade = quantidade - :quantidade WHERE id = :produto_id";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':quantidade', $quantidade);
                $stmtUpdate->bindParam(':produto_id', $produto_id);

                if (!$stmtUpdate->execute()) {
                    throw new Exception("Erro ao atualizar o estoque para o produto com o código: " . $produto['codigo']);
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Saída de produtos registrada com sucesso!";
            header('Location: saida_produtos.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['erros'] = ["Erro ao registrar a saída de produtos: " . $e->getMessage()];
            header('Location: saida_produtos.php');
            exit();
        }
    } else {
        $_SESSION['erros'] = ["Erro: Por favor, preencha todos os campos obrigatórios."];
        header('Location: saida_produtos.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saída de Produtos</title>
    <link rel="stylesheet" href="estilos/estilos.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>

<body>
<?php include 'includes/painel_lateral.php'; ?>
<div class="container-saida">
    <div class="mensagens">
        <?php
        if (isset($_SESSION['erros'])) {
            $erros = $_SESSION['erros'];
            unset($_SESSION['erros']);
            echo '<div class="alert alert-danger">';
            foreach ($erros as $erro) {
                echo $erro;
            }
            echo '</div>'; // Fecha a div de alert
        }

        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        ?>
    </div>
    <h2>Registrar Saída de Produtos</h2>

    <form method="post" class="product-form">
        <label for="destino_pesquisa">Destinatário:</label>
        <input type="text" id="destino_pesquisa" placeholder="Digite o destinatário" autocomplete="off" required>
        <input type="hidden" id="destino_id" name="destino_id" required>
        <ul id="destino_lista" class="autocomplete-list-destino"></ul>

        <div id="produtos-container">
            <div class="produto-linha">
                <button type="button" class="fechar-produto" style="display: none;" onclick="removerProduto(this)">x</button>
                <div class="produto-nome-container">
                    <label for="produto_pesquisa_1">Produto:</label>
                    <input type="text" class="produto_pesquisa" placeholder="Digite o código ou nome" autocomplete="off" required>
                    <input type="hidden" name="produto_id[]" class="produto_id">
                </div>
                <div class="produto-quantidade-container">
                    <label for="quantidade_1">Quantidade:</label>
                    <input type="number" name="quantidade[]" class="quantidade" min="1" required>
                    <div id="error-messages"></div>
                </div>
            </div>
        </div>

        <button type="button" class="botao-adicionar-produto" onclick="adicionarProduto()">Adicionar Produto</button>
        <input type="submit" value="Registrar Saída" class="submit-btn">
    </form>
</div>

<script>
$(document).ready(function() {
    // Autocomplete para destinatários com pesquisa AJAX
    $("#destino_pesquisa").on('keyup', function() {
        const query = $(this).val();

        $.ajax({
            url: '', // URL do mesmo arquivo
            data: { destino_query: query },
            dataType: 'json',
            success: function(data) {
                const listaDestinos = data.map(dest => ({
                    label: dest.nome,
                    value: dest.nome,
                    id: dest.id
                }));

                $("#destino_pesquisa").autocomplete({
                    source: listaDestinos,
                    minLength: 1,
                    select: function(event, ui) {
                        $("#destino_id").val(ui.item.id);
                    }
                });
            }
        });
    });

    // Função para adicionar novos produtos
    window.adicionarProduto = function() {
        const novaLinha = $('.produto-linha:first').clone();
        novaLinha.find('input').val('');
        novaLinha.find('.produto_id').val('');
        novaLinha.find('.fechar-produto').show();
        $('#produtos-container').append(novaLinha);
        inicializarAutocompleteProduto(novaLinha.find('.produto_pesquisa'));
    };

    // Função para inicializar autocomplete para produtos
    function inicializarAutocompleteProduto(input) {
        input.on('keyup', function() {
            const query = $(this).val();

            $.ajax({
                url: '', // URL do mesmo arquivo
                data: { query: query },
                dataType: 'json',
                success: function(data) {
                    input.autocomplete({
                        source: data.map(produto => ({
                            label: `${produto.nome} - ${produto.codigo}`,
                            value: produto.nome,
                            id: produto.id
                        })),
                        minLength: 1,
                        select: function(event, ui) {
                            input.next('.produto_id').val(ui.item.id);
                        }
                    });
                }
            });
        });
    }

    // Inicializa autocomplete para o primeiro produto
    inicializarAutocompleteProduto($('.produto_pesquisa:first'));

});

// Função para remover um produto
function removerProduto(button) {
    $(button).closest('.produto-linha').remove();
}
</script>

</body>
</html>

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
        margin: 4% auto;
        padding: 2.5%;
        background: #fff;
        border-radius: 8px;
        height: auto;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    h2 {
        color: #e91e63;
        margin-bottom: 5vh;
        text-align: center;
    }

    /* Estilização do formulário */
    .product-form {
        display: flex;
        flex-direction: column;
    }

    label {
        font-size: 0.9rem;
        color: #555;
        font-weight: 700;
        position: relative;
        top: 0.5rem;
        margin: 0 0 0 7px;
        padding: 0 3px;
        background: #fff;
        width: fit-content;
        
    }

    

    .submit-btn {
        background-color: #e91e63;
        color: #fff;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top:1.5rem;
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
            width: 19vw;
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
    display: block; /* Força a lista a aparecer */
}

   

   .alert {
    padding: 0.5%;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
    height: 5vh; /* ou um valor fixo, como 30px */
    min-height: 30px; /* Adiciona uma altura mínima */
    display: flex; /* Para centralizar texto verticalmente */
    align-items: center; /* Centraliza verticalmente */
    justify-content: center; /* Centraliza horizontalmente */
}


    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
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


</body>
</html>