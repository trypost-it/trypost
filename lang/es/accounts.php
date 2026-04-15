<?php

return [
    'title' => 'Conexiones',
    'page_title' => 'Cuentas Sociales',
    'description' => 'Resumen de todas tus cuentas sociales conectadas',
    'add_social' => 'Agregar Red Social',
    'add_social_title' => 'Conectar una Cuenta Social',
    'add_social_description' => 'Conecta una cuenta social a TryPost para empezar a publicar',
    'no_accounts' => 'No hay cuentas conectadas todavía',
    'no_accounts_description' => 'Conecta tus redes sociales para empezar a programar y publicar posts',
    'added' => 'Agregada :date',

    'limit_reached' => 'Has alcanzado el límite de cuentas sociales de tu plan.',

    'not_connected' => 'No conectado',
    'connect' => 'Conectar',
    'connection_lost' => 'Conexión perdida',
    'reconnect_account' => 'Reconectar cuenta',
    'view_profile' => 'Ver perfil',
    'disconnect' => 'Desconectar',

    'descriptions' => [
        'linkedin' => 'Conecta tu perfil personal de LinkedIn',
        'linkedin-page' => 'Conecta una página de empresa de LinkedIn',
        'x' => 'Conecta tu cuenta de X (Twitter)',
        'tiktok' => 'Conecta tu cuenta de TikTok',
        'youtube' => 'Conecta un canal de YouTube',
        'facebook' => 'Conecta una página de Facebook',
        'instagram' => 'Conecta una cuenta profesional de Instagram',
        'instagram-facebook' => 'Conecta Instagram vía página de Facebook',
        'threads' => 'Conecta tu cuenta de Threads',
        'pinterest' => 'Conecta tu cuenta de Pinterest',
        'bluesky' => 'Conecta tu cuenta de Bluesky',
        'mastodon' => 'Conecta tu cuenta de Mastodon',
    ],

    'disconnect_modal' => [
        'title' => 'Desconectar cuenta',
        'description' => '¿Estás seguro de que deseas desconectar esta cuenta? Puedes volver a conectarla en cualquier momento.',
        'confirm' => 'Desconectar',
        'cancel' => 'Cancelar',
    ],

    'bluesky' => [
        'title' => 'Conectar Bluesky',
        'description' => 'Introduce tus credenciales para conectar',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'tuusuario.bsky.social',
        'app_password' => 'Contraseña de app',
        'app_password_placeholder' => 'xxxx-xxxx-xxxx-xxxx',
        'app_password_hint' => 'Usa una <strong>Contraseña de App</strong> por seguridad. Crea una en <a href="https://bsky.app/settings/app-passwords" target="_blank" class="underline">bsky.app/settings</a>.',
        'submit' => 'Conectar Bluesky',
        'submitting' => 'Conectando...',
    ],

    'mastodon' => [
        'title' => 'Conectar Mastodon',
        'description' => 'Introduce tu instancia de Mastodon',
        'instance_url' => 'URL de la instancia',
        'instance_placeholder' => 'https://mastodon.social',
        'instance_hint' => 'Introduce la URL de tu instancia de Mastodon (ej: mastodon.social, techhub.social)',
        'submit' => 'Continuar con Mastodon',
        'submitting' => 'Conectando...',
    ],

    'facebook' => [
        'title' => 'Seleccionar página de Facebook',
        'description' => 'Elige qué página deseas conectar',
        'no_pages' => 'No se encontraron páginas',
        'no_pages_description' => 'No eres administrador de ninguna página de Facebook.',
        'page_label' => 'Página de Facebook',
    ],

    'instagram_facebook' => [
        'title' => 'Seleccionar cuenta de Instagram',
        'description' => 'Elige qué cuenta de Instagram deseas conectar',
        'no_pages' => 'No se encontraron cuentas de Instagram',
        'no_pages_description' => 'No se encontraron páginas de Facebook con cuentas Instagram Business vinculadas.',
    ],

    'linkedin' => [
        'title' => 'Seleccionar página de LinkedIn',
        'description' => 'Elige qué página deseas conectar',
        'no_pages' => 'No se encontraron páginas',
        'no_pages_description' => 'No eres administrador de ninguna página de LinkedIn.',
        'page_label' => 'Página de LinkedIn',
    ],

    'flash' => [
        'disconnected' => '¡Cuenta desconectada correctamente!',
        'connected' => '¡Cuenta conectada correctamente!',
        'session_expired' => 'Sesión expirada. Inténtalo de nuevo.',
        'workspace_not_found' => 'Workspace no encontrado.',
        'activated' => '¡Cuenta activada!',
        'deactivated' => '¡Cuenta desactivada!',
        'already_connected' => 'Esta plataforma ya está conectada.',
        'no_youtube_channels' => 'No se encontraron canales de YouTube. Crea un canal primero.',
    ],
];
