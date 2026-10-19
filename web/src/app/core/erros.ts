import { HttpErrorResponse } from '@angular/common/http';

/** Converte o erro da API em uma mensagem legível para o usuário. */
export function mensagemDeErro(erro: unknown): string {
  if (erro instanceof HttpErrorResponse) {
    if (erro.status === 0) return 'Não foi possível conectar ao servidor. Verifique sua internet.';
    const corpo = erro.error as { message?: string; errors?: Record<string, string[]> } | null;
    const primeiroCampo = corpo?.errors ? Object.values(corpo.errors)[0]?.[0] : undefined;
    return primeiroCampo ?? corpo?.message ?? 'Ocorreu um erro inesperado. Tente novamente.';
  }
  return 'Ocorreu um erro inesperado. Tente novamente.';
}

/** Erros de validação por campo, no formato { campo: 'mensagem' }. */
export function errosPorCampo(erro: unknown): Record<string, string> {
  if (erro instanceof HttpErrorResponse && erro.status === 422 && erro.error?.errors) {
    return Object.fromEntries(
      Object.entries(erro.error.errors as Record<string, string[]>).map(([campo, msgs]) => [campo, msgs[0]]),
    );
  }
  return {};
}
