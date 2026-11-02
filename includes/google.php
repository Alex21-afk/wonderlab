<?php
// Cliente mínimo de Google Calendar API usando cURL (sin Composer)

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_API_BASE  = 'https://www.googleapis.com/calendar/v3';
const GOOGLE_SCOPE     = 'https://www.googleapis.com/auth/calendar.events';

function google_http(string $method, string $url, ?array $body = null, ?string $token = null, bool $form = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL de PHP no está activa en Apache.');
    }
    $headers = [];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init($url);
    if ($body !== null) {
        $headers[] = $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form ? http_build_query($body) : json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new RuntimeException('Error de conexión con Google: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = $resp === '' ? [] : (json_decode($resp, true) ?? []);
    if ($status >= 400) {
        $msg = $data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? $resp;
        throw new RuntimeException("Google ($status): " . (is_string($msg) ? $msg : json_encode($msg)), $status);
    }
    return $data;
}

// ---------- OAuth ----------

function google_configurado(): bool
{
    return GOOGLE_CLIENT_ID !== '' && !str_contains(GOOGLE_CLIENT_ID, 'PEGA_AQUI');
}

function google_auth_url(): string
{
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    return GOOGLE_AUTH_URL . '?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => GOOGLE_SCOPE,
        'access_type'   => 'offline',  // para recibir refresh_token
        'prompt'        => 'consent',
        'state'         => $_SESSION['oauth_state'],
    ]);
}

function google_exchange_code(string $code): void
{
    $tok = google_http('POST', GOOGLE_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ], null, true);
    google_save_token($tok);
}

function google_load_token(): ?array
{
    $row = db()->query('SELECT * FROM google_tokens WHERE id = 1')->fetch();
    return $row ?: null;
}

function google_save_token(array $tok): void
{
    $actual = google_load_token();
    // Google solo envía refresh_token la primera vez; conservamos el anterior si no viene
    $refresh = $tok['refresh_token'] ?? ($actual['refresh_token'] ?? null);
    db()->prepare('REPLACE INTO google_tokens (id, access_token, refresh_token, expires_at) VALUES (1, ?, ?, ?)')
        ->execute([$tok['access_token'], $refresh, time() + (int)($tok['expires_in'] ?? 3600)]);
}

function google_disconnect(): void
{
    $tok = google_load_token();
    if ($tok) {
        try {
            google_http('POST', 'https://oauth2.googleapis.com/revoke', ['token' => $tok['refresh_token'] ?: $tok['access_token']], null, true);
        } catch (RuntimeException $e) {
            // si ya estaba revocado no importa
        }
    }
    db()->exec('DELETE FROM google_tokens');
}

/** Devuelve un access token válido (lo renueva si expiró) o null si no hay conexión. */
function google_access_token(): ?string
{
    $tok = google_load_token();
    if (!$tok) {
        return null;
    }
    if ($tok['expires_at'] > time() + 60) {
        return $tok['access_token'];
    }
    if (empty($tok['refresh_token'])) {
        return null;
    }
    $nuevo = google_http('POST', GOOGLE_TOKEN_URL, [
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $tok['refresh_token'],
        'grant_type'    => 'refresh_token',
    ], null, true);
    google_save_token($nuevo);
    return $nuevo['access_token'];
}

function google_connected(): bool
{
    static $conectado = null;
    return $conectado ??= google_load_token() !== null;
}

// ---------- Eventos ----------

function google_events_url(string $sufijo = ''): string
{
    return GOOGLE_API_BASE . '/calendars/' . rawurlencode(GOOGLE_CALENDAR_ID) . '/events' . $sufijo;
}

function cita_a_google(array $c): array
{
    $descripcion = "Cliente: {$c['cliente_nombre']}\nTeléfono: {$c['cliente_telefono']}";
    if ($c['cliente_email']) {
        $descripcion .= "\nCorreo: {$c['cliente_email']}";
    }
    if ($c['notas']) {
        $descripcion .= "\nNotas del cliente: {$c['notas']}";
    }
    $descripcion .= "\nEstado: " . ESTADOS_CITA[$c['estado']][0];

    $evento = [
        'summary'     => $c['servicio_nombre'] . ' – ' . $c['cliente_nombre'],
        'description' => $descripcion,
        'location'    => config('direccion'),
        'start'       => ['dateTime' => str_replace(' ', 'T', $c['inicio']), 'timeZone' => APP_TIMEZONE],
        'end'         => ['dateTime' => str_replace(' ', 'T', $c['fin']), 'timeZone' => APP_TIMEZONE],
        'colorId'     => $c['estado'] === 'pendiente' ? '5' : '10', // amarillo / verde
        // Marca para reconocer nuestros eventos y no contarlos dos veces como "ocupado"
        'extendedProperties' => ['private' => ['wonderlab_cita' => (string)$c['id']]],
    ];
    if (config('google_invitar') === '1' && $c['cliente_email']) {
        $evento['attendees'] = [['email' => $c['cliente_email'], 'displayName' => $c['cliente_nombre']]];
    }
    return $evento;
}

