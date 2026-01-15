<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Calendar, Clock, Image, Video, FileText, X } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    socialAccounts: SocialAccount[];
    scheduledDate?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
    {
        title: props.workspace.name,
        href: `/workspaces/${props.workspace.id}`,
    },
    {
        title: 'Calendário',
        href: `/workspaces/${props.workspace.id}/calendar`,
    },
    {
        title: 'Novo Post',
        href: `/workspaces/${props.workspace.id}/posts/create`,
    },
];

interface PlatformContent {
    social_account_id: string;
    platform: string;
    content: string;
    media_ids: string[];
}

const selectedPlatforms = ref<string[]>([]);
const platformContents = ref<Record<string, PlatformContent>>({});
const globalContent = ref('');
const useGlobalContent = ref(true);
const uploadedMedia = ref<Array<{ id: string; url: string; type: string; original_filename: string }>>([]);
const isUploading = ref(false);

const form = useForm({
    status: 'scheduled',
    scheduled_at: props.scheduledDate ? `${props.scheduledDate}T09:00` : '',
    platforms: [] as PlatformContent[],
});

watch(selectedPlatforms, (newVal) => {
    newVal.forEach(accountId => {
        const account = props.socialAccounts.find(a => a.id === accountId);
        if (account && !platformContents.value[accountId]) {
            platformContents.value[accountId] = {
                social_account_id: accountId,
                platform: account.platform,
                content: '',
                media_ids: [],
            };
        }
    });

    Object.keys(platformContents.value).forEach(accountId => {
        if (!newVal.includes(accountId)) {
            delete platformContents.value[accountId];
        }
    });
});

watch(globalContent, (newVal) => {
    if (useGlobalContent.value) {
        Object.keys(platformContents.value).forEach(accountId => {
            platformContents.value[accountId].content = newVal;
        });
    }
});

const getPlatformEmoji = (platform: string): string => {
    const emojis: Record<string, string> = {
        linkedin: '💼',
        twitter: '𝕏',
        tiktok: '🎵',
    };
    return emojis[platform] || '🌐';
};

const getPlatformLabel = (platform: string): string => {
    const labels: Record<string, string> = {
        linkedin: 'LinkedIn',
        twitter: 'X (Twitter)',
        tiktok: 'TikTok',
    };
    return labels[platform] || platform;
};

const getCharLimit = (platform: string): number => {
    const limits: Record<string, number> = {
        linkedin: 3000,
        twitter: 280,
        tiktok: 2200,
    };
    return limits[platform] || 5000;
};

const handleFileUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (!files || files.length === 0) return;

    isUploading.value = true;

    for (const file of Array.from(files)) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch('/media', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();
            uploadedMedia.value.push(data);
        } catch (error) {
            console.error('Upload failed:', error);
        }
    }

    isUploading.value = false;
    input.value = '';
};

