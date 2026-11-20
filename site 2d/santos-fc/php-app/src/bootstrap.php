<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const COOKIE_NAME = 'santos_session';
const REMEMBER_SECONDS = 30 * 86400;

class FormError extends RuntimeException {}

function data_dir(): string {
    return getenv('SANTOS_DATA_DIR') ?: APP_ROOT . '/var';
}
function is_local(): bool { return getenv('APP_ENV') === 'local'; }
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (getenv('SANTOS_TRUST_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        if (!is_file(data_dir() . '/santos.sqlite')) throw new RuntimeException('Execute bin/init.php antes de iniciar.');
        $pdo = new PDO('sqlite:' . data_dir() . '/santos.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function transaction(callable $work): mixed {
    db()->exec('BEGIN IMMEDIATE');
    try { $result = $work(); db()->exec('COMMIT'); return $result; }
    catch (Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
}
function set_auth_cookie(string $token, bool $remember): void {
    setcookie(COOKIE_NAME, $token, ['expires' => $remember ? time() + REMEMBER_SECONDS : 0, 'path' => '/', 'secure' => !is_local() || is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[COOKIE_NAME] = $token;
}
function clear_auth_cookie(): void {
    setcookie(COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => !is_local() || is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[COOKIE_NAME]);
}
function current_session(): ?array {
    $token = $_COOKIE[COOKIE_NAME] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
    $s = query('SELECT * FROM sessions WHERE token_hash = ? AND expires_at > ?', [hash('sha256', $token), time()])->fetch();
    return $s ?: null;
}
function issue_session(?int $userId, bool $remember = false): array {
    $old = current_session();
    if ($old) query('DELETE FROM sessions WHERE token_hash = ?', [$old['token_hash']]);
    $token = bin2hex(random_bytes(32));
    $csrf = bin2hex(random_bytes(32));
    $lifetime = $userId === null ? 3600 : ($remember ? REMEMBER_SECONDS : 8 * 3600);
    query('INSERT INTO sessions(token_hash,user_id,csrf_token,persistent,created_at,expires_at) VALUES(?,?,?,?,?,?)', [hash('sha256', $token), $userId, $csrf, (int)$remember, time(), time() + $lifetime]);
    set_auth_cookie($token, $remember);
    return current_session();
}
function csrf_token(): string { return (current_session() ?? issue_session(null))['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function post_guard(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new FormError('Método inválido.');
    $s = current_session();
    $token = $_POST['csrf'] ?? null;
    if (!$s || !is_string($token) || !hash_equals($s['csrf_token'], $token)) {
        http_response_code(403); throw new FormError('Sua sessão expirou. Atualize a página e tente novamente.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $expected = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== null && $origin !== $expected) { http_response_code(403); throw new FormError('Origem da solicitação inválida.'); }
}
function current_user(): ?array {
    $s = current_session();
    if (!$s || !$s['user_id']) return null;
    $u = query('SELECT id,name,email,role,active,created_at,last_login_at FROM users WHERE id=? AND active=1', [$s['user_id']])->fetch();
    if (!$u) { query('DELETE FROM sessions WHERE token_hash=?', [$s['token_hash']]); clear_auth_cookie(); return null; }
    return $u;
}
function require_user(): array {
    $u = current_user();
    if (!$u) redirect('/login.php');
    return $u;
}
function require_admin(): array {
    $u = require_user();
    if ($u['role'] !== 'admin') { http_response_code(403); require APP_ROOT . '/src/forbidden.php'; exit; }
    return $u;
}
function input(string $key): string { $v = $_POST[$key] ?? ''; return is_string($v) ? $v : ''; }
function clean_email(string $value): string {
    $v = strtolower(trim($value));
    if (strlen($v) > 254 || !filter_var($v, FILTER_VALIDATE_EMAIL)) throw new FormError('Informe um e-mail válido.');
    return $v;
}
function clean_name(string $value): string {
    $v = trim($value);
    if (mb_strlen($v) < 2 || mb_strlen($v) > 80) throw new FormError('Use de 2 a 80 caracteres para o nome.');
    return $v;
}
function validate_password(string $password, string $confirmation): void {
    if (mb_strlen($password) < 12 || strlen($password) > 72) throw new FormError('Use uma senha com pelo menos 12 caracteres e no máximo 72 bytes.');
    if ($password !== $confirmation) throw new FormError('A confirmação da senha não confere.');
}
function rate_limit(string $scope, string $key, int $limit, int $seconds): void {
    $bucket = hash('sha256', $scope . ':' . $key);
    transaction(function () use ($bucket, $limit, $seconds) {
        $r = query('SELECT * FROM rate_limits WHERE bucket=?', [$bucket])->fetch();
        if (!$r || $r['reset_at'] <= time()) {
            query('INSERT INTO rate_limits(bucket,hits,reset_at) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET hits=1,reset_at=excluded.reset_at', [$bucket, time()+$seconds]);
        } elseif ($r['hits'] >= $limit) { http_response_code(429); header('Retry-After: ' . max(1, $r['reset_at'] - time())); throw new FormError('Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.'); }
        else query('UPDATE rate_limits SET hits=hits+1 WHERE bucket=?', [$bucket]);
    });
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }
function audit(?int $actor, string $action, ?int $target = null): void {
    query('INSERT INTO audit_log(actor_id,action,target_id,created_at) VALUES(?,?,?,?)', [$actor,$action,$target,time()]);
}
function verify_current_password(int $userId, string $password): void {
    rate_limit('confirm-password', (string)$userId, 10, 900);
    $u = query('SELECT password_hash FROM users WHERE id=? AND active=1',[$userId])->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) throw new FormError('A senha atual não confere.');
}
function setup_available(): bool {
    return is_local() && in_array(client_ip(), ['127.0.0.1','::1'],true)
        && !(int)query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
}
function format_date(?int $time): string { return $time ? date('d/m/Y H:i', $time) : 'Ainda não acessou'; }

date_default_timezone_set('America/Sao_Paulo');
set_exception_handler(function(Throwable $error): void {
    error_log('Santos: ' . $error->getMessage());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Não foi possível concluir. Consulte a configuração e o log do PHP.\n"); exit(1); }
    http_response_code(500);
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Temporariamente indisponível</title><h1>Não foi possível concluir agora.</h1><p>Tente novamente em instantes.</p></html>';
});
if (PHP_SAPI !== 'cli') {
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
    if (!is_local() && !is_https()) { http_response_code(400); exit('Esta instalação exige HTTPS. Para uso local, execute iniciar.bat.'); }
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    if (random_int(1, 100) === 1) {
        query('DELETE FROM sessions WHERE expires_at < ?', [time()]);
        query('DELETE FROM rate_limits WHERE reset_at < ?', [time()]);
    }
}
