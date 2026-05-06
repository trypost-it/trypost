<?php

return [
    'title' => 'Faturamento',

    'upgrade_dialog' => [
        'title' => 'Faça upgrade do seu plano',
        'description' => 'Escolha um plano que se encaixe nas suas necessidades.',
        'current_plan' => 'Plano atual',
        'current_short' => 'Atual',
        'current_badge' => 'Atual',
        'subscribe' => 'Assinar',
        'switch' => 'Mudar para este plano',
        'switch_short' => 'Mudar',
        'switch_to_yearly' => 'Mudar para anual',
        'switch_to_monthly' => 'Mudar para mensal',
        'unavailable' => 'Indisponível',
        'reasons' => [
            'workspace_limit' => 'Você atingiu o limite de workspaces do seu plano. Faça upgrade pra criar mais.',
            'social_account_limit' => 'Você atingiu o limite de contas sociais do seu plano. Faça upgrade pra conectar mais.',
            'member_limit' => 'Você atingiu o limite de membros do seu plano. Faça upgrade pra convidar mais pessoas.',
        ],
    ],

    'subscribe' => [
        'page_title' => 'Escolha seu plano',
        'eyebrow' => 'Preços',
        'title' => 'Escolha o plano ideal pra você',
        'description' => 'Comece com 7 dias grátis. Sem cobrança até o fim do seu teste.',
        'trial_info' => '7 dias grátis, depois cobrança automática',
        'monthly' => 'Mensal',
        'yearly' => 'Anual',
        'per_month' => 'mensal',
        'per_year' => 'anual',
        'billed_monthly' => 'Cobrança mensal',
        'billed_yearly' => 'Cobrança anual',
        'features_included' => 'O que está incluído:',
        'everything_in' => 'Tudo do :plan, mais:',
        'save_months' => '2 meses grátis',
        'popular' => 'Mais popular',
        'start_trial' => 'Iniciar teste de 7 dias',
        'prices' => [
            'starter' => ['monthly' => 'R$ 95', 'yearly_per_month' => 'R$ 79', 'yearly' => 'R$ 950'],
            'plus' => ['monthly' => 'R$ 145', 'yearly_per_month' => 'R$ 121', 'yearly' => 'R$ 1450'],
            'pro' => ['monthly' => 'R$ 245', 'yearly_per_month' => 'R$ 204', 'yearly' => 'R$ 2450'],
            'max' => ['monthly' => 'R$ 495', 'yearly_per_month' => 'R$ 413', 'yearly' => 'R$ 4950'],
        ],
        'features' => [
            'social_accounts' => ':count contas sociais',
            'workspaces' => ':count workspaces',
            'members' => ':count membros da equipe',
            'credits' => ':count créditos IA/mês',
        ],
        'credit_tooltips' => [
            'starter' => 'Em média 150 posts de tamanho médio + 5 imagens de IA por mês.',
            'plus' => 'Em média 300 posts de tamanho médio + 10 imagens de IA por mês.',
            'pro' => 'Em média 700 posts de tamanho médio + 30 imagens de IA por mês.',
            'max' => 'Em média 2.000 posts de tamanho médio + 100 imagens de IA por mês.',
        ],
    ],

    'plan' => [
        'title' => 'Plano',
        'description' => 'Gerencie seu plano de assinatura.',
        'change' => 'Mudar plano',
        'label' => 'Plano',
        'price' => 'Preço',
        'month' => 'mês',
        'trial' => 'Trial',
        'active' => 'Ativo',
        'past_due' => 'Vencido',
        'cancelling' => 'Cancelando',
        'trial_ends' => 'Teste termina em',
    ],

    'subscription' => [
        'title' => 'Assinatura',
        'description' => 'Gerencie seu método de pagamento, dados de cobrança e assinatura.',
        'payment_method' => 'Método de pagamento',
        'no_payment_method' => 'Nenhum método de pagamento cadastrado.',
        'expires_on' => 'Expira em :month/:year',
        'manage_label' => 'Assinatura',
        'manage_stripe' => 'Gerenciar no Stripe',
    ],

    'invoices' => [
        'title' => 'Faturas',
        'description' => 'Baixe suas faturas anteriores.',
        'empty' => 'Nenhuma fatura encontrada',
        'paid' => 'Pago',
    ],

    'flash' => [
        'plan_changed' => 'Você está agora no plano :plan.',
        'cannot_manage' => 'Apenas o owner da conta pode gerenciar a cobrança.',
        'cannot_downgrade' => [
            'workspaces' => 'Não é possível mudar para :plan: você tem :count workspaces mas o plano só permite :limit.',
            'social_accounts' => 'Não é possível mudar para :plan: você tem :count contas sociais mas o plano só permite :limit.',
            'members' => 'Não é possível mudar para :plan: você tem :count membros (incluindo convites) mas o plano só permite :limit.',
        ],
        'credits_exhausted' => 'Sem créditos de IA — você usou seus :limit créditos mensais. Faça upgrade do plano ou aguarde até o próximo mês.',
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
];
