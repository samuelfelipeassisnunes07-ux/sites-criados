<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/bootstrap.php';
if (!is_local()) throw new RuntimeException('A configuração inicial pelo navegador é exclusiva do ambiente local.');
if ((int)query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()) { echo "Administrador já configurado.\n"; exit; }
$code = bin2hex(random_bytes(16));
file_put_contents(data_dir().'/setup.json', json_encode(['hash'=>hash('sha256',$code),'expires'=>time()+3600],JSON_THROW_ON_ERROR), LOCK_EX);
@chmod(data_dir().'/setup.json',0600);
echo "Código para criar seu primeiro administrador (válido por 1 hora):\n" . $code . "\n";
