<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'conexao.php';

// Obtém o ID do usuário da sessão
$usuario_id = $_SESSION['user_id'];


// Consulta o banco de dados para obter o grupo do usuário logado
$sql = "SELECT grupo_id, nome FROM clientes WHERE id = :usuario_id";
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
$usuario_nome = $result['nome']; // Obtém o nome do usuário

// Verifica se o grupo do usuário é o grupo 2 (Gerente) ou grupo 3 (Vendedor)
$is_group_2 = ($grupo_id == 2);
$is_group_3 = ($grupo_id == 3);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Painel de Controle</title>
    <link rel="stylesheet" href="estilos/estilos.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            display:flex;
            justify-content:center;
            align-items:center;
        }

        .segundo_container {
            background-color: #ffffff;
            margin: 10vh;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 3%;
        }

        .identidade {
            font-size: 2vw;
            color: #ff4081;
            text-align: center;
        }

        .container_painel {
            padding: 3%;
            display: flex;
            flex-wrap: wrap;
            gap: 2vw;
            justify-content: center;
        }

        .painel_item {
            background-color: #ff4081;
            position: relative;
            color: #fff;
            font-size: 1.2vw;
            height: 13vh; /* Ajusta a altura do item */
            width: 10vw; /* Ajusta a largura do item */
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden; /* Garante que nada saia da borda da div */
            text-align: center;
            flex: 1 1 calc(33.333% - 10px); /* Ajusta o tamanho para três colunas */
            max-width: calc(33.333% - 10px);
        }

        .painel_item a {
            text-decoration: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .painel_item:hover {
            background-color: #e91e63;
        }

        .painel_item.disabled {
            background-color: #cb9bac;
            cursor: not-allowed;
            pointer-events: none;
        }

        .painel_item.disabled a {
            color: #fff;
        }
    </style>
</head>
<body>
<?php include 'includes/painel_lateral.php'; ?>


    <div class="segundo_container">
        <h1 class="identidade">Bem-vindo, <?php echo htmlspecialchars($usuario_nome); ?>!</h1>
        <div class="container_painel">
            <div class="painel_item <?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>">
                <a href="cadastro_produto.php">Cadastrar Produto</a>
            </div>
            <div class="painel_item <?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>">
                <a href="cadastro_fornecedor.php">Cadastrar Fornecedor</a>
            </div>
            <div class="painel_item <?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>">
                <a href="cadastro_cliente.php">Cadastrar Cliente</a>
            </div>
            <div class="painel_item <?php echo $is_group_3 ? 'disabled' : ''; ?>">
                <a href="entrada_produtos.php">Registrar Entrada de Produtos</a>
            </div>
            <div class="painel_item <?php echo $is_group_3 ? 'disabled' : ''; ?>">
                <a href="saida_produtos.php">Registrar Saída de Produtos</a>
            </div>
            <div class="painel_item <?php echo $is_group_3 ? 'disabled' : ''; ?>">
                <a href="relatorios.php">Gerar Relatórios</a>
            </div>
        </div>
    </div>
</body>
</html>
