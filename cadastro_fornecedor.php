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
    header('Location: login.php');
    exit();
}

$grupo_id = $result['grupo_id'];

// Verifica permissão para gerenciar usuários
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
    echo "Você não tem permissão para acessar esta página.";
    exit();
}

// Processamento do formulário de cadastro
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter dados do formulário
    $nome = $_POST["nome"];
    $endereco = $_POST["endereco"];
    $telefone = $_POST["telefone"];
    $email = $_POST["email"];
    $cnpj = $_POST["cnpj"];

    // Verifica se o CNPJ já existe no banco de dados
    $sql = "SELECT COUNT(*) FROM fornecedores WHERE cnpj = :cnpj";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':cnpj', $cnpj);
    $stmt->execute();
    $cnpjExists = $stmt->fetchColumn();

    if ($cnpjExists) {
        $_SESSION['message'] = "<p class='error-message'>Erro: CNPJ já cadastrado!</p>";
    } else {
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
            $_SESSION['message'] = "<p class='success-message'>Fornecedor cadastrado com sucesso!</p>";
        } catch (PDOException $e) {
            $_SESSION['message'] = "<p class='error-message'>Erro ao cadastrar o fornecedor: " . $e->getMessage() . "</p>";
        }
    }

    // Redireciona para evitar reenvio do formulário
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Verifica e exibe a mensagem armazenada na sessão
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']); // Remove a mensagem após exibição
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Fornecedor</title>
  <link rel="stylesheet" href="estilos/estilos.css">
  <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .container-fornecedor {
        width: 45%;
        max-width: 70%;
        margin: 4% auto;
        padding: 2.5%;
        background: #fff;
        border-radius: 8px;
        height: auto;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .titulo-fornecedor {
        text-align: center;
        margin-bottom: 20px;
        color: #e91e63;
    }

  
    .fornecedor-formulario {
        display: flex;
        flex-direction: column;
    }

    label {
        font-size: 0.9rem;
        color: #555;
        font-weight: bold;
        margin-bottom: 5px;
    }

    input[type="text"], 
    input[type="email"], 
    input[type="number"] {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 15px;
        transition: border-color 0.3s;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="number"]:focus {
        border-color: #e91e63;
        outline: none;
    }

    .submit-btn {
        padding: 10px;
        background-color: #e91e63;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s;
    }

    .submit-btn:hover {
        background-color: #d81b60;
    }

    .success-message,
    .error-message {
        padding: 0.5%;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
    height: 5vh;
    min-height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    }

    .success-message {
        background-color: #28a745;
    }

    .error-message {
        background-color: #f8d7da;
        color: #721c24;
    }

    .notification {
        margin-bottom: 20px;
    }
  </style>
</head>
<body>

<?php include 'includes/painel_lateral.php'; ?>

<div class="container-fornecedor">
    
  <!-- Exibir mensagens -->
  <?php if ($message): ?>
    <div class="notification">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

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
        input.value = input.value.replace(/\D/g, '');
        if (input.value.length > 14) {
          input.value = input.value.slice(0, 14);
        }
      }
    </script>

    <input type="submit" value="Cadastrar" class="submit-btn">
  </form> 
</div>
</body>
</html>
