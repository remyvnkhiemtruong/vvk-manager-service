import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, Plus, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { AcademicLookups, CanCrud, ClassRecord, PaginatedClasses } from '../types';

type Props = {
    classes: PaginatedClasses;
    lookups: AcademicLookups;
    filters: Record<string, string | undefined>;
    can: CanCrud;
};

const emptyClass = {
    school_year_id: '',
    grade_id: '',
    homeroom_teacher_id: '',
    name: '',
    code: '',
    room: '',
    capacity: '',
    status: 'active',
};

export default function ClassIndex({ classes, lookups, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<ClassRecord | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm({ ...emptyClass });

    function openCreate() {
        setEditing(null);
        form.setData({ ...emptyClass });
        setIsOpen(true);
    }

    function openEdit(record: ClassRecord) {
        setEditing(record);
        form.setData({
            school_year_id: String(record.school_year_id),
            grade_id: String(record.grade_id),
            homeroom_teacher_id: record.homeroom_teacher_id ? String(record.homeroom_teacher_id) : '',
            name: record.name,
            code: record.code ?? '',
            room: record.room ?? '',
            capacity: record.capacity ? String(record.capacity) : '',
            status: record.status,
        });
        setIsOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (editing) {
            form.put(`/academic/classes/${editing.id}`, { preserveScroll: true, onSuccess: () => setIsOpen(false) });
            return;
        }
        form.post('/academic/classes', { preserveScroll: true, onSuccess: () => setIsOpen(false) });
    }

    return (
        <AuthenticatedLayout title="Lớp học" eyebrow="Năm học, GVCN và sĩ số" actions={can.create && <button className="primary-button compact" onClick={openCreate}><Plus size={17} />Thêm lớp</button>}>
            <Head title="Lớp học" />

            <section className="resource-toolbar">
                <form className="search-box" onSubmit={(event) => { event.preventDefault(); router.get('/academic/classes', { search }, { preserveState: true }); }}>
                    <Search size={18} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm lớp, mã lớp, phòng" />
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Lớp</th>
                            <th>Năm học</th>
                            <th>Khối</th>
                            <th>GVCN</th>
                            <th>Sĩ số</th>
                            <th>Phòng</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classes.data.map((record) => (
                            <tr key={record.id}>
                                <td><Link href={`/academic/classes/${record.id}`}>{record.name}</Link><div className="muted">{record.code ?? '-'}</div></td>
                                <td>{record.school_year}</td>
                                <td>{record.grade}</td>
                                <td>{record.homeroom_teacher ?? '-'}</td>
                                <td>{record.active_students_count.toLocaleString('vi-VN')}{record.capacity ? `/${record.capacity}` : ''}</td>
                                <td>{record.room ?? '-'}</td>
                                <td className="row-actions">
                                    <Link className="secondary-button compact" href={`/academic/classes/${record.id}`}>Chi tiết</Link>
                                    {can.update && <button className="icon-button" onClick={() => openEdit(record)}><Edit3 size={16} /></button>}
                                    {can.delete && <button className="icon-button danger" onClick={() => confirm('Xóa lớp này?') && router.delete(`/academic/classes/${record.id}`, { preserveScroll: true })}><Trash2 size={16} /></button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="pagination">
                    {classes.links.map((link, index) => link.url ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </div>
            </section>

            {isOpen && (
                <div className="modal-backdrop">
                    <form className="modal" onSubmit={submit}>
                        <div className="modal-header">
                            <h2>{editing ? 'Sửa lớp' : 'Thêm lớp'}</h2>
                            <button type="button" className="icon-button" onClick={() => setIsOpen(false)}><X size={18} /></button>
                        </div>
                        <div className="form-grid">
                            <label><span>Năm học</span><select value={form.data.school_year_id} onChange={(event) => form.setData('school_year_id', event.target.value)} required><option value="">Chọn</option>{lookups.schoolYears.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}</select></label>
                            <label><span>Khối</span><select value={form.data.grade_id} onChange={(event) => form.setData('grade_id', event.target.value)} required><option value="">Chọn</option>{lookups.grades.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}</select></label>
                            <label><span>Tên lớp</span><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></label>
                            <label><span>Mã lớp</span><input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></label>
                            <label><span>GVCN</span><select value={form.data.homeroom_teacher_id} onChange={(event) => form.setData('homeroom_teacher_id', event.target.value)}><option value="">Chưa phân công</option>{lookups.teachers.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.full_name}</option>)}</select></label>
                            <label><span>Phòng</span><input value={form.data.room} onChange={(event) => form.setData('room', event.target.value)} /></label>
                            <label><span>Sức chứa</span><input type="number" value={form.data.capacity} onChange={(event) => form.setData('capacity', event.target.value)} /></label>
                            <label><span>Trạng thái</span><select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="active">Đang hoạt động</option><option value="inactive">Tạm dừng</option></select></label>
                        </div>
                        <div className="modal-actions">
                            <button type="button" className="secondary-button" onClick={() => setIsOpen(false)}>Hủy</button>
                            <button className="primary-button" disabled={form.processing}>Lưu</button>
                        </div>
                    </form>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
