<?php

return [
    'title' => 'Settings',
    'description' => 'Manage your profile and account settings',

    'nav' => [
        'profile' => 'Profile',
        'password' => 'Password',
        'workspace' => 'Workspace',
        'members' => 'Members',
        'notifications' => 'Notifications',
        'billing' => 'Billing',
    ],

    'notifications' => [
        'title' => 'Notification preferences',
        'heading' => 'Email notifications',
        'description' => 'Choose which email notifications you want to receive',
        'post_published' => 'Post published',
        'post_published_description' => 'Receive an email when your post is published successfully',
        'post_failed' => 'Post failed',
        'post_failed_description' => 'Receive an email when your post fails to publish',
        'account_disconnected' => 'Account disconnected',
        'account_disconnected_description' => 'Receive an email when a social account is disconnected',
        'save' => 'Save preferences',
    ],

    'profile' => [
        'title' => 'Profile settings',
        'photo_heading' => 'Profile photo',
        'photo_description' => 'Upload a profile photo',
        'heading' => 'Profile information',
        'description' => 'Update your name and email address',
        'avatar' => 'Avatar',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => 'Email address',
        'email_unverified' => 'Your email address is unverified.',
        'resend_verification' => 'Click here to resend the verification email.',
        'verification_sent' => 'A new verification link has been sent to your email address.',
        'save' => 'Save',
    ],

    'password' => [
        'title' => 'Password settings',
        'heading' => 'Update password',
        'description' => 'Ensure your account is using a long, random password to stay secure',
        'current_password' => 'Current password',
        'current_password_placeholder' => 'Current password',
        'new_password' => 'New password',
        'new_password_placeholder' => 'New password',
        'confirm_password' => 'Confirm password',
        'confirm_password_placeholder' => 'Confirm password',
        'save' => 'Save password',
    ],

    'delete_account' => [
        'heading' => 'Delete account',
        'description' => 'Delete your account and all of its resources',
        'warning' => 'Warning',
        'warning_message' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'modal_title' => 'Are you sure you want to delete your account?',
        'modal_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'cancel' => 'Cancel',
        'confirm' => 'Delete account',
    ],

    'workspace' => [
        'title' => 'Workspace settings',
        'logo_heading' => 'Workspace logo',
        'logo_description' => 'Upload a logo for your workspace',
        'heading' => 'Workspace name',
        'description' => 'Update your workspace name and timezone',
        'members_heading' => 'Members',
        'members_description' => 'Manage workspace members and invitations',
        'name' => 'Name',
        'name_placeholder' => 'My Workspace',
        'timezone' => 'Timezone',
        'save' => 'Save',
    ],

    'members' => [
        'title' => 'Members',
        'heading' => 'Team members',
        'description' => 'Manage members and invites for this workspace',

        'cancel' => 'Cancel',
        'remove' => 'Remove',
        'make_admin' => 'Make admin',
        'make_member' => 'Make member',

        'invite' => [
            'title' => 'Invite Member',
            'description' => 'Send an email invite to add collaborators',
            'email' => 'Email',
            'email_placeholder' => 'collaborator@email.com',
            'role' => 'Role',
            'role_placeholder' => 'Select a role',
            'submit' => 'Send Invite',
        ],

        'pending' => [
            'title' => 'Pending Invites',
            'description' => 'Invites awaiting acceptance',
            'empty' => 'No pending invites',
        ],

        'list' => [
            'title' => 'Members',
            'description' => 'People with access to this workspace',
            'empty' => 'No members besides the owner',
        ],

        'remove_modal' => [
            'title' => 'Remove member',
            'description' => 'Are you sure you want to remove this member from the workspace? They will lose access to all workspace resources.',
            'action' => 'Remove member',
        ],

        'cancel_invite_modal' => [
            'title' => 'Cancel invitation',
            'description' => 'Are you sure you want to cancel this invitation?',
            'action' => 'Cancel invitation',
        ],

        'roles' => [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Member',
        ],

        'flash' => [
            'invite_sent' => 'Invite sent successfully!',
            'invite_deleted' => 'Invite deleted.',
            'member_removed' => 'Member removed successfully.',
            'role_updated' => 'Member role updated.',
            'wrong_email' => 'This invite is for a different email address.',
            'already_member' => 'You are already a member of this workspace.',
            'invite_accepted' => 'Welcome! You are now a member of the workspace.',
            'invite_declined' => 'Invite declined.',
        ],
    ],

    'flash' => [
        'profile_updated' => 'Profile updated successfully!',
        'language_updated' => 'Language updated successfully!',
        'password_updated' => 'Password updated successfully!',
        'workspace_updated' => 'Settings updated successfully!',
        'photo_updated' => 'Photo updated successfully!',
        'photo_deleted' => 'Photo removed successfully!',
        'logo_updated' => 'Logo uploaded successfully!',
        'logo_deleted' => 'Logo removed successfully!',
        'notifications_updated' => 'Notification preferences updated!',
    ],

    'api_keys' => [
        'title' => 'API Keys',
        'page_title' => 'API Keys',
        'heading' => 'API Keys',
        'description' => 'Manage API keys for programmatic access to your workspace.',
        'create' => 'Create API Key',
        'copy' => 'Copy',
        'new_token_message' => 'Your new API key has been created. Copy it now — you won\'t be able to see it again.',
        'table' => [
            'name' => 'Name',
            'key' => 'Key',
            'status' => 'Status',
            'expires' => 'Expires',
            'last_used' => 'Last Used',
            'never' => 'Never',
        ],
        'actions' => [
            'copy_id' => 'Copy API Key ID',
            'copy_id_success' => 'API Key ID copied to clipboard',
            'delete' => 'Delete',
        ],
        'empty' => [
            'title' => 'No API keys yet',
            'description' => 'Create an API key to access your workspace programmatically.',
        ],
        'delete_modal' => [
            'title' => 'Delete API key',
            'description' => 'Are you sure you want to delete this API key? Any applications using this key will lose access immediately.',
            'action' => 'Delete API key',
        ],
        'create_dialog' => [
            'title' => 'Create API Key',
            'description' => 'Create a new API key for programmatic access to your workspace.',
            'name' => 'Name',
            'name_placeholder' => 'e.g. Production API Key',
            'expires' => 'Expiration date (optional)',
            'expires_placeholder' => 'No expiration',
            'submit' => 'Create',
            'cancel' => 'Cancel',
        ],
        'flash' => [
            'created' => 'API key created successfully!',
            'deleted' => 'API key deleted successfully!',
        ],
    ],
];
