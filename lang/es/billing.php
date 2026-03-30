<?php

return [
    'title' => 'Suscripción',
    'description' => 'Administra tu suscripción y método de pago',

    'trial' => [
        'title' => 'Periodo de prueba activo',
        'description' => 'Tu prueba termina el :date. Después, tu suscripción se cobrará automáticamente.',
    ],

    'subscription' => [
        'title' => 'Tu suscripción',
        'workspaces' => 'Workspaces',
        'quantity' => 'Cantidad de suscripción',
        'expires' => 'Expira :date',
        'canceled_on' => 'Tu suscripción se cancelará el :date',
        'manage' => 'Administrar en Stripe',
    ],

    'invoices' => [
        'title' => 'Facturas',
        'description' => 'Historial de pagos',
        'empty' => 'No se encontraron facturas',
        'paid' => 'Pagado',
    ],

    'status' => [
        'active' => 'Activa',
        'canceled' => 'Cancelada',
        'incomplete' => 'Incompleta',
        'incomplete_expired' => 'Expirada',
        'past_due' => 'Vencida',
        'trialing' => 'Prueba',
        'unpaid' => 'Sin pagar',
    ],
];
