<?php

return [
    'title' => 'Posts',
    'all_posts' => 'Todos os Posts',
    'new_post' => 'Novo Post',
    'create_post' => 'Criar Post',
    'no_posts' => 'Nenhum post encontrado',
    'no_posts_status' => 'Nenhum post :status ainda.',
    'start_creating' => 'Comece criando seu primeiro post.',
    'manage_posts' => 'Gerencie todos os seus posts',
    'delete_confirm' => 'Tem certeza que deseja excluir este post?',
    'by' => 'por',

    'actions' => [
        'view' => 'Ver post',
        'delete' => 'Excluir post',
    ],

    'status' => [
        'draft' => 'Rascunho',
        'scheduled' => 'Agendado',
        'publishing' => 'Publicando',
        'published' => 'Publicado',
        'partially_published' => 'Parcialmente Publicado',
        'failed' => 'Falhou',
    ],

    'descriptions' => [
        'draft' => 'Posts aguardando agendamento',
        'scheduled' => 'Posts agendados para publicação',
        'published' => 'Posts já publicados',
    ],

    'edit' => [
        'title' => 'Editar Post',
        'view_title' => 'Visualizar Post',
        'manage_platforms' => 'Gerenciar plataformas',
        'sync' => 'Sincronizar',
        'schedule' => 'Agendar',
        'publish' => 'Publicar',
        'saving' => 'Salvando...',
        'saved' => 'Salvo',
        'scheduled_at' => 'Agendado:',
        'published_at' => 'Publicado:',
        'media' => 'Mídia',
        'caption' => 'Legenda',
        'no_caption' => 'Sem legenda',
        'no_content' => 'Sem conteúdo',

        'empty_state' => [
            'title' => 'Nenhuma plataforma selecionada',
            'description' => 'Selecione pelo menos uma plataforma para criar seu post',
        ],

        'delete_modal' => [
            'title' => 'Excluir Post',
            'description' => 'Tem certeza que deseja excluir este post? Esta ação não pode ser desfeita.',
            'action' => 'Excluir',
            'cancel' => 'Cancelar',
        ],

        'sync_enable' => [
            'title' => 'Descartar texto e sincronizar com :platform?',
            'description' => 'Se você ativar a sincronização, perderá todas as edições feitas especificamente para outras plataformas.',
            'confirm_question' => 'Tem certeza que deseja sincronizar com a versão do :platform?',
            'cancel' => 'Cancelar',
            'action' => 'Sincronizar com :platform',
        ],

        'sync_disable' => [
            'title' => 'Desativar sincronização?',
            'description' => 'Cada plataforma manterá seu conteúdo atual, mas edições futuras serão aplicadas apenas à plataforma que você estiver editando.',
            'customize_note' => 'Você poderá personalizar o conteúdo para cada plataforma individualmente.',
            'cancel' => 'Cancelar',
            'action' => 'Desativar sincronização',
        ],

        'platforms_dialog' => [
            'title' => 'Selecionar Plataformas',
            'description' => 'Escolha em quais plataformas publicar este post.',
        ],

        'validation' => [
            'select_board' => 'Selecione uma pasta',
            'images_not_supported' => 'Imagens não suportadas',
            'videos_not_supported' => 'Vídeos não suportados',
            'max_images' => 'Máx :count imagens',
            'requires_media' => 'Requer mídia',
            'exceeded' => ':count excedido',
            'does_not_support_images' => ':platform não suporta imagens',
            'supports_up_to_images' => ':platform suporta até :count imagens',
            'does_not_support_videos' => ':platform não suporta vídeos',
        ],
    ],

    'content_types' => [
        'instagram_feed' => [
            'label' => 'Post do Feed',
            'description' => 'Aparece no seu feed e perfil',
        ],
        'instagram_reel' => [
            'label' => 'Reels',
            'description' => 'Vídeo curto de até 90 segundos',
        ],
        'instagram_story' => [
            'label' => 'Story',
            'description' => 'Desaparece após 24 horas',
        ],
        'linkedin_post' => [
            'label' => 'Post',
            'description' => 'Post padrão com texto e mídia',
        ],
        'linkedin_carousel' => [
            'label' => 'Carrossel',
            'description' => 'Imagens deslizáveis',
        ],
        'linkedin_page_post' => [
            'label' => 'Post',
            'description' => 'Post padrão com texto e mídia',
        ],
        'linkedin_page_carousel' => [
            'label' => 'Carrossel',
            'description' => 'Imagens deslizáveis',
        ],
        'facebook_post' => [
            'label' => 'Post',
            'description' => 'Post padrão na sua página',
        ],
        'facebook_reel' => [
            'label' => 'Reels',
            'description' => 'Vídeo curto de até 90 segundos',
        ],
        'facebook_story' => [
            'label' => 'Story',
            'description' => 'Desaparece após 24 horas',
        ],
        'tiktok_video' => [
            'label' => 'Vídeo',
            'description' => 'Conteúdo de vídeo curto',
        ],
        'youtube_short' => [
            'label' => 'Short',
            'description' => 'Vídeo vertical de até 60 segundos',
        ],
        'x_post' => [
            'label' => 'Post',
            'description' => 'Tweet com texto e mídia',
        ],
        'threads_post' => [
            'label' => 'Post',
            'description' => 'Post de texto com mídia opcional',
        ],
        'pinterest_pin' => [
            'label' => 'Pin',
            'description' => 'Pin de imagem com link',
        ],
        'pinterest_video_pin' => [
            'label' => 'Pin de Vídeo',
            'description' => 'Conteúdo em vídeo',
        ],
        'pinterest_carousel' => [
            'label' => 'Carrossel',
            'description' => '2-5 imagens',
        ],
        'bluesky_post' => [
            'label' => 'Post',
            'description' => 'Post de texto com imagens opcionais',
        ],
        'mastodon_post' => [
            'label' => 'Post',
            'description' => 'Post de texto com mídia opcional',
        ],
    ],

    'platforms' => [
        'linkedin' => 'LinkedIn',
        'linkedin-page' => 'Página do LinkedIn',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube Shorts',
        'facebook' => 'Página do Facebook',
        'instagram' => 'Instagram',
        'threads' => 'Threads',
        'pinterest' => 'Pinterest',
        'bluesky' => 'Bluesky',
        'mastodon' => 'Mastodon',
    ],
];
