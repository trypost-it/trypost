# Mastodon Integration Plan

## Overview

Mastodon é uma rede social federada que usa OAuth 2.0 para autenticação. Diferente de outras plataformas, cada usuário pode estar em uma instância (servidor) diferente, o que requer suporte a múltiplas instâncias.

## Arquitetura

### Autenticação
- **Usa OAuth 2.0** - fluxo padrão de autorização
- **Suporte a múltiplas instâncias** - não apenas mastodon.social
- **App registration dinâmico** - para instâncias customizadas
- **Scopes necessários**: `read:accounts`, `write:statuses`, `write:media`

### Endpoints Principais
- `POST /api/v1/apps` - Registrar aplicação (para instâncias customizadas)
- `GET /oauth/authorize` - Autorização do usuário
- `POST /oauth/token` - Obter access token
- `GET /api/v1/accounts/verify_credentials` - Dados do usuário
- `POST /api/v1/statuses` - Criar post (toot)
- `POST /api/v1/media` - Upload de mídia

### Limitações
- **Texto**: 500 caracteres (padrão, pode variar por instância)
- **Imagens**: Máximo 4 por post
- **Vídeo**: Suportado
- **Visibilidade**: public, unlisted, private, direct

---

## Implementação

### 1. Arquivos a Criar/Modificar

#### Novos Arquivos
- `app/Http/Controllers/Auth/MastodonController.php` - Controller de conexão
- `app/Services/Social/MastodonPublisher.php` - Serviço de publicação
- `resources/js/components/posts/previews/MastodonPreview.vue` - Preview component
- `resources/js/pages/accounts/MastodonConnect.vue` - Tela para inserir instância

#### Arquivos a Modificar
- `app/Enums/SocialAccount/Platform.php` - Adicionar Mastodon
- `app/Enums/PostPlatform/ContentType.php` - Adicionar MastodonPost
- `app/Jobs/PublishToSocialPlatform.php` - Registrar MastodonPublisher
- `config/services.php` - Adicionar config Mastodon (para mastodon.social padrão)
- `config/trypost.php` - Adicionar toggle de plataforma
- `routes/web.php` - Adicionar rotas
- `resources/js/components/posts/previews/PlatformPreview.vue` - Importar MastodonPreview
- `resources/js/components/posts/previews/index.ts` - Exportar MastodonPreview
- `resources/js/pages/posts/Edit.vue` - Adicionar logo/label

---

### 2. Platform Enum

```php
case Mastodon = 'mastodon';

public function label(): string
{
    return match ($this) {
        // ...
        self::Mastodon => 'Mastodon',
    };
}

public function color(): string
{
    return match ($this) {
        // ...
        self::Mastodon => '#6364FF',
    };
}

public function maxContentLength(): int
{
    return match ($this) {
        // ...
        self::Mastodon => 500,
    };
}

public function maxImages(): int
{
    return match ($this) {
        // ...
        self::Mastodon => 4,
    };
}

public function supportsTextOnly(): bool
{
    return match ($this) {
        // ...
        self::Mastodon => true,
    };
}
```

---

### 3. ContentType Enum

```php
case MastodonPost = 'mastodon_post';

public static function defaultFor(Platform $platform): ?self
{
    return match ($platform) {
        // ...
        Platform::Mastodon => self::MastodonPost,
    };
}
```

---

### 4. MastodonController

