import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../core/auth.service';
import { podeEditar } from '../../../core/auth.guards';
import { AvisoService } from '../../../core/aviso.service';
import { CadastrosService } from '../../../core/cadastros.service';
import { mensagemDeErro } from '../../../core/erros';
import { Funcionario, VINCULOS } from '../../../core/modelos';
import { TelefonePipe } from '../../../core/telefone.pipe';

@Component({
  selector: 'app-barbeiros',
  imports: [RouterLink, TelefonePipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Barbeiros</h1>
        <p class="texto-suave">Equipe, serviços realizados, expediente e folgas.</p>
      </div>
      @if (editavel) {
        <a class="botao botao-auto" routerLink="/configuracoes/barbeiros/novo">+ Novo barbeiro</a>
      }
    </div>

    @if (carregando()) {
      <p class="texto-suave">Carregando…</p>
    } @else if (equipe().length === 0) {
      <div class="vazio">Nenhum barbeiro cadastrado ainda.</div>
    } @else {
      <div class="cartoes">
        @for (f of equipe(); track f.id) {
          <a class="cartao-pessoa" [routerLink]="['/configuracoes/barbeiros', f.id]" [class.inativo]="!f.ativo">
            @if (f.foto_url) {
              <img class="avatar" [src]="f.foto_url" [alt]="f.nome" />
            } @else {
              <span class="avatar avatar-inicial">{{ f.nome.charAt(0) }}</span>
            }
            <div class="cartao-pessoa-info">
              <strong>{{ f.nome }}</strong>
              <span class="texto-suave pequeno">{{ f.telefone | telefone }}</span>
              <span class="pequeno">
                {{ f.servicos.length }} serviço(s) · {{ diasDeTrabalho(f) }}
              </span>
              <span class="pequeno texto-suave">
                {{ f.tipo_vinculo ? vinculos[f.tipo_vinculo] : 'Vínculo não informado' }} · {{ +f.comissao_percentual }}% comissão
              </span>
            </div>
            @if (!f.ativo) {<span class="etiqueta">Inativo</span>}
          </a>
        }
      </div>
    }
  `,
})
export class Barbeiros implements OnInit {
  private readonly api = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);
  protected readonly editavel = podeEditar(inject(AuthService), 'dono');
  protected readonly vinculos = VINCULOS;

  protected readonly equipe = signal<Funcionario[]>([]);
  protected readonly carregando = signal(true);

  ngOnInit(): void {
    this.api.listarFuncionarios().subscribe({
      next: (f) => {
        this.equipe.set(f);
        this.carregando.set(false);
      },
      error: (e) => {
        this.aviso.erro(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  diasDeTrabalho(f: Funcionario): string {
    const dias = new Set(f.horarios_trabalho.map((h) => h.dia_semana)).size;
    return dias ? `${dias} dia(s) por semana` : 'sem expediente';
  }
}
