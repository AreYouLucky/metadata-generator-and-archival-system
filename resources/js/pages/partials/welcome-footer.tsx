import { usePage } from '@inertiajs/react';

export default function WelcomeFooter() {
    const { name } = usePage().props;

    return (
        <footer className="border-t">
            <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>{name}</p>
                <p>Recordings are published only after review.</p>
            </div>
        </footer>
    );
}
