import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';

type StudentPortal = {
    id: number;
    student_code: string;
    full_name: string;
    scores: { subject_id: number; score: string; status: string; note?: string }[];
    conduct: { score: number; rating: string; status: string; note?: string }[];
    invoices: { invoice_no: string; total_amount: string; paid_amount: string; status: string; due_date?: string }[];
};

type Announcement = {
    title: string;
    body: string;
    audience: string;
    published_at: string | null;
};

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });

export default function Portal({ students, announcements, hasStudentContext }: { students: StudentPortal[]; announcements: Announcement[]; hasStudentContext: boolean }) {
    return (
        <AuthenticatedLayout title="Cổng phụ huynh/học sinh" eyebrow="Thông tin cá nhân hóa">
            <Head title="Cổng phụ huynh/học sinh" />
            {!hasStudentContext && <div className="empty-state">Tài khoản này chưa liên kết hồ sơ học sinh hoặc phụ huynh.</div>}

            <div className="portal-grid">
                {students.map((student) => (
                    <section className="panel" key={student.id}>
                        <h2>{student.full_name}</h2>
                        <p className="muted">{student.student_code}</p>
                        <div className="portal-section">
                            <h3>Điểm gần đây</h3>
                            {student.scores.map((score, index) => (
                                <div className="compact-row" key={`${score.subject_id}-${index}`}>
                                    <span>Môn #{score.subject_id}</span>
                                    <strong>{score.score}</strong>
                                </div>
                            ))}
                        </div>
                        <div className="portal-section">
                            <h3>Rèn luyện</h3>
                            {student.conduct.map((conduct, index) => (
                                <div className="compact-row" key={index}>
                                    <span>{conduct.rating}</span>
                                    <strong>{conduct.score}</strong>
                                </div>
                            ))}
                        </div>
                        <div className="portal-section">
                            <h3>Học phí</h3>
                            {student.invoices.map((invoice) => (
                                <div className="compact-row" key={invoice.invoice_no}>
                                    <span>{invoice.invoice_no} · {invoice.status}</span>
                                    <strong>{currency.format(Number(invoice.total_amount) - Number(invoice.paid_amount))}</strong>
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
            </div>

            <section className="panel">
                <h2>Thông báo</h2>
                <div className="announcement-list">
                    {announcements.map((announcement) => (
                        <article key={announcement.title}>
                            <strong>{announcement.title}</strong>
                            <span>{announcement.audience}</span>
                            <p>{announcement.body}</p>
                        </article>
                    ))}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

