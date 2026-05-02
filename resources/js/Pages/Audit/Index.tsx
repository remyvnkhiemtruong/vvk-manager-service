import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type AuditLog = {
    id: number;
    action: string;
    actor: string;
    subject_type: string;
    subject_id: number | null;
    before_values: Record<string, unknown> | null;
    after_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

export default function AuditIndex({ logs }: { logs: Paginated<AuditLog> }) {
    return (
        <AuthenticatedLayout title="Audit log" eyebrow="Theo dõi thao tác nhạy cảm">
            <Head title="Audit log" />
            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Người thao tác</th>
                            <th>Hành động</th>
                            <th>Đối tượng</th>
                            <th>IP</th>
                            <th>Before/After</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.data.map((log) => (
                            <tr key={log.id}>
                                <td>{log.created_at}</td>
                                <td>{log.actor}</td>
                                <td><code>{log.action}</code></td>
                                <td>{log.subject_type} #{log.subject_id}</td>
                                <td>{log.ip_address ?? '-'}</td>
                                <td>
                                    <details>
                                        <summary>Chi tiết</summary>
                                        <pre>{JSON.stringify({ before: log.before_values, after: log.after_values }, null, 2)}</pre>
                                    </details>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AuthenticatedLayout>
    );
}

