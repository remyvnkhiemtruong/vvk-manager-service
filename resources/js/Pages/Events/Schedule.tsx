import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventMatch } from './types';

type ScheduleRow = { id: number; category_name?: string; name?: string; starts_at?: string; ends_at?: string; location?: string; status: string };
type Props = { event: EventItem; categories: EventCategory[]; schedules: ScheduleRow[]; matches: EventMatch[] };

export default function Schedule({ event, categories, schedules, matches }: Props) {
    const form = useForm({ event_category_id: String(categories[0]?.id ?? ''), name: '', schedule_type: 'match', starts_at: event.starts_at ?? '', ends_at: event.ends_at ?? '', location: event.location ?? '', status: 'scheduled' });
    function submit(e: FormEvent) { e.preventDefault(); form.post(`/events/${event.id}/schedules`, { preserveScroll: true, onSuccess: () => form.reset('name') }); }

    return (
        <AuthenticatedLayout title="Lịch thi đấu" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Lịch thi đấu" />
            <section className="panel"><form className="grid-form" onSubmit={submit}><label><span>Nội dung</span><select value={form.data.event_category_id} onChange={(e) => form.setData('event_category_id', e.target.value)}>{categories.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label><label className="span-2"><span>Tên lịch</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label><label><span>Bắt đầu</span><input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></label><label><span>Kết thúc</span><input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></label><label><span>Địa điểm</span><input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} /></label><button className="primary-button"><Save size={17} />Lưu lịch</button></form></section>
            <section className="table-panel"><table><thead><tr><th>Nội dung</th><th>Tên lịch</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th></tr></thead><tbody>{schedules.map((row) => <tr key={row.id}><td>{row.category_name}</td><td>{row.name}</td><td>{row.starts_at} - {row.ends_at}</td><td>{row.location}</td><td>{row.status}</td></tr>)}</tbody></table></section>
            <section className="table-panel"><table><thead><tr><th>Trận</th><th>Bảng</th><th>Đội 1</th><th>Đội 2</th><th>Thời gian</th><th>Địa điểm</th></tr></thead><tbody>{matches.map((match) => <tr key={match.id}><td>{match.category_name}</td><td>{match.group_code}</td><td>{match.home_team_name}</td><td>{match.away_team_name}</td><td>{match.starts_at}</td><td>{match.location}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
