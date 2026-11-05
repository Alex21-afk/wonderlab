<?php
// ---------- Salida segura y rutas ----------

function e(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/** URL de un archivo de assets/ con su fecha de modificación, para que el navegador no use una copia vieja. */
function asset_url(string $ruta): string
{
    $archivo = dirname(__DIR__) . '/' . ltrim($ruta, '/');
    return url($ruta) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

function url_absoluta(string $ruta = ''): string
{
    $esquema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($ruta);
}

function redirigir(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Lee un campo de texto de $_POST (o $_GET) sin espacios extra; ignora arreglos. */
function post(string $campo, string $defecto = ''): string
{
    $v = $_POST[$campo] ?? $defecto;
    return is_string($v) ? trim($v) : $defecto;
}

function get(string $campo, string $defecto = ''): string
{
    $v = $_GET[$campo] ?? $defecto;
    return is_string($v) ? trim($v) : $defecto;
}

function responder_json(array $datos, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valido(): bool
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function csrf_verificar(): void
{
    // Si el envío supera post_max_size, PHP vacía $_POST: avisamos en vez de "sesión expirada"
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('danger', 'El archivo es demasiado grande para el servidor (máximo ' . ini_get('post_max_size') . ').');
        redirigir($_SERVER['REQUEST_URI']);
    }
    if (!csrf_valido()) {
        http_response_code(403);
        exit('La sesión expiró. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}

// ---------- Mensajes flash ----------

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [$tipo, $mensaje];
}

function flash_html(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $mensaje]) {
        $html .= '<div class="alert alert-' . e($tipo) . ' alert-dismissible fade show" role="alert">' . e($mensaje)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Configuración del sitio (tabla configuracion) ----------

function &config_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT clave, valor FROM configuracion') as $fila) {
            $cache[$fila['clave']] = $fila['valor'];
        }
    }
    return $cache;
}

function config(string $clave, string $defecto = ''): string
{
    $cache = &config_cache();
    return isset($cache[$clave]) && $cache[$clave] !== '' ? (string)$cache[$clave] : $defecto;
}

function config_set(string $clave, string $valor): void
{
    db()->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$clave, $valor]);
    $cache = &config_cache();
    $cache[$clave] = $valor;
}

/** Color hexadecimal de la configuración; si no es válido, el valor por defecto. */
function config_color(string $clave, string $defecto): string
{
    $color = config($clave, $defecto);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $defecto;
}

/** Bloque <style> con los colores de la marca para las páginas públicas. */
function estilos_marca(): string
{
    return '<style>:root { --primario: ' . config_color('color_primario', '#b76e79')
        . '; --oscuro: ' . config_color('color_secundario', '#2b2024') . '; }</style>';
}

// ---------- Archivos subidos ----------

const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const TIPOS_VIDEO  = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

/**
 * Valida y guarda un archivo de $_FILES. Devuelve la ruta relativa ("uploads/abc.jpg"),
 * null si no se envió nada, o lanza RuntimeException si no es válido.
 */
function guardar_subida(?array $archivo, string $tipo = 'imagen'): ?string
{
    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('El archivo "' . $archivo['name'] . '" supera el tamaño permitido por el servidor.');
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se pudo subir el archivo "' . $archivo['name'] . '".');
    }

    $permitidos = $tipo === 'video' ? TIPOS_VIDEO : TIPOS_IMAGEN;
    $maxMb = $tipo === 'video' ? MAX_VIDEO_MB : MAX_IMAGEN_MB;
    if ($archivo['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException('El archivo "' . $archivo['name'] . "\" supera los {$maxMb} MB.");
    }

    // Se valida el contenido real del archivo, no la extensión que envía el navegador
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset($permitidos[$mime]) || ($tipo === 'imagen' && @getimagesize($archivo['tmp_name']) === false)) {
        $formatos = strtoupper(implode(', ', array_unique($permitidos)));
        throw new RuntimeException('"' . $archivo['name'] . "\" no es un formato válido. Usa: $formatos.");
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], UPLOAD_DIR . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }
    return 'uploads/' . $nombre;
}

function subir_archivo(string $campo, string $tipo = 'imagen'): ?string
{
    return guardar_subida($_FILES[$campo] ?? null, $tipo);
}

/** Convierte un input <input type="file" multiple> en una lista de archivos individuales. */
function archivos_multiples(string $campo): array
{
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'])) {
        return [];
    }
    $lista = [];
    foreach (array_keys($f['name']) as $i) {
        $lista[] = [
            'name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
        ];
    }
    return $lista;
}

function borrar_archivo(?string $ruta): void
{
    if ($ruta && str_starts_with($ruta, 'uploads/')) {
        $archivo = UPLOAD_DIR . '/' . basename($ruta);
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}

// ---------- Formatos ----------

const DIAS_SEMANA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

const ESTADOS_CITA = [
    'pendiente'  => ['Pendiente', 'warning', '#a86a12'],
    'confirmada' => ['Confirmada', 'success', '#3f7a5a'],
    'completada' => ['Completada', 'primary', '#3f6488'],
    'no_asistio' => ['No asistió', 'danger', '#b0413a'],
    'cancelada'  => ['Cancelada', 'secondary', '#857a7c'],
];

function estado_badge(string $estado): string
{
    [$texto, , $color] = ESTADOS_CITA[$estado] ?? [$estado, 'secondary', '#857a7c'];
    return '<span class="estado" style="--estado: ' . $color . '">' . e($texto) . '</span>';
}

function fecha_humana(string $fecha, bool $conHora = true): string
{
    $d = new DateTime($fecha);
    $texto = DIAS_SEMANA[(int)$d->format('w')] . ' ' . $d->format('j') . ' de ' . MESES[(int)$d->format('n') - 1];
    if ($d->format('Y') !== date('Y')) {
        $texto .= ' de ' . $d->format('Y');
    }
    return $conHora ? $texto . ', ' . hora12($d->format('H:i')) : $texto;
}

function hora12(string $hora): string
{
    return date('g:i a', strtotime($hora));
}

function dinero($valor): string
{
    $valor = (float)$valor;
    $decimales = floor($valor) == $valor ? 0 : 2;
    return config('moneda', '$') . ' ' . number_format($valor, $decimales, ',', '.');
}

function duracion_texto(int $minutos): string
{
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    return trim(($h ? "$h h " : '') . ($m ? "$m min" : ''));
}

// ---------- Enlaces externos ----------

function telefono_internacional(string $numero): string
{
    $digitos = preg_replace('/\D+/', '', $numero);
    $codigo = preg_replace('/\D+/', '', config('codigo_pais'));
    // Si escribieron el número local (sin código de país), se lo agregamos
    if ($digitos !== '' && $codigo !== '' && !str_starts_with(ltrim($numero), '+') && !str_starts_with($digitos, $codigo)) {
        $digitos = $codigo . $digitos;
    }
    return $digitos;
}

function whatsapp_url(string $numero, string $mensaje = ''): string
{
    $digitos = telefono_internacional($numero);
    if ($digitos === '') {
        return '';
    }
    return 'https://wa.me/' . $digitos . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

/** Acepta la URL de "Insertar mapa" de Google Maps o el <iframe> completo; devuelve solo un src seguro. */
function mapa_src(string $entrada): string
{
    if (preg_match('~src="([^"]+)"~', $entrada, $m)) {
        $entrada = html_entity_decode($m[1]);
    }
    $entrada = trim($entrada);
    return str_starts_with($entrada, 'https://www.google.com/maps/embed') ? $entrada : '';
}

function es_url_web(string $url): bool
{
    return (bool)preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}
