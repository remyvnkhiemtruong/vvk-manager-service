import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileText, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Campaign, Registration, ResultRow } from './types';

type Props = {
    campaign: Campaign;
    ranking: { rows: ResultRow[] };
    registrations: Registration[];
};

export default function Summary({ campaign, ranking, registrations }: Props) {
    const form = useForm({ summary_report: campaign.summary_report ?? '' });

    function summarize(event: FormEvent) {
        event.preventDefault();
        form.post(`/campaigns/${campaign.id}/summarize`, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            title="Tổng kết phong trào"
            eyebrow={campaign.title}
            actions={<><Link className="secondary-button" href={`/campaigns/${campaign.id}/exports/participants?format=xlsx`}><Download size={17} />DS Excel</Link><Link className="secondary-button" href={`/campaigns/${campaign.id}/exports/results?format=pdf`}><FileText size={17} />KQ PDF</Link></>}
        >
            <Head title="Tổng kết phong trào" />

            <section className="stats-grid">
                <div className="stat-card tone-blue"><span>Đăng ký</span><strong>{registrations.length}</strong></div>
                <div className="stat-card tone-green"><span>Đã duyệt</span><strong>{registrations.filter((row) => row.status === 'approved').length}</strong></div>
                <div className="stat-card tone-amber"><span>Kết quả</span><strong>{ranking.rows.length}</strong></div>
                <div className="stat-card tone-rose"><span>Trạng thái</span><strong>{campaign.status_label}</strong></div>
            </section>

            <section className="panel profile-password-panel">
                <form className="grid-form" onSubmit={summarize}>
                    <label className="span-2"><span>Báo cáo tổng kết</span><textarea value={form.data.summary_report} onChange={(event) => form.setData('summary_report', event.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Chốt tổng kết và cộng điểm</button>
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Hạng</th><th>Đăng ký</th><th>Lớp</th><th>Tổng điểm</th><th>Giải thưởng</th></tr></thead>
                    <tbody>
                        {ranking.rows.map((row) => (
                            <tr key={row.id}><td>{row.rank ?? '-'}</td><td>{row.participant_name}</td><td>{row.class_name ?? '-'}</td><td>{row.total_score ?? '-'}</td><td>{row.award_title ?? '-'}</td></tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
