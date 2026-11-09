@php($p = config('plataforma'))
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') | {{ $p['nome'] }}</title>
    <meta name="robots" content="noindex">
    <link href="{{ asset('fonts/fontes.css') }}" rel="stylesheet">
    <style>
        :root {
            --fundo: #f6f4f0;
            --superficie: #ffffff;
            --texto: #1f1d1a;
            --suave: #6b655c;
            --borda: #e3ddd2;
            --destaque: #8a6a32;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.65; }
        .conteudo { max-width: 820px; margin: 0 auto; padding: 0 20px; }
        header { padding: 28px 0 8px; font-weight: 700; }
        main { background: var(--superficie); border: 1px solid var(--borda); border-radius: 16px; padding: 32px; margin: 16px auto 32px; }
        h1 { margin: 0 0 4px; font-size: clamp(1.6rem, 4vw, 2.1rem); line-height: 1.2; }
        h2 { margin: 32px 0 8px; font-size: 1.2rem; }
        h3 { margin: 20px 0 6px; font-size: 1rem; }
        .versao { margin: 0 0 24px; color: var(--suave); font-size: .9rem; }
        a { color: var(--destaque); }
        ul { padding-left: 22px; }
        li { margin: 4px 0; }
        .tabela { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; vertical-align: top; padding: 10px 8px; border-bottom: 1px solid var(--borda); }
        th { background: var(--fundo); }
        .destaque { padding: 14px 16px; border-left: 4px solid var(--destaque); background: var(--fundo); border-radius: 8px; }
        footer { padding: 0 0 32px; color: var(--suave); font-size: .88rem; }
        footer p { margin: 2px 0; }
        @media (max-width: 560px) { main { padding: 22px 18px; } }
    </style>
</head>
<body>
    <header class="conteudo">{{ $p['nome'] }}</header>

    <main class="conteudo">
        @yield('conteudo')
    </main>

    <footer class="conteudo">
        <p><strong>{{ $p['razao_social'] }}</strong> · CNPJ {{ $p['cnpj'] }}</p>
        <p>{{ $p['endereco'] }}</p>
        <p>Contato: <a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>@if ($p['telefone']) · {{ $p['telefone'] }}@endif</p>
        <p>Encarregado de dados: {{ $p['encarregado_nome'] }} · <a href="mailto:{{ $p['encarregado_email'] }}">{{ $p['encarregado_email'] }}</a></p>
        <p><a href="{{ route('privacidade') }}">Política de privacidade</a> · <a href="{{ route('termos') }}">Termos de uso</a></p>
    </footer>
</body>
</html>
