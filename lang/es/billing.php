<?php

return [
    'title' => 'Suscripción',
    'description' => 'Administra tu suscripción y método de pago',

    'subscribe' => [
        'page_title' => 'Elige tu plan',
        'title' => 'Elige el plan ideal para ti',
        'description' => 'Comienza con :days días gratis. Sin cargo hasta que termine tu prueba.',
        'trial_info' => 'Prueba gratuita de :days días, luego se cobra automáticamente',
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
        'per_month' => 'mes',
        'billed_monthly' => 'facturado mensualmente',
        'billed_yearly' => 'facturado anualmente',
        'save_months' => '2 meses gratis',
        'popular' => 'Más popular',
        'start_trial' => 'Comenzar prueba de :days días',
        'card_required' => 'Tarjeta de crédito requerida para iniciar tu prueba.',
        'cancel_anytime' => 'Cancela antes que termine tu prueba — sin cargo.',
        'features' => [
            'social_accounts' => ':count cuentas sociales',
            'workspaces' => ':count workspaces',
            'members' => ':count miembros del equipo',
            'ai_images' => ':count imágenes IA/mes',
            'data_retention' => ':days de retención de datos',
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
