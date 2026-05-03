import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventItem, EventLookups } from './types';

type Props = { lookups: EventLookups; event: EventItem | null };

export default function Form({ lookups, event }: Props) {
    const form = useForm({
        _method: event ? 'put' : '',
        school_year_id: String(event?.school_year_id ?? lookups.schoolYears[0]?.id ?? ''),
        semester_id: String(event?.semester_id ?? lookups.semesters[0]?.id ?? ''),
        title: event?.title ?? '',
        event_type: event?.event_type ?? Object.keys(lookups.types)[0] ?? '',
        organizer_unit: event?.organizer_unit ?? 'Đoàn trường/BTC',
        location: event?.location ?? '',
        target_audience: event?.target_audience ?? 'all_students',
        registration_modes: event?.registration_modes ?? ['individual', 'team', 'class'],
        starts_at: event?.starts_at ?? '',
        ends_at: event?.ends_at ?? '',
        description: event?.description ?? '',
        summary_report: event?.summary_report ?? '',
        conduct_points_per_student: String(event?.conduct_points_per_student ?? 0),
        class_competition_points: String(event?.class_competition_points ?? 0),
        status: event?.status ?? 'draft',
        plan_file: null as File | null,
    });

    function toggleMode(mode: string) {
        form.setData('registration_modes', form.data.registration_modes.includes(mode) ? form.data.registration_modes.filter((item) => item !== mode) : [...form.data.registration_modes, mode]);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(event ? `/events/${event.id}` : '/events', { forceFormData: true });
    }

    return (
        <AuthenticatedLayout title={event ? 'Sửa sự kiện' : 'Tạo sự kiện'} eyebrow="Kế hoạch hội thi/hội thao" actions={<Link className="secondary-button" href="/events"><ArrowLeft size={17} />Quay lại</Link>}>
            <Head title={event ? 'Sửa sự kiện' : 'Tạo sự kiện'} />
            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Năm học</span><select value={form.data.school_year_id} onChange={(e) => form.setData('school_year_id', e.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Học kỳ</span><select value={form.data.semester_id} onChange={(e) => form.setData('semester_id', e.target.value)}><option value="">Không gắn học kỳ</option>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label className="span-2"><span>Tên sự kiện</span><input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /></label>
                    <label><span>Loại sự kiện</span><select value={form.data.event_type} onChange={(e) => form.setData('event_type', e.target.value)}>{Object.entries(lookups.types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Đơn vị tổ chức</span><input value={form.data.organizer_unit} onChange={(e) => form.setData('organizer_unit', e.target.value)} /></label>
                    <label><span>Địa điểm</span><input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} /></label>
                    <label><span>Đối tượng</span><select value={form.data.target_audience} onChange={(e) => form.setData('target_audience', e.target.value)}>{Object.entries(lookups.targetAudiences).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Bắt đầu</span><input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></label>
                    <label><span>Kết thúc</span><input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></label>
                    <label><span>Điểm rèn luyện/HS</span><input type="number" min="0" value={form.data.conduct_points_per_student} onChange={(e) => form.setData('conduct_points_per_student', e.target.value)} /></label>
                    <label><span>Điểm thi đua lớp</span><input type="number" min="0" value={form.data.class_competition_points} onChange={(e) => form.setData('class_competition_points', e.target.value)} /></label>
                    <label><span>Trạng thái</span><select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>{Object.entries(lookups.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <div className="span-2 tag-list">
                        {Object.entries(lookups.registrationModes).map(([value, label]) => (
                            <label className="checkbox-row" key={value}><input type="checkbox" checked={form.data.registration_modes.includes(value)} onChange={() => toggleMode(value)} /><span>{label}</span></label>
                        ))}
                    </div>
                    <label className="span-2"><span>File kế hoạch/thể lệ</span><input type="file" onChange={(e) => form.setData('plan_file', e.target.files?.[0] ?? null)} /></label>
                    <label className="span-2"><span>Mô tả</span><textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></label>
                    <label className="span-2"><span>Báo cáo tổng kết</span><textarea value={form.data.summary_report} onChange={(e) => form.setData('summary_report', e.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Lưu sự kiện</button>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
