<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'criar_grupo') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (empty($nome)) {
                throw new Exception('Nome do grupo é obrigatório.');
            }

            $stmt = $pdo->prepare('INSERT INTO grupos (nome, descricao) VALUES (?, ?)');
            $stmt->execute([$nome, $descricao ?: null]);
            $sucesso = 'Grupo criado com sucesso.';
        }

        if ($action === 'adicionar_membro') {
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            $doador_id = (int)($_POST['doador_id'] ?? 0);

            if (empty($grupo_id) || empty($doador_id)) {
                throw new Exception('Selecione grupo e doador.');
            }

            $stmt = $pdo->prepare('INSERT IGNORE INTO grupo_membros (grupo_id, doador_id) VALUES (?, ?)');
            $stmt->execute([$grupo_id, $doador_id]);
            $sucesso = 'Membro adicionado.';
        }

        if ($action === 'remover_membro') {
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            $doador_id = (int)($_POST['doador_id'] ?? 0);

            $stmt = $pdo->prepare('DELETE FROM grupo_membros WHERE grupo_id = ? AND doador_id = ?');
            $stmt->execute([$grupo_id, $doador_id]);
            $sucesso = 'Membro removido.';
        }

    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

$grupo_selecionado = (int)($_GET['grupo_id'] ?? 0);

$grupos = $pdo->query('SELECT * FROM grupos ORDER BY nome')->fetchAll();
$doadores = $pdo->query('SELECT id, nome, telefone FROM doadores ORDER BY nome')->fetchAll();

$membros = [];
if ($grupo_selecionado) {
    $stmt = $pdo->prepare("
        SELECT d.id, d.nome, d.telefone
        FROM grupo_membros gm
        JOIN doadores d ON d.id = gm.doador_id
        WHERE gm.grupo_id = ?
        ORDER BY d.nome
    ");
    $stmt->execute([$grupo_selecionado]);
    $membros = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos - Admin - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background-color:#f8f9fa;">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-heart-fill"></i> Listafacil</a>
            <div class="ms-auto">
                <a class="btn btn-light btn-sm" href="dashboard.php"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Grupos</li>
            </ol>
        </nav>

        <h3 class="fw-bold mb-3">Grupos</h3>

        <?php if ($sucesso): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= sanitizar($sucesso) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= sanitizar($erro) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-bold">Criar grupo</div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="criar_grupo">
                            <div class="col-12">
                                <label class="form-label">Nome *</label>
                                <input class="form-control" name="nome" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white fw-bold">Selecionar grupo</div>
                    <div class="card-body">
                        <form method="GET">
                            <select class="form-select" name="grupo_id" onchange="this.form.submit()">
                                <option value="">Selecione...</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= (int)$g['id'] ?>" <?= $grupo_selecionado === (int)$g['id'] ? 'selected' : '' ?>>
                                        <?= sanitizar($g['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <div class="form-text">Escolha um grupo para gerenciar membros.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-bold">Adicionar membro</div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="adicionar_membro">
                            <div class="col-md-5">
                                <label class="form-label">Grupo *</label>
                                <select class="form-select" name="grupo_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($grupos as $g): ?>
                                        <option value="<?= (int)$g['id'] ?>" <?= $grupo_selecionado === (int)$g['id'] ? 'selected' : '' ?>>
                                            <?= sanitizar($g['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Doador *</label>
                                <select class="form-select" name="doador_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($doadores as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>"><?= sanitizar($d['nome']) ?> (<?= sanitizar($d['telefone']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white fw-bold">Membros do grupo</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Telefone</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membros as $m): ?>
                                        <tr>
                                            <td><?= sanitizar($m['nome']) ?></td>
                                            <td><?= sanitizar($m['telefone']) ?></td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remover_membro">
                                                    <input type="hidden" name="grupo_id" value="<?= (int)$grupo_selecionado ?>">
                                                    <input type="hidden" name="doador_id" value="<?= (int)$m['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover membro?')">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($grupo_selecionado && empty($membros)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">Nenhum membro neste grupo.</td></tr>
                                    <?php endif; ?>
                                    <?php if (!$grupo_selecionado): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">Selecione um grupo para ver os membros.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
