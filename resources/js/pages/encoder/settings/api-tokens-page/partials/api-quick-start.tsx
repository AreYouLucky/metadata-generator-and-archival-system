import type { ApiLimits } from '@/types/api-tokens';

type Props = {
    baseUrl: string;
    limits: ApiLimits;
};

/**
 * The four endpoints with copy-ready examples. Static text: nothing here
 * comes from records or users.
 */
export default function ApiQuickStart({ baseUrl, limits }: Props) {
    const examples = [
        {
            title: 'Create a record from a file',
            code: `curl -X POST ${baseUrl}/records \\
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -F file=@interview.mp4 -F project_id=PROJECT_ID \\
  -F webhook_url=https://example.org/hooks/archive`,
        },
        {
            title: 'Or import from a web address',
            code: `curl -X POST ${baseUrl}/records \\
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -d url=https://www.youtube.com/watch?v=... -d project_id=PROJECT_ID`,
        },
        {
            title: 'Status, stages and review',
            code: `curl ${baseUrl}/records/RECORD_ID -H "Authorization: Bearer $TOKEN"`,
        },
        {
            title: 'Export (approved, public records only)',
            code: `curl "${baseUrl}/records/RECORD_ID/export?format=json" -H "Authorization: Bearer $TOKEN"`,
        },
    ];

    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-medium">Quick start</h3>
            <p className="text-sm text-muted-foreground">
                Base address <code className="text-xs">{baseUrl}</code>. Up to{' '}
                {limits.requests_per_minute} requests a minute per token; files
                sent directly up to {limits.max_upload_mb} MB (import bigger
                videos from a web address). <code>GET /records</code> lists
                yours, paginated.
            </p>
            {examples.map((example) => (
                <div key={example.title} className="grid gap-1">
                    <p className="text-xs font-medium text-muted-foreground">
                        {example.title}
                    </p>
                    <pre className="overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs leading-relaxed">
                        {example.code}
                    </pre>
                </div>
            ))}
        </section>
    );
}
