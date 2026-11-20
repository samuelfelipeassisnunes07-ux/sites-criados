<?php
require __DIR__.'/../src/bootstrap.php';
if(!setup_available()){http_response_code(404);exit('Página não encontrada.');}
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        post_guard();rate_limit('setup',client_ip(),10,900);
        $setup=is_file(data_dir().'/setup.json')?json_decode(file_get_contents(data_dir().'/setup.json'),true):null;
        if(!$setup || $setup['expires']<time() || !hash_equals($setup['hash'],hash('sha256',trim(input('code'))))) throw new FormError('Código inválido ou expirado. Execute iniciar.bat para gerar um novo código.');
        $name=clean_name(input('name'));$email=clean_email(input('email'));validate_password(input('password'),input('confirmation'));
        $hash=password_hash(input('password'),PASSWORD_DEFAULT);
        $id=transaction(function()use($name,$email,$hash){
            if((int)query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn())throw new FormError('O administrador já foi configurado.');
            if(query('SELECT id FROM users WHERE email=?',[$email])->fetch())throw new FormError('Esse e-mail já está cadastrado. Use outro e-mail para o primeiro administrador.');
            query("INSERT INTO users(name,email,password_hash,role,created_at,last_login_at) VALUES(?,?,?,'admin',?,?)",[$name,$email,$hash,time(),time()]);
            $id=(int)db()->lastInsertId();audit($id,'primeiro_admin',$id);return $id;
        });
        unlink(data_dir().'/setup.json');issue_session($id,false);redirect('/admin.php?ready=1');
    }catch(FormError $ex){$error=$ex->getMessage();}
}
require __DIR__.'/../src/layout.php';page_start('Primeiro administrador');
?>
<main class="account-main narrow" id="conteudo"><div class="panel"><span class="eyebrow">CONFIGURAÇÃO INICIAL · APENAS NESTE COMPUTADOR</span><h1>VOCÊ NO<br><em>COMANDO.</em></h1><p>Crie a primeira conta de administrador. Esta configuração será encerrada assim que a conta for criada.</p><?php error_box($error); ?><form method="post" action="/configurar.php"><?= csrf_field() ?><label for="code">Código de instalação</label><input name="code" id="code" required autocomplete="off" spellcheck="false" maxlength="64"><small class="field-hint">Copie o código exibido na janela do iniciar.bat.</small><label for="name">Nome do administrador</label><input name="name" id="name" autocomplete="name" required minlength="2" maxlength="80" value="<?= e(input('name')) ?>"><label for="email">E-mail</label><input name="email" id="email" type="email" autocomplete="username" required maxlength="254" value="<?= e(input('email')) ?>"><?php password_field('password','Senha'); ?><small class="field-hint">Escolha uma senha com pelo menos 12 caracteres.</small><?php password_field('confirmation','Confirmar senha'); ?><button class="button gold submit">Criar administrador <span>↗</span></button></form></div></main><?php page_end(); ?>
