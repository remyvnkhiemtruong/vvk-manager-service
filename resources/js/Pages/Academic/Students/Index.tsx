import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileUp, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import type { AcademicLookups, CanCrud, PaginatedStudents } from '../types';

type Props = {
    students: PaginatedStudents;
    lookups: AcademicLookups;
    filters: Record<string, string | undefined>;
    can: CanCrud;
};

export default function StudentIndex({ students, lookups, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [classId, setClassId] = useState(filters.class_id ?? '');
    const importForm = useForm<{ file: File | null }>({ file: null });

    function runSearch(event: React.FormEvent) {
        event.preventDefault();
        router.get('/academic/students', { search, class_id: classId }, { preserveState: true, preserveScroll: true });
    }

    function destroy(id: number) {
        if (confirm('Xóa hồ sơ học sinh này?')) {
            router.delete(`/academic/students/${id}`, { preserveScroll: true });
        }
    }

    function importStudents(event: React.FormEvent) {
        event.preventDefault();
        importForm.post('/academic/students/import', { preserveScroll: true, onSuccess: () => importForm.reset() });
    }

    return (
        <AuthenticatedLayout
            title="Học sinh"
            eyebrow="Quản lý hồ sơ và xếp lớp"
            actions={
                can.create && (
                    <Link href="/academic/students/create" className="primary-button compact">
                        <Plus size={17} />
                        <span>Thêm học sinh</span>
                    </Link>
                )
            }
        >
            <Head title="Học sinh" />

            <section className="resource-toolbar academic-toolbar">
                <form onSubmit={runSearch} className="search-box">
                    <Search size={18} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm theo mã, họ tên, email, điện thoại" />
                    <select value={classId} onChange={(event) => setClassId(event.target.value)}>
                        <option value="">Tất cả lớp</option>
                        {lookups.classes.map((item) => (
                            <option key={String(item.id)} value={String(item.id)}>{item.name}</option>
                        ))}
                    </select>
                    <button className="secondary-button">Lọc</button>
                </form>

                {can.create && (
                    <form onSubmit={importStudents} className="inline-upload">
                        <input type="file" accept=".xlsx,.xls,.csv" onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)} />
                        <button className="secondary-button" disabled={importForm.processing || !importForm.data.file}>
                            <FileUp size={17} />
                            Import Excel
                        </button>
                    </form>
                )}
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Họ tên</th>
                            <th>Lớp hiện tại</th>
                            <th>Phụ huynh</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.data.map((student) => (
                            <tr key={student.id}>
                                <td><code>{student.student_code}</code></td>
                                <td>
                                    <Link href={`/academic/students/${student.id}`}>{student.full_name}</Link>
                                    <div className="muted">{student.email ?? student.phone ?? '-'}</div>
                                </td>
                                <td>{student.current_class?.name ?? '-'}</td>
                                <td>{student.guardians.map((guardian) => guardian.full_name).join(', ') || '-'}</td>
                                <td>{student.status}</td>
                                <td className="row-actions">
                                    <Link className="secondary-button compact" href={`/academic/students/${student.id}`}>Chi tiết</Link>
                                    {student.current_class?.id && (
                                        <a className="icon-button" title="Export lớp" href={`/academic/classes/${student.current_class.id}/students/export`}>
                                            <Download size={16} />
                                        </a>
                                    )}
                                    {can.delete && (
                                        <button className="icon-button danger" title="Xóa" onClick={() => destroy(student.id)}>
                                            <Trash2 size={16} />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="pagination">
                    {students.links.map((link, index) => (
                        link.url ? (
                            <Link key={`${link.label}-${index}`} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span key={`${link.label}-${index}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        )
                    ))}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
