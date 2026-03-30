<?php

return [
    'title' => 'Configuración',
    'description' => 'Administra tu perfil y configuración de la cuenta',

    'nav' => [
        'profile' => 'Perfil',
        'password' => 'Contraseña',
        'workspace' => 'Workspace',
        'members' => 'Miembros',
        'billing' => 'Facturación',
    ],

    'profile' => [
        'title' => 'Configuración del perfil',
        'photo_heading' => 'Foto de perfil',
        'photo_description' => 'Sube una foto de perfil',
        'heading' => 'Información del perfil',
        'description' => 'Actualiza tu nombre y correo electrónico',
        'avatar' => 'Avatar',
        'name' => 'Nombre',
        'name_placeholder' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'Correo electrónico',
        'email_unverified' => 'Tu correo electrónico no ha sido verificado.',
        'resend_verification' => 'Haz clic aquí para reenviar el correo de verificación.',
        'verification_sent' => 'Se ha enviado un nuevo enlace de verificación a tu correo electrónico.',
        'save' => 'Guardar',
    ],

    'password' => [
        'title' => 'Configuración de contraseña',
        'heading' => 'Actualizar contraseña',
        'description' => 'Asegúrate de que tu cuenta use una contraseña larga y aleatoria para mantenerte seguro',
        'current_password' => 'Contraseña actual',
        'current_password_placeholder' => 'Contraseña actual',
        'new_password' => 'Nueva contraseña',
        'new_password_placeholder' => 'Nueva contraseña',
        'confirm_password' => 'Confirmar contraseña',
        'confirm_password_placeholder' => 'Confirmar contraseña',
        'save' => 'Guardar contraseña',
    ],

    'delete_account' => [
        'heading' => 'Eliminar cuenta',
        'description' => 'Elimina tu cuenta y todos sus recursos',
        'warning' => 'Advertencia',
        'warning_message' => 'Procede con precaución, esta acción no se puede deshacer.',
        'button' => 'Eliminar cuenta',
        'modal_title' => '¿Estás seguro de que deseas eliminar tu cuenta?',
        'modal_description' => 'Una vez eliminada tu cuenta, todos sus recursos y datos también se eliminarán permanentemente. Introduce tu contraseña para confirmar que deseas eliminar permanentemente tu cuenta.',
        'password' => 'Contraseña',
        'password_placeholder' => 'Contraseña',
        'cancel' => 'Cancelar',
        'confirm' => 'Eliminar cuenta',
    ],

    'workspace' => [
        'title' => 'Configuración del workspace',
        'logo_heading' => 'Logo del workspace',
        'logo_description' => 'Sube un logo para tu workspace',
        'heading' => 'Nombre del workspace',
        'description' => 'Actualiza el nombre y zona horaria del workspace',
        'members_heading' => 'Miembros',
        'members_description' => 'Administra miembros e invitaciones del workspace',
        'name' => 'Nombre',
        'name_placeholder' => 'Mi Workspace',
        'timezone' => 'Zona horaria',
        'save' => 'Guardar',
    ],

    'members' => [
        'title' => 'Miembros',
        'heading' => 'Miembros del equipo',
        'description' => 'Administra miembros e invitaciones de este workspace',

        'cancel' => 'Cancelar',
        'remove' => 'Eliminar',

        'invite' => [
            'title' => 'Invitar miembro',
            'description' => 'Envía una invitación por correo para agregar colaboradores',
            'email' => 'Correo electrónico',
            'email_placeholder' => 'colaborador@email.com',
            'role' => 'Rol',
            'role_placeholder' => 'Selecciona un rol',
            'submit' => 'Enviar invitación',
        ],

        'pending' => [
            'title' => 'Invitaciones pendientes',
            'description' => 'Invitaciones en espera de aceptación',
            'empty' => 'No hay invitaciones pendientes',
        ],

        'list' => [
            'title' => 'Miembros',
            'description' => 'Personas con acceso a este workspace',
            'empty' => 'No hay miembros además del propietario',
        ],

        'remove_modal' => [
            'title' => 'Eliminar miembro',
            'description' => '¿Estás seguro de que deseas eliminar a este miembro del workspace? Perderá acceso a todos los recursos del workspace.',
            'action' => 'Eliminar miembro',
        ],

        'cancel_invite_modal' => [
            'title' => 'Cancelar invitación',
            'description' => '¿Estás seguro de que deseas cancelar esta invitación?',
            'action' => 'Cancelar invitación',
        ],

        'roles' => [
            'owner' => 'Propietario',
            'admin' => 'Administrador',
            'member' => 'Miembro',
        ],

        'flash' => [
            'invite_sent' => '¡Invitación enviada correctamente!',
            'invite_deleted' => 'Invitación eliminada.',
            'member_removed' => '¡Miembro eliminado correctamente!',
            'wrong_email' => 'Esta invitación es para otro correo electrónico.',
            'already_member' => 'Ya eres miembro de este workspace.',
            'invite_accepted' => '¡Bienvenido! Ahora eres miembro del workspace.',
            'invite_declined' => 'Invitación rechazada.',
        ],
    ],

    'flash' => [
        'profile_updated' => '¡Perfil actualizado correctamente!',
        'language_updated' => '¡Idioma actualizado correctamente!',
        'password_updated' => '¡Contraseña actualizada correctamente!',
        'workspace_updated' => '¡Configuración actualizada correctamente!',
        'photo_updated' => '¡Foto actualizada correctamente!',
        'photo_deleted' => '¡Foto eliminada correctamente!',
        'logo_updated' => '¡Logo subido correctamente!',
        'logo_deleted' => '¡Logo eliminado correctamente!',
    ],
];
