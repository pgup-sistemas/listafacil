<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$stats = [
    'campanhas_ativas' => (int)$pdo->query("SELECT COUNT(*) FROM campanhas WHERE status = 'ativa'")->fetchColumn(),
    'total_doacoes' => (int)$pdo->query('SELECT COUNT(*) FROM doacoes')->fetchColumn(),
    'total_arrecadado' => (float)$pdo->query("SELECT COALESCE(SUM(valor), 0) FROM doacoes WHERE tipo = 'dinheiro' AND status IN ('pago', 'entregue')")->fetchColumn(),
    'doadores_unicos' => (int)$pdo->query('SELECT COUNT(DISTINCT doador_id) FROM doacoes')->fetchColumn()
];

$campanhas = $pdo->query('SELECT * FROM campanhas ORDER BY criado_em DESC LIMIT 10')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Listafacil</title>
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
            <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block">
                <div class="p-4 text-white">
                    <h4 class="fw-bold"><i class="bi bi-heart-fill"></i> Listafacil</h4>
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
                    <a class="nav-link" href="admin_doadores.php">
                        <i class="bi bi-people"></i> Doadores
                    </a>
                    <a class="nav-link" href="admin_grupos.php">
                        <i class="bi bi-diagram-3"></i> Grupos
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

            <div class="col-md-9 col-lg-10 px-4 py-4">
                <div class="d-md-none mb-4">
                    <h4 class="fw-bold">Dashboard</h4>
                </div>

                <nav aria-label="breadcrumb" class="mb-3 d-none d-md-block">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>

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
                                                <?php $status_class = ['ativa' => 'success', 'concluida' => 'info', 'cancelada' => 'danger']; ?>
                                                <span class="badge bg-<?= $status_class[$camp['status']] ?>">
                                                    <?= ucfirst($camp['status']) ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-lg-table-cell"><?= formatar_data($camp['criado_em']) ?></td>
                                            <td>
                                                <a href="campanha.php?token=<?= sanitizar($camp['token']) ?>" class="btn btn-sm btn-outline-primary" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="compartilhar('<?= sanitizar($camp['token']) ?>')" title="Compartilhar">
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
            const base = `${window.location.origin}${window.location.pathname.replace(/\/[^/]*$/, '')}`;
            const url = `${base}/campanha.php?token=${token}`;
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
