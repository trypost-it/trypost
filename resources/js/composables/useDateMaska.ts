export function useDateMaska() {
    const dateOptions = {
        'data-maska': '##/##/####',
        'data-maska-tokens': '#:[0-9]',
    };

    return {
        dateOptions,
    };
}
