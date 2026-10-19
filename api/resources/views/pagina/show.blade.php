@php
    $titulo = $empresa->nome.($empresa->slogan ? ' · '.$empresa->slogan : '');
    $descricao = $empresa->descricao
        ? \Illuminate\Support\Str::limit(strip_tags($empresa->descricao), 155)
        : 'Agende seu horário online na '.$empresa->nome.'.';
    $imagem = $empresa->capa_url ?? $empresa->logo_url ?? $fotoCapa;
    $whatsapp = $empresa->whatsapp ? 'https://wa.me/55'.$empresa->whatsapp : null;
    $mapa = $endereco ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($endereco) : null;
    $preco = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $duracao = fn (int $m) => $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? sprintf('%02d', $m % 60) : '') : $m.' min';
    $menorPreco = $servicos->min(fn ($s) => (float) $s->preco);
    // Último nome vira destaque no título: "Barbearia <em>Demo</em>"
    $palavras = explode(' ', $empresa->nome);
    $destaque = count($palavras) > 1 ? array_pop($palavras) : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descricao }}">
    <link rel="canonical" href="{{ url($empresa->slug) }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $empresa->nome }}">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ url($empresa->slug) }}">
    <meta property="og:image" content="{{ $imagem }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0d0d0e">
    @if ($empresa->logo_url)<link rel="icon" href="{{ $empresa->logo_url }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,500;1,700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ $fotoCapa }}">
    <style>
        :root {
            --acento: {{ $empresa->cor_primaria }};
            --acento-texto: {{ $corTexto }};
            --acento-claro: {{ $acentoClaro }};
            --ouro: #c8a165;
            --escuro: #0d0d0e;
            --escuro-2: #17171a;
            --creme: #f4efe6;
            --creme-2: #e9e1d3;
            --texto: #1b1a18;
            --suave: #6d675e;
            --claro: #f5f1ea;
            --claro-suave: rgba(245, 241, 234, .66);
            --borda: #ddd3c2;
            --erro: #b42318;
            --raio: 16px;
            --display: "Bebas Neue", Impact, "Arial Narrow", sans-serif;
            --serif: "Playfair Display", Georgia, serif;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        html, body { overflow-x: hidden; }
        body { margin: 0; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--escuro); color: var(--claro); line-height: 1.55; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        .conteudo { max-width: 1120px; margin: 0 auto; padding: 0 20px; }
        [hidden] { display: none !important; }

        /* ---------- Botões ---------- */
        .botao { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border: 0; border-radius: 999px; padding: 16px 30px; font: inherit; font-weight: 700; letter-spacing: .02em; cursor: pointer; text-decoration: none; background: var(--acento); color: var(--acento-texto); overflow: hidden; transition: transform .2s, box-shadow .2s, filter .2s; }
        .botao::after { content: ""; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%); transform: translateX(-120%); transition: transform .7s; }
        .botao:hover { transform: translateY(-2px); box-shadow: 0 12px 30px -10px var(--acento); }
        .botao:hover::after { transform: translateX(120%); }
        .botao:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .botao-ouro { background: var(--ouro); color: #1b1408; }
        .botao-ouro:hover { box-shadow: 0 12px 30px -10px var(--ouro); }
        .botao-vazado { background: transparent; color: var(--claro); box-shadow: inset 0 0 0 1.5px rgba(245,241,234,.5); }
        .botao-vazado:hover { box-shadow: inset 0 0 0 1.5px var(--claro); }
        .botao-claro { background: #fff; color: var(--texto); box-shadow: inset 0 0 0 1px var(--borda); }
        .botao-whats { background: #25d366; color: #fff; }
        .botao svg { width: 20px; height: 20px; flex: none; }

        /* ---------- Cabeçalho fixo ---------- */
        .barra { position: fixed; inset: 0 0 auto; z-index: 30; transition: background .3s, backdrop-filter .3s, padding .3s; padding: 18px 0; }
        .barra.solida { background: rgba(13,13,14,.82); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); padding: 10px 0; box-shadow: 0 1px 0 rgba(255,255,255,.06); }
        .barra .conteudo { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .marca { display: flex; align-items: center; gap: 10px; text-decoration: none; font-family: var(--display); font-size: 1.6rem; letter-spacing: .04em; line-height: 1; }
        .marca img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .menu { display: flex; gap: 26px; align-items: center; font-size: .92rem; font-weight: 500; }
        .menu a { text-decoration: none; opacity: .8; transition: opacity .2s; }
        .menu a:hover { opacity: 1; }
        .menu .botao { padding: 10px 20px; opacity: 1; }
        @media (max-width: 820px) { .menu a:not(.botao) { display: none; } }

        /* ---------- Topo ---------- */
        .hero { position: relative; min-height: 100svh; display: flex; align-items: center; overflow: hidden; isolation: isolate; }
        .hero-foto { position: absolute; inset: -4%; z-index: -2; background: center/cover no-repeat; animation: zoom 22s ease-in-out infinite alternate; will-change: transform; }
        .hero::before { content: ""; position: absolute; inset: 0; z-index: -1; background:
            radial-gradient(ellipse at 20% 50%, rgba(13,13,14,.25), rgba(13,13,14,.85) 70%),
            linear-gradient(180deg, rgba(13,13,14,.55) 0%, rgba(13,13,14,.35) 40%, var(--escuro) 100%); }
        .hero .conteudo { width: 100%; padding-top: 120px; padding-bottom: 120px; }
        .selo { display: inline-flex; align-items: center; gap: 10px; padding: 8px 16px; border-radius: 999px; background: rgba(245,241,234,.08); border: 1px solid rgba(245,241,234,.18); backdrop-filter: blur(6px); font-size: .82rem; letter-spacing: .14em; text-transform: uppercase; }
        .selo i { width: 8px; height: 8px; border-radius: 50%; background: #3ddc84; box-shadow: 0 0 0 0 rgba(61,220,132,.7); animation: pulso 2s infinite; }
        .hero h1 { font-family: var(--display); font-weight: 400; font-size: clamp(4.4rem, 15vw, 9.5rem); line-height: .9; letter-spacing: .01em; margin: 22px 0 18px; text-transform: uppercase; }
        .hero h1 .linha { display: block; overflow: hidden; }
        .hero h1 .linha span { display: inline-block; animation: subir 1s cubic-bezier(.2,.8,.2,1) both; }
        .hero h1 .linha:nth-child(2) span { animation-delay: .15s; }
        .hero h1 em { font-family: var(--serif); font-style: italic; font-weight: 700; text-transform: none; color: var(--ouro); font-size: .78em; letter-spacing: 0; }
        .hero p.frase { font-size: clamp(1.05rem, 2.4vw, 1.35rem); max-width: 560px; color: var(--claro-suave); margin: 0 0 34px; animation: aparecer 1s .45s both; }
        .hero .acoes-hero { display: flex; flex-wrap: wrap; gap: 14px; animation: aparecer 1s .6s both; }
        .rolar { position: absolute; left: 50%; bottom: 28px; transform: translateX(-50%); width: 26px; height: 42px; border: 2px solid rgba(245,241,234,.5); border-radius: 14px; }
        .rolar::after { content: ""; position: absolute; left: 50%; top: 8px; width: 4px; height: 8px; margin-left: -2px; border-radius: 2px; background: var(--claro); animation: rolar 1.8s infinite; }

        /* Poste de barbeiro animado */
        .poste { position: absolute; right: max(20px, calc((100vw - 1120px) / 2 + 20px)); top: calc(50% - 150px); width: 44px; height: 300px; animation: aparecer 1.2s .8s both; }
        .poste .tubo { position: absolute; inset: 22px 6px; border-radius: 18px; overflow: hidden; background: repeating-linear-gradient(-45deg, #c62828 0 16px, #f5f1ea 16px 32px, #1e4fa8 32px 48px, #f5f1ea 48px 64px); background-size: 100% 90.5px; animation: girar 1.6s linear infinite; box-shadow: inset 8px 0 14px rgba(0,0,0,.35), inset -6px 0 10px rgba(255,255,255,.25); }
        .poste::before, .poste::after { content: ""; position: absolute; left: 0; right: 0; height: 26px; border-radius: 12px; background: linear-gradient(90deg, #8a7350, #e6cf9e, #8a7350); }
        .poste::before { top: 0; }
        .poste::after { bottom: 0; }
        @media (max-width: 820px) { .poste { display: none; } }

        /* ---------- Faixa rolante ---------- */
        .faixa { background: var(--ouro); color: #1b1408; overflow: hidden; white-space: nowrap; transform: rotate(-1.5deg) scale(1.03); margin: -24px 0 0; position: relative; z-index: 2; box-shadow: 0 20px 40px -20px rgba(0,0,0,.6); }
        .faixa .trilho { display: inline-flex; animation: correr 32s linear infinite; padding: 16px 0; }
        .faixa span { font-family: var(--display); font-size: 1.9rem; letter-spacing: .06em; padding: 0 22px; display: inline-flex; align-items: center; gap: 44px; }
        .faixa span::after { content: "✂"; font-size: 1.3rem; }

        /* ---------- Seções ---------- */
        section { padding: 110px 0; position: relative; scroll-margin-top: 64px; }
        .claro { background: var(--creme); color: var(--texto); }
        .rotulo { display: inline-flex; align-items: center; gap: 12px; font-size: .78rem; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: var(--ouro); margin-bottom: 14px; }
        .rotulo::before { content: ""; width: 34px; height: 2px; background: currentColor; }
        .claro .rotulo { color: #9a7438; }
        h2 { font-family: var(--display); font-weight: 400; font-size: clamp(2.6rem, 7vw, 4.6rem); line-height: .95; margin: 0 0 22px; letter-spacing: .01em; }
        h2 em { font-family: var(--serif); font-style: italic; font-weight: 500; color: var(--ouro); font-size: .82em; }
        .claro h2 em { color: #9a7438; }
        .intro { max-width: 560px; color: var(--claro-suave); font-size: 1.08rem; margin: 0 0 48px; }
        .claro .intro { color: var(--suave); }

        /* Sobre */
        .sobre { display: grid; grid-template-columns: 1.05fr 1fr; gap: 70px; align-items: center; }
        .sobre-fotos { position: relative; aspect-ratio: 4/5; }
        .sobre-fotos img { position: absolute; object-fit: cover; border-radius: 22px; box-shadow: 0 30px 60px -25px rgba(0,0,0,.45); }
        .sobre-fotos img:first-child { inset: 0 18% 14% 0; width: 82%; height: 86%; }
        .sobre-fotos img:last-child { right: 0; bottom: 0; width: 52%; height: 50%; border: 8px solid var(--creme); }
        .sobre-fotos .carimbo { position: absolute; left: -18px; bottom: 8%; width: 128px; height: 128px; border-radius: 50%; background: var(--escuro); color: var(--ouro); display: grid; place-items: center; animation: girar-lento 18s linear infinite; }
        .carimbo svg { width: 100%; height: 100%; }
        .carimbo b { position: absolute; font-size: 1.8rem; color: var(--claro); animation: girar-lento 18s linear infinite reverse; }
        .sobre p.texto { white-space: pre-line; color: #3d3a35; font-size: 1.08rem; margin: 0 0 30px; }
        .numeros { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding-top: 28px; border-top: 1px solid var(--borda); }
        .numeros strong { display: block; font-family: var(--display); font-size: 2.8rem; line-height: 1; color: var(--texto); font-weight: 400; }
        .numeros span { font-size: .85rem; color: var(--suave); }
        @media (max-width: 860px) {
            .sobre { grid-template-columns: 1fr; gap: 56px; }
            .sobre-fotos { max-width: 460px; width: 100%; margin: 0 auto; }
            .sobre-fotos .carimbo { left: -6px; width: 104px; height: 104px; }
        }

        /* Serviços — cardápio */
        .servicos { background: radial-gradient(ellipse at top right, rgba(200,161,101,.12), transparent 55%), var(--escuro); }
        .cardapio { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 64px; }
        .item { display: block; padding: 22px 0; border-bottom: 1px dashed rgba(245,241,234,.16); text-decoration: none; transition: padding .25s, background .25s; }
        .item:hover { padding-left: 12px; }
        .item .linha-item { display: flex; align-items: baseline; gap: 14px; }
        .item h3 { margin: 0; font-size: 1.22rem; font-weight: 600; }
        .item .pontos { flex: 1; border-bottom: 2px dotted rgba(200,161,101,.45); transform: translateY(-5px); }
        .item .valor { font-family: var(--display); font-size: 1.9rem; color: var(--ouro); line-height: 1; }
        .item p { margin: 6px 0 0; font-size: .92rem; color: var(--claro-suave); }
        .item .tempo { display: inline-flex; align-items: center; gap: 6px; }
        .item .tempo::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--acento-claro); }
        @media (max-width: 760px) { .cardapio { grid-template-columns: 1fr; } }

        /* Equipe */
        .equipe { display: grid; gap: 26px; grid-template-columns: repeat(auto-fill, minmax(min(230px, 100%), 1fr)); }
        .barbeiro { position: relative; border-radius: 22px; overflow: hidden; aspect-ratio: 3/4; background: linear-gradient(160deg, #2a2723, #121111); color: var(--claro); text-decoration: none; display: block; }
        .barbeiro img { width: 100%; height: 100%; object-fit: cover; transition: transform .7s cubic-bezier(.2,.8,.2,1), filter .5s; filter: grayscale(.35); }
        .barbeiro:hover img { transform: scale(1.07); filter: grayscale(0); }
        .barbeiro .inicial { position: absolute; inset: 0; display: grid; place-items: center; font-family: var(--display); font-size: 8rem; color: rgba(200,161,101,.35); }
        .barbeiro .info { position: absolute; inset: auto 0 0; padding: 70px 22px 22px; background: linear-gradient(180deg, transparent, rgba(0,0,0,.85)); }
        .barbeiro strong { display: block; font-family: var(--display); font-size: 2rem; font-weight: 400; letter-spacing: .03em; line-height: 1; }
        .barbeiro small { color: var(--ouro); font-weight: 500; }
        .barbeiro .agendar-com { position: absolute; top: 16px; right: 16px; padding: 8px 14px; border-radius: 999px; background: var(--ouro); color: #1b1408; font-size: .8rem; font-weight: 700; opacity: 0; transform: translateY(-8px); transition: .3s; }
        .barbeiro:hover .agendar-com { opacity: 1; transform: none; }

        /* Galeria */
        .galeria { columns: 3 260px; column-gap: 16px; }
        .galeria button { display: block; width: 100%; margin: 0 0 16px; padding: 0; border: 0; border-radius: 18px; overflow: hidden; cursor: zoom-in; background: #222; break-inside: avoid; position: relative; }
        .galeria img { width: 100%; transition: transform .8s cubic-bezier(.2,.8,.2,1), filter .5s; filter: saturate(.85); }
        .galeria button:hover img { transform: scale(1.06); filter: saturate(1.1); }
        .galeria button::after { content: "＋"; position: absolute; inset: 0; display: grid; place-items: center; font-size: 2.4rem; color: #fff; background: rgba(0,0,0,.35); opacity: 0; transition: opacity .3s; }
        .galeria button:hover::after { opacity: 1; }
        .galeria .legenda { position: absolute; left: 12px; bottom: 12px; padding: 4px 12px; border-radius: 999px; background: rgba(0,0,0,.6); color: #fff; font-size: .82rem; z-index: 1; }
        .lightbox { position: fixed; inset: 0; z-index: 50; background: rgba(5,5,6,.94); display: grid; place-items: center; padding: 20px; opacity: 0; pointer-events: none; transition: opacity .3s; }
        .lightbox.aberto { opacity: 1; pointer-events: auto; }
        .lightbox img { max-height: 86vh; border-radius: 14px; transform: scale(.94); transition: transform .4s; }
        .lightbox.aberto img { transform: none; }
        .lightbox button { position: absolute; background: rgba(255,255,255,.1); color: #fff; border: 0; width: 50px; height: 50px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; }
        .lightbox .fechar { top: 18px; right: 18px; }
        .lightbox .ant { left: 18px; top: 50%; }
        .lightbox .prox { right: 18px; top: 50%; }

        /* ---------- Agendamento ---------- */
        .agendar { background: linear-gradient(180deg, var(--escuro) 0 180px, var(--creme) 180px); padding-top: 0; }
        .cartao-agenda { background: #fff; color: var(--texto); border-radius: 28px; padding: clamp(24px, 5vw, 56px); box-shadow: 0 40px 80px -40px rgba(0,0,0,.55); position: relative; overflow: hidden; }
        .cartao-agenda::before { content: ""; position: absolute; inset: 0 0 auto; height: 6px; background: repeating-linear-gradient(-45deg, #c62828 0 12px, #fff 12px 24px, #1e4fa8 24px 36px, #fff 36px 48px); background-size: 68px 100%; animation: correr-poste 2s linear infinite; }
        .cabeca-agenda { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .cabeca-agenda h2 { margin: 0; color: var(--texto); }
        .cabeca-agenda h2 em { color: #9a7438; }
        .cabeca-agenda p { margin: 0; color: var(--suave); max-width: 320px; }
        .passos { display: flex; gap: 8px; margin-bottom: 30px; }
        .passos span { flex: 1; height: 5px; border-radius: 5px; background: var(--creme-2); position: relative; overflow: hidden; }
        .passos span::after { content: ""; position: absolute; inset: 0; background: var(--acento); transform: scaleX(0); transform-origin: left; transition: transform .5s cubic-bezier(.2,.8,.2,1); }
        .passos span.feito::after { transform: scaleX(1); }
        .etapa { animation: entrar .45s cubic-bezier(.2,.8,.2,1); }
        .etapa h3 { margin: 0 0 4px; font-size: 1.35rem; }
        .etapa > p { margin: 0 0 20px; color: var(--suave); }
        .opcoes { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr)); }
        .opcao { display: flex; align-items: center; gap: 14px; padding: 16px; border: 2px solid var(--creme-2); border-radius: var(--raio); background: #fff; cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%; transition: border-color .2s, transform .2s, box-shadow .2s; }
        .opcao:hover { border-color: #c9bda8; transform: translateY(-2px); box-shadow: 0 10px 24px -14px rgba(0,0,0,.3); }
        .opcao.sel { border-color: var(--acento); box-shadow: 0 0 0 1px var(--acento), 0 10px 24px -14px var(--acento); }
        .opcao input { width: 22px; height: 22px; accent-color: var(--acento); flex: none; }
        .opcao .texto { flex: 1; }
        .opcao strong { display: block; }
        .opcao small { color: var(--suave); display: block; }
        .foto.mini { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; flex: none; background: var(--escuro); color: var(--ouro); display: grid; place-items: center; font-family: var(--display); font-size: 1.5rem; }
        .dias { display: flex; gap: 10px; overflow-x: auto; padding: 4px 2px 10px; scroll-snap-type: x mandatory; scrollbar-width: thin; }
        .dia { flex: none; width: 70px; padding: 12px 0; border: 2px solid var(--creme-2); border-radius: var(--raio); background: #fff; font: inherit; cursor: pointer; text-align: center; scroll-snap-align: start; color: inherit; transition: .2s; }
        .dia:hover { border-color: #c9bda8; }
        .dia small { display: block; font-size: .72rem; color: var(--suave); text-transform: uppercase; letter-spacing: .06em; }
        .dia strong { font-family: var(--display); font-size: 1.9rem; font-weight: 400; line-height: 1.1; }
        .dia.sel { border-color: var(--escuro); background: var(--escuro); color: var(--claro); transform: translateY(-3px); }
        .dia.sel small { color: var(--ouro); }
        .outra-data { margin: 10px 0 22px; display: flex; align-items: center; gap: 10px; color: var(--suave); font-size: .95rem; }
        .outra-data input { padding: 8px 10px; border: 1px solid var(--borda); border-radius: 10px; font: inherit; color: inherit; background: #fff; }
        .horarios { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); }
        .hora { padding: 12px 0; border: 2px solid var(--creme-2); border-radius: 12px; background: #fff; font: inherit; font-weight: 700; cursor: pointer; color: inherit; transition: .2s; animation: entrar .4s both; }
        .hora:hover { border-color: var(--acento); background: var(--acento); color: var(--acento-texto); transform: translateY(-2px); }
        .aviso { padding: 22px; border-radius: var(--raio); background: var(--creme); color: var(--suave); text-align: center; }
        .campo { display: block; margin-bottom: 16px; }
        .campo span { display: block; font-weight: 600; margin-bottom: 6px; }
        .campo input, .campo textarea { width: 100%; padding: 14px; border: 1.5px solid var(--borda); border-radius: 12px; font: inherit; background: #fff; color: inherit; transition: border-color .2s, box-shadow .2s; }
        .campo input:focus, .campo textarea:focus { outline: 0; border-color: var(--acento); box-shadow: 0 0 0 4px color-mix(in srgb, var(--acento) 18%, transparent); }
        .check { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px; cursor: pointer; }
        .check input { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--acento); flex: none; }
        .resumo { background: var(--creme); border-radius: var(--raio); padding: 18px 20px; margin-bottom: 20px; }
        .resumo div { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; }
        .resumo div + div { border-top: 1px dashed var(--borda); }
        .resumo div span:first-child { color: var(--suave); }
        .resumo div span:last-child { text-align: right; font-weight: 700; }
        .erro { color: var(--erro); background: #fef3f2; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
        .acoes { display: flex; justify-content: space-between; gap: 12px; margin-top: 28px; flex-wrap: wrap; }
        .acoes .botao { flex: 1; min-width: 160px; }
        .sucesso { text-align: center; }
        .sucesso .icone { width: 84px; height: 84px; margin: 0 auto 16px; }
        .sucesso .icone circle { stroke-dasharray: 160; stroke-dashoffset: 160; animation: tracar .8s .1s forwards; }
        .sucesso .icone path { stroke-dasharray: 40; stroke-dashoffset: 40; animation: tracar .5s .7s forwards; }
        .confete { position: fixed; top: -12px; width: 10px; height: 14px; z-index: 60; pointer-events: none; animation: cair linear forwards; }

        /* ---------- Contato ---------- */
        .contato { display: grid; grid-template-columns: 1fr 1.2fr; gap: 48px; align-items: stretch; }
        .funcionamento { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .funcionamento td { padding: 14px 0; border-bottom: 1px solid rgba(245,241,234,.1); }
        .funcionamento td:last-child { text-align: right; font-weight: 600; }
        .funcionamento tr.hoje td { color: var(--ouro); }
        .funcionamento tr.hoje td:first-child::after { content: " · hoje"; font-size: .8rem; }
        .links { display: flex; flex-wrap: wrap; gap: 12px; }
        .mapa-moldura { border-radius: 24px; overflow: hidden; min-height: 360px; position: relative; background: var(--escuro-2); }
        .mapa-moldura iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; filter: grayscale(1) invert(.92) contrast(.9); }
        .mapa-moldura .endereco { position: absolute; left: 16px; right: 16px; bottom: 16px; background: rgba(13,13,14,.88); backdrop-filter: blur(8px); border-radius: 16px; padding: 16px 18px; display: flex; gap: 14px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        @media (max-width: 860px) { .contato { grid-template-columns: 1fr; } }

        /* Chamada final */
        .chamada { text-align: center; padding: 130px 0; background: linear-gradient(rgba(13,13,14,.78), rgba(13,13,14,.88)), center/cover fixed; }
        .chamada h2 { font-size: clamp(3rem, 10vw, 7rem); }
        .chamada p { color: var(--claro-suave); margin: 0 auto 34px; max-width: 480px; }

        footer { padding: 34px 0 110px; text-align: center; color: rgba(245,241,234,.45); font-size: .88rem; border-top: 1px solid rgba(245,241,234,.06); }

        /* Barra fixa no celular + WhatsApp flutuante */
        .cta-celular { position: fixed; left: 12px; right: 84px; bottom: 14px; z-index: 25; transform: translateY(140%); transition: transform .4s cubic-bezier(.2,.8,.2,1); }
        .cta-celular.visivel { transform: none; }
        .cta-celular .botao { width: 100%; box-shadow: 0 14px 30px -10px rgba(0,0,0,.6); }
        @media (min-width: 821px) { .cta-celular { display: none; } }
        .flutuante { position: fixed; right: 16px; bottom: 14px; z-index: 26; width: 58px; height: 58px; border-radius: 50%; background: #25d366; color: #fff; display: grid; place-items: center; box-shadow: 0 8px 22px rgba(0,0,0,.4); }
        .flutuante::before { content: ""; position: absolute; inset: 0; border-radius: 50%; border: 2px solid #25d366; animation: onda 2.4s infinite; }
        .flutuante svg { width: 30px; height: 30px; }

        /* ---------- Revelar ao rolar ---------- */
        .revelar { opacity: 0; transform: translateY(40px); transition: opacity .9s cubic-bezier(.2,.8,.2,1), transform .9s cubic-bezier(.2,.8,.2,1); transition-delay: var(--atraso, 0s); }
        .revelar.visto { opacity: 1; transform: none; }

        @keyframes zoom { from { transform: scale(1); } to { transform: scale(1.12) translate(-1.5%, -1%); } }
        @keyframes subir { from { transform: translateY(105%); } to { transform: none; } }
        @keyframes aparecer { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
        @keyframes entrar { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes pulso { 70% { box-shadow: 0 0 0 10px rgba(61,220,132,0); } 100% { box-shadow: 0 0 0 0 rgba(61,220,132,0); } }
        @keyframes rolar { 0% { opacity: 0; transform: translateY(0); } 40% { opacity: 1; } 100% { opacity: 0; transform: translateY(14px); } }
        @keyframes girar { to { background-position: 0 90.5px; } }
        @keyframes correr-poste { to { background-position: 68px 0; } }
        @keyframes girar-lento { to { transform: rotate(360deg); } }
        @keyframes correr { to { transform: translateX(-50%); } }
        @keyframes onda { 0% { transform: scale(1); opacity: .8; } 100% { transform: scale(1.7); opacity: 0; } }
        @keyframes tracar { to { stroke-dashoffset: 0; } }
        @keyframes cair { to { transform: translateY(105vh) rotate(720deg); } }

        @media (max-width: 600px) {
            section { padding: 80px 0; }
            .equipe { grid-template-columns: 1fr 1fr; gap: 12px; }
            .barbeiro { border-radius: 16px; }
            .barbeiro .info { padding: 50px 14px 14px; }
            .barbeiro strong { font-size: 1.5rem; }
            .barbeiro .inicial { font-size: 5rem; }
            .galeria { columns: 2; column-gap: 10px; }
            .galeria button { margin-bottom: 10px; border-radius: 12px; }
            .hero .acoes-hero { flex-direction: column; }
            .acoes { flex-direction: column-reverse; }
            .chamada { background-attachment: scroll; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
            html { scroll-behavior: auto; }
            .revelar { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <header class="barra" id="barra">
        <div class="conteudo">
            <a class="marca" href="#topo">
                @if ($empresa->logo_url)<img src="{{ $empresa->logo_url }}" alt="">@endif
                {{ $empresa->nome }}
            </a>
            <nav class="menu">
                @if ($servicos->isNotEmpty())<a href="#servicos">Serviços</a>@endif
                @if ($barbeiros->isNotEmpty())<a href="#equipe">Equipe</a>@endif
                <a href="#galeria">Galeria</a>
                <a href="#contato">Contato</a>
                <a class="botao" href="#agendar">Agendar</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" id="topo">
            <div class="hero-foto" style="background-image: url('{{ $fotoCapa }}')"></div>
            <div class="conteudo">
                <span class="selo"><i></i> Agendamento online 24h</span>
                <h1>
                    <span class="linha"><span>{{ implode(' ', $palavras) }}</span></span>
                    @if ($destaque)<span class="linha"><span><em>{{ $destaque }}</em></span></span>@endif
                </h1>
                <p class="frase">{{ $empresa->slogan ?: 'Corte na régua, barba alinhada e aquele cuidado que faz diferença. Escolha seu horário em menos de um minuto.' }}</p>
                <div class="acoes-hero">
                    <a class="botao botao-ouro" href="#agendar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                        Agendar meu horário
                    </a>
                    @if ($servicos->isNotEmpty())<a class="botao botao-vazado" href="#servicos">Ver serviços e preços</a>@endif
                </div>
            </div>
            <div class="poste" aria-hidden="true"><div class="tubo"></div></div>
            <a class="rolar" href="#sobre" aria-label="Rolar para baixo"></a>
        </section>

        @if ($servicos->isNotEmpty())
            <div class="faixa" aria-hidden="true">
                <div class="trilho">
                    @for ($i = 0; $i < 2; $i++)
                        @foreach ($servicos->pluck('nome')->merge(['Agende online', 'Estilo', 'Tradição'])->pad(8, 'Barbearia') as $nome)
                            <span>{{ $nome }}</span>
                        @endforeach
                    @endfor
                </div>
            </div>
        @endif

        <section class="claro" id="sobre">
            <div class="conteudo sobre">
                <div class="sobre-fotos revelar">
                    <img src="{{ $galeria[0]['url'] }}" alt="" loading="lazy">
                    <img src="{{ $fotoSobre }}" alt="" loading="lazy">
                    <div class="carimbo" aria-hidden="true">
                        <svg viewBox="0 0 100 100"><defs><path id="circulo" d="M50,50 m-37,0 a37,37 0 1,1 74,0 a37,37 0 1,1 -74,0"/></defs>
                            <text font-size="10.5" font-weight="700" letter-spacing="3.2" fill="currentColor"><textPath href="#circulo">CORTE • BARBA • ESTILO • TRADIÇÃO •</textPath></text></svg>
                        <b>✂</b>
                    </div>
                </div>
                <div>
                    <div class="rotulo revelar">Sobre a casa</div>
                    <h2 class="revelar" style="--atraso:.1s">Mais que um corte,<br><em>um ritual.</em></h2>
                    <p class="texto revelar" style="--atraso:.2s">{{ $empresa->descricao ?: 'Aqui cada detalhe importa: da toalha quente ao acabamento na navalha. Um espaço para você relaxar, colocar a conversa em dia e sair do jeito que gosta.' }}</p>
                    <div class="numeros revelar" style="--atraso:.3s">
                        <div><strong data-contar="{{ $barbeiros->count() }}">{{ $barbeiros->count() }}</strong><span>{{ $barbeiros->count() === 1 ? 'barbeiro' : 'barbeiros' }}</span></div>
                        <div><strong data-contar="{{ $servicos->count() }}">{{ $servicos->count() }}</strong><span>serviços</span></div>
                        <div><strong>{{ $menorPreco !== null ? 'R$ '.number_format($menorPreco, 0, ',', '.') : '1 min' }}</strong><span>{{ $menorPreco !== null ? 'a partir de' : 'para agendar' }}</span></div>
                    </div>
                </div>
            </div>
        </section>

        @if ($servicos->isNotEmpty())
            <section class="servicos" id="servicos">
                <div class="conteudo">
                    <div class="rotulo revelar">Serviços</div>
                    <h2 class="revelar" style="--atraso:.1s">Nosso <em>cardápio</em></h2>
                    <p class="intro revelar" style="--atraso:.2s">Preços claros, sem surpresa. Toque em um serviço para já começar o agendamento.</p>
                    <div class="cardapio">
                        @foreach ($servicos as $s)
                            <a class="item revelar" href="#agendar" data-servico="{{ $s->id }}" style="--atraso: {{ ($loop->index % 6) * 0.07 }}s">
                                <div class="linha-item">
                                    <h3>{{ $s->nome }}</h3>
                                    <span class="pontos"></span>
                                    <span class="valor">{{ $preco($s->preco) }}</span>
                                </div>
                                <p><span class="tempo">{{ $duracao($s->duracao_minutos) }}</span>@if ($s->descricao) · {{ $s->descricao }}@endif</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($barbeiros->isNotEmpty())
            <section class="claro" id="equipe">
                <div class="conteudo">
                    <div class="rotulo revelar">Equipe</div>
                    <h2 class="revelar" style="--atraso:.1s">Mãos de <em>mestre</em></h2>
                    <p class="intro revelar" style="--atraso:.2s">Escolha quem vai cuidar do seu visual.</p>
                    <div class="equipe">
                        @foreach ($barbeiros as $b)
                            <a class="barbeiro revelar" href="#agendar" style="--atraso: {{ ($loop->index % 4) * 0.1 }}s">
                                @if ($b->foto_url)
                                    <img src="{{ $b->foto_url }}" alt="{{ $b->nome }}" loading="lazy">
                                @else
                                    <span class="inicial" aria-hidden="true">{{ mb_strtoupper(mb_substr($b->nome, 0, 1)) }}</span>
                                @endif
                                <span class="agendar-com">Agendar →</span>
                                <span class="info">
                                    <strong>{{ $b->nome }}</strong>
                                    <small>{{ $b->especialidades ?: 'Barbeiro' }}</small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="galeria">
            <div class="conteudo">
                <div class="rotulo revelar">Galeria</div>
                <h2 class="revelar" style="--atraso:.1s">O <em>resultado</em> fala</h2>
                <p class="intro revelar" style="--atraso:.2s">Um pouco do nosso trabalho e do nosso espaço.</p>
                <div class="galeria">
                    @foreach ($galeria as $foto)
                        <button type="button" class="revelar" data-foto="{{ $loop->index }}" style="--atraso: {{ ($loop->index % 3) * 0.1 }}s" aria-label="Ampliar foto">
                            <img src="{{ $foto['url'] }}" alt="{{ $foto['legenda'] ?? 'Foto da '.$empresa->nome }}" loading="lazy">
                            @if ($foto['legenda'])<span class="legenda">{{ $foto['legenda'] }}</span>@endif
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="agendar" id="agendar">
            <div class="conteudo">
                <div class="cartao-agenda revelar">
                    <div class="cabeca-agenda">
                        <div>
                            <div class="rotulo">Agendamento</div>
                            <h2>Reserve sua <em>cadeira</em></h2>
                        </div>
                        <p>Rápido, sem cadastro e sem baixar aplicativo. Só os horários realmente livres aparecem.</p>
                    </div>
                    @if ($servicos->isEmpty())
                        <p class="aviso">O agendamento online ainda não está disponível.@if ($whatsapp) Fale com a gente pelo <a href="{{ $whatsapp }}">WhatsApp</a>.@endif</p>
                    @else
                        <div class="passos" aria-hidden="true"><span></span><span></span><span></span><span></span></div>

                        <div class="etapa" data-etapa="1">
                            <h3>Quais serviços?</h3>
                            <p>Você pode escolher mais de um.</p>
                            <div class="opcoes" id="lista-servicos"></div>
                            <div class="acoes"><button class="botao" type="button" id="ir-barbeiro" disabled>Continuar →</button></div>
                        </div>

                        <div class="etapa" data-etapa="2" hidden>
                            <h3>Com quem?</h3>
                            <p>Escolha o barbeiro ou deixe a gente encaixar com quem estiver livre.</p>
                            <div class="opcoes" id="lista-barbeiros"></div>
                            <div class="acoes">
                                <button class="botao botao-claro" type="button" data-voltar="1">← Voltar</button>
                            </div>
                        </div>

                        <div class="etapa" data-etapa="3" hidden>
                            <h3>Dia e horário</h3>
                            <p id="resumo-escolha"></p>
                            <div class="dias" id="lista-dias"></div>
                            <label class="outra-data">Outra data: <input type="date" id="outra-data"></label>
                            <div id="lista-horarios"></div>
                            <div class="acoes">
                                <button class="botao botao-claro" type="button" data-voltar="2">← Voltar</button>
                            </div>
                        </div>

                        <form class="etapa" data-etapa="4" id="form-dados" hidden novalidate>
                            <h3>Seus dados</h3>
                            <p>Para a barbearia saber quem vem e confirmar o horário.</p>
                            <div class="resumo" id="resumo"></div>
                            <div class="erro" id="erro" hidden></div>
                            <label class="campo"><span>Seu nome</span><input name="nome" autocomplete="name" maxlength="120" required></label>
                            <label class="campo"><span>WhatsApp com DDD</span><input name="telefone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 99999-0000" required></label>
                            <label class="check"><input type="checkbox" name="aceita_whatsapp" value="1"><span>Quero receber a confirmação e lembretes do horário pelo WhatsApp.</span></label>
                            <label class="campo"><span>Observação (opcional)</span><textarea name="observacoes" rows="2" maxlength="300" placeholder="Ex.: quero manter o comprimento em cima"></textarea></label>
                            <div class="acoes">
                                <button class="botao botao-claro" type="button" data-voltar="3">← Voltar</button>
                                <button class="botao" type="submit" id="confirmar">Confirmar agendamento</button>
                            </div>
                        </form>

                        <div class="etapa sucesso" data-etapa="5" hidden>
                            <svg class="icone" viewBox="0 0 56 56" fill="none" stroke="#1a7f37" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="28" cy="28" r="25"/><path d="M17 29l7 7 15-16"/>
                            </svg>
                            <h3>Horário reservado!</h3>
                            <p>Te esperamos. Se precisar mudar, avise a barbearia.</p>
                            <div class="resumo" id="resumo-final"></div>
                            <div class="acoes">
                                <button class="botao botao-claro" type="button" id="novo">Agendar outro horário</button>
                                @if ($whatsapp)<a class="botao botao-whats" href="{{ $whatsapp }}" target="_blank" rel="noopener">Falar no WhatsApp</a>@endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section id="contato">
            <div class="conteudo">
                <div class="rotulo revelar">Contato</div>
                <h2 class="revelar" style="--atraso:.1s">Vem pra <em>cadeira</em></h2>
                <div class="contato">
                    <div class="revelar" style="--atraso:.15s">
                        <table class="funcionamento">
                            @foreach ($funcionamento as $linha)
                                <tr><td>{{ $linha['dias'] }}</td><td>{{ $linha['horario'] }}</td></tr>
                            @endforeach
                        </table>
                        <div class="links">
                            @if ($whatsapp)<a class="botao botao-whats" href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>@endif
                            @if ($empresa->instagram)<a class="botao botao-vazado" href="https://instagram.com/{{ $empresa->instagram }}" target="_blank" rel="noopener">&#64;{{ $empresa->instagram }}</a>@endif
                            @if ($empresa->telefone && $empresa->telefone !== $empresa->whatsapp)<a class="botao botao-vazado" href="tel:+55{{ $empresa->telefone }}">Ligar</a>@endif
                        </div>
                    </div>
                    @if ($endereco)
                        <div class="mapa-moldura revelar" style="--atraso:.25s">
                            <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa"
                                src="https://www.google.com/maps?q={{ urlencode($endereco) }}&output=embed"></iframe>
                            <div class="endereco">
                                <span>{{ $endereco }}</span>
                                <a class="botao botao-ouro" href="{{ $mapa }}" target="_blank" rel="noopener" style="padding: 10px 18px">Como chegar</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="chamada" style="background-image: linear-gradient(rgba(13,13,14,.78), rgba(13,13,14,.9)), url('{{ $galeria[1]['url'] ?? $fotoCapa }}')">
            <div class="conteudo">
                <h2 class="revelar">Bora dar um <em>trato</em>?</h2>
                <p class="revelar" style="--atraso:.1s">Seu próximo corte está a poucos toques de distância.</p>
                <a class="botao botao-ouro revelar" style="--atraso:.2s" href="#agendar">Agendar agora</a>
            </div>
        </section>
    </main>

    <footer>
        <div class="conteudo">© {{ now()->year }} {{ $empresa->nome }} · Agendamento online</div>
    </footer>

    @if ($servicos->isNotEmpty())
        <div class="cta-celular" id="cta-celular"><a class="botao botao-ouro" href="#agendar">Agendar horário</a></div>
    @endif
    @if ($whatsapp)
        <a class="flutuante" href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5 2.5 1 3 .8 3.6.7.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg>
        </a>
    @endif

    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada">
        <img alt="">
        <button class="fechar" type="button" aria-label="Fechar">✕</button>
        <button class="ant" type="button" aria-label="Foto anterior">‹</button>
        <button class="prox" type="button" aria-label="Próxima foto">›</button>
    </div>

    <script>
    (function () {
        // Cabeçalho sólido e botão fixo do celular depois do topo
        const barra = document.getElementById('barra');
        const cta = document.getElementById('cta-celular');
        const agendar = document.getElementById('agendar');
        let agendaVisivel = false;
        const aoRolar = () => {
            const y = window.scrollY;
            barra.classList.toggle('solida', y > 40);
            if (cta) cta.classList.toggle('visivel', y > window.innerHeight * .7 && !agendaVisivel);
        };
        window.addEventListener('scroll', aoRolar, { passive: true });
        aoRolar();
        if (cta && 'IntersectionObserver' in window) {
            new IntersectionObserver(([e]) => { agendaVisivel = e.isIntersecting; aoRolar(); }).observe(agendar);
        }

        // Revelar elementos ao rolar e contar números
        const contar = (el) => {
            const alvo = Number(el.dataset.contar);
            const inicio = performance.now();
            const passo = (t) => {
                const p = Math.min(1, (t - inicio) / 1200);
                el.textContent = Math.round(alvo * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(passo);
            };
            requestAnimationFrame(passo);
        };
        const revelar = document.querySelectorAll('.revelar');
        if ('IntersectionObserver' in window) {
            const obs = new IntersectionObserver((entradas) => entradas.forEach((e) => {
                if (!e.isIntersecting) return;
                e.target.classList.add('visto');
                e.target.querySelectorAll('[data-contar]').forEach(contar);
                obs.unobserve(e.target);
            }), { threshold: .15, rootMargin: '0px 0px -40px 0px' });
            revelar.forEach((el) => obs.observe(el));
        } else {
            revelar.forEach((el) => el.classList.add('visto'));
        }

        // Linha de hoje no horário de funcionamento
        const hoje = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][new Date().getDay()];
        const ordem = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
        document.querySelectorAll('.funcionamento tr').forEach((tr) => {
            const [de, ate] = tr.cells[0].textContent.split(' a ');
            const i = ordem.indexOf(hoje);
            if (i >= ordem.indexOf(de) && i <= ordem.indexOf(ate || de)) tr.classList.add('hoje');
        });

        // Galeria ampliada
        const fotos = [...document.querySelectorAll('[data-foto]')];
        const caixa = document.getElementById('lightbox');
        const img = caixa.querySelector('img');
        let atual = 0;
        const mostrar = (i) => { atual = (i + fotos.length) % fotos.length; img.src = fotos[atual].querySelector('img').src; img.alt = fotos[atual].querySelector('img').alt; };
        const fechar = () => caixa.classList.remove('aberto');
        fotos.forEach((f, i) => f.addEventListener('click', () => { mostrar(i); caixa.classList.add('aberto'); }));
        caixa.querySelector('.fechar').addEventListener('click', fechar);
        caixa.querySelector('.ant').addEventListener('click', () => mostrar(atual - 1));
        caixa.querySelector('.prox').addEventListener('click', () => mostrar(atual + 1));
        caixa.addEventListener('click', (e) => { if (e.target === caixa) fechar(); });
        document.addEventListener('keydown', (e) => {
            if (!caixa.classList.contains('aberto')) return;
            if (e.key === 'Escape') fechar();
            if (e.key === 'ArrowLeft') mostrar(atual - 1);
            if (e.key === 'ArrowRight') mostrar(atual + 1);
        });

        // Tocar num serviço do cardápio já marca ele no agendamento
        document.querySelectorAll('[data-servico]').forEach((a) => a.addEventListener('click', () => {
            const input = document.querySelector('#lista-servicos input[value="' + a.dataset.servico + '"]');
            if (input && !input.checked && !document.querySelector('[data-etapa="1"]').hidden) input.click();
        }));

        // Confete na confirmação do agendamento
        const etapaFinal = document.querySelector('[data-etapa="5"]');
        if (etapaFinal && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
            new MutationObserver(() => {
                if (etapaFinal.hidden) return;
                const cores = ['#c8a165', '#c62828', '#1e4fa8', '#f5f1ea', getComputedStyle(document.documentElement).getPropertyValue('--acento')];
                for (let i = 0; i < 70; i++) {
                    const c = document.createElement('i');
                    c.className = 'confete';
                    c.style.left = Math.random() * 100 + 'vw';
                    c.style.background = cores[i % cores.length];
                    c.style.animationDuration = 2 + Math.random() * 2.5 + 's';
                    c.style.animationDelay = Math.random() * .6 + 's';
                    c.style.borderRadius = i % 2 ? '50%' : '2px';
                    document.body.append(c);
                    setTimeout(() => c.remove(), 5500);
                }
            }).observe(etapaFinal, { attributes: true, attributeFilter: ['hidden'] });
        }
    })();
    </script>

    @if ($servicos->isNotEmpty())
        @include('pagina._agendamento-script')
    @endif
</body>
</html>
