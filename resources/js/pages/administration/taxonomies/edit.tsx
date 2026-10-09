import { ActivityTaxonomyForm, type TaxonomyValue } from '@/components/activity-taxonomy-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditTaxonomy({ taxonomy }: { taxonomy: TaxonomyValue }) { return <><Head title={`Ubah ${taxonomy.name}`} /><PageHeader eyebrow="Administrasi · master data" title={`Ubah ${taxonomy.name}`} description="Kode dan status taksonomi yang sudah dipakai Planning dikunci untuk menjaga lineage." actions={<Link href={`/administration/taxonomies/${taxonomy.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Batal</Link>} /><ActivityTaxonomyForm taxonomy={taxonomy} /></>; }
