import { Head, Link, router, useForm } from '@inertiajs/react';
import { Link2, MoveRight, Trash2, Users } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { AcademicLookups, StudentRecord } from '../types';

type Enrollment = {
    id: number;
    class_id: number;
    class_name: string | null;
    school_year: string | null;
    semester: string | null;
    enrolled_at: string | null;
    left_at: string | null;
    status: string;
    note: string | null;
};

type Transfer = {
    id: number;
    from_class: string | null;
    to_class: string | null;
    school_year: string | null;
    semester: string | null;
    transferred_at: string | null;
    note: string | null;
};

type Props = {
    student: StudentRecord;
    enrollments: Enrollment[];
    transfers: Transfer[];
    lookups: AcademicLookups;
    can: { update: boolean; enroll: boolean; transfer: boolean };
};

export default function StudentShow({ student, enrollments, transfers, lookups, can }: Props) {
    const guardianForm = useForm({ guardian_id: '', relationship: '', is_primary: false });
    const enrollForm = useForm({ class_id: '', school_year_id: '', semester_id: '', enrolled_at: '', status: 'active', note: '' });
    const transferForm = useForm({ to_class_id: '', school_year_id: '', semester_id: '', transferred_at: new Date().toISOString().slice(0, 10), note: '' });

    function unlinkGuardian(id: number) {
        if (confirm('Bỏ liên kết phụ huynh này?')) {
            router.delete(`/academic/students/${student.id}/guardians/${id}`, { preserveScroll: true });
        }
    }

    return (
        <AuthenticatedLayout
            title={student.full_name}
            eyebrow={student.student_code}
            actions={can.update && <Link href={`/academic/students/${student.id}/edit`} className="primary-button compact">Sửa hồ sơ</Link>}
        >
            <Head title={student.full_name} />

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading">
                        <Users size={20} />
                        <h2>Thông tin học sinh</h2>
                    </div>
                    <div className="metric-list">
                        <div><span>Lớp hiện tại</span><strong>{student.current_class?.name ?? '-'}</strong></div>
                        <div><span>Ngày sinh</span><strong>{student.birth_date ?? '-'}</strong></div>
                        <div><span>Liên hệ</span><strong>{student.phone ?? student.email ?? '-'}</strong></div>
                        <div><span>Trạng thái</span><strong>{student.status}</strong></div>
                    </div>
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <Link2 size={20} />
                        <h2>Phụ huynh/người giám hộ</h2>
                    </div>
                    <div className="audit-list">
                        {student.guardians.map((guardian) => (
                            <div key={guardian.id} className="audit-row">
                                <div>
                                    <strong>{guardian.full_name}</strong>
                                    <span>{guardian.relationship ?? '-'} · {guardian.phone ?? guardian.email ?? '-'}</span>
                                </div>
                                {can.update && (
                                    <button className="icon-button danger" onClick={() => unlinkGuardian(guardian.id)} title="Bỏ liên kết">
                                        <Trash2 size={16} />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>

                    {can.update && (
                        <form
                            className="inline-form"
                            onSubmit={(event) => {
                                event.preventDefault();
                                guardianForm.post(`/academic/students/${student.id}/guardians`, { preserveScroll: true, onSuccess: () => guardianForm.reset() });
                            }}
                        >
                            <select value={guardianForm.data.guardian_id} onChange={(event) => guardianForm.setData('guardian_id', event.target.value)} required>
                                <option value="">Chọn phụ huynh</option>
                                {lookups.guardians.map((guardian) => (
                                    <option key={String(guardian.id)} value={String(guardian.id)}>{guardian.full_name}</option>
                                ))}
                            </select>
                            <input placeholder="Quan hệ" value={guardianForm.data.relationship} onChange={(event) => guardianForm.setData('relationship', event.target.value)} />
                            <label className="checkbox-row compact-check">
                                <input type="checkbox" checked={guardianForm.data.is_primary} onChange={(event) => guardianForm.setData('is_primary', event.target.checked)} />
                                <span>Chính</span>
                            </label>
                            <button className="secondary-button">Liên kết</button>
                        </form>
                    )}
                </section>
            </div>

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading"><h2>Lịch sử xếp lớp</h2></div>
                    <div className="audit-list">
                        {enrollments.map((item) => (
                            <div key={item.id} className="audit-row">
                                <div>
                                    <strong>{item.class_name}</strong>
                                    <span>{item.school_year} · {item.semester} · {item.status}</span>
                                </div>
                                <time>{item.enrolled_at ?? '-'}</time>
                            </div>
                        ))}
                    </div>

                    {can.enroll && (
                        <form
                            className="form-grid compact-form academic-card-form"
                            onSubmit={(event) => {
                                event.preventDefault();
                                enrollForm.post(`/academic/students/${student.id}/enrollments`, { preserveScroll: true, onSuccess: () => enrollForm.reset() });
                            }}
                        >
                            <select value={enrollForm.data.class_id} onChange={(event) => enrollForm.setData('class_id', event.target.value)} required>
                                <option value="">Lớp</option>
                                {lookups.classes.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <select value={enrollForm.data.school_year_id} onChange={(event) => enrollForm.setData('school_year_id', event.target.value)} required>
                                <option value="">Năm học</option>
                                {lookups.schoolYears.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <select value={enrollForm.data.semester_id} onChange={(event) => enrollForm.setData('semester_id', event.target.value)} required>
                                <option value="">Học kỳ</option>
                                {lookups.semesters.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <button className="secondary-button">Xếp lớp</button>
                        </form>
                    )}
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <MoveRight size={20} />
                        <h2>Lịch sử chuyển lớp</h2>
                    </div>
                    <div className="audit-list">
                        {transfers.map((item) => (
                            <div key={item.id} className="audit-row">
                                <div>
                                    <strong>{item.from_class ?? '-'} → {item.to_class}</strong>
                                    <span>{item.school_year} · {item.semester} · {item.note ?? '-'}</span>
                                </div>
                                <time>{item.transferred_at ?? '-'}</time>
                            </div>
                        ))}
                    </div>

                    {can.transfer && (
                        <form
                            className="form-grid compact-form academic-card-form"
                            onSubmit={(event) => {
                                event.preventDefault();
                                transferForm.post(`/academic/students/${student.id}/transfer`, { preserveScroll: true, onSuccess: () => transferForm.reset('to_class_id', 'note') });
                            }}
                        >
                            <select value={transferForm.data.to_class_id} onChange={(event) => transferForm.setData('to_class_id', event.target.value)} required>
                                <option value="">Lớp mới</option>
                                {lookups.classes.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <select value={transferForm.data.school_year_id} onChange={(event) => transferForm.setData('school_year_id', event.target.value)} required>
                                <option value="">Năm học</option>
                                {lookups.schoolYears.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <select value={transferForm.data.semester_id} onChange={(event) => transferForm.setData('semester_id', event.target.value)} required>
                                <option value="">Học kỳ</option>
                                {lookups.semesters.map((item) => <option key={String(item.id)} value={String(item.id)}>{item.name}</option>)}
                            </select>
                            <input type="date" value={transferForm.data.transferred_at} onChange={(event) => transferForm.setData('transferred_at', event.target.value)} required />
                            <input className="span-2" placeholder="Ghi chú" value={transferForm.data.note} onChange={(event) => transferForm.setData('note', event.target.value)} />
                            <button className="secondary-button">Chuyển lớp</button>
                        </form>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
