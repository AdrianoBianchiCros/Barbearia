import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminService, BarbeariaAdmin, SITUACOES_ACESSO } from '../../core/admin.service';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';

/** Lista das barbearias clientes com a situação do prazo de acesso. */
@Component({
  selector: 'app-admin-barbearias',
  imports: [FormsModule, RouterLink, DatePipe, CurrencyPipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Barbearias</h1>
        <p class="texto-suave">Clientes do sistema e o prazo de acesso de cada uma.</p>
      </div>
      <a class="botao botao-auto" routerLink="/admin/barbearias/nova">+ Nova barbearia</a>
    </div>

    <div class="indicadores">
      <div class="indicador"><span>Barbearias</span><strong>{{ lista().length }}</strong></div>
      <div class="indicador"><span>Em dia</span><strong>{{ contar('em_dia', 'sem_prazo') }}</strong></div>
      <div class="indicador"><span>Vencendo ou em carência</span><strong>{{ contar('vencendo', 'carencia') }}</strong></div>
      <div class="indicador" [class.alerta-indicador]="contar('vencida', 'suspensa') > 0">
        <span>Bloqueadas ou suspensas</span><strong>{{ contar('vencida', 'suspensa') }}</strong>
      </div>
    </div>

    <input class="campo-busca" type="search" placeholder="Buscar por barbearia, dono ou e-mail" [ngModel]="termo()" (ngModelChange)="termo.set($event)" aria-label="Buscar barbearia" />

    @if (carregando()) {
      <div class="vazio">Carregando…</div>
    } @else if (filtradas().length === 0) {
      <div class="vazio">{{ lista().length ? 'Nenhuma barbearia encontrada.' : 'Nenhuma barbearia cadastrada ainda.' }}</div>
    } @else {
      <div class="tabela-container">
        <table class="tabela">
          <thead><tr><th>Barbearia</th><th>Dono</th><th>Situação</th><th>Vence em</th><th>Último plano</th><th></th></tr></thead>
          <tbody>
            @for (b of filtradas(); track b.id) {
              <tr>
                <td><strong>{{ b.nome }}</strong><br /><small class="texto-suave">/{{ b.slug }}</small></td>
                <td>{{ b.dono?.nome ?? '—' }}<br /><small class="texto-suave">{{ b.dono?.email }}</small></td>
                <td><span class="etiqueta" [class]="situacoes[b.acesso.situacao].classe">{{ situacoes[b.acesso.situacao].rotulo }}</span></td>
                <td>
                  @if (b.acesso.vence_em) {
                    {{ b.acesso.vence_em + 'T12:00:00' | date: 'dd/MM/yyyy' }}
                    <br /><small class="texto-suave">{{ textoDias(b) }}</small>
                  } @else {
                    —
                  }
                </td>
                <td>
                  @if (b.ultima_assinatura; as u) {
                    {{ rotuloPeriodo(u.periodo) }} · {{ +u.valor | currency: 'BRL' }}
                  } @else {
                    —
                  }
                </td>
                <td class="acoes"><a class="botao-link" [routerLink]="['/admin/barbearias', b.id]">Abrir</a></td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class AdminBarbearias implements OnInit {
  private readonly api = inject(AdminService);
  private readonly aviso = inject(AvisoService);
  protected readonly situacoes = SITUACOES_ACESSO;

  protected readonly lista = signal<BarbeariaAdmin[]>([]);
  protected readonly carregando = signal(true);
  protected readonly termo = signal('');
  private readonly periodos = signal<Record<string, string>>({});

  protected readonly filtradas = computed(() => {
    const termo = this.termo().trim().toLowerCase();
    if (!termo) return this.lista();
    return this.lista().filter((b) =>
      [b.nome, b.slug, b.dono?.nome, b.dono?.email].some((t) => t?.toLowerCase().includes(termo)),
    );
  });

  ngOnInit(): void {
    this.api.opcoes().subscribe((o) => this.periodos.set(Object.fromEntries(o.periodos.map((p) => [p.valor, p.rotulo]))));
    this.api.barbearias().subscribe({
      next: (l) => {
        this.lista.set(l);
        this.carregando.set(false);
      },
      error: (e) => {
        this.carregando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  contar(...situacoes: string[]): number {
    return this.lista().filter((b) => situacoes.includes(b.acesso.situacao)).length;
  }

  rotuloPeriodo(periodo: string): string {
    return this.periodos()[periodo] ?? periodo;
  }

  textoDias(b: BarbeariaAdmin): string {
    const dias = b.acesso.dias_restantes ?? 0;
    if (dias > 1) return `faltam ${dias} dias`;
    if (dias === 1) return 'vence amanhã';
    if (dias === 0) return 'vence hoje';
    return `venceu há ${-dias} dia(s)`;
  }
}
