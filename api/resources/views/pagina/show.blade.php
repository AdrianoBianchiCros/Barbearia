@php
    $titulo = $empresa->nome.($empresa->slogan ? ' · '.$empresa->slogan : '');
    $descricao = $empresa->descricao
        ? \Illuminate\Support\Str::limit(strip_tags($empresa->descricao), 155)
        : 'Agende seu horário online na '.$empresa->nome.'.';
    $imagem = $empresa->capa_url ?? $empresa->logo_url;
    $whatsapp = $empresa->whatsapp ? 'https://wa.me/55'.$empresa->whatsapp : null;
    $mapa = $endereco ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($endereco) : null;
    $preco = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $duracao = fn (int $m) => $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? sprintf('%02d', $m % 60) : '') : $m.' min';
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
    @if ($imagem)<meta property="og:image" content="{{ $imagem }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="{{ $empresa->cor_primaria }}">
    @if ($empresa->logo_url)<link rel="icon" href="{{ $empresa->logo_url }}">@endif
    <style>
        :root {
            --cor: {{ $empresa->cor_primaria }};
            --cor-texto: {{ $corTexto }};
            --fundo: #f6f5f2;
            --cartao: #ffffff;
            --texto: #1c1c1c;
            --suave: #666;
            --borda: #e3e1dc;
            --erro: #b42318;
            --raio: 14px;
        }
        * { box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fundo); color: var(--texto); line-height: 1.5; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        .conteudo { max-width: 960px; margin: 0 auto; padding: 0 16px; }
        .botao { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 999px; padding: 14px 26px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; background: var(--cor); color: var(--cor-texto); transition: filter .15s; }
        .botao:hover { filter: brightness(1.08); }
        .botao:disabled { opacity: .45; cursor: not-allowed; filter: none; }
        .botao-claro { background: transparent; color: var(--texto); border: 1px solid var(--borda); }
        .botao-whats { background: #25d366; color: #fff; }

        /* Topo */
        .topo { position: relative; color: #fff; background: #222 center/cover no-repeat; }
        .topo::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.7)); }
        .topo .conteudo { position: relative; padding-top: 64px; padding-bottom: 56px; text-align: center; }
        .topo .logo { width: 104px; height: 104px; border-radius: 50%; object-fit: cover; margin: 0 auto 16px; border: 3px solid rgba(255,255,255,.85); background: #fff; }
        .topo h1 { margin: 0; font-size: clamp(1.8rem, 6vw, 2.8rem); line-height: 1.15; }
        .topo p { margin: 8px auto 24px; max-width: 560px; font-size: 1.1rem; opacity: .92; }

        section { padding: 40px 0; }
        h2 { font-size: 1.5rem; margin: 0 0 20px; }
        .sobre { white-space: pre-line; color: #333; max-width: 720px; }

        /* Serviços */
        .lista-servicos { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr)); }
        .servico { background: var(--cartao); border: 1px solid var(--borda); border-radius: var(--raio); padding: 16px; display: flex; justify-content: space-between; gap: 12px; }
        .servico strong { display: block; }
        .servico small { color: var(--suave); }
        .servico .preco { font-weight: 700; white-space: nowrap; }

        /* Equipe */
        .equipe { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(min(140px, 100%), 1fr)); }
        .barbeiro { text-align: center; }
        .foto { width: 112px; height: 112px; border-radius: 50%; object-fit: cover; margin: 0 auto 10px; background: var(--cor); color: var(--cor-texto); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 700; }
        .barbeiro small { color: var(--suave); display: block; }

        /* Contato */
        .contato { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); }
        .funcionamento { width: 100%; border-collapse: collapse; }
        .funcionamento td { padding: 6px 0; border-bottom: 1px solid var(--borda); }
        .funcionamento td:last-child { text-align: right; }
        .mapa { width: 100%; height: 260px; border: 0; border-radius: var(--raio); background: #ddd; }
        .links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }

        /* Agendamento */
        #agendar { background: var(--cartao); border-top: 1px solid var(--borda); border-bottom: 1px solid var(--borda); }
        .passos { display: flex; gap: 6px; margin-bottom: 24px; }
        .passos span { flex: 1; height: 4px; border-radius: 4px; background: var(--borda); }
        .passos span.feito { background: var(--cor); }
        .etapa h3 { margin: 0 0 4px; font-size: 1.2rem; }
        .etapa > p { margin: 0 0 16px; color: var(--suave); }
        .opcoes { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr)); }
        .opcao { display: flex; align-items: center; gap: 12px; padding: 14px; border: 2px solid var(--borda); border-radius: var(--raio); background: #fff; cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%; }
        .opcao:hover { border-color: #bbb; }
        .opcao.sel { border-color: var(--cor); box-shadow: 0 0 0 1px var(--cor); }
        .opcao input { width: 20px; height: 20px; accent-color: var(--cor); flex: none; }
        .opcao .texto { flex: 1; }
        .opcao small { color: var(--suave); display: block; }
        .opcao .mini { width: 44px; height: 44px; font-size: 1.1rem; margin: 0; flex: none; }
        .dias { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x mandatory; }
        .dia { flex: none; width: 64px; padding: 10px 0; border: 2px solid var(--borda); border-radius: var(--raio); background: #fff; font: inherit; cursor: pointer; text-align: center; scroll-snap-align: start; color: inherit; }
        .dia small { display: block; font-size: .75rem; color: var(--suave); text-transform: uppercase; }
        .dia strong { font-size: 1.25rem; }
        .dia.sel { border-color: var(--cor); background: var(--cor); color: var(--cor-texto); }
        .dia.sel small { color: inherit; }
        .outra-data { margin: 8px 0 20px; display: flex; align-items: center; gap: 8px; color: var(--suave); font-size: .95rem; }
        .horarios { display: grid; gap: 8px; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); }
        .hora { padding: 10px 0; border: 2px solid var(--borda); border-radius: 10px; background: #fff; font: inherit; font-weight: 600; cursor: pointer; color: inherit; }
        .hora.sel { border-color: var(--cor); background: var(--cor); color: var(--cor-texto); }
        .aviso { padding: 16px; border-radius: var(--raio); background: var(--fundo); color: var(--suave); text-align: center; }
        .campo { display: block; margin-bottom: 14px; }
        .campo span { display: block; font-weight: 600; margin-bottom: 4px; }
        .campo input, .campo textarea, .outra-data input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 10px; font: inherit; background: #fff; color: inherit; }
        .outra-data input { width: auto; padding: 8px; }
        .check { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
        .check input { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--cor); flex: none; }
        .resumo { background: var(--fundo); border-radius: var(--raio); padding: 16px; margin-bottom: 16px; }
        .resumo div { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; }
        .resumo div span:first-child { color: var(--suave); }
        .resumo div span:last-child { text-align: right; font-weight: 600; }
        .erro { color: var(--erro); background: #fef3f2; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
        .acoes { display: flex; justify-content: space-between; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
        .acoes .botao { flex: 1; min-width: 140px; }
        .sucesso { text-align: center; }
        .sucesso .icone { width: 64px; height: 64px; border-radius: 50%; background: #e7f6ec; color: #1a7f37; font-size: 2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
        [hidden] { display: none !important; }

        footer { padding: 32px 0 96px; text-align: center; color: var(--suave); font-size: .9rem; }
        .flutuante { position: fixed; right: 16px; bottom: 16px; z-index: 10; width: 56px; height: 56px; border-radius: 50%; background: #25d366; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
        .flutuante svg { width: 30px; height: 30px; }
        @media (max-width: 600px) {
            section { padding: 32px 0; }
            .acoes { flex-direction: column-reverse; }
        }
    </style>
</head>
<body>
    <header class="topo" @if ($empresa->capa_url) style="background-image: url('{{ $empresa->capa_url }}')" @endif>
        <div class="conteudo">
            @if ($empresa->logo_url)
                <img class="logo" src="{{ $empresa->logo_url }}" alt="Logo da {{ $empresa->nome }}">
            @endif
            <h1>{{ $empresa->nome }}</h1>
            @if ($empresa->slogan)<p>{{ $empresa->slogan }}</p>@else<p>Agende seu horário em poucos toques.</p>@endif
            <a class="botao" href="#agendar">Agendar horário</a>
        </div>
    </header>

    <main>
        @if ($empresa->descricao)
            <section>
                <div class="conteudo">
                    <h2>Sobre</h2>
                    <p class="sobre">{{ $empresa->descricao }}</p>
                </div>
            </section>
        @endif

        @if ($servicos->isNotEmpty())
            <section id="servicos">
                <div class="conteudo">
                    <h2>Serviços</h2>
                    <div class="lista-servicos">
                        @foreach ($servicos as $s)
                            <div class="servico">
                                <div>
                                    <strong>{{ $s->nome }}</strong>
                                    <small>{{ $duracao($s->duracao_minutos) }}@if ($s->descricao) · {{ $s->descricao }}@endif</small>
                                </div>
                                <span class="preco">{{ $preco($s->preco) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($barbeiros->isNotEmpty())
            <section id="equipe">
                <div class="conteudo">
                    <h2>Equipe</h2>
                    <div class="equipe">
                        @foreach ($barbeiros as $b)
                            <div class="barbeiro">
                                @if ($b->foto_url)
                                    <img class="foto" src="{{ $b->foto_url }}" alt="{{ $b->nome }}" loading="lazy">
                                @else
                                    <div class="foto" aria-hidden="true">{{ mb_strtoupper(mb_substr($b->nome, 0, 1)) }}</div>
                                @endif
                                <strong>{{ $b->nome }}</strong>
                                @if ($b->especialidades)<small>{{ $b->especialidades }}</small>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="agendar">
            <div class="conteudo">
                <h2>Agendar horário</h2>
                @if ($servicos->isEmpty())
                    <p class="aviso">O agendamento online ainda não está disponível.@if ($whatsapp) Fale com a gente pelo <a href="{{ $whatsapp }}">WhatsApp</a>.@endif</p>
                @else
                    <div class="passos" aria-hidden="true"><span></span><span></span><span></span><span></span></div>

                    <div class="etapa" data-etapa="1">
                        <h3>Quais serviços?</h3>
                        <p>Você pode escolher mais de um.</p>
                        <div class="opcoes" id="lista-servicos"></div>
                        <div class="acoes"><button class="botao" type="button" id="ir-barbeiro" disabled>Continuar</button></div>
                    </div>

                    <div class="etapa" data-etapa="2" hidden>
                        <h3>Com quem?</h3>
                        <p>Escolha o barbeiro ou deixe a gente encaixar com quem estiver livre.</p>
                        <div class="opcoes" id="lista-barbeiros"></div>
                        <div class="acoes">
                            <button class="botao botao-claro" type="button" data-voltar="1">Voltar</button>
                        </div>
                    </div>

                    <div class="etapa" data-etapa="3" hidden>
                        <h3>Dia e horário</h3>
                        <p id="resumo-escolha"></p>
                        <div class="dias" id="lista-dias"></div>
                        <label class="outra-data">Outra data: <input type="date" id="outra-data"></label>
                        <div id="lista-horarios"></div>
                        <div class="acoes">
                            <button class="botao botao-claro" type="button" data-voltar="2">Voltar</button>
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
                            <button class="botao botao-claro" type="button" data-voltar="3">Voltar</button>
                            <button class="botao" type="submit" id="confirmar">Confirmar agendamento</button>
                        </div>
                    </form>

                    <div class="etapa sucesso" data-etapa="5" hidden>
                        <div class="icone" aria-hidden="true">✓</div>
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
        </section>

        <section id="contato">
            <div class="conteudo contato">
                <div>
                    <h2>Horário de funcionamento</h2>
                    <table class="funcionamento">
                        @foreach ($funcionamento as $linha)
                            <tr><td>{{ $linha['dias'] }}</td><td>{{ $linha['horario'] }}</td></tr>
                        @endforeach
                    </table>
                    <div class="links">
                        @if ($whatsapp)<a class="botao botao-whats" href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>@endif
                        @if ($empresa->instagram)<a class="botao botao-claro" href="https://instagram.com/{{ $empresa->instagram }}" target="_blank" rel="noopener">&#64;{{ $empresa->instagram }}</a>@endif
                        @if ($empresa->telefone && $empresa->telefone !== $empresa->whatsapp)<a class="botao botao-claro" href="tel:+55{{ $empresa->telefone }}">Ligar</a>@endif
                    </div>
                </div>
                @if ($endereco)
                    <div>
                        <h2>Onde estamos</h2>
                        <p>{{ $endereco }}</p>
                        <iframe class="mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa"
                            src="https://www.google.com/maps?q={{ urlencode($endereco) }}&output=embed"></iframe>
                        <div class="links"><a class="botao botao-claro" href="{{ $mapa }}" target="_blank" rel="noopener">Como chegar</a></div>
                    </div>
                @endif
            </div>
        </section>
    </main>

    <footer>
        <div class="conteudo">© {{ now()->year }} {{ $empresa->nome }}</div>
    </footer>

    @if ($whatsapp)
        <a class="flutuante" href="{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5 2.5 1 3 .8 3.6.7.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg>
        </a>
    @endif

    @if ($servicos->isNotEmpty())
    <script>
    (function () {
        const C = @json($config);
        const DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        const $ = (s) => document.querySelector(s);
        const estado = { servicos: [], barbeiro: null, data: null, hora: null, opcoes: [] };

        const dinheiro = (v) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const minutos = (m) => m >= 60 ? Math.floor(m / 60) + 'h' + (m % 60 ? String(m % 60).padStart(2, '0') : '') : m + ' min';
        const dataLonga = (iso) => {
            const d = new Date(iso + 'T12:00:00');
            return DIAS[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()];
        };
        const somarDias = (iso, n) => {
            const d = new Date(iso + 'T12:00:00');
            d.setDate(d.getDate() + n);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };
        const el = (tag, attrs, ...filhos) => {
            const e = document.createElement(tag);
            Object.entries(attrs || {}).forEach(([k, v]) => k === 'class' ? e.className = v : k.startsWith('on') ? e.addEventListener(k.slice(2), v) : e.setAttribute(k, v));
            filhos.flat().forEach((f) => f != null && e.append(f));
            return e;
        };
        const fotoMini = (b) => b && b.foto
            ? el('img', { class: 'foto mini', src: b.foto, alt: '' })
            : el('div', { class: 'foto mini' }, b ? b.nome.charAt(0).toUpperCase() : '★');

        function irPara(n) {
            document.querySelectorAll('.etapa').forEach((e) => e.hidden = Number(e.dataset.etapa) !== n);
            document.querySelectorAll('.passos span').forEach((s, i) => s.classList.toggle('feito', i < n));
            $('#agendar').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.querySelectorAll('[data-voltar]').forEach((b) => b.addEventListener('click', () => irPara(Number(b.dataset.voltar))));

        // 1. Serviços
        const listaServicos = $('#lista-servicos');
        C.servicos.forEach((s) => {
            const input = el('input', { type: 'checkbox', value: s.id });
            const opcao = el('label', { class: 'opcao' }, input,
                el('span', { class: 'texto' }, el('strong', {}, s.nome), el('small', {}, minutos(s.duracao) + ' · ' + dinheiro(s.preco))));
            input.addEventListener('change', () => {
                opcao.classList.toggle('sel', input.checked);
                estado.servicos = [...listaServicos.querySelectorAll('input:checked')].map((i) => Number(i.value));
                $('#ir-barbeiro').disabled = estado.servicos.length === 0;
            });
            listaServicos.append(opcao);
        });
        $('#ir-barbeiro').addEventListener('click', () => { montarBarbeiros(); irPara(2); });

        // 2. Barbeiro
        function montarBarbeiros() {
            const aptos = C.barbeiros.filter((b) => estado.servicos.every((id) => b.servicos.includes(id)));
            const lista = $('#lista-barbeiros');
            lista.replaceChildren();
            if (aptos.length === 0) {
                lista.append(el('p', { class: 'aviso' }, 'Nenhum barbeiro faz todos esses serviços juntos. Volte e escolha outra combinação.'));
                return;
            }
            const escolher = (b) => { estado.barbeiro = b; estado.data = null; montarDias(); irPara(3); };
            if (aptos.length > 1) {
                lista.append(el('button', { type: 'button', class: 'opcao', onclick: () => escolher(null) }, fotoMini(null),
                    el('span', { class: 'texto' }, el('strong', {}, 'Sem preferência'), el('small', {}, 'Primeiro horário livre de qualquer barbeiro'))));
            }
            aptos.forEach((b) => lista.append(el('button', { type: 'button', class: 'opcao', onclick: () => escolher(b) }, fotoMini(b),
                el('span', { class: 'texto' }, el('strong', {}, b.nome)))));
        }

        // 3. Dia e horário
        const outraData = $('#outra-data');
        outraData.min = C.hoje;
        outraData.max = somarDias(C.hoje, C.diasAntecedencia);
        outraData.addEventListener('change', () => outraData.value && escolherDia(outraData.value));

        function montarDias() {
            const nomes = estado.servicos.map((id) => C.servicos.find((s) => s.id === id).nome).join(' + ');
            $('#resumo-escolha').textContent = nomes + ' · ' + (estado.barbeiro ? estado.barbeiro.nome : 'sem preferência de barbeiro');
            const lista = $('#lista-dias');
            lista.replaceChildren();
            for (let i = 0; i < 14; i++) {
                const iso = somarDias(C.hoje, i);
                const d = new Date(iso + 'T12:00:00');
                lista.append(el('button', { type: 'button', class: 'dia', 'data-data': iso, onclick: () => escolherDia(iso) },
                    el('small', {}, i === 0 ? 'hoje' : DIAS[d.getDay()]), el('strong', {}, String(d.getDate())), el('small', {}, MESES[d.getMonth()])));
            }
            escolherDia(estado.data || C.hoje);
        }

        let consulta = 0;
        async function escolherDia(iso) {
            estado.data = iso;
            estado.hora = null;
            document.querySelectorAll('.dia').forEach((d) => d.classList.toggle('sel', d.dataset.data === iso));
            outraData.value = document.querySelector('.dia.sel') ? '' : iso;
            const area = $('#lista-horarios');
            area.replaceChildren(el('p', { class: 'aviso' }, 'Buscando horários…'));

            const params = new URLSearchParams({ data: iso });
            estado.servicos.forEach((id) => params.append('servico_ids[]', id));
            if (estado.barbeiro) params.append('funcionario_id', estado.barbeiro.id);
            const minha = ++consulta;
            try {
                const resp = await fetch(C.urlHorarios + '?' + params, { headers: { Accept: 'application/json' } });
                if (!resp.ok) throw new Error();
                const dados = await resp.json();
                if (minha !== consulta) return; // o cliente já trocou de dia
                montarHorarios(dados);
            } catch (e) {
                if (minha === consulta) area.replaceChildren(el('p', { class: 'aviso' }, 'Não foi possível carregar os horários. Verifique a conexão e tente de novo.'));
            }
        }

        function montarHorarios(dados) {
            // Para cada hora, o primeiro barbeiro livre (sem preferência) ou o escolhido.
            const porHora = new Map();
            dados.forEach((b) => b.horarios.forEach((h) => { if (!porHora.has(h)) porHora.set(h, b); }));
            const horas = [...porHora.keys()].sort();
            const area = $('#lista-horarios');
            if (horas.length === 0) {
                area.replaceChildren(el('p', { class: 'aviso' }, 'Sem horários livres neste dia. Tente outra data.'));
                return;
            }
            area.replaceChildren(el('div', { class: 'horarios' }, horas.map((h) => el('button', {
                type: 'button', class: 'hora',
                onclick: () => {
                    estado.hora = h;
                    estado.opcao = porHora.get(h);
                    montarResumo($('#resumo'));
                    $('#erro').hidden = true;
                    irPara(4);
                    lembrarDados();
                },
            }, h))));
        }

        // 4. Dados e confirmação
        function linhasResumo(r) {
            return [
                ['Serviços', r.servicos],
                ['Barbeiro', r.barbeiro],
                ['Quando', dataLonga(r.data) + ' às ' + r.hora],
                ['Duração', minutos(r.duracao)],
                ['Valor', dinheiro(r.valor)],
            ];
        }
        function preencher(alvo, r) {
            alvo.replaceChildren(...linhasResumo(r).map(([k, v]) => el('div', {}, el('span', {}, k), el('span', {}, v))));
        }
        function montarResumo(alvo) {
            const o = estado.opcao;
            preencher(alvo, {
                servicos: estado.servicos.map((id) => C.servicos.find((s) => s.id === id).nome).join(' + '),
                barbeiro: estado.barbeiro ? estado.barbeiro.nome : o.nome,
                data: estado.data, hora: estado.hora, duracao: o.duracao_minutos, valor: o.valor_total,
            });
        }

        const form = $('#form-dados');
        const CHAVE = 'agendamento-dados';
        function lembrarDados() {
            try {
                const salvo = JSON.parse(localStorage.getItem(CHAVE) || 'null');
                if (salvo && !form.nome.value) { form.nome.value = salvo.nome || ''; form.telefone.value = salvo.telefone || ''; }
            } catch (e) { /* sem armazenamento local */ }
        }

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const erro = $('#erro');
            erro.hidden = true;
            if (!form.nome.value.trim() || form.telefone.value.replace(/\D/g, '').length < 10) {
                erro.textContent = 'Informe seu nome e o WhatsApp com DDD.';
                erro.hidden = false;
                return;
            }
            const botao = $('#confirmar');
            botao.disabled = true;
            botao.textContent = 'Agendando…';
            try {
                const resp = await fetch(C.urlAgendar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({
                        nome: form.nome.value.trim(),
                        telefone: form.telefone.value,
                        aceita_whatsapp: form.aceita_whatsapp.checked,
                        observacoes: form.observacoes.value.trim() || null,
                        funcionario_id: estado.barbeiro ? estado.barbeiro.id : null,
                        servico_ids: estado.servicos,
                        data: estado.data,
                        hora: estado.hora,
                    }),
                });
                const dados = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    const msgs = dados.errors ? Object.values(dados.errors).flat() : [];
                    erro.textContent = msgs[0] || dados.message || 'Não foi possível agendar. Tente de novo.';
                    erro.hidden = false;
                    if (dados.errors && dados.errors.hora) escolherDia(estado.data); // horário foi ocupado: atualiza a lista
                    return;
                }
                try { localStorage.setItem(CHAVE, JSON.stringify({ nome: form.nome.value.trim(), telefone: form.telefone.value })); } catch (e) { /* ok */ }
                preencher($('#resumo-final'), {
                    servicos: dados.servicos.join(' + '), barbeiro: dados.barbeiro, data: dados.data,
                    hora: dados.hora, duracao: dados.duracao_minutos, valor: dados.valor_total,
                });
                irPara(5);
            } catch (e) {
                erro.textContent = 'Sem conexão. Verifique a internet e tente de novo.';
                erro.hidden = false;
            } finally {
                botao.disabled = false;
                botao.textContent = 'Confirmar agendamento';
            }
        });

        $('#novo').addEventListener('click', () => {
            listaServicos.querySelectorAll('input').forEach((i) => { i.checked = false; i.closest('.opcao').classList.remove('sel'); });
            Object.assign(estado, { servicos: [], barbeiro: null, data: null, hora: null });
            $('#ir-barbeiro').disabled = true;
            form.observacoes.value = '';
            irPara(1);
        });

        document.querySelectorAll('.passos span')[0].classList.add('feito');
    })();
    </script>
    @endif
</body>
</html>
