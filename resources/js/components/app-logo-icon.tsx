import type { SVGAttributes } from 'react';

/**
 * A film frame with sprocket holes and a play mark: one archived recording.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M8 2h24a6 6 0 0 1 6 6v24a6 6 0 0 1-6 6H8a6 6 0 0 1-6-6V8a6 6 0 0 1 6-6Zm3 7h18v22H11V9Zm6 6.5v9l7.5-4.5L17 15.5ZM5 8h3v3H5V8Zm0 7h3v3H5v-3Zm0 7h3v3H5v-3Zm0 7h3v3H5v-3Zm27-21h3v3h-3V8Zm0 7h3v3h-3v-3Zm0 7h3v3h-3v-3Zm0 7h3v3h-3v-3Z"
            />
        </svg>
    );
}
