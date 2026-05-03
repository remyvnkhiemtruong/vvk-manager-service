import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Send, Trophy } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';
import type { Campaign, CampaignLookups } from './types';

type Props = {
    lookups: CampaignLookups;
    campaigns: Paginated<Campaign>;
    filters: Record<string, string>;
};

export default function Index({ lookups, campaigns, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/campaigns', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            title="Danh sách phong trào"
            eyebrow="Hoạt động Đoàn, thi đua và ngoại khóa"
            actions={<Link className="primary-button" href="/campaigns/create"><Plus size={17} />Tạo phong trào</Link>}
        >
            <Head title="Phong trào" />

            <section className="resource-toolbar academic-toolbar">
                <input value={filters.search ?? ''} onChange={(event) => applyFilter('search', event.target.value)} placeholder="Tìm tên hoạt động" />
                <select value={filters.campaign_type ?? ''} onChange={(event) => applyFilter('campaign_type', event.target.value)}>
                    <option value="">Loại hoạt động</option>
                    {Object.entries(lookups.types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
                <select value={filters.status ?? ''} onChange={(event) => applyFilter('status', event.target.value)}>
                    <option value="">Trạng thái</option>
                    {Object.entries(lookups.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Hoạt động</th><th>Loại</th><th>Thời gian</th><th>Đơn vị</th><th>Đăng ký</th><th>Kết quả</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        {campaigns.data.map((campaign) => (
                            <tr key={campaign.id}>
                                <td><strong>{campaign.title}</strong><br /><span className="muted">{campaign.description ?? '-'}</span></td>
                                <td>{campaign.type_label}</td>
                                <td>{campaign.start_date ?? '-'}<br />{campaign.end_date ?? '-'}</td>
                                <td>{campaign.organizer_unit ?? '-'}</td>
                                <td>{campaign.participants_count ?? 0}</td>
                                <td>{campaign.results_count ?? 0}</td>
                                <td>{campaign.status_label}</td>
                                <td className="row-actions">
                                    <Link className="icon-button" href={`/campaigns/${campaign.id}/register`} title="Đăng ký"><Send size={16} /></Link>
                                    <Link className="icon-button" href={`/campaigns/${campaign.id}/rankings`} title="Xếp hạng"><Trophy size={16} /></Link>
                                    <Link className="icon-button" href={`/campaigns/${campaign.id}/edit`} title="Sửa"><Edit size={16} /></Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
