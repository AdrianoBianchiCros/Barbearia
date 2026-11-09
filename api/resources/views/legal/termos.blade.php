@extends('legal.layout')
@php($p = config('plataforma'))
@php($versao = \Carbon\Carbon::parse($p['versao_termos'])->format('d/m/Y'))

@section('titulo', 'Termos de uso')

@section('conteudo')
    <h1>Termos de uso</h1>
    <p class="versao">Versão de {{ $versao }} · vigente a partir desta data</p>

    <p>Estes termos regem o uso do sistema de agendamento e gestão para barbearias oferecido pela
        <strong>{{ $p['razao_social'] }}</strong> (“{{ $p['nome'] }}”), CNPJ {{ $p['cnpj'] }}, com sede em {{ $p['endereco'] }}.
        Ao criar uma conta ou agendar um horário, você declara que leu e concorda com estes termos e com a
        <a href="{{ route('privacidade') }}">Política de privacidade</a>.</p>

    <h2>1. O que o sistema oferece</h2>
    <ul>
        <li>Para as <strong>barbearias</strong>: painel com agenda, cadastro de serviços, equipe e clientes, comanda, caixa,
            comissões, relatórios e página pública com agendamento online.</li>
        <li>Para os <strong>clientes das barbearias</strong>: agendamento online e avisos sobre o horário marcado.</li>
    </ul>

    <h2>2. Conta da barbearia</h2>
    <ul>
        <li>Quem cria a conta declara ter poderes para representar a barbearia e informar dados verdadeiros.</li>
        <li>O dono é responsável pelos acessos que cria para a equipe e por manter as senhas em sigilo.</li>
        <li>A barbearia é a <strong>controladora</strong> dos dados dos seus clientes (LGPD) e se compromete a usá-los apenas
            para atendê-los, a respeitar os pedidos dos titulares e a não cadastrar ninguém para envio de mensagens sem
            autorização. A {{ $p['nome'] }} atua como <strong>operadora</strong> desses dados.</li>
    </ul>

    <h2>3. Agendamento pelo cliente</h2>
    <ul>
        <li>Quem agenda declara ter 18 anos ou mais, ou ser o responsável legal por quem será atendido.</li>
        <li>O atendimento, o preço e as regras de cancelamento são definidos pela barbearia, que é a responsável pelo serviço prestado.</li>
        <li>Para evitar abusos, o agendamento online tem limites por número de WhatsApp e por período.</li>
    </ul>

    <h2>4. Uso permitido</h2>
    <p>Não é permitido usar o sistema para enviar mensagens não solicitadas, cadastrar dados de terceiros sem base legal,
        tentar acessar dados de outras barbearias, sobrecarregar o serviço ou violar a lei. O descumprimento pode levar à
        suspensão da conta.</p>

    <h2>5. Planos, preço e cancelamento</h2>
    <ul>
        <li>Quando houver cobrança, o plano, o <strong>valor total</strong>, a periodicidade e as condições de renovação serão
            informados de forma clara <strong>antes</strong> da contratação, sem taxas escondidas.</li>
        <li>Planos com renovação automática terão aviso antes de cada renovação.</li>
        <li>Nas contratações pela internet, você pode desistir em até <strong>7 dias</strong> da contratação, com devolução
            integral do valor pago (art. 49 do Código de Defesa do Consumidor).</li>
        <li>O cancelamento pode ser feito a qualquer momento, pelo mesmo meio usado para contratar, sem burocracia.</li>
    </ul>

    <h2>6. Disponibilidade</h2>
    <p>Trabalhamos para manter o sistema disponível e seguro, mas podem ocorrer interrupções para manutenção ou por falhas
        de terceiros (internet, hospedagem, WhatsApp). Sempre que possível, avisaremos manutenções programadas.</p>

    <h2>7. Encerramento da conta</h2>
    <p>O dono pode pedir o encerramento da conta a qualquer momento pelo e-mail
        <a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>. Antes do encerramento, a barbearia pode solicitar uma cópia
        dos seus dados. Após o encerramento, os dados seguem os prazos da <a href="{{ route('privacidade') }}">Política de privacidade</a>.</p>

    <h2>8. Propriedade intelectual</h2>
    <p>O sistema, sua marca e seu código pertencem à {{ $p['nome'] }}. A barbearia mantém a titularidade dos seus dados,
        textos, fotos e logotipo enviados.</p>

    <h2>9. Alterações destes termos</h2>
    <p>Podemos atualizar estes termos. A nova versão será publicada nesta página com a data atualizada e, se a mudança
        for relevante, avisaremos no painel e pediremos um novo aceite.</p>

    <h2>10. Contato e foro</h2>
    <p>Dúvidas: <a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>. Fica eleito o foro do domicílio do consumidor,
        quando a relação for de consumo; nos demais casos, o foro da comarca da sede da {{ $p['nome'] }}.</p>
@endsection
