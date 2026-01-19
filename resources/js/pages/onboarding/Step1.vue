<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Building, Building2, Rocket, Sparkles, Store, User } from 'lucide-vue-next';

import { storeStep1 } from '@/actions/App/Http/Controllers/OnboardingController';
import { Button } from '@/components/ui/button';
import OnboardingLayout from '@/layouts/OnboardingLayout.vue';

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

const icons: Record<string, typeof Rocket> = {
    rocket: Rocket,
    sparkles: Sparkles,
    building: Building,
    'building-2': Building2,
    store: Store,
    user: User,
};

const submit = () => {
    form.post(storeStep1.url());
};

const isSelected = (value: string) => form.persona === value;
</script>

<template>
    <Head title="Welcome - Tell us about yourself" />

    <OnboardingLayout
        title="Tell us about yourself"
        description="Help us personalize your experience"
        :step="1"
    >
        <div class="grid grid-cols-2 gap-4">
            <button
                v-for="persona in personas"
                :key="persona.value"
                type="button"
                class="group flex flex-col items-center gap-3 rounded-xl border-2 p-6 text-center transition-all hover:border-primary hover:bg-accent"
                :class="{
                    'border-primary bg-primary/5': isSelected(persona.value),
                    'border-border': !isSelected(persona.value),
                }"
                @click="form.persona = persona.value"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full transition-colors"
                    :class="{
                        'bg-primary text-primary-foreground': isSelected(persona.value),
                        'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary': !isSelected(persona.value),
                    }"
                >
                    <component :is="icons[persona.icon]" class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="font-semibold">{{ persona.label }}</h3>
                    <p class="text-sm text-muted-foreground">{{ persona.description }}</p>
                </div>
            </button>
        </div>

        <Button
            class="w-full"
            size="lg"
            :disabled="!form.persona || form.processing"
            @click="submit"
        >
            Continue
        </Button>
    </OnboardingLayout>
</template>
