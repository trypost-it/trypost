import { InertiaLinkProps } from '@inertiajs/vue3';
import type { Component } from 'vue';

export interface Auth {
    user: User;
    role: 'owner' | 'admin' | 'member' | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: Component;
    isActive?: boolean;
    activePattern?: string;
    exact?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    selfHosted: boolean;
    [key: string]: unknown;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & SharedData;

export interface User {
    id: number;
    name: string;
    email: string;
    has_photo: boolean;
    photo_url: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export type BreadcrumbItemType = BreadcrumbItem;
