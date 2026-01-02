// ==========================================
// campanha.php - Página Pública de Doação
// ==========================================
<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';

// Buscar campanha
$stmt = $pdo->prepare("SELECT * FROM campanhas WHERE token = ?");
$stmt->execute([$token]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die("Campanha não encontrada!");
}

// Buscar doações da campanha
$stmt = $pdo->prepare("
    SELECT d.*, do.nome as doador_nome 
    FROM doacoes d 
    JOIN doadores do ON d.doador_id = do.id 
    WHERE d.campanha_id = ? 
    ORDER BY d.data_promessa DESC
");
$stmt->execute([$campanha['id']]);
$doacoes = $stmt->fetchAll();

// Calcular totais
$total_dinheiro = 0;
$total_itens = 0;
foreach ($doacoes as $doacao) {
    if ($doacao['tipo'] === 'dinheiro' && in_array($doacao['status'], ['pago', 'entregue'])) {
        $total_dinheiro += $doacao['valor'];
    } elseif ($doacao['tipo'] === 'item') {
        $total_itens += $doacao['quantidade'];
    }
}

$percentual = 0;
if ($campanha['tipo'] === 'dinheiro' && $campanha['meta_valor'] > 0) {
    $percentual = ($total_dinheiro / $campanha['meta_valor']) * 100;
} elseif ($campanha['tipo'] === 'itens' && $campanha['meta_itens'] > 0) {
    $percentual = ($total_itens / $campanha['meta_itens']) * 100;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitizar($campanha['titulo']) ?> - Faça sua Doação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px 0; }
        .main-card { border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.2); border: none; }
        .progress { height: 30px; border-radius: 15px; }
        .doacao-item { border-left: 4px solid #28a745; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .doacao-item.prometido { border-left-color: #ffc107; }
        .btn-floating { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; font-size: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header da Campanha -->
        <div class="card main-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="fw-bold mb-2"><?= sanitizar($campanha['titulo']) ?></h2>
                        <p class="text-muted mb-0"><?= sanitizar($campanha['descricao']) ?></p>
                    </div>
                    <span class="badge bg-success fs-6">Ativa</span>
                </div>
                
                <?php if ($campanha['tipo'] === 'dinheiro' || $campanha['tipo'] === 'misto'): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Meta Financeira</span>
                            <span class="fw-bold text-success">
                                <?= formatar_moeda($total_dinheiro) ?> / <?= formatar_moeda($campanha['meta_valor']) ?>
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?= min($percentual, 100) ?>%">
                                <?= number_format(min($percentual, 100), 1) ?>%
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($campanha['tipo'] === 'itens' || $campanha['tipo'] === 'misto'): ?>
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-basket"></i> 
                        <strong><?= $total_itens ?></strong> itens prometidos
                        <?php if ($campanha['meta_itens']): ?>
                            de <strong><?= $campanha['meta_itens'] ?></strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#modalDoar">
                        <i class="bi bi-heart-fill"></i> Fazer Doação
                    </button>
                    <button class="btn btn-outline-secondary" onclick="compartilhar()">
                        <i class="bi bi-share"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Lista de Doações -->
        <div class="card main-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-people"></i> Doações (<?= count($doacoes) ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($doacoes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">Seja o primeiro a doar!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($doacoes as $doacao): ?>
                        <div class="doacao-item <?= $doacao['status'] === 'prometido' ? 'prometido' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= sanitizar($doacao['doador_nome']) ?></div>
                                    <div class="text-muted small">
                                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                                            <i class="bi bi-cash"></i> <?= formatar_moeda($doacao['valor']) ?>
                                        <?php else: ?>
                                            <i class="bi bi-box"></i> <?= sanitizar($doacao['item_descricao']) ?> 
                                            (<?= $doacao['quantidade'] ?>x)
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($doacao['observacao']): ?>
                                        <div class="small mt-1 text-muted fst-italic">
                                            "<?= sanitizar($doacao['observacao']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php
                                    $status_badges = [
                                        'prometido' => '<span class="badge bg-warning">Prometido</span>',
                                        'pago' => '<span class="badge bg-success">Pago</span>',
                                        'entregue' => '<span class="badge bg-success">Entregue</span>',
                                        'cancelado' => '<span class="badge bg-danger">Cancelado</span>'
                                    ];
                                    echo $status_badges[$doacao['status']];
                                    ?>
                                    <div class="small text-muted mt-1">
                                        <?= formatar_data_hora($doacao['data_promessa']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Fazer Doação -->
    <div class="modal fade" id="modalDoar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Fazer Doação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formDoacao">
                    <div class="modal-body">
                        <input type="hidden" name="campanha_id" value="<?= $campanha['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Seu Nome *</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="tel" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        
                        <?php if ($campanha['tipo'] === 'misto'): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tipo de Doação *</label>
                                <select name="tipo_doacao" class="form-select" required onchange="toggleTipoDoacao(this.value)">
                                    <option value="">Selecione...</option>
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="item">Item/Produto</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div id="campoDinheiro" style="<?= $campanha['tipo'] === 'itens' ? 'display:none' : '' ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Valor (R$) *</label>
                                <input type="number" name="valor" class="form-control" step="0.01" min="1">
                            </div>
                        </div>
                        
                        <div id="campoItem" style="<?= $campanha['tipo'] === 'dinheiro' ? 'display:none' : '' ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Descrição do Item *</label>
                                <input type="text" name="item_descricao" class="form-control" placeholder="Ex: Arroz 5kg">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Quantidade *</label>
                                <input type="number" name="quantidade" class="form-control" min="1" value="1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observação</label>
                            <textarea name="observacao" class="form-control" rows="2" placeholder="Alguma mensagem ou observação..."></textarea>
                        </div>
                        
                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="bi bi-info-circle"></i> 
                                Após confirmar, sua doação aparecerá como "Prometida". 
                                O administrador confirmará quando receber o pagamento/item.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Confirmar Doação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTipoDoacao(tipo) {
            const campoDinheiro = document.getElementById('campoDinheiro');
            const campoItem = document.getElementById('campoItem');
            
            if (tipo === 'dinheiro') {
                campoDinheiro.style.display = 'block';
                campoItem.style.display = 'none';
                document.querySelector('[name="valor"]').required = true;
                document.querySelector('[name="item_descricao"]').required = false;
            } else if (tipo === 'item') {
                campoDinheiro.style.display = 'none';
                campoItem.style.display = 'block';
                document.querySelector('[name="valor"]').required = false;
                document.querySelector('[name="item_descricao"]').required = true;
            }
        }
        
        document.getElementById('formDoacao').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api.php?action=criar_doacao', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Doação registrada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                alert('Erro ao processar doação. Tente novamente.');
            }
        });
        
        function compartilhar() {
            const url = window.location.href;
            const titulo = '<?= sanitizar($campanha['titulo']) ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: titulo,
                    text: 'Participe desta campanha de doação!',
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url);
                alert('Link copiado! Cole no WhatsApp.');
            }
        }
        
        // Atualizar página a cada 30 segundos para mostrar novas doações
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>


// ==========================================
// nova_campanha.php - Criar Nova Campanha
// ==========================================
<?php
require_once 'config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$sucesso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $meta_valor = $_POST['meta_valor'] ?? null;
    $meta_itens = $_POST['meta_itens'] ?? null;
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim = $_POST['data_fim'] ?? null;
    
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
            $campanha_id = $pdo->lastInsertId();
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
    <title>Nova Campanha - Sistema de Doações</title>
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
                <i class="bi bi-heart-fill"></i> Sistema de Doações
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-5">
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
                                <i class="bi bi-check-circle"></i> <?= $sucesso ?>
                                <div class="mt-2">
                                    <a href="campanha.php?token=<?= $token ?>" class="btn btn-sm btn-success" target="_blank">
                                        Ver Campanha
                                    </a>
                                    <button class="btn btn-sm btn-outline-success" onclick="compartilhar('<?= $token ?>')">
                                        Compartilhar
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($erro): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= $erro ?>
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
                                    <input type="number" name="meta_valor" class="form-control" step="0.01" min="0" placeholder="0.00">
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
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
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
            }
        }
        
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
    </script>
</body>
</html>