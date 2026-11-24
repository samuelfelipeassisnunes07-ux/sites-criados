# Santos FC — versão PHP local

Esta é a versão funcional em PHP do site Alma Alvinegra. O site estático publicado no Sites continua intacto: Sites não executa PHP. Esta pasta é independente e não deve ser enviada como conteúdo estático.

## Iniciar no Windows

1. Abra `iniciar.bat` nesta pasta.
2. A janela mostra um **código de instalação**, válido por uma hora. Mantenha a janela aberta.
3. Abra **http://127.0.0.1:4180/configurar.php**, copie o código e escolha nome, e-mail e senha para seu primeiro administrador.
4. O painel abre após o cadastro. Nas próximas vezes, use **http://127.0.0.1:4180/login.php**.

Não existe senha padrão. Ninguém é promovido automaticamente ao se cadastrar. A configuração inicial é desativada após criar o administrador, só aceita conexões locais e só funciona em `APP_ENV=local`. Para gerar um novo código antes da primeira configuração, reinicie o launcher.

O PHP portátil oficial 8.4.27-1 já está em `runtime/` neste computador, com o SHA-256 conferido na página oficial. Ele não é versionado nem incluído no pacote de distribuição. Não é necessário XAMPP ou Composer. Se mover o projeto, instale PHP 8.4+ e habilite `pdo_sqlite`, `sqlite3` e `mbstring`, ou baixe o ZIP x64 NTS oficial e extraia em `runtime/`. O pacote Windows requer o Visual C++ Redistributable compatível com a versão do PHP.

## Funcionalidades

- Cadastro de torcedores e login por e-mail e senha.
- **Lembrar de mim:** acesso por 30 dias no mesmo navegador. Sem essa opção, cookie de sessão e validade máxima de 8 horas; alguns navegadores podem restaurar cookies de sessão ao reabrir abas.
- Minha conta: atualizar nome, trocar senha e sair de todos os dispositivos.
- Painel de administrador: busca e paginação de usuários, promoção/rebaixamento, bloqueio/reativação, encerramento de sessões e registro de atividades.
- Senhas com `password_hash` / `password_verify`; tokens aleatórios de 256 bits e apenas seus hashes no SQLite. Cookies HttpOnly e SameSite=Lax, Secure quando fora do modo local; CSRF em todos os formulários de alteração, limites de tentativas, consultas preparadas e autorização no servidor.
- Troca de senha, bloqueio, mudança de perfil de acesso e revogação invalidam sessões existentes. Não é possível remover o próprio acesso de administrador pelo painel.

Não há envio de e-mail, recuperação automática de senha ou confirmação do e-mail nesta versão local. Para trocar uma senha conhecida, use Minha conta. Preserve sua senha de administrador em um gerenciador de senhas.

## Dados e privacidade

Contas, hashes de senha, sessões e registros ficam em **`var/santos.sqlite`**, fora da pasta pública. O código de instalação é armazenado apenas como hash em `var/setup.json`, com expiração. `var/` e `runtime/` estão no `.gitignore`. Não publique, compartilhe ou versione `var/`. Para backup, pare o servidor e copie a pasta `var/` inteira para um local protegido.

## Executar com outro PHP

Defina `APP_ENV=local` no ambiente e execute na pasta do projeto:

```text
php bin/init.php
php bin/setup-code.php
php -S 127.0.0.1:4180 -t public router.php
```

Variáveis opcionais: `SANTOS_DATA_DIR` (diretório privado do SQLite, fora da raiz pública) e `SANTOS_TRUST_PROXY=1` (somente se houver proxy HTTPS confiável configurado para sobrescrever X-Forwarded-Proto).

## Futura hospedagem PHP

Use PHP 8.4+, HTTPS, PDO SQLite, mbstring e armazenamento persistente. Configure a raiz do domínio **exatamente como `public/`**, com `index.php` como índice. O processo PHP precisa escrever em `var/`, mas o servidor web não pode servi-la. Faça a configuração inicial no computador local e transfira o banco por um canal seguro, ou use um procedimento administrativo equivalente via CLI. Fora do computador local, remova `APP_ENV=local`; o padrão é produção e exige HTTPS. Nunca use o servidor `php -S` como servidor público. Em hospedagem Apache, mantenha `public/.htaccess`; no Nginx, configure PHP-FPM e restrinja a raiz a `public/`.

O domínio atual `chatgpt.site` não hospeda esta versão PHP. A migração pública exige contratar/configurar um servidor PHP e transferir este projeto com seus dados.

## Validação

`tests/integration.py` usa somente a biblioteca padrão do Python e o PHP portátil local. Cria seu próprio banco temporário, inicia um servidor em loopback, executa testes reais HTTP de autenticação/autorização e encerra o processo. Nenhuma conta de teste é adicionada ao banco de uso real.

```text
python tests/integration.py
```

Fotos e conteúdo histórico mantêm os créditos disponíveis no rodapé do site.
