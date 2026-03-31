<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconBuilding, IconBuildingStore, IconCheck, IconRocket, IconSparkles, IconUser } from '@tabler/icons-vue';

import { storeRole } from '@/actions/App/Http/Controllers/App/OnboardingController';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';

interface Persona {
    value: string;
    label: string;
    description: string;
    icon: string;
}

interface Props {
    personas: Persona[];
}

defineProps<Props>();

const form = useForm({
    persona: '',
});

const icons: Record<string, typeof IconRocket> = {
    rocket: IconRocket,
    sparkles: IconSparkles,
    building: IconBuilding,
    'building-2': IconBuilding,
    store: IconBuildingStore,
    user: IconUser,
};

const submit = () => {
    form.post(storeRole.url());
};

const isSelected = (value: string) => form.persona === value;
</script>

<template>
    <Head :title="$t('onboarding.role.page_title')" />

    <AuthLayout
        :title="$t('onboarding.role.title')"
        :description="$t('onboarding.role.description')"
    >
        <div class="flex flex-col gap-2">
            <button
                v-for="persona in personas"
                :key="persona.value"
                type="button"
                class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left transition-all hover:border-primary hover:bg-accent"
                :class="{
                    'border-primary bg-primary/5': isSelected(persona.value),
                    'border-border': !isSelected(persona.value),
                }"
                @click="form.persona = persona.value"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition-colors"
                    :class="{
                        'bg-primary text-primary-foreground': isSelected(persona.value),
                        'bg-muted text-muted-foreground': !isSelected(persona.value),
                    }"
                >
                    <component :is="icons[persona.icon]" class="h-4 w-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ persona.label }}</p>
                    <p class="text-xs text-muted-foreground">{{ persona.description }}</p>
                </div>
                <IconCheck v-if="isSelected(persona.value)" class="h-4 w-4 shrink-0 text-primary" />
            </button>
        </div>

        <Button
            class="mt-2 w-full"
            :disabled="!form.persona || form.processing"
            @click="submit"
        >
            {{ $t('onboarding.role.submit') }}
        </Button>
    </AuthLayout>
</template>
