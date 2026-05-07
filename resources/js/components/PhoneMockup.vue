<script setup lang="ts">
interface Props {
    variant?: 'default' | 'minimal';
}

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <div class="relative mx-auto origin-top select-none">
        <!-- Phone Frame - iPhone 14 Pro style -->
        <div
            class="relative rounded-[44px] bg-[#1a1a1a] p-[10px] shadow-2xl ring-1 ring-white/10"
        >
            <!-- Inner frame -->
            <div class="relative overflow-hidden rounded-[34px] bg-[#0a0a0a]">
                <!-- Screen Content - iPhone 14 Pro proportions (393x852 scaled down) -->
                <div
                    class="relative h-[780px] w-[360px] overflow-hidden bg-white dark:bg-black"
                >
                    <!-- iOS Status Bar - dark text on light mode, light text on dark mode -->
                    <div
                        class="absolute top-0 right-0 left-0 z-40 flex h-[44px] items-center justify-between px-5 text-black dark:text-white"
                    >
                        <!-- Left: Time -->
                        <span
                            class="w-[54px] text-[15px] font-semibold tracking-tight"
                            >9:41</span
                        >

                        <!-- Center: Dynamic Island -->
                        <div
                            class="flex h-[25px] w-[80px] items-center justify-center rounded-full bg-black"
                        >
                            <div
                                class="mr-5 h-[6px] w-[6px] rounded-full bg-[#1a1a1a]"
                            ></div>
                        </div>

                        <!-- Right: Icons -->
                        <div
                            class="flex w-[54px] items-center justify-end gap-[5px]"
                        >
                            <!-- Cellular Signal -->
                            <svg
                                class="h-[12px] w-[17px]"
                                viewBox="0 0 17 12"
                                fill="currentColor"
                            >
                                <rect
                                    x="0"
                                    y="8"
                                    width="3"
                                    height="4"
                                    rx="0.5"
                                />
                                <rect
                                    x="4.5"
                                    y="5.5"
                                    width="3"
                                    height="6.5"
                                    rx="0.5"
                                />
                                <rect
                                    x="9"
                                    y="3"
                                    width="3"
                                    height="9"
                                    rx="0.5"
                                />
                                <rect
                                    x="13.5"
                                    y="0"
                                    width="3"
                                    height="12"
                                    rx="0.5"
                                />
                            </svg>
                            <!-- WiFi -->
                            <svg
                                class="h-[11px] w-[15px]"
                                viewBox="0 0 15 11"
                                fill="currentColor"
                            >
                                <path
                                    d="M7.5 8.5a1.25 1.25 0 110 2.5 1.25 1.25 0 010-2.5z"
                                />
                                <path
                                    d="M4.5 7.2c.8-.8 1.9-1.2 3-1.2s2.2.4 3 1.2l-.9.9c-.6-.5-1.3-.8-2.1-.8s-1.5.3-2.1.8l-.9-.9z"
                                />
                                <path
                                    d="M2.1 4.8C3.5 3.4 5.4 2.5 7.5 2.5s4 .9 5.4 2.3l-.9.9C10.8 4.5 9.2 3.8 7.5 3.8S4.2 4.5 3 5.7l-.9-.9z"
                                />
                                <path
                                    d="M0 2.7C1.9 1 4.6 0 7.5 0s5.6 1 7.5 2.7l-.9.9C12.4 2 10.1 1.2 7.5 1.2S2.6 2 .9 3.6L0 2.7z"
                                />
                            </svg>
                            <!-- Battery -->
                            <svg class="h-[11px] w-[22px]" viewBox="0 0 25 11">
                                <rect
                                    x="0.5"
                                    y="0.5"
                                    width="21"
                                    height="10"
                                    rx="2.5"
                                    stroke="currentColor"
                                    fill="none"
                                    stroke-opacity="0.4"
                                />
                                <rect
                                    x="2"
                                    y="2"
                                    width="17"
                                    height="7"
                                    rx="1.5"
                                    fill="currentColor"
                                />
                                <path
                                    d="M23 3.5v4c1 0 1.5-.9 1.5-2s-.5-2-1.5-2z"
                                    fill="currentColor"
                                    fill-opacity="0.4"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- App Content (below status bar) -->
                    <div
                        class="absolute top-[44px] right-0 bottom-0 left-0 overflow-hidden"
                    >
                        <slot />
                    </div>
                </div>
            </div>
        </div>

        <!-- Side buttons -->
        <div
            class="absolute top-[100px] -left-[2px] h-[28px] w-[3px] rounded-l-sm bg-[#2a2a2a]"
        ></div>
        <div
            class="absolute top-[145px] -left-[2px] h-[50px] w-[3px] rounded-l-sm bg-[#2a2a2a]"
        ></div>
        <div
            class="absolute top-[205px] -left-[2px] h-[50px] w-[3px] rounded-l-sm bg-[#2a2a2a]"
        ></div>
        <div
            class="absolute top-[160px] -right-[2px] h-[70px] w-[3px] rounded-r-sm bg-[#2a2a2a]"
        ></div>
    </div>
</template>
