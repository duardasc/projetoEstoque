<?php
include 'conexao.php';

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Obtém o ID do usuário da sessão
$usuario_id = $_SESSION['user_id'];

// Consulta o banco de dados para obter o grupo do usuário logado
$sql = "SELECT grupo_id FROM clientes WHERE id = :usuario_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    // Redireciona para o login se o usuário não existir
    header('Location: login.php');
    exit();
}

$grupo_id = $result['grupo_id'];

// Consulta para verificar se o grupo do usuário tem permissão para "gerenciar" o recurso 'usuarios'
$sql = "SELECT pgr.grupo_id, p.nome AS permissao_nome, r.nome AS recurso_nome 
        FROM permissoes_por_grupo_e_recurso pgr
        JOIN permissoes p ON pgr.permissao_id = p.id_permissao
        JOIN recursos r ON pgr.recurso_id = r.id_recurso
        WHERE pgr.grupo_id = :grupo_id AND p.nome = 'gerenciar' AND r.nome = 'usuarios'";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':grupo_id', $grupo_id);
$stmt->execute();
$permissao = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$permissao) {
    // Se o grupo do usuário não tem permissão para gerenciar o recurso, redireciona ou exibe uma mensagem de erro
    echo "Você não tem permissão para acessar esta página.";
    exit();
}

// Processamento do formulário de cadastro (continua seu código abaixo)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter dados do formulário
    $nome = $_POST["nome"];
    $endereco = $_POST["endereco"];
    $telefone = $_POST["telefone"];
    $email = $_POST["email"];
    $cnpj = $_POST["cnpj"];

    // Preparar e executar a consulta SQL para inserir o novo fornecedor
    $sql = "INSERT INTO fornecedores (nome, endereco, telefone, email, cnpj) VALUES (:nome, :endereco, :telefone, :email, :cnpj)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':endereco', $endereco);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':cnpj', $cnpj);

    try {
        $stmt->execute();
        echo "<p class='success-message'>Fornecedor cadastrado com sucesso!</p>";
    } catch (PDOException $e) {
        echo "<p class='error-message'>Erro ao cadastrar o fornecedor: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Fornecedor</title>
  <link rel="stylesheet" href="estilos/estilos.css">
</head>
<body>

<?php include 'includes/painel_lateral.php'; ?>

<div class="container-fornecedor">
  <div class="titulo-fornecedor"><h2>Cadastrar Fornecedor</h2></div>

  <form method="post" class="fornecedor-formulario">
    <label for="nome">Razão Social:</label>
    <input type="text" id="nome" name="nome" required>

    <label for="endereco">Endereço:</label>
    <input type="text" id="endereco" name="endereco" required>

    <label for="telefone">Telefone:</label>
    <input type="text" id="telefone" name="telefone" required>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required>

    <label for="cnpj">CNPJ:</label>
            <input type="text" id="cnpj" name="cnpj" required maxlength="14" pattern="^\d{14}$"
                title="O CNPJ deve conter exatamente 14 dígitos." oninput="validarCNPJ(this)">

            <script>
                function validarCNPJ(input) {
                    // Remove caracteres não numéricos
                    input.value = input.value.replace(/\D/g, '');

                    // Limita a entrada a 6 dígitos
                    if (input.value.length > 14) {
                        input.value = input.value.slice(0, 14);
                    }
                }
            </script>

    <input type="submit" value="Cadastrar" class="submit-btn">
  </form> 
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const notification = document.querySelector('.success-message, .error-message');

        if (notification) {
            notification.style.opacity = 1; // Mostrar a mensagem

            setTimeout(() => {
                notification.style.opacity = 0; // Ocultar a mensagem após o tempo definido
            }, 2000); 
        }
    });
</script>
</body>
</html>
