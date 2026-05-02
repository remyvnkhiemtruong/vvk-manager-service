import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, School, Trash2, UserRound } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { AcademicLookups, ClassRecord, StudentRecord } from '../types';

type Assignment = {
    id: number;
    teacher_id: number;
    teacher_name: string | null;
    subject_id: number;
    subject_name: string | null;
    semester_id: number;
    semester_name: string | null;
    status: string;
};

type Props = {
    classRecord: ClassRecord;
    students: StudentRecord[];
    teachingAssignments: Assignment[];
    lookups: AcademicLookups;
    can: { update: boolean; assign: boolean; export: boolean };
};

export default function ClassShow({ classRecord, students, teachingAssignments, lookups, can }: Props) {
    const homeroomForm = useForm({ homeroom_teacher_id: classRecord.homeroom_teacher_id ? String(classRecord.homeroom_teacher_id) : '' });
    const assignmentForm = useForm({
        school_year_id: String(classRecord.school_year_id),
        semester_id: '',
        teacher_id: '',
        subject_id: '',
        status: 'active',
    });

    return (
        <AuthenticatedLayout
            title={classRecord.name}
            eyebrow={`${classRecord.school_year ?? ''} · ${classRecord.grade ?? ''}`}
            actions={can.export && <a className="primary-button compact" href={`/academic/classes/${classRecord.id}/students/export`}><Download size={17} />Export danh sách</a>}
        >
            <Head title={classRecord.name} />

            <div className="stats-grid">
                <div className="stat-card tone-blue"><span>Sĩ số</span><strong>{students.length.toLocaleString('vi-VN')}</strong></div>
                <div className="stat-card tone-green"><span>GVCN</span><strong>{classRecord.homeroom_teacher ?? '-'}</strong></div>
                <div className="stat-card tone-amber"><span>Phòng</span><strong>{classRecord.room ?? '-'}</strong></div>
                <div className="stat-card tone-rose"><span>Sức chứa</span><strong>{classRecord.capacity ?? '-'}</strong></div>
            </div>

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading">
                        <UserRound size={20} />
                        <h2>Giáo viên chủ nhiệm</h2>
                    </div>
                    {can.update && (
                        <form className="inline-form" onSubmit={(event) => { event.preventDefault(); homeroomForm.put(`/academic/classes/${classRecord.id}/homeroom`, { preserveScroll: true }); }}>
                            <select value={homeroomForm.data.homeroom_teacher_id} onChange={(event) => homeroomForm.setData('homeroom_teacher_id', event.target.value)}>
                                <option value="">Chưa phân công</option>
                                {lookups.teachers.map((teacher) => <option key={String(teacher.id)} value={String(teacher.id)}>{teacher.full_name}</option>)}
                            </select>
                            <button className="secondary-button">Lưu GVCN</button>
                        </form>
                    )}
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <School size={20} />
                        <h2>Giáo viên bộ môn</h2>
                    </div>
                    <div className="audit-list">
                        {teachingAssignments.map((item) => (
                            <div key={item.id} className="audit-row">
                                <div>
                                    <strong>{item.subject_name}</strong>
                                    <span>{item.teacher_name} · {item.semester_name} · {item.status}</span>
                                </div>
                                {can.assign && <button className="icon-button danger" onClick={() => confirm('Xóa phân công này?') && router.delete(`/academic/teaching-assignments/${item.id}`, { preserveScroll: true })}><Trash2 size={16} /></button>}
                            </div>
                        ))}
                    </div>

                    {can.assign && (
                        <form className="form-grid compact-form academic-card-form" onSubmit={(event) => { event.preventDefault(); assignmentForm.post(`/academic/classes/${classRecord.id}/teaching-assignments`, { preserveScroll: true, onSuccess: () => assignmentForm.reset('teacher_id', 'subject_id', 'semester_id') }); }}>
                            <select value={assignmentForm.data.teacher_id} onChange={(event) => assignmentForm.setData('teacher_id', event.target.value)} required>
                                <option value="">Giáo viên</option>
                                {lookups.teachers.map((teacher) => <option key={String(teacher.id)} value={String(teacher.id)}>{teacher.full_name}</option>)}
                            </select>
                            <select value={assignmentForm.data.subject_id} onChange={(event) => assignmentForm.setData('subject_id', event.target.value)} required>
                                <option value="">Môn học</option>
                                {lookups.subjects.map((subject) => <option key={String(subject.id)} value={String(subject.id)}>{subject.name}</option>)}
                            </select>
                            <select value={assignmentForm.data.semester_id} onChange={(event) => assignmentForm.setData('semester_id', event.target.value)} required>
                                <option value="">Học kỳ</option>
                                {lookups.semesters.map((semester) => <option key={String(semester.id)} value={String(semester.id)}>{semester.name}</option>)}
                            </select>
                            <button className="secondary-button">Thêm phân công</button>
                        </form>
                    )}
                </section>
            </div>

            <section className="panel profile-password-panel">
                <div className="panel-heading"><h2>Danh sách học sinh</h2></div>
                <div className="table-panel embedded-table">
                    <table>
                        <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Giới tính</th><th>Liên hệ</th><th>Phụ huynh</th></tr></thead>
                        <tbody>
                            {students.map((student) => (
                                <tr key={student.id}>
                                    <td><code>{student.student_code}</code></td>
                                    <td><Link href={`/academic/students/${student.id}`}>{student.full_name}</Link></td>
                                    <td>{student.gender}</td>
                                    <td>{student.phone ?? student.email ?? '-'}</td>
                                    <td>{student.guardians.map((guardian) => guardian.full_name).join(', ') || '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
