import BackLink from '@/Components/BackLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import GoogleAccountForm from './Partials/GoogleAccountForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import VerifiedManager from './Partials/VerifiedManager';

export default function Settings({
    googleConfigured = false,
    googleLinked = false,
    canManageVerified = false,
    verifiedPlayers = [],
}: {
    googleConfigured?: boolean;
    googleLinked?: boolean;
    canManageVerified?: boolean;
    verifiedPlayers?: {
        username: string;
        display_name: string;
        is_verified: boolean;
    }[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Configurações" />
            <main className="min-h-screen overflow-x-hidden bg-[#e8e5dc] px-3 py-6 sm:px-4 sm:py-10 lg:px-6 text-[#1d1d1b]">
                <div className="w-full">
                    <BackLink className="mb-4" href={route('profile.edit')} />
                    <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#464498]">
                                Estação Commit / conta
                            </p>
                            <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                                Configurações
                            </h1>
                            <p className="mt-1 text-sm text-[#5b5953]">
                                Configurações privadas, separadas do perfil
                                público.
                            </p>
                        </div>
                    </header>

                    <div className="grid items-stretch gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                        <UpdateProfileInformationForm className="h-full" />
                        <GoogleAccountForm
                            configured={googleConfigured}
                            linked={googleLinked}
                        />
                        <UpdatePasswordForm />
                        <DeleteUserForm />
                    </div>

                    {canManageVerified && (
                        <div className="mt-6">
                            <VerifiedManager verified={verifiedPlayers} />
                        </div>
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
