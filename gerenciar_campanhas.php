<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$campanhas = $pdo->query("
    SELECT c.*,
           COUNT(d.id) as total_doacoes,
           COALESCE(SUM(CASE WHEN d.tipo = 'dinheiro' AND d.status IN ('pago', 'entregue') THEN d.valor ELSE 0 END), 0) as total_arrecadado
    FROM campanhas c
    LEFT JOIN doacoes d ON c.id = d.campanha_id
    GROUP BY c.id
    ORDER BY c.criado_em DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Campanhas - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .campanha-card { border-radius: 12px; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.08); margin-bottom: 15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-fill"></i> Listafacil
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="nova_campanha.php">Nova Campanha</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Campanhas</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">Gerenciar Campanhas</h3>
            <a href="nova_campanha.php" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Nova Campanha
            </a>
        </div>

        <?php foreach ($campanhas as $camp): ?>
            <div class="card campanha-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-2"><?= sanitizar($camp['titulo']) ?></h5>
                            <p class="text-muted mb-2 small"><?= sanitizar($camp['descricao']) ?></p>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-secondary"><?= ucfirst($camp['tipo']) ?></span>
                                <?php $status_colors = ['ativa' => 'success', 'concluida' => 'info', 'cancelada' => 'danger']; ?>
                                <span class="badge bg-<?= $status_colors[$camp['status']] ?>"><?= ucfirst($camp['status']) ?></span>
                                <span class="badge bg-light text-dark"><i class="bi bi-people"></i> <?= (int)$camp['total_doacoes'] ?> doações</span>
                            </div>
                        </div>

                        <div class="col-md-3 text-center my-3 my-md-0">
                            <?php if ($camp['tipo'] === 'dinheiro' || $camp['tipo'] === 'misto'): ?>
                                <div class="text-success fw-bold" style="font-size: 1.5rem;">
                                    <?= formatar_moeda($camp['total_arrecadado']) ?>
                                </div>
                                <?php if (!empty($camp['meta_valor'])): ?>
                                    <small class="text-muted">Meta: <?= formatar_moeda($camp['meta_valor']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <div class="d-grid gap-2">
                                <a href="campanha.php?token=<?= sanitizar($camp['token']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-eye"></i> Visualizar
                                </a>
                                <a href="editar_campanha.php?id=<?= (int)$camp['id'] ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-pencil-square"></i> Editar
                                </a>
                                <a href="detalhes_campanha.php?id=<?= (int)$camp['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-list-check"></i> Gerenciar Doações
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="compartilhar('<?= sanitizar($camp['token']) ?>')">
                                    <i class="bi bi-share"></i> Compartilhar
                                </button>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-warning" onclick="alterarStatus(<?= (int)$camp['id'] ?>, 'concluida')">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="alterarStatus(<?= (int)$camp['id'] ?>, 'cancelada')">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($campanhas)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> Nenhuma campanha criada ainda
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <div class="modal fade" id="modalCompartilhar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-share"></i> Compartilhar campanha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link da campanha</label>
                        <div class="input-group">
                            <input id="shareUrl" class="form-control" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copiarLinkCompartilhar()">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                        <div id="shareMsg" class="form-text"></div>
                    </div>

                    <div class="text-center">
                        <div class="fw-bold mb-2">QR Code</div>
                        <div class="bg-white rounded p-3 d-inline-block">
                            <canvas id="shareQrCanvas" style="max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" onclick="imprimirQr()">
                        <i class="bi bi-printer"></i> Imprimir QR
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

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
                abrirModalCompartilhar(url);
            }
        }

        function abrirModalCompartilhar(url) {
            document.getElementById('shareUrl').value = url;
            const msg = document.getElementById('shareMsg');
            msg.textContent = '';

            const canvas = document.getElementById('shareQrCanvas');
            canvas.width = 260;
            canvas.height = 260;

            if (window.QRCode && QRCode.toCanvas) {
                QRCode.toCanvas(canvas, url, { width: 260, margin: 1 }, function (error) {
                    if (error) {
                        msg.textContent = 'Não foi possível gerar o QR Code.';
                    }
                });
            } else {
                msg.textContent = 'QR Code indisponível.';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalCompartilhar'));
            modal.show();
        }

        async function copiarLinkCompartilhar() {
            const input = document.getElementById('shareUrl');
            const msg = document.getElementById('shareMsg');
            try {
                await navigator.clipboard.writeText(input.value);
                msg.textContent = 'Link copiado!';
            } catch (e) {
                input.select();
                document.execCommand('copy');
                msg.textContent = 'Link copiado!';
            }
        }

        function imprimirQr() {
            const url = document.getElementById('shareUrl').value;
            const canvas = document.getElementById('shareQrCanvas');
            const imgData = canvas.toDataURL('image/png');

            const w = window.open('', '_blank');
            if (!w) return;
            w.document.write(`
                <html>
                <head>
                    <title>QR Code - Campanha</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 24px; }
                        .box { text-align: center; }
                        img { width: 320px; height: 320px; }
                        .url { margin-top: 12px; word-break: break-all; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="box">
                        <h2>Campanha de Doação</h2>
                        <img src="${imgData}" alt="QR Code" />
                        <div class="url">${url}</div>
                    </div>
                    <script>window.onload = () => window.print();<\/script>
                </body>
                </html>
            `);
            w.document.close();
        }

        async function alterarStatus(campanhaId, status) {
            if (!confirm(`Deseja alterar o status da campanha para "${status}"?`)) return;

            const formData = new FormData();
            formData.append('campanha_id', campanhaId);
            formData.append('status', status);

            try {
                const response = await fetch('api.php?action=alterar_status_campanha', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                alert('Erro ao alterar status');
            }
        }
    </script>
</body>
</html>
