<?php

return [
    'title' => 'Conexões',
    'page_title' => 'Contas Conectadas',
    'description' => 'Conecte suas redes sociais para agendar e publicar posts',

    'not_connected' => 'Não conectado',
    'connect' => 'Conectar',
    'connection_lost' => 'Conexão perdida',
    'reconnect_account' => 'Reconectar conta',
    'view_profile' => 'Ver perfil',
    'disconnect' => 'Desconectar',

    'tooltips' => [
        'instagram_facebook' => 'Conecta via sua Página do Facebook. Recomendado para contas business vinculadas a uma Página do Facebook.',
        'instagram_direct' => 'Conecta direto pelo Instagram. Para contas profissionais/criadores sem Página do Facebook.',
        'bluesky' => 'Não suportamos autenticação de dois fatores. Se estiver ativada no Bluesky, será necessário desativá-la.',
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
