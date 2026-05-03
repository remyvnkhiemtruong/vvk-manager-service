import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventMatch, EventResult, EventStanding } from './types';

type Props = { event: EventItem; categories: EventCategory[]; matches: EventMatch[]; standings: EventStanding[]; results: EventResult[] };

export default function Results({ event, categories, matches, standings, results }: Props) {
    const form = useForm({ event_category_id: String(categories[0]?.id ?? ''), event_team_id: '', score: '', rank: '', award_title: '', conduct_points: String(event.conduct_points_per_student ?? 0), class_points: String(event.class_competition_points ?? 0), status: 'published' });
    function submit(e: FormEvent) { e.preventDefault(); form.post(`/events/${event.id}/results`, { preserveScroll: true, onSuccess: () => form.reset('event_team_id', 'score', 'rank', 'award_title') }); }
    function scoreMatch(match: EventMatch) {
        const home_score = window.prompt(`Tỷ số ${match.home_team_name}`, String(match.home_score ?? '0'));
        const away_score = window.prompt(`Tỷ số ${match.away_team_name}`, String(match.away_score ?? '0'));
        if (home_score !== null && away_score !== null) router.post(`/events/matches/${match.id}/score`, { home_score, away_score }, { preserveScroll: true });
    }
    return (
        <AuthenticatedLayout title="Nhập kết quả" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Nhập kết quả" />
            <section className="panel"><form className="grid-form" onSubmit={submit}><label><span>Nội dung</span><select value={form.data.event_category_id} onChange={(e) => form.setData('event_category_id', e.target.value)}>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label><span>ID đội/kết quả</span><input value={form.data.event_team_id} onChange={(e) => form.setData('event_team_id', e.target.value)} placeholder="Nhập ID đội nếu nhập thủ công" /></label><label><span>Điểm</span><input type="number" value={form.data.score} onChange={(e) => form.setData('score', e.target.value)} /></label><label><span>Hạng</span><input type="number" value={form.data.rank} onChange={(e) => form.setData('rank', e.target.value)} /></label><label><span>Giải</span><input value={form.data.award_title} onChange={(e) => form.setData('award_title', e.target.value)} /></label><label><span>Điểm RL</span><input type="number" value={form.data.conduct_points} onChange={(e) => form.setData('conduct_points', e.target.value)} /></label><label><span>Điểm lớp</span><input type="number" value={form.data.class_points} onChange={(e) => form.setData('class_points', e.target.value)} /></label><button className="primary-button"><Save size={17} />Lưu kết quả</button></form></section>
            <section className="table-panel"><table><thead><tr><th>Trận</th><th>Bảng</th><th>Đội 1</th><th>Đội 2</th><th>Tỷ số</th><th></th></tr></thead><tbody>{matches.map((match) => <tr key={match.id}><td>{match.category_name}</td><td>{match.group_code}</td><td>{match.home_team_name}</td><td>{match.away_team_name}</td><td>{match.home_score ?? '-'} - {match.away_score ?? '-'}</td><td><button className="secondary-button" onClick={() => scoreMatch(match)}>Nhập tỷ số</button></td></tr>)}</tbody></table></section>
            <section className="two-column"><div className="table-panel"><table><thead><tr><th>Hạng</th><th>Bảng</th><th>Đội</th><th>Điểm</th><th>Hiệu số</th></tr></thead><tbody>{standings.map((row) => <tr key={row.id}><td>{row.rank}{row.needs_manual_rank ? ' *' : ''}</td><td>{row.group_code}</td><td>{row.team_name}</td><td>{row.points}</td><td>{row.score_diff}</td></tr>)}</tbody></table></div><div className="table-panel"><table><thead><tr><th>Nội dung</th><th>Người/đội</th><th>Điểm</th><th>Hạng</th><th>Giải</th></tr></thead><tbody>{results.map((row) => <tr key={row.id}><td>{row.category_name}</td><td>{row.participant_name}</td><td>{row.score}</td><td>{row.rank}</td><td>{row.award_title}</td></tr>)}</tbody></table></div></section>
        </AuthenticatedLayout>
    );
}
