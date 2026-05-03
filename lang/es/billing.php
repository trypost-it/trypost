<?php

return [
    'title' => 'Facturación',

    'upgrade_dialog' => [
        'title' => 'Actualiza tu plan',
        'description' => 'Elige un plan que se adapte a tus necesidades.',
        'current_plan' => 'Plan actual',
        'current_short' => 'Actual',
        'current_badge' => 'Actual',
        'subscribe' => 'Suscribirse',
        'switch' => 'Cambiar a este plan',
        'switch_short' => 'Cambiar',
        'switch_to_yearly' => 'Cambiar a anual',
        'switch_to_monthly' => 'Cambiar a mensual',
        'unavailable' => 'No disponible',
        'reasons' => [
            'workspace_limit' => 'Has alcanzado el límite de workspaces de tu plan. Actualiza para crear más.',
            'social_account_limit' => 'Has alcanzado el límite de cuentas sociales de tu plan. Actualiza para conectar más.',
            'member_limit' => 'Has alcanzado el límite de miembros de tu plan. Actualiza para invitar a más personas.',
        ],
    ],

    'subscribe' => [
        'page_title' => 'Elige tu plan',
        'title' => 'Elige el plan ideal para ti',
        'description' => 'Comienza con 7 días gratis. Sin cargo hasta que termine tu prueba.',
        'trial_info' => 'Prueba gratuita de 7 días, luego se cobra automáticamente',
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
        'per_month' => 'mes',
        'per_year' => 'año',
        'billed_monthly' => 'facturado mensualmente',
        'billed_yearly' => 'facturado anualmente',
        'save_months' => '2 meses gratis',
        'popular' => 'Más popular',
        'start_trial' => 'Comenzar prueba de 7 días',
        'card_required' => 'Tarjeta de crédito requerida para iniciar tu prueba.',
        'cancel_anytime' => 'No se realizará ningún cobro antes de que termine el período de prueba.',
        'prices' => [
            'starter' => ['monthly' => '$19', 'yearly' => '$190'],
            'plus' => ['monthly' => '$29', 'yearly' => '$290'],
            'pro' => ['monthly' => '$49', 'yearly' => '$490'],
            'max' => ['monthly' => '$99', 'yearly' => '$990'],
        ],
        'features' => [
            'social_accounts' => ':count cuentas sociales',
            'workspaces' => ':count workspaces',
            'members' => ':count miembros del equipo',
            'ai_images' => ':count imágenes IA/mes',
        ],
    ],

    'plan' => [
        'title' => 'Plan',
        'description' => 'Gestiona tu plan de suscripción.',
        'change' => 'Cambiar plan',
        'label' => 'Plan',
        'price' => 'Precio',
        'month' => 'mes',
        'trial' => 'Prueba',
        'active' => 'Activo',
        'past_due' => 'Vencido',
        'cancelling' => 'Cancelando',
        'trial_ends' => 'La prueba termina en',
    ],

    'subscription' => [
        'title' => 'Suscripción',
        'description' => 'Gestiona tu método de pago, datos de facturación y suscripción.',
        'payment_method' => 'Método de pago',
        'manage_label' => 'Suscripción',
        'manage_stripe' => 'Gestionar en Stripe',
    ],

    'invoices' => [
        'title' => 'Facturas',
        'description' => 'Descarga tus facturas anteriores.',
        'empty' => 'No se encontraron facturas',
        'paid' => 'Pagado',
    ],

    'flash' => [
        'plan_changed' => 'Ahora estás en el plan :plan.',
        'cannot_manage' => 'Solo el propietario de la cuenta puede gestionar la facturación.',
        'cannot_downgrade' => [
            'workspaces' => 'No puedes cambiar a :plan: tienes :count workspaces pero el plan solo permite :limit.',
            'social_accounts' => 'No puedes cambiar a :plan: tienes :count cuentas sociales pero el plan solo permite :limit.',
            'members' => 'No puedes cambiar a :plan: tienes :count miembros (incluyendo invitaciones) pero el plan solo permite :limit.',
        ],
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
];
