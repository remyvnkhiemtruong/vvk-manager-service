import { Head, Link, router } from '@inertiajs/react';
import { Lock, RefreshCw, SlidersHorizontal, Unlock } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string; school_year_id?: number };
type SummaryRow = {
    id: number;
    student_id: number;
    student_code: string;
    student_name: string;
    class_name: string | null;
    base_score: number;
    bonus_points: number;
    minus_points: number;
    adjustment_points: number;
    score: number;
    rating: string | null;
    lock_status: string;
    homeroom_comment: string | null;
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[] };
    summary: { filters: Record<string, number>; rows: SummaryRow[]; can: { adjust: boolean; lock: boolean; unlock: boolean } };
};

function value(summary: Props['summary'], key: string) {
    return String(summary.filters[key] ?? '');
}

export default function ClassScores({ lookups, summary }: Props) {
    const filters = {
        school_year_id: value(summary, 'school_year_id'),
        semester_id: value(summary, 'semester_id'),
        class_id: value(summary, 'class_id'),
    };

    function applyFilter(key: string, nextValue: string) {
        router.get('/conduct/classes', { ...filters, [key]: nextValue }, { preserveState: true, preserveScroll: true });
    }

    function recompute() {
        router.post('/conduct/summaries/recompute', filters, { preserveScroll: true });
    }

    function adjust(row: SummaryRow) {
        const delta = window.prompt(`Điểm điều chỉnh cho ${row.student_name}`, '0');
        const reason = delta ? window.prompt('Lý do điều chỉnh') : null;
        if (delta && reason) router.put(`/conduct/summaries/${row.id}/adjust`, { points_delta: Number(delta), reason }, { preserveScroll: true });
    }

    function lock(row: SummaryRow) {
        router.post(`/conduct/summaries/${row.id}/lock`, {}, { preserveScroll: true });
    }

    function unlock(row: SummaryRow) {
        router.post(`/conduct/summaries/${row.id}/unlock`, {}, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Bảng điểm rèn luyện lớp" eyebrow="Tổng hợp học kỳ">
            <Head title="Bảng điểm rèn luyện" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id} onChange={(event) => applyFilter('school_year_id', event.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id} onChange={(event) => applyFilter('semester_id', event.target.value)}>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id} onChange={(event) => applyFilter('class_id', event.target.value)}>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <button className="secondary-button" onClick={recompute}><RefreshCw size={17} />Tính lại</button>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Lớp</th><th>Nền</th><th>Cộng</th><th>Trừ</th><th>Điều chỉnh</th><th>Điểm cuối</th><th>Xếp loại</th><th>Khóa</th><th></th></tr></thead>
                    <tbody>
                        {summary.rows.map((row) => (
                            <tr key={row.id}>
                                <td>{row.student_code}</td>
                                <td><Link href={`/conduct/students/${row.student_id}`}>{row.student_name}</Link></td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{row.base_score}</td>
                                <td>{row.bonus_points}</td>
                                <td>{row.minus_points}</td>
                                <td>{row.adjustment_points}</td>
                                <td><strong>{row.score}</strong></td>
                                <td>{row.rating ?? '-'}</td>
                                <td>{row.lock_status === 'locked' ? 'Đã khóa' : 'Đang mở'}</td>
                                <td className="row-actions">
                                    {summary.can.adjust && <button className="icon-button" onClick={() => adjust(row)} title="Điều chỉnh"><SlidersHorizontal size={16} /></button>}
                                    {summary.can.lock && row.lock_status !== 'locked' && <button className="icon-button" onClick={() => lock(row)} title="Khóa"><Lock size={16} /></button>}
                                    {summary.can.unlock && row.lock_status === 'locked' && <button className="icon-button" onClick={() => unlock(row)} title="Mở khóa"><Unlock size={16} /></button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
