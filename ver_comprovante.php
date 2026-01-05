<?php
require_once __DIR__ . '/config.php';

if (!verificar_login()) {
    redirecionar('/login.php');
}

$doacao_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare('SELECT comprovante, comprovante_nome_original, comprovante_mime FROM doacoes WHERE id = ? LIMIT 1');
$stmt->execute([$doacao_id]);
$doacao = $stmt->fetch();

if (!$doacao || empty($doacao['comprovante'])) {
    http_response_code(404);
    die('Comprovante não encontrado.');
}

$arquivo = basename($doacao['comprovante']);
$caminho = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $arquivo;

if (!is_file($caminho)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor.');
}

$mime = $doacao['comprovante_mime'] ?: 'application/octet-stream';
$nome_original = $doacao['comprovante_nome_original'] ?: $arquivo;

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $nome_original) . '"');

readfile($caminho);
exit;
