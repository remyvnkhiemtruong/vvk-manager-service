import { Head, router, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Rule = {
    id: number;
    code: string;
    name: string;
    rule_type: 'bonus' | 'deduction';
    points: number;
    severity: string;
    requires_approval: boolean;
    status: string;
};

type Rating = { id: number; rating: string; min_score: number; max_score: number };

type Props = {
    rules: Paginated<Rule>;
    ratingRules: Rating[];
};

export default function Rules({ rules, ratingRules }: Props) {
    const form = useForm({
        code: '',
        name: '',
        rule_type: 'deduction',
        points: -1,
        severity: 'minor',
        requires_approval: false,
        description: '',
        sort_order: 1,
        status: 'active',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/conduct/rules', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function remove(rule: Rule) {
        if (window.confirm(`Ngưng dùng tiêu chí ${rule.code}?`)) {
            router.delete(`/conduct/rules/${rule.id}`, { preserveScroll: true });
        }
    }

    return (
        <AuthenticatedLayout title="Cấu hình tiêu chí rèn luyện" eyebrow="Điểm rèn luyện">
            <Head title="Cấu hình tiêu chí rèn luyện" />

            <section className="panel">
                <form className="grid-form" onSubmit={submit}>
                    <label><span>Mã tiêu chí</span><input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></label>
                    <label><span>Tên tiêu chí</span><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                    <label><span>Loại</span><select value={form.data.rule_type} onChange={(event) => form.setData('rule_type', event.target.value)}><option value="bonus">Cộng điểm</option><option value="deduction">Trừ điểm</option></select></label>
                    <label><span>Số điểm</span><input type="number" value={form.data.points} onChange={(event) => form.setData('points', Number(event.target.value))} /></label>
                    <label><span>Mức độ</span><select value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}><option value="minor">Nhẹ</option><option value="normal">Thông thường</option><option value="major">Nặng</option><option value="serious">Rất nặng</option></select></label>
                    <label><span>Thứ tự</span><input type="number" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} /></label>
                    <label className="checkbox-row"><input type="checkbox" checked={form.data.requires_approval} onChange={(event) => form.setData('requires_approval', event.target.checked)} />Cần duyệt</label>
                    <button className="primary-button"><Save size={17} />Lưu tiêu chí</button>
                </form>
            </section>

            <div className="two-column">
                <section className="table-panel">
                    <table>
                        <thead><tr><th>Mã</th><th>Tiêu chí</th><th>Loại</th><th>Điểm</th><th>Mức độ</th><th>Duyệt</th><th></th></tr></thead>
                        <tbody>
                            {rules.data.map((rule) => (
                                <tr key={rule.id}>
                                    <td><code>{rule.code}</code></td>
                                    <td>{rule.name}</td>
                                    <td>{rule.rule_type === 'bonus' ? 'Cộng' : 'Trừ'}</td>
                                    <td>{rule.points}</td>
                                    <td>{rule.severity}</td>
                                    <td>{rule.requires_approval ? 'Có' : 'Không'}</td>
                                    <td><button className="icon-button danger" onClick={() => remove(rule)} title="Ngưng dùng"><Trash2 size={16} /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="panel">
                    <h2>Xếp loại</h2>
                    <div className="metric-list">
                        {ratingRules.map((rating) => (
                            <div key={rating.id}><span>{rating.rating}</span><strong>{rating.min_score} - {rating.max_score}</strong></div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
