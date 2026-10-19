import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  BloqueioAgenda,
  Cliente,
  EmpresaCompleta,
  FaixaHorario,
  FotoGaleria,
  Funcionario,
  Pagina,
  Servico,
} from './modelos';

const API = environment.apiUrl;

/** Chamadas da API para os cadastros da barbearia. */
@Injectable({ providedIn: 'root' })
export class CadastrosService {
  private readonly http = inject(HttpClient);

  // Serviços
  listarServicos(): Observable<Servico[]> {
    return this.http.get<Servico[]>(`${API}/servicos`);
  }
  salvarServico(dados: object, id?: number): Observable<Servico> {
    return id
      ? this.http.put<Servico>(`${API}/servicos/${id}`, dados)
      : this.http.post<Servico>(`${API}/servicos`, dados);
  }
  excluirServico(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/servicos/${id}`);
  }

  // Barbeiros / funcionários
  listarFuncionarios(): Observable<Funcionario[]> {
    return this.http.get<Funcionario[]>(`${API}/funcionarios`);
  }
  buscarFuncionario(id: number): Observable<Funcionario> {
    return this.http.get<Funcionario>(`${API}/funcionarios/${id}`);
  }
  salvarFuncionario(dados: object, id?: number): Observable<Funcionario> {
    return id
      ? this.http.put<Funcionario>(`${API}/funcionarios/${id}`, dados)
      : this.http.post<Funcionario>(`${API}/funcionarios`, dados);
  }
  excluirFuncionario(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/funcionarios/${id}`);
  }
  salvarServicosDoFuncionario(
    id: number,
    servicos: { id: number; preco: number | null; duracao_minutos: number | null }[],
  ): Observable<Funcionario> {
    return this.http.put<Funcionario>(`${API}/funcionarios/${id}/servicos`, { servicos });
  }
  salvarHorarios(id: number, horarios: FaixaHorario[]): Observable<Funcionario> {
    return this.http.put<Funcionario>(`${API}/funcionarios/${id}/horarios`, { horarios });
  }
  enviarFoto(id: number, arquivo: File): Observable<Funcionario> {
    const form = new FormData();
    form.append('foto', arquivo);
    return this.http.post<Funcionario>(`${API}/funcionarios/${id}/foto`, form);
  }

  // Bloqueios (folgas, férias, feriados)
  listarBloqueios(funcionarioId?: number): Observable<BloqueioAgenda[]> {
    let params = new HttpParams();
    if (funcionarioId) params = params.set('funcionario_id', funcionarioId);
    return this.http.get<BloqueioAgenda[]>(`${API}/bloqueios`, { params });
  }
  criarBloqueio(dados: { funcionario_id: number | null; inicio: string; fim: string; motivo: string }): Observable<BloqueioAgenda> {
    return this.http.post<BloqueioAgenda>(`${API}/bloqueios`, dados);
  }
  excluirBloqueio(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/bloqueios/${id}`);
  }

  // Clientes
  listarClientes(busca: string, pagina: number): Observable<Pagina<Cliente>> {
    const params = new HttpParams().set('q', busca).set('page', pagina);
    return this.http.get<Pagina<Cliente>>(`${API}/clientes`, { params });
  }
  salvarCliente(dados: object, id?: number): Observable<Cliente> {
    return id
      ? this.http.put<Cliente>(`${API}/clientes/${id}`, dados)
      : this.http.post<Cliente>(`${API}/clientes`, dados);
  }
  excluirCliente(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/clientes/${id}`);
  }

  // Barbearia
  buscarEmpresa(): Observable<EmpresaCompleta> {
    return this.http.get<EmpresaCompleta>(`${API}/empresa`);
  }
  salvarEmpresa(dados: object): Observable<EmpresaCompleta> {
    return this.http.put<EmpresaCompleta>(`${API}/empresa`, dados);
  }
  enviarImagemEmpresa(tipo: 'logo' | 'capa', arquivo: File): Observable<EmpresaCompleta> {
    const form = new FormData();
    form.append('imagem', arquivo);
    return this.http.post<EmpresaCompleta>(`${API}/empresa/imagens/${tipo}`, form);
  }
  removerImagemEmpresa(tipo: 'logo' | 'capa'): Observable<EmpresaCompleta> {
    return this.http.delete<EmpresaCompleta>(`${API}/empresa/imagens/${tipo}`);
  }

  listarGaleria(): Observable<FotoGaleria[]> {
    return this.http.get<FotoGaleria[]>(`${API}/galeria`);
  }
  enviarFotoGaleria(arquivo: File): Observable<FotoGaleria> {
    const form = new FormData();
    form.append('imagem', arquivo);
    return this.http.post<FotoGaleria>(`${API}/galeria`, form);
  }
  salvarLegendaGaleria(id: number, legenda: string | null): Observable<FotoGaleria> {
    return this.http.put<FotoGaleria>(`${API}/galeria/${id}`, { legenda });
  }
  removerFotoGaleria(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/galeria/${id}`);
  }
}
