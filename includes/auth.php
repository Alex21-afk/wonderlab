<?php
const MAX_INTENTOS_LOGIN = 5;      // intentos fallidos permitidos...
const MINUTOS_BLOQUEO_LOGIN = 15;  // ...en esta ventana de tiempo
const HORAS_SESION = 8;            // inactividad máxima del panel

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function hay_usuarios(): bool
{
    return (int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
}

function iniciar_sesion(array $usuario): void
{
    session_regenerate_id(true); // evita fijación de sesión
    $_SESSION['usuario'] = ['id' => (int)$usuario['id'], 'nombre' => $usuario['nombre'], 'email' => $usuario['email']];
    $_SESSION['ultima_actividad'] = time();
    unset($_SESSION['csrf']);
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requiere_login(): void
{
    if (!usuario_actual()) {
        redirigir(url('admin/login.php'));
    }
    if (time() - ($_SESSION['ultima_actividad'] ?? 0) > HORAS_SESION * 3600) {
        cerrar_sesion();
        redirigir(url('admin/login.php?expirada=1'));
    }
    $_SESSION['ultima_actividad'] = time();
}

/** Devuelve true si inició sesión, o un mensaje de error. */
function intentar_login(string $email, string $password): true|string
{
    $pdo = db();
    $pdo->prepare('DELETE FROM intentos_login WHERE creado_en < NOW() - INTERVAL 1 DAY')->execute();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND creado_en > NOW() - INTERVAL ? MINUTE');
    $stmt->execute([ip_cliente(), MINUTOS_BLOQUEO_LOGIN]);
    if ((int)$stmt->fetchColumn() >= MAX_INTENTOS_LOGIN) {
        return 'Demasiados intentos fallidos. Espera ' . MINUTOS_BLOQUEO_LOGIN . ' minutos e inténtalo de nuevo.';
    }

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        $pdo->prepare('INSERT INTO intentos_login (ip, creado_en) VALUES (?, NOW())')->execute([ip_cliente()]);
        return 'Correo o contraseña incorrectos.';
    }

    if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $usuario['id']]);
    }
    $pdo->prepare('DELETE FROM intentos_login WHERE ip = ?')->execute([ip_cliente()]);
    iniciar_sesion($usuario);
    return true;
}

function validar_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'La contraseña debe combinar letras y números.';
    }
    return null;
}
