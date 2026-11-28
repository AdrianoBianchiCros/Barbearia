@php
    $r = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $c = $contratante;
    $p = $contratada;
    $extenso = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Termo de parceria de divulgação — {{ $c['nome'] }}</title>
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
<div class="rodape">{{ $p['nome'] }} · CNPJ {{ $p['cnpj'] }} · Termo de parceria de divulgação e licença gratuita de uso de software — {{ $c['nome'] }}</div>

<h1>Termo de parceria de divulgação e licença gratuita de uso de software (SaaS)</h1>
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

<p>As partes acima identificadas celebram o presente termo de parceria, pelo qual a CONTRATADA concede ao CONTRATANTE
    o uso gratuito da plataforma em troca da divulgação descrita na Cláusula 3, e que se rege pelas cláusulas a seguir.</p>

<h2>Cláusula 1 – Do objeto</h2>
<p>1.1. O objeto deste contrato é a concessão, pela CONTRATADA ao CONTRATANTE, do direito de uso, não exclusivo e
    intransferível, do sistema de gestão para barbearias disponibilizado pela internet no modelo SaaS (software como
    serviço), incluindo agenda, agendamento online pela página da barbearia, cadastro de clientes, serviços e equipe,
    comandas, caixa, comissões, relatórios e os demais recursos disponíveis na plataforma, além da hospedagem,
    manutenção, atualizações e suporte técnico.</p>
<p>1.2. Este termo não transfere ao CONTRATANTE a propriedade do sistema, de seu código-fonte ou de qualquer
    direito de propriedade intelectual, que permanecem da CONTRATADA.</p>

<h2>Cláusula 2 – Do início e do acesso</h2>
<p>2.1. O serviço tem início em <strong>{{ $inicio->format('d/m/Y') }}</strong>, data prevista para disponibilização do
    acesso à plataforma. O acesso do responsável é feito com o e-mail {{ $c['representante_email'] }}, e o CONTRATANTE
    poderá cadastrar outros usuários (recepção e barbeiros), respondendo pelo uso que fizerem do sistema.</p>
<p>2.2. O CONTRATANTE é responsável pela guarda das senhas de acesso e por comunicar imediatamente à CONTRATADA
    qualquer uso não autorizado.</p>
<h2>Cláusula 3 – Da gratuidade e da contrapartida de divulgação</h2>
<p>3.1. A licença de uso é concedida <strong>gratuitamente</strong>: enquanto esta parceria estiver vigente, não há
    mensalidade, taxa de instalação ou qualquer outra cobrança pelo uso da plataforma.</p>
<p>3.2. Como contrapartida, o CONTRATANTE se compromete a <strong>divulgar a plataforma da CONTRATADA</strong> em seus
    canais (redes sociais, página de agendamento, atendimento aos seus clientes e outros meios combinados entre as
    partes), mencionando-a como a ferramenta que utiliza, de forma verdadeira e de acordo com a sua experiência real de
    uso, e mantendo na sua página de agendamento a menção "Agendamento online por {{ $p['nome'] }}".</p>
<p>3.3. Nas publicações feitas em razão desta parceria, o CONTRATANTE identificará, quando cabível, que se trata de
    parceria com a CONTRATADA, observadas as normas de publicidade aplicáveis.</p>
<p>3.4. O CONTRATANTE autoriza a CONTRATADA a citar o seu nome comercial e o seu logotipo como cliente da plataforma em
    materiais de divulgação, podendo revogar essa autorização a qualquer momento, por comunicação escrita.</p>
<p>3.5. A parceria não gera remuneração, comissão, exclusividade ou vínculo empregatício, societário ou de
    representação entre as partes.</p>

<h2>Cláusula 4 – Da vigência e do encerramento</h2>
<p>4.1. Esta parceria vigora por <strong>prazo indeterminado</strong>, a partir da disponibilização do acesso à
    plataforma, sem período mínimo de permanência.</p>
<p>4.2. Qualquer das partes poderá encerrar a parceria a qualquer momento, sem multa, mediante aviso prévio de 15
    (quinze) dias pelos canais indicados neste termo. A CONTRATADA poderá, nesse caso, oferecer ao CONTRATANTE a
    continuidade do uso por meio de um plano pago, que dependerá de contratação própria; sem ela, o acesso será
    encerrado ao fim do aviso prévio, assegurada a exportação dos dados prevista na Cláusula 7.</p>
<p>4.3. A CONTRATADA poderá encerrar a parceria imediatamente em caso de uso ilícito da plataforma, fraude,
    comprometimento da segurança do sistema ou divulgação enganosa ou ofensiva à sua imagem.</p>

<h2>Cláusula 5 – Das obrigações da CONTRATADA</h2>
<p>5.1. Manter o sistema disponível pela internet, empregando os melhores esforços para que funcione sem interrupções,
    ressalvadas as paradas para manutenção, as falhas de provedores de internet e de hospedagem e os casos fortuitos ou
    de força maior.</p>
<p>5.2. Fazer cópias de segurança periódicas dos dados, aplicar as correções e atualizações do sistema e prestar
    suporte técnico pelos canais oficiais, em dias úteis e horário comercial.</p>
<p>5.3. Adotar medidas técnicas e administrativas de segurança para proteger os dados armazenados.</p>

<h2>Cláusula 6 – Das obrigações do CONTRATANTE</h2>
<p>6.1. Cumprir a contrapartida de divulgação da Cláusula 3; informar dados verdadeiros e mantê-los atualizados; usar o sistema de
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
<p>7.6. <strong>Incidentes de segurança.</strong> A CONTRATADA comunicará ao CONTRATANTE, em prazo razoável,
    preferencialmente em até 48 (quarenta e oito) horas após tomar conhecimento, qualquer incidente de segurança envolvendo dados pessoais tratados em nome do
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
<p>8.1. A responsabilidade da CONTRATADA por danos decorrentes deste termo será apurada conforme a legislação
    aplicável e as circunstâncias do caso concreto. Na máxima extensão permitida por lei, a CONTRATADA não responderá
    por lucros cessantes ou danos indiretos decorrentes de falhas na internet, nos equipamentos do CONTRATANTE ou em
    serviços de terceiros integrados ao sistema (como WhatsApp, provedores de e-mail e meios de pagamento), desde que
    tais eventos não decorram de ação ou omissão imputável à CONTRATADA. Esta disposição não exclui responsabilidades
    que não possam ser legalmente afastadas.</p>

<h2>Cláusula 9 – Das disposições gerais</h2>
<p>9.1. As comunicações entre as partes serão feitas pelos e-mails indicados neste termo ou pelos canais oficiais
    da CONTRATADA.</p>
<p>9.2. As partes reconhecem como válida a assinatura deste termo por meio eletrônico, inclusive por plataforma de
    assinatura eletrônica, observada a legislação aplicável.</p>
<p>9.3. A tolerância de uma parte com o descumprimento de qualquer cláusula não significa renúncia ou alteração
    contratual.</p>
<p>9.4. Fica eleito o foro da comarca de {{ $p['foro'] }}, observadas as regras legais aplicáveis, para resolver as
    questões decorrentes deste termo.</p>

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
