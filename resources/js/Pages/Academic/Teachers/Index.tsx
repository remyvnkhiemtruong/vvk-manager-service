import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, Plus, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { CanCrud, PaginatedTeachers, TeacherRecord } from '../types';

type Props = {
    teachers: PaginatedTeachers;
    filters: Record<string, string | undefined>;
    can: CanCrud;
};

const emptyTeacher = {
    teacher_code: '',
    staff_code: '',
    full_name: '',
    gender: '',
    birth_date: '',
    position: '',
    department: '',
    specialization: '',
    qualification: '',
    hire_date: '',
    phone: '',
    email: '',
    status: 'active',
};

export default function TeacherIndex({ teachers, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<TeacherRecord | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm({ ...emptyTeacher });

    function openCreate() {
        setEditing(null);
        form.setData({ ...emptyTeacher });
        form.clearErrors();
        setIsOpen(true);
    }

    function openEdit(teacher: TeacherRecord) {
        setEditing(teacher);
        form.setData({
            teacher_code: teacher.teacher_code,
            staff_code: teacher.staff_code ?? '',
            full_name: teacher.full_name,
            gender: teacher.gender ?? '',
            birth_date: teacher.birth_date ?? '',
            position: teacher.position ?? '',
            department: teacher.department ?? '',
            specialization: teacher.specialization ?? '',
            qualification: teacher.qualification ?? '',
            hire_date: teacher.hire_date ?? '',
            phone: teacher.phone ?? '',
            email: teacher.email ?? '',
            status: teacher.status,
        });
        setIsOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (editing) {
            form.put(`/academic/teachers/${editing.id}`, { preserveScroll: true, onSuccess: () => setIsOpen(false) });
            return;
        }
        form.post('/academic/teachers', { preserveScroll: true, onSuccess: () => setIsOpen(false) });
    }

    return (
        <AuthenticatedLayout
            title="Giáo viên"
            eyebrow="Hồ sơ giáo viên và nhân sự"
            actions={can.create && <button className="primary-button compact" onClick={openCreate}><Plus size={17} />Thêm giáo viên</button>}
        >
            <Head title="Giáo viên" />

            <section className="resource-toolbar">
                <form className="search-box" onSubmit={(event) => { event.preventDefault(); router.get('/academic/teachers', { search }, { preserveState: true }); }}>
                    <Search size={18} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm theo mã, họ tên, tổ/bộ phận" />
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Mã GV</th>
                            <th>Họ tên</th>
                            <th>Tổ/bộ phận</th>
                            <th>Chức vụ</th>
                            <th>Liên hệ</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {teachers.data.map((teacher) => (
                            <tr key={teacher.id}>
                                <td><code>{teacher.teacher_code}</code></td>
                                <td>{teacher.full_name}</td>
                                <td>{teacher.department ?? '-'}</td>
                                <td>{teacher.position ?? '-'}</td>
                                <td>{teacher.email ?? teacher.phone ?? '-'}</td>
                                <td>{teacher.status}</td>
                                <td className="row-actions">
                                    {can.update && <button className="icon-button" onClick={() => openEdit(teacher)}><Edit3 size={16} /></button>}
                                    {can.delete && <button className="icon-button danger" onClick={() => confirm('Xóa giáo viên này?') && router.delete(`/academic/teachers/${teacher.id}`, { preserveScroll: true })}><Trash2 size={16} /></button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="pagination">
                    {teachers.links.map((link, index) => link.url ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </div>
            </section>

            {isOpen && (
                <div className="modal-backdrop">
                    <form className="modal" onSubmit={submit}>
                        <div className="modal-header">
                            <h2>{editing ? 'Sửa giáo viên' : 'Thêm giáo viên'}</h2>
                            <button type="button" className="icon-button" onClick={() => setIsOpen(false)}><X size={18} /></button>
                        </div>
                        <div className="form-grid">
                            <label><span>Mã giáo viên</span><input value={form.data.teacher_code} onChange={(event) => form.setData('teacher_code', event.target.value)} required /></label>
                            <label><span>Họ tên</span><input value={form.data.full_name} onChange={(event) => form.setData('full_name', event.target.value)} required /></label>
                            <label><span>Tổ/bộ phận</span><input value={form.data.department} onChange={(event) => form.setData('department', event.target.value)} /></label>
                            <label><span>Chức vụ</span><input value={form.data.position} onChange={(event) => form.setData('position', event.target.value)} /></label>
                            <label><span>Điện thoại</span><input value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                            <label><span>Email</span><input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></label>
                            <label><span>Ngày tuyển dụng</span><input type="date" value={form.data.hire_date} onChange={(event) => form.setData('hire_date', event.target.value)} /></label>
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
