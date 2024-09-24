<?php

include 'conexao.php'; // Inclua o arquivo de conexão com o banco de dados

session_start();


if (isset($_SESSION['usuario'])) {
    header('Location: painel.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Prepare a consulta para buscar o nome e o hash da senha do usuário com base no email fornecido
    $sql = "SELECT id, nome, senha FROM clientes WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Senha correta, armazene o nome do usuário na sessão
        $_SESSION['usuario'] = $usuario['nome'];
        $_SESSION['email'] = $email; // Você ainda pode querer armazenar o email para outras verificações
        $_SESSION['user_id'] = $usuario['id']; // Armazenar o ID do cliente

        header('Location: painel.php');
        exit();
    } else {
        // Armazenar a mensagem de erro na sessão
        $_SESSION['error_message'] = "Senha incorreta!";
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>

<html>
<head>
    <title>Login</title>
   
    <link rel="stylesheet" href="estilos/estilos.css">
</head>
<body>
    <div class="caixa_principal">
        <div class="container_login">
            <div class="form_area">
                <p class="title">LOGIN </p>
                <form action="login.php" method="post">
                    <div class="form_group">
                        <label class="sub_title" for="email">Email</label>
                        <input placeholder="Enter your email" id="email" class="form_style" type="email" name="email">
                    </div>
                    <div class="form_group">
                        <label class="sub_title" for="senha">Password</label>
                        <input placeholder="Enter your password" id="senha" class="form_style" type="password" name="senha">

<i class="fa-solid fa-house"></i>
                    </div>
                    <div>
                        <button class="btn" type="submit">Entrar</button>
                        <p>Não tem uma conta? <a class="link" href="cadastro_cliente.php">Cadastre aqui!</a></p>
                    </div>
                    
                    <i class="fa-solid fa-house"></i>
                </form>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <p class="error-message"><?php echo $_SESSION['error_message']; ?></p>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
<script>
    // Função para ocultar a mensagem de erro após 5 segundos (5000 milissegundos)
    window.onload = function() {
        var errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(function(errorMessage) {
            setTimeout(function() {
                errorMessage.style.opacity = '0';
            }, 1000); // Tempo em milissegundos
        });
    }
</script>
<style>
    .error-message {
        position: absolute;
        top: 90%;
        left: 50%;
        transform: translateX(-50%);
        padding: 15px;
        color: #dc3545;
        font-weight: bold;
        z-index: 1000; /* Para garantir que fique sobre outros elementos */
        opacity: 1; /* Certifique-se de que a opacidade esteja em 1 para visibilidade */
        transition: opacity 1.5s ease; /* Transição suave para ocultar a mensagem */
    }
</style>
</html>
