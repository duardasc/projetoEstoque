<?php
include 'conexao.php';

session_start();
if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit();
}

// Processamento do formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Obter dados do formulário
  $nome = $_POST["nome"];
  $cpf_cnpj = $_POST["cpf_cnpj"];
  $inscricao_estadual = $_POST["inscricao_estadual"];
  $cep = $_POST["cep"];
  $estado = $_POST["estado"];
  $cidade = $_POST["cidade"];
  $bairro = $_POST["bairro"];
  $logradouro = $_POST["logradouro"];
  $numero = $_POST["numero"];
  $telefone = $_POST["telefone"];
  $email = $_POST["email"];

  // Preparar e executar a consulta SQL para inserir o novo destinatario
  $sql = "INSERT INTO destino (nome, cpf_cnpj, inscricao_estadual, cep, estado, cidade, bairro, logradouro, numero, telefone, email ) VALUES (:nome, :cpf_cnpj, :inscricao_estadual, :cep, :estado, :cidade, :bairro, :logradouro, :numero, :telefone, :email )";

  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':nome', $nome);
  $stmt->bindParam(':cpf_cnpj', $cpf_cnpj);
  $stmt->bindParam(':inscricao_estadual', $inscricao_estadual);
  $stmt->bindParam(':cep', $cep);
  $stmt->bindParam(':estado', $estado);
  $stmt->bindParam(':cidade', $cidade);
  $stmt->bindParam(':bairro', $bairro);
  $stmt->bindParam(':logradouro', $logradouro);
  $stmt->bindParam(':numero', $numero); 
  $stmt->bindParam(':telefone', $telefone);
  $stmt->bindParam(':email', $email);


  try {
    $stmt->execute();
    echo "<p class='success-message'>destinatario cadastrado com sucesso!</p>";
  } catch (PDOException $e) {
    echo "<p class='error-message'>Erro ao cadastrar o destinatario: " . $e->getMessage() . "</p>";
  }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Destinatário</title>
  <link rel="stylesheet" href="estilos/estilos.css">
</head>
<body>


<div class="container-destinatario">
  <div class="titulo-destinatario"><h2>Cadastrar Destinatário</h2></div>

  <form method="post" class="destinatario-formulario">
    <label for="nome">Razão Social:</label>
    <input type="text" id="nome" name="nome" required>

    <label for="cpf_cnpj">CPF / CNPJ:</label>
    <input type="text" id="cpf_cnpj" name="cpf_cnpj" required>

    <label for="inscricao_estadual">Inscrição Estadual:</label>
    <input type="number" id="inscricao_estadual" name="inscricao_estadual" required>

    <label for="cep">CEP:</label>
    <input type="number" id="cep" name="cep" required>

    <label for="estado">Estado:</label>
    <input type="text" id="estado" name="estado" required>

    <label for="cidade">Cidade:</label>
    <input type="text" id="cidade" name="cidade" required>

    <label for="bairro">Bairro:</label>
    <input type="text" id="bairro" name="bairro" required>

    <label for="logradouro">logradouro:</label>
    <input type="text" id="logradouro" name="logradouro" required>

    <label for="numero">Numero:</label>
    <input type="number" id="numero" name="numero" required>
    
    <label for="telefone">Telefone:</label>
    <input type="number" id="telefone" name="telefone" required>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required>

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