export interface Servico {
  id: number;
  nome: string;
  descricao: string | null;
  duracao_minutos: number;
  preco: string;
  ativo: boolean;
}

export interface ServicoDoBarbeiro {
  id: number;
  nome: string;
  preco: string | null;
  duracao_minutos: number | null;
  preco_padrao: string;
  duracao_padrao: number;
}

export interface FaixaHorario {
  dia_semana: number;
  inicio: string;
  fim: string;
}

export type TipoVinculo = 'clt' | 'parceiro' | 'autonomo';

export interface Funcionario {
  id: number;
  nome: string;
  telefone: string | null;
  email: string | null;
  especialidades: string | null;
  tipo_vinculo: TipoVinculo | null;
  comissao_percentual: string;
  atende_clientes: boolean;
  ativo: boolean;
  foto_url: string | null;
  servicos: ServicoDoBarbeiro[];
  horarios_trabalho: FaixaHorario[];
}

export interface BloqueioAgenda {
  id: number;
  funcionario_id: number | null;
  funcionario: { id: number; nome: string } | null;
  inicio: string;
  fim: string;
  motivo: string | null;
}

export interface Cliente {
  id: number;
  nome: string;
  telefone: string;
  email: string | null;
  data_nascimento: string | null;
  observacoes: string | null;
  aceita_whatsapp: boolean;
  created_at: string;
}

export interface Pagina<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface EmpresaCompleta {
  id: number;
  nome: string;
  slug: string;
  slogan: string | null;
  descricao: string | null;
  documento: string | null;
  telefone: string | null;
  whatsapp: string | null;
  email: string | null;
  instagram: string | null;
  cep: string | null;
  logradouro: string | null;
  numero: string | null;
  complemento: string | null;
  bairro: string | null;
  cidade: string | null;
  uf: string | null;
  cor_primaria: string;
  fuso_horario: string;
  logo_url: string | null;
  capa_url: string | null;
}

export const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

export const VINCULOS: Record<TipoVinculo, string> = {
  clt: 'CLT',
  parceiro: 'Profissional-parceiro',
  autonomo: 'Autônomo',
};
