import { Head, usePage } from '@inertiajs/react';
import { Activity, ClipboardCheck, ReceiptText, ShieldCheck } from 'lucide-react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import type { PageProps } from '../types';

type Props = {
    stats: { label: string; value: number; tone: string }[];
    activityStats: {
        events: number;
        conductScores: number;
        unpaidInvoices: number;
    };
    recentAudits: {
        id: number;
        action: string;
        actor: string;
        subject: string;
        created_at: string;
    }[];
};

export default function Dashboard({ stats, activityStats, recentAudits }: Props) {
    const { school } = usePage<PageProps>().props;

    return (
        <AuthenticatedLayout title="Dashboard" eyebrow={school.email}>
            <Head title="Dashboard" />

            <div className="stats-grid">
                {stats.map((stat) => (
                    <div className={`stat-card tone-${stat.tone}`} key={stat.label}>
                        <span>{stat.label}</span>
                        <strong>{stat.value.toLocaleString('vi-VN')}</strong>
                    </div>
                ))}
            </div>

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading">
                        <Activity size={20} />
                        <h2>Vận hành trường học</h2>
                    </div>
                    <div className="metric-list">
                        <div>
                            <ClipboardCheck size={18} />
                            <span>Điểm rèn luyện</span>
                            <strong>{activityStats.conductScores}</strong>
                        </div>
                        <div>
                            <ReceiptText size={18} />
                            <span>Phiếu chưa tất toán</span>
                            <strong>{activityStats.unpaidInvoices}</strong>
                        </div>
                        <div>
                            <ShieldCheck size={18} />
                            <span>Phong trào/Hội thi</span>
                            <strong>{activityStats.events}</strong>
                        </div>
                    </div>
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <ShieldCheck size={20} />
                        <h2>Audit gần đây</h2>
                    </div>
                    <div className="audit-list">
                        {recentAudits.map((audit) => (
                            <div key={audit.id} className="audit-row">
                                <div>
                                    <strong>{audit.action}</strong>
                                    <span>{audit.actor} · {audit.subject}</span>
                                </div>
                                <time>{audit.created_at}</time>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

