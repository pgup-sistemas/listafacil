// ==========================================
// ESTRUTURA DO BANCO DE DADOS (MySQL)
// ==========================================

CREATE DATABASE IF NOT EXISTS sistema_doacoes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_doacoes;

-- Tabela de usuários administradores
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'usuario') DEFAULT 'usuario',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela de campanhas
CREATE TABLE campanhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    tipo ENUM('dinheiro', 'itens', 'misto') NOT NULL,
    meta_valor DECIMAL(10,2) NULL,
    meta_itens INT NULL,
    status ENUM('ativa', 'concluida', 'cancelada') DEFAULT 'ativa',
    data_inicio DATE NOT NULL,
    data_fim DATE,
    token VARCHAR(50) UNIQUE NOT NULL,
    criado_por INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Tabela de doadores
CREATE TABLE doadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela de doações
CREATE TABLE doacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campanha_id INT NOT NULL,
    doador_id INT NOT NULL,
    tipo ENUM('dinheiro', 'item') NOT NULL,
    valor DECIMAL(10,2) NULL,
    item_descricao VARCHAR(200) NULL,
    quantidade INT DEFAULT 1,
    status ENUM('prometido', 'pago', 'entregue', 'cancelado') DEFAULT 'prometido',
    comprovante VARCHAR(255) NULL,
    observacao TEXT,
    data_promessa TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_confirmacao TIMESTAMP NULL,
    FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (doador_id) REFERENCES doadores(id)
) ENGINE=InnoDB;

-- Inserir usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'admin@igreja.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');


// ==========================================
// config.php - Configurações do Sistema
// ==========================================
<?php
session_start();

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_doacoes');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações gerais
define('SITE_URL', 'http://localhost/doacoes');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Conexão com banco de dados
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Funções auxiliares
function gerar_token($tamanho = 32) {
    return bin2hex(random_bytes($tamanho / 2));
}

function verificar_login() {
    return isset($_SESSION['usuario_id']);
}

function verificar_admin() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

