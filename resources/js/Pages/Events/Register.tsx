import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventLookups, EventRegistration } from './types';

type Props = { lookups: EventLookups; event: EventItem; categories: EventCategory[]; registrations: { data: EventRegistration[] } | EventRegistration[] };

export default function Register({ lookups, event, categories, registrations }: Props) {
    const rows = Array.isArray(registrations) ? registrations : registrations.data;
    const form = useForm({ event_category_id: String(categories[0]?.id ?? ''), registration_type: 'individual', class_id: '', student_id: '', participant_name: '', member_ids: [] as number[], note: '' });

    function toggleStudent(id: number) {
        form.setData('member_ids', form.data.member_ids.includes(id) ? form.data.member_ids.filter((item) => item !== id) : [...form.data.member_ids, id]);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/events/${event.id}/registrations`, { preserveScroll: true, onSuccess: () => form.reset('student_id', 'participant_name', 'member_ids', 'note') });
    }

    return (
        <AuthenticatedLayout title="Đăng ký tham gia" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Đăng ký sự kiện" />
            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Nội dung</span><select value={form.data.event_category_id} onChange={(e) => form.setData('event_category_id', e.target.value)}>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Loại đăng ký</span><select value={form.data.registration_type} onChange={(e) => form.setData('registration_type', e.target.value)}>{Object.entries(lookups.registrationModes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Lớp</span><select value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}><option value="">Chọn lớp nếu cần</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Học sinh/đội trưởng</span><select value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}><option value="">Chọn học sinh</option>{lookups.students.map((item) => <option key={item.id} value={item.id}>{item.student_code} {item.full_name}</option>)}</select></label>
                    <label className="span-2"><span>Tên đội/tên đăng ký</span><input value={form.data.participant_name} onChange={(e) => form.setData('participant_name', e.target.value)} /></label>
                    <div className="span-2 tag-list">{lookups.students.slice(0, 24).map((student) => <label className="checkbox-row" key={student.id}><input type="checkbox" checked={form.data.member_ids.includes(student.id)} onChange={() => toggleStudent(student.id)} /><span>{student.student_code} {student.full_name}</span></label>)}</div>
                    <label className="span-2"><span>Ghi chú</span><textarea value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} /></label>
                    <button className="primary-button"><Send size={17} />Gửi đăng ký</button>
                </form>
            </section>
            <section className="table-panel">
                <table><thead><tr><th>Nội dung</th><th>Tên đăng ký</th><th>Lớp</th><th>Thành viên</th><th>Trạng thái</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id}><td>{row.category_name}</td><td>{row.participant_name}</td><td>{row.class_name ?? '-'}</td><td>{row.members?.length ?? 0}</td><td>{row.status_label}</td></tr>)}</tbody></table>
            </section>
        </AuthenticatedLayout>
    );
}
