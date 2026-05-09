<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const isOpen = ref(false);
const message = ref('');
const chatHistory = ref<{ role: 'user' | 'brain'; content: string; actions?: any[] }[]>([]);
const isProcessing = ref(false);

const toggleBrain = () => {
    isOpen.value = !isOpen.value;
};

const sendRequest = async () => {
    if (!message.value.trim() || isProcessing.value) return;

    const userMessage = message.value;
    chatHistory.value.push({ role: 'user', content: userMessage });
    message.value = '';
    isProcessing.value = true;

    try {
        const response = await axios.post(route('app.brain.chat'), {
            message: userMessage,
            context: {
                url: window.location.href,
                page: usePage().component,
            }
        });

        chatHistory.value.push({
            role: 'brain',
            content: response.data.message,
            actions: response.data.suggested_actions
        });
    } catch (error) {
        chatHistory.value.push({
            role: 'brain',
            content: "Sorry, I'm having trouble connecting to my neurons right now. Please try again later."
        });
    } finally {
        isProcessing.value = false;
    }
};

const executeAction = (action: any) => {
    console.log('Executing action:', action);
    
    if (action.action === 'redirect' && action.url) {
        router.visit(action.url);
        isOpen.value = false;
    }
    
    if (action.action === 'message') {
        message.value = action.label;
        sendRequest();
    }
};

// Auto-suggest on first open
watch(isOpen, (newVal) => {
    if (newVal && chatHistory.value.length === 0) {
        chatHistory.value.push({
            role: 'brain',
            content: "Hello! I'm your PostPro Brain. I've been analyzing your workspace and I'm ready to help you grow. What's on your mind today?"
        });
    }
});

</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <!-- Floating Brain Icon -->
        <button 
            @click="toggleBrain"
            class="relative group w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 hover:scale-110 active:scale-95"
        >
            <div class="absolute inset-0 rounded-full bg-indigo-400 blur-md opacity-40 group-hover:opacity-70 transition-opacity"></div>
            <svg class="w-8 h-8 text-white relative z-10 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.348-.347z" />
            </svg>
        </button>

        <!-- Command Center Panel -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-10 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-10 opacity-0 scale-95"
        >
            <div 
                v-if="isOpen"
                class="absolute bottom-20 right-0 w-96 h-[500px] bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border border-white/20 dark:border-gray-700/30 rounded-2xl shadow-2xl overflow-hidden flex flex-col"
            >
                <!-- Header -->
                <div class="p-4 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border-b border-white/20 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-ping"></div>
                        <span class="font-bold text-gray-800 dark:text-white">PostPro Brain</span>
                    </div>
                    <button @click="isOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>

                <!-- Chat Area -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin scrollbar-thumb-indigo-500/20">
                    <div v-for="(chat, index) in chatHistory" :key="index" :class="chat.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div 
                            :class="[
                                'max-w-[85%] p-3 rounded-2xl text-sm leading-relaxed',
                                chat.role === 'user' 
                                    ? 'bg-indigo-600 text-white rounded-tr-none shadow-md' 
                                    : 'bg-white/50 dark:bg-gray-800/50 border border-white/20 dark:border-gray-700/50 text-gray-700 dark:text-gray-200 rounded-tl-none'
                            ]"
                        >
                            <p>{{ chat.content }}</p>
                            
                            <!-- Suggested Actions -->
                            <div v-if="chat.actions?.length" class="mt-3 flex flex-wrap gap-2">
                                <button 
                                    v-for="action in chat.actions" 
                                    :key="action.action"
                                    @click="executeAction(action)"
                                    class="px-3 py-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 rounded-lg text-xs font-medium transition-colors"
                                >
                                    {{ action.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="isProcessing" class="flex justify-start">
                        <div class="bg-white/50 dark:bg-gray-800/50 p-3 rounded-2xl rounded-tl-none">
                            <div class="flex gap-1">
                                <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-4 border-t border-white/20 bg-white/30 dark:bg-gray-900/30">
                    <div class="relative flex items-center">
                        <input 
                            v-model="message"
                            @keyup.enter="sendRequest"
                            type="text" 
                            placeholder="Ask the Brain..."
                            class="w-full pl-4 pr-12 py-3 bg-white/50 dark:bg-gray-800/50 border border-white/20 dark:border-gray-700/50 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm transition-all dark:text-white"
                        >
                        <button 
                            @click="sendRequest"
                            :disabled="!message.trim() || isProcessing"
                            class="absolute right-2 p-2 text-indigo-500 hover:text-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.backdrop-blur-xl {
    backdrop-filter: blur(24px);
}
</style>
