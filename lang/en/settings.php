<?php

return [
    'title' => 'Settings',
    'description' => 'Manage your profile and account settings',

    'nav' => [
        'profile' => 'Profile',
        'password' => 'Password',
        'workspace' => 'Workspace',
        'members' => 'Members',
        'billing' => 'Billing',
    ],

    'profile' => [
        'title' => 'Profile settings',
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
        'heading' => 'Workspace settings',
        'description' => 'Update your workspace name, logo, and timezone',
        'logo' => 'Logo',
        'name' => 'Name',
        'name_placeholder' => 'My Workspace',
        'timezone' => 'Timezone',
        'save' => 'Save',
        'saved' => 'Saved.',
    ],

    'members' => [
        'title' => 'Members',
        'heading' => 'Team members',
        'description' => 'Manage members and invites for this workspace',

        'invite' => [
            'title' => 'Invite Member',
            'description' => 'Send an email invite to add collaborators',
            'email' => 'Email',
            'email_placeholder' => 'collaborator@email.com',
            'role' => 'Role',
            'role_placeholder' => 'Select a role',
            'submit' => 'Send Invite',
            'cancel_confirm' => 'Are you sure you want to cancel this invite?',
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
            'remove_confirm' => 'Are you sure you want to remove this member?',
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
    ],
];
