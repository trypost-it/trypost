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
        'photo_heading' => 'Foto do perfil',
        'photo_description' => 'Envie uma foto de perfil',
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
        'logo_heading' => 'Logo do workspace',
        'logo_description' => 'Envie um logo para o workspace',
        'heading' => 'Nome do workspace',
        'description' => 'Atualize o nome e fuso horário do workspace',
        'members_heading' => 'Membros',
        'members_description' => 'Gerencie membros e convites do workspace',
        'name' => 'Nome',
        'name_placeholder' => 'Meu Workspace',
        'timezone' => 'Fuso horário',
        'save' => 'Salvar',
    ],

    'members' => [
        'title' => 'Membros',
        'heading' => 'Membros da equipe',
        'description' => 'Gerencie membros e convites deste workspace',

        'cancel' => 'Cancelar',
        'remove' => 'Remover',
        'make_admin' => 'Tornar administrador',
        'make_member' => 'Tornar membro',

        'invite' => [
            'title' => 'Convidar Membro',
            'description' => 'Envie um convite por e-mail para adicionar colaboradores',
            'email' => 'E-mail',
            'email_placeholder' => 'colaborador@email.com',
            'role' => 'Função',
            'role_placeholder' => 'Selecione uma função',
            'submit' => 'Enviar Convite',
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
        ],

        'remove_modal' => [
            'title' => 'Remover membro',
            'description' => 'Tem certeza que deseja remover este membro do workspace? Ele perderá acesso a todos os recursos do workspace.',
            'action' => 'Remover membro',
        ],

        'cancel_invite_modal' => [
            'title' => 'Cancelar convite',
            'description' => 'Tem certeza que deseja cancelar este convite?',
            'action' => 'Cancelar convite',
        ],

        'roles' => [
            'owner' => 'Proprietário',
            'admin' => 'Administrador',
            'member' => 'Membro',
        ],

        'flash' => [
            'invite_sent' => 'Convite enviado com sucesso!',
            'invite_deleted' => 'Convite excluído.',
            'member_removed' => 'Membro removido com sucesso.',
            'role_updated' => 'Função do membro atualizada.',
            'wrong_email' => 'Este convite é para um endereço de e-mail diferente.',
            'already_member' => 'Você já é membro deste workspace.',
            'invite_accepted' => 'Bem-vindo! Você agora é membro do workspace.',
            'invite_declined' => 'Convite recusado.',
        ],
    ],

    'flash' => [
        'profile_updated' => 'Perfil atualizado com sucesso!',
        'language_updated' => 'Idioma atualizado com sucesso!',
        'password_updated' => 'Senha atualizada com sucesso!',
        'workspace_updated' => 'Configurações atualizadas com sucesso!',
        'photo_updated' => 'Foto atualizada com sucesso!',
        'photo_deleted' => 'Foto removida com sucesso!',
        'logo_updated' => 'Logo enviado com sucesso!',
        'logo_deleted' => 'Logo removido com sucesso!',
    ],
];
