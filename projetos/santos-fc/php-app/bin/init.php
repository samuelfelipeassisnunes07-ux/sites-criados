<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/bootstrap.php';
if (!extension_loaded('pdo_sqlite') || !extension_loaded('mbstring')) throw new RuntimeException('Habilite pdo_sqlite e mbstring.');
if (!is_dir(data_dir())) mkdir(data_dir(),0700,true);
$pdo = new PDO('sqlite:' . data_dir() . '/santos.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
$version=(int)$pdo->query('PRAGMA user_version')->fetchColumn();
if ($version === 0) {
    $pdo->beginTransaction();
    $pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,password_hash TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user','admin')),active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),created_at INTEGER NOT NULL,last_login_at INTEGER);
CREATE TABLE sessions(token_hash TEXT PRIMARY KEY,user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,csrf_token TEXT NOT NULL,persistent INTEGER NOT NULL DEFAULT 0,created_at INTEGER NOT NULL,expires_at INTEGER NOT NULL);
CREATE INDEX sessions_user ON sessions(user_id);
CREATE INDEX sessions_expiry ON sessions(expires_at);
CREATE TABLE rate_limits(bucket TEXT PRIMARY KEY,hits INTEGER NOT NULL,reset_at INTEGER NOT NULL);
CREATE TABLE audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id INTEGER REFERENCES users(id),action TEXT NOT NULL,target_id INTEGER REFERENCES users(id),created_at INTEGER NOT NULL);
PRAGMA user_version=1;");
    $pdo->commit();
}
if ($version > 1) throw new RuntimeException('Versão de banco incompatível.');
@chmod(data_dir() . '/santos.sqlite',0600);
echo "Banco de dados pronto.\n";
