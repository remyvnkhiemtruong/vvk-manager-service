import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Lookup = { id: number; name: string };
type AverageSubject = { subject_id: number; subject_name: string; average_score: number | null };
type AverageClass = { class_id: number; class_name: string | null; average_score: number | null };
type StudentRow = { student_id: number; student_code: string; student_name: string; class_name: string | null; average_score: number; delta_score?: number };

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[]; subjects: Lookup[] };
    filters: Record<string, string>;
    overview: { averageBySubject: AverageSubject[]; averageByClass: AverageClass[] };
    lowScoreStudents: Paginated<StudentRow>;
    improvedStudents: Paginated<StudentRow>;
};

export default function Reports({ lookups, filters, overview, lowScoreStudents, improvedStudents }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/assessment/reports', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Báo cáo điểm" eyebrow="Phân tích học tập">
            <Head title="Báo cáo điểm" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id ?? ''} onChange={(event) => applyFilter('school_year_id', event.target.value)}>
                    <option value="">Năm học</option>
                    {lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={filters.semester_id ?? ''} onChange={(event) => applyFilter('semester_id', event.target.value)}>
                    <option value="">Học kỳ</option>
                    {lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={filters.class_id ?? ''} onChange={(event) => applyFilter('class_id', event.target.value)}>
                    <option value="">Lớp</option>
                    {lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={filters.subject_id ?? ''} onChange={(event) => applyFilter('subject_id', event.target.value)}>
                    <option value="">Môn</option>
                    {lookups.subjects.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <input value={filters.threshold ?? '5'} onChange={(event) => applyFilter('threshold', event.target.value)} placeholder="Ngưỡng điểm thấp" />
            </section>

            <div className="two-column">
                <section className="panel">
                    <h2>Điểm trung bình theo môn</h2>
                    <div className="metric-list">
                        {overview.averageBySubject.map((row) => <div key={row.subject_id}><span>{row.subject_name}</span><strong>{row.average_score ?? '-'}</strong></div>)}
                    </div>
                </section>
                <section className="panel">
                    <h2>Điểm trung bình theo lớp</h2>
                    <div className="metric-list">
                        {overview.averageByClass.map((row) => <div key={row.class_id}><span>{row.class_name ?? '-'}</span><strong>{row.average_score ?? '-'}</strong></div>)}
                    </div>
                </section>
            </div>

            <section className="panel">
                <h2>Học sinh điểm thấp</h2>
                <table>
                    <thead><tr><th>Mã</th><th>Họ tên</th><th>Lớp</th><th>Điểm TB</th></tr></thead>
                    <tbody>{lowScoreStudents.data.map((row) => <tr key={row.student_id}><td>{row.student_code}</td><td>{row.student_name}</td><td>{row.class_name ?? '-'}</td><td>{row.average_score}</td></tr>)}</tbody>
                </table>
            </section>

            <section className="panel">
                <h2>Học sinh tiến bộ</h2>
                <table>
                    <thead><tr><th>Mã</th><th>Họ tên</th><th>Lớp</th><th>Điểm TB</th><th>Chênh lệch</th></tr></thead>
                    <tbody>{improvedStudents.data.map((row) => <tr key={row.student_id}><td>{row.student_code}</td><td>{row.student_name}</td><td>{row.class_name ?? '-'}</td><td>{row.average_score}</td><td>{row.delta_score ?? 0}</td></tr>)}</tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
