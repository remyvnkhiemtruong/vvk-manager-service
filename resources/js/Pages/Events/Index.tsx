import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Send, Trophy } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';
import type { EventItem, EventLookups } from './types';

type Props = { lookups: EventLookups; events: Paginated<EventItem>; filters: Record<string, string> };

export default function Index({ lookups, events, filters }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/events', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Danh sách hội thi/hội thao" eyebrow="Sự kiện, cuộc thi và hội thao cấp trường" actions={<Link className="primary-button" href="/events/create"><Plus size={17} />Tạo sự kiện</Link>}>
            <Head title="Hội thi/hội thao" />
            <section className="resource-toolbar academic-toolbar">
                <input value={filters.search ?? ''} onChange={(event) => applyFilter('search', event.target.value)} placeholder="Tìm tên sự kiện" />
                <select value={filters.event_type ?? ''} onChange={(event) => applyFilter('event_type', event.target.value)}>
                    <option value="">Loại sự kiện</option>
                    {Object.entries(lookups.types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
                <select value={filters.status ?? ''} onChange={(event) => applyFilter('status', event.target.value)}>
                    <option value="">Trạng thái</option>
                    {Object.entries(lookups.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </section>
            <section className="table-panel">
                <table>
                    <thead><tr><th>Sự kiện</th><th>Loại</th><th>Thời gian</th><th>Địa điểm</th><th>ĐK</th><th>KQ</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        {events.data.map((event) => (
                            <tr key={event.id}>
                                <td><strong>{event.title}</strong><br /><span className="muted">{event.organizer_unit ?? '-'}</span></td>
                                <td>{event.type_label}</td>
                                <td>{event.starts_at ?? '-'}<br />{event.ends_at ?? '-'}</td>
                                <td>{event.location ?? '-'}</td>
                                <td>{event.registrations_count ?? 0}</td>
                                <td>{event.results_count ?? 0}</td>
                                <td>{event.status_label}</td>
                                <td className="row-actions">
                                    <Link className="icon-button" href={`/events/${event.id}`} title="Chi tiết"><Eye size={16} /></Link>
                                    <Link className="icon-button" href={`/events/${event.id}/register`} title="Đăng ký"><Send size={16} /></Link>
                                    <Link className="icon-button" href={`/events/${event.id}/awards`} title="Trao giải"><Trophy size={16} /></Link>
                                    <Link className="icon-button" href={`/events/${event.id}/edit`} title="Sửa"><Edit size={16} /></Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
