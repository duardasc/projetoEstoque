<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Obter o ID do usuário da sessão
$usuario_id = $_SESSION['user_id'];

// Inicializa variáveis de mensagem
$mensagem = '';

// Processamento do formulário de entrada de produtos
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter dados do formulário
    $produto_id = $_POST["produto_id"];
    $quantidade = $_POST["quantidade"];
    $data_entrada = date('Y-m-d'); // Pega a data atual
    $cliente_id = $usuario_id; // Usar o ID do usuário da sessão como cliente_id

    try {
        // Iniciar transação
        $conn->beginTransaction();

        // Inserir registro na tabela de entradas
        $sqlEntrada = "INSERT INTO entradas (produto_id, quantidade, data_entrada, cliente_id) VALUES (:produto_id, :quantidade, NOW(), :cliente_id)";
        $stmtEntrada = $conn->prepare($sqlEntrada);
        $stmtEntrada->bindParam(':produto_id', $produto_id);
        $stmtEntrada->bindParam(':quantidade', $quantidade);
        $stmtEntrada->bindParam(':cliente_id', $cliente_id); // Usa o ID do usuário da sessão
        $stmtEntrada->execute();

        // Atualizar a quantidade em estoque do produto
        $sqlUpdate = "UPDATE produtos SET quantidade = quantidade + :quantidade WHERE id = :produto_id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':quantidade', $quantidade);
        $stmtUpdate->bindParam(':produto_id', $produto_id);
        $stmtUpdate->execute();

        // Commit da transação
        $conn->commit();

        // Mensagem de sucesso
        $_SESSION['mensagem'] = "Entrada de produtos registrada com sucesso!";
        header('Location: ' . $_SERVER['PHP_SELF']); // Redireciona para a mesma página
        exit();
    } catch (PDOException $e) {
        // Rollback da transação em caso de erro
        $conn->rollback();
        $_SESSION['mensagem'] = "Erro ao registrar a entrada de produtos: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']); // Redireciona para a mesma página
        exit();
    }
}

// Lógica para buscar produtos via AJAX
if (isset($_GET['term'])) {
    $term = $_GET['term'] . '%'; // Usado para busca
    $sqlBusca = "SELECT id, nome, codigo FROM produtos WHERE nome LIKE :term OR codigo LIKE :term";
    $stmtBusca = $conn->prepare($sqlBusca);
    $stmtBusca->bindParam(':term', $term);
    $stmtBusca->execute();
    $resultados = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);
    
    $produtos = [];
    foreach ($resultados as $resultado) {
        $produtos[] = [
            'id' => $resultado['id'],
            'label' => $resultado['codigo'] . " " . $resultado['nome'],
            'value' => $resultado['id'] // O valor do campo será o id do produto
        ];
    }
    echo json_encode($produtos);
    exit();
}

// Verifica se há mensagem na sessão
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']); // Limpa a mensagem após exibir
}

// Consulta SQL para buscar clientes
$sqlClientes = "SELECT id, nome FROM clientes";
$stmtClientes = $conn->query($sqlClientes);
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Produtos</title>
    <link rel="stylesheet" href="estilos/estilos.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa; /* Cor de fundo leve */
            font-family: Arial, sans-serif; /* Fonte padrão */
            color: #333; /* Cor do texto */
        }
        
        .container-entrada {
            width: 45%;
            max-width: 70%;
            margin: 4% auto; /* Centraliza a caixa */
            padding: 2.5%; /* Espaçamento interno */
            background-color: #ffffff; /* Fundo branco */
            border-radius: 8px; /* Bordas arredondadas */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Sombra */
        }

        h2 {
            color: #d63384; /* Cor do título */
        }

        .success-message {
            background-color: #d4edda; /* Fundo da mensagem de sucesso */
            color: #155724; /* Cor do texto da mensagem */
            padding: 10px; /* Espaçamento interno */
            border-radius: 5px; /* Bordas arredondadas */
            margin-bottom: 15px; /* Margem inferior */
        }

        .product-form label {
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

        .product-form input[type="text"],
        .product-form input[type="number"] {
            width: 100%; /* Largura total */
            padding: 10px; /* Espaçamento interno */
            border: 1px solid #ced4da; /* Borda */
            border-radius: 5px; /* Bordas arredondadas */
            box-sizing: border-box; /* Inclui borda e padding na largura total */
            margin-bottom: 4%;
        }

        .submit-btn {
            background-color: #d63384; /* Cor do botão */
            color: #ffffff; /* Cor do texto do botão */
            padding: 10px; /* Espaçamento interno */
            border: none; /* Remove borda */
            border-radius: 5px; /* Bordas arredondadas */
            cursor: pointer; /* Muda o cursor para pointer */
            width: 100%; /* Largura total */
            font-size: 16px; /* Tamanho da fonte */
        }

        .submit-btn:hover {
            background-color: #c8235a; /* Cor ao passar o mouse */
        }

        .ui-autocomplete {
            max-height: 150px; /* Altura máxima da lista */
            overflow-y: auto;  /* Adiciona rolagem vertical */
            overflow-x: hidden; /* Oculta rolagem horizontal */
            z-index: 1000; /* Coloca a lista acima de outros elementos */
            
        }

  
    </style>
</head>

<body>
<?php include 'includes/painel_lateral.php'; ?>

<div class="container-entrada">
    <h2>Registrar Entrada de Produtos</h2>

    <?php if ($mensagem): ?>
        <p class="success-message"><?php echo $mensagem; ?></p>
    <?php endif; ?>

    <form method="post" class="product-form">
        <label for="produto_id">Produto:</label>
        <input type="text" id="produto_autocomplete" name="produto_autocomplete" placeholder="Digite o nome ou código do produto" required>
        <input type="hidden" id="produto_id" name="produto_id" required>

        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" min="1" required>

        <input type="hidden" name="data_entrada" value="<?php echo date('Y-m-d'); ?>"> <!-- Data gerada no backend -->

        <input type="hidden" name="cliente_id" value="<?php echo $usuario_id; ?>"> <!-- ID do usuário da sessão -->

        <input type="submit" value="Registrar Entrada" class="submit-btn">
    </form>
</div>

<script>
    $(function() {
        $("#produto_autocomplete").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "",
                    type: "GET",
                    data: { term: request.term },
                    dataType: "json",
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 1, // Número mínimo de caracteres para começar a busca
            select: function(event, ui) {
                // Quando o usuário selecionar um produto, preenchendo o campo oculto com o id
                $("#produto_id").val(ui.item.value);
                $("#produto_autocomplete").val(ui.item.label);
                return false;
            }
        });
    });
</script>

</body>
</html>
