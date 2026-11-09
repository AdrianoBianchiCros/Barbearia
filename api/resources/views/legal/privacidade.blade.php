@extends('legal.layout')
@php($p = config('plataforma'))
@php($versao = \Carbon\Carbon::parse($p['versao_privacidade'])->format('d/m/Y'))

@section('titulo', 'Política de privacidade')

@section('conteudo')
    <h1>Política de privacidade</h1>
    <p class="versao">Versão de {{ $versao }} · vigente a partir desta data</p>

    <p>Esta política explica como o sistema de agendamento e gestão para barbearias da <strong>{{ $p['nome'] }}</strong>
        ({{ $p['razao_social'] }}, CNPJ {{ $p['cnpj'] }}) trata dados pessoais, em conformidade com a
        Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018, “LGPD”).</p>

    <h2>1. Quem é responsável pelos seus dados</h2>
    <ul>
        <li><strong>Donos e equipe das barbearias</strong> (quem cria a conta e usa o painel): a {{ $p['nome'] }} é a
            <em>controladora</em> desses dados.</li>
        <li><strong>Clientes das barbearias</strong> (quem agenda um horário): a <em>controladora</em> é a barbearia
            onde você agendou. A {{ $p['nome'] }} é a <em>operadora</em>: guarda e processa os dados em nome da barbearia,
            seguindo as instruções dela e esta política.</li>
    </ul>

    <h2>2. Quais dados coletamos e para quê</h2>
    <p>Coletamos apenas o necessário para cada finalidade:</p>
    <div class="tabela">
        <table>
            <thead><tr><th>Dado</th><th>Para quê</th><th>Base legal (LGPD, art. 7º)</th></tr></thead>
            <tbody>
                <tr><td>Nome e WhatsApp do cliente</td><td>Registrar o agendamento, identificar quem será atendido e falar sobre o horário</td><td>Execução de contrato / procedimentos preliminares (inciso V)</td></tr>
                <tr><td>Serviços, data, horário e observação do agendamento</td><td>Organizar a agenda da barbearia</td><td>Execução de contrato (inciso V)</td></tr>
                <tr><td>Mensagens de confirmação e lembrete pelo WhatsApp</td><td>Avisar sobre o horário marcado</td><td>Consentimento (inciso I), que pode ser retirado a qualquer momento</td></tr>
                <tr><td>Atendimentos, pagamentos e comissões</td><td>Controle financeiro da barbearia e obrigações fiscais</td><td>Obrigação legal (inciso II) e execução de contrato (inciso V)</td></tr>
                <tr><td>Nome, e-mail e senha (criptografada) da equipe</td><td>Acesso ao painel</td><td>Execução de contrato (inciso V)</td></tr>
                <tr><td>Registro do aceite (data, hora, IP e navegador)</td><td>Comprovar que você aceitou os documentos</td><td>Cumprimento de obrigação legal e exercício regular de direitos (incisos II e VI)</td></tr>
                <tr><td>Endereço IP e registros de acesso</td><td>Segurança e prevenção de fraudes</td><td>Obrigação legal (Marco Civil da Internet, art. 15) e legítimo interesse (inciso IX)</td></tr>
            </tbody>
        </table>
    </div>
    <p>Não pedimos CPF, data de nascimento, endereço nem dados sensíveis para agendar. <strong>Não vendemos dados</strong>
        e não usamos seus dados para publicidade de terceiros.</p>

    <h2>3. Comunicações de marketing</h2>
    <p>Promoções e novidades só são enviadas a quem der um consentimento <strong>separado e específico</strong> para isso,
        que nunca vem marcado por padrão. Os avisos sobre o seu horário não dependem dele. Toda mensagem de marketing
        traz um meio de descadastro, e no WhatsApp basta responder <strong>PARAR</strong>.</p>

    <h2>4. Por quanto tempo guardamos</h2>
    <div class="tabela">
        <table>
            <thead><tr><th>Dado</th><th>Prazo</th></tr></thead>
            <tbody>
                <tr><td>Cadastro do cliente (nome e WhatsApp)</td><td>Enquanto houver atendimentos; anonimizado após 2 anos sem nenhum atendimento</td></tr>
                <tr><td>Histórico de atendimentos, pagamentos e comissões</td><td>5 anos após o atendimento (prazo da legislação fiscal); depois, apagado ou anonimizado</td></tr>
                <tr><td>Histórico de mensagens do WhatsApp</td><td>12 meses</td></tr>
                <tr><td>Conta da barbearia e da equipe</td><td>Enquanto a conta estiver ativa; até 6 meses após o encerramento, exceto o que a lei exige guardar</td></tr>
                <tr><td>Registros de aceite</td><td>Enquanto durar a relação e por mais 5 anos, para comprovação</td></tr>
                <tr><td>Registros de acesso (IP)</td><td>6 meses (Marco Civil da Internet, art. 15)</td></tr>
            </tbody>
        </table>
    </div>

    <h2>5. Com quem compartilhamos</h2>
    <ul>
        <li><strong>A barbearia</strong> onde você agendou, que vê seu nome, WhatsApp e o horário.</li>
        <li><strong>Provedor de hospedagem</strong> do sistema, que armazena os dados com medidas de segurança.</li>
        <li><strong>Meta (WhatsApp Business)</strong>, apenas para enviar as mensagens que você autorizou. Os servidores da
            Meta podem ficar fora do Brasil; essa transferência segue o art. 33 da LGPD.</li>
        <li><strong>Autoridades</strong>, quando houver obrigação legal ou ordem judicial.</li>
    </ul>

    <h2 id="cookies">6. Cookies e armazenamento no aparelho</h2>
    <ul>
        <li>Usamos apenas <strong>cookies necessários</strong> para o site funcionar (sessão e proteção contra fraudes).
            Eles não rastreiam você.</li>
        <li>A página da barbearia pode guardar no <em>seu</em> aparelho o nome e o WhatsApp digitados, para preencher
            o próximo agendamento. Esses dados não saem do aparelho por causa disso, e você pode apagá-los limpando os
            dados do navegador.</li>
        <li>O <strong>mapa do Google</strong> só é carregado se você permitir no aviso de cookies. Se recusar, nenhum dado
            é enviado ao Google; o botão “Como chegar” continua disponível.</li>
        <li>Não usamos ferramentas de análise, publicidade ou rastreamento. Se um dia usarmos, só depois do seu consentimento.</li>
    </ul>

    <h2>7. Crianças e adolescentes</h2>
    <p>O agendamento online é feito por adultos. Para agendar o atendimento de uma criança ou adolescente, quem preenche
        declara ser o pai, a mãe ou o responsável legal e consente com o tratamento dos dados, como exige o art. 14 da LGPD.
        Não pedimos idade, documentos nem outros dados da criança; o contato fica sendo o do responsável.</p>

    <h2>8. Seus direitos</h2>
    <p>Pelo art. 18 da LGPD, você pode pedir, a qualquer momento e sem custo:</p>
    <ul>
        <li>confirmação de que tratamos seus dados e <strong>acesso</strong> a eles;</li>
        <li><strong>correção</strong> de dados incompletos ou errados;</li>
        <li><strong>anonimização, bloqueio ou eliminação</strong> de dados desnecessários ou tratados em desacordo com a lei;</li>
        <li><strong>portabilidade</strong> e <strong>eliminação</strong> dos dados tratados com o seu consentimento;</li>
        <li>informação sobre com quem compartilhamos;</li>
        <li><strong>revogação do consentimento</strong> (por exemplo, parar de receber mensagens no WhatsApp).</li>
    </ul>
    <p class="destaque">Para exercer seus direitos, fale com a barbearia onde agendou ou escreva para o nosso encarregado:
        <a href="mailto:{{ $p['encarregado_email'] }}">{{ $p['encarregado_email'] }}</a>. Respondemos em até 15 dias.
        Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD) em
        <a href="https://www.gov.br/anpd" target="_blank" rel="noopener">gov.br/anpd</a>.</p>

    <h2>9. Encarregado de dados (DPO)</h2>
    <p>{{ $p['encarregado_nome'] }} · <a href="mailto:{{ $p['encarregado_email'] }}">{{ $p['encarregado_email'] }}</a></p>

    <h2>10. Segurança e incidentes</h2>
    <p>Usamos conexão criptografada (HTTPS), senhas protegidas por criptografia, separação dos dados de cada barbearia e
        acesso restrito por perfil. Se ocorrer um incidente de segurança que possa trazer risco ou dano relevante a você,
        comunicaremos a ANPD e as pessoas afetadas em até <strong>3 dias úteis</strong>, como determina a regulamentação
        da ANPD, informando o que aconteceu, quais dados foram afetados e o que está sendo feito.</p>

    <h2>11. Alterações desta política</h2>
    <p>Quando esta política mudar, a nova versão será publicada nesta página com a data atualizada. Mudanças relevantes
        serão avisadas no painel e, quando necessário, pediremos um novo aceite.</p>
@endsection
