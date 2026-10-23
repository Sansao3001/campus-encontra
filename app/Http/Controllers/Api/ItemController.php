<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    // Listar todos os itens: operação pública
    public function index()
    {
        $items = Item::with(['user:id,name'])->latest()->get();

        // Resolve mantém o formato actual (array), mas garante que a
        // representação pública do autor nunca inclui o email.
        return response()->json(
            ItemResource::collection($items)->resolve()
        );
    }

    // Consultar um item: operação pública
    public function show(Item $item)
    {
        $item->load(['user:id,name']);

        return response()->json(
            (new ItemResource($item))->resolve()
        );
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

            // A autoria é determinada pelo servidor e o estado inicial
            // é sempre "open". O cliente não pode atribuir nenhum dos dois.
            'user_id' => 'prohibited',
            'status' => 'prohibited',
        ]);

        // O estado inicial é definido pelo servidor, não pelo cliente.
        // Atribuí-lo explicitamente garante que o modelo devolvido já o contém.
        $validated['status'] = 'open';

        $item = $request->user()
            ->items()
            ->create($validated);

        $item->load(['user:id,name']);

        return response()->json(
            (new ItemResource($item))->resolve(),
            201
        );
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

            // A autoria e o estado não são alteráveis por esta rota.
            // O estado será alterado apenas pelo fluxo de recuperação.
            'user_id' => 'prohibited',
            'status' => 'prohibited',
        ]);

        $item->update($validated);
        $item->load(['user:id,name']);

        return response()->json(
            (new ItemResource($item))->resolve()
        );
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
