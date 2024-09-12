<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Processamento do formulário de entrada de produtos
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter dados do formulário
    $produto_id = $_POST["produto_id"];
    $quantidade = $_POST["quantidade"];
    $data_entrada = $_POST["data_entrada"];
    $cliente_id = $_POST["cliente_id"]; // Adicionado cliente_id

    try {
        // Iniciar transação
        $conn->beginTransaction();

        // Inserir registro na tabela de entradas
        $sqlEntrada = "INSERT INTO entradas (produto_id, quantidade, data_entrada, cliente_id) VALUES (:produto_id, :quantidade, :data_entrada, :cliente_id)";
        $stmtEntrada = $conn->prepare($sqlEntrada);
        $stmtEntrada->bindParam(':produto_id', $produto_id);
        $stmtEntrada->bindParam(':quantidade', $quantidade);
        $stmtEntrada->bindParam(':data_entrada', $data_entrada);
        $stmtEntrada->bindParam(':cliente_id', $cliente_id); // Adicionado cliente_id
        $stmtEntrada->execute();

        // Atualizar a quantidade em estoque do produto
        $sqlUpdate = "UPDATE produtos SET quantidade = quantidade + :quantidade WHERE id = :produto_id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':quantidade', $quantidade);
        $stmtUpdate->bindParam(':produto_id', $produto_id);
        $stmtUpdate->execute();

        // Commit da transação
        $conn->commit();

        echo "<p class='success-message'>Entrada de produtos registrada com sucesso!</p>";
    } catch (PDOException $e) {
        // Rollback da transação em caso de erro
        $conn->rollback();
        echo "<p class='error-message'>Erro ao registrar a entrada de produtos: " . $e->getMessage() . "</p>";
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
        .ui-autocomplete {
            max-height: 150px; /* Define a altura máxima da lista */
            overflow-y: auto;  /* Adiciona a barra de rolagem vertical */
            overflow-x: hidden; /* Oculta a barra de rolagem horizontal */
        }
    </style>
</head>

<body>

<div class="container-entrada">
    <h2>Registrar Entrada de Produtos</h2>

    <form method="post" class="product-form">
        <label for="produto_id">Produto:</label>
        <input type="text" id="produto_autocomplete" name="produto_autocomplete" placeholder="Digite o nome ou código do produto" required>
        <input type="hidden" id="produto_id" name="produto_id" required>

        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" min="1" required>

        <label for="data_entrada">Data da Entrada:</label>
        <input type="date" id="data_entrada" name="data_entrada" value="<?php echo date('Y-m-d'); ?>" required>

        <label for="cliente_id">Usuário:</label>
        <select id="cliente_id" name="cliente_id" required>
            <option value="">Selecione um cliente</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?php echo $cliente['id']; ?>"><?php echo $cliente['nome']; ?></option>
            <?php endforeach; ?>
        </select>

        <input type="submit" value="Registrar Entrada" class="submit-btn">
    </form>
</div>

<?php include 'includes/footer.php'; ?>

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
            minLength: 1, // Número mínimo de caracteres para começar a busca (alterado para 1)
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
</body>
</html>
