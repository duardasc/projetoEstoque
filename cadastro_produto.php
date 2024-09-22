<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


$erros = [];
$usuario_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter os valores do formulário
    $codigo = $_POST['codigo'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $marca = $_POST['marca'] ?? null;
    $preco = $_POST['preco'] ?? null;
    $quantidade = $_POST['quantidade'] ?? null;
    $tamanho = $_POST['tamanho'] ?? null;
    $cor = $_POST['cor'] ?? null;
    $fornecedor_id = $_POST['fornecedor_id'] ?? null;
    $categoria = $_POST['categoria'] ?? null;
    $minimo = $_POST['minimo'] ?? null;

    // Verificação de campos obrigatórios
    if (empty($codigo) || empty($nome) || empty($preco) || empty($quantidade) || empty($tamanho) || empty($cor) || empty($minimo) || empty($fornecedor_id) || empty($categoria)) {
        $erros[] = "Erro: Todos os campos obrigatórios devem ser preenchidos corretamente.";
    } else {
        // Verificar se o código já existe
        $sqlVerificaCodigo = "SELECT COUNT(*) FROM produtos WHERE codigo = :codigo";
        $stmtVerifica = $conn->prepare($sqlVerificaCodigo);
        $stmtVerifica->bindParam(':codigo', $codigo);
        $stmtVerifica->execute();
        $codigoExiste = $stmtVerifica->fetchColumn();

        if ($codigoExiste > 0) {
            $erros[] = "Erro: O código de produto já existe. Por favor, use outro código.";
        }
    }

    // Se houver erros, armazená-los na sessão e redirecionar
    if (!empty($erros)) {
        $_SESSION['erros'] = $erros;
        header('Location: cadastro_produto.php');
        exit();
    } 

    // Preparar e executar a consulta SQL para inserir o novo produto
    $sql = "INSERT INTO produtos (nome, codigo, marca, preco, quantidade, tamanho, cor, fornecedor_id, categoria, minimo, cliente_id, data_hora) 
            VALUES (:nome, :codigo, :marca, :preco, :quantidade, :tamanho, :cor, :fornecedor_id, :categoria, :minimo, :cliente_id, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':marca', $marca);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':quantidade', $quantidade);
    $stmt->bindParam(':tamanho', $tamanho);
    $stmt->bindParam(':cor', $cor);
    $stmt->bindParam(':fornecedor_id', $fornecedor_id);
    $stmt->bindParam(':categoria', $categoria);
    $stmt->bindParam(':minimo', $minimo);
    $stmt->bindParam(':cliente_id', $usuario_id);

    try {
        $stmt->execute();
        $_SESSION['success'] = "Produto cadastrado com sucesso!";
        header('Location: cadastro_produto.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['erros'] = ["Erro ao cadastrar o produto: " . $e->getMessage()];
        header('Location: cadastro_produto.php');
        exit();
          }


}


// Consulta SQL para buscar fornecedores
$sqlFornecedores = "SELECT id, nome FROM fornecedores";
$stmtFornecedores = $conn->query($sqlFornecedores);
$fornecedores = $stmtFornecedores->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Produto</title>
    <link rel="stylesheet" href="estilos/estilos.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <style>
        /* estilos/estilos.css */

