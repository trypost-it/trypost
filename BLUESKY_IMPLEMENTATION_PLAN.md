# Bluesky Integration Plan

## Overview

Bluesky uses o protocolo AT (ATProto) e não usa OAuth tradicional. A autenticação é feita com username/password que retorna JWT tokens.

## Arquitetura

### Autenticação
- **Não usa OAuth** - usa login com identifier (handle/email) + password
- Retorna `accessJwt` (curta duração) e `refreshJwt` (longa duração)
- Suporta instâncias customizadas (não só bsky.social)

### Endpoints Principais
- `com.atproto.server.createSession` - Login
- `com.atproto.server.refreshSession` - Refresh token
- `com.atproto.repo.createRecord` - Criar post
- `com.atproto.repo.uploadBlob` - Upload de mídia

### Limitações
- **Texto**: 300 caracteres
- **Imagens**: Máximo 4, até 1MB cada
- **Vídeo**: Máximo 1 (não pode misturar com múltiplas imagens)

---

## Implementação

### 1. Dependências

```bash
composer require socialiteproviders/bluesky
# OU usar HTTP client direto já que não é OAuth
```

Alternativa: Usar HTTP client direto (recomendado, como postiz faz).

### 2. Arquivos a Criar/Modificar

#### Novos Arquivos
- `app/Http/Controllers/Auth/BlueskyController.php` - Controller de conexão
- `app/Services/Social/BlueskyPublisher.php` - Serviço de publicação
- `resources/js/components/posts/previews/BlueskyPreview.vue` - Preview component
- `resources/js/pages/accounts/BlueskyConnect.vue` - Tela de conexão (custom, não OAuth popup)

#### Arquivos a Modificar
- `app/Enums/SocialAccount/Platform.php` - Adicionar Bluesky
- `app/Enums/PostPlatform/ContentType.php` - Adicionar BlueskyPost
- `app/Jobs/PublishToSocialPlatform.php` - Registrar BlueskyPublisher
- `config/trypost.php` - Adicionar toggle de plataforma
- `routes/web.php` - Adicionar rotas
- `resources/js/components/posts/previews/PlatformPreview.vue` - Importar BlueskyPreview
- `resources/js/components/posts/previews/index.ts` - Exportar BlueskyPreview
- `resources/js/pages/posts/Edit.vue` - Adicionar logo/label

---

### 3. Platform Enum

```php
case Bluesky = 'bluesky';

public function label(): string
{
    return match ($this) {
        // ...
        self::Bluesky => 'Bluesky',
    };
}

public function color(): string
{
    return match ($this) {
        // ...
        self::Bluesky => '#0085FF',
    };
}

public function maxContentLength(): int
{
    return match ($this) {
        // ...
        self::Bluesky => 300,
    };
}

public function maxImages(): int
{
    return match ($this) {
        // ...
        self::Bluesky => 4,
    };
}

public function supportsTextOnly(): bool
{
    return match ($this) {
        // ...
        self::Bluesky => true,
    };
}
```

---

### 4. ContentType Enum

```php
case BlueskyPost = 'bluesky_post';

public static function defaultFor(Platform $platform): ?self
{
    return match ($platform) {
        // ...
        Platform::Bluesky => self::BlueskyPost,
    };
}
```

---

### 5. BlueskyController (Conexão Custom)

