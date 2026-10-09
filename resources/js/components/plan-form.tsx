import { useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Save, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export type MandateOption = { id: string; number: string; title: string };
export type ActivityTypeOption = { id: string; code: string; name: string };
export type KpiCatalogOption = { id: string; activity_type: string | null; code: string; name: string; definition: string; unit: string; formula: string; default_weight: string };
export type PlanKpi = { name: string; definition?: string; baseline?: string | number; target: string | number; unit: string; formula?: string; weight?: string | number; period_start?: string; period_end?: string };
export type PlanFormValue = { id: string; mandate: MandateOption; code: string; status: string; current_version: number; current_version_record: { activity_type: string; objective: string; output: string; outcome: string; target_participants: number; kpis: PlanKpi[]; schedule?: { starts_at?: string; ends_at?: string }; resources?: string[]; risks?: Array<{ risk: string; mitigation: string }> } };

type KpiRow = PlanKpi & { key: string };
type RiskRow = { key: string; risk: string; mitigation: string };
const key = () => crypto.randomUUID();
const blankKpi = (): KpiRow => ({ key: key(), name: '', definition: '', baseline: '', target: '', unit: 'persen', formula: 'HIGHER_IS_BETTER', weight: '1', period_start: '', period_end: '' });

export function PlanForm({ mandates, activityTypes, kpiCatalogs, plan, selectedMandateId }: { mandates: MandateOption[]; activityTypes: ActivityTypeOption[]; kpiCatalogs: KpiCatalogOption[]; plan?: PlanFormValue; selectedMandateId?: string | null }) {
    const version = plan?.current_version_record;
    const [catalogId, setCatalogId] = useState('');
    const form = useForm({
        mandate_id: plan?.mandate.id ?? selectedMandateId ?? '',
        code: plan?.code ?? '',
        activity_type: version?.activity_type ?? activityTypes[0]?.code ?? '',
        objective: version?.objective ?? '', output: version?.output ?? '', outcome: version?.outcome ?? '',
        target_participants: String(version?.target_participants ?? 1),
        kpis: (version?.kpis?.length ? version.kpis.map((item) => ({ ...item, key: key() })) : [blankKpi()]) as KpiRow[],
        schedule: { starts_at: version?.schedule?.starts_at?.slice(0, 16) ?? '', ends_at: version?.schedule?.ends_at?.slice(0, 16) ?? '' },
        resources: version?.resources?.join('\n') ?? '',
        risks: (version?.risks?.length ? version.risks.map((item) => ({ ...item, key: key() })) : [{ key: key(), risk: '', mitigation: '' }]) as RiskRow[],
        change_reason: '',
    });
    const availableCatalogs = kpiCatalogs.filter((catalog) => !catalog.activity_type || catalog.activity_type === form.data.activity_type);
    const fieldError = (path: string) => (form.errors as Record<string, string>)[path];

    const addCatalog = () => {
        const catalog = availableCatalogs.find((item) => item.id === catalogId);
        if (!catalog) return;
        form.setData('kpis', [...form.data.kpis, { ...blankKpi(), name: catalog.name, definition: catalog.definition, unit: catalog.unit, formula: catalog.formula, weight: catalog.default_weight }]);
        setCatalogId('');
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            resources: data.resources.split('\n').map((item) => item.trim()).filter(Boolean),
            kpis: data.kpis.map(({ key: _key, ...item }) => item),
            risks: data.risks.filter((item) => item.risk.trim() || item.mitigation.trim()).map(({ key: _key, ...item }) => item),
        }));
        if (plan) form.patch(`/planning/${plan.id}`);
        else form.post('/planning');
    };

    return <form onSubmit={submit} className="mt-6 grid gap-6">
        <section className="panel grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <div><label htmlFor="plan-mandate" className="label">Mandat aktif</label><select id="plan-mandate" required className="field" value={form.data.mandate_id} onChange={(event) => form.setData('mandate_id', event.target.value)} disabled={Boolean(plan)}><option value="">Pilih mandat</option>{mandates.map((item) => <option key={item.id} value={item.id}>{item.number} — {item.title}</option>)}</select>{form.errors.mandate_id && <p className="error">{form.errors.mandate_id}</p>}</div>
            <div><label htmlFor="plan-code" className="label">Kode Planning</label><input id="plan-code" required className="field tabular" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} placeholder="PLN-2026-001" />{form.errors.code && <p className="error">{form.errors.code}</p>}</div>
            <div><label htmlFor="plan-type" className="label">Jenis kegiatan</label><select id="plan-type" required className="field" value={form.data.activity_type} onChange={(event) => { form.setData('activity_type', event.target.value); setCatalogId(''); }}><option value="">Pilih jenis kegiatan</option>{activityTypes.map((item) => <option key={item.id} value={item.code}>{item.name}</option>)}</select>{form.errors.activity_type && <p className="error">{form.errors.activity_type}</p>}</div>
            <div><label htmlFor="plan-participants" className="label">Target peserta</label><input id="plan-participants" type="number" min="1" required className="field" value={form.data.target_participants} onChange={(event) => form.setData('target_participants', event.target.value)} />{form.errors.target_participants && <p className="error">{form.errors.target_participants}</p>}</div>
            {([['objective', 'Tujuan'], ['output', 'Output'], ['outcome', 'Outcome']] as const).map(([name, label]) => <div key={name} className={name === 'objective' ? 'sm:col-span-2' : ''}><label htmlFor={`plan-${name}`} className="label">{label}</label><textarea id={`plan-${name}`} rows={4} required className="field" value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} />{form.errors[name] && <p className="error">{form.errors[name]}</p>}</div>)}
            <div><label htmlFor="plan-start" className="label">Mulai</label><input id="plan-start" type="datetime-local" className="field" value={form.data.schedule.starts_at} onChange={(event) => form.setData('schedule', { ...form.data.schedule, starts_at: event.target.value })} /></div>
            <div><label htmlFor="plan-end" className="label">Selesai</label><input id="plan-end" type="datetime-local" className="field" value={form.data.schedule.ends_at} onChange={(event) => form.setData('schedule', { ...form.data.schedule, ends_at: event.target.value })} />{fieldError('schedule.ends_at') && <p className="error">{fieldError('schedule.ends_at')}</p>}</div>
        </section>

        <section className="panel p-5 sm:p-6"><div className="flex flex-col gap-3 border-b border-line-200 pb-5 sm:flex-row sm:items-end"><div className="min-w-0 flex-1"><h2 className="text-base font-bold">Indikator kinerja</h2><p className="mt-1 text-xs text-muted-600">Target, formula, periode, dan bobot akan disalin ke snapshot evaluasi.</p><label htmlFor="kpi-template" className="label mt-4">Tambahkan dari katalog</label><select id="kpi-template" className="field" value={catalogId} onChange={(event) => setCatalogId(event.target.value)}><option value="">Pilih template KPI</option>{availableCatalogs.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.name}</option>)}</select></div><button type="button" className="btn-secondary" disabled={!catalogId} onClick={addCatalog}><Plus aria-hidden="true" className="size-4" />Tambah template</button></div>
            <div className="mt-5 grid gap-4">{form.data.kpis.map((kpi, index) => <fieldset key={kpi.key} className="grid gap-4 rounded-xl border border-line-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4"><legend className="px-2 text-xs font-bold">KPI {index + 1}</legend><div className="sm:col-span-2"><label htmlFor={`kpi-name-${kpi.key}`} className="label">Nama indikator</label><input id={`kpi-name-${kpi.key}`} required className="field bg-white" value={kpi.name} onChange={(event) => form.setData('kpis', form.data.kpis.map((item) => item.key === kpi.key ? { ...item, name: event.target.value } : item))} />{fieldError(`kpis.${index}.name`) && <p className="error">{fieldError(`kpis.${index}.name`)}</p>}</div><div><label htmlFor={`kpi-target-${kpi.key}`} className="label">Target</label><input id={`kpi-target-${kpi.key}`} type="number" min="0" step="any" required className="field bg-white" value={kpi.target} onChange={(event) => form.setData('kpis', form.data.kpis.map((item) => item.key === kpi.key ? { ...item, target: event.target.value } : item))} /></div><div><label htmlFor={`kpi-unit-${kpi.key}`} className="label">Satuan</label><input id={`kpi-unit-${kpi.key}`} required className="field bg-white" value={kpi.unit} onChange={(event) => form.setData('kpis', form.data.kpis.map((item) => item.key === kpi.key ? { ...item, unit: event.target.value } : item))} /></div><div className="sm:col-span-2 lg:col-span-4"><label htmlFor={`kpi-definition-${kpi.key}`} className="label">Definisi</label><textarea id={`kpi-definition-${kpi.key}`} rows={2} className="field bg-white" value={kpi.definition ?? ''} onChange={(event) => form.setData('kpis', form.data.kpis.map((item) => item.key === kpi.key ? { ...item, definition: event.target.value } : item))} /></div><button type="button" className="btn-secondary justify-self-start px-3 sm:col-span-2 lg:col-span-4" disabled={form.data.kpis.length === 1} onClick={() => form.setData('kpis', form.data.kpis.filter((item) => item.key !== kpi.key))}><Trash2 aria-hidden="true" className="size-4" />Hapus KPI</button></fieldset>)}</div>
            <button type="button" className="btn-secondary mt-4" onClick={() => form.setData('kpis', [...form.data.kpis, blankKpi()])}><Plus aria-hidden="true" className="size-4" />Tambah KPI manual</button>
        </section>

        <section className="panel grid gap-5 p-5 sm:grid-cols-2 sm:p-6"><div><label htmlFor="plan-resources" className="label">Sumber daya</label><textarea id="plan-resources" rows={6} className="field" value={form.data.resources} onChange={(event) => form.setData('resources', event.target.value)} placeholder="Satu kebutuhan per baris" /></div><div><h2 className="label">Risiko & mitigasi</h2><div className="grid gap-3">{form.data.risks.map((risk, index) => <div key={risk.key} className="grid gap-2 rounded-lg border border-line-200 p-3"><input aria-label={`Risiko ${index + 1}`} className="field" placeholder="Risiko" value={risk.risk} onChange={(event) => form.setData('risks', form.data.risks.map((item) => item.key === risk.key ? { ...item, risk: event.target.value } : item))} /><input aria-label={`Mitigasi ${index + 1}`} className="field" placeholder="Mitigasi" value={risk.mitigation} onChange={(event) => form.setData('risks', form.data.risks.map((item) => item.key === risk.key ? { ...item, mitigation: event.target.value } : item))} /></div>)}<button type="button" className="btn-secondary justify-self-start" onClick={() => form.setData('risks', [...form.data.risks, { key: key(), risk: '', mitigation: '' }])}><Plus aria-hidden="true" className="size-4" />Tambah risiko</button></div></div>{plan && <div className="sm:col-span-2"><label htmlFor="plan-change-reason" className="label">Alasan perubahan</label><textarea id="plan-change-reason" required rows={3} className="field" value={form.data.change_reason} onChange={(event) => form.setData('change_reason', event.target.value)} />{form.errors.change_reason && <p className="error">{form.errors.change_reason}</p>}</div>}</section>
        <div className="flex justify-end"><button type="submit" className="btn-primary" disabled={form.processing}>{form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Save aria-hidden="true" className="size-4" />}{plan ? 'Ajukan versi baru' : 'Ajukan Planning'}</button></div>
    </form>;
}
