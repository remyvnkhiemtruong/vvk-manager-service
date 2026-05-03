import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Shuffle } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventMatch, EventStanding, EventTeam } from './types';

type Props = { event: EventItem; categories: EventCategory[]; teams: EventTeam[]; matches: EventMatch[]; standings: EventStanding[] };

export default function Groups({ event, categories, teams, matches, standings }: Props) {
    const form = useForm({ event_category_id: String(categories[0]?.id ?? ''), group_count: '2', starts_at: event.starts_at ?? '', minutes_per_match: '45', location: event.location ?? '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/events/${event.id}/groups/draw`, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Chia bảng và bốc thăm" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Chia bảng" />
            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Nội dung</span><select value={form.data.event_category_id} onChange={(e) => form.setData('event_category_id', e.target.value)}>{categories.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Số bảng</span><input type="number" min="1" value={form.data.group_count} onChange={(e) => form.setData('group_count', e.target.value)} /></label>
                    <label><span>Bắt đầu</span><input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></label>
                    <label><span>Phút/trận</span><input type="number" min="10" value={form.data.minutes_per_match} onChange={(e) => form.setData('minutes_per_match', e.target.value)} /></label>
                    <label className="span-2"><span>Địa điểm</span><input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} /></label>
                    <button className="primary-button"><Shuffle size={17} />Bốc thăm</button>
                </form>
            </section>
            <section className="two-column">
                <div className="table-panel"><table><thead><tr><th>Đội</th><th>Nội dung</th><th>Bảng</th><th>Seed</th><th>Thành viên</th></tr></thead><tbody>{teams.map((team) => <tr key={team.id}><td>{team.name}</td><td>{team.category_name}</td><td>{team.group_code ?? '-'}</td><td>{team.seed_number ?? '-'}</td><td>{team.members.length}</td></tr>)}</tbody></table></div>
                <div className="table-panel"><table><thead><tr><th>Hạng</th><th>Bảng</th><th>Đội</th><th>Trận</th><th>Điểm</th><th>Hiệu số</th></tr></thead><tbody>{standings.map((row) => <tr key={row.id}><td>{row.rank}{row.needs_manual_rank ? ' *' : ''}</td><td>{row.group_code}</td><td>{row.team_name}</td><td>{row.played}</td><td>{row.points}</td><td>{row.score_diff}</td></tr>)}</tbody></table></div>
            </section>
            <section className="table-panel"><table><thead><tr><th>Nội dung</th><th>Bảng</th><th>Đội 1</th><th>Đội 2</th><th>Tỷ số</th><th>Trạng thái</th></tr></thead><tbody>{matches.map((match) => <tr key={match.id}><td>{match.category_name}</td><td>{match.group_code}</td><td>{match.home_team_name}</td><td>{match.away_team_name}</td><td>{match.home_score ?? '-'} - {match.away_score ?? '-'}</td><td>{match.status}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
