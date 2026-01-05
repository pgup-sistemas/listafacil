<?php
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM campanhas WHERE token = ?');
$stmt->execute([$token]);
$campanha = $stmt->fetch();

if (!$campanha) {
    die('Campanha não encontrada!');
}

// Se a campanha estiver vinculada a grupos, exige identificação do doador
if (campanha_tem_grupos($pdo, $campanha['id'])) {
    if (!doador_logado()) {
        exigir_doador_login();
    }

    if (!doador_tem_acesso_campanha($pdo, (int)$_SESSION['doador_id'], (int)$campanha['id'])) {
        die('Seu acesso não está liberado para esta campanha.');
    }
}

$doador = obter_doador($pdo);

$stmt = $pdo->prepare("
    SELECT d.*, do.nome as doador_nome
    FROM doacoes d
    JOIN doadores do ON d.doador_id = do.id
    WHERE d.campanha_id = ?
    ORDER BY d.data_promessa DESC
");
$stmt->execute([$campanha['id']]);
$doacoes = $stmt->fetchAll();

$total_dinheiro = 0;
$total_itens = 0;
foreach ($doacoes as $doacao) {
    if ($doacao['tipo'] === 'dinheiro' && in_array($doacao['status'], ['pago', 'entregue'], true)) {
        $total_dinheiro += (float)$doacao['valor'];
    } elseif ($doacao['tipo'] === 'item') {
        $total_itens += (int)$doacao['quantidade'];
    }
}

$percentual = 0;
if ($campanha['tipo'] === 'dinheiro' && ($campanha['meta_valor'] ?? 0) > 0) {
    $percentual = ($total_dinheiro / $campanha['meta_valor']) * 100;
} elseif ($campanha['tipo'] === 'itens' && ($campanha['meta_itens'] ?? 0) > 0) {
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
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        body { padding: 20px 0; }
    </style>
</head>
<body>
    <div class="container lf-page-pad">
        <div class="card lf-main-card mb-4">
            <div class="card-body p-4">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= sanitizar($campanha['titulo']) ?></li>
                    </ol>
                </nav>

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
                            <span class="fw-bold text-success"><?= formatar_moeda($total_dinheiro) ?> / <?= formatar_moeda($campanha['meta_valor']) ?></span>
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
                        <?php if (!empty($campanha['meta_itens'])): ?>
                            de <strong><?= (int)$campanha['meta_itens'] ?></strong>
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

        <div class="card lf-main-card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-people"></i> Doações (<?= count($doacoes) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($doacoes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">Seja o primeiro a doar!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($doacoes as $doacao): ?>
                        <div class="lf-doacao-item <?= $doacao['status'] === 'prometido' ? 'prometido' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= sanitizar($doacao['doador_nome']) ?></div>
                                    <div class="text-muted small">
                                        <?php if ($doacao['tipo'] === 'dinheiro'): ?>
                                            <i class="bi bi-cash"></i> <?= formatar_moeda($doacao['valor']) ?>
                                        <?php else: ?>
                                            <i class="bi bi-box"></i> <?= sanitizar($doacao['item_descricao']) ?> (<?= (int)$doacao['quantidade'] ?>x)
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($doacao['observacao'])): ?>
                                        <div class="small mt-1 text-muted fst-italic">"<?= sanitizar($doacao['observacao']) ?>"</div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php
                                    $status_badges = [
                                        'prometido' => '<span class="badge bg-warning">Prometido</span>',
                                        'aguardando_confirmacao' => '<span class="badge bg-info">Aguardando confirmação</span>',
                                        'pago' => '<span class="badge bg-success">Pago</span>',
                                        'entregue' => '<span class="badge bg-success">Entregue</span>',
                                        'cancelado' => '<span class="badge bg-danger">Cancelado</span>'
                                    ];
                                    echo $status_badges[$doacao['status']] ?? '';
                                    ?>
                                    <div class="small text-muted mt-1"><?= formatar_data_hora($doacao['data_promessa']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDoar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Fazer Doação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formDoacao">
                    <div class="modal-body">
                        <input type="hidden" name="campanha_id" value="<?= (int)$campanha['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Seu Nome *</label>
                            <input type="text" name="nome" class="form-control" required value="<?= $doador ? sanitizar($doador['nome']) : '' ?>" <?= $doador ? 'readonly' : '' ?>>
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
                                <input type="text" name="valor" class="form-control" inputmode="decimal" placeholder="Ex: 50,00 ou 2.000,00">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Forma de pagamento</label>
                                <select name="forma_pagamento" class="form-select">
                                    <option value="pix" selected>PIX (vou pagar pelo app)</option>
                                    <option value="dinheiro">Dinheiro em mãos (tesouraria)</option>
                                </select>
                                <div class="form-text">Se for PIX, depois você pode anexar o comprovante em "Minhas doações".</div>
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

    <div class="modal fade" id="modalCompartilhar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-share"></i> Compartilhar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link da campanha</label>
                        <div class="input-group">
                            <input id="shareUrl" class="form-control" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copiarLinkCompartilhar()">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                        <div id="shareMsg" class="form-text"></div>
                    </div>

                    <div class="text-center">
                        <div class="fw-bold mb-2">QR Code</div>
                        <div class="bg-white rounded p-3 d-inline-block">
                            <canvas id="shareQrCanvas" style="max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" onclick="imprimirQr()">
                        <i class="bi bi-printer"></i> Imprimir QR
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        function basePath() {
            return `${window.location.origin}${window.location.pathname.replace(/\/[^/]*$/, '')}`;
        }

        function exibirLinkComprovante(tokenPublico) {
            const url = `${basePath()}/comprovante.php?token=${tokenPublico}`;
            const texto = `Doação registrada!\n\nPara marcar como pago e enviar o comprovante depois, use este link:\n${url}`;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).catch(() => {});
            }

            alert(texto);
        }

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
                    if (result.token_publico) {
                        exibirLinkComprovante(result.token_publico);
                    } else {
                        alert('Doação registrada com sucesso!');
                    }
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
                abrirModalCompartilhar(url);
            }
        }

        function abrirModalCompartilhar(url) {
            document.getElementById('shareUrl').value = url;
            const msg = document.getElementById('shareMsg');
            msg.textContent = '';

            const canvas = document.getElementById('shareQrCanvas');
            canvas.width = 260;
            canvas.height = 260;

            if (window.QRCode && QRCode.toCanvas) {
                QRCode.toCanvas(canvas, url, { width: 260, margin: 1 }, function (error) {
                    if (error) {
                        msg.textContent = 'Não foi possível gerar o QR Code.';
                    }
                });
            } else {
                msg.textContent = 'QR Code indisponível.';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalCompartilhar'));
            modal.show();
        }

        async function copiarLinkCompartilhar() {
            const input = document.getElementById('shareUrl');
            const msg = document.getElementById('shareMsg');
            try {
                await navigator.clipboard.writeText(input.value);
                msg.textContent = 'Link copiado!';
            } catch (e) {
                input.select();
                document.execCommand('copy');
                msg.textContent = 'Link copiado!';
            }
        }

        function imprimirQr() {
            const url = document.getElementById('shareUrl').value;
            const canvas = document.getElementById('shareQrCanvas');
            const imgData = canvas.toDataURL('image/png');

            const w = window.open('', '_blank');
            if (!w) return;
            w.document.write(`
                <html>
                <head>
                    <title>QR Code - Campanha</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 24px; }
                        .box { text-align: center; }
                        img { width: 320px; height: 320px; }
                        .url { margin-top: 12px; word-break: break-all; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="box">
                        <h2>Campanha de Doação</h2>
                        <img src="${imgData}" alt="QR Code" />
                        <div class="url">${url}</div>
                    </div>
                    <script>window.onload = () => window.print();<\/script>
                </body>
                </html>
            `);
            w.document.close();
        }
    </script>
</body>
</html>
