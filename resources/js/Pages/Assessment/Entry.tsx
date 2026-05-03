import { Head, router } from '@inertiajs/react';
import { Download, FileUp, Lock, Save, Unlock } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string; code?: string; school_year_id?: number; input_type?: string };
type Student = { id: number; student_code: string; full_name: string };
type ScoreColumn = { id: number; code: string; name: string; input_type: 'numeric' | 'comment'; max_score: string; lock_status: string; unlock_reason?: string | null };
type Cell = { id?: number; score?: string | number | null; comment?: string | null };
type Scorebook = {
    filters: Record<string, number>;
    students: Student[];
    columns: ScoreColumn[];
    scores: Record<string, Record<string, Cell>>;
    averages: Record<string, number | null>;
    can: { enter: boolean; manage_columns: boolean };
};

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[]; subjects: Lookup[] };
    scorebook: Scorebook;
};

function filter(scorebook: Scorebook, key: string) {
    return String(scorebook.filters[key] ?? '');
}

export default function Entry({ lookups, scorebook }: Props) {
    const [values, setValues] = useState<Record<string, Record<string, string>>>({});
    const [revisionReason, setRevisionReason] = useState('');
    const [importReason, setImportReason] = useState('');
    const [file, setFile] = useState<File | null>(null);

    useEffect(() => {
        const next: Record<string, Record<string, string>> = {};
        scorebook.students.forEach((student) => {
            next[student.id] = {};
            scorebook.columns.forEach((column) => {
                const cell = scorebook.scores[String(student.id)]?.[String(column.id)];
                next[student.id][column.id] = column.input_type === 'comment' ? String(cell?.comment ?? '') : String(cell?.score ?? '');
            });
        });
        setValues(next);
    }, [scorebook]);

    const query = useMemo(() => ({
        school_year_id: filter(scorebook, 'school_year_id'),
        semester_id: filter(scorebook, 'semester_id'),
        class_id: filter(scorebook, 'class_id'),
        subject_id: filter(scorebook, 'subject_id'),
    }), [scorebook]);

    function applyFilter(key: string, value: string) {
        router.get('/assessment/entry', { ...query, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function save() {
        const scores = scorebook.students.flatMap((student) => scorebook.columns.flatMap((column) => {
            const rawValue = values[String(student.id)]?.[String(column.id)] ?? '';
            const existing = scorebook.scores[String(student.id)]?.[String(column.id)];

            if (!existing && rawValue === '') {
                return [];
            }

            return [{
                student_id: student.id,
                score_column_id: column.id,
                [column.input_type === 'comment' ? 'comment' : 'score']: rawValue,
                status: 'submitted',
            }];
        }));

        router.put('/assessment/scores/bulk', { ...query, revision_reason: revisionReason, scores }, { preserveScroll: true });
    }

    function importScores(event: React.FormEvent) {
        event.preventDefault();

        if (!file) {
            return;
        }

        router.post('/assessment/scores/import', { ...query, file, revision_reason: importReason }, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    function exportScores() {
        window.location.href = `/assessment/scores/export?${new URLSearchParams(query).toString()}`;
    }

    function requestUnlock(column: ScoreColumn) {
        const reason = window.prompt(`Lý do yêu cầu mở khóa cột ${column.code}`);

        if (reason) {
            router.post(`/assessment/score-columns/${column.id}/request-unlock`, { reason }, { preserveScroll: true });
        }
    }

    return (
        <AuthenticatedLayout title="Nhập điểm" eyebrow="Điểm học tập">
            <Head title="Nhập điểm" />

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
                <button className="secondary-button" onClick={exportScores}><Download size={17} />Xuất Excel</button>
            </section>

            <section className="panel score-tools">
                <label>
                    <span>Lý do sửa điểm</span>
                    <input value={revisionReason} onChange={(event) => setRevisionReason(event.target.value)} placeholder="Bắt buộc khi sửa điểm đã có" />
                </label>
                <button className="primary-button" onClick={save} disabled={!scorebook.can.enter}>
                    <Save size={17} />Lưu bảng điểm
                </button>
                <form className="inline-upload" onSubmit={importScores}>
                    <input type="file" accept=".xlsx,.xls,.csv" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
                    <input value={importReason} onChange={(event) => setImportReason(event.target.value)} placeholder="Lý do import/cập nhật" />
                    <button className="secondary-button" disabled={!scorebook.can.enter}><FileUp size={17} />Import</button>
                </form>
            </section>

            <section className="table-panel scorebook-table">
                <table>
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Họ tên</th>
                            {scorebook.columns.map((column) => (
                                <th key={column.id}>
                                    <div>{column.code}</div>
                                    <small>{column.name}</small>
                                    {column.lock_status !== 'open' && (
                                        <button className="mini-button" onClick={() => requestUnlock(column)}>
                                            {column.lock_status === 'locked' ? <Lock size={13} /> : <Unlock size={13} />}
                                            {column.lock_status === 'locked' ? 'Đã khóa' : 'Đang yêu cầu'}
                                        </button>
                                    )}
                                </th>
                            ))}
                            <th>TB môn</th>
                        </tr>
                    </thead>
                    <tbody>
                        {scorebook.students.map((student) => (
                            <tr key={student.id}>
                                <td>{student.student_code}</td>
                                <td>{student.full_name}</td>
                                {scorebook.columns.map((column) => {
                                    const disabled = !scorebook.can.enter || column.lock_status !== 'open';
                                    const value = values[String(student.id)]?.[String(column.id)] ?? '';
                                    return (
                                        <td key={column.id}>
                                            {column.input_type === 'comment' ? (
                                                <textarea
                                                    className="score-comment"
                                                    value={value}
                                                    disabled={disabled}
                                                    onChange={(event) => setValues((current) => ({ ...current, [student.id]: { ...current[String(student.id)], [column.id]: event.target.value } }))}
                                                />
                                            ) : (
                                                <input
                                                    className="score-input"
                                                    type="number"
                                                    min="0"
                                                    max={column.max_score}
                                                    step="0.25"
                                                    value={value}
                                                    disabled={disabled}
                                                    onChange={(event) => setValues((current) => ({ ...current, [student.id]: { ...current[String(student.id)], [column.id]: event.target.value } }))}
                                                />
                                            )}
                                        </td>
                                    );
                                })}
                                <td><strong>{scorebook.averages[String(student.id)] ?? '-'}</strong></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
