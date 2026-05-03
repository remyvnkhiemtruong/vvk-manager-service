import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string };
type Student = { id: number; student_code: string; full_name: string };
type ScoreColumn = { id: number; code: string; name: string; input_type: 'numeric' | 'comment' };
type Cell = { score?: string | number | null; comment?: string | null };
type Scorebook = {
    filters: Record<string, number>;
    students: Student[];
    columns: ScoreColumn[];
    scores: Record<string, Record<string, Cell>>;
    averages: Record<string, number | null>;
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[]; subjects: Lookup[] };
    scorebook: Scorebook;
};

function value(scorebook: Scorebook, key: string) {
    return String(scorebook.filters[key] ?? '');
}

export default function ClassScores({ lookups, scorebook }: Props) {
    const query = {
        school_year_id: value(scorebook, 'school_year_id'),
        semester_id: value(scorebook, 'semester_id'),
        class_id: value(scorebook, 'class_id'),
        subject_id: value(scorebook, 'subject_id'),
    };

    function applyFilter(key: string, nextValue: string) {
        router.get('/assessment/classes', { ...query, [key]: nextValue }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            title="Bảng điểm lớp"
            eyebrow="Tổng hợp theo lớp"
            actions={<Link className="secondary-button" href={`/assessment/scores/export?${new URLSearchParams(query).toString()}`}><Download size={17} />Xuất Excel</Link>}
        >
            <Head title="Bảng điểm lớp" />

            <section className="resource-toolbar academic-toolbar">
                <select value={query.school_year_id} onChange={(event) => applyFilter('school_year_id', event.target.value)}>
                    {lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={query.semester_id} onChange={(event) => applyFilter('semester_id', event.target.value)}>
                    {lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={query.class_id} onChange={(event) => applyFilter('class_id', event.target.value)}>
                    {lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <select value={query.subject_id} onChange={(event) => applyFilter('subject_id', event.target.value)}>
                    {lookups.subjects.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
            </section>

            <section className="table-panel scorebook-table">
                <table>
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Họ tên</th>
                            {scorebook.columns.map((column) => <th key={column.id}>{column.code}</th>)}
                            <th>TB môn</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        {scorebook.students.map((student) => (
                            <tr key={student.id}>
                                <td>{student.student_code}</td>
                                <td>{student.full_name}</td>
                                {scorebook.columns.map((column) => {
                                    const cell = scorebook.scores[String(student.id)]?.[String(column.id)];
                                    return <td key={column.id}>{column.input_type === 'comment' ? (cell?.comment ?? '-') : (cell?.score ?? '-')}</td>;
                                })}
                                <td><strong>{scorebook.averages[String(student.id)] ?? '-'}</strong></td>
                                <td><Link href={`/assessment/students/${student.id}`}>Xem</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
