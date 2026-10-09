import { KpiCatalogForm, type TaxonomyOption } from '@/components/kpi-catalog-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateKpi({ taxonomies }: { taxonomies: TaxonomyOption[] }) { return <><Head title="Tambah KPI" /><PageHeader eyebrow="Administrasi · master data" title="Tambah definisi KPI" description="Template membantu konsistensi; target dan periode tetap ditetapkan pada setiap versi Planning." actions={<Link href="/administration/kpis" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link>} /><KpiCatalogForm taxonomies={taxonomies} /></>; }
