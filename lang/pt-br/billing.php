<?php

return [
    'title' => 'Assinatura',
    'description' => 'Gerencie sua assinatura e método de pagamento',

    'trial' => [
        'title' => 'Período de teste ativo',
        'description' => 'Seu período de teste termina em :date. Após isso, sua assinatura será cobrada automaticamente.',
    ],

    'subscription' => [
        'title' => 'Sua Assinatura',
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
