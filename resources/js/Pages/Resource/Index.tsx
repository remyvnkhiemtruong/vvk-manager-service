import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, Plus, Search, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import type { Paginated } from '../../types';

type Field = {
    name: string;
    label: string;
    type: 'text' | 'email' | 'password' | 'select' | 'multiselect' | 'date' | 'datetime-local' | 'number' | 'textarea' | 'checkbox';
    options?: Record<string, string>;
    lookup?: boolean;
    required?: boolean;
    storeOnlyRequired?: boolean;
    skipEmptyOnUpdate?: boolean;
    step?: string;
};

type Definition = {
    label: string;
    module: string;
    columns: string[];
    fields: Field[];
    revision?: unknown;
};

type RecordValue = string | number | boolean | null | number[] | string[];
type ResourceRecord = Record<string, RecordValue> & { id: number };
type LookupOption = { value: number | string; label: string };

type Props = {
    resourceKey: string;
    definition: Definition;
    records: Paginated<ResourceRecord>;
    lookups: Record<string, LookupOption[]>;
    filters: { search?: string };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

function blankValue(field: Field): RecordValue {
    if (field.type === 'checkbox') {
        return false;
    }

    if (field.type === 'multiselect') {
        return [];
    }

    return '';
}

function normalizeValue(field: Field, value: RecordValue | undefined): RecordValue {
    if (value === undefined || value === null) {
        return blankValue(field);
    }

    if (field.type === 'date') {
        return String(value).slice(0, 10);
    }

    if (field.type === 'datetime-local') {
        return String(value).replace(' ', 'T').slice(0, 16);
    }

    return value;
}

function fieldLabel(fieldName: string, definition: Definition) {
    return definition.fields.find((field) => field.name === fieldName)?.label ?? fieldName;
}

function displayValue(fieldName: string, value: RecordValue, definition: Definition, lookups: Record<string, LookupOption[]>) {
    const field = definition.fields.find((item) => item.name === fieldName);

    if (Array.isArray(value)) {
        return value
            .map((item) => lookups[fieldName]?.find((option) => String(option.value) === String(item))?.label ?? String(item))
            .join(', ');
    }

    if (field?.options && value !== null && value !== undefined) {
        return field.options[String(value)] ?? String(value);
    }

    if (field?.lookup && value !== null && value !== undefined) {
        return lookups[fieldName]?.find((option) => String(option.value) === String(value))?.label ?? String(value);
    }

    if (typeof value === 'boolean') {
        return value ? 'Có' : 'Không';
    }

    return value === null || value === '' ? '-' : String(value);
}

export default function ResourceIndex({ resourceKey, definition, records, lookups, filters, can }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [editing, setEditing] = useState<ResourceRecord | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const initialData = useMemo(
        () => Object.fromEntries(definition.fields.map((field) => [field.name, blankValue(field)])) as Record<string, RecordValue>,
        [definition.fields],
    );

    const form = useForm<Record<string, RecordValue>>({
        ...initialData,
        revision_reason: '',
    });

    function openCreate() {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...initialData, revision_reason: '' });
        setIsOpen(true);
    }

    function openEdit(record: ResourceRecord) {
        setEditing(record);
        form.clearErrors();
        form.setData({
            ...initialData,
            ...Object.fromEntries(definition.fields.map((field) => [field.name, normalizeValue(field, record[field.name])])),
            revision_reason: '',
        });
        setIsOpen(true);
    }

    function closeModal() {
        setIsOpen(false);
        setEditing(null);
        form.clearErrors();
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (editing) {
            form.put(`/manage/${resourceKey}/${editing.id}`, { preserveScroll: true, onSuccess: closeModal });
            return;
        }

        form.post(`/manage/${resourceKey}`, { preserveScroll: true, onSuccess: closeModal });
    }

    function destroy(record: ResourceRecord) {
        if (!confirm(`Xóa ${definition.label.toLowerCase()} #${record.id}?`)) {
            return;
        }

        router.delete(`/manage/${resourceKey}/${record.id}`, { preserveScroll: true });
    }

    function runSearch(event: React.FormEvent) {
        event.preventDefault();
        router.get(`/manage/${resourceKey}`, { search }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            title={definition.label}
            eyebrow="Quản lý dữ liệu"
            actions={
                can.create && (
                    <button className="primary-button compact" onClick={openCreate}>
                        <Plus size={17} />
                        <span>Tạo mới</span>
                    </button>
                )
            }
        >
            <Head title={definition.label} />

            <section className="resource-toolbar">
                <form onSubmit={runSearch} className="search-box">
                    <Search size={18} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm kiếm" />
                </form>
            </section>

            <section className="table-panel">
                <table>
                    <thead>
                        <tr>
                            {definition.columns.map((column) => (
                                <th key={column}>{fieldLabel(column, definition)}</th>
                            ))}
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.data.map((record) => (
                            <tr key={record.id}>
                                {definition.columns.map((column) => (
                                    <td key={column}>{displayValue(column, record[column], definition, lookups)}</td>
                                ))}
                                <td className="row-actions">
                                    {can.update && (
                                        <button className="icon-button" title="Sửa" onClick={() => openEdit(record)}>
                                            <Edit3 size={16} />
                                        </button>
                                    )}
                                    {can.delete && (
                                        <button className="icon-button danger" title="Xóa" onClick={() => destroy(record)}>
                                            <Trash2 size={16} />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="pagination">
                    {records.links.map((link, index) => (
                        link.url ? (
                            <Link key={`${link.label}-${index}`} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span key={`${link.label}-${index}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        )
                    ))}
                </div>
            </section>

            {isOpen && (
                <div className="modal-backdrop">
                    <form className="modal" onSubmit={submit}>
                        <div className="modal-header">
                            <div>
                                <h2>{editing ? 'Cập nhật' : 'Tạo mới'} {definition.label.toLowerCase()}</h2>
                                <p>{editing ? `Bản ghi #${editing.id}` : 'Nhập thông tin cần thiết'}</p>
                            </div>
                            <button className="icon-button" type="button" onClick={closeModal}>
                                <X size={18} />
                            </button>
                        </div>

                        <div className="form-grid">
                            {definition.fields.map((field) => (
                                <label key={field.name} className={field.type === 'textarea' ? 'span-2' : undefined}>
                                    <span>{field.label}</span>
                                    {renderField(field, form.data[field.name], (value) => form.setData((data) => ({ ...data, [field.name]: value })), lookups, Boolean(editing))}
                                    {form.errors[field.name] && <small className="field-error">{form.errors[field.name]}</small>}
                                </label>
                            ))}

                            {Boolean(definition.revision) && editing && (
                                <label className="span-2">
                                    <span>Lý do chỉnh sửa</span>
                                    <textarea value={String(form.data.revision_reason ?? '')} onChange={(event) => form.setData((data) => ({ ...data, revision_reason: event.target.value }))} />
                                </label>
                            )}
                        </div>

                        <div className="modal-actions">
                            <button className="secondary-button" type="button" onClick={closeModal}>Hủy</button>
                            <button className="primary-button" disabled={form.processing}>{editing ? 'Lưu thay đổi' : 'Tạo mới'}</button>
                        </div>
                    </form>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

function renderField(field: Field, value: RecordValue, onChange: (value: RecordValue) => void, lookups: Record<string, LookupOption[]>, isEditing: boolean) {
    if (field.type === 'textarea') {
        return <textarea value={String(value ?? '')} onChange={(event) => onChange(event.target.value)} required={field.required} />;
    }

    if (field.type === 'select') {
        const options = field.options
            ? Object.entries(field.options).map(([optionValue, label]) => ({ value: optionValue, label }))
            : lookups[field.name] ?? [];

        return (
            <select value={String(value ?? '')} onChange={(event) => onChange(event.target.value)} required={field.required}>
                <option value="">Chọn</option>
                {options.map((option) => (
                    <option key={String(option.value)} value={String(option.value)}>{option.label}</option>
                ))}
            </select>
        );
    }

    if (field.type === 'multiselect') {
        const selected = Array.isArray(value) ? value.map(String) : [];

        return (
            <select multiple value={selected} onChange={(event) => onChange(Array.from(event.target.selectedOptions).map((option) => Number(option.value)))}>
                {(lookups[field.name] ?? []).map((option) => (
                    <option key={String(option.value)} value={String(option.value)}>{option.label}</option>
                ))}
            </select>
        );
    }

    if (field.type === 'checkbox') {
        return (
            <label className="switch">
                <input type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />
                <span />
            </label>
        );
    }

    return (
        <input
            type={field.type}
            step={field.step}
            value={String(value ?? '')}
            onChange={(event) => onChange(field.type === 'number' ? event.target.value : event.target.value)}
            required={field.required || (field.storeOnlyRequired && !isEditing)}
        />
    );
}
