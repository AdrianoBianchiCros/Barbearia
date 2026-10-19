<?php

namespace App\Http\Controllers;

use App\Models\GaleriaFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Fotos de cortes e do espaço mostradas na galeria da página pública.
 */
class GaleriaController extends Controller
{
    public const MAX_FOTOS = 12;

    public function index(): JsonResponse
    {
        return response()->json(GaleriaFoto::orderBy('ordem')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'imagem' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'legenda' => ['nullable', 'string', 'max:120'],
        ]);

        if (GaleriaFoto::count() >= self::MAX_FOTOS) {
            throw ValidationException::withMessages([
                'imagem' => 'A galeria aceita até '.self::MAX_FOTOS.' fotos. Remova uma para enviar outra.',
            ]);
        }

        $empresaId = $request->user()->empresa_id;
        $foto = GaleriaFoto::create([
            'caminho' => $request->file('imagem')->store("empresas/{$empresaId}/galeria", 'public'),
            'legenda' => $request->input('legenda'),
            'ordem' => (int) GaleriaFoto::max('ordem') + 1,
        ]);

        return response()->json($foto, 201);
    }

    public function update(Request $request, GaleriaFoto $foto): JsonResponse
    {
        $foto->update($request->validate(['legenda' => ['nullable', 'string', 'max:120']]));

        return response()->json($foto);
    }

    public function destroy(GaleriaFoto $foto): Response
    {
        Storage::disk('public')->delete($foto->caminho);
        $foto->delete();

        return response()->noContent();
    }
}
