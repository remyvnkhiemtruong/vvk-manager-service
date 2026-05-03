import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Lookup = { id: number; name: string };
type ClassRow = { student_id: number; student_code: string; student_name: string; class_name: string | null; score: number; rating: string | null; bonus_points: number; minus_points: number };
type PointRow = { student_id: number; student_code: string; student_name: string; class_name: string | null; total_points: number };
type ViolationRow = { code: string; name: string; record_count: number; total_minus: number };
type DistributionRow = { grade_level: number | null; class_name: string | null; rating: string | null; total: number };
type RiskRow = { student_id: number; student_code: string; student_name: string; class_name: string | null; score: number; rating: string | null; minus_points: number };
type Props = {
    lookups: { schoolYears: Lookup[]; semesters: Lookup[]; classes: Lookup[] };
    filters: Record<string, string>;
    overview: {
        classTable: ClassRow[];
        topDeducted: Paginated<PointRow>;
        topAwarded: Paginated<PointRow>;
        commonViolations: ViolationRow[];
        ratingDistribution: DistributionRow[];
        lowRiskStudents: Paginated<RiskRow>;
    };
};

export default function Reports({ lookups, filters, overview }: Props) {
    function applyFilter(key: string, value: string) {
        router.get('/conduct/reports', { ...filters, [key]: value }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Báo cáo rèn luyện" eyebrow="Phân tích nề nếp và phong trào">
            <Head title="Báo cáo rèn luyện" />

            <section className="resource-toolbar academic-toolbar">
                <select value={filters.school_year_id ?? ''} onChange={(event) => applyFilter('school_year_id', event.target.value)}><option value="">Năm học</option>{lookups.schoolYears.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.semester_id ?? ''} onChange={(event) => applyFilter('semester_id', event.target.value)}><option value="">Học kỳ</option>{lookups.semesters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <select value={filters.class_id ?? ''} onChange={(event) => applyFilter('class_id', event.target.value)}><option value="">Lớp</option>{lookups.classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                <input value={filters.threshold ?? '60'} onChange={(event) => applyFilter('threshold', event.target.value)} placeholder="Ngưỡng nguy cơ" />
            </section>

            <div className="two-column">
                <section className="panel">
                    <h2>Học sinh bị trừ nhiều nhất</h2>
                    <div className="metric-list">{overview.topDeducted.data.map((row) => <div key={row.student_id}><span>{row.student_name}</span><strong>{row.total_points}</strong></div>)}</div>
                </section>
                <section className="panel">
                    <h2>Học sinh được cộng nhiều nhất</h2>
                    <div className="metric-list">{overview.topAwarded.data.map((row) => <div key={row.student_id}><span>{row.student_name}</span><strong>{row.total_points}</strong></div>)}</div>
                </section>
            </div>

            <div className="two-column">
                <section className="panel">
                    <h2>Lỗi vi phạm phổ biến</h2>
                    <div className="metric-list">{overview.commonViolations.map((row) => <div key={row.code}><span>{row.name}</span><strong>{row.record_count}</strong></div>)}</div>
                </section>
                <section className="panel">
                    <h2>Tỷ lệ xếp loại</h2>
                    <div className="metric-list">{overview.ratingDistribution.map((row, index) => <div key={`${row.class_name}-${row.rating}-${index}`}><span>{row.class_name ?? `Khối ${row.grade_level ?? '-'}`} · {row.rating ?? '-'}</span><strong>{row.total}</strong></div>)}</div>
                </section>
            </div>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Lớp</th><th>Điểm</th><th>Xếp loại</th><th>Điểm trừ</th></tr></thead>
                    <tbody>
                        {overview.lowRiskStudents.data.map((row) => (
                            <tr key={row.student_id}><td>{row.student_code}</td><td>{row.student_name}</td><td>{row.class_name ?? '-'}</td><td>{row.score}</td><td>{row.rating ?? '-'}</td><td>{row.minus_points}</td></tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="table-panel">
                <table>
                    <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Lớp</th><th>Điểm</th><th>Xếp loại</th><th>Cộng</th><th>Trừ</th></tr></thead>
                    <tbody>
                        {overview.classTable.map((row) => (
                            <tr key={row.student_id}><td>{row.student_code}</td><td>{row.student_name}</td><td>{row.class_name ?? '-'}</td><td>{row.score}</td><td>{row.rating ?? '-'}</td><td>{row.bonus_points}</td><td>{row.minus_points}</td></tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}
