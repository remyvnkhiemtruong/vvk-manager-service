import { Head, Link, useForm } from '@inertiajs/react';
import { Download, FileText, Trophy } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventAward, EventCategory, EventItem, EventMatch, EventRegistration, EventResult } from './types';

type Props = { event: EventItem; categories: EventCategory[]; registrations: EventRegistration[]; matches: EventMatch[]; results: EventResult[]; awards: EventAward[] };

export default function Summary({ event, categories, registrations, matches, results, awards }: Props) {
    const form = useForm({ summary_report: event.summary_report ?? '' });
    function submit(e: FormEvent) { e.preventDefault(); form.post(`/events/${event.id}/summarize`, { preserveScroll: true }); }
    return (
        <AuthenticatedLayout
            title="Tổng kết sự kiện"
            eyebrow={event.title}
            actions={<><Link className="secondary-button" href={`/events/${event.id}/exports/registrations?format=xlsx`}><Download size={17} />ĐK Excel</Link><Link className="secondary-button" href={`/events/${event.id}/exports/results?format=pdf`}><FileText size={17} />KQ PDF</Link></>}
        >
            <Head title="Tổng kết sự kiện" />
            <section className="stats-grid"><div className="stat-card tone-blue"><span>Nội dung</span><strong>{categories.length}</strong></div><div className="stat-card tone-green"><span>Đăng ký</span><strong>{registrations.length}</strong></div><div className="stat-card tone-amber"><span>Lịch/trận</span><strong>{matches.length}</strong></div><div className="stat-card tone-rose"><span>Giải</span><strong>{awards.length}</strong></div></section>
            <section className="panel"><form className="grid-form" onSubmit={submit}><label className="span-2"><span>Báo cáo tổng kết</span><textarea value={form.data.summary_report} onChange={(e) => form.setData('summary_report', e.target.value)} /></label><button className="primary-button"><Trophy size={17} />Tổng kết và cộng điểm</button></form></section>
            <section className="table-panel"><table><thead><tr><th>Nội dung</th><th>Người/đội</th><th>Điểm</th><th>Hạng</th><th>Giải</th><th>Điểm RL</th><th>Điểm lớp</th></tr></thead><tbody>{results.map((row) => <tr key={row.id}><td>{row.category_name}</td><td>{row.participant_name}</td><td>{row.score}</td><td>{row.rank}</td><td>{row.award_title}</td><td>{row.conduct_points ?? event.conduct_points_per_student}</td><td>{row.class_points ?? event.class_competition_points}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
