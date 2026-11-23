<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\User;
use App\Notifications\RedefinirSenhaAdmin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminSenhaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function admin(array $dados = []): Administrador
    {
        return Administrador::create($dados + ['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]);
    }

    public function test_esqueci_a_senha_envia_link_para_a_area_do_admin_e_redefine(): void
    {
        $admin = $this->admin();
        $admin->createToken('admin');

        $this->postJson('/api/admin/auth/esqueci-senha', ['email' => 'admin@exemplo.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($admin, RedefinirSenhaAdmin::class, function (RedefinirSenhaAdmin $n) use ($admin, &$token) {
            $url = $n->toMail($admin)->actionUrl;
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return str_contains($url, '/admin/redefinir-senha?') && $query['email'] === 'admin@exemplo.test';
        });

        $this->postJson('/api/admin/auth/redefinir-senha', [
            'token' => $token,
            'email' => 'admin@exemplo.test',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertOk();

        $this->assertTrue(Hash::check('novaSenha123', $admin->fresh()->password));
        $this->assertSame(0, $admin->tokens()->count());
        $this->postJson('/api/admin/auth/login', ['email' => 'admin@exemplo.test', 'password' => 'novaSenha123'])->assertOk();
    }

    public function test_nao_revela_se_o_email_e_de_um_administrador(): void
    {
        $this->admin(['email' => 'inativo@exemplo.test', 'ativo' => false]);
        // Dono de barbearia com o mesmo e-mail não recebe o link da administração.
        $dono = User::factory()->create(['email' => 'dono@exemplo.test']);

        foreach (['ninguem@exemplo.test', 'inativo@exemplo.test', 'dono@exemplo.test'] as $email) {
            $this->postJson('/api/admin/auth/esqueci-senha', ['email' => $email])->assertOk();
        }

        Notification::assertNothingSent();
        Notification::assertNotSentTo($dono, ResetPassword::class);
    }

    public function test_link_do_painel_da_barbearia_nao_vale_na_area_do_admin(): void
    {
        $this->admin(['email' => 'mesmo@exemplo.test']);
        $dono = User::factory()->create(['email' => 'mesmo@exemplo.test']);
        $tokenDoPainel = app('auth.password.broker')->createToken($dono);

        $this->postJson('/api/admin/auth/redefinir-senha', [
            'token' => $tokenDoPainel,
            'email' => 'mesmo@exemplo.test',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_pede_para_aguardar_entre_pedidos_e_valida_a_senha(): void
    {
        $this->admin();

        $this->postJson('/api/admin/auth/esqueci-senha', ['email' => 'admin@exemplo.test'])->assertOk();
        $this->postJson('/api/admin/auth/esqueci-senha', ['email' => 'admin@exemplo.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/admin/auth/redefinir-senha', [
            'token' => 'qualquer',
            'email' => 'admin@exemplo.test',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
