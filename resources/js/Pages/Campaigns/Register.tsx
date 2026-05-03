import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Send, X } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';
import type { Campaign, CampaignLookups, Registration } from './types';

type Props = {
    lookups: CampaignLookups;
    campaign: Campaign;
    registrations: Paginated<Registration>;
};

export default function Register({ lookups, campaign, registrations }: Props) {
    const form = useForm({
        participant_type: campaign.registration_modes[0] ?? 'individual',
        class_id: '',
        student_id: '',
        member_ids: [] as string[],
        participant_name: '',
        note: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/campaigns/${campaign.id}/registrations`, { preserveScroll: true, onSuccess: () => form.reset('student_id', 'member_ids', 'participant_name', 'note') });
    }

    function toggleMember(id: string) {
        form.setData('member_ids', form.data.member_ids.includes(id) ? form.data.member_ids.filter((item) => item !== id) : [...form.data.member_ids, id]);
    }

    function approve(row: Registration) {
        router.post(`/campaigns/registrations/${row.id}/approve`, {}, { preserveScroll: true });
    }

    function reject(row: Registration) {
        const reason = window.prompt('Lý do từ chối');
        if (reason) router.post(`/campaigns/registrations/${row.id}/reject`, { reason }, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Đăng ký tham gia" eyebrow={campaign.title} actions={<Link className="secondary-button" href={`/campaigns/${campaign.id}/summary`}>Tổng kết</Link>}>
            <Head title="Đăng ký phong trào" />

            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Hình thức</span><select value={form.data.participant_type} onChange={(event) => form.setData('participant_type', event.target.value)}>{campaign.registration_modes.map((mode) => <option key={mode} value={mode}>{lookups.registrationModes[mode]}</option>)}</select></label>
                    <label><span>Lớp</span><select value={form.data.class_id} onChange={(event) => form.setData('class_id', event.target.value)}><option value="">Chọn lớp</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    {form.data.participant_type === 'individual' && <label><span>Học sinh</span><select value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)}><option value="">Chọn học sinh</option>{lookups.students.map((item) => <option key={item.id} value={item.id}>{item.student_code} - {item.full_name}</option>)}</select></label>}
                    <label><span>Tên đội/nhóm</span><input value={form.data.participant_name} onChange={(event) => form.setData('participant_name', event.target.value)} /></label>
                    {form.data.participant_type === 'team' && (
                        <label className="span-2"><span>Thành viên</span><select multiple value={form.data.member_ids} onChange={(event) => form.setData('member_ids', Array.from(event.target.selectedOptions).map((option) => option.value))}>{lookups.students.map((item) => <option key={item.id} value={item.id}>{item.student_code} - {item.full_name}</option>)}</select></label>
                    )}
                    <label className="span-2"><span>Ghi chú</span><textarea value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></label>
                    <button className="primary-button"><Send size={17} />Gửi đăng ký</button>
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Tên đăng ký</th><th>Loại</th><th>Lớp</th><th>Học sinh/thành viên</th><th>Trạng thái</th><th>Người đăng ký</th><th></th></tr></thead>
                    <tbody>
                        {registrations.data.map((row) => (
                            <tr key={row.id}>
                                <td>{row.participant_name}</td>
                                <td>{row.participant_type_label}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{(row.student_name ?? row.members.map((member) => member.full_name).join(', ')) || '-'}</td>
                                <td>{row.status_label}</td>
                                <td>{row.registered_by ?? '-'}</td>
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
