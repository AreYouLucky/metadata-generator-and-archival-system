import type { ReactNode } from 'react';
import AskMe from '@/components/all/ask-me';
import CatalogueHeader from '@/layouts/catalogue/partials/catalogue-header';

/**
 * The public catalogue (pages/user/*), for guests and `user` accounts: its
 * own header instead of the staff sidebar.
 */
export default function CatalogueLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col text-foreground">
            <CatalogueHeader />
            <main className="w-full flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10 2xl:px-14">
                {children}
            </main>
            <footer className="border-t px-4 py-6 text-center text-xs text-muted-foreground sm:px-6 lg:px-10 2xl:px-14">
                Published records are shared under the licence shown on each
                video.
            </footer>
            <AskMe />
        </div>
    );
}
