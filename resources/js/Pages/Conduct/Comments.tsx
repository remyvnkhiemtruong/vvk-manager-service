import { Head, router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string };
type SummaryRow = { id: number; student_code: string; student_name: string; class_name: string | null; score: number; rating: string | null; homeroom_comment: string | null };
type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[] };
    summary: { filters: Record<string, number>; rows: SummaryRow[] };
};

export default function Comments({ lookups, summary }: Props) {
    const [comments, setComments] = useState<Record<number, string>>({});
    const filters = {
        school_year_id: String(summary.filters.school_year_id ?? ''),
        semester_id: String(summary.filters.semester_id ?? ''),
        class_id: String(summary.filters.class_id ?? ''),
    };

    useEffect(() => {
        setComments(Object.fromEntries(summary.rows.map((row) => [row.id, row.homeroom_comment ?? ''])));
    }, [summary.rows]);

    function applyFilter(key: string, value: string) {
        router.get('/conduct/comments', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function save(row: SummaryRow) {
        router.put(`/conduct/summaries/${row.id}/comment`, { homeroom_comment: comments[row.id] ?? '' }, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Nhận xét cuối kỳ" eyebrow="GVCN">
            <Head title="Nhận xét rèn luyện" />
            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id} onChange={(event) => applyFilter('school_year_id', event.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id} onChange={(event) => applyFilter('semester_id', event.target.value)}>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id} onChange={(event) => applyFilter('class_id', event.target.value)}>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Điểm</th><th>Xếp loại</th><th>Nhận xét</th><th></th></tr></thead>
                    <tbody>
                        {summary.rows.map((row) => (
                            <tr key={row.id}>
                                <td>{row.student_code}</td>
                                <td>{row.student_name}</td>
                                <td>{row.score}</td>
                                <td>{row.rating ?? '-'}</td>
                                <td><textarea value={comments[row.id] ?? ''} onChange={(event) => setComments((current) => ({ ...current, [row.id]: event.target.value }))} /></td>
                                <td><button className="icon-button" onClick={() => save(row)} title="Lưu"><Save size={16} /></button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
