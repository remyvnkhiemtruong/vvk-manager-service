import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Medal, PenLine, Swords, Trophy } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventAward, EventCategory, EventItem, EventMatch, EventResult, EventTeam } from './types';

type Props = { event: EventItem; categories: EventCategory[]; teams: EventTeam[]; matches: EventMatch[]; results: EventResult[]; awards: EventAward[] };

export default function Detail({ event, categories, teams, matches, results, awards }: Props) {
    return (
        <AuthenticatedLayout
            title={event.title}
            eyebrow={`${event.type_label} · ${event.status_label}`}
            actions={<><Link className="secondary-button" href={`/events/${event.id}/edit`}><PenLine size={17} />Sửa</Link><Link className="primary-button" href={`/events/${event.id}/register`}>Đăng ký</Link></>}
        >
            <Head title={event.title} />
            <section className="stats-grid">
                <div className="stat-card tone-blue"><span>Nội dung thi</span><strong>{categories.length}</strong></div>
                <div className="stat-card tone-green"><span>Đội/nhóm</span><strong>{teams.length}</strong></div>
                <div className="stat-card tone-amber"><span>Trận/lượt thi</span><strong>{matches.length}</strong></div>
                <div className="stat-card tone-rose"><span>Giải thưởng</span><strong>{awards.length}</strong></div>
            </section>
            <section className="two-column">
                <div className="panel">
                    <div className="panel-heading"><ClipboardList size={18} /><h2>Thông tin</h2></div>
                    <div className="metric-list">
                        <div><span>Đơn vị</span><strong>{event.organizer_unit ?? '-'}</strong></div>
                        <div><span>Địa điểm</span><strong>{event.location ?? '-'}</strong></div>
                        <div><span>Thời gian</span><strong>{event.starts_at ?? '-'} - {event.ends_at ?? '-'}</strong></div>
                    </div>
                </div>
                <div className="panel">
                    <div className="panel-heading"><Swords size={18} /><h2>Thao tác</h2></div>
                    <div className="tag-list">
                        <Link className="secondary-button" href={`/events/${event.id}/categories`}>Nội dung thi</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/groups`}>Chia bảng</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/schedules`}>Lịch thi đấu</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/results`}>Nhập kết quả</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/scoring`}>Chấm điểm</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/awards`}>Trao giải</Link>
                        <Link className="secondary-button" href={`/events/${event.id}/summary`}>Tổng kết</Link>
                    </div>
                </div>
            </section>
            <section className="table-panel">
                <table>
                    <thead><tr><th>Nội dung</th><th>Hình thức</th><th>Luật/chấm</th><th>Trạng thái</th></tr></thead>
                    <tbody>{categories.map((category) => <tr key={category.id}><td>{category.name}</td><td>{category.participation_type}</td><td>{category.sport_rule ?? category.scoring_mode}</td><td>{category.status}</td></tr>)}</tbody>
                </table>
            </section>
            <section className="two-column">
                <div className="panel">
                    <div className="panel-heading"><Trophy size={18} /><h2>Kết quả</h2></div>
                    <div className="metric-list">{results.slice(0, 6).map((row) => <div key={row.id}><span>{row.participant_name}</span><strong>{row.award_title ?? row.score ?? '-'}</strong></div>)}</div>
                </div>
                <div className="panel">
                    <div className="panel-heading"><Medal size={18} /><h2>Giải thưởng</h2></div>
                    <div className="metric-list">{awards.slice(0, 6).map((award) => <div key={award.id}><span>{award.participant_name}</span><strong>{award.title}</strong></div>)}</div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
