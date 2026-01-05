<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$campanha_id = (int)($_GET['id'] ?? 0);
if ($campanha_id <= 0) {
    die('Campanha inválida');
}

$stmt = $pdo->prepare('SELECT * FROM campanhas WHERE id = ?');
$stmt->execute([$campanha_id]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die('Campanha não encontrada');
}

$grupos = $pdo->query('SELECT id, nome FROM grupos ORDER BY nome')->fetchAll();
$stmt = $pdo->prepare('SELECT grupo_id FROM campanha_grupos WHERE campanha_id = ?');
$stmt->execute([$campanha_id]);
$grupos_selecionados = array_map('intval', array_column($stmt->fetchAll(), 'grupo_id'));

$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $tipo = $_POST['tipo'] ?? $campanha['tipo'];
    $meta_valor = parse_moeda_br($_POST['meta_valor'] ?? null);
    $meta_itens = $_POST['meta_itens'] ?? null;
    $data_inicio = $_POST['data_inicio'] ?? $campanha['data_inicio'];
    $data_fim = $_POST['data_fim'] ?? null;
    $grupos_ids = $_POST['grupos'] ?? [];

    if ($titulo === '' || $tipo === '') {
        $erro = 'Preencha os campos obrigatórios.';
    } else {
        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare('
                UPDATE campanhas
                SET titulo = ?, descricao = ?, tipo = ?, meta_valor = ?, meta_itens = ?, data_inicio = ?, data_fim = ?
                WHERE id = ?
            ');
            $upd->execute([
                $titulo,
                $descricao ?: null,
                $tipo,
                $meta_valor,
                ($meta_itens === '' ? null : $meta_itens),
                $data_inicio,
                ($data_fim === '' ? null : $data_fim),
                $campanha_id
            ]);

            $del = $pdo->prepare('DELETE FROM campanha_grupos WHERE campanha_id = ?');
            $del->execute([$campanha_id]);

            if (!empty($grupos_ids) && is_array($grupos_ids)) {
                $ins = $pdo->prepare('INSERT INTO campanha_grupos (campanha_id, grupo_id) VALUES (?, ?)');
                foreach ($grupos_ids as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) {
                        $ins->execute([$campanha_id, $gid]);
                    }
                }
            }

            $pdo->commit();
            $sucesso = 'Campanha atualizada com sucesso.';

            $stmt = $pdo->prepare('SELECT * FROM campanhas WHERE id = ?');
            $stmt->execute([$campanha_id]);
            $campanha = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT grupo_id FROM campanha_grupos WHERE campanha_id = ?');
            $stmt->execute([$campanha_id]);
            $grupos_selecionados = array_map('intval', array_column($stmt->fetchAll(), 'grupo_id'));
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Erro ao atualizar campanha.';
        }
    }
}

function fmt_input_moeda($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }
    return number_format((float)$valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Campanha - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-fill"></i> Listafacil
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="gerenciar_campanhas.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <a href="campanha.php?token=<?= sanitizar($campanha['token']) ?>" class="btn btn-outline-light btn-sm" target="_blank">
                    <i class="bi bi-eye"></i> Ver
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="gerenciar_campanhas.php">Campanhas</a></li>
                <li class="breadcrumb-item active" aria-current="page">Editar</li>
            </ol>
        </nav>

        <div class="card form-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="fw-bold mb-1">Editar Campanha</h3>
                        <div class="text-muted">Ajuste dados e metas sem alterar as doações já registradas.</div>
                    </div>
                    <span class="badge bg-secondary">ID #<?= (int)$campanha['id'] ?></span>
                </div>

                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= sanitizar($sucesso) ?></div>
                <?php endif; ?>
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= sanitizar($erro) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Título *</label>
                        <input type="text" name="titulo" class="form-control" required value="<?= sanitizar($campanha['titulo']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"><?= sanitizar($campanha['descricao']) ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tipo *</label>
                            <select name="tipo" class="form-select" required>
                                <option value="dinheiro" <?= $campanha['tipo'] === 'dinheiro' ? 'selected' : '' ?>>Somente Dinheiro</option>
                                <option value="itens" <?= $campanha['tipo'] === 'itens' ? 'selected' : '' ?>>Somente Itens/Produtos</option>
                                <option value="misto" <?= $campanha['tipo'] === 'misto' ? 'selected' : '' ?>>Dinheiro e Itens</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Meta Financeira (R$)</label>
                            <input type="text" name="meta_valor" class="form-control" inputmode="decimal" placeholder="Ex: 2.000,00" value="<?= sanitizar(fmt_input_moeda($campanha['meta_valor'])) ?>">
                            <div class="form-text">Aceita 2.000,00 (padrão BR).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Meta de Itens</label>
                            <input type="number" name="meta_itens" class="form-control" min="0" value="<?= sanitizar($campanha['meta_itens']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" required value="<?= sanitizar($campanha['data_inicio']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= sanitizar($campanha['data_fim']) ?>">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold">Grupos com acesso (opcional)</label>
                        <select name="grupos[]" class="form-select" multiple size="6">
                            <?php foreach ($grupos as $g): ?>
                                <?php $sel = in_array((int)$g['id'], $grupos_selecionados, true) ? 'selected' : ''; ?>
                                <option value="<?= (int)$g['id'] ?>" <?= $sel ?>><?= sanitizar($g['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Se selecionar grupos, a campanha fica restrita. Se deixar vazio, fica pública.</div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-primary btn-lg" type="submit">
                            <i class="bi bi-check-lg"></i> Salvar alterações
                        </button>
                        <a class="btn btn-outline-secondary" href="gerenciar_campanhas.php">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-muted mt-3">
            <small>Dica: alterar metas não apaga nem recalcula doações já registradas; apenas muda o objetivo exibido.</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
