<script setup lang="ts">
import { IconCheck, IconChevronDown, IconSearch } from '@tabler/icons-vue';
import { FocusScope } from 'reka-ui';
import { ref, watchEffect } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';

interface Language {
    id: string;
    name: string;
    code: string;
}

interface Props {
    modelValue?: string | null;
    languages: Language[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const selectedLanguage = ref<Language | undefined>();

watchEffect(() => {
    selectedLanguage.value = props.languages.find(
        (lang) => lang.id === props.modelValue,
    );
});
</script>

<template>
    <FocusScope as-child>
        <Combobox
            :model-value="selectedLanguage"
            @update:model-value="
                (v: Language) => {
                    selectedLanguage = v;
                    emit('update:modelValue', v?.id || null);
                }
            "
        >
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button
                        variant="outline"
                        class="w-full justify-between"
                    >
                        {{
                            selectedLanguage
                                ? selectedLanguage.name
                                : 'Select language'
                        }}
                        <IconChevronDown
                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                        />
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>
            <ComboboxList class="w-full">
                <div class="relative">
                    <ComboboxInput placeholder="Search language..." />
                    <span
                        class="absolute inset-y-0 start-0 flex items-center justify-center px-3"
                    >
                        <IconSearch class="size-4 text-muted-foreground" />
                    </span>
                </div>
                <ComboboxEmpty>No language found</ComboboxEmpty>
                <ComboboxGroup>
                    <ComboboxItem
                        v-for="lang in languages"
                        :key="lang.id"
                        :value="lang"
                    >
                        <span class="min-w-0 flex-1 truncate">{{ lang.name }}</span>
                        <ComboboxItemIndicator>
                            <IconCheck class="ml-auto h-4 w-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxList>
        </Combobox>
    </FocusScope>
</template>
