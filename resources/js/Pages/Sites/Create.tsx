import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { PageProps } from '@/types';
import { FormEvent } from 'react';
import { useTranslations } from '@/hooks/useTranslations';

export default function SitesCreate({}: PageProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        domain: '',
        name: '',
        language: 'en',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('sites.store'));
    };

    return (
        <AppLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('sites.index')}
                        className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">{t?.sites?.create?.title ?? 'Add New Site'}</h1>
                </div>
            }
        >
            <Head title={t?.sites?.create?.title ?? 'Add Site'} />

            <div className="mx-auto max-w-2xl">
                <Card>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="domain" className="block text-sm font-medium text-gray-700">
                                {t?.sites?.create?.domain ?? 'Domain'}
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="domain"
                                    value={data.domain}
                                    onChange={(e) => setData('domain', e.target.value)}
                                    placeholder="example.com"
                                    className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            {errors.domain && (
                                <p className="mt-1 text-sm text-red-600">{errors.domain}</p>
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                {t?.sites?.create?.domainHelp ?? 'Enter your domain without http:// or www.'}
                            </p>
                        </div>

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                {t?.sites?.create?.name ?? 'Site Name'}
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t?.sites?.create?.namePlaceholder ?? 'My Website'}
                                    className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="language" className="block text-sm font-medium text-gray-700">
                                {t?.sites?.create?.language ?? 'Primary Language'}
                            </label>
                            <div className="mt-1">
                                <select
                                    id="language"
                                    value={data.language}
                                    onChange={(e) => setData('language', e.target.value)}
                                    className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="en">{t?.sites?.languages?.en ?? 'English'}</option>
                                    <option value="fr">{t?.sites?.languages?.fr ?? 'French'}</option>
                                    <option value="de">{t?.sites?.languages?.de ?? 'German'}</option>
                                    <option value="es">{t?.sites?.languages?.es ?? 'Spanish'}</option>
                                    <option value="it">{t?.sites?.languages?.it ?? 'Italian'}</option>
                                    <option value="pt">{t?.sites?.languages?.pt ?? 'Portuguese'}</option>
                                    <option value="nl">{t?.sites?.languages?.nl ?? 'Dutch'}</option>
                                </select>
                            </div>
                            {errors.language && (
                                <p className="mt-1 text-sm text-red-600">{errors.language}</p>
                            )}
                        </div>

                        <div className="flex justify-end gap-3 pt-4">
                            <Button
                                as="link"
                                href={route('sites.index')}
                                variant="secondary"
                            >
                                {t?.common?.cancel ?? 'Cancel'}
                            </Button>
                            <Button type="submit" loading={processing}>
                                {t?.sites?.addSite ?? 'Add Site'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
