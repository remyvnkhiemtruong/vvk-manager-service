import { Head, Link, router, useForm } from '@inertiajs/react';
import { Save, Trophy } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Campaign, Criterion, Registration, ResultRow } from './types';

type Props = {
    campaign: Campaign;
    criteria: Criterion[];
    participants: Registration[];
    results: ResultRow[];
};

export default function Results({ campaign, criteria, participants, results }: Props) {
    const form = useForm({
        campaign_participant_id: '',
        award_title: '',
        conduct_points: String(campaign.conduct_points_per_student ?? 0),
        class_points: String(campaign.class_competition_points ?? 0),
        status: 'published',
        note: '',
        scores: criteria.map((criterion) => ({ campaign_criterion_id: Number(criterion.id), score: '', note: '' })),
        evidences: [] as File[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/campaigns/${campaign.id}/results`, { forceFormData: true, preserveScroll: true });
    }

    function updateScore(index: number, score: string) {
        const scores = [...form.data.scores];
        scores[index] = { ...scores[index], score };
        form.setData('scores', scores);
    }

    return (
        <AuthenticatedLayout title="Nhập kết quả phong trào" eyebrow={campaign.title} actions={<Link className="primary-button" href={`/campaigns/${campaign.id}/rankings`}><Trophy size={17} />Xếp hạng</Link>}>
            <Head title="Nhập kết quả phong trào" />

            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label className="span-2"><span>Đối tượng tham gia</span><select value={form.data.campaign_participant_id} onChange={(event) => form.setData('campaign_participant_id', event.target.value)}><option value="">Chọn đăng ký đã duyệt</option>{participants.filter((item) => item.status === 'approved').map((item) => <option key={item.id} value={item.id}>{item.participant_name} - {item.class_name ?? item.student_name ?? ''}</option>)}</select></label>
                    <label><span>Giải thưởng</span><input value={form.data.award_title} onChange={(event) => form.setData('award_title', event.target.value)} /></label>
                    <label><span>Trạng thái kết quả</span><select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="draft">Nháp</option><option value="published">Công bố</option><option value="final">Chốt</option></select></label>
                    {criteria.map((criterion, index) => (
                        <label key={criterion.id}><span>{criterion.name} / {criterion.max_score}</span><input type="number" min="0" max={Number(criterion.max_score)} value={form.data.scores[index]?.score ?? ''} onChange={(event) => updateScore(index, event.target.value)} /></label>
                    ))}
                    <label><span>Điểm rèn luyện/HS</span><input type="number" min="0" value={form.data.conduct_points} onChange={(event) => form.setData('conduct_points', event.target.value)} /></label>
                    <label><span>Điểm thi đua lớp</span><input type="number" min="0" value={form.data.class_points} onChange={(event) => form.setData('class_points', event.target.value)} /></label>
                    <label className="span-2"><span>Minh chứng</span><input type="file" multiple onChange={(event) => form.setData('evidences', Array.from(event.target.files ?? []))} /></label>
                    <label className="span-2"><span>Ghi chú</span><textarea value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Lưu kết quả</button>
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Đăng ký</th><th>Lớp</th><th>Tổng điểm</th><th>Hạng</th><th>Giải thưởng</th><th>Điểm RL</th><th>Điểm lớp</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                        {results.map((row) => (
                            <tr key={row.id}>
                                <td>{row.participant_name}</td>
                                <td>{row.class_name ?? '-'}</td>
                                <td>{row.total_score ?? '-'}</td>
                                <td>{row.rank ?? '-'}</td>
                                <td>{row.award_title ?? '-'}</td>
                                <td>{row.conduct_points ?? '-'}</td>
                                <td>{row.class_points ?? '-'}</td>
                                <td>{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