function google_parametros_envio(array $cita): string
{
    // Si se invita al cliente, Google le envía el correo de invitación/cambio/cancelación
    return config('google_invitar') === '1' && $cita['cliente_email'] ? '?sendUpdates=all' : '';
}

/**
 * Crea, actualiza o elimina el evento de Google de una cita según su estado.
 * Devuelve un mensaje de error o null si todo salió bien (o si Google no está conectado).
 */
function google_sincronizar_cita(int $id): ?string
{
    if (!google_connected()) {
        return null;
    }
    $c = cita_obtener($id);
    if (!$c) {
        return null;
    }
    try {
        $token = google_access_token();
        $params = google_parametros_envio($c);
        if ($c['estado'] === 'cancelada') {
            if ($c['google_event_id']) {
                google_eliminar_evento($c['google_event_id'], $params);
                db()->prepare('UPDATE citas SET google_event_id = NULL WHERE id = ?')->execute([$id]);
            }
            return null;
        }
        $evento = cita_a_google($c);
        if ($c['google_event_id']) {
            try {
                google_http('PATCH', google_events_url('/' . rawurlencode($c['google_event_id']) . $params), $evento, $token);
                return null;
            } catch (RuntimeException $e) {
                if (!in_array($e->getCode(), [404, 410], true)) {
                    throw $e;
                }
                // lo borraron en Google: se vuelve a crear
            }
        }
        $g = google_http('POST', google_events_url($params), $evento, $token);
        db()->prepare('UPDATE citas SET google_event_id = ? WHERE id = ?')->execute([$g['id'], $id]);
        return null;
    } catch (Throwable $e) {
        error_log('Google Calendar, cita ' . $id . ': ' . $e->getMessage());
        return 'No se pudo actualizar Google Calendar: ' . $e->getMessage();
    }
}

function google_eliminar_evento(string $googleId, string $params = ''): void
{
    try {
        google_http('DELETE', google_events_url('/' . rawurlencode($googleId) . $params), null, google_access_token());
    } catch (RuntimeException $e) {
        if (!in_array($e->getCode(), [404, 410], true)) { // ya eliminado en Google
            throw $e;
        }
    }
}

/** Lista eventos de Google entre dos fechas (RFC3339). */
function google_listar_eventos(string $desde, string $hasta): array
{
    $eventos = [];
    $pageToken = null;
    do {
        $params = [
            'timeMin'      => $desde,
            'timeMax'      => $hasta,
            'singleEvents' => 'true', // expande eventos recurrentes
            'maxResults'   => 250,
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        $resp = google_http('GET', google_events_url('?' . http_build_query($params)), null, google_access_token());
        $eventos = array_merge($eventos, $resp['items'] ?? []);
        $pageToken = $resp['nextPageToken'] ?? null;
    } while ($pageToken);
    return $eventos;
}

/**
 * Intervalos [inicio, fin] (timestamps) ocupados en Google Calendar que NO son citas de este sistema.
 * Respeta "Mostrar como: Disponible" (transparent), así los eventos de todo el día informativos no bloquean.
 */
function google_ocupados(int $desde, int $hasta): array
{
    $ocupados = [];
    foreach (google_listar_eventos(date(DATE_RFC3339, $desde), date(DATE_RFC3339, $hasta)) as $ev) {
        if (($ev['status'] ?? '') === 'cancelled'
            || ($ev['transparency'] ?? '') === 'transparent'
            || isset($ev['extendedProperties']['private']['wonderlab_cita'])
            || empty($ev['start'])) {
            continue;
        }
        $ini = strtotime($ev['start']['dateTime'] ?? $ev['start']['date']);
        $fin = strtotime($ev['end']['dateTime'] ?? $ev['end']['date']);
        $ocupados[] = [$ini, $fin];
    }
    return $ocupados;
}
