@php
    $r = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $c = $contratante;
    $p = $contratada;
    $extenso = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze'];
    $mensal = $ciclo === 'mensal';
    // Textos que mudam com o plano: mensal ou pago por vários meses de uma vez.
    $valorDoPlano = $mensal ? 'mensais' : ($meses ? "a cada período de {$meses} meses" : "pelo período {$ciclo}");
    $cobranca = $mensal ? 'mensal' : ($meses ? "a cada {$meses} meses" : 'por período');
    $renovacao = $mensal ? 'mensal' : ($meses ? "a cada {$meses} meses" : 'a cada período');
    $k = 0; // numeração da cláusula 3 (a taxa de instalação só aparece quando existe)
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Contrato de prestação de serviço — {{ $c['nome'] }}</title>
<style>
    @page { margin: 2.2cm 2cm 2cm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; line-height: 1.5; color: #1a1a1a; }
    h1 { font-size: 14px; text-align: center; margin: 0 0 4px; text-transform: uppercase; }
    .sub { text-align: center; font-size: 10px; color: #555; margin: 0 0 18px; }
    h2 { font-size: 11px; margin: 16px 0 6px; text-transform: uppercase; }
    p { margin: 0 0 6px; text-align: justify; }
    .partes { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .partes td { vertical-align: top; padding: 8px 10px; border: 1px solid #ccc; width: 50%; }
    .partes strong.rotulo { display: block; font-size: 9px; color: #555; text-transform: uppercase; margin-bottom: 2px; }
    .assinaturas { width: 100%; margin-top: 46px; }
    .assinaturas td { width: 50%; text-align: center; padding: 0 12px; vertical-align: top; font-size: 10px; }
    .linha { border-top: 1px solid #333; margin-bottom: 4px; }
    .rodape { position: fixed; bottom: -1.2cm; left: 0; right: 0; text-align: center; font-size: 8px; color: #777; }
</style>
</head>
<body>
<div class="rodape">{{ $p['nome'] }} · CNPJ {{ $p['cnpj'] }} · Contrato de prestação de serviço e licença de uso de software — {{ $c['nome'] }}</div>

<h1>Contrato de prestação de serviço e licença de uso de software (SaaS)</h1>
<p class="sub">Sistema de Gestão para Barbearias</p>

<table class="partes">
    <tr>
        <td>
            <strong class="rotulo">Contratada</strong>
            <strong>{{ $p['razao_social'] }}</strong>@if ($p['nome'] && $p['nome'] !== $p['razao_social']) ({{ $p['nome'] }})@endif<br>
            CNPJ: {{ $p['cnpj'] }}<br>
            Endereço: {{ $p['endereco'] }}<br>
            E-mail: {{ $p['email'] }}@if ($p['telefone'])<br>Telefone: {{ $p['telefone'] }}@endif
        </td>
        <td>
            <strong class="rotulo">Contratante</strong>
            <strong>{{ $c['razao_social'] }}</strong>@if ($c['nome'] !== $c['razao_social']) ({{ $c['nome'] }})@endif<br>
            {{ $c['tipo_documento'] }}: {{ $c['documento'] ?? '—' }}<br>
            Endereço: {{ $c['endereco'] ?? '—' }}<br>
            Telefone: {{ $c['telefone'] ?? '—' }} · E-mail: {{ $c['email'] }}<br>
            Representante: {{ $c['representante'] }} ({{ $c['representante_email'] }})
        </td>
    </tr>
</table>

<p>As partes acima identificadas celebram o presente contrato, que se rege pelas cláusulas a seguir.</p>

<h2>Cláusula 1 – Do objeto</h2>
<p>1.1. O objeto deste contrato é a concessão, pela CONTRATADA ao CONTRATANTE, do direito de uso, não exclusivo e
    intransferível, do sistema de gestão para barbearias disponibilizado pela internet no modelo SaaS (software como
    serviço), incluindo agenda, agendamento online pela página da barbearia, cadastro de clientes, serviços e equipe,
    comandas, caixa, comissões, relatórios e os demais recursos disponíveis no plano contratado, além da hospedagem,
    manutenção, atualizações e suporte técnico.</p>
<p>1.2. Este contrato não transfere ao CONTRATANTE a propriedade do sistema, de seu código-fonte ou de qualquer
    direito de propriedade intelectual, que permanecem da CONTRATADA.</p>

<h2>Cláusula 2 – Do início e do acesso</h2>
<p>2.1. O serviço tem início em <strong>{{ $inicio->format('d/m/Y') }}</strong>, data prevista para disponibilização do
    acesso à plataforma. O acesso do responsável é feito com o e-mail {{ $c['representante_email'] }}, e o CONTRATANTE
    poderá cadastrar outros usuários (recepção e barbeiros), respondendo pelo uso que fizerem do sistema.</p>
<p>2.2. O CONTRATANTE é responsável pela guarda das senhas de acesso e por comunicar imediatamente à CONTRATADA
    qualquer uso não autorizado.</p>

<h2>Cláusula 3 – Do preço e da forma de pagamento</h2>
<p>3.{{ ++$k }}. Pela disponibilização da plataforma SaaS, o CONTRATANTE pagará à CONTRATADA o valor de
    <strong>{{ $r($valor) }} ({{ $valor_extenso }})</strong> {{ $valorDoPlano }}, referente à assinatura do plano
    contratado ({{ $plano }}).</p>
@if ($taxa_instalacao)
<p>3.{{ ++$k }}. No início da contratação, será cobrada uma taxa única de instalação e configuração inicial da
    plataforma, no valor de <strong>{{ $r($taxa_instalacao) }} ({{ $taxa_extenso }})</strong>, juntamente com a primeira
    {{ $mensal ? 'mensalidade' : 'cobrança da assinatura' }}. Essa taxa não será recorrente e será destinada aos serviços
    iniciais de preparação e disponibilização do ambiente do CONTRATANTE.</p>
@endif
<p>3.{{ ++$k }}. A assinatura será cobrada de forma recorrente, {{ $cobranca }} e antecipada, mediante pagamento via
    <strong>{{ $forma_pagamento }}</strong>, com vencimento na data correspondente ao início de cada novo ciclo
    {{ $mensal ? 'mensal' : 'de cobrança' }}.</p>
<p>3.{{ ++$k }}. O atraso no pagamento sujeita o CONTRATANTE à multa de 2% (dois por cento) e juros de 1% (um por
    cento) ao mês sobre o valor devido. Após {{ $carencia_dias }}{{ $carencia_dias <= 12 ? ' ('.$extenso[$carencia_dias].')' : '' }}
    dias de atraso, a CONTRATADA poderá suspender temporariamente o acesso ao sistema até a regularização do pagamento,
    mediante comunicação ao CONTRATANTE.</p>
<p>3.{{ ++$k }}. Os valores poderão ser reajustados a cada 12 (doze) meses pela variação do IPCA/IBGE ou, na sua
    falta, de outro índice oficial que o substitua, mediante aviso prévio de 30 (trinta) dias.</p>

<h2>Cláusula 4 – Da vigência, renovação e rescisão</h2>
<p>4.1. O presente contrato terá início na data de disponibilização do acesso à plataforma SaaS, sendo celebrado por
    prazo indeterminado, sem exigência de período mínimo de permanência.</p>
<p>4.2. O contrato permanecerá ativo mediante renovação automática {{ $renovacao }}, com cobrança recorrente e
    antecipada do valor correspondente ao plano contratado, enquanto não houver solicitação de cancelamento por
    qualquer das partes.</p>
<p>4.3. O CONTRATANTE poderá solicitar o cancelamento a qualquer momento, sem incidência de multa rescisória ou
    cobrança de {{ $mensal ? 'mensalidades futuras' : 'períodos futuros' }}, mediante comunicação pelos canais oficiais
    da CONTRATADA. O cancelamento produzirá efeitos ao término do período {{ $mensal ? 'mensal ' : '' }}já pago,
    permanecendo o acesso disponível até essa data. Os valores referentes a períodos já iniciados não serão
    reembolsados, ressalvadas as hipóteses legais aplicáveis.</p>
<p>4.4. Qualquer das partes poderá rescindir o presente contrato em caso de descumprimento das obrigações contratuais
    pela outra parte, mediante notificação e concessão de prazo razoável para regularização, quando cabível, sem
    prejuízo dos valores devidos pelos serviços efetivamente prestados.</p>
<p>4.5. A CONTRATADA poderá rescindir o contrato mediante aviso prévio de 30 (trinta) dias ou imediatamente em
    situações de uso ilícito da plataforma, fraude, comprometimento da segurança do sistema ou inadimplência superior
    a 30 (trinta) dias, observadas as disposições legais aplicáveis.</p>

<h2>Cláusula 5 – Das obrigações da CONTRATADA</h2>
<p>5.1. Manter o sistema disponível pela internet, empregando os melhores esforços para que funcione sem interrupções,
    ressalvadas as paradas para manutenção, as falhas de provedores de internet e de hospedagem e os casos fortuitos ou
    de força maior.</p>
<p>5.2. Fazer cópias de segurança periódicas dos dados, aplicar as correções e atualizações do sistema e prestar
    suporte técnico pelos canais oficiais, em dias úteis e horário comercial.</p>
<p>5.3. Adotar medidas técnicas e administrativas de segurança para proteger os dados armazenados.</p>

<h2>Cláusula 6 – Das obrigações do CONTRATANTE</h2>
<p>6.1. Pagar os valores nas datas combinadas; informar dados verdadeiros e mantê-los atualizados; usar o sistema de
    forma lícita e de acordo com os Termos de Uso da plataforma.</p>
<p>6.2. Responder pelas informações que cadastra e pelo relacionamento com seus clientes, inclusive pela obtenção das
    bases legais e autorizações necessárias para o envio de mensagens (WhatsApp, e-mail) pelo sistema.</p>

<h2>Cláusula 7 – Da proteção de dados pessoais (LGPD)</h2>
<p>7.1. <strong>Papéis das partes.</strong> Para os dados pessoais inseridos pelo CONTRATANTE na plataforma,
    relacionados aos seus clientes, funcionários e demais usuários, o CONTRATANTE atuará como CONTROLADOR e a
    CONTRATADA como OPERADORA, nos termos da Lei nº 13.709/2018 (Lei Geral de Proteção de Dados Pessoais – LGPD),
    sempre que essa divisão de papéis corresponder às atividades efetivamente realizadas.</p>
<p>7.2. <strong>Finalidade do tratamento.</strong> A CONTRATADA realizará o tratamento dos dados pessoais na medida
    necessária para disponibilizar, operar, manter e prestar suporte à plataforma SaaS, observando as instruções
    legítimas e documentadas do CONTRATANTE e a legislação aplicável. A CONTRATADA também poderá tratar dados
    estritamente necessários para faturamento, segurança, prevenção a fraudes e cumprimento de obrigações legais
    próprias, atuando, nesses casos, conforme os papéis definidos pela legislação.</p>
<p>7.3. <strong>Segurança das informações.</strong> A CONTRATADA adotará medidas técnicas e administrativas razoáveis
    e compatíveis com a natureza dos dados e os riscos envolvidos para protegê-los contra acessos não autorizados e
    situações acidentais ou ilícitas de destruição, perda, alteração, comunicação ou tratamento inadequado.</p>
<p>7.4. <strong>Confidencialidade.</strong> A CONTRATADA compromete-se a manter a confidencialidade dos dados pessoais
    tratados em razão deste contrato, restringindo o acesso às pessoas autorizadas que necessitem dessas informações
    para a execução dos serviços e que estejam sujeitas a deveres de confidencialidade.</p>
<p>7.5. <strong>Serviços de terceiros.</strong> Para a execução dos serviços, a CONTRATADA poderá utilizar fornecedores
    de infraestrutura tecnológica, hospedagem, armazenamento e outros serviços necessários ao funcionamento da
    plataforma, inclusive provedores de nuvem, adotando medidas razoáveis para que tais fornecedores estejam sujeitos a
    obrigações de proteção e segurança compatíveis com os serviços prestados.</p>
<p>7.6. <strong>Incidentes de segurança.</strong> A CONTRATADA comunicará ao CONTRATANTE, em até 24 (vinte e quatro)
    horas após tomar conhecimento, qualquer incidente de segurança envolvendo dados pessoais tratados em nome do
    CONTRATANTE que possa acarretar risco ou dano relevante aos titulares, fornecendo as informações disponíveis para
    auxiliar na avaliação e adoção das providências necessárias. A comunicação inicial poderá ser complementada à
    medida que novas informações forem apuradas.</p>
<p>7.7. <strong>Responsabilidades do CONTRATANTE.</strong> Compete ao CONTRATANTE, na qualidade de controlador,
    definir as finalidades e bases legais para o tratamento dos dados pessoais inseridos na plataforma, fornecer as
    informações de transparência necessárias aos titulares e atender às solicitações e obrigações legais que lhe sejam
    aplicáveis.</p>
<p>7.8. <strong>Direitos dos titulares.</strong> A CONTRATADA prestará assistência razoável ao CONTRATANTE, dentro de
    suas possibilidades técnicas e contratuais, para atendimento de solicitações dos titulares relacionadas às operações
    de tratamento realizadas por meio da plataforma.</p>
<p>7.9. <strong>Exportação e exclusão dos dados.</strong> Após o encerramento do contrato, o CONTRATANTE poderá
    solicitar a exportação dos dados pessoais e demais informações de sua titularidade armazenadas na plataforma no
    prazo de até 30 (trinta) dias. Encerrado esse prazo, os dados poderão ser excluídos dos ambientes operacionais da
    CONTRATADA, ressalvadas as informações cuja conservação seja exigida por obrigação legal ou regulatória. As cópias
    de segurança serão eliminadas conforme os respectivos ciclos de retenção, observadas as medidas de segurança
    aplicáveis.</p>
<p>7.10. <strong>Responsabilidades.</strong> Cada parte responderá pelo cumprimento das obrigações legais e
    contratuais relacionadas às operações de tratamento de dados pessoais sob sua responsabilidade, nos termos da
    legislação aplicável.</p>

<h2>Cláusula 8 – Da responsabilidade</h2>
<p>8.1. A responsabilidade da CONTRATADA por danos decorrentes deste contrato será apurada conforme a legislação
    aplicável e as circunstâncias do caso concreto. Na máxima extensão permitida por lei, a CONTRATADA não responderá
    por lucros cessantes ou danos indiretos decorrentes de falhas na internet, nos equipamentos do CONTRATANTE ou em
    serviços de terceiros integrados ao sistema (como WhatsApp, provedores de e-mail e meios de pagamento), desde que
    tais eventos não decorram de ação ou omissão imputável à CONTRATADA. Esta disposição não exclui responsabilidades
    que não possam ser legalmente afastadas.</p>

<h2>Cláusula 9 – Das disposições gerais</h2>
<p>9.1. As comunicações entre as partes serão feitas pelos e-mails indicados neste contrato ou pelos canais oficiais
    da CONTRATADA.</p>
<p>9.2. As partes reconhecem como válida a assinatura deste contrato por meio eletrônico, inclusive por plataforma de
    assinatura eletrônica, observada a legislação aplicável.</p>
<p>9.3. A tolerância de uma parte com o descumprimento de qualquer cláusula não significa renúncia ou alteração
    contratual.</p>
<p>9.4. Fica eleito o foro da comarca de {{ $p['foro'] }}, observadas as regras legais aplicáveis, para resolver as
    questões decorrentes deste contrato.</p>

<div style="page-break-inside: avoid;">
<p style="margin-top: 18px;">E, por estarem de acordo, as partes assinam o presente instrumento.</p>
<p>{{ $p['foro'] }}, {{ $emitido_em->translatedFormat('d \d\e F \d\e Y') }}.</p>

<table class="assinaturas">
    <tr>
        <td><div class="linha"></div><strong>CONTRATADA</strong><br>{{ $p['razao_social'] }}<br>CNPJ {{ $p['cnpj'] }}</td>
        <td><div class="linha"></div><strong>CONTRATANTE</strong><br>{{ $c['razao_social'] }}<br>{{ $c['representante'] }}</td>
    </tr>
</table>
</div>
</body>
</html>
