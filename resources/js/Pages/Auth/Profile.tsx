import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck, UserRound } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Role = {
    id: number;
    name: string;
    slug: string;
};

type StudentSummary = {
    id: number;
    student_code: string;
    full_name: string;
};

type Profile = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    status: string;
    last_login_at: string | null;
    roles: Role[];
    permissions: string[];
    context: {
        staff: null | {
            id: number;
            teacher_code: string;
            full_name: string;
            position: string | null;
            department: string | null;
        };
        student: null | StudentSummary & { status: string };
        guardian: null | {
            id: number;
            full_name: string;
            students: StudentSummary[];
        };
        homeroom_class_ids: number[];
        teaching_assignments: { class_id: number; subject_id: number; semester_id: number }[];
    };
};

type Props = {
    profile: Profile;
};

export default function Profile({ profile }: Props) {
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <AuthenticatedLayout title="Hồ sơ tài khoản" eyebrow={profile.email}>
            <Head title="Hồ sơ tài khoản" />

            <div className="two-column">
                <section className="panel">
                    <div className="panel-heading">
                        <UserRound size={20} />
                        <h2>Thông tin đăng nhập</h2>
                    </div>

                    <div className="metric-list">
                        <div>
                            <span>Họ tên</span>
                            <strong>{profile.name}</strong>
                        </div>
                        <div>
                            <span>Tên đăng nhập</span>
                            <strong>{profile.username ?? '-'}</strong>
                        </div>
                        <div>
                            <span>Trạng thái</span>
                            <strong>{profile.status}</strong>
                        </div>
                        <div>
                            <span>Lần đăng nhập gần nhất</span>
                            <strong>{profile.last_login_at ? new Date(profile.last_login_at).toLocaleString('vi-VN') : '-'}</strong>
                        </div>
                    </div>
                </section>

                <section className="panel">
                    <div className="panel-heading">
                        <ShieldCheck size={20} />
                        <h2>Vai trò và phạm vi</h2>
                    </div>

                    <div className="tag-list">
                        {profile.roles.map((role) => (
                            <span key={role.id}>{role.name}</span>
                        ))}
                    </div>

                    <div className="profile-context">
                        {profile.context.staff && (
                            <p>
                                Giáo viên/nhân sự: <strong>{profile.context.staff.full_name}</strong> ({profile.context.staff.teacher_code})
                            </p>
                        )}
                        {profile.context.student && (
                            <p>
                                Học sinh: <strong>{profile.context.student.full_name}</strong> ({profile.context.student.student_code})
                            </p>
                        )}
                        {profile.context.guardian && (
                            <p>
                                Phụ huynh: <strong>{profile.context.guardian.full_name}</strong> · học sinh liên kết:{' '}
                                {profile.context.guardian.students.map((student) => student.full_name).join(', ') || '-'}
                            </p>
                        )}
                        <p>Quyền được cấp: {profile.permissions.length.toLocaleString('vi-VN')}</p>
                    </div>
                </section>
            </div>

            <section className="panel profile-password-panel">
                <div className="panel-heading">
                    <KeyRound size={20} />
                    <h2>Đổi mật khẩu</h2>
                </div>

                <form className="form-grid compact-form" onSubmit={submit}>
                    <label>
                        <span>Mật khẩu hiện tại</span>
                        <input
                            type="password"
                            value={form.data.current_password}
                            onChange={(event) => form.setData('current_password', event.target.value)}
                            required
                        />
                        {form.errors.current_password && <small className="field-error">{form.errors.current_password}</small>}
                    </label>

                    <label>
                        <span>Mật khẩu mới</span>
                        <input
                            type="password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            required
                            minLength={8}
                        />
                        {form.errors.password && <small className="field-error">{form.errors.password}</small>}
                    </label>

                    <label>
                        <span>Nhập lại mật khẩu mới</span>
                        <input
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            required
                            minLength={8}
                        />
                    </label>

                    <div className="form-actions-inline">
                        <button className="primary-button" disabled={form.processing}>
                            Cập nhật mật khẩu
                        </button>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
