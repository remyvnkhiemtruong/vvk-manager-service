import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventRegistration, EventResult, EventTeam } from './types';

type Props = { event: EventItem; categories: EventCategory[]; registrations: EventRegistration[]; teams: EventTeam[]; results: EventResult[] };

export default function Scoring({ event, categories, registrations, teams, results }: Props) {
    const firstCategory = categories.find((item) => item.scoring_mode === 'judged') ?? categories[0];
    const form = useForm({ event_category_id: String(firstCategory?.id ?? ''), event_registration_id: '', event_team_id: '', scores: (firstCategory?.criteria ?? []).map((criterion) => ({ event_category_criterion_id: criterion.id ?? 0, score: '', comment: '' })), award_title: '', status: 'published' });
    const selectedCategory = categories.find((item) => String(item.id) === String(form.data.event_category_id)) ?? firstCategory;
    function resetScores(categoryId: string) {
        const category = categories.find((item) => String(item.id) === categoryId);
        form.setData('event_category_id', categoryId);
        form.setData('scores', (category?.criteria ?? []).map((criterion) => ({ event_category_criterion_id: criterion.id ?? 0, score: '', comment: '' })));
    }
    function submit(e: FormEvent) { e.preventDefault(); form.post(`/events/${event.id}/scoring`, { preserveScroll: true }); }
    return (
        <AuthenticatedLayout title="Chấm điểm giám khảo" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Chấm điểm" />
            <section className="panel"><form className="grid-form" onSubmit={submit}><label><span>Nội dung</span><select value={form.data.event_category_id} onChange={(e) => resetScores(e.target.value)}>{categories.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label><label><span>Đăng ký</span><select value={form.data.event_registration_id} onChange={(e) => form.setData('event_registration_id', e.target.value)}><option value="">Chọn đăng ký</option>{registrations.filter((row) => String(row.event_category_id) === String(form.data.event_category_id)).map((row) => <option key={row.id} value={row.id}>{row.participant_name}</option>)}</select></label><label><span>Đội</span><select value={form.data.event_team_id} onChange={(e) => form.setData('event_team_id', e.target.value)}><option value="">Chọn đội nếu cần</option>{teams.filter((team) => String(team.event_category_id) === String(form.data.event_category_id)).map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</select></label><label><span>Giải dự kiến</span><input value={form.data.award_title} onChange={(e) => form.setData('award_title', e.target.value)} /></label>{form.data.scores.map((score, index) => <label key={score.event_category_criterion_id}><span>{selectedCategory?.criteria[index]?.name ?? 'Tiêu chí'}</span><input type="number" min="0" value={score.score} onChange={(e) => form.setData('scores', form.data.scores.map((item, i) => i === index ? { ...item, score: e.target.value } : item))} /></label>)}<button className="primary-button"><Save size={17} />Lưu điểm</button></form></section>
            <section className="table-panel"><table><thead><tr><th>Nội dung</th><th>Người/đội</th><th>Điểm TB</th><th>Hạng</th><th>Giải</th></tr></thead><tbody>{results.map((row) => <tr key={row.id}><td>{row.category_name}</td><td>{row.participant_name}</td><td>{row.score}</td><td>{row.rank}</td><td>{row.award_title}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
