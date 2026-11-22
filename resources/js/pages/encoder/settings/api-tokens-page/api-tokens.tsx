import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index } from '@/routes/api-tokens';
import type {
    ApiLimits,
    ApiToken,
    ApiTokenAbilityOption,
} from '@/types/api-tokens';
import ApiQuickStart from './partials/api-quick-start';
import CreateTokenDialog from './partials/create-token-dialog';
import TokenRow from './partials/token-row';
import WebhookSecretCard from './partials/webhook-secret-card';

type Props = {
    tokens: ApiToken[];
    abilities: ApiTokenAbilityOption[];
    expiryDays: number[];
    hasWebhookSecret: boolean;
    apiBaseUrl: string;
    limits: ApiLimits;
};

/**
 * Settings → API tokens: personal tokens for the public API, the webhook
 * signing secret, and a quick start.
 */
export default function ApiTokens({
    tokens,
    abilities,
    expiryDays,
    hasWebhookSecret,
    apiBaseUrl,
    limits,
}: Props) {
    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="API tokens"
                        description="Let other systems create records, follow their processing and download exports."
                    />
                    <CreateTokenDialog
                        abilities={abilities}
                        expiryDays={expiryDays}
                    />
                </div>

                {tokens.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No tokens yet.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {tokens.map((token) => (
                            <TokenRow key={token.id} token={token} />
                        ))}
                    </ul>
                )}

                <WebhookSecretCard hasSecret={hasWebhookSecret} />

                <ApiQuickStart baseUrl={apiBaseUrl} limits={limits} />
            </div>
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [{ title: 'API tokens', href: index() }],
};
