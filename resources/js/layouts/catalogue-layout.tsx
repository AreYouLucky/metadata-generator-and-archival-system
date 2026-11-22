import type { ReactNode } from 'react';
import AskMe from '@/components/all/ask-me';
import CatalogueHeader from '@/layouts/catalogue/partials/catalogue-header';

/**
 * The public catalogue (pages/user/*), for guests and `user` accounts: its
 * own header instead of the staff sidebar.
 */
export default function CatalogueLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <CatalogueHeader />
            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:py-10">
                {children}
            </main>
            <footer className="border-t py-6 text-center text-xs text-muted-foreground">
                Published records are shared under the licence shown on each
                video.
            </footer>
            <AskMe />
        </div>
    );
}
