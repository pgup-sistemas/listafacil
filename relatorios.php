<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$campanhas = $pdo->query('SELECT id, titulo FROM campanhas ORDER BY titulo')->fetchAll();

$campanha_id = $_GET['campanha_id'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT
        c.titulo as campanha,
        do.nome as doador,
        do.telefone,
        do.email,
        d.tipo,
        d.valor,
        d.item_descricao,
        d.quantidade,
        d.status,
        d.observacao,
        d.data_promessa,
        d.data_confirmacao
    FROM doacoes d
    JOIN campanhas c ON d.campanha_id = c.id
    JOIN doadores do ON d.doador_id = do.id
    WHERE 1=1
";

$params = [];

if ($campanha_id) {
    $sql .= ' AND c.id = ?';
    $params[] = $campanha_id;
}

if ($tipo) {
    $sql .= ' AND d.tipo = ?';
    $params[] = $tipo;
}

if ($status) {
    $sql .= ' AND d.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY d.data_promessa DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doacoes = $stmt->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_doacoes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'Campanha',
        'Doador',
        'Telefone',
        'Email',
        'Tipo',
        'Valor',
        'Item',
        'Quantidade',
        'Status',
        'Observação',
        'Data Promessa',
        'Data Confirmação'
    ], ';');

    foreach ($doacoes as $doacao) {
        fputcsv($output, [
            $doacao['campanha'],
            $doacao['doador'],
            $doacao['telefone'],
            $doacao['email'],
            $doacao['tipo'],
            $doacao['valor'] ? 'R$ ' . number_format($doacao['valor'], 2, ',', '.') : '',
            $doacao['item_descricao'],
            $doacao['quantidade'],
            $doacao['status'],
            $doacao['observacao'],
            formatar_data_hora($doacao['data_promessa']),
            $doacao['data_confirmacao'] ? formatar_data_hora($doacao['data_confirmacao']) : ''
        ], ';');
    }

    fclose($output);
    exit;
}

$total_dinheiro = 0;
$total_itens = 0;
$total_confirmados = 0;

foreach ($doacoes as $doacao) {
    if ($doacao['tipo'] === 'dinheiro') {
        $total_dinheiro += (float)$doacao['valor'];
        if (in_array($doacao['status'], ['pago', 'entregue'], true)) {
            $total_confirmados += (float)$doacao['valor'];
        }
    } else {
        $total_itens += (int)$doacao['quantidade'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .stats-card { border-radius: 12px; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
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
                    <li class="nav-item"><a class="nav-link" href="gerenciar_campanhas.php">Campanhas</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3 no-print">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Relatórios</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Relatórios e Exportação</h3>
        </div>

        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Campanha</label>
                        <select name="campanha_id" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($campanhas as $camp): ?>
                                <option value="<?= (int)$camp['id'] ?>" <?= $campanha_id == $camp['id'] ? 'selected' : '' ?>>
                                    <?= sanitizar($camp['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="dinheiro" <?= $tipo === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                            <option value="item" <?= $tipo === 'item' ? 'selected' : '' ?>>Itens</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="prometido" <?= $status === 'prometido' ? 'selected' : '' ?>>Prometido</option>
                            <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="entregue" <?= $status === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                            <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h6 class="mb-0 opacity-75">Total Prometido (Dinheiro)</h6>
                        <h3 class="fw-bold mb-0 mt-2"><?= formatar_moeda($total_dinheiro) ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="mb-0 opacity-75">Total Confirmado</h6>
                        <h3 class="fw-bold mb-0 mt-2"><?= formatar_moeda($total_confirmados) ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h6 class="mb-0 opacity-75">Total de Itens</h6>
                        <h3 class="fw-bold mb-0 mt-2"><?= (int)$total_itens ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3 d-flex gap-2 no-print">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Doações (<?= count($doacoes) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campanha</th>
                                <th>Doador</th>
                                <th>Contato</th>
                                <th>Tipo</th>
                                <th>Valor/Item</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($doacoes)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Nenhuma doação encontrada com os filtros selecionados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($doacoes as $doacao): ?>
                                    <tr>
                                        <td><?= sanitizar($doacao['campanha']) ?></td>
                                        <td class="fw-bold"><?= sanitizar($doacao['doador']) ?></td>
                                        <td>
                                            <?php if (!empty($doacao['telefone'])): ?>
                                                <small><i class="bi bi-phone"></i> <?= sanitizar($doacao['telefone']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if (!empty($doacao['email'])): ?>
                                                <small><i class="bi bi-envelope"></i> <?= sanitizar($doacao['email']) ?></small>
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
                                            <?php $badges = ['prometido' => 'warning', 'pago' => 'success', 'entregue' => 'success', 'cancelado' => 'danger']; ?>
                                            <span class="badge bg-<?= $badges[$doacao['status']] ?>"><?= ucfirst($doacao['status']) ?></span>
                                        </td>
                                        <td><small><?= formatar_data_hora($doacao['data_promessa']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center text-muted mt-4">
            <small>Relatório gerado em <?= date('d/m/Y H:i') ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