Como Bluesky não usa OAuth, precisamos de uma tela custom para inserir credenciais.

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class BlueskyController extends Controller
{
    protected SocialPlatform $platform = SocialPlatform::Bluesky;

    private const API_BASE = 'https://bsky.social/xrpc';

    public function connect(Request $request)
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        return Inertia::render('accounts/BlueskyConnect', [
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service' => 'required|url',
            'identifier' => 'required|string',
            'password' => 'required|string|min:3',
        ]);

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        // Authenticate with Bluesky
        $response = Http::post($request->service . '/xrpc/com.atproto.server.createSession', [
            'identifier' => $request->identifier,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['password' => 'Invalid credentials']);
        }

        $data = $response->json();

        // Get profile
        $profileResponse = Http::withToken($data['accessJwt'])
            ->get($request->service . '/xrpc/app.bsky.actor.getProfile', [
                'actor' => $data['did'],
            ]);

        $profile = $profileResponse->json();

        // Check existing
        $existingAccount = $workspace->socialAccounts()
            ->where('platform', $this->platform->value)
            ->first();

        if ($existingAccount && ! $existingAccount->isDisconnected()) {
            return back()->withErrors(['identifier' => 'Bluesky is already connected.']);
        }

        $avatarPath = isset($profile['avatar']) ? uploadFromUrl($profile['avatar']) : null;

        $accountData = [
            'platform' => $this->platform->value,
            'platform_user_id' => $data['did'],
            'username' => $data['handle'],
            'display_name' => $profile['displayName'] ?? $data['handle'],
            'avatar_url' => $avatarPath,
            'access_token' => $data['accessJwt'],
            'refresh_token' => $data['refreshJwt'],
            'token_expires_at' => now()->addHours(2), // JWT expires quickly
            'meta' => [
                'service' => $request->service,
                'identifier' => $request->identifier,
                'password' => encrypt($request->password), // Store encrypted for re-auth
            ],
        ];

        if ($existingAccount) {
            $existingAccount->update($accountData);
            $existingAccount->markAsConnected();
        } else {
            $accountData['status'] = Status::Connected;
            $workspace->socialAccounts()->create($accountData);
        }

        session()->flash('flash.banner', 'Bluesky connected successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('accounts');
    }
}
```

---

### 6. BlueskyPublisher Service

```php
<?php

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueskyPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $service = $account->meta['service'] ?? 'https://bsky.social';

        // Refresh token if needed
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $medias = $postPlatform->media;
        $embed = null;

        // Upload images if present
        if ($medias->count() > 0) {
            $images = [];
            foreach ($medias->take(4) as $media) {
                if ($media->type === 'image') {
                    $blob = $this->uploadBlob($account, $service, $media->url, $media->mime_type);
                    $images[] = [
                        'alt' => '',
                        'image' => $blob,
                    ];
                }
            }

            if (count($images) > 0) {
                $embed = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => $images,
                ];
            }
        }

        // Create post record
        $record = [
            'text' => $postPlatform->content ?? '',
            'createdAt' => now()->toIso8601String(),
        ];

        if ($embed) {
            $record['embed'] = $embed;
        }

        $response = Http::withToken($account->access_token)
            ->post("{$service}/xrpc/com.atproto.repo.createRecord", [
                'repo' => $account->platform_user_id, // DID
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

        if ($response->failed()) {
            Log::error('Bluesky post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->handleApiError($response);
        }

        $data = $response->json();

        // Extract post ID from URI (at://did/app.bsky.feed.post/xxx)
        $uri = $data['uri'];
        $postId = basename($uri);

        return [
            'id' => $postId,
            'url' => $this->buildPostUrl($account->username, $postId),
        ];
    }

    private function uploadBlob(SocialAccount $account, string $service, string $url, string $mimeType): array
    {
        $imageContent = file_get_contents($url);

        // Bluesky has 1MB limit
        if (strlen($imageContent) > 1000000) {
            // TODO: Resize image
        }

        $response = Http::withToken($account->access_token)
            ->withHeaders(['Content-Type' => $mimeType])
            ->withBody($imageContent, $mimeType)
            ->post("{$service}/xrpc/com.atproto.repo.uploadBlob");

        if ($response->failed()) {
            throw new \Exception('Failed to upload blob: ' . $response->body());
        }

        return $response->json()['blob'];
    }

    private function buildPostUrl(string $handle, string $postId): string
    {
        return "https://bsky.app/profile/{$handle}/post/{$postId}";
    }

    public function refreshToken(SocialAccount $account): void
    {
        $service = $account->meta['service'] ?? 'https://bsky.social';

        // Try refresh first
        $response = Http::withToken($account->refresh_token)
            ->post("{$service}/xrpc/com.atproto.server.refreshSession");

        if ($response->successful()) {
            $data = $response->json();
            $account->update([
                'access_token' => $data['accessJwt'],
                'refresh_token' => $data['refreshJwt'],
                'token_expires_at' => now()->addHours(2),
            ]);
            return;
        }

        // If refresh fails, re-authenticate with stored credentials
        if (isset($account->meta['password'])) {
            $password = decrypt($account->meta['password']);
            $identifier = $account->meta['identifier'];

            $response = Http::post("{$service}/xrpc/com.atproto.server.createSession", [
                'identifier' => $identifier,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $account->update([
                    'access_token' => $data['accessJwt'],
                    'refresh_token' => $data['refreshJwt'],
                    'token_expires_at' => now()->addHours(2),
                ]);
                return;
            }
        }

        throw new TokenExpiredException('Bluesky session expired');
    }

    private function handleApiError($response): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? 'Unknown error';
        $message = $body['message'] ?? $response->body();

        if ($error === 'ExpiredToken' || $error === 'InvalidToken') {
            throw new TokenExpiredException("Bluesky: {$message}");
        }

        throw new \Exception("Bluesky API error: {$message}");
    }
}
```

---

### 7. Routes

```php
// Bluesky (custom auth, not OAuth)
Route::get('connect/bluesky', [BlueskyController::class, 'connect'])->name('social.bluesky.connect');
Route::post('connect/bluesky', [BlueskyController::class, 'store'])->name('social.bluesky.store');
```

---

### 8. BlueskyConnect.vue (Frontend)

Tela customizada para inserir credenciais do Bluesky.

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';

const form = useForm({
    service: 'https://bsky.social',
    identifier: '',
    password: '',
});

const submit = () => {
    form.post(route('social.bluesky.store'));
};
</script>

<template>
    <Head title="Connect Bluesky" />
    <AppLayout>
        <div class="max-w-md mx-auto py-8 px-4">
            <h1 class="text-2xl font-bold mb-6">Connect Bluesky</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <Label for="service">Service URL</Label>
                    <Input
                        id="service"
                        v-model="form.service"
                        type="url"
                        placeholder="https://bsky.social"
                    />
                </div>

                <div>
                    <Label for="identifier">Handle or Email</Label>
                    <Input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        placeholder="yourhandle.bsky.social"
                    />
                    <p v-if="form.errors.identifier" class="text-sm text-red-500 mt-1">
                        {{ form.errors.identifier }}
                    </p>
                </div>

                <div>
                    <Label for="password">App Password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        Use an App Password from Settings → App Passwords
                    </p>
                    <p v-if="form.errors.password" class="text-sm text-red-500 mt-1">
                        {{ form.errors.password }}
                    </p>
                </div>

                <Alert>
                    <AlertDescription>
                        We recommend using an App Password instead of your main password.
                        Create one at bsky.app → Settings → App Passwords.
                    </AlertDescription>
                </Alert>

                <Button type="submit" :disabled="form.processing" class="w-full">
                    {{ form.processing ? 'Connecting...' : 'Connect Bluesky' }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
```

---

### 9. BlueskyPreview.vue

Similar ao ThreadsPreview, com layout simples de texto e imagens.

---

### 10. Assets

- Adicionar `public/images/accounts/bluesky.png` (logo do Bluesky)

---

## Ordem de Implementação

1. [ ] Adicionar Bluesky ao Platform enum
2. [ ] Adicionar BlueskyPost ao ContentType enum
3. [ ] Criar BlueskyController
4. [ ] Adicionar rotas
5. [ ] Criar BlueskyConnect.vue (frontend)
6. [ ] Criar BlueskyPublisher service
7. [ ] Registrar no PublishToSocialPlatform job
8. [ ] Criar BlueskyPreview.vue
9. [ ] Atualizar PlatformPreview.vue
10. [ ] Atualizar Edit.vue (logo, label)
11. [ ] Adicionar config em trypost.php
12. [ ] Adicionar logo
13. [ ] Testar conexão
14. [ ] Testar publicação

---

## Considerações de Segurança

- **App Password**: Recomendado usar App Password ao invés da senha principal
- **Armazenamento**: Password é encriptado no campo `meta`
- **Token Refresh**: JWT tokens expiram rápido, refresh automático implementado

---

## Diferenças do OAuth

| Aspecto | OAuth (Pinterest, etc) | Bluesky |
|---------|------------------------|---------|
| Fluxo | Popup redirect | Formulário inline |
| Tokens | Via OAuth provider | Via API direta |
| Callback | URL de callback | Não necessário |
| Refresh | Refresh token | Re-autenticação ou refresh |

---

## Sources

- [Bluesky API Get Started](https://docs.bsky.app/docs/get-started)
- [Bluesky Posts Guide](https://docs.bsky.app/docs/advanced-guides/posts)
- [Upload Blob API](https://docs.bsky.app/docs/api/com-atproto-repo-upload-blob)
- [Postiz Bluesky Implementation](~/Code/postiz-app)
