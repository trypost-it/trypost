<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { CreditCard, FileText, Building2, Check, ExternalLink } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { type BreadcrumbItemType } from '@/types';

interface Subscription {
    stripe_status: string;
    quantity: number;
    ends_at: string | null;
}

interface PaymentMethod {
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
}

interface Invoice {
    id: string;
    date: string;
    total: string;
    status: string;
    invoice_pdf: string;
}

interface Props {
    hasSubscription: boolean;
    subscription: Subscription | null;
    workspacesCount: number;
    invoices: Invoice[];
    defaultPaymentMethod: PaymentMethod | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Assinatura',
        href: '/billing',
    },
];

const form = useForm({});

function subscribe() {
    form.post('/billing/checkout');
}

function openPortal() {
    window.location.href = '/billing/portal';
}

function getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        active: 'Ativa',
        canceled: 'Cancelada',
        incomplete: 'Incompleta',
        incomplete_expired: 'Expirada',
        past_due: 'Atrasada',
        trialing: 'Teste',
        unpaid: 'Não paga',
    };
    return labels[status] || status;
}

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'active' || status === 'trialing') return 'default';
    if (status === 'canceled' || status === 'past_due' || status === 'unpaid') return 'destructive';
    return 'secondary';
}

const pricePerWorkspace = 20;
</script>

<template>
    <Head title="Assinatura" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Assinatura</h1>
                <p class="text-muted-foreground">
                    Gerencie sua assinatura e método de pagamento
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card v-if="!hasSubscription">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Plano Pro
                        </CardTitle>
                        <CardDescription>
                            Crie e gerencie múltiplos workspaces
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-6">
                            <span class="text-4xl font-bold">${{ pricePerWorkspace }}</span>
                            <span class="text-muted-foreground">/workspace/mês</span>
                        </div>
                        <ul class="space-y-2">
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                <span>Workspaces ilimitados</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                <span>LinkedIn, X e TikTok</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                <span>Calendário de agendamento</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="h-4 w-4 text-green-500" />
                                <span>Convide colaboradores</span>
                            </li>
                        </ul>
                    </CardContent>
                    <CardFooter>
                        <Button @click="subscribe" :disabled="form.processing" class="w-full">
                            <CreditCard class="mr-2 h-4 w-4" />
                            Assinar Agora
                        </Button>
                    </CardFooter>
                </Card>

                <Card v-else>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <Building2 class="h-5 w-5" />
                                Sua Assinatura
                            </CardTitle>
                            <Badge :variant="getStatusVariant(subscription?.stripe_status || '')">
                                {{ getStatusLabel(subscription?.stripe_status || '') }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-muted rounded-lg">
                            <div>
                                <p class="text-sm text-muted-foreground">Workspaces</p>
                                <p class="text-2xl font-bold">{{ workspacesCount }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-muted-foreground">Total mensal</p>
                                <p class="text-2xl font-bold">${{ workspacesCount * pricePerWorkspace }}</p>
                            </div>
                        </div>

                        <div v-if="defaultPaymentMethod" class="flex items-center gap-3 p-3 border rounded-lg">
                            <CreditCard class="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p class="font-medium capitalize">{{ defaultPaymentMethod.brand }} **** {{ defaultPaymentMethod.last4 }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Expira {{ defaultPaymentMethod.exp_month }}/{{ defaultPaymentMethod.exp_year }}
                                </p>
                            </div>
                        </div>

                        <div v-if="subscription?.ends_at" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                Sua assinatura será cancelada em {{ subscription.ends_at }}
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button @click="openPortal" variant="outline" class="w-full">
                            <ExternalLink class="mr-2 h-4 w-4" />
                            Gerenciar no Stripe
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Faturas
                        </CardTitle>
                        <CardDescription>
                            Histórico de pagamentos
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invoices.length === 0" class="text-center py-6 text-muted-foreground">
                            Nenhuma fatura encontrada
                        </div>
                        <div v-else class="space-y-3">
                            <a
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                :href="invoice.invoice_pdf"
                                target="_blank"
                                class="flex items-center justify-between p-3 border rounded-lg hover:bg-muted transition-colors"
                            >
                                <div>
                                    <p class="font-medium">{{ invoice.date }}</p>
                                    <p class="text-sm text-muted-foreground">{{ invoice.total }}</p>
                                </div>
                                <Badge variant="outline">
                                    {{ invoice.status === 'paid' ? 'Paga' : invoice.status }}
                                </Badge>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Alert v-if="!hasSubscription && workspacesCount > 0">
                <Building2 class="h-4 w-4" />
                <AlertTitle>Workspace Grátis</AlertTitle>
                <AlertDescription>
                    Você está usando seu workspace gratuito. Assine para criar mais workspaces e desbloquear todos os recursos.
                </AlertDescription>
            </Alert>
        </div>
    </AppLayout>
</template>
