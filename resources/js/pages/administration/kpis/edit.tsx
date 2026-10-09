import { KpiCatalogForm, type KpiCatalogValue, type TaxonomyOption } from '@/components/kpi-catalog-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditKpi({ catalog, taxonomies }: { catalog: KpiCatalogValue; taxonomies: TaxonomyOption[] }) { return <><Head title={`Ubah ${catalog.name}`} /><PageHeader eyebrow="Administrasi · master data" title={`Ubah ${catalog.name}`} description="Perubahan template hanya berlaku untuk Planning baru; snapshot versi lama tidak berubah." actions={<Link href={`/administration/kpis/${catalog.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Batal</Link>} /><KpiCatalogForm catalog={catalog} taxonomies={taxonomies} /></>; }
