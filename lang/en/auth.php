<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'flash' => [
        'welcome' => 'Welcome to TryPost!',
        'welcome_trial' => 'Welcome to TryPost! Your trial has started.',
    ],

    'login' => [
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'page_title' => 'Log in',
        'email' => 'Email address',
        'password' => 'Password',
        'forgot_password' => 'Forgot password?',
        'remember_me' => 'Remember me',
        'submit' => 'Log in',
        'no_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
    ],

    'register' => [
        'title' => 'Create an account',
        'description' => 'Enter your details below to create your account',
        'page_title' => 'Register',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'password' => 'Password',
        'submit' => 'Create account',
        'has_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],

    'forgot_password' => [
        'title' => 'Forgot password',
        'description' => 'Enter your email to receive a password reset link',
        'page_title' => 'Forgot password',
        'email' => 'Email address',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],

    'reset_password' => [
        'title' => 'Reset password',
        'description' => 'Please enter your new password below',
        'page_title' => 'Reset password',
        'email' => 'Email',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'confirm_placeholder' => 'Confirm password',
        'submit' => 'Reset password',
    ],

    'verify_email' => [
        'title' => 'Verify email',
        'description' => 'Please verify your email address by clicking on the link we just emailed to you.',
        'page_title' => 'Email verification',
        'link_sent' => 'A new verification link has been sent to the email address you provided during registration.',
        'resend' => 'Resend verification email',
        'log_out' => 'Log out',
    ],

    'accept_invite' => [
        'page_title' => 'Accept Invite',
        'title' => "You've been invited!",
        'description' => "You've been invited to join the :workspace workspace.",
        'workspace' => 'Workspace',
        'your_role' => 'Your role',
        'email' => 'Email',
        'accept' => 'Accept Invite',
        'decline' => 'Decline Invite',
        'login_prompt' => 'Log in or create an account to accept this invite.',
        'log_in' => 'Log in',
        'create_account' => 'Create Account',
    ],

];
