<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { login, register } from '@/routes';
import { ref } from 'vue';

const props = defineProps<{
    invite: {
        id: string;
        token: string;
        email: string;
        role: {
            value: string;
            label: string;
        };
        workspace: {
            id: string;
            name: string;
        };
        inviter: {
            id: string;
            name: string;
            email: string;
        };
    };
    isAuthenticated: boolean;
    userEmail?: string;
}>();

const accepting = ref(false);

const acceptInvite = () => {
    accepting.value = true;
    router.post(`/invites/${props.invite.token}/accept`, {}, {
        onFinish: () => {
            accepting.value = false;
        },
    });
};
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Accept Invite" />

        <div class="w-full max-w-md">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link href="/" class="flex flex-col items-center gap-2 font-medium">
                        <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-8 w-auto" />
                        <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-8 w-auto" />
                    </Link>
                </div>

                <Card>
                    <CardHeader class="text-center">
                        <CardTitle class="text-xl">You've been invited!</CardTitle>
                        <CardDescription>
                            <span class="font-medium text-foreground">{{ invite.inviter.name }}</span>
                            has invited you to join the
                            <span class="font-medium text-foreground">{{ invite.workspace.name }}</span>
                            workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="rounded-lg bg-muted p-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Workspace</span>
                                <span class="font-medium">{{ invite.workspace.name }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Your role</span>
                                <span class="font-medium">{{ invite.role.label }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Invited by</span>
                                <span class="font-medium">{{ invite.inviter.name }}</span>
                            </div>
                        </div>

                        <!-- Authenticated user -->
                        <template v-if="isAuthenticated">
                            <p class="text-sm text-muted-foreground text-center">
                                You're logged in as <span class="font-medium text-foreground">{{ userEmail }}</span>
                            </p>
                            <Button
                                @click="acceptInvite"
                                class="w-full"
                                size="lg"
                                :disabled="accepting"
                            >
                                <Spinner v-if="accepting" class="mr-2" />
                                Accept Invite
                            </Button>
                        </template>

                        <!-- Not authenticated -->
                        <template v-else>
                            <div class="space-y-3">
                                <p class="text-sm text-muted-foreground text-center">
                                    To accept this invite, please log in or create an account.
                                </p>
                                <div class="grid gap-2">
                                    <Button
                                        as-child
                                        class="w-full"
                                        size="lg"
                                    >
                                        <Link :href="login({ query: { email: invite.email } })">
                                            Log in to accept
                                        </Link>
                                    </Button>
                                    <Button
                                        as-child
                                        variant="outline"
                                        class="w-full"
                                        size="lg"
                                    >
                                        <Link :href="register({ query: { email: invite.email } })">
                                            Create an account
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <p class="text-center text-xs text-muted-foreground">
                    This invite was sent to <span class="font-medium">{{ invite.email }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
