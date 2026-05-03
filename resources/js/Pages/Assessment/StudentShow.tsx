import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string };
type Score = {
    id: number;
    semester_name?: string;
    class_name?: string;
    subject_name?: string;
    score_column_code?: string;
    score_column_name?: string;
    input_type: 'numeric' | 'comment';
    score?: string | number | null;
    comment?: string | null;
    status: string;
};
type Detail = {
    student: { id: number; student_code: string; full_name: string };
    scores: Score[];
    averages: Record<string, number | null>;
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; subjects: Lookup[] };
    detail: Detail;
    filters: Record<string, string>;
};

export default function StudentShow({ lookups, detail, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get(`/assessment/students/${detail.student.id}`, { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Chi tiết điểm học sinh" eyebrow={`${detail.student.student_code} · ${detail.student.full_name}`}>
            <Head title="Chi tiết điểm học sinh" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id ?? ''} onChange={(event) => applyFilter('school_year_id', event.target.value)}>
                    <option value="">Năm học</option>
                    {lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={filters.semester_id ?? ''} onChange={(event) => applyFilter('semester_id', event.target.value)}>
                    <option value="">Học kỳ</option>
                    {lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={filters.subject_id ?? ''} onChange={(event) => applyFilter('subject_id', event.target.value)}>
                    <option value="">Môn học</option>
                    {lookups.subjects.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Học kỳ</th>
                            <th>Lớp</th>
                            <th>Môn</th>
                            <th>Cột điểm</th>
                            <th>Điểm/Nhận xét</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        {detail.scores.map((score) => (
                            <tr key={score.id}>
                                <td>{score.semester_name ?? '-'}</td>
                                <td>{score.class_name ?? '-'}</td>
                                <td>{score.subject_name ?? '-'}</td>
                                <td>{score.score_column_code ?? score.score_column_name ?? '-'}</td>
                                <td>{score.input_type === 'comment' ? (score.comment ?? '-') : (score.score ?? '-')}</td>
                                <td>{score.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
