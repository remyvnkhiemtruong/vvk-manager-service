import { Head, useForm, usePage } from '@inertiajs/react';
import { LockKeyhole, Mail } from 'lucide-react';
import type { PageProps } from '../../types';

export default function Login() {
    const { school } = usePage<PageProps>().props;
    const form = useForm({
        email: 'admin@vvk.local',
        password: 'password',
        remember: true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <div className="login-page">
            <Head title="Đăng nhập" />
            <section className="login-panel">
                <div className="login-brand">
                    <div className="brand-mark large">VK</div>
                    <div>
                        <h1>{school.name}</h1>
                        <p>{school.address}</p>
                    </div>
                </div>

                <form onSubmit={submit} className="login-form">
                    <label>
                        <span>Email</span>
                        <div className="input-with-icon">
                            <Mail size={18} />
                            <input value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} type="email" autoFocus />
                        </div>
                        {form.errors.email && <small className="field-error">{form.errors.email}</small>}
                    </label>

                    <label>
                        <span>Mật khẩu</span>
                        <div className="input-with-icon">
                            <LockKeyhole size={18} />
                            <input value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} type="password" />
                        </div>
                        {form.errors.password && <small className="field-error">{form.errors.password}</small>}
                    </label>

                    <label className="checkbox-row">
                        <input checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} type="checkbox" />
                        <span>Ghi nhớ đăng nhập</span>
                    </label>

                    <button className="primary-button" disabled={form.processing}>
                        Đăng nhập
                    </button>
                </form>
            </section>
        </div>
    );
}

