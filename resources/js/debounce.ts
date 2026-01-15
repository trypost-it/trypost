export default function debounce(
    callback: (...args: any[]) => void,
    wait = 1000,
) {
    let timeoutId: NodeJS.Timeout | null = null;

    const debouncedFn = (...args: any[]) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            callback(...args);
        }, wait);
    };

    debouncedFn.cancel = () => {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };

    return debouncedFn;
}
