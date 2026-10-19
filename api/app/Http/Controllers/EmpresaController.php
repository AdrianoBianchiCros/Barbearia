<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Dados da barbearia do usuário logado, incluindo o conteúdo da página pública.
 */
class EmpresaController extends Controller
{
    private const IMAGENS = ['logo' => 'logo_path', 'capa' => 'capa_path'];

    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->empresa);
    }

    public function update(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;

        foreach (['telefone', 'whatsapp'] as $campo) {
            if ($request->has($campo)) {
                $request->merge([$campo => Telefone::normalizar($request->input($campo))]);
            }
        }
        if ($request->filled('instagram')) {
            $request->merge(['instagram' => ltrim(trim($request->input('instagram')), '@')]);
        }
        if ($request->filled('uf')) {
            $request->merge(['uf' => strtoupper($request->input('uf'))]);
        }

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'alpha_dash:ascii', 'max:80', Rule::unique('empresas', 'slug')->ignore($empresa->id), Rule::notIn(Empresa::SLUGS_RESERVADOS)],
            'slogan' => ['nullable', 'string', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', Telefone::REGRA],
            'whatsapp' => ['nullable', Telefone::REGRA],
            'email' => ['nullable', 'email', 'max:180'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cor_primaria' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'limite_gaveta' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'fuso_horario' => ['sometimes', 'required', 'timezone:all'],
        ], [
            'telefone.regex' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'whatsapp.regex' => 'Informe o WhatsApp com DDD (10 ou 11 dígitos).',
            'instagram.regex' => 'Informe apenas o usuário do Instagram (ex.: barbeariadoze).',
            'cor_primaria.regex' => 'Escolha uma cor válida.',
            'slug.not_in' => 'Esse endereço é reservado pelo sistema. Escolha outro.',
        ], [
            'slug' => 'endereço da página',
            'cor_primaria' => 'cor principal',
            'descricao' => 'descrição',
            'limite_gaveta' => 'limite de dinheiro na gaveta',
        ]);

        $empresa->update($dados);

        return response()->json($empresa);
    }

    /** Envia o logo ou a foto de capa: POST /empresa/imagens/{tipo} */
    public function enviarImagem(Request $request, string $tipo): JsonResponse
    {
        abort_unless(isset(self::IMAGENS[$tipo]), 404);

        $request->validate(['imagem' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        $empresa = $request->user()->empresa;
        $campo = self::IMAGENS[$tipo];
        $antiga = $empresa->{$campo};

        $empresa->{$campo} = $request->file('imagem')->store("empresas/{$empresa->id}/pagina", 'public');
        $empresa->save();

        if ($antiga) {
            Storage::disk('public')->delete($antiga);
        }

        return response()->json($empresa);
    }

    public function removerImagem(Request $request, string $tipo): JsonResponse
    {
        abort_unless(isset(self::IMAGENS[$tipo]), 404);

        $empresa = $request->user()->empresa;
        $campo = self::IMAGENS[$tipo];

        if ($empresa->{$campo}) {
            Storage::disk('public')->delete($empresa->{$campo});
            $empresa->{$campo} = null;
            $empresa->save();
        }

        return response()->json($empresa);
    }
}
