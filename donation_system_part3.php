// ==========================================
// api.php - API para Operações AJAX
// ==========================================
<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'criar_doacao':
            // Validar dados
            $nome = $_POST['nome'] ?? '';
            $telefone = $_POST['telefone'] ?? null;
            $email = $_POST['email'] ?? null;
            $campanha_id = $_POST['campanha_id'] ?? 0;
            
            if (empty($nome) || empty($campanha_id)) {
                throw new Exception('Dados incompletos');
            }
            
            // Verificar se doador já existe
            $stmt = $pdo->prepare("SELECT id FROM doadores WHERE nome = ? LIMIT 1");
            $stmt->execute([$nome]);
            $doador = $stmt->fetch();
            
            if ($doador) {
                $doador_id = $doador['id'];
            } else {
                // Criar novo doador
                $stmt = $pdo->prepare("INSERT INTO doadores (nome, telefone, email) VALUES (?, ?, ?)");
                $stmt->execute([$nome, $telefone, $email]);
                $doador_id = $pdo->lastInsertId();
            }
            
            // Determinar tipo de doação
            $tipo_doacao = $_POST['tipo_doacao'] ?? '';
            if (empty($tipo_doacao)) {
                // Auto-detectar baseado nos campos preenchidos
                $tipo_doacao = !empty($_POST['valor']) ? 'dinheiro' : 'item';
            }
            
            $valor = $tipo_doacao === 'dinheiro' ? ($_POST['valor'] ?? 0) : null;
            $item_descricao = $tipo_doacao === 'item' ? ($_POST['item_descricao'] ?? '') : null;
            $quantidade = $tipo_doacao === 'item' ? ($_POST['quantidade'] ?? 1) : 1;
            $observacao = $_POST['observacao'] ?? null;
            
            // Inserir doação
            $stmt = $pdo->prepare("
                INSERT INTO doacoes (campanha_id, doador_id, tipo, valor, item_descricao, quantidade, observacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $campanha_id,
                $doador_id,
                $tipo_doacao,
                $valor,
                $item_descricao,
                $quantidade,
                $observacao
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Doação registrada com sucesso!']);
            break;
            
        case 'atualizar_status_doacao':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }
            
            $doacao_id = $_POST['doacao_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            
            if (empty($doacao_id) || empty($status)) {
                throw new Exception('Dados incompletos');
            }
            
            $stmt = $pdo->prepare("UPDATE doacoes SET status = ?, data_confirmacao = NOW() WHERE id = ?");
            $stmt->execute([$status, $doacao_id]);
            
            echo json_encode(['success' => true, 'message' => 'Status atualizado']);
            break;
            
        case 'excluir_doacao':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }
            
            $doacao_id = $_POST['doacao_id'] ?? 0;
            
            $stmt = $pdo->prepare("DELETE FROM doacoes WHERE id = ?");
            $stmt->execute([$doacao_id]);
            
            echo json_encode(['success' => true, 'message' => 'Doação excluída']);
            break;
            
        case 'alterar_status_campanha':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }
            
            $campanha_id = $_POST['campanha_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            
            $stmt = $pdo->prepare("UPDATE campanhas SET status = ? WHERE id = ?");
            $stmt->execute([$status, $campanha_id]);
            
            echo json_encode(['success' => true, 'message' => 'Status da campanha atualizado']);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


// ==========================================
// gerenciar_campanhas.php - Gerenciar Campanhas
// ==========================================
<?php
require_once 'config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

// Buscar todas as campanhas
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
    <title>Gerenciar Campanhas</title>
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
                <i class="bi bi-heart-fill"></i> Sistema de Doações
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nova_campanha.php">Nova Campanha</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">Gerenciar Campanhas</h3>
            <a href="nova_campanha.php" class="btn btn-primary">
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
                                <?php
                                $status_colors = ['ativa' => 'success', 'concluida' => 'info', 'cancelada' => 'danger'];
                                ?>
                                <span class="badge bg-<?= $status_colors[$camp['status']] ?>">
                                    <?= ucfirst($camp['status']) ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-people"></i> <?= $camp['total_doacoes'] ?> doações
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-3 text-center my-3 my-md-0">
                            <?php if ($camp['tipo'] === 'dinheiro' || $camp['tipo'] === 'misto'): ?>
                                <div class="text-success fw-bold" style="font-size: 1.5rem;">
                                    <?= formatar_moeda($camp['total_arrecadado']) ?>
                                </div>
                                <?php if ($camp['meta_valor']): ?>
                                    <small class="text-muted">Meta: <?= formatar_moeda($camp['meta_valor']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="d-grid gap-2">
                                <a href="campanha.php?token=<?= $camp['token'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-eye"></i> Visualizar
                                </a>
                                <a href="detalhes_campanha.php?id=<?= $camp['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-list-check"></i> Gerenciar Doações
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="compartilhar('<?= $camp['token'] ?>')">
                                    <i class="bi bi-share"></i> Compartilhar
                                </button>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-warning" onclick="alterarStatus(<?= $camp['id'] ?>, 'concluida')">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="alterarStatus(<?= $camp['id'] ?>, 'cancelada')">
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
    <script>
        function compartilhar(token) {
            const url = `${window.location.origin}/doacoes/campanha.php?token=${token}`;
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


// ==========================================
// detalhes_campanha.php - Gerenciar Doações da Campanha
// ==========================================
<?php
require_once 'config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$campanha_id = $_GET['id'] ?? 0;

// Buscar campanha
$stmt = $pdo->prepare("SELECT * FROM campanhas WHERE id = ?");
$stmt->execute([$campanha_id]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die("Campanha não encontrada");
}

// Buscar doações
$doacoes = $pdo->prepare("
    SELECT d.*, do.nome as doador_nome, do.telefone, do.email
    FROM doacoes d
    JOIN doadores do ON d.doador_id = do.id
    WHERE d.campanha_id = ?
    ORDER BY d.data_promessa DESC
");
$doacoes->execute([$campanha_id]);
$doacoes = $doacoes->fetchAll();
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
                <i class="bi bi-heart-fill"></i> Sistema de Doações
            </a>
            <div class="ms-auto">
                <a href="gerenciar_campanhas.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
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
                                        <?php if ($doacao['telefone']): ?>
                                            <small class="text-muted"><i class="bi bi-phone"></i> <?= sanitizar($doacao['telefone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= ucfirst($doacao['tipo']) ?></td>
                                    <td>
                                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                                            <strong><?= formatar_moeda($doacao['valor']) ?></strong>
                                        <?php else: ?>
                                            <?= sanitizar($doacao['item_descricao']) ?> (<?= $doacao['quantidade'] ?>x)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" onchange="alterarStatus(<?= $doacao['id'] ?>, this.value)">
                                            <option value="prometido" <?= $doacao['status'] === 'prometido' ? 'selected' : '' ?>>Prometido</option>
                                            <option value="pago" <?= $doacao['status'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                                            <option value="entregue" <?= $doacao['status'] === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                                            <option value="cancelado" <?= $doacao['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                        </select>
                                    </td>
                                    <td>
                                        <small><?= formatar_data_hora($doacao['data_promessa']) ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirDoacao(<?= $doacao['id'] ?>)">
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
    </script>
</body>
</html>