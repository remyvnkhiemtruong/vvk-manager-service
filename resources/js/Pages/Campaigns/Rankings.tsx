import { Head, Link } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Campaign, ResultRow } from './types';

type Props = {
    campaign: Campaign;
    ranking: { rows: ResultRow[] };
};

export default function Rankings({ campaign, ranking }: Props) {
    return (
        <AuthenticatedLayout
            title="Xếp hạng phong trào"
            eyebrow={campaign.title}
            actions={<><Link className="secondary-button" href={`/campaigns/${campaign.id}/exports/rankings?format=xlsx`}><Download size={17} />Excel</Link><Link className="secondary-button" href={`/campaigns/${campaign.id}/exports/rankings?format=pdf`}><FileText size={17} />PDF</Link></>}
        >
            <Head title="Xếp hạng phong trào" />
            <section className="table-panel">
                <table>
                    <thead><tr><th>Hạng</th><th>Đăng ký</th><th>Lớp</th><th>Tổng điểm</th><th>Giải thưởng</th><th>Điểm RL</th><th>Điểm lớp</th></tr></thead>
                    <tbody>
                        {ranking.rows.map((row) => (
                            <tr key={row.id}>
                                <td><strong>{row.rank ?? '-'}</strong></td>
                                <td>{row.participant_name}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{row.total_score ?? '-'}</td>
                                <td>{row.award_title ?? '-'}</td>
                                <td>{row.conduct_points ?? '-'}</td>
                                <td>{row.class_points ?? '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
