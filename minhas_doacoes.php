<?php
require_once __DIR__ . '/config.php';

if (!doador_logado()) {
    exigir_doador_login();
}

$doador = obter_doador($pdo);

$stmt = $pdo->prepare(" 
    SELECT d.*, c.titulo AS campanha_titulo, c.token AS campanha_token
    FROM doacoes d
    JOIN campanhas c ON d.campanha_id = c.id
    WHERE d.doador_id = ?
    ORDER BY d.data_promessa DESC
");
$stmt->execute([(int)$_SESSION['doador_id']]);
$doacoes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Doações - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-heart-fill"></i> Listafacil
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doador_logout.php">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container lf-page-pad">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Minhas doações</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-1">Minhas doações</h1>
                <div class="text-muted">Olá, <?= sanitizar($doador['nome'] ?? '') ?>. Aqui você acompanha e envia comprovantes quando quiser.</div>
            </div>
        </div>

        <?php if (empty($doacoes)): ?>
            <div class="card lf-main-card">
                <div class="card-body p-4 text-center text-muted">
                    <i class="bi bi-inbox" style="font-size: 2.5rem;"></i>
                    <div class="mt-2">Você ainda não fez nenhuma doação.</div>
                    <div class="mt-3"><a class="btn btn-primary" href="index.php">Ver campanhas</a></div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($doacoes as $d): ?>
                    <div class="col-12">
                        <div class="card lf-main-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <div class="fw-bold">Campanha</div>
                                        <div><?= sanitizar($d['campanha_titulo']) ?></div>
                                        <div class="text-muted small mt-1"><?= formatar_data_hora($d['data_promessa']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <?php
                                        $status_badges = [
                                            'prometido' => '<span class="badge bg-warning text-dark">Prometido</span>',
                                            'aguardando_confirmacao' => '<span class="badge bg-info">Aguardando confirmação</span>',
                                            'pago' => '<span class="badge bg-success">Pago</span>',
                                            'entregue' => '<span class="badge bg-success">Entregue</span>',
                                            'cancelado' => '<span class="badge bg-danger">Cancelado</span>'
                                        ];
                                        echo $status_badges[$d['status']] ?? '';
                                        ?>
                                    </div>
                                </div>

                                <hr>

                                <div class="row g-3 align-items-center">
                                    <div class="col-md-5">
                                        <div class="fw-bold">Doação</div>
                                        <div>
                                            <?php if ($d['tipo'] === 'dinheiro'): ?>
                                                <?= formatar_moeda($d['valor']) ?>
                                            <?php else: ?>
                                                <?= sanitizar($d['item_descricao']) ?> (<?= (int)$d['quantidade'] ?>x)
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($d['tipo'] === 'dinheiro'): ?>
                                            <div class="text-muted small mt-1">Pagamento: <?= ($d['forma_pagamento'] ?? 'pix') === 'dinheiro' ? 'Dinheiro em mãos' : 'PIX' ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-7">
                                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                            <a class="btn btn-outline-secondary" href="campanha.php?token=<?= sanitizar($d['campanha_token']) ?>" target="_blank">
                                                <i class="bi bi-eye"></i> Ver campanha
                                            </a>

                                            <?php if (!empty($d['token_publico'])): ?>
                                                <a class="btn btn-outline-primary" href="comprovante.php?token=<?= sanitizar($d['token_publico']) ?>" target="_blank">
                                                    <i class="bi bi-paperclip"></i> Enviar comprovante
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($d['tipo'] === 'dinheiro' && ($d['forma_pagamento'] ?? 'pix') === 'dinheiro' && in_array($d['status'], ['prometido'], true)): ?>
                                                <button class="btn btn-outline-success" onclick="solicitarConfirmacaoDinheiro(<?= (int)$d['id'] ?>)">
                                                    <i class="bi bi-check2-circle"></i> Solicitar confirmação
                                                </button>
                                            <?php endif; ?>

                                            <?php if (($d['status'] ?? '') === 'pago' || ($d['status'] ?? '') === 'entregue'): ?>
                                                <span class="text-muted small align-self-center">Confirmado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div id="msg-<?= (int)$d['id'] ?>" class="small mt-2"></div>
                                    </div>
                                </div>

                                <?php if (!empty($d['observacao'])): ?>
                                    <div class="text-muted small mt-3 fst-italic">"<?= sanitizar($d['observacao']) ?>"</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function solicitarConfirmacaoDinheiro(doacaoId) {
            if (!confirm('Confirmar: você entregou/entregará o dinheiro em mãos e deseja que o administrador valide?')) return;

            const msg = document.getElementById('msg-' + doacaoId);
            msg.textContent = '';

            const formData = new FormData();
            formData.append('doacao_id', doacaoId);

            try {
                const resp = await fetch('api.php?action=solicitar_confirmacao_dinheiro', {
                    method: 'POST',
                    body: formData
                });
                const result = await resp.json();
                if (result.success) {
                    msg.className = 'small mt-2 text-success';
                    msg.textContent = result.message;
                    setTimeout(() => location.reload(), 800);
                } else {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = result.message || 'Erro.';
                }
            } catch (e) {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = 'Erro ao enviar solicitação.';
            }
        }
    </script>
</body>
</html>
