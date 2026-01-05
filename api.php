<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

function gerar_token_publico() {
    return bin2hex(random_bytes(32));
}

try {
    switch ($action) {
        case 'criar_doacao':
            $nome = $_POST['nome'] ?? '';
            $telefone = $_POST['telefone'] ?? null;
            $email = $_POST['email'] ?? null;
            $campanha_id = $_POST['campanha_id'] ?? 0;

            if (empty($campanha_id)) {
                throw new Exception('Dados incompletos');
            }

            // Se estiver logado como doador, o nome vem do cadastro
            if (doador_logado()) {
                $d = obter_doador($pdo);
                $nome = $d['nome'] ?? $nome;
            }

            if (empty($nome)) {
                throw new Exception('Informe seu nome');
            }

            // Se a campanha estiver vinculada a grupos, o doador precisa estar logado
            if (campanha_tem_grupos($pdo, $campanha_id) && !doador_logado()) {
                throw new Exception('Você precisa se identificar (telefone/PIN) para participar desta campanha.');
            }

            if (doador_logado()) {
                $doador_id = (int)$_SESSION['doador_id'];

                // Se houver grupos na campanha, valida se o doador pertence
                if (!doador_tem_acesso_campanha($pdo, $doador_id, $campanha_id)) {
                    throw new Exception('Seu acesso não está liberado para esta campanha.');
                }
            } else {
                // Modo público antigo: cria/vincula pelo nome
                $stmt = $pdo->prepare('SELECT id FROM doadores WHERE nome = ? LIMIT 1');
                $stmt->execute([$nome]);
                $doador = $stmt->fetch();

                if ($doador) {
                    $doador_id = $doador['id'];
                } else {
                    $stmt = $pdo->prepare('INSERT INTO doadores (nome, telefone, email) VALUES (?, ?, ?)');
                    $stmt->execute([$nome, $telefone, $email]);
                    $doador_id = $pdo->lastInsertId();
                }
            }

            $tipo_doacao = $_POST['tipo_doacao'] ?? '';
            if (empty($tipo_doacao)) {
                $tipo_doacao = !empty($_POST['valor']) ? 'dinheiro' : 'item';
            }

            $valor = $tipo_doacao === 'dinheiro' ? parse_moeda_br($_POST['valor'] ?? 0) : null;
            $item_descricao = $tipo_doacao === 'item' ? ($_POST['item_descricao'] ?? '') : null;
            $quantidade = $tipo_doacao === 'item' ? ($_POST['quantidade'] ?? 1) : 1;
            $observacao = $_POST['observacao'] ?? null;
            $forma_pagamento = $_POST['forma_pagamento'] ?? 'pix';
            if (!in_array($forma_pagamento, ['pix', 'dinheiro'], true)) {
                $forma_pagamento = 'pix';
            }

            $token_publico = gerar_token_publico();

            $stmt = $pdo->prepare("
                INSERT INTO doacoes (campanha_id, doador_id, tipo, forma_pagamento, valor, item_descricao, quantidade, observacao, token_publico)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $campanha_id,
                $doador_id,
                $tipo_doacao,
                $forma_pagamento,
                $valor,
                $item_descricao,
                $quantidade,
                $observacao,
                $token_publico
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Doação registrada com sucesso!',
                'token_publico' => $token_publico
            ]);
            break;

        case 'solicitar_confirmacao_dinheiro':
            if (!doador_logado()) {
                throw new Exception('Você precisa se identificar (telefone/PIN).');
            }

            $doacao_id = (int)($_POST['doacao_id'] ?? 0);
            if ($doacao_id <= 0) {
                throw new Exception('Dados incompletos');
            }

            $stmt = $pdo->prepare('SELECT id, doador_id, status FROM doacoes WHERE id = ? LIMIT 1');
            $stmt->execute([$doacao_id]);
            $doacao = $stmt->fetch();
            if (!$doacao) {
                throw new Exception('Doação não encontrada');
            }

            if ((int)$doacao['doador_id'] !== (int)$_SESSION['doador_id']) {
                throw new Exception('Não autorizado');
            }

            if (in_array($doacao['status'], ['pago', 'entregue', 'cancelado'], true)) {
                throw new Exception('Esta doação não pode ser alterada.');
            }

            $stmt = $pdo->prepare("UPDATE doacoes SET forma_pagamento = 'dinheiro', status = 'aguardando_confirmacao' WHERE id = ?");
            $stmt->execute([$doacao_id]);

            echo json_encode(['success' => true, 'message' => 'Solicitação enviada. Aguarde a confirmação do administrador.']);
            break;

        case 'enviar_comprovante':
            $token_publico = $_POST['token_publico'] ?? '';
            if (empty($token_publico)) {
                throw new Exception('Token inválido');
            }

            if (!isset($_FILES['comprovante'])) {
                throw new Exception('Nenhum arquivo enviado');
            }

            $file = $_FILES['comprovante'];
            if (!empty($file['error'])) {
                throw new Exception('Erro no upload');
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande (máx. 5MB)');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf'
            ];
            if (!isset($allowed[$mime])) {
                throw new Exception('Tipo de arquivo não permitido (JPG, PNG, PDF)');
            }

            if (!is_dir(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0755, true)) {
                    throw new Exception('Falha ao criar diretório de uploads');
                }
            }

            $stmt = $pdo->prepare('SELECT id FROM doacoes WHERE token_publico = ? LIMIT 1');
            $stmt->execute([$token_publico]);
            $doacao = $stmt->fetch();
            if (!$doacao) {
                throw new Exception('Doação não encontrada');
            }

            $ext = $allowed[$mime];
            $nome_original = $file['name'] ?? '';
            $nome_arquivo = 'comprovante_' . $token_publico . '_' . time() . '.' . $ext;
            $destino = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $nome_arquivo;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                throw new Exception('Falha ao salvar arquivo');
            }

            $stmt = $pdo->prepare("
                UPDATE doacoes
                SET comprovante = ?,
                    comprovante_nome_original = ?,
                    comprovante_mime = ?,
                    comprovante_enviado_em = NOW(),
                    status = 'aguardando_confirmacao'
                WHERE id = ?
            ");
            $stmt->execute([$nome_arquivo, $nome_original, $mime, $doacao['id']]);

            echo json_encode(['success' => true, 'message' => 'Comprovante enviado com sucesso!']);
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

            $stmt = $pdo->prepare('UPDATE doacoes SET status = ?, data_confirmacao = NOW() WHERE id = ?');
            $stmt->execute([$status, $doacao_id]);

            echo json_encode(['success' => true, 'message' => 'Status atualizado']);
            break;

        case 'excluir_doacao':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }

            $doacao_id = $_POST['doacao_id'] ?? 0;

            $stmt = $pdo->prepare('DELETE FROM doacoes WHERE id = ?');
            $stmt->execute([$doacao_id]);

            echo json_encode(['success' => true, 'message' => 'Doação excluída']);
            break;

        case 'alterar_status_campanha':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }

            $campanha_id = $_POST['campanha_id'] ?? 0;
            $status = $_POST['status'] ?? '';

            $stmt = $pdo->prepare('UPDATE campanhas SET status = ? WHERE id = ?');
            $stmt->execute([$status, $campanha_id]);

            echo json_encode(['success' => true, 'message' => 'Status da campanha atualizado']);
            break;

        case 'confirmar_pagamento_doacao':
            if (!verificar_login()) {
                throw new Exception('Não autorizado');
            }

            $doacao_id = $_POST['doacao_id'] ?? 0;
            if (empty($doacao_id)) {
                throw new Exception('Dados incompletos');
            }

            $stmt = $pdo->prepare("UPDATE doacoes SET status = 'pago', data_confirmacao = NOW() WHERE id = ?");
            $stmt->execute([$doacao_id]);

            echo json_encode(['success' => true, 'message' => 'Pagamento confirmado']);
            break;

        default:
            throw new Exception('Ação inválida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
