import { Head, router, useForm } from '@inertiajs/react';
import { Check, Save, X } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Lookup = { id: number; name: string; code?: string; school_year_id?: number; full_name?: string; student_code?: string; points?: number; rule_type?: string };
type RecordRow = {
    id: number;
    class_name: string | null;
    student_code: string;
    student_name: string;
    rule_code: string;
    rule_name: string;
    rule_type: string;
    points: number;
    recorded_date: string;
    description: string | null;
    status: string;
    recorded_by: string | null;
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[]; students: Lookup[]; rules: Lookup[] };
    records: Paginated<RecordRow>;
    filters: Record<string, string>;
};

export default function Records({ lookups, records, filters }: Props) {
    const form = useForm({
        school_year_id: filters.school_year_id ?? String(lookups.schoolYears[0]?.id ?? ''),
        semester_id: filters.semester_id ?? String(lookups.semesters[0]?.id ?? ''),
        class_id: filters.class_id ?? String(lookups.classes[0]?.id ?? ''),
        student_id: '',
        conduct_rule_id: '',
        points: '',
        recorded_date: new Date().toISOString().slice(0, 10),
        description: '',
        evidences: [] as File[],
    });

    function applyFilter(key: string, value: string) {
        router.get('/conduct/records', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/conduct/records', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('student_id', 'conduct_rule_id', 'points', 'description', 'evidences') });
    }

    function approve(row: RecordRow) {
        router.post(`/conduct/records/${row.id}/approve`, {}, { preserveScroll: true });
    }

    function reject(row: RecordRow) {
        const reason = window.prompt('Lý do từ chối');
        if (reason) router.post(`/conduct/records/${row.id}/reject`, { reason }, { preserveScroll: true });
    }

    const statusText: Record<string, string> = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', cancelled: 'Đã hủy' };

    return (
        <AuthenticatedLayout title="Ghi nhận rèn luyện" eyebrow="Vi phạm và khen thưởng">
            <Head title="Ghi nhận rèn luyện" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id ?? ''} onChange={(event) => applyFilter('school_year_id', event.target.value)}><option value="">Năm học</option>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id ?? ''} onChange={(event) => applyFilter('semester_id', event.target.value)}><option value="">Học kỳ</option>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id ?? ''} onChange={(event) => applyFilter('class_id', event.target.value)}><option value="">Lớp</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.status ?? ''} onChange={(event) => applyFilter('status', event.target.value)}><option value="">Trạng thái</option><option value="pending">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="rejected">Từ chối</option><option value="cancelled">Đã hủy</option></select>
            </section>

            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Năm học</span><select value={form.data.school_year_id} onChange={(event) => form.setData('school_year_id', event.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Học kỳ</span><select value={form.data.semester_id} onChange={(event) => form.setData('semester_id', event.target.value)}>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Lớp</span><select value={form.data.class_id} onChange={(event) => form.setData('class_id', event.target.value)}>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Học sinh</span><select value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)}><option value="">Chọn học sinh</option>{lookups.students.map((item) => <option key={item.id} value={item.id}>{item.student_code} - {item.full_name}</option>)}</select></label>
                    <label><span>Tiêu chí</span><select value={form.data.conduct_rule_id} onChange={(event) => {
                        const rule = lookups.rules.find((item) => String(item.id) === event.target.value);
                        form.setData('conduct_rule_id', event.target.value);
                        form.setData('points', String(rule?.points ?? ''));
                    }}><option value="">Chọn tiêu chí</option>{lookups.rules.map((item) => <option key={item.id} value={item.id}>{item.code} - {item.name}</option>)}</select></label>
                    <label><span>Điểm</span><input type="number" value={form.data.points} onChange={(event) => form.setData('points', event.target.value)} /></label>
                    <label><span>Ngày xảy ra</span><input type="date" value={form.data.recorded_date} onChange={(event) => form.setData('recorded_date', event.target.value)} /></label>
                    <label><span>Minh chứng</span><input type="file" multiple onChange={(event) => form.setData('evidences', Array.from(event.target.files ?? []))} /></label>
                    <label className="span-2"><span>Mô tả</span><textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Lưu sự kiện</button>
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Ngày</th><th>Học sinh</th><th>Lớp</th><th>Tiêu chí</th><th>Điểm</th><th>Trạng thái</th><th>Người ghi</th><th></th></tr></thead>
                    <tbody>
                        {records.data.map((row) => (
                            <tr key={row.id}>
                                <td>{row.recorded_date}</td>
                                <td>{row.student_code}<br />{row.student_name}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td><code>{row.rule_code}</code><br />{row.rule_name}</td>
                                <td>{row.points}</td>
                                <td>{statusText[row.status] ?? row.status}</td>
                                <td>{row.recorded_by ?? '-'}</td>
                                <td className="row-actions">
                                    {row.status === 'pending' && <button className="icon-button" onClick={() => approve(row)} title="Duyệt"><Check size={16} /></button>}
                                    {row.status === 'pending' && <button className="icon-button danger" onClick={() => reject(row)} title="Từ chối"><X size={16} /></button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
