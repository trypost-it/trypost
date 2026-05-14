<?php

declare(strict_types=1);

return [
    'title' => 'Workspaces',
    'select_title' => 'Seus workspaces',
    'select_description' => 'Selecione um workspace para continuar',
    'current' => 'Atual',
    'connections' => ':count conexões',
    'posts' => ':count posts',

    'create' => [
        'page_title' => 'Crie seu workspace',
        'title' => 'Configure seu workspace',
        'description' => 'Conte um pouco sobre você ou seu projeto. Vamos usar pra personalizar os posts gerados por IA com a sua voz.',
        'website' => 'Site',
        'website_placeholder' => 'https://suamarca.com',
        'autofill' => 'Preencher do site',
        'autofill_missing_url' => 'Informe uma URL primeiro.',
        'autofill_success' => 'Informações da marca carregadas.',
        'autofill_error' => 'Não foi possível preencher automaticamente. Você pode preencher os campos manualmente.',
        'autofill_errors' => [
            'unreachable' => 'Não conseguimos acessar esse site (:reason).',
            'http_status' => 'O site retornou um status inesperado (:status).',
            'invalid_scheme' => 'Apenas URLs http e https são suportadas.',
            'missing_host' => 'A URL está sem um host.',
            'unresolvable_host' => 'Não conseguimos resolver o host (:host).',
            'private_network' => 'URLs apontando para redes privadas não são permitidas.',
        ],
        'logo_captured' => 'Logo capturada do seu site.',
        'name' => 'Nome do workspace',
        'name_placeholder' => 'ex. Acme Inc',
        'brand_description' => 'Descrição da marca',
        'brand_description_placeholder' => 'O que sua marca faz?',
        'tone' => 'Tom da marca',
        'tone_professional' => 'Profissional',
        'tone_casual' => 'Casual',
        'tone_friendly' => 'Amigável',
        'tone_bold' => 'Ousado',
        'tone_inspirational' => 'Inspirador',
        'tone_humorous' => 'Bem-humorado',
        'tone_educational' => 'Educacional',
        'content_language' => 'Idioma do conteúdo',
        'content_language_description' => 'Legendas geradas por IA serão escritas neste idioma.',
        'voice_notes' => 'Notas de voz (opcional)',
        'voice_notes_placeholder' => 'ex. frases curtas e diretas. sem jargão.',
        'brand_color' => 'Cor da marca',
        'background_color' => 'Cor de fundo',
        'text_color' => 'Cor do texto',
        'submit' => 'Criar workspace',
        'success' => 'Workspace criado. Conecte uma conta social para começar a postar.',
    ],

    'limit_reached' => 'Você atingiu o limite de workspaces do seu plano.',

    'delete' => [
        'title' => 'Excluir workspace',
        'description' => 'Esta ação é permanente e não pode ser desfeita.',
        'impact_title' => 'Os seguintes itens serão excluídos permanentemente:',
        'impact' => [
            'posts' => '{0} Nenhum post|{1} :count post|[2,*] :count posts',
            'social_accounts' => '{0} Nenhuma conta conectada|{1} :count conta conectada|[2,*] :count contas conectadas',
            'labels' => '{0} Nenhuma etiqueta|{1} :count etiqueta|[2,*] :count etiquetas',
            'signatures' => '{0} Nenhuma assinatura|{1} :count assinatura|[2,*] :count assinaturas',
            'members' => '{0} Nenhum membro|{1} :count membro perderá o acesso|[2,*] :count membros perderão o acesso',
        ],
        'confirm' => 'Excluir workspace permanentemente',
    ],

    'flash' => [
        'deleted' => 'Workspace excluído com sucesso.',
    ],
];
