<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaginaPublicaTest extends TestCase
{
    use RefreshDatabase;

    /** 2030-01-15 é uma terça-feira (dia_semana = 2). */
    private const DIA = '2030-01-15';

    private Empresa $empresa;

    private Funcionario $carlos;

    private Funcionario $rafael;

    private Servico $corte;

    private Servico $barba;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-14 12:00', 'America/Sao_Paulo'));

        $this->empresa = Empresa::factory()->create([
            'nome' => 'Barbearia do Zé',
            'slug' => 'barbearia-do-ze',
            'slogan' => 'Tradição e estilo',
            'whatsapp' => '11999990000',
            'logradouro' => 'Rua das Flores',
            'numero' => '10',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
        ]);

        $this->naEmpresa($this->empresa, function () {
            $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $this->barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 35]);
            Servico::create(['nome' => 'Luzes', 'duracao_minutos' => 90, 'preco' => 120, 'ativo' => false]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos', 'especialidades' => 'Degradê']);
            $this->rafael = Funcionario::create(['nome' => 'Rafael']);
            Funcionario::create(['nome' => 'Inativo', 'ativo' => false]);
            $this->carlos->servicos()->attach([$this->corte->id => ['preco' => 50], $this->barba->id => []]);
            $this->rafael->servicos()->attach([$this->corte->id => []]);
            foreach ([$this->carlos, $this->rafael] as $f) {
                $f->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendar(array $dados = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/publico/barbearia-do-ze/agendamentos', $dados + [
            'nome' => 'João',
            'telefone' => '(11) 98888-7777',
            'servico_ids' => [$this->corte->id],
            'funcionario_id' => $this->carlos->id,
            'data' => self::DIA,
            'hora' => '09:00',
        ]);
    }

    public function test_pagina_mostra_dados_servicos_e_equipe(): void
    {
        $this->get('/barbearia-do-ze')
            ->assertOk()
            ->assertSee('Barbearia do Zé')
            ->assertSee('Tradição e estilo')
            ->assertSee('<meta property="og:title" content="Barbearia do Zé">', false)
            ->assertSee('Corte')
            ->assertSee('R$ 45,00')
            ->assertSee('Degradê')
            ->assertSee('Terça')
            ->assertSee('09h00 às 12h00')
            ->assertSee('https://wa.me/5511999990000')
            ->assertSee('Rua das Flores, 10')
            ->assertDontSee('Luzes')
            ->assertDontSee('Inativo');
    }

    public function test_pagina_inexistente_ou_de_barbearia_inativa_da_404(): void
    {
        Empresa::factory()->inativa()->create(['slug' => 'fechada']);

        $this->get('/nao-existe')->assertNotFound();
        $this->get('/fechada')->assertNotFound();
        $this->getJson('/api/publico/fechada/horarios?data='.self::DIA.'&servico_ids[]=1')->assertNotFound();
    }

    public function test_horarios_por_barbeiro_que_faz_os_servicos(): void
    {
        $resp = $this->getJson('/api/publico/barbearia-do-ze/horarios?'.http_build_query([
            'data' => self::DIA,
            'servico_ids' => [$this->corte->id, $this->barba->id],
        ]))->assertOk();

        $resp->assertJsonCount(1)
            ->assertJsonPath('0.nome', 'Carlos')
            ->assertJsonPath('0.valor_total', '85.00')
            ->assertJsonPath('0.duracao_minutos', 60);
        $this->assertSame('09:00', $resp->json('0.horarios.0'));
        $this->assertSame('11:00', last($resp->json('0.horarios')));
    }

    public function test_horarios_fora_da_janela_de_agendamento_vem_vazios(): void
    {
        $this->getJson('/api/publico/barbearia-do-ze/horarios?data=2030-01-10&servico_ids[]='.$this->corte->id)
            ->assertOk()->assertExactJson([]);
        $this->getJson('/api/publico/barbearia-do-ze/horarios?data=2030-06-10&servico_ids[]='.$this->corte->id)
            ->assertOk()->assertExactJson([]);
    }

    public function test_cliente_agenda_e_e_cadastrado_com_consentimento(): void
    {
        $this->agendar(['aceita_whatsapp' => true, 'observacoes' => 'Máquina 2 dos lados'])
            ->assertCreated()
            ->assertJsonPath('barbeiro', 'Carlos')
            ->assertJsonPath('hora', '09:00')
            ->assertJsonPath('valor_total', '50.00');

        $this->naEmpresa($this->empresa, function () {
            $cliente = Cliente::where('telefone', '11988887777')->firstOrFail();
            $this->assertSame('João', $cliente->nome);
            $this->assertTrue($cliente->consentimentoWhatsapp->aceito);
            $this->assertSame('link_publico', $cliente->consentimentoWhatsapp->origem);

            $a = Agendamento::firstOrFail();
            $this->assertSame('agendado', $a->status);
            $this->assertSame('link_publico', $a->origem);
            $this->assertSame($this->empresa->id, $a->empresa_id);
            $this->assertSame('2030-01-15 12:00:00', $a->inicio->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('Máquina 2 dos lados', $a->observacoes);
        });
    }

    public function test_cliente_existente_e_reaproveitado_pelo_whatsapp(): void
    {
        $this->naEmpresa($this->empresa, fn () => Cliente::create(['nome' => 'João Silva', 'telefone' => '11988887777']));

        $this->agendar(['nome' => 'Outro nome'])->assertCreated();

        $this->naEmpresa($this->empresa, function () {
            $this->assertSame(1, Cliente::count());
            $this->assertSame('João Silva', Cliente::first()->nome);
        });
    }

    public function test_sem_preferencia_fica_com_o_barbeiro_livre(): void
    {
        $this->agendar(['funcionario_id' => null])->assertCreated()->assertJsonPath('barbeiro', 'Carlos');
        $this->agendar(['funcionario_id' => null, 'telefone' => '11977776666'])->assertCreated()->assertJsonPath('barbeiro', 'Rafael');
        $this->agendar(['funcionario_id' => null, 'telefone' => '11966665555'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hora');
    }

    public function test_nao_agenda_horario_ocupado_fora_do_expediente_ou_passado(): void
    {
        $this->agendar()->assertCreated();
        $this->agendar(['telefone' => '11977776666'])->assertUnprocessable()->assertJsonValidationErrors('hora');
        $this->agendar(['hora' => '13:00', 'telefone' => '11955554444'])->assertUnprocessable()->assertJsonValidationErrors('hora');
        $this->agendar(['data' => '2030-01-13'])->assertUnprocessable()->assertJsonValidationErrors('data');
    }

    public function test_valida_servicos_barbeiro_e_telefone(): void
    {
        $this->agendar(['servico_ids' => [$this->barba->id], 'funcionario_id' => $this->rafael->id])
            ->assertUnprocessable()->assertJsonValidationErrors('servico_ids');
        $this->agendar(['telefone' => '1234'])->assertUnprocessable()->assertJsonValidationErrors('telefone');

        // Serviço de outra barbearia não é aceito.
        $outra = Empresa::factory()->create();
        $alheio = $this->naEmpresa($outra, fn () => Servico::create(['nome' => 'X', 'duracao_minutos' => 30, 'preco' => 10]));
        $this->agendar(['servico_ids' => [$alheio->id]])->assertUnprocessable()->assertJsonValidationErrors('servico_ids.0');
    }

    public function test_cliente_marca_no_maximo_um_horario_por_semana(): void
    {
        $this->agendar()->assertCreated(); // terça 15/01

        // Mesma semana (segunda 14/01 a domingo 20/01): bloqueado.
        $this->agendar(['data' => '2030-01-17', 'hora' => '10:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data')
            ->assertJsonPath('errors.data.0', 'Você já tem um horário nesta semana (terça, 15/01 às 09:00). Pela página dá para marcar 1 horário por semana; para outro, fale com a barbearia.');

        // Semana seguinte: liberado. Outro WhatsApp na mesma semana também.
        $this->horarioNaQuinta('2030-01-24');
        $this->agendar(['data' => '2030-01-24', 'hora' => '09:00'])->assertCreated();
        $this->agendar(['telefone' => '11977776666', 'hora' => '10:00'])->assertCreated();
    }

    public function test_horario_cancelado_libera_a_semana(): void
    {
        $this->agendar()->assertCreated();
        $this->naEmpresa($this->empresa, fn () => Agendamento::first()->update(['status' => 'cancelado']));

        $this->agendar(['hora' => '10:00'])->assertCreated();
    }

    public function test_cliente_marca_no_maximo_quatro_horarios_no_mes(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-03-31 12:00', 'America/Sao_Paulo'));
        // Abril/2030: terças 2, 9, 16, 23 e 30 — cinco semanas.
        foreach (['2030-04-02', '2030-04-09', '2030-04-16', '2030-04-23'] as $data) {
            $this->agendar(['data' => $data])->assertCreated();
        }

        $this->agendar(['data' => '2030-04-30'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.data.0', 'Você já tem 4 horários em abril. Pela página dá para marcar até 4 por mês; para outro, fale com a barbearia.');

        $this->agendar(['data' => '2030-05-07'])->assertCreated();
    }

    private function horarioNaQuinta(string $data): void
    {
        $this->naEmpresa($this->empresa, function () {
            $this->carlos->horariosTrabalho()->create(['dia_semana' => 4, 'inicio' => '09:00', 'fim' => '12:00']);
        });
    }

    public function test_endereco_reservado_nao_pode_ser_usado(): void
    {
        $usuario = $this->logarComo('dono', $this->empresa);

        $this->putJson('/api/empresa', ['slug' => 'api'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
        $this->assertSame('barbearia-do-ze', $usuario->empresa->fresh()->slug);
        $this->assertSame('barbearia-admin', Empresa::gerarSlug('Admin'));
    }
}
