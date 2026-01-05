<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$campanha_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM campanhas WHERE id = ?');
$stmt->execute([$campanha_id]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die('Campanha não encontrada');
}

$doacoes_stmt = $pdo->prepare("
    SELECT d.*, do.nome as doador_nome, do.telefone, do.email
    FROM doacoes d
    JOIN doadores do ON d.doador_id = do.id
    WHERE d.campanha_id = ?
    ORDER BY d.data_promessa DESC
");
$doacoes_stmt->execute([$campanha_id]);
$doacoes = $doacoes_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Doações - <?= sanitizar($campanha['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-fill"></i> Listafacil
            </a>
            <div class="ms-auto">
                <a href="gerenciar_campanhas.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="gerenciar_campanhas.php">Campanhas</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= sanitizar($campanha['titulo']) ?></li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="fw-bold"><?= sanitizar($campanha['titulo']) ?></h4>
                <p class="text-muted mb-0"><?= sanitizar($campanha['descricao']) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Doações (<?= count($doacoes) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Doador</th>
                                <th>Tipo</th>
                                <th>Valor/Item</th>
                                <th>Pagamento</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doacoes as $doacao): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= sanitizar($doacao['doador_nome']) ?></div>
                                        <?php if (!empty($doacao['telefone'])): ?>
                                            <small class="text-muted"><i class="bi bi-phone"></i> <?= sanitizar($doacao['telefone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= ucfirst($doacao['tipo']) ?></td>
                                    <td>
                                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                                            <strong><?= formatar_moeda($doacao['valor']) ?></strong>
                                        <?php else: ?>
                                            <?= sanitizar($doacao['item_descricao']) ?> (<?= (int)$doacao['quantidade'] ?>x)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                                            <span class="badge bg-<?= ($doacao['forma_pagamento'] ?? 'pix') === 'dinheiro' ? 'secondary' : 'primary' ?>">
                                                <?= ($doacao['forma_pagamento'] ?? 'pix') === 'dinheiro' ? 'Dinheiro' : 'PIX' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" onchange="alterarStatus(<?= (int)$doacao['id'] ?>, this.value)">
                                            <option value="prometido" <?= $doacao['status'] === 'prometido' ? 'selected' : '' ?>>Prometido</option>
                                            <option value="aguardando_confirmacao" <?= $doacao['status'] === 'aguardando_confirmacao' ? 'selected' : '' ?>>Aguardando confirmação</option>
                                            <option value="pago" <?= $doacao['status'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                                            <option value="entregue" <?= $doacao['status'] === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                                            <option value="cancelado" <?= $doacao['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                        </select>
                                        <?php if (!empty($doacao['comprovante'])): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-success">
                                                    <i class="bi bi-paperclip"></i> Comprovante anexado
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= formatar_data_hora($doacao['data_promessa']) ?></small></td>
                                    <td>
                                        <?php if (!empty($doacao['comprovante'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="ver_comprovante.php?id=<?= (int)$doacao['id'] ?>" target="_blank" title="Ver comprovante">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($doacao['status'] === 'aguardando_confirmacao'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="confirmarPagamento(<?= (int)$doacao['id'] ?>)" title="Confirmar pagamento">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirDoacao(<?= (int)$doacao['id'] ?>)">
                                            <i class="bi bi-trash"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function alterarStatus(doacaoId, status) {
            const formData = new FormData();
            formData.append('doacao_id', doacaoId);
            formData.append('status', status);

            try {
                const response = await fetch('api.php?action=atualizar_status_doacao', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (!result.success) {
                    alert('Erro: ' + result.message);
                    location.reload();
                }
            } catch (error) {
                alert('Erro ao atualizar status');
            }
        }

        async function excluirDoacao(doacaoId) {
            if (!confirm('Deseja realmente excluir esta doação?')) return;

            const formData = new FormData();
            formData.append('doacao_id', doacaoId);

            try {
                const response = await fetch('api.php?action=excluir_doacao', {
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
                alert('Erro ao excluir doação');
            }
        }

        async function confirmarPagamento(doacaoId) {
            if (!confirm('Confirmar este pagamento como PAGO?')) return;

            const formData = new FormData();
            formData.append('doacao_id', doacaoId);

            try {
                const response = await fetch('api.php?action=confirmar_pagamento_doacao', {
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
                alert('Erro ao confirmar pagamento');
            }
        }
    </script>
</body>
</html>
