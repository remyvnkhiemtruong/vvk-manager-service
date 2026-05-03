import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Plus, Trophy } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Campaign, ResultRow } from './types';

type Props = {
    dashboard: {
        stats: { total: number; open: number; running: number; summarized: number; pending_registrations: number };
        upcoming: Campaign[];
        recentResults: ResultRow[];
    };
};

export default function Dashboard({ dashboard }: Props) {
    return (
        <AuthenticatedLayout
            title="Dashboard Đoàn trường/BTC"
            eyebrow="Phong trào, thi đua và hoạt động Đoàn"
            actions={<Link className="primary-button" href="/campaigns/create"><Plus size={17} />Tạo hoạt động</Link>}
        >
            <Head title="Dashboard phong trào" />

            <section className="stats-grid">
                <div className="stat-card tone-blue"><span>Tổng hoạt động</span><strong>{dashboard.stats.total}</strong></div>
                <div className="stat-card tone-green"><span>Mở đăng ký</span><strong>{dashboard.stats.open}</strong></div>
                <div className="stat-card tone-amber"><span>Đang diễn ra</span><strong>{dashboard.stats.running}</strong></div>
                <div className="stat-card tone-rose"><span>Chờ duyệt</span><strong>{dashboard.stats.pending_registrations}</strong></div>
            </section>

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading"><Trophy size={18} /><h2>Hoạt động đang mở</h2></div>
                    <div className="metric-list">
                        {dashboard.upcoming.map((campaign) => (
                            <Link className="compact-row" href={`/campaigns/${campaign.id}/summary`} key={campaign.id}>
                                <span>{campaign.title}</span>
                                <strong>{campaign.status_label}</strong>
                            </Link>
                        ))}
                    </div>
                </section>
                <section className="panel">
                    <div className="panel-heading"><ClipboardCheck size={18} /><h2>Kết quả gần đây</h2></div>
                    <div className="metric-list">
                        {dashboard.recentResults.map((result) => (
                            <div key={result.id}>
                                <span>{result.participant_name ?? '-'}</span>
                                <strong>{result.award_title ?? (result.rank ? `Hạng ${result.rank}` : result.total_score ?? '-')}</strong>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
