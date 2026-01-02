<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$grupos = $pdo->query('SELECT id, nome FROM grupos ORDER BY nome')->fetchAll();

$sucesso = '';
$erro = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $meta_valor = parse_moeda_br($_POST['meta_valor'] ?? null);
    $meta_itens = $_POST['meta_itens'] ?? null;
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim = $_POST['data_fim'] ?? null;
    $grupos_ids = $_POST['grupos'] ?? [];

    if (empty($titulo) || empty($tipo)) {
        $erro = 'Preencha todos os campos obrigatórios';
    } else {
        $token = gerar_token();

        $stmt = $pdo->prepare("
            INSERT INTO campanhas (titulo, descricao, tipo, meta_valor, meta_itens, data_inicio, data_fim, token, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$titulo, $descricao, $tipo, $meta_valor, $meta_itens, $data_inicio, $data_fim, $token, $_SESSION['usuario_id']])) {
            $sucesso = 'Campanha criada com sucesso!';

            $campanha_id = (int)$pdo->lastInsertId();
            if (!empty($grupos_ids) && is_array($grupos_ids)) {
                $ins = $pdo->prepare('INSERT IGNORE INTO campanha_grupos (campanha_id, grupo_id) VALUES (?, ?)');
                foreach ($grupos_ids as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) {
                        $ins->execute([$campanha_id, $gid]);
                    }
                }
            }
        } else {
            $erro = 'Erro ao criar campanha';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Campanha - Listafacil</title>
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
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Nova Campanha</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card form-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-plus-circle text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 fw-bold">Nova Campanha</h3>
                            <p class="text-muted">Preencha os dados da campanha de doação</p>
                        </div>

                        <?php if ($sucesso): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?= sanitizar($sucesso) ?>
                                <div class="mt-2">
                                    <a href="campanha.php?token=<?= sanitizar($token) ?>" class="btn btn-sm btn-success" target="_blank">Ver Campanha</a>
                                    <button class="btn btn-sm btn-outline-success" onclick="compartilhar('<?= sanitizar($token) ?>')">Compartilhar</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($erro): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= sanitizar($erro) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Título da Campanha *</label>
                                <input type="text" name="titulo" class="form-control" required placeholder="Ex: Cesta Básica Natal 2024">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva o objetivo da campanha..."></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Grupos com acesso (opcional)</label>
                                <select name="grupos[]" class="form-select" multiple size="5">
                                    <?php foreach ($grupos as $g): ?>
                                        <option value="<?= (int)$g['id'] ?>"><?= sanitizar($g['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Se selecionar grupos, somente participantes desses grupos conseguem doar (com telefone+PIN). Se deixar em branco, fica público.</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Tipo de Doação *</label>
                                <select name="tipo" class="form-select" required onchange="toggleMetas(this.value)">
                                    <option value="">Selecione...</option>
                                    <option value="dinheiro">Somente Dinheiro</option>
                                    <option value="itens">Somente Itens/Produtos</option>
                                    <option value="misto">Dinheiro e Itens</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4" id="campoMetaValor" style="display:none;">
                                    <label class="form-label fw-bold">Meta Financeira (R$)</label>
                                    <input type="text" name="meta_valor" class="form-control" inputmode="decimal" placeholder="Ex: 2.000,00">
                                    <small class="text-muted">Deixe em branco se não houver meta</small>
                                </div>

                                <div class="col-md-6 mb-4" id="campoMetaItens" style="display:none;">
                                    <label class="form-label fw-bold">Meta de Itens</label>
                                    <input type="number" name="meta_itens" class="form-control" min="0" placeholder="0">
                                    <small class="text-muted">Quantidade total de itens desejados</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Data Início</label>
                                    <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Data Fim</label>
                                    <input type="date" name="data_fim" class="form-control">
                                    <small class="text-muted">Opcional</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Criar Campanha
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMetas(tipo) {
            const campoMetaValor = document.getElementById('campoMetaValor');
            const campoMetaItens = document.getElementById('campoMetaItens');

            if (tipo === 'dinheiro') {
                campoMetaValor.style.display = 'block';
                campoMetaItens.style.display = 'none';
            } else if (tipo === 'itens') {
                campoMetaValor.style.display = 'none';
                campoMetaItens.style.display = 'block';
            } else if (tipo === 'misto') {
                campoMetaValor.style.display = 'block';
                campoMetaItens.style.display = 'block';
            } else {
                campoMetaValor.style.display = 'none';
                campoMetaItens.style.display = 'none';
            }
        }

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
