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
