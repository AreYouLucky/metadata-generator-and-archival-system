/**
 * Text with every case-insensitive match of `query` wrapped in <mark>.
 */
export default function HighlightedText({
    text,
    query,
}: {
    text: string;
    query: string;
}) {
    const needle = query.trim();

    if (needle === '') {
        return <>{text}</>;
    }

    const parts = text.split(
        new RegExp(`(${needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'),
    );

    return (
        <>
            {parts.map((part, index) =>
                index % 2 === 1 ? (
                    <mark
                        key={index}
                        className="rounded bg-amber-200 px-0.5 text-inherit dark:bg-amber-500/40"
                    >
                        {part}
                    </mark>
                ) : (
                    part
                ),
            )}
        </>
    );
}
