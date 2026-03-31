<?php

return [
    'title' => 'Assinatura',
    'description' => 'Gerencie sua assinatura e método de pagamento',

    'subscribe' => [
        'page_title' => 'Comece seu teste gratuito',
        'title' => 'Comece seu teste gratuito',
        'description' => ':days dias grátis para explorar todos os recursos.',
        'start_trial' => 'Começar teste de :days dias',
        'cancel_anytime' => 'Cancele a qualquer momento. Sem perguntas.',
        'switch_workspace' => 'Trocar workspace',
        'features' => [
            'calendar' => 'Calendário visual com arrastar e soltar',
            'scheduling' => 'Agendamento ilimitado de posts',
            'media' => 'Imagens, carrosséis e stories',
            'video' => 'Publicação de vídeos em todas as plataformas',
            'team' => 'Colaboração em equipe e workspaces',
            'hashtags' => 'Grupos de hashtags e etiquetas',
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
