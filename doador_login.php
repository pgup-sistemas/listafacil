<?php
require_once __DIR__ . '/config.php';

$erro = '';
$next = $_GET['next'] ?? '/index.php';

if (doador_logado()) {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone = trim($_POST['telefone'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $next = $_POST['next'] ?? $next;

    if (empty($telefone) || empty($pin)) {
        $erro = 'Informe telefone e PIN.';
    } else {
        $stmt = $pdo->prepare('SELECT id, pin_hash FROM doadores WHERE telefone = ? LIMIT 1');
        $stmt->execute([$telefone]);
        $doador = $stmt->fetch();

        if (!$doador || empty($doador['pin_hash']) || !password_verify($pin, $doador['pin_hash'])) {
            $erro = 'Telefone ou PIN inválidos.';
        } else {
            $_SESSION['doador_id'] = (int)$doador['id'];

            $token = bin2hex(random_bytes(32));
            $token_hash = password_hash($token, PASSWORD_DEFAULT);
            $expira_em = date('Y-m-d H:i:s', time() + (90 * 24 * 60 * 60));

            $stmt = $pdo->prepare('INSERT INTO doador_sessoes (doador_id, token_hash, expira_em) VALUES (?, ?, ?)');
            $stmt->execute([$_SESSION['doador_id'], $token_hash, $expira_em]);

            setcookie('LF_DOADOR', $token, [
                'expires' => time() + (90 * 24 * 60 * 60),
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            header('Location: ' . $next);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar (Doador) - Listafacil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow border-0" style="border-radius: 20px;">
                    <div class="card-body p-4">
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Identificação</li>
                            </ol>
                        </nav>

                        <div class="text-center mb-4">
                            <i class="bi bi-person-check text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 fw-bold">Entrar como participante</h3>
                            <p class="text-muted">Use seu telefone e PIN</p>
                        </div>

                        <?php if ($erro): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= sanitizar($erro) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="next" value="<?= sanitizar($next) ?>">

                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" name="telefone" class="form-control" required placeholder="(00) 00000-0000">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">PIN</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" name="pin" class="form-control" required placeholder="Seu PIN">
                                </div>
                                <div class="form-text">Você faz isso só na primeira vez. O sistema lembra neste aparelho.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
