import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Campaign, CampaignLookups } from './types';

type Props = {
    lookups: CampaignLookups;
    campaign: Campaign | null;
};

export default function Form({ lookups, campaign }: Props) {
    const form = useForm({
        _method: campaign ? 'put' : '',
        school_year_id: String(campaign?.school_year_id ?? lookups.schoolYears[0]?.id ?? ''),
        semester_id: String(campaign?.semester_id ?? lookups.semesters[0]?.id ?? ''),
        title: campaign?.title ?? '',
        campaign_type: campaign?.campaign_type ?? Object.keys(lookups.types)[0] ?? '',
        organizer_unit: campaign?.organizer_unit ?? 'Đoàn trường/BTC',
        target_audience: campaign?.target_audience ?? 'all_students',
        registration_modes: campaign?.registration_modes ?? ['individual', 'team', 'class'],
        start_date: campaign?.start_date ?? '',
        end_date: campaign?.end_date ?? '',
        description: campaign?.description ?? '',
        summary_report: campaign?.summary_report ?? '',
        conduct_points_per_student: String(campaign?.conduct_points_per_student ?? 0),
        class_competition_points: String(campaign?.class_competition_points ?? 0),
        status: campaign?.status ?? 'draft',
        plan_file: null as File | null,
    });

    function toggleMode(mode: string) {
        const modes = form.data.registration_modes.includes(mode)
            ? form.data.registration_modes.filter((item) => item !== mode)
            : [...form.data.registration_modes, mode];
        form.setData('registration_modes', modes);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(campaign ? `/campaigns/${campaign.id}` : '/campaigns', { forceFormData: true });
    }

    return (
        <AuthenticatedLayout
            title={campaign ? 'Sửa phong trào' : 'Tạo phong trào'}
            eyebrow="Kế hoạch hoạt động"
            actions={<Link className="secondary-button" href="/campaigns"><ArrowLeft size={17} />Quay lại</Link>}
        >
            <Head title={campaign ? 'Sửa phong trào' : 'Tạo phong trào'} />

            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Năm học</span><select value={form.data.school_year_id} onChange={(event) => form.setData('school_year_id', event.target.value)}>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label><span>Học kỳ</span><select value={form.data.semester_id} onChange={(event) => form.setData('semester_id', event.target.value)}><option value="">Không gắn học kỳ</option>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                    <label className="span-2"><span>Tên hoạt động</span><input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></label>
                    <label><span>Loại hoạt động</span><select value={form.data.campaign_type} onChange={(event) => form.setData('campaign_type', event.target.value)}>{Object.entries(lookups.types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Đơn vị tổ chức</span><input value={form.data.organizer_unit} onChange={(event) => form.setData('organizer_unit', event.target.value)} /></label>
                    <label><span>Đối tượng</span><select value={form.data.target_audience} onChange={(event) => form.setData('target_audience', event.target.value)}>{Object.entries(lookups.targetAudiences).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Trạng thái</span><select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>{Object.entries(lookups.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    <label><span>Bắt đầu</span><input type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} /></label>
                    <label><span>Kết thúc</span><input type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} /></label>
                    <label><span>Điểm rèn luyện/HS</span><input type="number" min="0" value={form.data.conduct_points_per_student} onChange={(event) => form.setData('conduct_points_per_student', event.target.value)} /></label>
                    <label><span>Điểm thi đua lớp</span><input type="number" min="0" value={form.data.class_competition_points} onChange={(event) => form.setData('class_competition_points', event.target.value)} /></label>
                    <div className="span-2 tag-list">
                        {Object.entries(lookups.registrationModes).map(([value, label]) => (
                            <label className="checkbox-row" key={value}>
                                <input type="checkbox" checked={form.data.registration_modes.includes(value)} onChange={() => toggleMode(value)} />
                                <span>{label}</span>
                            </label>
                        ))}
                    </div>
                    <label className="span-2"><span>File kế hoạch</span><input type="file" onChange={(event) => form.setData('plan_file', event.target.files?.[0] ?? null)} /></label>
                    <label className="span-2"><span>Mô tả</span><textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></label>
                    <label className="span-2"><span>Báo cáo tổng kết</span><textarea value={form.data.summary_report} onChange={(event) => form.setData('summary_report', event.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Lưu phong trào</button>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
