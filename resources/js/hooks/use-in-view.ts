import { useEffect, useRef, useState } from 'react';

/**
 * Whether the element has scrolled into view. Stays true once seen, so
 * entrance animations run a single time.
 */
export function useInView<T extends Element>(threshold = 0.15) {
    const ref = useRef<T>(null);
    const [isInView, setIsInView] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element || isInView) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            setIsInView(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsInView(true);
                    observer.disconnect();
                }
            },
            { threshold, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [isInView, threshold]);

    return { ref, isInView };
}
