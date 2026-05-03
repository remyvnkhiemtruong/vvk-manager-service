import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Lookup = { id: number; name: string };
type Revision = {
    id: number;
    student_code?: string;
    student_name?: string;
    subject_name?: string;
    score_column?: string;
    before_values?: Record<string, unknown> | null;
    after_values?: Record<string, unknown> | null;
    changed_by?: string;
    reason?: string;
    created_at?: string;
};

type Props = {
    lookups: { semesters: Lookup[]; classes: Lookup[]; subjects: Lookup[] };
    revisions: Paginated<Revision>;
    filters: Record<string, string>;
};

function changed(beforeValues?: Record<string, unknown> | null, afterValues?: Record<string, unknown> | null) {
    const beforeScore = beforeValues?.score ?? beforeValues?.comment ?? '-';
    const afterScore = afterValues?.score ?? afterValues?.comment ?? '-';

    return `${beforeScore} → ${afterScore}`;
}

export default function Revisions({ lookups, revisions, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/assessment/revisions', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Lịch sử sửa điểm" eyebrow="Audit điểm học tập">
            <Head title="Lịch sử sửa điểm" />

            <section className="resource-toolbar academic-toolbar">
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
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Học sinh</th>
                            <th>Môn/Cột</th>
                            <th>Thay đổi</th>
                            <th>Người sửa</th>
                            <th>Lý do</th>
                        </tr>
                    </thead>
                    <tbody>
                        {revisions.data.map((revision) => (
                            <tr key={revision.id}>
                                <td>{revision.created_at}</td>
                                <td>{revision.student_code} · {revision.student_name}</td>
                                <td>{revision.subject_name} · {revision.score_column}</td>
                                <td>{changed(revision.before_values, revision.after_values)}</td>
                                <td>{revision.changed_by ?? '-'}</td>
                                <td>{revision.reason ?? '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="pagination">
                    {revisions.links.map((link, index) => link.url ? (
                        <Link key={`${link.label}-${index}`} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                        <span key={`${link.label}-${index}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
