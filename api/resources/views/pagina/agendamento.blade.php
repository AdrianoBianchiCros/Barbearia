@php
    $whatsapp = $empresa->whatsapp ? 'https://wa.me/55'.$empresa->whatsapp : null;
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $dataLonga = $dias[$inicio->dayOfWeek].', '.$inicio->day.' de '.$meses[$inicio->month - 1];
    $situacao = match (true) {
        $a->status === 'cancelado' => ['Cancelado', 'cancelado'],
        ! $ativo => ['Encerrado', 'encerrado'],
        $a->status === 'confirmado' => ['Confirmado', 'confirmado'],
        default => ['Aguardando confirmação', 'agendado'],
    };
    $endereco = trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->bairro, $empresa->cidade])));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Seu horário · {{ $empresa->nome }}</title>
    <meta name="theme-color" content="#0d0d0e">
    @if ($empresa->logo_url)<link rel="icon" href="{{ $empresa->logo_url }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --acento: {{ $empresa->cor_primaria }};
            --ouro: #c8a165;
            --escuro: #0d0d0e;
            --creme: #f4efe6;
            --creme-2: #e9e1d3;
            --texto: #1b1a18;
            --suave: #6d675e;
            --borda: #ddd3c2;
            --erro: #b42318;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: Inter, system-ui, sans-serif; color: var(--texto); background: var(--escuro) url('{{ $empresa->capa_url ?? asset('img/pagina/salao.jpg') }}') center/cover fixed; display: flex; flex-direction: column; align-items: center; padding: 28px 16px 48px; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(13,13,14,.82); z-index: -1; }
        .topo { text-align: center; color: #f5f1ea; margin-bottom: 22px; }
        .topo img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; margin: 0 auto 10px; display: block; border: 2px solid rgba(255,255,255,.7); }
        .topo strong { font-family: "Bebas Neue", Impact, sans-serif; font-weight: 400; font-size: 2rem; letter-spacing: .04em; }
        .cartao { width: 100%; max-width: 520px; background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 40px 80px -30px rgba(0,0,0,.7); animation: entrar .6s cubic-bezier(.2,.8,.2,1); }
        .listra { height: 6px; background: repeating-linear-gradient(-45deg, #c62828 0 12px, #fff 12px 24px, #1e4fa8 24px 36px, #fff 36px 48px); background-size: 68px 100%; animation: correr 2s linear infinite; }
        .corpo { padding: 28px 24px; }
        .selo { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: .8rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .selo::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .selo.agendado { background: #fff4e0; color: #9a6400; }
        .selo.confirmado { background: #e7f6ec; color: #1a7f37; }
        .selo.cancelado { background: #fef3f2; color: var(--erro); }
        .selo.encerrado { background: #eee; color: #555; }
        h1 { font-family: "Bebas Neue", Impact, sans-serif; font-weight: 400; font-size: 2.6rem; line-height: 1; margin: 16px 0 4px; }
        h1 em { font-family: "Playfair Display", serif; font-style: italic; color: #9a7438; font-size: .8em; }
        .quando { font-size: 1.1rem; color: var(--suave); margin: 0 0 20px; }
        .quando b { color: var(--texto); }
        .detalhes { background: var(--creme); border-radius: 16px; padding: 6px 18px; margin-bottom: 22px; }
        .detalhes div { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; }
        .detalhes div + div { border-top: 1px dashed var(--borda); }
        .detalhes span:first-child { color: var(--suave); }
        .detalhes span:last-child { font-weight: 600; text-align: right; }
        .botoes { display: grid; gap: 10px; }
        .botao { display: flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 999px; padding: 15px 22px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; background: var(--escuro); color: #fff; transition: transform .15s, opacity .15s; }
        .botao:hover { transform: translateY(-1px); }
        .botao:disabled { opacity: .5; cursor: wait; transform: none; }
        .botao.ouro { background: var(--ouro); color: #1b1408; }
        .botao.claro { background: #fff; color: var(--texto); box-shadow: inset 0 0 0 1.5px var(--borda); }
        .botao.perigo { background: #fff; color: var(--erro); box-shadow: inset 0 0 0 1.5px #f3c4c0; }
        .botao.whats { background: #25d366; color: #fff; }
        .aviso { background: var(--creme); border-radius: 14px; padding: 14px 16px; color: var(--suave); font-size: .95rem; margin-bottom: 18px; }
        .erro { color: var(--erro); background: #fef3f2; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
        .ok { color: #1a7f37; background: #e7f6ec; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; }
        .remarcar h2 { font-size: 1.1rem; margin: 6px 0 12px; }
        .dias { display: flex; gap: 8px; overflow-x: auto; padding: 2px 2px 10px; }
        .dia { flex: none; width: 64px; padding: 10px 0; border: 2px solid var(--creme-2); border-radius: 14px; background: #fff; font: inherit; cursor: pointer; text-align: center; color: inherit; }
        .dia small { display: block; font-size: .7rem; color: var(--suave); text-transform: uppercase; }
        .dia strong { font-family: "Bebas Neue", sans-serif; font-weight: 400; font-size: 1.7rem; line-height: 1.1; }
        .dia.sel { background: var(--escuro); border-color: var(--escuro); color: #fff; }
        .dia.sel small { color: var(--ouro); }
        .horarios { display: grid; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 8px; margin: 12px 0 16px; }
        .hora { padding: 11px 0; border: 2px solid var(--creme-2); border-radius: 12px; background: #fff; font: inherit; font-weight: 700; cursor: pointer; color: inherit; }
        .hora.sel, .hora:hover { border-color: var(--acento); background: var(--acento); color: #fff; }
        .vazio { color: var(--suave); text-align: center; padding: 14px; background: var(--creme); border-radius: 12px; margin: 12px 0 16px; }
        .rodape { color: rgba(245,241,234,.6); font-size: .88rem; margin-top: 22px; text-align: center; }
        .rodape a { color: #f5f1ea; }
        [hidden] { display: none !important; }
        @keyframes entrar { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
        @keyframes correr { to { background-position: 68px 0; } }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
    <div class="topo">
        @if ($empresa->logo_url)<img src="{{ $empresa->logo_url }}" alt="">@endif
        <strong>{{ $empresa->nome }}</strong>
    </div>

    <main class="cartao">
        <div class="listra"></div>
        <div class="corpo">
            <span class="selo {{ $situacao[1] }}" id="selo">{{ $situacao[0] }}</span>
            <h1>Olá, <em>{{ strtok($a->cliente->nome, ' ') }}</em></h1>
            <p class="quando" id="quando"><b>{{ ucfirst($dataLonga) }}</b> às <b>{{ $inicio->format('H:i') }}</b></p>

            <div class="detalhes">
                <div><span>Serviços</span><span>{{ $a->itens->pluck('nome')->join(' + ') }}</span></div>
                <div><span>Barbeiro</span><span>{{ $a->funcionario->nome }}</span></div>
                <div><span>Valor</span><span>R$ {{ number_format((float) $a->valor_total, 2, ',', '.') }}</span></div>
                @if ($endereco)<div><span>Endereço</span><span>{{ $endereco }}</span></div>@endif
            </div>

            <div class="erro" id="erro" hidden></div>
            <div class="ok" id="ok" hidden></div>

            @if ($ativo)
                <div id="acoes" class="botoes">
                    @if ($a->status === 'agendado')
                        <button class="botao ouro" type="button" id="confirmar">✓ Confirmar presença</button>
                    @endif
                    @if ($podeAlterar)
                        <button class="botao claro" type="button" id="abrir-remarcar">Remarcar horário</button>
                        <button class="botao perigo" type="button" id="cancelar">Cancelar horário</button>
                    @else
                        <p class="aviso">Remarcações e cancelamentos pelo link só até {{ $empresa->cancelamento_antecedencia_horas }} h antes do horário. Precisa mudar? Fale com a barbearia.</p>
                    @endif
                    @if ($whatsapp)<a class="botao whats" href="{{ $whatsapp }}" target="_blank" rel="noopener">Falar com a barbearia</a>@endif
                </div>

                @if ($podeAlterar)
                    <div class="remarcar" id="remarcar" hidden>
                        <h2>Escolha o novo dia e horário</h2>
                        <div class="dias" id="dias"></div>
                        <div id="horas"></div>
                        <div class="botoes">
                            <button class="botao ouro" type="button" id="salvar-remarcar" disabled>Confirmar novo horário</button>
                            <button class="botao claro" type="button" id="fechar-remarcar">Voltar</button>
                        </div>
                    </div>
                @endif
            @else
                <p class="aviso">
                    {{ $a->status === 'cancelado' ? 'Este horário foi cancelado.' : 'Este horário já passou.' }}
                    Quer marcar outro? É rapidinho.
                </p>
                <div class="botoes"><a class="botao ouro" href="{{ url($empresa->slug) }}#agendar">Agendar novo horário</a></div>
            @endif
        </div>
    </main>

    <p class="rodape"><a href="{{ url($empresa->slug) }}">Ver página da {{ $empresa->nome }}</a></p>

    @if ($ativo)
    <script>
    (function () {
        const C = @json($config);
        const $ = (s) => document.querySelector(s);
        const DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        const DIAS_LONGOS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        const somarDias = (iso, n) => { const d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };

        function mostrar(tipo, texto) {
            $('#erro').hidden = tipo !== 'erro';
            $('#ok').hidden = tipo !== 'ok';
            if (tipo === 'erro' || tipo === 'ok') $('#' + tipo).textContent = texto;
        }
        function selo(texto, classe) { const s = $('#selo'); s.textContent = texto; s.className = 'selo ' + classe; }

        async function enviar(url, corpo) {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(corpo || {}),
            });
            const dados = await resp.json().catch(() => ({}));
            if (!resp.ok) {
                const msgs = dados.errors ? Object.values(dados.errors).flat() : [];
                throw new Error(msgs[0] || dados.message || 'Não foi possível concluir. Tente de novo.');
            }
            return dados;
        }
        async function acao(botao, fn) {
            botao.disabled = true;
            try { await fn(); } catch (e) { mostrar('erro', e.message === 'Failed to fetch' ? 'Sem conexão. Tente de novo.' : e.message); }
            finally { botao.disabled = false; }
        }

        const confirmar = $('#confirmar');
        if (confirmar) confirmar.addEventListener('click', () => acao(confirmar, async () => {
            await enviar(C.urlConfirmar);
            selo('Confirmado', 'confirmado');
            confirmar.remove();
            mostrar('ok', 'Presença confirmada. Te esperamos!');
        }));

        const cancelar = $('#cancelar');
        if (cancelar) cancelar.addEventListener('click', () => {
            if (!confirm('Cancelar este horário? Ele fica livre para outro cliente.')) return;
            acao(cancelar, async () => {
                await enviar(C.urlCancelar);
                selo('Cancelado', 'cancelado');
                $('#acoes').replaceChildren();
                const novo = document.createElement('a');
                novo.className = 'botao ouro';
                novo.href = @json(url($empresa->slug).'#agendar');
                novo.textContent = 'Agendar outro horário';
                $('#acoes').append(novo);
                mostrar('ok', 'Horário cancelado. Obrigado por avisar!');
            });
        });

        // Remarcar: mesmo barbeiro e serviços, outro dia/horário
        const painel = $('#remarcar');
        if (!painel) return;
        let escolha = { data: null, hora: null };
        $('#abrir-remarcar').addEventListener('click', () => {
            $('#acoes').hidden = true;
            painel.hidden = false;
            mostrar('nada');
            if (!$('#dias').children.length) montarDias();
        });
        $('#fechar-remarcar').addEventListener('click', () => { painel.hidden = true; $('#acoes').hidden = false; });

        function montarDias() {
            for (let i = 0; i < Math.min(21, C.diasAntecedencia + 1); i++) {
                const iso = somarDias(C.hoje, i);
                const d = new Date(iso + 'T12:00:00');
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'dia';
                b.dataset.data = iso;
                b.innerHTML = '<small>' + (i === 0 ? 'hoje' : DIAS[d.getDay()]) + '</small><strong>' + d.getDate() + '</strong><small>' + MESES[d.getMonth()].slice(0, 3) + '</small>';
                b.addEventListener('click', () => escolherDia(iso));
                $('#dias').append(b);
            }
            escolherDia(C.hoje);
        }

        let consulta = 0;
        async function escolherDia(iso) {
            escolha = { data: iso, hora: null };
            $('#salvar-remarcar').disabled = true;
            document.querySelectorAll('.dia').forEach((d) => d.classList.toggle('sel', d.dataset.data === iso));
            const area = $('#horas');
            area.innerHTML = '<p class="vazio">Buscando horários…</p>';
            const minha = ++consulta;
            try {
                const resp = await fetch(C.urlHorarios + '?data=' + iso, { headers: { Accept: 'application/json' } });
                const horas = await resp.json();
                if (minha !== consulta) return;
                if (!horas.length) { area.innerHTML = '<p class="vazio">Sem horários livres neste dia.</p>'; return; }
                const grade = document.createElement('div');
                grade.className = 'horarios';
                horas.forEach((h) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'hora';
                    b.textContent = h;
                    b.addEventListener('click', () => {
                        grade.querySelectorAll('.hora').forEach((x) => x.classList.remove('sel'));
                        b.classList.add('sel');
                        escolha.hora = h;
                        $('#salvar-remarcar').disabled = false;
                    });
                    grade.append(b);
                });
                area.replaceChildren(grade);
            } catch (e) {
                if (minha === consulta) area.innerHTML = '<p class="vazio">Não foi possível carregar os horários.</p>';
            }
        }

        const salvar = $('#salvar-remarcar');
        salvar.addEventListener('click', () => acao(salvar, async () => {
            const r = await enviar(C.urlRemarcar, escolha);
            const d = new Date(r.data + 'T12:00:00');
            $('#quando').innerHTML = '<b>' + DIAS_LONGOS[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + '</b> às <b>' + r.hora + '</b>';
            selo('Confirmado', 'confirmado');
            painel.hidden = true;
            $('#acoes').hidden = false;
            const conf = $('#confirmar');
            if (conf) conf.remove();
            mostrar('ok', 'Horário remarcado! Te esperamos no novo dia.');
        }));
    })();
    </script>
    @endif
</body>
</html>
