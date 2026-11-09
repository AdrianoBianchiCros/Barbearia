import { environment } from '../../environments/environment';

/** Endereço público do sistema (onde ficam as páginas das barbearias e os documentos legais). */
export const ENDERECO_SITE = environment.apiUrl.replace(/\/api\/?$/, '');

/** Dados da empresa dona da plataforma, mostrados no rodapé (os mesmos de api/config/plataforma.php). */
export const PLATAFORMA = {
  nome: 'ABianchi Tech',
  cnpj: '54.942.870/0001-09',
  privacidade: `${ENDERECO_SITE}/privacidade`,
  termos: `${ENDERECO_SITE}/termos`,
};
