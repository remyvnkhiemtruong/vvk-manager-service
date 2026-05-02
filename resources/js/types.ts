import type { ReactNode } from 'react';

export type NavigationItem = {
    label: string;
    href: string;
    icon: string;
};

export type NavigationGroup = {
    label: string;
    items: NavigationItem[];
};

export type PageProps = {
    auth: {
        user: null | {
            id: number;
            name: string;
            email: string;
            roles: string[];
            permissions: string[];
        };
    };
    flash: {
        success?: string | null;
        error?: string | null;
    };
    school: {
        name: string;
        address: string;
        email: string;
        website: string;
    };
    navigation: NavigationGroup[];
    children?: ReactNode;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

