<?php require __DIR__.'/../src/bootstrap.php'; $user=current_user(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#101110">
  <meta name="description" content="Uma homenagem visual ao Santos FC. Explore a Vila Belmiro, os ídolos e as histórias que fizeram o mundo vestir preto e branco.">
  <title>Santos FC — Alma Alvinegra</title>
  <link rel="icon" href="assets/crest.png"><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="auth.css">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="app.js" defer></script>
</head>
<body>
<a class="skip" href="#historia">Pular para o conteúdo</a>
<header class="header">
  <a class="brand" href="#inicio" aria-label="Santos FC, início"><img src="assets/crest.png" alt="Escudo do Santos FC" width="42" height="51"><span>SANTOS FC<small>ALMA ALVINEGRA</small></span></a>
  <nav aria-label="Menu principal" id="navigation"><a href="#historia">Nossa história</a><a href="#idolos">Ídolos eternos</a><a href="#vila">A Vila</a><a href="#galeria">Galeria</a></nav>
  <div class="header-actions site-account-actions"><a class="account-link" href="<?= $user ? '/conta.php' : '/login.php' ?>"><?= $user ? 'Minha conta' : 'Entrar' ?> ↗</a><button id="menu" class="menu" aria-label="Abrir menu" aria-expanded="false" aria-controls="navigation">☰</button></div>
</header>
<main>
<section class="hero" id="inicio" aria-labelledby="hero-title">
  <img class="hero-photo" src="assets/stadium.webp" alt="Vista da Vila Belmiro, casa do Santos Futebol Clube" fetchpriority="high">
  <div class="hero-shade"></div><div class="hero-lines" aria-hidden="true"></div><div class="particles" aria-hidden="true"></div>
  <div class="hero-content"><div class="eyebrow"><span></span> SANTOS, BRASIL · DESDE 1912</div>
    <h1 id="hero-title">MUITO ALÉM<br>DO <em>FUTEBOL.</em></h1>
    <p>Um manto. Milhões de histórias.<br>Uma paixão que não cabe em quatro linhas.</p>
    <div class="hero-buttons"><a class="button gold" href="#historia">Explore nossa história <span>↗</span></a><a class="text-link" href="#galeria"><span class="play">▧</span> Sinta essa paixão</a></div>
  </div>
  <div class="crest-scene" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><img class="hero-crest" src="assets/crest.png" alt=""><span class="crest-caption">UM LEGADO SEM LIMITES</span></div>
  <div class="hero-bottom"><a href="#historia"><span class="scroll-icon">↓</span> ROLE PARA DESCOBRIR</a><span>O ALVINEGRO MAIS FAMOSO DO MUNDO <i>✦</i></span><span class="hero-index">01 <span>/</span> 05</span></div>
</section>
<div class="marquee" aria-hidden="true"><div><span>MENINOS DA VILA</span><i>✦</i><span>FUTEBOL ARTE</span><i>✦</i><span>ORGULHO ALVINEGRO</span><i>✦</i><span>SANTOS SEMPRE SANTOS</span><i>✦</i><span>MENINOS DA VILA</span><i>✦</i><span>FUTEBOL ARTE</span><i>✦</i><span>ORGULHO ALVINEGRO</span><i>✦</i><span>SANTOS SEMPRE SANTOS</span><i>✦</i></div></div>
<section class="intro section" id="historia">
  <div class="section-top reveal"><span class="eyebrow dark">01 / NOSSA ESSÊNCIA</span><span class="small-note">UMA CIDADE. UM CLUBE. O MUNDO.</span></div>
  <div class="intro-heading reveal"><h2>NASCEU NA VILA.<br><span>CONQUISTOU O MUNDO.</span></h2><p>Do litoral para a eternidade. O Santos é o encontro entre o talento que encanta, a coragem de ser diferente e uma torcida que nunca deixa de acreditar.</p></div>
  <div class="stats reveal"><div><strong>1912</strong><span>O início de tudo</span></div><div><strong>02<span>★</span></strong><span>Mundiais Interclubes</span></div><div><strong>03</strong><span>Copas Libertadores</span></div><div class="stat-word"><strong>∞</strong><span>Razões para ser Santos</span></div></div>
</section>
<section class="legends section" id="idolos">
  <div class="section-top reveal"><span class="eyebrow">02 / QUEM VESTIU O MANTO</span><span class="small-note">O TALENTO TEM ENDEREÇO.</span></div>
  <div class="section-heading reveal"><h2>GIGANTES.<br><span>PARA SEMPRE.</span></h2><p>Alguns jogam futebol.<br>Outros mudam a história dele.</p></div>
  <div class="legend-grid">
    <button class="legend-card tilt reveal" data-story="pele"><img src="assets/pele.webp" alt="Pelé, ídolo eterno do Santos" loading="lazy"><span class="card-number">10</span><span class="legend-bottom"><span class="eyebrow">O REI DO FUTEBOL</span><strong>PELÉ</strong><span>O eterno camisa 10. <i>↗</i></span></span></button>
    <button class="legend-card tilt reveal" data-story="neymar"><img src="assets/neymar.webp" alt="Neymar com a camisa do Santos" loading="lazy"><span class="card-number">11</span><span class="legend-bottom"><span class="eyebrow">OUSADIA QUE VEM DO BERÇO</span><strong>NEYMAR JR.</strong><span>A alegria de ser Menino da Vila. <i>↗</i></span></span></button>
    <button class="legend-card tilt reveal" data-story="vila"><img src="assets/match.webp" alt="Categorias de base do Santos em campo" loading="lazy"><span class="card-number">∞</span><span class="legend-bottom"><span class="eyebrow">O FUTURO SEMPRE COMEÇA AQUI</span><strong>MENINOS<br>DA VILA</strong><span>Uma escola de futebol arte. <i>↗</i></span></span></button>
  </div>
</section>
<section class="history section">
  <div class="section-top reveal"><span class="eyebrow dark">03 / MEMÓRIAS QUE VIVEM</span><span class="small-note">REVIVA CADA CAPÍTULO.</span></div>
  <div class="history-head reveal"><h2>O TEMPO PASSA.<br><span>A GRANDEZA FICA.</span></h2><span class="history-label">EXPLORE OS CAPÍTULOS ↓</span></div>
  <div class="years reveal" role="tablist" aria-label="Capítulos da história"><button role="tab" id="tab-1912" aria-controls="chapter" aria-selected="true" data-year="1912">1912<span>O começo</span></button><button role="tab" id="tab-1962" aria-controls="chapter" aria-selected="false" tabindex="-1" data-year="1962">1962<span>O mundo</span></button><button role="tab" id="tab-1963" aria-controls="chapter" aria-selected="false" tabindex="-1" data-year="1963">1963<span>De novo</span></button><button role="tab" id="tab-2011" aria-controls="chapter" aria-selected="false" tabindex="-1" data-year="2011">2011<span>A América</span></button></div>
  <div class="chapter reveal" id="chapter" role="tabpanel" aria-labelledby="tab-1912" tabindex="0"><div class="chapter-photo"><img id="chapter-image" src="assets/stadium.webp" alt="A Vila Belmiro, símbolo da história santista" loading="lazy"><span id="chapter-image-note">VILA BELMIRO · A CASA DESSA HISTÓRIA</span></div><div class="chapter-copy" aria-live="polite"><span class="eyebrow dark" id="chapter-kicker">14 DE ABRIL DE 1912</span><h3 id="chapter-title">Uma paixão<br>ganha nome.</h3><p id="chapter-description">Na cidade de Santos, nasce um clube que levaria o futebol brasileiro aos quatro cantos do planeta. Um começo no litoral. Uma história sem fronteiras.</p><a class="text-link dark-link" href="https://www.santosfc.com.br/fundacao/" target="_blank" rel="noopener">Conheça a história do clube <span>↗</span></a></div></div>
</section>
<section class="vila" id="vila"><img src="assets/stadium.webp" alt="Arquibancadas e gramado da Vila Belmiro" loading="lazy"><div class="vila-shade"></div><div class="vila-copy reveal"><span class="eyebrow">23°57′ S · 46°20′ O</span><h2>TEM LUGAR<br>QUE É <em>SENTIMENTO.</em></h2><p>Vila Belmiro. O palco dos nossos sonhos.<br>Onde cada pedaço de arquibancada tem uma história para contar.</p><a class="button white" href="https://www.santosfc.com.br/vila-belmiro/" target="_blank" rel="noopener">Conheça a nossa casa <span>↗</span></a></div><span class="vila-side">ESTÁDIO URBANO CALDEIRA · DESDE 1916</span></section>
<section class="gallery section" id="galeria"><div class="section-top reveal"><span class="eyebrow dark">04 / UM OLHAR ALVINEGRO</span><span class="small-note">CLIQUE. AMPLIE. SINTA.</span></div><div class="gallery-heading reveal"><h2>O SANTOS.<br><span>EM CADA DETALHE.</span></h2><div class="filters" role="group" aria-label="Filtrar fotos"><button class="active" aria-pressed="true" data-filter="all">Tudo</button><button aria-pressed="false" data-filter="vila">A Vila</button><button aria-pressed="false" data-filter="idolos">Ídolos</button><button aria-pressed="false" data-filter="campo">Em campo</button></div></div>
  <div class="gallery-grid">
    <button class="gallery-item wide reveal" data-category="vila" data-photo="stadium.webp" data-caption="Vila Belmiro. Muito mais que um estádio."><img src="assets/stadium.webp" alt="Vista da Vila Belmiro" loading="lazy"><span><small>NOSSA CASA</small>O coração do alvinegro <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="idolos" data-photo="pele.webp" data-caption="Pelé. A realeza veste preto e branco."><img src="assets/pele.webp" alt="Pelé, o Rei do Futebol" loading="lazy"><span><small>ETERNO REI</small>Um legado imortal <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="idolos" data-photo="neymar.webp" data-caption="Neymar Jr. O talento que nasceu na Vila."><img src="assets/neymar.webp" alt="Neymar pelo Santos" loading="lazy"><span><small>DNA SANTISTA</small>Futebol com alegria <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="campo" data-photo="match.webp" data-caption="O Santos em campo. O manto que une gerações."><img src="assets/match.webp" alt="Jogadores das categorias de base do Santos em campo" loading="lazy"><span><small>O NOSSO MANTO</small>Orgulho em campo <i>↗</i></span></button>
    <button class="gallery-item wide reveal" data-category="vila" data-photo="fans.webp" data-caption="A força que vem da arquibancada."><img src="assets/fans.webp" alt="Torcida do Santos na arquibancada" loading="lazy"><span><small>PAIXÃO QUE PULSA</small>Juntos em qualquer lugar <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="vila" data-photo="memorial.webp" data-caption="Memorial das Conquistas. A grandeza guardada em cada taça."><img src="assets/memorial.webp" alt="Troféus no Memorial das Conquistas" loading="lazy"><span><small>NOSSAS GLÓRIAS</small>História que brilha <i>↗</i></span></button>
    <button class="gallery-item wide reveal" data-category="vila" data-photo="fireworks.webp" data-caption="Uma noite de homenagem a Pelé na Vila Belmiro."><img src="assets/fireworks.webp" alt="Fogos de artifício em homenagem a Pelé na Vila Belmiro" loading="lazy"><span><small>A VILA EM FESTA</small>Uma paixão que ilumina <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="vila" data-photo="vila.webp" data-caption="A fachada histórica do Estádio Urbano Caldeira."><img src="assets/vila.webp" alt="Fachada histórica da Vila Belmiro" loading="lazy"><span><small>AS PORTAS DA HISTÓRIA</small>A nossa casa <i>↗</i></span></button>
    <button class="gallery-item reveal" data-category="vila" data-photo="stadium-city.webp" data-caption="Santos e a Vila Belmiro. Uma cidade de alma alvinegra."><img src="assets/stadium-city.webp" alt="Vista aérea da Vila Belmiro e da cidade de Santos" loading="lazy"><span><small>DO LITORAL PARA O MUNDO</small>Santos vista de cima <i>↗</i></span></button>
  </div>
</section>
<section class="closing section"><span class="eyebrow reveal">05 / A HISTÓRIA CONTINUA COM VOCÊ</span><h2 class="reveal">NÃO SE EXPLICA.<br><em>SE SENTE.</em></h2><div class="closing-bottom reveal"><p>Preto no branco.<br>Santos no peito.</p><a class="button gold" href="https://www.santosfc.com.br/" target="_blank" rel="noopener">Continue no universo Santos <span>↗</span></a><a href="#inicio" class="back-top" aria-label="Voltar ao início">↑</a></div><span class="closing-bg" aria-hidden="true">SFC</span></section>
</main>
<footer><a class="brand" href="#inicio"><img src="assets/crest.png" alt="" width="34" height="41"><span>SANTOS FC<small>ALMA ALVINEGRA</small></span></a><p>Feito de paixão. Projeto independente de torcedor.<br>Sem vínculo oficial com o Santos Futebol Clube.</p><button class="credits-button" id="credits">Fontes e créditos ↗</button><span class="footer-year">DESDE 1912. PARA SEMPRE.</span></footer>
<dialog id="lightbox" class="lightbox" aria-label="Galeria de fotos ampliadas"><button class="dialog-close" aria-label="Fechar galeria">✕</button><button class="photo-prev" aria-label="Foto anterior">←</button><figure><img id="lightbox-image" alt=""><figcaption id="lightbox-caption"></figcaption></figure><button class="photo-next" aria-label="Próxima foto">→</button><span id="photo-counter"></span></dialog>
<dialog id="story-dialog" class="story-dialog" aria-labelledby="story-title"><button class="dialog-close" aria-label="Fechar">✕</button><div id="story-content"></div></dialog>
</body>
</html>
