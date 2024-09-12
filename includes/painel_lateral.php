<?php
include 'conexao.php';
// Restante do código PHP

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
    <title>Painel Lateral</title>
    <link rel="stylesheet" href="estilos/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Ícones FontAwesome -->
    <style>
        body {
            display: flex;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Evita rolagem horizontal */
        }

        /* Estilo para o botão de alternância */
        .toggle-btn {
            position: fixed;
            width: 2vw;
            top: 1vw;
            left: 5vw; /* Posição inicial fora do painel lateral */
            background-color: #ff4081; /* Cor rosa */
            color: white;
            border: none;
            padding: 1% 2%;
            cursor: pointer;
            border-radius: 5px;
            z-index: 1000; /* Sobrepor ao conteúdo */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s; /* Transição suave */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn:hover {
            background-color: #e91e63; /* Cor rosa escura */
        }

        .toggle-btn.move-right {
            transform: translateX(calc(13vw + 1vw)); /* Move o botão para a direita do painel */
        }

        /* Estilo para o painel lateral */
        .sidebar {
            width: 18vw;
            background-color: #f8f9fa;
            padding: 20px;
            height: 100vh;
            position: fixed;
            left: -22vw; /* Oculto inicialmente fora da tela */
            top: 0;
            transition: left 0.4s ease; /* Transição mais suave */
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.3); /* Sombra para dar destaque */
            z-index: 1000; /* Garantir que o painel fique abaixo do botão */
        }

        .sidebar.active {
            left: 0; /* Mostra o painel quando ativo */
        }

        .sidebar h2 {
            margin-bottom: 30px;
            color: #333;
            font-size: 24px;
            text-align: center;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            text-decoration: none;
            color: #555;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar a i {
            margin-right: 10px; /* Espaçamento entre ícone e texto */
        }

        .sidebar a:hover {
            background-color: #e0e0e0;
            color: #ff4081; /* Cor rosa */
        }

        .sidebar a.disabled {
            color: #bbb;
            cursor: not-allowed;
            pointer-events: none;
        }

        .content {
            flex: 1;
            padding: 5%;
            margin-left: 1vw; /* Margin para o botão de alternância */
            width: 100%;
        }

        /* Estilo para o botão de logout */
        .logout-btn {
            display: block;
            margin-top: 30px;
            padding: 12px;
            background-color: #ff4081;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

           /* Linha de separação */
           .sidebar .separator {
            border-top: 1px solid #c1c1c1; /* Linha cinza clara */
            margin: 20px 0; /* Espaçamento vertical */
            padding-top: 10px;
        }
    </style>
</head>
<body>

<!-- Botão de alternância (abrir/fechar) -->
<button class="toggle-btn" id="toggleButton" onclick="toggleSidebar()">
    <i class="fas fa-bars" id="toggleIcon"></i>
</button>

<!-- Painel Lateral -->
<div class="sidebar" id="sidebar">
    <h2>Menu</h2>
     <!-- Linha de separação -->
     <div class="separator"></div>

    <a href="painel.php"><i class="fas fa-home"></i>Painel</a>
    <a href="cadastro_produto.php" class="<?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-box"></i> Cadastrar Produto</a>
    <a href="cadastro_fornecedor.php" class="<?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-truck"></i> Cadastrar Fornecedor</a>
    <a href="cadastro_cliente.php" class="<?php echo $is_group_2 || $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-user-plus"></i> Cadastrar Cliente</a>
    <a href="entrada_produtos.php" class="<?php echo $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-sign-in-alt"></i> Registrar Entrada de Produtos</a>
    <a href="saida_produtos.php" class="<?php echo $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-sign-out-alt"></i> Registrar Saída de Produtos</a>
    <a href="relatorios.php" class="<?php echo $is_group_3 ? 'disabled' : ''; ?>"><i class="fas fa-chart-line"></i> Gerar Relatórios</a>
<a href="logout.php" class="logout-btn"><i class="fas fa-door-open"></i>Sair</a>
</div>

<!-- Script para alternar o painel lateral -->
<script>
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var toggleButton = document.getElementById('toggleButton');
        var toggleIcon = document.getElementById('toggleIcon');
        
        sidebar.classList.toggle('active');

        // Altera o ícone do botão de alternância entre "hambúrguer" e "X"
        if (sidebar.classList.contains('active')) {
            toggleIcon.classList.remove('fa-bars');
            toggleIcon.classList.add('fa-times');
            toggleButton.classList.add('move-right'); // Move o botão para o lado direito
        } else {
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-bars');
            toggleButton.classList.remove('move-right'); // Voltar o botão para o lado esquerdo
        }
    }
</script>

</body>
</html>