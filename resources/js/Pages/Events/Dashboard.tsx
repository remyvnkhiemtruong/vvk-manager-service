import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Plus, Trophy } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventItem, EventResult } from './types';

type Props = {
    dashboard: {
        stats: { total: number; open: number; running: number; summarized: number; pending_registrations: number };
        upcoming: EventItem[];
        recentResults: EventResult[];
    };
};

export default function Dashboard({ dashboard }: Props) {
    return (
        <AuthenticatedLayout
            title="Dashboard hội thi/hội thao"
            eyebrow="Điều phối sự kiện cấp trường"
            actions={<Link className="primary-button" href="/events/create"><Plus size={17} />Tạo sự kiện</Link>}
        >
            <Head title="Dashboard hội thi/hội thao" />
            <section className="stats-grid">
                <div className="stat-card tone-blue"><span>Tổng sự kiện</span><strong>{dashboard.stats.total}</strong></div>
                <div className="stat-card tone-green"><span>Mở đăng ký</span><strong>{dashboard.stats.open}</strong></div>
                <div className="stat-card tone-amber"><span>Đang diễn ra</span><strong>{dashboard.stats.running}</strong></div>
                <div className="stat-card tone-rose"><span>Chờ duyệt</span><strong>{dashboard.stats.pending_registrations}</strong></div>
            </section>
            <section className="two-column">
                <div className="panel">
                    <div className="panel-heading"><Trophy size={18} /><h2>Sắp diễn ra</h2></div>
                    <div className="metric-list">
                        {dashboard.upcoming.map((event) => <Link className="compact-row" href={`/events/${event.id}`} key={event.id}><span>{event.title}</span><strong>{event.status_label}</strong></Link>)}
                    </div>
                </div>
                <div className="panel">
                    <div className="panel-heading"><ClipboardCheck size={18} /><h2>Kết quả mới</h2></div>
                    <div className="metric-list">
                        {dashboard.recentResults.map((result) => <div key={result.id}><span>{result.participant_name ?? '-'}</span><strong>{result.award_title ?? result.score ?? '-'}</strong></div>)}
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
