/**
 * Best-effort browser/OS detection from a user-agent string.
 *
 * Order matters: more specific identifiers (Edge, Opera, Samsung, etc.) must
 * be checked before the engines they piggyback on (Chrome, Safari).
 */

export const parseBrowserName = (userAgent: string | null): string => {
    if (!userAgent) {
        return 'Unknown browser';
    }

    if (
        userAgent.includes('Edg/') ||
        userAgent.includes('EdgiOS/') ||
        userAgent.includes('EdgA/')
    ) {
        return 'Edge';
    }

    if (userAgent.includes('OPR/') || userAgent.includes('Opera/')) {
        return 'Opera';
    }

    if (userAgent.includes('Vivaldi/')) {
        return 'Vivaldi';
    }

    if (userAgent.includes('SamsungBrowser/')) {
        return 'Samsung Internet';
    }

    if (userAgent.includes('UCBrowser/')) {
        return 'UC Browser';
    }

    if (userAgent.includes('YaBrowser/')) {
        return 'Yandex';
    }

    if (userAgent.includes('DuckDuckGo/') || userAgent.includes('Ddg/')) {
        return 'DuckDuckGo';
    }

    if (userAgent.includes('FxiOS/') || userAgent.includes('Firefox/')) {
        return 'Firefox';
    }

    if (userAgent.includes('CriOS/') || userAgent.includes('Chrome/')) {
        return 'Chrome';
    }

    if (userAgent.includes('Safari/')) {
        return 'Safari';
    }

    if (userAgent.includes('Trident/') || userAgent.includes('MSIE ')) {
        return 'Internet Explorer';
    }

    return 'Unknown browser';
};

export const parseOsName = (userAgent: string | null): string | null => {
    if (!userAgent) {
        return null;
    }

    if (/iPhone|iPad|iPod/.test(userAgent)) {
        return 'iOS';
    }

    if (/Android/.test(userAgent)) {
        return 'Android';
    }

    if (/Windows Phone/.test(userAgent)) {
        return 'Windows Phone';
    }

    if (/Windows NT/.test(userAgent)) {
        return 'Windows';
    }

    if (/Mac OS X|Macintosh/.test(userAgent)) {
        return 'macOS';
    }

    if (/CrOS/.test(userAgent)) {
        return 'ChromeOS';
    }

    if (/FreeBSD|OpenBSD|NetBSD/.test(userAgent)) {
        return 'BSD';
    }

    if (/Linux/.test(userAgent)) {
        return 'Linux';
    }

    return null;
};

export const isMobileDevice = (userAgent: string | null): boolean => {
    if (!userAgent) {
        return false;
    }

    return /Mobile|Android|iPhone|iPad|iPod|Windows Phone|BlackBerry|BB10|webOS|Opera Mini|IEMobile/i.test(
        userAgent,
    );
};
