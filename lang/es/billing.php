<?php

return [
    'title' => 'Suscripción',
    'description' => 'Administra tu suscripción y método de pago',

    'subscribe' => [
        'page_title' => 'Comienza tu prueba gratuita',
        'title' => 'Comienza tu prueba gratuita',
        'description' => ':days días gratis para explorar todas las funciones.',
        'start_trial' => 'Comenzar prueba de :days días',
        'cancel_anytime' => 'Cancela en cualquier momento. Sin preguntas.',
        'switch_workspace' => 'Cambiar workspace',
        'features' => [
            'calendar' => 'Calendario visual con arrastrar y soltar',
            'scheduling' => 'Programación ilimitada de posts',
            'media' => 'Imágenes, carruseles e historias',
            'video' => 'Publicación de videos en todas las plataformas',
            'team' => 'Colaboración en equipo y workspaces',
            'hashtags' => 'Grupos de hashtags y etiquetas',
        ],
    ],

    'trial' => [
        'title' => 'Periodo de prueba activo',
        'description' => 'Tu prueba termina el :date. Después, tu suscripción se cobrará automáticamente.',
    ],

    'subscription' => [
        'title' => 'Tu suscripción',
        'status' => 'Estado',
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

    'processing' => [
        'page_title' => 'Procesando...',
        'title' => 'Procesando tu suscripción',
        'description' => 'Espera mientras configuramos tu cuenta. Solo tomará un momento.',
        'success_title' => '¡Todo listo!',
        'success_description' => 'Tu suscripción está activa. Redirigiendo a tus workspaces...',
        'cancelled_title' => 'Pago cancelado',
        'cancelled_description' => 'Tu pago fue cancelado. No se realizaron cargos.',
        'retry' => 'Intentar de nuevo',
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
