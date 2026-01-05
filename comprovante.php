<?php
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("
    SELECT d.id, d.tipo, d.valor, d.item_descricao, d.quantidade, d.status, d.comprovante_enviado_em,
           c.titulo AS campanha_titulo,
           do.nome AS doador_nome
    FROM doacoes d
    JOIN campanhas c ON d.campanha_id = c.id
    JOIN doadores do ON d.doador_id = do.id
    WHERE d.token_publico = ?
    LIMIT 1
");
$stmt->execute([$token]);
$doacao = $stmt->fetch();

if (!$doacao) {
    die('Link inválido ou doação não encontrada.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Comprovante - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        body { padding: 20px 0; }
    </style>
</head>
<body>
    <div class="container lf-page-pad">
        <div class="card lf-main-card">
            <div class="card-body p-4">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Enviar comprovante</li>
                    </ol>
                </nav>

                <h3 class="fw-bold mb-2"><i class="bi bi-receipt"></i> Enviar comprovante</h3>
                <p class="text-muted mb-4">Você pode enviar o comprovante agora ou depois por este mesmo link. O administrador fará a confirmação.</p>

                <div class="alert alert-light">
                    <div class="fw-bold">Campanha</div>
                    <div><?= sanitizar($doacao['campanha_titulo']) ?></div>
                    <hr>
                    <div class="fw-bold">Doador</div>
                    <div><?= sanitizar($doacao['doador_nome']) ?></div>
                    <hr>
                    <div class="fw-bold">Doação</div>
                    <div>
                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                            <?= formatar_moeda($doacao['valor']) ?>
                        <?php else: ?>
                            <?= sanitizar($doacao['item_descricao']) ?> (<?= (int)$doacao['quantidade'] ?>x)
                        <?php endif; ?>
                    </div>
                </div>

                <div id="msg"></div>

                <?php if (!empty($doacao['comprovante_enviado_em'])): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        Já recebemos um comprovante em <?= formatar_data_hora($doacao['comprovante_enviado_em']) ?>.
                        Se precisar, você pode enviar novamente para substituir.
                    </div>
                <?php endif; ?>

                <form id="formComprovante" enctype="multipart/form-data">
                    <input type="hidden" name="token_publico" value="<?= sanitizar($token) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Arquivo do comprovante</label>
                        <input class="form-control" type="file" name="comprovante" accept="image/jpeg,image/png,application/pdf" required>
                        <div class="form-text">Formatos aceitos: JPG, PNG ou PDF (máx. 5MB).</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cloud-arrow-up"></i> Enviar comprovante para confirmação
                        </button>
                        <a class="btn btn-outline-light" href="index.php">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('formComprovante').addEventListener('submit', async function(e) {
            e.preventDefault();

            const msg = document.getElementById('msg');
            msg.innerHTML = '';

            const formData = new FormData(this);

            try {
                const response = await fetch('api.php?action=enviar_comprovante', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    msg.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle"></i> ${result.message}</div>`;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    msg.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ${result.message}</div>`;
                }
            } catch (err) {
                msg.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Erro ao enviar comprovante.</div>';
            }
        });
    </script>
</body>
</html>
