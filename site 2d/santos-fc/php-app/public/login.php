<?php
require __DIR__.'/../src/bootstrap.php';
if(current_user()) redirect('/conta.php');
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        post_guard();
        rate_limit('login-ip',client_ip(),30,900);
        $email=clean_email(input('email'));
        rate_limit('login-email',$email,8,900);
        $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
        $dummy='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid=password_verify(input('password'),$u['password_hash']??$dummy);
        if(!$u || !$valid || !$u['active']) throw new FormError('E-mail ou senha incorretos, ou conta indisponível.');
        if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) query('UPDATE users SET password_hash=? WHERE id=?',[password_hash(input('password'),PASSWORD_DEFAULT),$u['id']]);
        query('UPDATE users SET last_login_at=? WHERE id=?',[time(),$u['id']]);
        issue_session((int)$u['id'],input('remember')==='1');
        query('DELETE FROM rate_limits WHERE bucket=?',[hash('sha256','login-email:'.$email)]);
        audit((int)$u['id'],'login',(int)$u['id']);
        redirect($u['role']==='admin'?'/admin.php':'/conta.php');
    } catch(FormError $ex) { $error=$ex->getMessage(); }
}
require __DIR__.'/../src/layout.php';page_start('Entrar');
?>
<main class="auth-layout" id="conteudo"><section class="auth-visual"><img src="/assets/fans.webp" alt="Torcida santista na Vila Belmiro"><div><span class="eyebrow">O MANTO NOS UNE</span><h1>SUA PAIXÃO.<br><em>SEU LUGAR.</em></h1><p>O Santos no peito.<br>A sua história por aqui.</p></div><span class="visual-caption">ALMA ALVINEGRA · DESDE 1912</span></section><section class="auth-form"><span class="eyebrow">BEM-VINDO DE VOLTA</span><h2>ENTRE PARA<br>FAZER PARTE.</h2><p>Acesse sua conta alvinegra.</p><?php error_box($error); ?><form method="post" action="/login.php"><?= csrf_field() ?><label for="email">E-mail</label><input type="email" id="email" name="email" autocomplete="username" maxlength="254" required value="<?= e(input('email')) ?>"><?php password_field('password','Senha','current-password'); ?><label class="remember"><input type="checkbox" name="remember" value="1" <?= input('remember')==='1'?'checked':'' ?>><span>Lembrar de mim por 30 dias<small>Use apenas em um dispositivo seu.</small></span></label><button class="button gold submit" type="submit">Entrar <span>↗</span></button></form><p class="switch-auth">Ainda não tem conta? <a href="/cadastro.php">Criar conta</a></p><?php if(setup_available()): ?><a class="setup-link" href="/configurar.php">Primeiro acesso? Configurar administrador ↗</a><?php endif; ?></section></main><?php page_end(); ?>