const removeMedia = (mediaId: string) => {
    uploadedMedia.value = uploadedMedia.value.filter(m => m.id !== mediaId);

    fetch(`/media/${mediaId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
    });
};

const submit = () => {
    const platforms = Object.values(platformContents.value).map(pc => ({
        ...pc,
        media_ids: uploadedMedia.value.map(m => m.id),
    }));

    form.platforms = platforms;
    form.post(`/workspaces/${props.workspace.id}/posts`);
};

const canSubmit = computed(() => {
    return selectedPlatforms.value.length > 0 &&
           form.scheduled_at &&
           Object.values(platformContents.value).some(pc => pc.content.trim() !== '');
});
</script>

<template>
    <Head title="Novo Post" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Criar Post</h1>
                <p class="text-muted-foreground">
                    Agende um novo post para suas redes sociais
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Selecione as Redes Sociais</CardTitle>
                            <CardDescription>
                                Escolha onde você quer publicar
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-4">
                                <div
                                    v-for="account in socialAccounts"
                                    :key="account.id"
                                    class="flex items-center gap-2"
                                >
                                    <Checkbox
                                        :id="account.id"
                                        :checked="selectedPlatforms.includes(account.id)"
                                        @update:checked="(checked) => {
                                            if (checked) {
                                                selectedPlatforms.push(account.id);
                                            } else {
                                                selectedPlatforms = selectedPlatforms.filter(id => id !== account.id);
                                            }
                                        }"
                                    />
                                    <Label :for="account.id" class="flex items-center gap-2 cursor-pointer">
                                        <span>{{ getPlatformEmoji(account.platform) }}</span>
                                        <span>{{ account.display_name || account.username }}</span>
                                    </Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="selectedPlatforms.length > 0">
                        <CardHeader>
                            <CardTitle>Conteúdo</CardTitle>
                            <CardDescription>
                                Escreva o conteúdo do seu post
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="selectedPlatforms.length > 1" class="flex items-center gap-2">
                                <Checkbox
                                    id="use-global"
                                    v-model:checked="useGlobalContent"
                                />
                                <Label for="use-global">
                                    Usar o mesmo conteúdo para todas as redes
                                </Label>
                            </div>

                            <div v-if="useGlobalContent && selectedPlatforms.length > 0" class="space-y-2">
                                <Label>Conteúdo</Label>
                                <textarea
                                    v-model="globalContent"
                                    class="w-full min-h-[150px] rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    placeholder="O que você quer compartilhar?"
                                />
                            </div>

                            <div v-else class="space-y-4">
                                <div
                                    v-for="accountId in selectedPlatforms"
                                    :key="accountId"
                                    class="space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <Label class="flex items-center gap-2">
                                            <span>{{ getPlatformEmoji(platformContents[accountId]?.platform) }}</span>
                                            {{ getPlatformLabel(platformContents[accountId]?.platform) }}
                                        </Label>
                                        <span class="text-xs text-muted-foreground">
                                            {{ platformContents[accountId]?.content.length || 0 }} /
                                            {{ getCharLimit(platformContents[accountId]?.platform) }}
                                        </span>
                                    </div>
                                    <textarea
                                        v-model="platformContents[accountId].content"
                                        class="w-full min-h-[100px] rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        :placeholder="`Conteúdo para ${getPlatformLabel(platformContents[accountId]?.platform)}`"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="selectedPlatforms.length > 0">
                        <CardHeader>
                            <CardTitle>Mídia</CardTitle>
                            <CardDescription>
                                Adicione imagens ou vídeos ao seu post
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="flex flex-wrap gap-4">
                                    <div
                                        v-for="media in uploadedMedia"
                                        :key="media.id"
                                        class="relative group"
                                    >
                                        <div class="w-24 h-24 rounded-lg overflow-hidden border">
                                            <img
                                                v-if="media.type === 'image'"
                                                :src="media.url"
                                                :alt="media.original_filename"
                                                class="w-full h-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="w-full h-full flex items-center justify-center bg-muted"
                                            >
                                                <Video class="h-8 w-8 text-muted-foreground" />
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeMedia(media.id)"
                                            class="absolute -top-2 -right-2 bg-destructive text-destructive-foreground rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </div>

                                    <label class="w-24 h-24 rounded-lg border-2 border-dashed flex items-center justify-center cursor-pointer hover:bg-accent transition-colors">
                                        <input
                                            type="file"
                                            accept="image/*,video/*"
                                            multiple
                                            class="hidden"
                                            @change="handleFileUpload"
                                            :disabled="isUploading"
                                        />
                                        <div class="text-center">
                                            <Image class="h-6 w-6 mx-auto text-muted-foreground" />
                                            <span class="text-xs text-muted-foreground">
                                                {{ isUploading ? 'Enviando...' : 'Adicionar' }}
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Agendamento</CardTitle>
                            <CardDescription>
                                Quando você quer publicar?
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="scheduled_at">Data e Hora</Label>
                                <Input
                                    id="scheduled_at"
                                    type="datetime-local"
                                    v-model="form.scheduled_at"
                                />
                                <p v-if="form.errors.scheduled_at" class="text-sm text-destructive">
                                    {{ form.errors.scheduled_at }}
                                </p>
                            </div>

                            <div class="pt-4 space-y-2">
                                <Button
                                    type="button"
                                    class="w-full"
                                    :disabled="!canSubmit || form.processing"
                                    @click="submit"
                                >
                                    <Clock class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Agendando...' : 'Agendar Post' }}
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    :disabled="selectedPlatforms.length === 0 || form.processing"
                                    @click="() => { form.status = 'draft'; submit(); }"
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    Salvar como Rascunho
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="selectedPlatforms.length > 0">
                        <CardHeader>
                            <CardTitle>Resumo</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Redes selecionadas</span>
                                    <span>{{ selectedPlatforms.length }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Mídias anexadas</span>
                                    <span>{{ uploadedMedia.length }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
