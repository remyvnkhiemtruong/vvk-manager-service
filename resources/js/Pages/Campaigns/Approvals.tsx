import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';
import type { Campaign, CampaignLookups, Registration } from './types';

type Props = {
    lookups: CampaignLookups & { campaigns: Campaign[] };
    registrations: Paginated<Registration>;
    filters: Record<string, string>;
};

export default function Approvals({ lookups, registrations, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/campaigns/registrations', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    function approve(row: Registration) {
        const note = window.prompt('Ghi chú duyệt', '');
        router.post(`/campaigns/registrations/${row.id}/approve`, { note }, { preserveScroll: true });
    }

    function reject(row: Registration) {
        const reason = window.prompt('Lý do từ chối');
        if (reason) router.post(`/campaigns/registrations/${row.id}/reject`, { reason }, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Duyệt đăng ký phong trào" eyebrow="GVCN hoặc BTC xác nhận đăng ký">
            <Head title="Duyệt đăng ký phong trào" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.campaign_id ?? ''} onChange={(event) => applyFilter('campaign_id', event.target.value)}><option value="">Phong trào</option>{lookups.campaigns.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select>
                <select value={filters.class_id ?? ''} onChange={(event) => applyFilter('class_id', event.target.value)}><option value="">Lớp</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.status ?? 'pending'} onChange={(event) => applyFilter('status', event.target.value)}><option value="">Trạng thái</option><option value="pending">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="rejected">Từ chối</option></select>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Phong trào</th><th>Tên đăng ký</th><th>Loại</th><th>Lớp</th><th>Thành viên</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        {registrations.data.map((row) => (
                            <tr key={row.id}>
                                <td>{row.campaign_title}</td>
                                <td>{row.participant_name}</td>
                                <td>{row.participant_type_label}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{(row.student_name ?? row.members.map((member) => member.full_name).join(', ')) || '-'}</td>
                                <td>{row.status_label}</td>
                                <td className="row-actions">
                                    {row.status === 'pending' && <button className="icon-button" onClick={() => approve(row)} title="Duyệt"><Check size={16} /></button>}
                                    {row.status === 'pending' && <button className="icon-button danger" onClick={() => reject(row)} title="Từ chối"><X size={16} /></button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
