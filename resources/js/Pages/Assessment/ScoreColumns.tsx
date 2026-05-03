import { Head, router } from '@inertiajs/react';
import { Edit3, Lock, Plus, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Lookup = { id: number; name: string; code?: string; input_type?: string };
type ScoreColumn = {
    id: number;
    code: string;
    name: string;
    score_type_id: number;
    score_type_name?: string;
    order_index: number;
    max_score: string;
    lock_status: string;
    status: string;
    unlock_reason?: string | null;
};
type Scorebook = { filters: Record<string, number>; columns: ScoreColumn[]; can: { manage_columns: boolean } };

type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[]; subjects: Lookup[]; scoreTypes: Lookup[] };
    scorebook: Scorebook;
    filters: Record<string, number>;
};

const blank = {
    score_type_id: '',
    code: '',
    name: '',
    order_index: '1',
    max_score: '10',
    lock_status: 'open',
    status: 'active',
};

export default function ScoreColumns({ lookups, scorebook, filters }: Props) {
    const [form, setForm] = useState<Record<string, string>>(blank);
    const [editing, setEditing] = useState<ScoreColumn | null>(null);

    const query = {
        school_year_id: String(filters.school_year_id ?? ''),
        semester_id: String(filters.semester_id ?? ''),
        class_id: String(filters.class_id ?? ''),
        subject_id: String(filters.subject_id ?? ''),
    };

    function applyFilter(key: string, value: string) {
        router.get('/assessment/score-columns', { ...query, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        const payload = { ...query, ...form };

        if (editing) {
            router.put(`/assessment/score-columns/${editing.id}`, payload, { preserveScroll: true, onSuccess: reset });
            return;
        }

        router.post('/assessment/score-columns', payload, { preserveScroll: true, onSuccess: reset });
    }

    function reset() {
        setEditing(null);
        setForm(blank);
    }

    function edit(column: ScoreColumn) {
        setEditing(column);
        setForm({
            score_type_id: String(column.score_type_id),
            code: column.code,
            name: column.name,
            order_index: String(column.order_index),
            max_score: String(column.max_score),
            lock_status: column.lock_status,
            status: column.status,
        });
    }

    function approve(column: ScoreColumn) {
        router.post(`/assessment/score-columns/${column.id}/approve-unlock`, {}, { preserveScroll: true });
    }

    function reject(column: ScoreColumn) {
        const resolution_note = window.prompt('Lý do từ chối mở khóa') ?? '';
        router.post(`/assessment/score-columns/${column.id}/reject-unlock`, { resolution_note }, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Cấu hình cột điểm" eyebrow="Điểm học tập">
            <Head title="Cấu hình cột điểm" />

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

            <section className="panel">
                <form className="grid-form score-column-form" onSubmit={submit}>
                    <select value={form.score_type_id} onChange={(event) => setForm({ ...form, score_type_id: event.target.value })} required>
                        <option value="">Loại điểm</option>
                        {lookups.scoreTypes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                    </select>
                    <input value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value })} placeholder="Mã cột" required />
                    <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Tên cột" required />
                    <input type="number" value={form.order_index} onChange={(event) => setForm({ ...form, order_index: event.target.value })} placeholder="Thứ tự" required />
                    <input type="number" step="0.25" value={form.max_score} onChange={(event) => setForm({ ...form, max_score: event.target.value })} placeholder="Điểm tối đa" required />
                    <select value={form.lock_status} onChange={(event) => setForm({ ...form, lock_status: event.target.value })}>
                        <option value="open">Đang mở</option>
                        <option value="locked">Đã khóa</option>
                        <option value="unlock_requested">Yêu cầu mở khóa</option>
                    </select>
                    <button className="primary-button"><Plus size={17} />{editing ? 'Lưu cột' : 'Tạo cột'}</button>
                    {editing && <button className="secondary-button" type="button" onClick={reset}>Hủy</button>}
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Tên cột</th>
                            <th>Loại điểm</th>
                            <th>Thứ tự</th>
                            <th>Trạng thái khóa</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {scorebook.columns.map((column) => (
                            <tr key={column.id}>
                                <td>{column.code}</td>
                                <td>{column.name}</td>
                                <td>{column.score_type_name}</td>
                                <td>{column.order_index}</td>
                                <td>
                                    <strong>{column.lock_status}</strong>
                                    {column.unlock_reason && <div className="muted">{column.unlock_reason}</div>}
                                </td>
                                <td className="row-actions">
                                    <button className="icon-button" title="Sửa" onClick={() => edit(column)}><Edit3 size={16} /></button>
                                    {column.lock_status === 'open' && <button className="icon-button" title="Khóa" onClick={() => router.post(`/assessment/score-columns/${column.id}/lock`, {}, { preserveScroll: true })}><Lock size={16} /></button>}
                                    {column.lock_status === 'unlock_requested' && <button className="icon-button" title="Duyệt mở khóa" onClick={() => approve(column)}><Unlock size={16} /></button>}
                                    {column.lock_status === 'unlock_requested' && <button className="secondary-button compact" onClick={() => reject(column)}>Từ chối</button>}
                                    <button className="icon-button danger" title="Xóa" onClick={() => router.delete(`/assessment/score-columns/${column.id}`, { preserveScroll: true })}><Trash2 size={16} /></button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
