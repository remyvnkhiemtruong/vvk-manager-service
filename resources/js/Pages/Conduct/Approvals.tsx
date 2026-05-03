import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Lookup = { id: number; name: string; student_code?: string; full_name?: string };
type RecordRow = {
    id: number;
    class_name: string | null;
    student_code: string;
    student_name: string;
    rule_code: string;
    rule_name: string;
    points: number;
    recorded_date: string;
    description: string | null;
    status: string;
    recorded_by: string | null;
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[] };
    records: Paginated<RecordRow>;
    filters: Record<string, string>;
};

export default function Approvals({ lookups, records, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/conduct/approvals', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function approve(row: RecordRow) {
        const note = window.prompt('Ghi chú duyệt', '');
        router.post(`/conduct/records/${row.id}/approve`, { note }, { preserveScroll: true });
    }

    function reject(row: RecordRow) {
        const reason = window.prompt('Lý do từ chối');
        if (reason) router.post(`/conduct/records/${row.id}/reject`, { reason }, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Duyệt sự kiện rèn luyện" eyebrow="Điểm rèn luyện">
            <Head title="Duyệt rèn luyện" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id ?? ''} onChange={(event) => applyFilter('school_year_id', event.target.value)}><option value="">Năm học</option>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id ?? ''} onChange={(event) => applyFilter('semester_id', event.target.value)}><option value="">Học kỳ</option>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id ?? ''} onChange={(event) => applyFilter('class_id', event.target.value)}><option value="">Lớp</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Ngày</th><th>Học sinh</th><th>Lớp</th><th>Tiêu chí</th><th>Điểm</th><th>Mô tả</th><th>Người ghi</th><th></th></tr></thead>
                    <tbody>
                        {records.data.map((row) => (
                            <tr key={row.id}>
                                <td>{row.recorded_date}</td>
                                <td>{row.student_code}<br />{row.student_name}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td><code>{row.rule_code}</code><br />{row.rule_name}</td>
                                <td>{row.points}</td>
                                <td>{row.description ?? '-'}</td>
                                <td>{row.recorded_by ?? '-'}</td>
                                <td className="row-actions">
                                    <button className="icon-button" onClick={() => approve(row)} title="Duyệt"><Check size={16} /></button>
                                    <button className="icon-button danger" onClick={() => reject(row)} title="Từ chối"><X size={16} /></button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
