<?php

return [
    'title' => 'Conexões',
    'page_title' => 'Contas Sociais',
    'description' => 'Visão geral de todas as suas contas sociais conectadas',
    'add_social' => 'Adicionar Rede Social',
    'add_social_title' => 'Conectar uma Conta Social',
    'add_social_description' => 'Conecte uma conta social ao TryPost para começar a publicar',
    'no_accounts' => 'Nenhuma conta conectada ainda',
    'no_accounts_description' => 'Conecte suas redes sociais para começar a agendar e publicar posts',
    'added' => 'Adicionada :date',

    'limit_reached' => 'Você atingiu o limite de contas sociais do seu plano.',

    'not_connected' => 'Não conectado',
    'connect' => 'Conectar',
    'connection_lost' => 'Conexão perdida',
    'reconnect_account' => 'Reconectar conta',
    'view_profile' => 'Ver perfil',
    'disconnect' => 'Desconectar',

    'descriptions' => [
        'linkedin' => 'Conecte seu perfil pessoal do LinkedIn',
        'linkedin-page' => 'Conecte uma página de empresa do LinkedIn',
        'x' => 'Conecte sua conta do X (Twitter)',
        'tiktok' => 'Conecte sua conta do TikTok',
        'youtube' => 'Conecte um canal do YouTube',
        'facebook' => 'Conecte uma página do Facebook',
        'instagram' => 'Conecte uma conta profissional do Instagram',
        'instagram-facebook' => 'Conecte Instagram via página do Facebook',
        'threads' => 'Conecte sua conta do Threads',
        'pinterest' => 'Conecte sua conta do Pinterest',
        'bluesky' => 'Conecte sua conta do Bluesky',
        'mastodon' => 'Conecte sua conta do Mastodon',
    ],

    'disconnect_modal' => [
        'title' => 'Desconectar Conta',
        'description' => 'Tem certeza que deseja desconectar esta conta? Você pode reconectá-la a qualquer momento.',
        'confirm' => 'Desconectar',
        'cancel' => 'Cancelar',
    ],

    'bluesky' => [
        'title' => 'Conectar Bluesky',
        'description' => 'Digite suas credenciais para conectar',
        'email' => 'E-mail',
        'email_placeholder' => 'seuhandle.bsky.social',
        'app_password' => 'Senha do App',
        'app_password_placeholder' => 'xxxx-xxxx-xxxx-xxxx',
        'app_password_hint' => 'Use uma <strong>Senha do App</strong> por segurança. Crie uma em <a href="https://bsky.app/settings/app-passwords" target="_blank" class="underline">bsky.app/settings</a>.',
        'submit' => 'Conectar Bluesky',
        'submitting' => 'Conectando...',
    ],

    'mastodon' => [
        'title' => 'Conectar Mastodon',
        'description' => 'Digite a instância do seu Mastodon',
        'instance_url' => 'URL da Instância',
        'instance_placeholder' => 'https://mastodon.social',
        'instance_hint' => 'Digite a URL da sua instância Mastodon (ex: mastodon.social, techhub.social)',
        'submit' => 'Continuar com Mastodon',
        'submitting' => 'Conectando...',
    ],

    'facebook' => [
        'title' => 'Selecionar Página do Facebook',
        'description' => 'Escolha qual página você deseja conectar',
        'no_pages' => 'Nenhuma página encontrada',
        'no_pages_description' => 'Você não é administrador de nenhuma página do Facebook.',
        'page_label' => 'Página do Facebook',
    ],

    'instagram_facebook' => [
        'title' => 'Selecionar Conta do Instagram',
        'description' => 'Escolha qual conta do Instagram você deseja conectar',
        'no_pages' => 'Nenhuma conta do Instagram encontrada',
        'no_pages_description' => 'Nenhuma Página do Facebook com conta Instagram Business vinculada foi encontrada.',
    ],

    'linkedin' => [
        'title' => 'Selecionar Página do LinkedIn',
        'description' => 'Escolha qual página você deseja conectar',
        'no_pages' => 'Nenhuma página encontrada',
        'no_pages_description' => 'Você não é administrador de nenhuma página do LinkedIn.',
        'page_label' => 'Página do LinkedIn',
    ],

    'flash' => [
        'disconnected' => 'Conta desconectada com sucesso!',
        'connected' => 'Conta conectada com sucesso!',
        'session_expired' => 'Sessão expirada. Por favor, tente novamente.',
        'workspace_not_found' => 'Workspace não encontrado.',
        'activated' => 'Conta ativada!',
        'deactivated' => 'Conta desativada!',
        'already_connected' => 'Esta plataforma já está conectada.',
        'no_youtube_channels' => 'Nenhum canal do YouTube encontrado. Por favor, crie um canal primeiro.',
    ],
];
