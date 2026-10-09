import { ActivityTaxonomyForm } from '@/components/activity-taxonomy-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateTaxonomy() { return <><Head title="Tambah taksonomi" /><PageHeader eyebrow="Administrasi · master data" title="Tambah taksonomi kegiatan" description="Taksonomi mengikat jenis kegiatan dan metodologi pembanding dari Planning hingga intelligence." actions={<Link href="/administration/taxonomies" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link>} /><ActivityTaxonomyForm /></>; }
