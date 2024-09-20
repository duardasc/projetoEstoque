<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$erros = [];
$usuario_id = $_SESSION['user_id'];

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

$sqlProdutos = "SELECT id, nome, codigo FROM produtos WHERE ativo = 1";
$stmtProdutos = $conn->query($sqlProdutos);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

$sqlDestino = "SELECT id, nome FROM destino"; // Ajuste aqui para trazer apenas os campos necessários
$stmtDestino = $conn->query($sqlDestino);
$destino = $stmtDestino->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/painel_lateral.php'; ?>

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

<div class="container-saida">
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

        <?php
        if (isset($_SESSION['erros'])) {
            $erros = $_SESSION['erros'];
            unset($_SESSION['erros']);
            echo '<div class="alert alert-danger"><ul>';
            foreach ($erros as $erro) {
                echo '<li>' . $erro . '</li>';
            }
            echo '</ul></div>';
        }

        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        ?>
    </form>
</div>

<script>
    $(document).ready(function() {
        const produtos = <?php echo json_encode($produtos); ?>;
        const destinos = <?php echo json_encode($destino); ?>;

        // Autocomplete para produtos
        function setupAutocompleteProduto(inputProduto) {
            $(inputProduto).autocomplete({
                source: produtos.map(produto => ({
                    label: `${produto.nome} - ${produto.codigo}`,
                    value: produto.nome,
                    id: produto.id
                })),
                select: function(event, ui) {
                    $(this).siblings('.produto_id').val(ui.item.id);
                }
            });
        }

        // Autocomplete para destinatários
        $("#destino_pesquisa").autocomplete({
            source: destinos.map(dest => ({
                label: dest.nome,
                value: dest.nome,
                id: dest.id
            })),
            select: function(event, ui) {
                $("#destino_id").val(ui.item.id);
            }
        });

        // Configura o autocomplete para a primeira linha de produtos
        setupAutocompleteProduto($('.produto_pesquisa')[0]);

        window.adicionarProduto = function () {
            const novaLinha = $('.produto-linha:first').clone(); // Clona a primeira linha
            novaLinha.find('input').val(''); // Limpa os campos
            novaLinha.find('.produto_id').val(''); // Limpa o ID do produto
            novaLinha.find('.fechar-produto').show(); // Mostra o botão de fechar
            $('#produtos-container').append(novaLinha); // Adiciona a nova linha ao contêiner
            setupAutocompleteProduto(novaLinha.find('.produto_pesquisa')); // Configura o autocomplete para a nova linha
        };

        window.removerProduto = function (botao) {
            $(botao).closest('.produto-linha').remove(); // Remove a linha do produto
        };
    });
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
        background-color: pink;
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


</body>
</html>