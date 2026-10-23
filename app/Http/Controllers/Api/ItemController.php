<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    // Listar todos os itens: operação pública
    public function index()
    {
        return response()->json(
            Item::with('user')->latest()->get()
        );
    }

    // Consultar um item: operação pública
    public function show(Item $item)
    {
        return response()->json($item->load('user'));
    }

    // Criar um item para o utilizador autenticado
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'occurrence_date' => 'required|date',
            'photo_path' => 'nullable|string|max:255',
            'type' => 'required|in:lost,found',
            'status' => 'sometimes|in:open,pending,returned',
        ]);

        $item = $request->user()
            ->items()
            ->create($validated);

        return response()->json($item, 201);
    }

    // Actualizar apenas um item do próprio utilizador
    public function update(Request $request, Item $item)
    {
        abort_unless(
            $item->user_id === $request->user()->id,
            403,
            'Não tens permissão para alterar este item.'
        );

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
            'location' => 'sometimes|required|string|max:255',
            'occurrence_date' => 'sometimes|required|date',
            'photo_path' => 'nullable|string|max:255',
            'type' => 'sometimes|required|in:lost,found',
            'status' => 'sometimes|required|in:open,pending,returned',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    // Eliminar apenas um item do próprio utilizador
    public function destroy(Request $request, Item $item)
    {
        abort_unless(
            $item->user_id === $request->user()->id,
            403,
            'Não tens permissão para eliminar este item.'
        );

        $item->delete();

        return response()->json([
            'message' => 'Item eliminado com sucesso.',
        ]);
    }
}

