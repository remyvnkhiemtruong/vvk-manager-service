import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { EventCategory, EventItem, EventLookups } from './types';

type Props = { lookups: EventLookups; event: EventItem; categories: EventCategory[] };

export default function Categories({ lookups, event, categories }: Props) {
    const form = useForm({
        name: '',
        participation_type: 'team',
        max_participants: '',
        gender_rule: '',
        rules_text: '',
        scoring_mode: 'sport',
        sport_rule: 'football',
        judge_score_mode: 'average',
        drop_extreme_scores: false,
        max_score: '100',
        order_index: String(categories.length + 1),
        status: 'active',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/events/${event.id}/categories`, { preserveScroll: true, onSuccess: () => form.reset('name', 'rules_text') });
    }

    return (
        <AuthenticatedLayout title="Quản lý nội dung thi" eyebrow={event.title} actions={<Link className="secondary-button" href={`/events/${event.id}`}><ArrowLeft size={17} />Chi tiết</Link>}>
            <Head title="Nội dung thi" />
            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label className="span-2"><span>Tên nội dung</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                    <label><span>Hình thức</span><select value={form.data.participation_type} onChange={(e) => form.setData('participation_type', e.target.value)}>{Object.entries(lookups.participationTypes).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
                    <label><span>Số lượng tối đa</span><input type="number" value={form.data.max_participants} onChange={(e) => form.setData('max_participants', e.target.value)} /></label>
                    <label><span>Cách tính</span><select value={form.data.scoring_mode} onChange={(e) => form.setData('scoring_mode', e.target.value)}>{Object.entries(lookups.scoringModes).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
                    <label><span>Luật môn</span><select value={form.data.sport_rule} onChange={(e) => form.setData('sport_rule', e.target.value)}>{Object.entries(lookups.sportRules).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
                    <label><span>Giới tính</span><input value={form.data.gender_rule} onChange={(e) => form.setData('gender_rule', e.target.value)} placeholder="nam/nữ/hỗn hợp nếu cần" /></label>
                    <label><span>Thang điểm</span><input type="number" value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} /></label>
                    <label className="checkbox-row"><input type="checkbox" checked={form.data.drop_extreme_scores} onChange={(e) => form.setData('drop_extreme_scores', e.target.checked)} /><span>Bỏ điểm cao/thấp khi đủ giám khảo</span></label>
                    <label className="span-2"><span>Luật/tiêu chí</span><textarea value={form.data.rules_text} onChange={(e) => form.setData('rules_text', e.target.value)} /></label>
                    <button className="primary-button"><Save size={17} />Lưu nội dung</button>
                </form>
            </section>
            <section className="table-panel">
                <table>
                    <thead><tr><th>Nội dung</th><th>Hình thức</th><th>Luật</th><th>Tiêu chí</th><th>Trạng thái</th></tr></thead>
                    <tbody>{categories.map((category) => <tr key={category.id}><td>{category.name}</td><td>{lookups.participationTypes[category.participation_type] ?? category.participation_type}</td><td>{lookups.sportRules[category.sport_rule ?? ''] ?? category.scoring_mode}</td><td>{category.criteria.length}</td><td>{category.status}</td></tr>)}</tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
