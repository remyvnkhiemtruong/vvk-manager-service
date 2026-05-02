import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { AcademicLookups, StudentRecord } from '../types';

type Props = {
    student: StudentRecord | null;
    lookups: AcademicLookups;
};

export default function StudentForm({ student }: Props) {
    const form = useForm({
        student_code: student?.student_code ?? '',
        full_name: student?.full_name ?? '',
        gender: student?.gender ?? 'female',
        birth_date: student?.birth_date ?? '',
        phone: student?.phone ?? '',
        email: student?.email ?? '',
        address: student?.address ?? '',
        status: student?.status ?? 'active',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (student) {
            form.put(`/academic/students/${student.id}`);
            return;
        }

        form.post('/academic/students');
    }

    return (
        <AuthenticatedLayout title={student ? 'Sửa học sinh' : 'Thêm học sinh'} eyebrow="Hồ sơ học sinh">
            <Head title={student ? 'Sửa học sinh' : 'Thêm học sinh'} />

            <section className="panel">
                <form className="form-grid" onSubmit={submit}>
                    <label>
                        <span>Mã học sinh</span>
                        <input value={form.data.student_code} onChange={(event) => form.setData('student_code', event.target.value)} required />
                        {form.errors.student_code && <small className="field-error">{form.errors.student_code}</small>}
                    </label>

                    <label>
                        <span>Họ tên</span>
                        <input value={form.data.full_name} onChange={(event) => form.setData('full_name', event.target.value)} required />
                        {form.errors.full_name && <small className="field-error">{form.errors.full_name}</small>}
                    </label>

                    <label>
                        <span>Giới tính</span>
                        <select value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)}>
                            <option value="female">Nữ</option>
                            <option value="male">Nam</option>
                            <option value="other">Khác</option>
                        </select>
                    </label>

                    <label>
                        <span>Ngày sinh</span>
                        <input type="date" value={form.data.birth_date ?? ''} onChange={(event) => form.setData('birth_date', event.target.value)} />
                    </label>

                    <label>
                        <span>Điện thoại</span>
                        <input value={form.data.phone ?? ''} onChange={(event) => form.setData('phone', event.target.value)} />
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" value={form.data.email ?? ''} onChange={(event) => form.setData('email', event.target.value)} />
                    </label>

                    <label className="span-2">
                        <span>Địa chỉ</span>
                        <input value={form.data.address ?? ''} onChange={(event) => form.setData('address', event.target.value)} />
                    </label>

                    <label>
                        <span>Trạng thái</span>
                        <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            <option value="active">Đang học</option>
                            <option value="inactive">Tạm dừng</option>
                        </select>
                    </label>

                    <div className="modal-actions span-2">
                        <Link href={student ? `/academic/students/${student.id}` : '/academic/students'} className="secondary-button">Hủy</Link>
                        <button className="primary-button" disabled={form.processing}>{student ? 'Lưu thay đổi' : 'Tạo hồ sơ'}</button>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
