import { Link, router, usePage } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import type { PageProps } from '../types';

type Props = {
    title: string;
    eyebrow?: string;
    actions?: ReactNode;
    children: ReactNode;
};

function Icon({ name, size = 18 }: { name: string; size?: number }) {
    const LucideIcon = (Icons as unknown as Record<string, ComponentType<{ size?: number }>>)[name] ?? Icons.Circle;

    return <LucideIcon size={size} />;
}

export default function AuthenticatedLayout({ title, eyebrow, actions, children }: Props) {
    const { auth, school, navigation, flash } = usePage<PageProps>().props;

    return (
        <div className="app-shell">
            <aside className="sidebar">
                <div className="brand-block">
                    <div className="brand-mark">VK</div>
                    <div>
                        <div className="brand-title">{school.name}</div>
                        <div className="brand-subtitle">Quản lý nội bộ</div>
                    </div>
                </div>

                <nav className="nav-groups">
                    {navigation.map((group) => (
                        <section key={group.label} className="nav-group">
                            <div className="nav-label">{group.label}</div>
                            {group.items.map((item) => (
                                <Link key={item.href} href={item.href} className="nav-link">
                                    <Icon name={item.icon} />
                                    <span>{item.label}</span>
                                </Link>
                            ))}
                        </section>
                    ))}
                </nav>
            </aside>

            <div className="main-shell">
                <header className="topbar">
                    <div>
                        {eyebrow && <div className="eyebrow">{eyebrow}</div>}
                        <h1>{title}</h1>
                    </div>
                    <div className="topbar-actions">
                        {actions}
                        <div className="user-chip">
                            <span>{auth.user?.name}</span>
                            <small>{auth.user?.roles.join(', ')}</small>
                        </div>
                        <button className="icon-button" title="Đăng xuất" onClick={() => router.post('/logout')}>
                            <Icons.LogOut size={18} />
                        </button>
                    </div>
                </header>

                {(flash.success || flash.error) && (
                    <div className={flash.success ? 'flash success' : 'flash error'}>{flash.success ?? flash.error}</div>
                )}

                <main className="content">{children}</main>
            </div>
        </div>
    );
}