function redirecionar($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

function formatar_moeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatar_data($data) {
    return date('d/m/Y', strtotime($data));
}

function formatar_data_hora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function sanitizar($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
?>


// ==========================================
// index.php - Página Principal
// ==========================================
<?php 
require_once 'config.php';

if (verificar_login()) {
    redirecionar('/dashboard.php');
}

$stmt = $pdo->query("SELECT * FROM campanhas WHERE status = 'ativa' ORDER BY criado_em DESC");
$campanhas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Doações - Igreja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .hero-section { padding: 60px 0; color: white; text-align: center; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .card:hover { transform: translateY(-5px); }
        .btn-primary { background: var(--primary-color); border: none; border-radius: 25px; padding: 12px 30px; }
        .btn-success { background: var(--success-color); border: none; border-radius: 25px; padding: 12px 30px; }
        .progress { height: 25px; border-radius: 15px; }
        .badge { font-size: 0.9em; padding: 8px 15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-transparent">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-heart-fill"></i> Sistema de Doações
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Entrar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Faça sua Doação com Facilidade</h1>
            <p class="lead mb-5">Colabore com nossa comunidade de forma simples, rápida e transparente</p>
        </div>
    </div>

    <div class="container pb-5">
        <h2 class="text-white text-center mb-5">Campanhas Ativas</h2>
        
        <?php if (empty($campanhas)): ?>
            <div class="alert alert-light text-center">
                <i class="bi bi-info-circle"></i> Nenhuma campanha ativa no momento
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($campanhas as $campanha): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title fw-bold"><?= sanitizar($campanha['titulo']) ?></h5>
                                    <span class="badge bg-success">Ativa</span>
                                </div>
                                
                                <p class="card-text text-muted"><?= sanitizar($campanha['descricao']) ?></p>
                                
                                <?php if ($campanha['tipo'] === 'dinheiro' || $campanha['tipo'] === 'misto'): ?>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM doacoes WHERE campanha_id = ? AND tipo = 'dinheiro' AND status IN ('pago', 'entregue')");
                                    $stmt->execute([$campanha['id']]);
                                    $total_arrecadado = $stmt->fetch()['total'];
                                    $percentual = $campanha['meta_valor'] > 0 ? ($total_arrecadado / $campanha['meta_valor']) * 100 : 0;
                                    ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Arrecadado</small>
                                            <small class="fw-bold"><?= formatar_moeda($total_arrecadado) ?> / <?= formatar_moeda($campanha['meta_valor']) ?></small>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?= min($percentual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2">
                                    <a href="campanha.php?token=<?= $campanha['token'] ?>" class="btn btn-primary flex-fill">
                                        <i class="bi bi-hand-thumbs-up"></i> Doar Agora
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="compartilhar('<?= $campanha['token'] ?>')">
                                        <i class="bi bi-share"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($campanha['data_fim']): ?>
                                <div class="card-footer bg-light text-muted text-center">
                                    <small><i class="bi bi-calendar-event"></i> Encerra em <?= formatar_data($campanha['data_fim']) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function compartilhar(token) {
            const url = `${window.location.origin}/doacoes/campanha.php?token=${token}`;
            if (navigator.share) {
                navigator.share({
                    title: 'Campanha de Doação',
                    text: 'Participe desta campanha!',
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url);
                alert('Link copiado! Cole no WhatsApp.');
            }
        }
    </script>
</body>
</html>


// ==========================================
// login.php - Página de Login Admin
// ==========================================
<?php
require_once 'config.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        redirecionar('/dashboard.php');
    } else {
        $erro = 'Email ou senha inválidos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Doações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { border: none; border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 fw-bold">Área Administrativa</h3>
                            <p class="text-muted">Entre com suas credenciais</p>
                        </div>
                        
                        <?php if ($erro): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= $erro ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="senha" class="form-control" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Voltar para início
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


// ==========================================
// dashboard.php - Painel Administrativo
// ==========================================
<?php
require_once 'config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

// Estatísticas gerais
$stats = [
    'campanhas_ativas' => $pdo->query("SELECT COUNT(*) FROM campanhas WHERE status = 'ativa'")->fetchColumn(),
    'total_doacoes' => $pdo->query("SELECT COUNT(*) FROM doacoes")->fetchColumn(),
    'total_arrecadado' => $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM doacoes WHERE tipo = 'dinheiro' AND status IN ('pago', 'entregue')")->fetchColumn(),
    'doadores_unicos' => $pdo->query("SELECT COUNT(DISTINCT doador_id) FROM doacoes")->fetchColumn()
];

// Campanhas recentes
$campanhas = $pdo->query("SELECT * FROM campanhas ORDER BY criado_em DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Doações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 5px 10px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.2); color: white; }
        .stat-card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table-card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block">
                <div class="p-4 text-white">
                    <h4 class="fw-bold"><i class="bi bi-heart-fill"></i> Doações</h4>
                    <p class="small mb-0">Olá, <?= sanitizar($_SESSION['usuario_nome']) ?></p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="nova_campanha.php">
                        <i class="bi bi-plus-circle"></i> Nova Campanha
                    </a>
                    <a class="nav-link" href="gerenciar_campanhas.php">
                        <i class="bi bi-list-ul"></i> Gerenciar Campanhas
                    </a>
                    <a class="nav-link" href="relatorios.php">
                        <i class="bi bi-file-earmark-bar-graph"></i> Relatórios
                    </a>
                    <hr class="text-white mx-3">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </a>
                </nav>
            </div>
            
            <!-- Content -->
            <div class="col-md-9 col-lg-10 px-4 py-4">
                <!-- Header Mobile -->
                <div class="d-md-none mb-4">
                    <h4 class="fw-bold">Dashboard</h4>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-0 opacity-75">Campanhas Ativas</h6>
                                        <h2 class="fw-bold mb-0 mt-2"><?= $stats['campanhas_ativas'] ?></h2>
                                    </div>
                                    <i class="bi bi-flag-fill" style="font-size: 2rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-0 opacity-75">Total Doações</h6>
                                        <h2 class="fw-bold mb-0 mt-2"><?= $stats['total_doacoes'] ?></h2>
                                    </div>
                                    <i class="bi bi-gift-fill" style="font-size: 2rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-0 opacity-75">Arrecadado</h6>
                                        <h2 class="fw-bold mb-0 mt-2"><?= formatar_moeda($stats['total_arrecadado']) ?></h2>
                                    </div>
                                    <i class="bi bi-cash-stack" style="font-size: 2rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-0 opacity-75">Doadores</h6>
                                        <h2 class="fw-bold mb-0 mt-2"><?= $stats['doadores_unicos'] ?></h2>
                                    </div>
                                    <i class="bi bi-people-fill" style="font-size: 2rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campanhas Recentes -->
                <div class="card table-card">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Campanhas Recentes</h5>
                            <a href="nova_campanha.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> Nova Campanha
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Campanha</th>
                                        <th class="d-none d-md-table-cell">Tipo</th>
                                        <th>Status</th>
                                        <th class="d-none d-lg-table-cell">Criada em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campanhas as $camp): ?>
                                        <tr>
                                            <td class="fw-bold"><?= sanitizar($camp['titulo']) ?></td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-secondary"><?= ucfirst($camp['tipo']) ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = [
                                                    'ativa' => 'success',
                                                    'concluida' => 'info',
                                                    'cancelada' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_class[$camp['status']] ?>">
                                                    <?= ucfirst($camp['status']) ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell"><?= formatar_data($camp['criado_em']) ?></td>
                                            <td>
                                                <a href="campanha.php?token=<?= $camp['token'] ?>" class="btn btn-sm btn-outline-primary" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="compartilhar('<?= $camp['token'] ?>')" title="Compartilhar">
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu -->
    <nav class="navbar fixed-bottom navbar-light bg-white border-top d-md-none">
        <div class="container-fluid justify-content-around">
            <a href="dashboard.php" class="text-primary">
                <i class="bi bi-speedometer2" style="font-size: 1.5rem;"></i>
            </a>
            <a href="nova_campanha.php" class="text-secondary">
                <i class="bi bi-plus-circle" style="font-size: 1.5rem;"></i>
            </a>
            <a href="gerenciar_campanhas.php" class="text-secondary">
                <i class="bi bi-list-ul" style="font-size: 1.5rem;"></i>
            </a>
            <a href="relatorios.php" class="text-secondary">
                <i class="bi bi-file-earmark-bar-graph" style="font-size: 1.5rem;"></i>
            </a>
        </div>
    </nav>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function compartilhar(token) {
            const url = `${window.location.origin}/doacoes/campanha.php?token=${token}`;
            if (navigator.share) {
                navigator.share({
                    title: 'Campanha de Doação',
                    text: 'Participe desta campanha!',
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url);
                alert('Link copiado!');
            }
        }
    </script>
</body>
</html>