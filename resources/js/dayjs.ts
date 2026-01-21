import dayjs from 'dayjs';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import calendar from 'dayjs/plugin/calendar';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';
import relativeTime from 'dayjs/plugin/relativeTime';
import timezone from 'dayjs/plugin/timezone';
import updateLocale from 'dayjs/plugin/updateLocale';
import utc from 'dayjs/plugin/utc';
import weekday from 'dayjs/plugin/weekday';

// Import locales
import 'dayjs/locale/en';
import "dayjs/locale/pt-br"

// Extend dayjs with plugins
dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(calendar);
dayjs.extend(customParseFormat);
dayjs.extend(relativeTime);
dayjs.extend(duration);
dayjs.extend(updateLocale);
dayjs.extend(advancedFormat);
dayjs.extend(weekday);
dayjs.extend(isBetween);

// Set Monday as first day of week (to match Carbon/Laravel)
dayjs.updateLocale('en', {
    weekStart: 1,
});

dayjs.updateLocale('pt-br', {
    weekStart: 1,
});

export default dayjs;
