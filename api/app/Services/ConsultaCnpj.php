<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Consulta um CNPJ na API pública do OpenCNPJ (dados da Receita Federal, sem chave)
 * e devolve os dados já no formato do cadastro da barbearia.
 * Documentação: https://opencnpj.org/#api — GET https://api.opencnpj.org/{cnpj}
 */
class ConsultaCnpj
{
    public const URL = 'https://api.opencnpj.org/';

    /** Só os 14 dígitos: "12.345.678/0001-95" → "12345678000195". */
    public static function normalizar(?string $cnpj): string
    {
        return preg_replace('/\D/', '', (string) $cnpj);
    }

    /** Confere os dois dígitos verificadores. */
    public static function valido(string $cnpj): bool
    {
        if (! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $tamanho) {
            $soma = 0;
            $peso = $tamanho - 7;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $cnpj[$i] * $peso--;
                if ($peso < 2) {
                    $peso = 9;
                }
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $cnpj[$tamanho] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed> campos do cadastro (nome, razao_social, endereço, situação...)
     *                              e "dono_nome" sugerido a partir do primeiro sócio
     *
     * @throws ValidationException CNPJ inválido ou não encontrado
     * @throws RuntimeException API fora do ar
     */
    public function consultar(string $cnpj): array
    {
        $cnpj = self::normalizar($cnpj);
        if (! self::valido($cnpj)) {
            throw ValidationException::withMessages(['documento' => 'CNPJ inválido. Confira os 14 números.']);
        }

        try {
            $resposta = Http::acceptJson()->timeout(10)->retry(2, 300, throw: false)->get(self::URL.$cnpj);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Não foi possível consultar o CNPJ agora. Tente de novo em instantes.', 0, $e);
        }

        if ($resposta->status() === 404) {
            throw ValidationException::withMessages(['documento' => 'CNPJ não encontrado na Receita Federal.']);
        }
        if (! $resposta->successful() || ! is_array($resposta->json())) {
            throw new RuntimeException('Não foi possível consultar o CNPJ agora. Tente de novo em instantes.');
        }

        return $this->mapear($cnpj, $resposta->json());
    }

    private function mapear(string $cnpj, array $d): array
    {
        $telefone = collect($d['telefones'] ?? [])->first(fn ($t) => empty($t['is_fax']));
        $cnae = collect($d['cnaes'] ?? [])->firstWhere('is_principal', true);
        $socio = collect($d['QSA'] ?? [])->first();
        $razao = trim((string) ($d['razao_social'] ?? ''));
        $fantasia = trim((string) ($d['nome_fantasia'] ?? ''));
        $logradouro = trim(($d['tipo_logradouro'] ?? '').' '.($d['logradouro'] ?? ''));

        return [
            'documento' => $cnpj,
            'nome' => $this->titulo($fantasia !== '' ? $fantasia : $razao),
            'razao_social' => $this->limite($razao, 160),
            'situacao_cadastral' => $d['situacao_cadastral'] ?? null,
            'data_abertura' => ($d['data_inicio_atividade'] ?? null) ?: null,
            'natureza_juridica' => $this->limite($d['natureza_juridica'] ?? null, 120),
            'cnae_principal' => $cnae ? $this->limite("{$cnae['codigo']} - {$cnae['descricao']}", 200) : ($d['cnae_principal'] ?? null),
            'porte' => $this->limite($d['porte_empresa'] ?? null, 40),
            'optante_simples' => isset($d['opcao_simples']) ? $d['opcao_simples'] === 'S' : null,
            'optante_mei' => isset($d['opcao_mei']) ? $d['opcao_mei'] === 'S' : null,
            'email' => isset($d['email']) && filter_var($d['email'], FILTER_VALIDATE_EMAIL) ? Str::lower($d['email']) : null,
            'telefone' => $telefone ? preg_replace('/\D/', '', $telefone['ddd'].$telefone['numero']) : null,
            'cep' => $d['cep'] ?? null,
            'logradouro' => $this->limite($this->titulo($logradouro), 255),
            'numero' => $this->limite($d['numero'] ?? null, 20),
            'complemento' => $this->limite($this->titulo($d['complemento'] ?? ''), 80),
            'bairro' => $this->limite($this->titulo($d['bairro'] ?? ''), 80),
            'cidade' => $this->limite($this->titulo($d['municipio'] ?? ''), 80),
            'uf' => $d['uf'] ?? null,
            'dono_nome' => $socio ? $this->titulo($socio['nome_socio'] ?? '') : null,
        ];
    }

    /** A Receita grava tudo em maiúsculas: "RUA DAS FLORES" → "Rua Das Flores". */
    private function titulo(?string $texto): ?string
    {
        $texto = trim(preg_replace('/\s+/', ' ', (string) $texto));

        return $texto === '' ? null : mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE);
    }

    private function limite(?string $texto, int $tamanho): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, $tamanho);
    }
}
