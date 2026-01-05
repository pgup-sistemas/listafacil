<?php
session_start();

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'listafacil');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações gerais (auto-detect base URL)
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('SITE_URL', $__scheme . '://' . $__host . ($__script_dir ? $__script_dir : ''));

define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Conexão com banco de dados
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

function gerar_token($tamanho = 32) {
    return bin2hex(random_bytes($tamanho / 2));
}

function verificar_login() {
    return isset($_SESSION['usuario_id']);
}

function doador_logado() {
    return isset($_SESSION['doador_id']);
}

function carregar_sessao_doador_por_cookie(PDO $pdo) {
    if (doador_logado()) {
        return;
    }

    if (empty($_COOKIE['LF_DOADOR'])) {
        return;
    }

    $token = (string)$_COOKIE['LF_DOADOR'];
    if (strlen($token) < 20) {
        return;
    }

    $stmt = $pdo->query('SELECT id, doador_id, token_hash, expira_em FROM doador_sessoes');
    $sessoes = $stmt->fetchAll();
    $agora = time();

    foreach ($sessoes as $sessao) {
        if (!empty($sessao['expira_em']) && strtotime($sessao['expira_em']) < $agora) {
            continue;
        }

        if (password_verify($token, $sessao['token_hash'])) {
            $_SESSION['doador_id'] = (int)$sessao['doador_id'];
            return;
        }
    }
}

function obter_doador(PDO $pdo) {
    if (!doador_logado()) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, nome, telefone, email FROM doadores WHERE id = ?');
    $stmt->execute([$_SESSION['doador_id']]);
    return $stmt->fetch() ?: null;
}

function exigir_doador_login() {
    $next = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: ' . SITE_URL . '/doador_login.php?next=' . urlencode($next));
    exit;
}

function campanha_tem_grupos(PDO $pdo, $campanha_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = ?');
    $stmt->execute([(int)$campanha_id]);
    return (int)$stmt->fetchColumn() > 0;
}

function doador_tem_acesso_campanha(PDO $pdo, $doador_id, $campanha_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = ?');
    $stmt->execute([(int)$campanha_id]);
    $qtd = (int)$stmt->fetchColumn();

    if ($qtd === 0) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM campanha_grupos cg
        JOIN grupo_membros gm ON gm.grupo_id = cg.grupo_id
        WHERE cg.campanha_id = ? AND gm.doador_id = ?
    ");
    $stmt->execute([(int)$campanha_id, (int)$doador_id]);
    return (int)$stmt->fetchColumn() > 0;
}

function verificar_admin() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

function redirecionar($url) {
    if (preg_match('/^https?:\/\//i', $url)) {
        header('Location: ' . $url);
        exit;
    }

    $path = '/' . ltrim($url, '/');
    header('Location: ' . SITE_URL . $path);
    exit;
}

function formatar_moeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function parse_moeda_br($str) {
    if ($str === null) {
        return null;
    }

    $s = trim((string)$str);
    if ($s === '') {
        return null;
    }

    $s = preg_replace('/[^0-9,\.\-]/', '', $s);

    $hasComma = strpos($s, ',') !== false;
    $hasDot = strpos($s, '.') !== false;

    if ($hasComma && $hasDot) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($hasComma && !$hasDot) {
        $s = str_replace(',', '.', $s);
    }

    if ($s === '' || $s === '-' || $s === '.') {
        return null;
    }

    return (float)$s;
}

function formatar_data($data) {
    return date('d/m/Y', strtotime($data));
}

function formatar_data_hora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function sanitizar($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

carregar_sessao_doador_por_cookie($pdo);
?>