Como Mastodon requer que o usuário informe sua instância antes do OAuth:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MastodonController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Mastodon;

    private const SCOPES = 'read:accounts write:statuses write:media';

    /**
     * Show form to enter Mastodon instance URL
     */
    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        return Inertia::render('accounts/MastodonConnect', [
            'errors' => session('errors')?->getBag('default')?->toArray() ?? [],
        ]);
    }

    /**
     * Register app on instance and redirect to OAuth
     */
    public function authorize(Request $request): SymfonyResponse|RedirectResponse
    {
        $request->validate([
            'instance' => 'required|url',
        ]);

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $instance = rtrim($request->instance, '/');

        try {
            // Register app on the instance
            $appResponse = Http::post("{$instance}/api/v1/apps", [
                'client_name' => config('app.name'),
                'redirect_uris' => route('social.mastodon.callback'),
                'scopes' => self::SCOPES,
                'website' => config('app.url'),
            ]);

            if ($appResponse->failed()) {
                Log::error('Mastodon app registration failed', [
                    'instance' => $instance,
                    'body' => $appResponse->body(),
                ]);
                return back()->withErrors(['instance' => 'Could not connect to this Mastodon instance.']);
            }

            $app = $appResponse->json();

            // Store in session for callback
            $state = bin2hex(random_bytes(16));
            session([
                'mastodon_instance' => $instance,
                'mastodon_client_id' => $app['client_id'],
                'mastodon_client_secret' => $app['client_secret'],
                'mastodon_oauth_state' => $state,
                'social_connect_workspace' => $workspace->id,
            ]);

            // Redirect to OAuth
            $params = http_build_query([
                'client_id' => $app['client_id'],
                'response_type' => 'code',
                'redirect_uri' => route('social.mastodon.callback'),
                'scope' => self::SCOPES,
                'state' => $state,
            ]);

            return Inertia::location("{$instance}/oauth/authorize?{$params}");
        } catch (\Exception $e) {
            Log::error('Mastodon connection error', ['error' => $e->getMessage()]);
            return back()->withErrors(['instance' => 'Error connecting to Mastodon instance.']);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');
        $savedState = session('mastodon_oauth_state');
        $instance = session('mastodon_instance');
        $clientId = session('mastodon_client_id');
        $clientSecret = session('mastodon_client_secret');

        if (! $workspaceId || ! $instance) {
            $this->clearMastodonSession();
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        if ($request->state !== $savedState) {
            $this->clearMastodonSession();
            return $this->popupCallback(false, 'Invalid state. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            $this->clearMastodonSession();
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        try {
            // Exchange code for token
            $tokenResponse = Http::asForm()->post("{$instance}/oauth/token", [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => route('social.mastodon.callback'),
                'code' => $request->code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Mastodon token exchange failed', ['body' => $tokenResponse->body()]);
                $this->clearMastodonSession();
                return $this->popupCallback(false, 'Failed to authenticate.', $this->platform->value);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Get user profile
            $profileResponse = Http::withToken($accessToken)
                ->get("{$instance}/api/v1/accounts/verify_credentials");

            if ($profileResponse->failed()) {
                $this->clearMastodonSession();
                return $this->popupCallback(false, 'Failed to get profile.', $this->platform->value);
            }

            $profile = $profileResponse->json();

            // Check existing
            $existingAccount = $workspace->socialAccounts()
                ->where('platform', $this->platform->value)
                ->first();

            if ($existingAccount && ! $existingAccount->isDisconnected()) {
                $this->clearMastodonSession();
                return $this->popupCallback(false, 'Mastodon is already connected.', $this->platform->value);
            }

            $avatarPath = uploadFromUrl($profile['avatar'] ?? null);

            $accountData = [
                'platform' => $this->platform->value,
                'platform_user_id' => $profile['id'],
                'username' => $profile['acct'],
                'display_name' => $profile['display_name'] ?: $profile['username'],
                'avatar_url' => $avatarPath,
                'access_token' => $accessToken,
                'refresh_token' => null, // Mastodon tokens don't expire
                'token_expires_at' => null,
                'meta' => [
                    'instance' => $instance,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ];

            if ($existingAccount) {
                $existingAccount->update($accountData);
                $existingAccount->markAsConnected();
                $this->clearMastodonSession();
                return $this->popupCallback(true, 'Mastodon account reconnected!', $this->platform->value);
            }

            $accountData['status'] = Status::Connected;
            $workspace->socialAccounts()->create($accountData);

            $this->clearMastodonSession();
            return $this->popupCallback(true, 'Mastodon account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Mastodon callback error', ['error' => $e->getMessage()]);
            $this->clearMastodonSession();
            return $this->popupCallback(false, 'Error connecting account.', $this->platform->value);
        }
    }

    private function clearMastodonSession(): void
    {
        session()->forget([
            'mastodon_instance',
            'mastodon_client_id',
            'mastodon_client_secret',
            'mastodon_oauth_state',
            'social_connect_workspace',
        ]);
    }
}
```

---

### 5. MastodonPublisher Service

```php
<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MastodonPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $instance = $account->meta['instance'] ?? 'https://mastodon.social';

        $medias = $postPlatform->media;
        $mediaIds = [];

        // Upload media first
        foreach ($medias->take(4) as $media) {
            $mediaId = $this->uploadMedia($account, $instance, $media->url);
            if ($mediaId) {
                $mediaIds[] = $mediaId;
            }
        }

        // Create status
        $payload = [
            'status' => $postPlatform->content ?? '',
            'visibility' => 'public',
        ];

        if (! empty($mediaIds)) {
            $payload['media_ids'] = $mediaIds;
        }

        Log::info('Mastodon publishing status', [
            'instance' => $instance,
            'user_id' => $account->platform_user_id,
            'has_media' => count($mediaIds) > 0,
        ]);

        $response = Http::withToken($account->access_token)
            ->post("{$instance}/api/v1/statuses", $payload);

        if ($response->failed()) {
            Log::error('Mastodon post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        Log::info('Mastodon post created', ['id' => $data['id']]);

        return [
            'id' => $data['id'],
            'url' => $data['url'],
        ];
    }

    private function uploadMedia(SocialAccount $account, string $instance, string $url): ?string
    {
        try {
            $fileContent = file_get_contents($url);
            if ($fileContent === false) {
                return null;
            }

            $response = Http::withToken($account->access_token)
                ->attach('file', $fileContent, basename($url))
                ->post("{$instance}/api/v1/media");

            if ($response->failed()) {
                Log::error('Mastodon media upload failed', ['body' => $response->body()]);
                return null;
            }

            return $response->json()['id'];
        } catch (\Exception $e) {
            Log::error('Mastodon media upload error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function handleApiError(Response $response): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? $response->body();

        if ($response->status() === 401 || $response->status() === 403) {
            throw new TokenExpiredException("Mastodon: {$error}");
        }

        throw new \Exception("Mastodon API error: {$error}");
    }
}
```

---

### 6. Routes

```php
// Mastodon (custom instance + OAuth)
Route::get('connect/mastodon', [MastodonController::class, 'connect'])->name('social.mastodon.connect');
Route::post('connect/mastodon', [MastodonController::class, 'authorize'])->name('social.mastodon.authorize');
Route::get('accounts/mastodon/callback', [MastodonController::class, 'callback'])->name('social.mastodon.callback');
```

---

### 7. MastodonConnect.vue

Tela para o usuário inserir a URL da instância Mastodon.

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { IconInfoCircle } from '@tabler/icons-vue';

import PopupLayout from '@/layouts/PopupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { authorize as authorizeMastodon } from '@/routes/social/mastodon';

interface Props {
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const formRef = ref<HTMLFormElement | null>(null);
const instance = ref('https://mastodon.social');
const isSubmitting = ref(false);

const submit = () => {
    isSubmitting.value = true;
    formRef.value?.submit();
};

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
</script>

<template>
    <PopupLayout title="Connect Mastodon">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <img src="/images/accounts/mastodon.png" alt="Mastodon" class="h-12 w-12" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Connect Mastodon</h1>
                    <p class="text-sm text-muted-foreground">Enter your Mastodon instance</p>
                </div>
            </div>

            <form
                ref="formRef"
                :action="authorizeMastodon.url()"
                method="POST"
                @submit.prevent="submit"
                class="space-y-4"
            >
                <input type="hidden" name="_token" :value="csrfToken" />

                <div class="space-y-2">
                    <Label for="instance">Instance URL</Label>
                    <Input
                        id="instance"
                        name="instance"
                        v-model="instance"
                        type="url"
                        placeholder="https://mastodon.social"
                        :class="{ 'border-destructive': errors?.instance }"
                        required
                    />
                    <p v-if="errors?.instance" class="text-sm text-destructive">
                        {{ errors.instance }}
                    </p>
                </div>

                <Alert>
                    <IconInfoCircle class="h-4 w-4" />
                    <AlertDescription class="inline">
                        Enter your Mastodon instance URL (e.g., mastodon.social, techhub.social, etc.)
                    </AlertDescription>
                </Alert>

                <Button type="submit" :disabled="isSubmitting" class="w-full">
                    {{ isSubmitting ? 'Connecting...' : 'Continue with Mastodon' }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
```

---

### 8. MastodonPreview.vue

Similar ao ThreadsPreview, estilo de timeline com texto e mídia.

---

## Ordem de Implementação

1. [ ] Adicionar Mastodon ao Platform enum
2. [ ] Adicionar MastodonPost ao ContentType enum
3. [ ] Criar MastodonController
4. [ ] Adicionar rotas
5. [ ] Criar MastodonConnect.vue (frontend)
6. [ ] Criar MastodonPublisher service
7. [ ] Registrar no PublishToSocialPlatform job
8. [ ] Criar MastodonPreview.vue
9. [ ] Atualizar PlatformPreview.vue
10. [ ] Atualizar Edit.vue (logo, label)
11. [ ] Adicionar config em trypost.php
12. [ ] Testar conexão com mastodon.social
13. [ ] Testar conexão com instância customizada
14. [ ] Testar publicação

---

## Diferenças do Bluesky

| Aspecto | Bluesky | Mastodon |
|---------|---------|----------|
| Auth | Credentials (JWT) | OAuth 2.0 |
| Instâncias | bsky.social (único) | Múltiplas (federado) |
| App Registration | Não necessário | Dinâmico por instância |
| Token Expiry | 2 horas (refresh) | Não expira |
| Char Limit | 300 | 500 |
| Callback | Não | Sim (OAuth) |

---

## Considerações

### Multi-Instance
- O usuário precisa informar sua instância antes de conectar
- App é registrado dinamicamente em cada instância
- client_id e client_secret são salvos no campo `meta` da conta

### Tokens
- Tokens Mastodon não expiram normalmente
- Não precisa de refresh token logic
- Se token inválido, usuário precisa reconectar

---

## Sources

- [Mastodon OAuth Docs](https://docs.joinmastodon.org/spec/oauth/)
- [Mastodon API - Statuses](https://docs.joinmastodon.org/methods/statuses/)
- [Mastodon API - Media](https://docs.joinmastodon.org/methods/media/)
- [Postiz App Implementation](~/Code/postiz-app)
