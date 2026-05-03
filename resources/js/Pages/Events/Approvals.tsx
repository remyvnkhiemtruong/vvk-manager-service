import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventItem, EventLookups, EventRegistration } from './types';

type Props = { lookups: EventLookups & { events: EventItem[] }; registrations: { data: EventRegistration[] }; filters: Record<string, string> };

export default function Approvals({ lookups, registrations, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/events/registrations', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Duyệt đăng ký hội thi/hội thao" eyebrow="GVCN và BTC kiểm tra danh sách">
            <Head title="Duyệt đăng ký sự kiện" />
            <section className="resource-toolbar academic-toolbar">
                <select value={filters.event_id ?? ''} onChange={(e) => applyFilter('event_id', e.target.value)}><option value="">Sự kiện</option>{lookups.events.map((event) => <option key={event.id} value={event.id}>{event.title}</option>)}</select>
                <select value={filters.status ?? ''} onChange={(e) => applyFilter('status', e.target.value)}><option value="">Trạng thái</option>{Object.entries(lookups.registrationStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
            </section>
            <section className="table-panel">
                <table><thead><tr><th>Sự kiện</th><th>Nội dung</th><th>Tên đăng ký</th><th>Lớp</th><th>Trạng thái</th><th></th></tr></thead><tbody>{registrations.data.map((row) => <tr key={row.id}><td>{row.event_title}</td><td>{row.category_name}</td><td>{row.participant_name}</td><td>{row.class_name ?? '-'}</td><td>{row.status_label}</td><td className="row-actions"><button className="icon-button" title="Duyệt" onClick={() => router.post(`/events/registrations/${row.id}/approve`)}><Check size={16} /></button><button className="icon-button danger" title="Từ chối" onClick={() => { const reason = window.prompt('Lý do từ chối'); if (reason) router.post(`/events/registrations/${row.id}/reject`, { reason }); }}><X size={16} /></button></td></tr>)}</tbody></table>
            </section>
        </AuthenticatedLayout>
    );
}
