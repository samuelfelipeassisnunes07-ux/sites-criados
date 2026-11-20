<?php
require __DIR__.'/../src/bootstrap.php';
if(current_user()) redirect('/conta.php');
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        post_guard();rate_limit('register',client_ip(),8,3600);
        $name=clean_name(input('name'));$email=clean_email(input('email'));validate_password(input('password'),input('confirmation'));
        $hash=password_hash(input('password'),PASSWORD_DEFAULT);
        try { query("INSERT INTO users(name,email,password_hash,role,created_at,last_login_at) VALUES(?,?,?,'user',?,?)",[$name,$email,$hash,time(),time()]); }
        catch(PDOException $ex) { if($ex->getCode()==='23000')throw new FormError('Não foi possível cadastrar esse e-mail. Se já tem conta, entre com sua senha.');throw $ex; }
        $id=(int)db()->lastInsertId();audit($id,'cadastro',$id);issue_session($id,false);redirect('/conta.php?welcome=1');
    }catch(FormError $ex){$error=$ex->getMessage();}
}
require __DIR__.'/../src/layout.php';page_start('Criar conta');
?>
<main class="auth-layout" id="conteudo"><section class="auth-visual"><img src="/assets/stadium-city.webp" alt="Santos e a Vila Belmiro vistos do alto"><div><span class="eyebrow">UMA PAIXÃO, MUITAS HISTÓRIAS</span><h1>O SEU LUGAR<br><em>É AQUI.</em></h1><p>Preto e branco.<br>Juntos, sempre Santos.</p></div><span class="visual-caption">SANTOS, BRASIL · ALMA ALVINEGRA</span></section><section class="auth-form"><span class="eyebrow">FAÇA PARTE</span><h2>CRIE SUA<br>CONTA ALVINEGRA.</h2><p>Preencha seus dados para começar.</p><?php error_box($error); ?><form method="post" action="/cadastro.php"><?= csrf_field() ?><label for="name">Seu nome</label><input id="name" name="name" autocomplete="name" minlength="2" maxlength="80" required value="<?= e(input('name')) ?>"><label for="email">E-mail</label><input type="email" id="email" name="email" autocomplete="username" maxlength="254" required value="<?= e(input('email')) ?>"><?php password_field('password','Senha'); ?><small class="field-hint">Use pelo menos 12 caracteres.</small><?php password_field('confirmation','Confirmar senha'); ?><button class="button gold submit" type="submit">Criar minha conta <span>↗</span></button></form><p class="switch-auth">Já tem uma conta? <a href="/login.php">Entrar</a></p></section></main><?php page_end(); ?>
