<?php

return [
    'title' => 'Posts',
    'search' => 'Buscar posts...',
    'all_posts' => 'Todos los posts',
    'new_post' => 'Nuevo post',
    'no_posts' => 'No se encontraron posts',
    'start_creating' => 'Empieza creando tu primer post.',
    'manage_posts' => 'Administra todos tus posts',
    'delete_confirm' => '¿Estás seguro de que deseas eliminar este post?',
    'by' => 'por',

    'actions' => [
        'view' => 'Ver post',
        'delete' => 'Eliminar post',
    ],

    'form' => [
        'post_type' => 'Tipo de post',
        'board' => 'Tablero',
        'select_board' => 'Seleccionar tablero',
        'search_board' => 'Buscar tablero...',
        'no_board_found' => 'No se encontró tablero',
        'media' => 'Multimedia',
        'min' => 'Min',
        'uploading' => 'Subiendo...',
        'drop_to_upload' => 'Suelta para subir',
        'drag_and_drop' => 'Arrastra y suelta o haz clic para subir',
        'photos_and_videos' => 'Fotos y videos',
        'photos_only' => 'Solo fotos',
        'videos_only' => 'Solo videos',
        'drag_to_reorder' => 'Arrastra para reordenar',
        'caption' => 'Descripción',
        'write_caption' => 'Escribe tu descripción...',
    ],

    'status' => [
        'pending' => 'Pendiente',
        'draft' => 'Borrador',
        'scheduled' => 'Programado',
        'publishing' => 'Publicando',
        'published' => 'Publicado',
        'partially_published' => 'Parcialmente publicado',
        'failed' => 'Fallido',
    ],

    'descriptions' => [
        'draft' => 'Posts esperando ser programados',
        'scheduled' => 'Posts programados para publicar',
        'published' => 'Posts ya publicados',
    ],

    'edit' => [
        'title' => 'Editar post',
        'view_title' => 'Ver post',
        'manage_platforms' => 'Administrar plataformas',
        'sync' => 'Sincronizar',
        'labels' => 'Etiquetas',
        'hashtags' => 'Hashtags',
        'schedule' => 'Programar',
        'publish' => 'Publicar',
        'delete' => 'Eliminar',
        'settings' => 'Configuración',
        'schedule_for' => 'Programar para',
        'saving' => 'Guardando...',
        'saved' => 'Guardado',
        'scheduled_at' => 'Programado:',
        'published_at' => 'Publicado:',
        'media' => 'Multimedia',
        'caption' => 'Descripción',
        'no_caption' => 'Sin descripción',
        'no_content' => 'Sin contenido',

        'empty_state' => [
            'title' => 'No hay plataformas seleccionadas',
            'description' => 'Selecciona al menos una plataforma para crear tu post',
        ],

        'delete_modal' => [
            'title' => 'Eliminar post',
            'description' => '¿Estás seguro de que deseas eliminar este post? Esta acción no se puede deshacer.',
            'action' => 'Eliminar',
            'cancel' => 'Cancelar',
        ],

        'sync_enable' => [
            'title' => '¿Activar sincronización?',
            'description' => 'Todas las plataformas compartirán el mismo contenido. Las ediciones personalizadas realizadas en plataformas individuales serán reemplazadas con el contenido actual.',
            'cancel' => 'Cancelar',
            'action' => 'Activar sincronización',
        ],

        'sync_disable' => [
            'title' => '¿Desactivar sincronización?',
            'description' => 'Cada plataforma mantendrá su contenido actual, pero las ediciones futuras solo se aplicarán a la plataforma que estés editando.',
            'customize_note' => 'Podrás personalizar el contenido de cada plataforma individualmente.',
            'cancel' => 'Cancelar',
            'action' => 'Desactivar sincronización',
        ],

        'platforms_dialog' => [
            'title' => 'Seleccionar plataformas',
            'description' => 'Elige en qué plataformas publicar este post.',
        ],

        'hashtags_modal' => [
            'search' => 'Buscar hashtags...',
            'no_results' => 'No se encontraron hashtags.',
        ],

        'validation' => [
            'select_board' => 'Selecciona un tablero',
            'images_not_supported' => 'Imágenes no soportadas',
            'videos_not_supported' => 'Videos no soportados',
            'max_images' => 'Máximo :count imágenes',
            'requires_media' => 'Requiere multimedia',
            'exceeded' => ':count excedido',
            'does_not_support_images' => ':platform no soporta imágenes',
            'supports_up_to_images' => ':platform soporta hasta :count imágenes',
            'does_not_support_videos' => ':platform no soporta videos',
        ],
    ],

    'content_types' => [
        'instagram_feed' => [
            'label' => 'Post del feed',
            'description' => 'Aparece en tu feed y perfil',
        ],
        'instagram_reel' => [
            'label' => 'Reel',
            'description' => 'Video corto de hasta 90 segundos',
        ],
        'instagram_story' => [
            'label' => 'Historia',
            'description' => 'Desaparece después de 24 horas',
        ],
        'linkedin_post' => [
            'label' => 'Post',
            'description' => 'Post estándar con texto y multimedia',
        ],
        'linkedin_carousel' => [
            'label' => 'Carrusel',
            'description' => 'Imágenes deslizables',
        ],
        'linkedin_page_post' => [
            'label' => 'Post',
            'description' => 'Post estándar con texto y multimedia',
        ],
        'linkedin_page_carousel' => [
            'label' => 'Carrusel',
            'description' => 'Imágenes deslizables',
        ],
        'facebook_post' => [
            'label' => 'Post',
            'description' => 'Post estándar en tu página',
        ],
        'facebook_reel' => [
            'label' => 'Reel',
            'description' => 'Video corto de hasta 90 segundos',
        ],
        'facebook_story' => [
            'label' => 'Historia',
            'description' => 'Desaparece después de 24 horas',
        ],
        'tiktok_video' => [
            'label' => 'Video',
            'description' => 'Contenido de video corto',
        ],
        'youtube_short' => [
            'label' => 'Short',
            'description' => 'Video vertical de hasta 60 segundos',
        ],
        'x_post' => [
            'label' => 'Post',
            'description' => 'Tweet con texto y multimedia',
        ],
        'threads_post' => [
            'label' => 'Post',
            'description' => 'Post de texto con multimedia opcional',
        ],
        'pinterest_pin' => [
            'label' => 'Pin',
            'description' => 'Pin de imagen con enlace',
        ],
        'pinterest_video_pin' => [
            'label' => 'Pin de video',
            'description' => 'Contenido de video',
        ],
        'pinterest_carousel' => [
            'label' => 'Carrusel',
            'description' => '2-5 imágenes',
        ],
        'bluesky_post' => [
            'label' => 'Post',
            'description' => 'Post de texto con imágenes opcionales',
        ],
        'mastodon_post' => [
            'label' => 'Post',
            'description' => 'Post de texto con multimedia opcional',
        ],
    ],

    'platforms' => [
        'linkedin' => 'LinkedIn',
        'linkedin-page' => 'Página de LinkedIn',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube Shorts',
        'facebook' => 'Página de Facebook',
        'instagram' => 'Instagram',
        'threads' => 'Threads',
        'pinterest' => 'Pinterest',
        'bluesky' => 'Bluesky',
        'mastodon' => 'Mastodon',
    ],

    'flash' => [
        'scheduled' => '¡Post programado correctamente!',
        'publishing' => '¡El post se está publicando!',
        'deleted' => '¡Post eliminado correctamente!',
        'cannot_edit_published' => 'Los posts publicados no se pueden editar.',
        'connect_first' => 'Conecta al menos una red social antes de crear un post.',
    ],

    'errors' => [
        'account_disconnected' => 'Cuenta social desconectada',
        'account_inactive' => 'Cuenta social desactivada',
    ],
];
