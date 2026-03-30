<?php

return [
    'title' => 'Conexiones',
    'page_title' => 'Cuentas conectadas',
    'description' => 'Conecta tus redes sociales para programar y publicar posts',

    'not_connected' => 'No conectado',
    'connect' => 'Conectar',
    'connection_lost' => 'Conexión perdida',
    'reconnect_account' => 'Reconectar cuenta',
    'view_profile' => 'Ver perfil',
    'disconnect' => 'Desconectar',

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
        'already_connected' => 'Esta plataforma ya está conectada.',
        'no_youtube_channels' => 'No se encontraron canales de YouTube. Crea un canal primero.',
    ],
];
