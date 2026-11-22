import BrandLogo from '@/components/all/brand-logo';

/**
 * The staff navigation's logo: the KeepFrame wordmark, or just the mark when
 * the sidebar is collapsed to icons.
 */
export default function AppLogo() {
    return (
        <>
            <BrandLogo
                variant="compact"
                className="h-7 group-data-[collapsible=icon]:hidden"
            />
            <BrandLogo
                variant="mark"
                alt="KeepFrame"
                className="hidden size-7 group-data-[collapsible=icon]:block"
            />
        </>
    );
}
