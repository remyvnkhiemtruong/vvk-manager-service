import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Option = { id: number; name: string };
type StudentRow = { student_id: number; student_code: string; student_name: string; class_name: string | null; average_score: number; delta_score?: number };
type Paginated<T> = { data: T[] };

type Props = {
    filters: Record<string, string>;
    options: { schoolYears: Option[]; semesters: Option[]; classes: Option[]; subjects: Option[] };
    overview: { averageBySubject: Array<{ subject_id: number; subject_name: string; average_score: number }>; averageByClass: Array<{ class_id: number; class_name: string; average_score: number }> };
    lowScoreStudents: Paginated<StudentRow>;
    improvedStudents: Paginated<StudentRow>;
};

export default function ReportsIndex({ filters, options, overview, lowScoreStudents, improvedStudents }: Props) {
    const applyFilter = (key: string, value: string) => router.get('/reports', { ...filters, [key]: value }, { preserveState: true });

    return (
        <AuthenticatedLayout title="Báo cáo điểm" eyebrow="Phân tích học tập">
            <Head title="Báo cáo điểm" />
            <div className="panel" style={{ marginBottom: 16 }}>
                <h2>Bộ lọc</h2>
                <div className="grid-form">
                    <select value={filters.school_year_id ?? ''} onChange={(e) => applyFilter('school_year_id', e.target.value)}><option value="">Năm học</option>{options.schoolYears.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
                    <select value={filters.semester_id ?? ''} onChange={(e) => applyFilter('semester_id', e.target.value)}><option value="">Học kỳ</option>{options.semesters.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
                    <select value={filters.class_id ?? ''} onChange={(e) => applyFilter('class_id', e.target.value)}><option value="">Lớp</option>{options.classes.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
                    <select value={filters.subject_id ?? ''} onChange={(e) => applyFilter('subject_id', e.target.value)}><option value="">Môn</option>{options.subjects.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
                </div>
            </div>
            <div className="two-column">
                <section className="panel"><h2>Điểm trung bình theo môn</h2>{overview.averageBySubject.map((row) => <div key={row.subject_id}><span>{row.subject_name}</span><strong>{row.average_score}</strong></div>)}</section>
                <section className="panel"><h2>Điểm trung bình theo lớp</h2>{overview.averageByClass.map((row) => <div key={row.class_id}><span>{row.class_name}</span><strong>{row.average_score}</strong></div>)}</section>
            </div>
            <section className="panel"><h2>Học sinh điểm thấp</h2><table><thead><tr><th>Mã</th><th>Họ tên</th><th>Lớp</th><th>Điểm TB</th></tr></thead><tbody>{lowScoreStudents.data.map((x) => <tr key={x.student_id}><td>{x.student_code}</td><td>{x.student_name}</td><td>{x.class_name ?? '-'}</td><td>{x.average_score}</td></tr>)}</tbody></table></section>
            <section className="panel"><h2>Học sinh tiến bộ</h2><table><thead><tr><th>Mã</th><th>Họ tên</th><th>Lớp</th><th>Điểm TB</th><th>Chênh lệch</th></tr></thead><tbody>{improvedStudents.data.map((x) => <tr key={x.student_id}><td>{x.student_code}</td><td>{x.student_name}</td><td>{x.class_name ?? '-'}</td><td>{x.average_score}</td><td>{x.delta_score ?? 0}</td></tr>)}</tbody></table></section>
        </AuthenticatedLayout>
    );
}
