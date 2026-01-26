<?php

return [
    'title' => 'Posts',
    'all_posts' => 'Todos os Posts',
    'new_post' => 'Novo Post',
    'no_posts' => 'Nenhum post encontrado',
    'start_creating' => 'Comece criando seu primeiro post.',
    'manage_posts' => 'Gerencie todos os seus posts',
    'delete_confirm' => 'Tem certeza que deseja excluir este post?',
    'by' => 'por',

    'actions' => [
        'view' => 'Ver post',
        'delete' => 'Excluir post',
    ],

    'form' => [
        'post_type' => 'Tipo de Post',
        'board' => 'Pasta',
        'select_board' => 'Selecione uma pasta',
        'search_board' => 'Buscar pasta...',
        'no_board_found' => 'Nenhuma pasta encontrada',
        'media' => 'Mídia',
        'min' => 'Mín',
        'uploading' => 'Enviando...',
        'drop_to_upload' => 'Solte para enviar',
        'drag_and_drop' => 'Arraste e solte ou clique para enviar',
        'photos_and_videos' => 'Fotos e vídeos',
        'photos_only' => 'Apenas fotos',
        'videos_only' => 'Apenas vídeos',
        'drag_to_reorder' => 'Arraste para reordenar',
        'caption' => 'Legenda',
        'write_caption' => 'Escreva sua legenda...',
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
        'labels' => 'Etiquetas',
        'hashtags' => 'Hashtags',
        'schedule' => 'Agendar',
        'publish' => 'Publicar',
        'delete' => 'Excluir',
        'settings' => 'Configurações',
        'schedule_for' => 'Agendar para',
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
            'title' => 'Ativar sincronização?',
            'description' => 'Todas as plataformas compartilharão o mesmo conteúdo. Qualquer edição personalizada feita em plataformas individuais será substituída pelo conteúdo atual.',
            'cancel' => 'Cancelar',
            'action' => 'Ativar sincronização',
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

        'hashtags_modal' => [
            'search' => 'Buscar hashtags...',
            'no_results' => 'Nenhuma hashtag encontrada.',
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

    'flash' => [
        'scheduled' => 'Post agendado com sucesso!',
        'publishing' => 'Post está sendo publicado!',
        'deleted' => 'Post excluído com sucesso!',
        'cannot_edit_published' => 'Posts publicados não podem ser editados.',
        'connect_first' => 'Conecte pelo menos uma rede social antes de criar um post.',
    ],

    'errors' => [
        'account_disconnected' => 'Conta social está desconectada',
    ],
];
