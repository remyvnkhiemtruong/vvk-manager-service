import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Medal } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventAward, EventItem, EventLookups, EventResult } from './types';

type Props = { lookups: EventLookups; event: EventItem; results: EventResult[]; awards: EventAward[] };

export default function Awards({ lookups, event, results, awards }: Props) {
    const form = useForm({ event_result_id: String(results[0]?.id ?? ''), award_type: 'first', rank: '', title: '', description: '', awarded_date: '' });
    function submit(e: FormEvent) { e.preventDefault(); form.post(`/events/${event.id}/awards`, { preserveScroll: true, onSuccess: () => form.reset('title', 'description') }); }
    return (
        <AuthenticatedLayout title="Trao giải" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Trao giải" />
            <section className="panel"><form className="grid-form" onSubmit={submit}><label className="span-2"><span>Kết quả</span><select value={form.data.event_result_id} onChange={(e) => form.setData('event_result_id', e.target.value)}>{results.map((result) => <option key={result.id} value={result.id}>{result.category_name} - {result.participant_name} - hạng {result.rank ?? '-'}</option>)}</select></label><label><span>Loại giải</span><select value={form.data.award_type} onChange={(e) => form.setData('award_type', e.target.value)}>{Object.entries(lookups.awardTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label><span>Hạng</span><input type="number" value={form.data.rank} onChange={(e) => form.setData('rank', e.target.value)} /></label><label className="span-2"><span>Tên giải</span><input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /></label><label><span>Ngày trao</span><input type="date" value={form.data.awarded_date} onChange={(e) => form.setData('awarded_date', e.target.value)} /></label><label className="span-2"><span>Mô tả</span><textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></label><button className="primary-button"><Medal size={17} />Lưu giải</button></form></section>
            <section className="table-panel"><table><thead><tr><th>Nội dung</th><th>Người/đội nhận</th><th>Giải</th><th>Hạng</th><th>Ngày trao</th><th>Người nhận</th></tr></thead><tbody>{awards.map((award) => <tr key={award.id}><td>{award.category_name}</td><td>{award.participant_name}</td><td>{award.title}</td><td>{award.rank}</td><td>{award.awarded_date}</td><td>{award.recipient_count ?? 0}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
