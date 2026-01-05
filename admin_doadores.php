<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    if (empty($nome) || empty($telefone) || empty($pin)) {
        $erro = 'Nome, telefone e PIN são obrigatórios.';
    } else {
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO doadores (nome, telefone, email, pin_hash) VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute([$nome, $telefone, $email ?: null, $pin_hash]);
            $sucesso = 'Doador cadastrado com sucesso.';
        } catch (Exception $e) {
            $erro = 'Erro ao cadastrar doador. Verifique se o telefone já existe.';
        }
    }
}

$doadores = $pdo->query('SELECT id, nome, telefone, email, criado_em FROM doadores ORDER BY nome')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doadores - Admin - Listafacil</title>
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
                <li class="breadcrumb-item active" aria-current="page">Doadores</li>
            </ol>
        </nav>

        <h3 class="fw-bold mb-3">Doadores (Participantes)</h3>

        <?php if ($sucesso): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= sanitizar($sucesso) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= sanitizar($erro) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">Cadastrar novo doador</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nome *</label>
                        <input class="form-control" name="nome" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone *</label>
                        <input class="form-control" name="telefone" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input class="form-control" name="email" type="email">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">PIN *</label>
                        <input class="form-control" name="pin" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Cadastrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">Lista</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>Criado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doadores as $d): ?>
                                <tr>
                                    <td><?= sanitizar($d['nome']) ?></td>
                                    <td><?= sanitizar($d['telefone']) ?></td>
                                    <td><?= sanitizar($d['email']) ?></td>
                                    <td><?= formatar_data_hora($d['criado_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($doadores)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum doador cadastrado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
