@php
    $r = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $c = $contratante;
    $p = $contratada;
    $extenso = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze'];
    $fid = $fidelidade <= 12 ? sprintf('%02d (%s)', $fidelidade, $extenso[$fidelidade]) : (string) $fidelidade;
    $mesesTxt = $fidelidade === 1 ? 'mês' : 'meses';
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
    table.multa { border-collapse: collapse; margin: 6px 0 8px; }
    table.multa th, table.multa td { border: 1px solid #ccc; padding: 4px 12px; text-align: left; }
    table.multa th { background: #f2f2f2; }
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
<p>1.1. O objeto deste contrato é a cessão, pela CONTRATADA ao CONTRATANTE, do direito de uso, não exclusivo e
    intransferível, do sistema de gestão para barbearias disponibilizado pela internet no modelo SaaS (software como
    serviço), incluindo agenda, agendamento online pela página da barbearia, cadastro de clientes, serviços e equipe,
    comandas, caixa, comissões, relatórios e os demais recursos disponíveis no plano contratado, além da hospedagem,
    manutenção, atualizações e suporte técnico.</p>
<p>1.2. Este contrato não transfere ao CONTRATANTE a propriedade do sistema, de seu código-fonte ou de qualquer
    direito de propriedade intelectual, que permanecem da CONTRATADA.</p>

<h2>Cláusula 2 – Do início e do acesso</h2>
<p>2.1. O serviço tem início em <strong>{{ $inicio->format('d/m/Y') }}</strong>, data em que a CONTRATADA disponibiliza o
    acesso à plataforma. O acesso do responsável é feito com o e-mail {{ $c['representante_email'] }}, e o CONTRATANTE
    pode cadastrar outros usuários (recepção e barbeiros), respondendo pelo uso que fizerem do sistema.</p>
<p>2.2. O CONTRATANTE é responsável pela guarda das senhas de acesso e por avisar a CONTRATADA, de imediato, sobre
    qualquer uso não autorizado.</p>

<h2>Cláusula 3 – Do preço e da forma de pagamento</h2>
<p>3.1. Pelo serviço, o CONTRATANTE pagará <strong>{{ $r($valor) }}</strong> pelo plano <strong>{{ $plano }}</strong>{{ $meses && $meses > 1 ? ', equivalente a '.$r($mensalidade).' por mês' : '' }},
    por <strong>{{ $forma_pagamento }}</strong>, de forma antecipada, no início de cada período.</p>
<p>3.2. Nas renovações, o valor é cobrado na data de vencimento do período em curso, pela mesma forma de pagamento,
    salvo acordo diferente entre as partes.</p>
<p>3.3. O atraso no pagamento sujeita o CONTRATANTE a multa de 2% (dois por cento) e juros de 1% (um por cento) ao mês
    sobre o valor devido. Passados {{ $carencia_dias }}{{ $carencia_dias <= 12 ? ' ('.$extenso[$carencia_dias].')' : '' }} dias do vencimento sem
    pagamento, o acesso ao sistema poderá ser suspenso até a regularização, sem que isso interrompa a vigência ou
    o período mínimo deste contrato.</p>
<p>3.4. Os valores poderão ser reajustados a cada 12 (doze) meses pela variação do IPCA/IBGE ou, na sua falta, de
    outro índice oficial que o substitua, com aviso prévio de 30 (trinta) dias.</p>

<h2>Cláusula 4 – Da vigência, renovação e rescisão</h2>
<p>4.1. <strong>Vigência:</strong> o presente contrato terá vigência inicial de {{ $fid }} {{ $mesesTxt }}, contados a
    partir da disponibilização do acesso à plataforma SaaS.</p>
<p>4.2. <strong>Renovação:</strong> após o término do período inicial, o contrato será renovado automaticamente por
    prazo indeterminado, com cobrança recorrente, mantendo-se as condições comerciais contratadas.</p>
<p>4.3. <strong>Cancelamento:</strong> após o cumprimento do período inicial de {{ $fid }} {{ $mesesTxt }}, o CONTRATANTE
    poderá solicitar o cancelamento a qualquer momento, sem incidência de multa rescisória, mediante comunicação pelos
    canais oficiais da CONTRATADA, observadas as condições de encerramento do ciclo de cobrança vigente. O período já
    pago não é devolvido e o acesso permanece até o seu fim.</p>
<p>4.4. <strong>Rescisão antecipada:</strong> caso o CONTRATANTE solicite o encerramento imotivado do contrato antes
    do término do período inicial de fidelidade, poderá ser aplicada multa compensatória correspondente a
    {{ rtrim(rtrim(number_format($percentual_multa, 2, ',', ''), '0'), ',') }}% das mensalidades vincendas até o
    encerramento do período mínimo contratado, conforme a tabela abaixo, calculada sobre a mensalidade de
    {{ $r($mensalidade) }}:</p>
<table class="multa">
    <tr><th>Momento do cancelamento</th><th>Multa</th></tr>
    @foreach ($multas as $m)
        <tr><td>{{ $m['momento'] }}</td><td>{{ $r($m['valor']) }}</td></tr>
    @endforeach
</table>
<p>4.5. <strong>Rescisão por descumprimento:</strong> não será aplicada multa rescisória quando o encerramento
    decorrer de descumprimento contratual não sanado pela parte responsável, após notificação e concessão de prazo
    razoável para regularização, sem prejuízo dos valores devidos pelos serviços efetivamente prestados.</p>
<p>4.6. A CONTRATADA poderá rescindir este contrato, com aviso de 30 (trinta) dias, ou de imediato em caso de uso
    ilícito do sistema, fraude ou inadimplência superior a 30 (trinta) dias.</p>

<h2>Cláusula 5 – Das obrigações da CONTRATADA</h2>
<p>5.1. Manter o sistema disponível pela internet, empregando os melhores esforços para que funcione sem interrupções,
    ressalvadas as paradas para manutenção, as falhas de provedores de internet e de hospedagem e os casos fortuitos ou
    de força maior.</p>
<p>5.2. Fazer cópias de segurança periódicas dos dados, aplicar as correções e atualizações do sistema e prestar
    suporte técnico pelos canais oficiais, em dias úteis e horário comercial.</p>
<p>5.3. Adotar medidas técnicas e administrativas de segurança para proteger os dados armazenados.</p>

<h2>Cláusula 6 – Das obrigações do CONTRATANTE</h2>
<p>6.1. Pagar os valores nas datas combinadas; informar dados verdadeiros e mantê-los atualizados; usar o sistema de
    forma lícita e de acordo com os Termos de uso da plataforma.</p>
<p>6.2. Responder pelas informações que cadastra e pelo relacionamento com os seus clientes, inclusive pela obtenção
    dos consentimentos necessários para o envio de mensagens (WhatsApp, e-mail) pelo sistema.</p>

<h2>Cláusula 7 – Da proteção de dados pessoais (LGPD)</h2>
<p>7.1. Em relação aos dados dos clientes da barbearia, o CONTRATANTE é o controlador e a CONTRATADA é a operadora,
    tratando esses dados apenas para prestar o serviço, conforme a Lei nº 13.709/2018 (LGPD), a Política de
    privacidade e os Termos de uso da plataforma, que fazem parte deste contrato.</p>
<p>7.2. A CONTRATADA comunicará ao CONTRATANTE, em prazo razoável, qualquer incidente de segurança que possa
    afetar esses dados.</p>
<p>7.3. <strong>Dados do CONTRATANTE:</strong> após o encerramento do contrato, o CONTRATANTE poderá solicitar, em até
    30 (trinta) dias, a exportação de seus dados, observadas as condições de segurança, privacidade e proteção de dados
    previstas neste instrumento e na legislação aplicável. Depois desse prazo, os dados poderão ser excluídos,
    ressalvados os que a lei obrigue a guardar.</p>

<h2>Cláusula 8 – Da responsabilidade</h2>
<p>8.1. A responsabilidade da CONTRATADA por danos decorrentes deste contrato fica limitada ao valor pago pelo
    CONTRATANTE nos 12 (doze) meses anteriores ao fato, não respondendo por lucros cessantes, danos indiretos, falhas
    na internet ou nos equipamentos do CONTRATANTE, nem por serviços de terceiros integrados ao sistema (como WhatsApp,
    provedores de e-mail e meios de pagamento).</p>

<h2>Cláusula 9 – Das disposições gerais</h2>
<p>9.1. As comunicações entre as partes serão feitas pelos e-mails indicados neste contrato ou pelos canais
    oficiais da CONTRATADA.</p>
<p>9.2. As partes reconhecem como válida a assinatura deste contrato por meio eletrônico, inclusive por plataforma de
    assinatura digital, nos termos da Medida Provisória nº 2.200-2/2001 e da Lei nº 14.063/2020.</p>
<p>9.3. A tolerância de uma parte com o descumprimento de qualquer cláusula não significa renúncia ou alteração
    do contrato.</p>
<p>9.4. Fica eleito o foro da comarca de {{ $p['foro'] }} para resolver as questões deste contrato, com renúncia a
    qualquer outro, por mais privilegiado que seja.</p>

<p style="margin-top: 18px;">E, por estarem de acordo, as partes assinam o presente contrato.</p>
<p>{{ $p['foro'] }}, {{ $emitido_em->translatedFormat('d \d\e F \d\e Y') }}.</p>

<table class="assinaturas">
    <tr>
        <td><div class="linha"></div><strong>CONTRATADA</strong><br>{{ $p['razao_social'] }}<br>CNPJ {{ $p['cnpj'] }}</td>
        <td><div class="linha"></div><strong>CONTRATANTE</strong><br>{{ $c['razao_social'] }}<br>{{ $c['representante'] }}</td>
    </tr>
</table>
</body>
</html>
