<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Claim;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    // Listar os itens: operação pública, com pesquisa, filtros e paginação
    public function index(Request $request)
    {
        $validated = $request->validate(
            $this->indexRules(),
            $this->indexValidationMessages()
        );

        $query = Item::query()->with(['user:id,name']);

        $term = isset($validated['q']) ? trim($validated['q']) : null;

        if ($term !== null && $term !== '') {
            $pattern = '%'.$term.'%';

            $query->where(function ($search) use ($pattern) {
                $search
                    ->whereLike('title', $pattern)
                    ->orWhereLike('description', $pattern)
                    ->orWhereLike('category', $pattern)
                    ->orWhereLike('location', $pattern)
                    ->orWhereHas('user', function ($user) use ($pattern) {
                        $user->whereLike('name', $pattern);
                    });
            });
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 12))
            ->withQueryString();

        return ItemResource::collection($items);
    }

    // Listar apenas os itens do utilizador autenticado
    public function mine(Request $request)
    {
        $items = Item::with(['user:id,name'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

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
        $validated = $request->validate(
            $this->storeRules(),
            $this->validationMessages()
        );

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

        $validated = $request->validate(
            $this->updateRules(),
            $this->validationMessages()
        );

        $item = DB::transaction(function () use ($item, $validated) {
            $lockedItem = Item::whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status !== 'open') {
                abort(409, 'Só é possível alterar objectos em aberto.');
            }

            if (
                isset($validated['type'])
                && $validated['type'] !== $lockedItem->type
                && $lockedItem->claims()->whereIn('status', Claim::ACTIVE_STATUSES)->exists()
            ) {
                abort(409, 'Não é possível alterar o tipo de um objecto com reclamações activas.');
            }

            $lockedItem->update($validated);

            return $lockedItem;
        });

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

        DB::transaction(function () use ($item) {
            $lockedItem = Item::whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedItem->status === 'pending'
                || $lockedItem->claims()->whereIn('status', Claim::ACTIVE_STATUSES)->exists()
            ) {
                abort(409, 'Não é possível eliminar um objecto com reclamações activas.');
            }

            $lockedItem->delete();
        });

        return response()->json([
            'message' => 'Item eliminado com sucesso.',
        ]);
    }

    private function indexRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:lost,found'],
            'status' => ['nullable', 'in:open,pending,returned'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    private function indexValidationMessages(): array
    {
        return [
            'q.string' => 'O termo de pesquisa deve ser texto.',
            'q.max' => 'O termo de pesquisa não pode ter mais de 100 caracteres.',
            'type.in' => 'O tipo deve ser "perdido" ou "encontrado".',
            'status.in' => 'O estado deve ser "em aberto", "em recuperação" ou "devolvido".',
            'page.integer' => 'A página deve ser um número inteiro.',
            'page.min' => 'A página deve ser pelo menos 1.',
            'per_page.integer' => 'O número de resultados por página deve ser um número inteiro.',
            'per_page.min' => 'O número de resultados por página deve ser pelo menos 1.',
            'per_page.max' => 'O número de resultados por página não pode ser superior a 50.',
        ];
    }

    private function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'occurrence_date' => ['required', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:lost,found'],

            // A autoria é determinada pelo servidor e o estado inicial
            // é sempre "open". O cliente não pode atribuir nenhum dos dois.
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'occurrence_date' => ['sometimes', 'required', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:lost,found'],

            // A autoria e o estado não são alteráveis por esta rota.
            // O estado será alterado apenas pelo fluxo de recuperação.
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'title.required' => 'Indica o título do objecto.',
            'title.string' => 'O título deve ser texto.',
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'category.required' => 'Indica a categoria do objecto.',
            'category.string' => 'A categoria deve ser texto.',
            'category.max' => 'A categoria não pode ter mais de 100 caracteres.',
            'description.required' => 'Indica uma descrição do objecto.',
            'description.string' => 'A descrição deve ser texto.',
            'location.required' => 'Indica o local onde o objecto foi perdido ou encontrado.',
            'location.string' => 'O local deve ser texto.',
            'location.max' => 'O local não pode ter mais de 255 caracteres.',
            'occurrence_date.required' => 'Indica a data da ocorrência.',
            'occurrence_date.date' => 'Indica uma data de ocorrência válida.',
            'photo_path.string' => 'O caminho da fotografia deve ser texto.',
            'photo_path.max' => 'O caminho da fotografia não pode ter mais de 255 caracteres.',
            'type.required' => 'Indica se o objecto foi perdido ou encontrado.',
            'type.in' => 'O tipo deve ser "perdido" ou "encontrado".',
            'user_id.prohibited' => 'O autor do objecto é definido pelo servidor.',
            'status.prohibited' => 'O estado do objecto é definido pelo servidor.',
        ];
    }
}
