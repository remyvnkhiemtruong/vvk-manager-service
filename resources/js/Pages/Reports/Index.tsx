import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Props = {
    summary: {
        students: number;
        averageScore: number;
        averageConduct: number;
        events: number;
        unpaidAmount: number;
    };
    invoiceStatus: Record<string, number>;
    eventTypes: Record<string, number>;
};

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });

export default function ReportsIndex({ summary, invoiceStatus, eventTypes }: Props) {
    return (
        <AuthenticatedLayout title="Báo cáo" eyebrow="Tổng hợp nhanh">
            <Head title="Báo cáo" />
            <div className="stats-grid">
                <div className="stat-card tone-blue"><span>Học sinh</span><strong>{summary.students}</strong></div>
                <div className="stat-card tone-green"><span>Điểm TB</span><strong>{summary.averageScore}</strong></div>
                <div className="stat-card tone-amber"><span>Rèn luyện TB</span><strong>{summary.averageConduct}</strong></div>
                <div className="stat-card tone-rose"><span>Công nợ</span><strong>{currency.format(summary.unpaidAmount)}</strong></div>
            </div>

            <div className="two-column">
                <section className="panel">
                    <h2>Trạng thái học phí</h2>
                    <div className="bar-list">
                        {Object.entries(invoiceStatus).map(([key, value]) => (
                            <div key={key}><span>{key}</span><strong>{value}</strong></div>
                        ))}
                    </div>
                </section>
                <section className="panel">
                    <h2>Loại hoạt động</h2>
                    <div className="bar-list">
                        {Object.entries(eventTypes).map(([key, value]) => (
                            <div key={key}><span>{key}</span><strong>{value}</strong></div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

