<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Claim;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ItemController extends Controller
{
    private const PHOTO_RULES = [
        'nullable',
        'image',
        'mimes:jpg,jpeg,png,webp',
        'max:5120',
        'dimensions:max_width=6000,max_height=6000',
    ];

    // Listar os itens: operação pública, com pesquisa, filtros e paginação
    public function index(Request $request)
    {
        $validated = $request->validate(
            $this->indexRules(),
            $this->indexValidationMessages()
        );

        $query = Item::query()->visible()->with(['user:id,name']);

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

    // Listar apenas os itens do utilizador autenticado.
    // Sem parâmetros devolve um array (compatibilidade); com page/per_page
    // devolve o envelope paginado habitual.
    public function mine(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Item::with(['user:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->hasAny(['page', 'per_page'])) {
            return ItemResource::collection(
                $query->paginate((int) ($validated['per_page'] ?? 12))->withQueryString()
            );
        }

        return response()->json(
            ItemResource::collection($query->get())->resolve()
        );
    }

    // Consultar um item: operação pública. Anúncios retirados pela moderação
    // só ficam visíveis para o próprio autor ou para administradores.
    public function show(Request $request, Item $item)
    {
        $viewer = $request->user('sanctum');

        if ($item->isModerated()) {
            $canView = $viewer !== null
                && ($viewer->id === $item->user_id || $viewer->isAdmin());

            abort_unless($canView, 404);
        }

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
        $validated['status'] = 'open';

        $photo = $request->file('photo');
        unset($validated['photo'], $validated['remove_photo'], $validated['photo_path']);

        $storedPath = null;

        if ($photo) {
            $storedPath = $photo->store('items', Item::photoDisk());
            $validated['photo_path'] = $storedPath;
        }

        try {
            $item = $request->user()
                ->items()
                ->create($validated);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(Item::photoDisk())->delete($storedPath);
            }

            throw $exception;
        }

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

        $photo = $request->file('photo');
        $removePhoto = $request->boolean('remove_photo');
        $photoDisk = Item::photoDisk();
        $storedPath = null;
        $oldPhoto = null;

        try {
            $item = DB::transaction(function () use (
                $item,
                $validated,
                $photo,
                $removePhoto,
                $photoDisk,
                &$storedPath,
                &$oldPhoto
            ) {
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

                $attributes = Arr::except($validated, ['photo', 'remove_photo', 'photo_path']);

                if ($removePhoto && $lockedItem->photo_path) {
                    $oldPhoto = $lockedItem->photo_path;
                    $attributes['photo_path'] = null;
                }

                if ($photo) {
                    $storedPath = $photo->store('items', $photoDisk);
                    $oldPhoto = $lockedItem->photo_path;
                    $attributes['photo_path'] = $storedPath;
                }

                $lockedItem->update($attributes);

                return $lockedItem;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk($photoDisk)->delete($storedPath);
            }

            throw $exception;
        }

        if ($oldPhoto && $oldPhoto !== $item->photo_path) {
            Storage::disk($photoDisk)->delete($oldPhoto);
        }

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
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'occurrence_date' => ['required', 'date'],
            'photo' => self::PHOTO_RULES,
            'type' => ['required', 'in:lost,found'],

            // A autoria é determinada pelo servidor e o estado inicial
            // é sempre "open". O cliente não pode atribuir nenhum dos dois.
            // O caminho da fotografia também é gerido pelo servidor.
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'photo_path' => ['prohibited'],
            'remove_photo' => ['prohibited'],
            'moderated_at' => ['prohibited'],
            'moderation_reason' => ['prohibited'],
            'moderated_by_id' => ['prohibited'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'occurrence_date' => ['sometimes', 'required', 'date'],
            'photo' => self::PHOTO_RULES,
            'remove_photo' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'required', 'in:lost,found'],

            // A autoria e o estado não são alteráveis por esta rota.
            // O estado será alterado apenas pelo fluxo de recuperação.
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'photo_path' => ['prohibited'],
            'moderated_at' => ['prohibited'],
            'moderation_reason' => ['prohibited'],
            'moderated_by_id' => ['prohibited'],
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
            'description.max' => 'A descrição não pode ter mais de 5000 caracteres.',
            'location.required' => 'Indica o local onde o objecto foi perdido ou encontrado.',
            'location.string' => 'O local deve ser texto.',
            'location.max' => 'O local não pode ter mais de 255 caracteres.',
            'occurrence_date.required' => 'Indica a data da ocorrência.',
            'occurrence_date.date' => 'Indica uma data de ocorrência válida.',
            'photo.image' => 'A fotografia deve ser uma imagem.',
            'photo.mimes' => 'A fotografia deve ser JPEG, PNG ou WebP.',
            'photo.max' => 'A fotografia não pode ter mais de 5 MB.',
            'photo.dimensions' => 'A fotografia não pode exceder 6000 por 6000 píxeis.',
            'photo.uploaded' => 'Não foi possível carregar a fotografia. Tenta novamente.',
            'type.required' => 'Indica se o objecto foi perdido ou encontrado.',
            'type.in' => 'O tipo deve ser "perdido" ou "encontrado".',
            'user_id.prohibited' => 'O autor do objecto é definido pelo servidor.',
            'status.prohibited' => 'O estado do objecto é definido pelo servidor.',
            'photo_path.prohibited' => 'A fotografia é enviada como ficheiro, não como caminho.',
            'remove_photo.prohibited' => 'A fotografia é gerida pelo servidor.',
            'remove_photo.boolean' => 'O pedido de remoção da fotografia é inválido.',
        ];
    }
}
