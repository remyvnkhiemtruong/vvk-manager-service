import { Head, router } from '@inertiajs/react';
import { Lock, Unlock } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string };
type SummaryRow = { id: number; student_code: string; student_name: string; class_name: string | null; score: number; rating: string | null; lock_status: string };
type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[] };
    summary: { filters: Record<string, number>; rows: SummaryRow[]; can: { lock: boolean; unlock: boolean } };
};

export default function Locks({ lookups, summary }: Props) {
    const filters = {
        school_year_id: String(summary.filters.school_year_id ?? ''),
        semester_id: String(summary.filters.semester_id ?? ''),
        class_id: String(summary.filters.class_id ?? ''),
    };

    function applyFilter(key: string, value: string) {
        router.get('/conduct/locks', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Khóa/mở khóa rèn luyện" eyebrow="Chốt điểm cuối kỳ">
            <Head title="Khóa điểm rèn luyện" />
            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id} onChange={(event) => applyFilter('school_year_id', event.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id} onChange={(event) => applyFilter('semester_id', event.target.value)}>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id} onChange={(event) => applyFilter('class_id', event.target.value)}>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
            </section>
            <section className="table-panel">
                <table>
                    <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Lớp</th><th>Điểm</th><th>Xếp loại</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        {summary.rows.map((row) => (
                            <tr key={row.id}>
                                <td>{row.student_code}</td>
                                <td>{row.student_name}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{row.score}</td>
                                <td>{row.rating ?? '-'}</td>
                                <td>{row.lock_status === 'locked' ? 'Đã khóa' : 'Đang mở'}</td>
                                <td className="row-actions">
                                    {summary.can.lock && row.lock_status !== 'locked' && <button className="secondary-button" onClick={() => router.post(`/conduct/summaries/${row.id}/lock`, {}, { preserveScroll: true })}><Lock size={16} />Khóa</button>}
                                    {summary.can.unlock && row.lock_status === 'locked' && <button className="secondary-button" onClick={() => router.post(`/conduct/summaries/${row.id}/unlock`, {}, { preserveScroll: true })}><Unlock size={16} />Mở khóa</button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
