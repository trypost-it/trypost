<?php

return [
    'title' => 'Assinatura',
    'description' => 'Gerencie sua assinatura e método de pagamento',

    'subscribe' => [
        'page_title' => 'Escolha seu plano',
        'title' => 'Escolha o plano ideal pra você',
        'description' => 'Comece com 7 dias grátis. Sem cobrança até o fim do seu teste.',
        'trial_info' => '7 dias grátis, depois cobrança automática',
        'monthly' => 'Mensal',
        'yearly' => 'Anual',
        'per_month' => 'mês',
        'per_year' => 'ano',
        'billed_monthly' => 'cobrança mensal',
        'billed_yearly' => 'cobrança anual',
        'save_months' => '2 meses grátis',
        'popular' => 'Mais popular',
        'start_trial' => 'Iniciar teste de 7 dias',
        'card_required' => 'Cartão de crédito necessário para iniciar o teste.',
        'cancel_anytime' => 'Nenhuma cobrança será realizada antes do fim do período de testes.',
        'prices' => [
            'starter' => ['monthly' => 'R$ 95', 'yearly' => 'R$ 950'],
            'plus' => ['monthly' => 'R$ 145', 'yearly' => 'R$ 1450'],
            'pro' => ['monthly' => 'R$ 245', 'yearly' => 'R$ 2450'],
            'max' => ['monthly' => 'R$ 495', 'yearly' => 'R$ 4950'],
        ],
        'features' => [
            'social_accounts' => ':count contas sociais',
            'workspaces' => ':count workspaces',
            'members' => ':count membros da equipe',
            'ai_images' => ':count imagens IA/mês',
        ],
    ],

    'trial' => [
        'title' => 'Período de teste ativo',
        'description' => 'Seu período de teste termina em :date. Após isso, sua assinatura será cobrada automaticamente.',
    ],

    'subscription' => [
        'title' => 'Sua Assinatura',
        'status' => 'Status',
        'workspaces' => 'Workspaces',
        'quantity' => 'Quantidade da assinatura',
        'expires' => 'Expira em :date',
        'canceled_on' => 'Sua assinatura será cancelada em :date',
        'manage' => 'Gerenciar no Stripe',
    ],

    'invoices' => [
        'title' => 'Faturas',
        'description' => 'Histórico de pagamentos',
        'empty' => 'Nenhuma fatura encontrada',
        'paid' => 'Pago',
    ],

    'processing' => [
        'page_title' => 'Processando...',
        'title' => 'Processando sua assinatura',
        'description' => 'Aguarde enquanto configuramos sua conta. Isso levará apenas um momento.',
        'success_title' => 'Tudo pronto!',
        'success_description' => 'Sua assinatura está ativa. Redirecionando para seus workspaces...',
        'cancelled_title' => 'Pagamento cancelado',
        'cancelled_description' => 'Seu pagamento foi cancelado. Nenhuma cobrança foi realizada.',
        'retry' => 'Tentar novamente',
    ],

    'status' => [
        'active' => 'Ativo',
        'canceled' => 'Cancelado',
        'incomplete' => 'Incompleto',
        'incomplete_expired' => 'Expirado',
        'past_due' => 'Vencido',
        'trialing' => 'Teste',
        'unpaid' => 'Não pago',
    ],
];
