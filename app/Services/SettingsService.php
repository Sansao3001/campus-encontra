<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    private const CACHE_KEY = 'campus.settings.values';

    public const GROUPS = [
        'general' => [
            'label' => 'Geral',
            'description' => 'Identidade, instituição e preferências de apresentação.',
        ],
        'operations' => [
            'label' => 'Operação',
            'description' => 'Categorias sugeridas e limites técnicos do upload.',
        ],
        'notifications' => [
            'label' => 'Notificações',
            'description' => 'Estado do transporte de email. Estes valores são apenas informativos.',
        ],
        'security' => [
            'label' => 'Segurança',
            'description' => 'Protecções activas. Não são editáveis pela interface.',
        ],
    ];

    public const DEFINITIONS = [
        'general.site_name' => [
            'group' => 'general',
            'label' => 'Nome da plataforma',
            'description' => 'Nome apresentado na aplicação.',
            'type' => 'string',
            'default' => 'CampusEncontra',
            'rules' => ['required', 'string', 'min:3', 'max:80'],
        ],
        'general.site_description' => [
            'group' => 'general',
            'label' => 'Descrição',
            'description' => 'Frase curta sobre o objectivo da plataforma.',
            'type' => 'text',
            'default' => 'Objectos perdidos e encontrados no campus.',
            'rules' => ['required', 'string', 'max:255'],
        ],
        'general.institution' => [
            'group' => 'general',
            'label' => 'Instituição',
            'description' => 'Nome da universidade ou instituição.',
            'type' => 'string',
            'default' => 'Universidade',
            'rules' => ['required', 'string', 'max:120'],
        ],
        'general.academic_year' => [
            'group' => 'general',
            'label' => 'Ano académico',
            'description' => 'Formato AAAA/AAAA.',
            'type' => 'string',
            'default' => '2026/2027',
            'rules' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
        ],
        'general.timezone' => [
            'group' => 'general',
            'label' => 'Fuso horário',
            'description' => 'Usado nos períodos e nas datas dos relatórios.',
            'type' => 'select',
            'default' => 'UTC',
            'options' => [
                ['title' => 'UTC', 'value' => 'UTC'],
                ['title' => 'Maputo (África/Maputo)', 'value' => 'Africa/Maputo'],
                ['title' => 'Lisboa (Europa/Lisboa)', 'value' => 'Europe/Lisbon'],
            ],
            'rules' => ['required', 'timezone'],
        ],
        'general.date_format' => [
            'group' => 'general',
            'label' => 'Formato de data',
            'description' => 'Formato preferido para datas administrativas.',
            'type' => 'select',
            'default' => 'd/m/Y',
            'options' => [
                ['title' => '31/12/2026', 'value' => 'd/m/Y'],
                ['title' => '2026-12-31', 'value' => 'Y-m-d'],
                ['title' => '31-12-2026', 'value' => 'd-m-Y'],
            ],
            'rules' => ['required', 'in:d/m/Y,Y-m-d,d-m-Y'],
        ],
        'operations.categories' => [
            'group' => 'operations',
            'label' => 'Categorias sugeridas',
            'description' => 'Sugestões apresentadas ao publicar um objecto. Não altera categorias existentes.',
            'type' => 'list',
            'default' => ['Electrónica', 'Documentos', 'Roupa', 'Chaves', 'Material escolar', 'Outros'],
            'rules' => ['required', 'array', 'min:1', 'max:20'],
            'item_rules' => ['required', 'string', 'max:40'],
        ],
        'operations.max_photo_kb' => [
            'group' => 'operations',
            'label' => 'Limite da fotografia (KB)',
            'description' => 'Entre 512 KB e 10 MB. O limite técnico do servidor continua a prevalecer.',
            'type' => 'integer',
            'default' => 5120,
            'rules' => ['required', 'integer', 'min:512', 'max:10240'],
        ],
    ];

    /**
     * Valores efectivos, incluindo os que ainda não foram guardados.
     */
    public function values(): array
    {
        if (! Schema::hasTable('settings')) {
            return $this->defaultValues();
        }

        $stored = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );

        $values = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $values[$key] = $this->castStoredValue(
                $definition['type'],
                $stored[$key] ?? null,
                $definition['default'],
            );
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values()[$key] ?? $default;
    }

    public function publicValues(): array
    {
        $values = $this->values();

        return [
            'site_name' => $values['general.site_name'],
            'site_description' => $values['general.site_description'],
            'institution' => $values['general.institution'],
            'academic_year' => $values['general.academic_year'],
            'timezone' => $values['general.timezone'],
            'date_format' => $values['general.date_format'],
            'categories' => $values['operations.categories'],
            'max_photo_kb' => $values['operations.max_photo_kb'],
        ];
    }

    public function adminPayload(Request $request): array
    {
        $values = $this->values();
        $groups = [];

        foreach (self::GROUPS as $groupKey => $group) {
            $settings = [];

            foreach (self::DEFINITIONS as $key => $definition) {
                if ($definition['group'] !== $groupKey) {
                    continue;
                }

                $settings[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                    'type' => $definition['type'],
                    'value' => $values[$key],
                    'default' => $definition['default'],
                    'options' => $definition['options'] ?? null,
                    'read_only' => false,
                ];
            }

            if ($groupKey === 'notifications') {
                $settings = $this->notificationStatus();
            }

            if ($groupKey === 'security') {
                $settings = $this->securityStatus($request);
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => $group['label'],
                'description' => $group['description'],
                'settings' => $settings,
            ];
        }

        return [
            'groups' => $groups,
            'public' => $this->publicValues(),
            'updated_at' => Schema::hasTable('settings')
                ? Setting::query()->max('updated_at')
                : null,
        ];
    }

    /**
     * Actualiza apenas chaves conhecidas. Devolve os valores efectivos.
     */
    public function update(array $incoming, User $admin): array
    {
        if ($incoming === []) {
            throw ValidationException::withMessages([
                'settings' => 'Não foram indicadas configurações para guardar.',
            ]);
        }

        $unknown = array_values(array_diff(array_keys($incoming), array_keys(self::DEFINITIONS)));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => 'Configurações não permitidas: '.implode(', ', $unknown).'.',
            ]);
        }

        $validated = [];
        $errors = [];

        foreach ($incoming as $key => $value) {
            $definition = self::DEFINITIONS[$key];
            $rules = ['value' => $definition['rules']];

            if (($definition['type'] ?? null) === 'list') {
                $rules['value.*'] = $definition['item_rules'];
            }

            $validator = Validator::make(
                ['value' => $value],
                $rules,
                $this->validationMessages($key),
                ['value' => $definition['label']],
            );

            if ($validator->fails()) {
                $errors[$key] = $validator->errors()->all();

                continue;
            }

            $validated[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($validated, $admin): void {
            foreach ($validated as $key => $value) {
                $definition = self::DEFINITIONS[$key];

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $this->serializeValue($definition['type'], $value),
                        'type' => $definition['type'],
                        'group_key' => $definition['group'],
                        'updated_by_id' => $admin->id,
                    ],
                );
            }

            AdminAction::record(
                $admin,
                'settings.update',
                'settings',
                null,
                null,
                ['keys' => array_keys($validated)],
            );
        });

        Cache::forget(self::CACHE_KEY);

        return $this->values();
    }

    private function validationMessages(string $key): array
    {
        return match ($key) {
            'general.site_name' => [
                'value.required' => 'Indica o nome da plataforma.',
                'value.min' => 'O nome da plataforma deve ter pelo menos 3 caracteres.',
            ],
            'general.academic_year' => [
                'value.required' => 'Indica o ano académico.',
                'value.regex' => 'O ano académico deve seguir o formato AAAA/AAAA.',
            ],
            'general.timezone' => [
                'value.required' => 'Indica o fuso horário.',
                'value.timezone' => 'Indica um fuso horário válido.',
            ],
            'operations.categories' => [
                'value.required' => 'Indica pelo menos uma categoria.',
                'value.min' => 'Indica pelo menos uma categoria.',
                'value.max' => 'Não podes indicar mais de 20 categorias.',
                'value.*.required' => 'Cada categoria deve ter um nome.',
                'value.*.max' => 'Cada categoria não pode exceder 40 caracteres.',
            ],
            'operations.max_photo_kb' => [
                'value.required' => 'Indica o limite da fotografia.',
                'value.integer' => 'O limite da fotografia deve ser um número inteiro.',
                'value.min' => 'O limite mínimo da fotografia é 512 KB.',
                'value.max' => 'O limite máximo da fotografia é 10240 KB.',
            ],
            default => [],
        };
    }

    private function defaultValues(): array
    {
        $values = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $values[$key] = $definition['default'];
        }

        return $values;
    }

    private function castStoredValue(string $type, mixed $raw, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            'list' => $this->decodeList($raw, $default),
            default => (string) $raw,
        };
    }

    private function decodeList(mixed $raw, mixed $default): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_values($decoded) : $default;
    }

    private function serializeValue(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) (int) $value,
            'list' => json_encode(
                array_values(array_unique(array_filter(
                    array_map(
                        fn ($item) => trim((string) $item),
                        $value,
                    ),
                    fn ($item) => $item !== '',
                ))),
                JSON_UNESCAPED_UNICODE,
            ),
            default => trim((string) $value),
        };
    }

    private function notificationStatus(): array
    {
        $transport = (string) config('mail.default', 'log');
        $operational = ! in_array($transport, ['log', 'array'], true);

        return [
            [
                'key' => 'notifications.transport',
                'label' => 'Transporte de email',
                'description' => 'Configurado no servidor; não é editável pela interface.',
                'type' => 'string',
                'value' => $transport,
                'default' => null,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'notifications.sender',
                'label' => 'Remetente configurado',
                'description' => 'Endereço usado pelo servidor para enviar email.',
                'type' => 'string',
                'value' => (string) config('mail.from.address', ''),
                'default' => null,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'notifications.operational',
                'label' => 'Envio de email operacional',
                'description' => $operational
                    ? 'Existe um transporte configurado. Confirma o envio real antes de comunicar esta funcionalidade.'
                    : 'O transporte actual guarda mensagens em log. Nenhuma notificação é enviada a utilizadores.',
                'type' => 'boolean',
                'value' => $operational,
                'default' => false,
                'options' => null,
                'read_only' => true,
            ],
        ];
    }

    private function securityStatus(Request $request): array
    {
        return [
            [
                'key' => 'security.environment',
                'label' => 'Ambiente',
                'description' => 'Ambiente actual da aplicação.',
                'type' => 'string',
                'value' => app()->environment(),
                'default' => null,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'security.https',
                'label' => 'Pedido actual em HTTPS',
                'description' => 'Indica se a ligação recebida usa TLS.',
                'type' => 'boolean',
                'value' => $request->isSecure(),
                'default' => false,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'security.https_forced',
                'label' => 'HTTPS obrigatório em produção',
                'description' => 'Controlado por APP_FORCE_HTTPS no servidor.',
                'type' => 'boolean',
                'value' => filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),
                'default' => false,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'security.csp',
                'label' => 'CSP activa',
                'description' => 'A política de conteúdo é aplicada em produção.',
                'type' => 'boolean',
                'value' => app()->environment('production'),
                'default' => false,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'security.token_expiration',
                'label' => 'Expiração de token (minutos)',
                'description' => 'Configurada no servidor. Vazio significa sem expiração.',
                'type' => 'string',
                'value' => (string) (config('sanctum.expiration') ?? 'Sem expiração'),
                'default' => null,
                'options' => null,
                'read_only' => true,
            ],
            [
                'key' => 'security.exports_rate_limit',
                'label' => 'Limite de exportações',
                'description' => 'Máximo de 10 exportações por minuto por administrador.',
                'type' => 'string',
                'value' => '10/minuto',
                'default' => null,
                'options' => null,
                'read_only' => true,
            ],
        ];
    }
}
