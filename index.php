<?php
require_once __DIR__ . '/config.php';

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
    <title>Listafacil - Sistema de Doações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        .card:hover { transform: translateY(-2px); transition: transform 0.2s ease; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= sanitizar(SITE_URL) ?>/index.php">
                <i class="bi bi-heart-fill"></i> Listafacil
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (doador_logado()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="minhas_doacoes.php">
                                <i class="bi bi-receipt"></i> Minhas doações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="doador_logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="doador_login.php">
                                <i class="bi bi-person-check"></i> Entrar (Participante)
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-shield-lock"></i> Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <nav aria-label="breadcrumb" class="pt-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active" aria-current="page">Início</li>
            </ol>
        </nav>
    </div>

    <div class="container lf-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 lf-title">Campanhas Ativas</h1>
                <div class="lf-subtitle">Escolha uma campanha e faça sua doação.</div>
            </div>
        </div>
    </div>

    <div class="container pb-5">

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
                                    $total_arrecadado = (float)($stmt->fetch()['total'] ?? 0);
                                    $percentual = ($campanha['meta_valor'] ?? 0) > 0 ? ($total_arrecadado / $campanha['meta_valor']) * 100 : 0;
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
                                    <a href="campanha.php?token=<?= sanitizar($campanha['token']) ?>" class="btn btn-primary flex-fill">
                                        <i class="bi bi-hand-thumbs-up"></i> Doar Agora
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="compartilhar('<?= sanitizar($campanha['token']) ?>')">
                                        <i class="bi bi-share"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($campanha['data_fim'])): ?>
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
            const url = `${window.location.origin}${window.location.pathname.replace(/\/[^/]*$/, '')}/campanha.php?token=${token}`;
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
