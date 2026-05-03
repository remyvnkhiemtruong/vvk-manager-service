import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Summary = { id: number; semester_name: string | null; class_name: string | null; score: number; rating: string | null; bonus_points: number; minus_points: number; adjustment_points: number; homeroom_comment: string | null };
type RecordRow = { id: number; recorded_date: string; rule_code: string; rule_name: string; rule_type: string; points: number; description: string | null; status: string; recorded_by: string | null; approved_by: string | null; evidence_count: number };
type Props = {
    detail: {
        student: { id: number; student_code: string; full_name: string };
        summaries: Summary[];
        records: RecordRow[];
    };
};

export default function StudentTimeline({ detail }: Props) {
    const statusText: Record<string, string> = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', cancelled: 'Đã hủy' };

    return (
        <AuthenticatedLayout title={`Rèn luyện: ${detail.student.full_name}`} eyebrow={detail.student.student_code}>
            <Head title="Chi tiết rèn luyện học sinh" />

            <section className="two-column">
                {detail.summaries.map((summary) => (
                    <div className="panel" key={summary.id}>
                        <h2>{summary.semester_name ?? 'Học kỳ'}</h2>
                        <div className="metric-list">
                            <div><span>Lớp</span><strong>{summary.class_name ?? '-'}</strong></div>
                            <div><span>Điểm cuối</span><strong>{summary.score}</strong></div>
                            <div><span>Xếp loại</span><strong>{summary.rating ?? '-'}</strong></div>
                            <div><span>Cộng/Trừ/Điều chỉnh</span><strong>+{summary.bonus_points} / -{summary.minus_points} / {summary.adjustment_points}</strong></div>
                        </div>
                        {summary.homeroom_comment && <p>{summary.homeroom_comment}</p>}
                    </div>
                ))}
            </section>

            <section className="panel">
                <h2>Timeline sự kiện</h2>
                <div className="audit-list">
                    {detail.records.map((record) => (
                        <div className="audit-row" key={record.id}>
                            <div>
                                <strong>{record.recorded_date} · {record.rule_name}</strong>
                                <span>{record.description ?? '-'} · {statusText[record.status] ?? record.status}</span>
                                <span>Người ghi: {record.recorded_by ?? '-'} · Duyệt: {record.approved_by ?? '-'}</span>
                            </div>
                            <strong>{record.rule_type === 'bonus' ? '+' : ''}{record.points}</strong>
                        </div>
                    ))}
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
