<?php

return [
    'title' => 'Configurações',
    'description' => 'Gerencie seu perfil e configurações da conta',

    'nav' => [
        'profile' => 'Perfil',
        'password' => 'Senha',
        'workspace' => 'Workspace',
        'members' => 'Membros',
        'billing' => 'Faturamento',
    ],

    'profile' => [
        'title' => 'Configurações do perfil',
        'heading' => 'Informações do perfil',
        'description' => 'Atualize seu nome e endereço de e-mail',
        'avatar' => 'Avatar',
        'name' => 'Nome',
        'name_placeholder' => 'Nome completo',
        'email' => 'Endereço de e-mail',
        'email_placeholder' => 'Endereço de e-mail',
        'email_unverified' => 'Seu endereço de e-mail não foi verificado.',
        'resend_verification' => 'Clique aqui para reenviar o e-mail de verificação.',
        'verification_sent' => 'Um novo link de verificação foi enviado para seu endereço de e-mail.',
        'save' => 'Salvar',
    ],

    'password' => [
        'title' => 'Configurações de senha',
        'heading' => 'Atualizar senha',
        'description' => 'Certifique-se de que sua conta esteja usando uma senha longa e aleatória para se manter seguro',
        'current_password' => 'Senha atual',
        'current_password_placeholder' => 'Senha atual',
        'new_password' => 'Nova senha',
        'new_password_placeholder' => 'Nova senha',
        'confirm_password' => 'Confirmar senha',
        'confirm_password_placeholder' => 'Confirmar senha',
        'save' => 'Salvar senha',
    ],

    'delete_account' => [
        'heading' => 'Excluir conta',
        'description' => 'Exclua sua conta e todos os seus recursos',
        'warning' => 'Atenção',
        'warning_message' => 'Por favor, prossiga com cuidado, isso não pode ser desfeito.',
        'button' => 'Excluir conta',
        'modal_title' => 'Tem certeza que deseja excluir sua conta?',
        'modal_description' => 'Uma vez que sua conta for excluída, todos os seus recursos e dados também serão permanentemente excluídos. Por favor, digite sua senha para confirmar que deseja excluir permanentemente sua conta.',
        'password' => 'Senha',
        'password_placeholder' => 'Senha',
        'cancel' => 'Cancelar',
        'confirm' => 'Excluir conta',
    ],

    'workspace' => [
        'title' => 'Configurações do workspace',
        'heading' => 'Configurações do workspace',
        'description' => 'Atualize o nome, logo e fuso horário do workspace',
        'logo' => 'Logo',
        'name' => 'Nome',
        'name_placeholder' => 'Meu Workspace',
        'timezone' => 'Fuso horário',
        'save' => 'Salvar',
        'saved' => 'Salvo.',
    ],

    'members' => [
        'title' => 'Membros',
        'heading' => 'Membros da equipe',
        'description' => 'Gerencie membros e convites deste workspace',

        'invite' => [
            'title' => 'Convidar Membro',
            'description' => 'Envie um convite por e-mail para adicionar colaboradores',
            'email' => 'E-mail',
            'email_placeholder' => 'colaborador@email.com',
            'role' => 'Função',
            'role_placeholder' => 'Selecione uma função',
            'submit' => 'Enviar Convite',
            'cancel_confirm' => 'Tem certeza que deseja cancelar este convite?',
        ],

        'pending' => [
            'title' => 'Convites Pendentes',
            'description' => 'Convites aguardando aceitação',
            'empty' => 'Nenhum convite pendente',
        ],

        'list' => [
            'title' => 'Membros',
            'description' => 'Pessoas com acesso a este workspace',
            'empty' => 'Nenhum membro além do proprietário',
            'remove_confirm' => 'Tem certeza que deseja remover este membro?',
        ],

        'roles' => [
            'owner' => 'Proprietário',
            'admin' => 'Administrador',
            'member' => 'Membro',
        ],
    ],
];