/* Estilo geral do corpo */
body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
      

        .container-prod {
            width: 45%;
    margin: 4% auto;
    padding: 2.5%;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .titulo-prod h2 {
            color: #e91e63;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .mensagem {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 4px;
    max-height: 150px; /* ou outro valor conforme necessário */
    overflow-y: auto;  /* Permitir rolagem nas mensagens se necessário */
}


        .success-messagen {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
            padding: 1%;
            text-align: center;
        }

        .error-messagen {
            padding: 1%;
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
            text-align: center;
        }

        .produto-form {
            display: flex;
            flex-direction: column;
        }

        .produto-form label {
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        label {
        font-size: 0.9rem;
        color: #555;
        position: relative;
        top: 1rem;
        margin: 0 0 0 7px;
        padding: 0 3px;
        background: #fff;
        width: fit-content;
        
    }

        input[type="text"],select,
    input[type="number"] {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        position: static;
        margin-bottom: 2%;
    }

    input[type="text"]:focus,
    select:focus,
    input[type="number"]:focus {
        border-color: #e91e63;
        outline: none;
    }

        .submit-btn {
            padding: 0.75rem;
            background-color: #e91e63;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 0.75rem;
        }

        .submit-btn:hover {
            background-color: #d81b60;
        }


    </style>
</head>


<body>
    <?php include 'includes/painel_lateral.php'; ?>

    <div class="container-prod">
          <!-- Exibir mensagens -->
          <?php if (isset($_SESSION['erros'])): ?>
            <?php foreach ($_SESSION['erros'] as $erro): ?>
                <p class="error-messagen"><?php echo $erro; ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['erros']); ?>
        <?php endif; ?>
<!-- Exibir mensagem de sucesso -->
<?php if (isset($_SESSION['success'])): ?>
    <p class="success-messagen"><?php echo $_SESSION['success']; ?></p>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

        <div class="titulo-prod">
            <h2>Cadastrar Produto</h2>
        </div>

      

        <form method="post" class="produto-form">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="codigo">Código (6 dígitos):</label>
            <input type="text" id="codigo" name="codigo" required maxlength="6" pattern="^\d{6}$"
                title="O código deve conter exatamente 6 dígitos." oninput="validarCodigo(this)">

            <script>
                function validarCodigo(input) {
                    input.value = input.value.replace(/\D/g, '');
                    if (input.value.length > 6) {
                        input.value = input.value.slice(0, 6);
                    }
                }
            </script>

            <label for="marca">Marca:</label>
            <input type="text" id="marca" name="marca">

            <label for="preco">Preço:</label>
            <input type="number" id="preco" name="preco" step="0.01" required>

            <label for="quantidade">Quantidade:</label>
            <input type="number" id="quantidade" name="quantidade" required>

            <label for="minimo">Estoque mínimo (Recomendação):</label>
            <input type="number" id="minimo" name="minimo" required>

            <label for="tamanho">Tamanho:</label>
            <input type="text" id="tamanho" name="tamanho" required>

            <label for="cor">Cor:</label>
            <input type="text" id="cor" name="cor" required>

            <label for="fornecedor_pesquisa">Fornecedor:</label>
            <input type="text" id="fornecedor_pesquisa" placeholder="Digite o nome do fornecedor" autocomplete="off" required>
            <input type="hidden" id="fornecedor_id" name="fornecedor_id" required>

            <label for="categoria">Categoria:</label>
            <select id="categoria" name="categoria" required>
                <option value="" disabled selected>Selecione uma categoria</option>
                <option value="blusa">Blusa</option>
                <option value="calca">Calça</option>
                <option value="camiseta">Camiseta</option>
                <option value="casaco">Casaco</option>
                <option value="saia">Saia</option>
                <option value="sapato">Sapato</option>
                <option value="shorts">Shorts</option>
                <option value="vestido">Vestido</option>
            </select>

            <input type="submit" value="Cadastrar" class="submit-btn">
        </form>

         


    <script>
$(document).ready(function () {
    const fornecedores = <?php echo json_encode($fornecedores); ?>;

    $('#fornecedor_pesquisa').autocomplete({
        source: function (request, response) {
            const term = request.term.toLowerCase();
            const results = fornecedores.filter(f => f.nome.toLowerCase().startsWith(term));
            response(results.map(f => f.nome));
        },
        minLength: 1,
        select: function (event, ui) {
            const fornecedorSelecionado = fornecedores.find(f => f.nome === ui.item.value);
            $('#fornecedor_id').val(fornecedorSelecionado.id);
        }
    });
});
</script>

</body>

</html>
