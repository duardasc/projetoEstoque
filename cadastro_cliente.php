
<?php
include 'conexao.php'; // Inclua o arquivo de conexão com o banco de dados

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


// Função para verificar a força da senha
function isStrongPassword($password)
{
  return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/', $password);
}

// Carregar grupos do banco de dados
$sqlGrupos = "SELECT id_grupo, nome FROM grupos";
$stmtGrupos = $conn->prepare($sqlGrupos);
$stmtGrupos->execute();
$grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

// Processamento do formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $nome = $_POST["nome"];
  $cpf = $_POST["cpf"];
  $telefone = $_POST["telefone"];
  $email = $_POST["email"];
  $endereco = $_POST["endereco"];
  $senha = $_POST["senha"];
  $confirmar_senha = $_POST["confirmar_senha"];
  $grupoSelecionado = $_POST["grupo_id"];

  if ($senha !== $confirmar_senha) {
    echo "<p class='error-message'>As senhas não coincidem.</p>";
  } elseif (!isStrongPassword($senha)) {
    echo "<p class='error-message'>A senha deve ter pelo menos 8 caracteres, uma letra maiúscula, uma letra minúscula e um número.</p>";
  } else {
    // Crie o hash da senha
    $hashedPassword = password_hash($senha, PASSWORD_BCRYPT);

    try {
      // Iniciar transação
      $conn->beginTransaction();

      // Prepare a consulta SQL para inserir o novo cliente
      $sql = "INSERT INTO clientes (nome, cpf, telefone, email, endereco, senha, grupo_id) 
              VALUES (:nome, :cpf, :telefone, :email, :endereco, :senha, :grupo_id)";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':nome', $nome);
      $stmt->bindParam(':cpf', $cpf);
      $stmt->bindParam(':telefone', $telefone);
      $stmt->bindParam(':email', $email);
      $stmt->bindParam(':endereco', $endereco);
      $stmt->bindParam(':senha', $hashedPassword);
      $stmt->bindParam(':grupo_id', $grupoSelecionado);
      $stmt->execute();

      // Confirmar transação
      $conn->commit();
      echo "<p class='success-message'>Usuário cadastrado com sucesso!</p>";
    } catch (PDOException $e) {
      // Reverter transação em caso de erro
      $conn->rollBack();
      echo "<p class='error-message'>Erro ao cadastrar o usuário: " . $e->getMessage() . "</p>";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Usuário</title>
  <link rel="stylesheet" href="estilos/estilos.css">
  <script>
    function validatePassword() {
      const senha = document.getElementById('senha').value;
      const confirmarSenha = document.getElementById('confirmar_senha').value;
      const message = document.getElementById('password-message');

      if (senha !== confirmarSenha) {
        message.textContent = "As senhas não coincidem.";
        return false;
      } else {
        message.textContent = "";
        return true;
      }
    }

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
</head>

<body>

<?php include 'includes/painel_lateral.php'; ?>

  <div class="container-cli">
    <h2>Cadastrar Usuário</h2>
    <form method="post" onsubmit="return validatePassword();">
      <label for="nome">Nome:</label>
      <input type="text" id="nome" name="nome" required>

      <label for="cpf">CPF:</label>
      <input type="text" id="cpf" name="cpf" required>

      <label for="telefone">Telefone:</label>
      <input type="text" id="telefone" name="telefone" required>

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>

      <label for="senha">Senha:</label>
      <input type="password" id="senha" name="senha" required oninput="validatePassword()">

      <label for="confirmar_senha">Confirmar Senha:</label>
      <input type="password" id="confirmar_senha" name="confirmar_senha" required oninput="validatePassword()">
      <span id="password-message"></span>

      <label for="endereco">Endereço:</label>
      <input type="text" id="endereco" name="endereco" required>

      <!-- Seleção de Grupo -->
      <label for="grupo_id">Grupo:</label>
      <select id="grupo_id" name="grupo_id" required>
        <?php foreach ($grupos as $grupo) : ?>
          <option value="<?= $grupo['id_grupo'] ?>"><?= $grupo['nome'] ?></option>
        <?php endforeach; ?>
      </select>

      <input type="submit" value="Cadastrar" class="submit-btn">
    </form>
  </div>


</body>

</html>
