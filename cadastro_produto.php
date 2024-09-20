<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Inicialize uma variável para exibir mensagens
$message = "";

// Obter o ID do usuário da sessão
if (isset($_SESSION['user_id'])) {
    $usuario_id = $_SESSION['user_id'];
} else {
    $message = "<p class='error-messagen'>Erro: Usuário não encontrado na sessão.</p>";
}

// Obter a data e hora local
$data_hora = date('Y-m-d H:i:s');

// Verificação básica para evitar envio de formulário com valores inválidos ou incompletos
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter os valores do formulário
    $codigo = $_POST['codigo'];
    $nome = $_POST['nome'];
    $marca = $_POST['marca'];
    $preco = $_POST['preco'];
    $quantidade = $_POST['quantidade'];
    $tamanho = $_POST['tamanho'];
    $cor = $_POST['cor'];
    $fornecedor_id = $_POST['fornecedor_id'];
    $categoria = $_POST['categoria'];
    $minimo = $_POST['minimo'];

    if (empty($codigo) || $codigo == "0" || empty($nome) || empty($preco) || empty($quantidade) || empty($tamanho) || empty($cor) || empty($minimo) || empty($fornecedor_id) || empty($categoria)) {
        $message = "<p class='error-messagen'>Erro: Todos os campos obrigatórios devem ser preenchidos corretamente e o código do produto deve ser válido.</p>";
    } else {
        // Verificar se o código já existe
        $sqlVerificaCodigo = "SELECT COUNT(*) FROM produtos WHERE codigo = :codigo";
        $stmtVerifica = $conn->prepare($sqlVerificaCodigo);
        $stmtVerifica->bindParam(':codigo', $codigo);
        $stmtVerifica->execute();
        $codigoExiste = $stmtVerifica->fetchColumn();

        if ($codigoExiste > 0) {
            $message = "<p class='error-messagen'>Erro: O código de produto já existe. Por favor, use outro código.</p>";
        } else {
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
                $message = "<p class='success-messagen'>Produto cadastrado com sucesso!</p>";

                // Redirecionar após o sucesso para evitar reenvio de formulário
                header("Location: cadastro_produto.php?success=1");
                exit();
            } catch (PDOException $e) {
                $message = "<p class='error-messagen'>Erro ao cadastrar o produto: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// Exibir mensagem de sucesso se o redirecionamento trouxer o parâmetro
if (isset($_GET['success'])) {
    $message = "<p class='success-messagen'>Produto cadastrado com sucesso!</p>";
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
    <style>
        /* Estilos para a página */
    </style>
</head>

<body>
    <?php include 'includes/painel_lateral.php'; ?>

    <div class="container-prod">
        <div class="titulo-prod">
            <h2>Cadastrar Produto</h2>
        </div>

        <!-- Exibir mensagem -->
        <?php if ($message): ?>
            <div class="mensagem"><?php echo $message; ?></div>
        <?php endif; ?>

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

            <label for="minimo">Estoque mínimo (recomendação):</label>
            <input type="number" id="minimo" name="minimo" required>

            <label for="tamanho">Tamanho:</label>
            <input type="text" id="tamanho" name="tamanho" required>

            <label for="cor">Cor:</label>
            <input type="text" id="cor" name="cor" required>

            <label for="fornecedor_pesquisa">Fornecedor:</label>
            <input type="text" id="fornecedor_pesquisa" placeholder="Digite o nome do fornecedor" autocomplete="off" required>
            <input type="hidden" id="fornecedor_id" name="fornecedor_id" required>
            <div id="fornecedor_container">
                <ul id="fornecedor_lista" class="autocomplete-list"></ul>
            </div>

            <label for="categoria">Categoria:</label>
            <select id="categoria" name="categoria" required>
                <option value="" disabled selected>Selecione uma categoria</option>
                <option value="calca">Calça</option>
                <option value="blusa">Blusa</option>
                <option value="shorts">Shorts</option>
                <option value="camiseta">Camiseta</option>
            </select>

            <input type="submit" value="Cadastrar" class="submit-btn">
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fornecedores = <?php echo json_encode($fornecedores); ?>;
            const fornecedorPesquisa = document.getElementById('fornecedor_pesquisa');
            const fornecedorLista = document.getElementById('fornecedor_lista');
            const fornecedorIdInput = document.getElementById('fornecedor_id');

            fornecedorPesquisa.addEventListener('input', function () {
                const query = fornecedorPesquisa.value.toLowerCase();
                if (query === '') {
                    fornecedorLista.innerHTML = ''; // Limpa a lista quando o campo está vazio
                    fornecedorLista.style.display = 'none'; // Esconde a lista
                } else {
                    const filtro = fornecedores.filter(item => item.nome.toLowerCase().includes(query));
                    atualizarLista(fornecedorLista, filtro);
                    fornecedorLista.style.display = filtro.length ? 'block' : 'none'; // Mostra a lista se houver resultados
                }
            });

            function atualizarLista(listaElement, dados) {
                listaElement.innerHTML = '';
                if (dados.length === 0) {
                    listaElement.innerHTML = '<li>Nenhum fornecedor encontrado.</li>';
                } else {
                    dados.forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = item.nome;
                        li.addEventListener('click', function () {
                           
                            fornecedorPesquisa.value = item.nome;
                            fornecedorIdInput.value = item.id;
                            fornecedorLista.innerHTML = ''; // Limpa a lista ao selecionar
                            fornecedorLista.style.display = 'none'; // Esconde a lista
                        });
                        listaElement.appendChild(li);
                    });
                }
            }

            document.addEventListener('click', function (event) {
                if (event.target !== fornecedorPesquisa) {
                    fornecedorLista.innerHTML = '';
                    fornecedorLista.style.display = 'none'; // Esconde a lista ao clicar fora
                }
            });
        });
    </script>
</body>

</html>
