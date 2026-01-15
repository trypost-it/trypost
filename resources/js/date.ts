import dayjs from '@/dayjs';

/**
 * Obtém o timezone do usuário
 * Tenta pegar do Inertia page props primeiro, senão usa o timezone do browser
 */
function getUserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

export default {
    formatDate(date: string | null | undefined) {
        if (!date) return '-';
        return dayjs(date).format('DD/MM/YYYY');
    },

    formatDateTime(date: string) {
        return dayjs
            .utc(date)
            .tz(getUserTimezone())
            .format('D [de] MMM [de] YYYY [às] HH:mm');
    },

    formatTime(date: string | null | undefined) {
        if (!date) return '-';
        return dayjs.utc(date).tz(getUserTimezone()).format('HH:mm');
    },

    formatDateTimeForApi(date: string) {
        // Convert from user timezone to UTC for API
        return dayjs
            .tz(date, getUserTimezone())
            .utc()
            .format('YYYY-MM-DD HH:mm:ss');
    },

    formatDateTimeForDatePicker(date: string) {
        // Convert from UTC to user timezone for display
        if (!date) return dayjs().tz(getUserTimezone());
        return dayjs.utc(date).tz(getUserTimezone());
    },

    diffForHumans(date: string) {
        const localDate = dayjs
            .utc(date)
            .tz(getUserTimezone())
            .format('YYYY-MM-DD HH:mm:ss');
        return dayjs().to(dayjs(localDate));
    },

    formatTimelineDate(dateStr: string) {
        const d = dayjs(dateStr);
        return {
            day: d.date(),
            month: d.format('MMM'),
            year: d.year(),
        };
    },

    formatDuration(duration: string) {
        // Duration comes in format "HH:mm:ss"
        const [hours, minutes, seconds] = duration.split(':');
        const h = parseInt(hours, 10);
        const m = parseInt(minutes, 10);

        if (h > 0) {
            return `${h}h ${m}min`;
        }
        return `${m}min`;
    },

    formatMedicalRecordDuration(startAt: string, duration: string | null) {
        const startTime = this.formatTime(startAt);

        if (!duration) {
            return startTime;
        }

        const formattedDuration = this.formatDuration(duration);
        return `${startTime} (${formattedDuration})`;
    },

    /**
     * Converte horário de appointment de UTC para timezone do usuário
     * Específico para agenda onde date e time vêm separados do banco
     * @param date - Data no formato YYYY-MM-DD
     * @param time - Horário no formato HH:mm:ss
     * @returns Horário formatado no timezone do usuário (HH:mm)
     */
    convertAppointmentTimeToUserTimezone(date: string, time: string): string {
        return dayjs
            .utc(`${date} ${time}`)
            .tz(getUserTimezone())
            .format('HH:mm');
    },

    /**
     * Converte horário de appointment de UTC para timezone do usuário e retorna objeto dayjs
     * Útil para cálculos de posicionamento na agenda
     * @param date - Data no formato YYYY-MM-DD
     * @param time - Horário no formato HH:mm:ss
     * @returns Objeto dayjs no timezone do usuário
     */
    getAppointmentDateTimeInUserTimezone(date: string, time: string) {
        return dayjs.utc(`${date} ${time}`).tz(getUserTimezone());
    },

    /**
     * Formata o nome do mês e ano
     * @param month - Número do mês (1-12)
     * @param year - Ano (ex: 2025)
     * @returns String formatada (ex: "Fev/2025")
     */
    formatMonthYear(month: number, year: number): string {
        return dayjs(new Date(year, month - 1, 1)).format('MMM/YYYY');
    },

    formatAge(birthDate: string): string {
        return dayjs().from(dayjs(birthDate), true);
    },

    /**
     * Formata tempo decorrido em segundos para formato de stopwatch HH:MM:SS
     * @param seconds - Tempo decorrido em segundos
     * @returns String formatada (ex: "02:30:45", "00:05:12")
     */
    formatStopwatch(seconds: number): string {
        return dayjs.duration(seconds, 'seconds').format('HH:mm:ss');
    },

    /**
     * Calcula tempo decorrido entre uma data UTC e o momento atual no timezone do usuário
     * @param startDateTime - Data/hora de início em UTC (ISO string)
     * @returns Tempo decorrido em segundos
     */
    getElapsedSeconds(startDateTime: string): number {
        const startTime = dayjs.utc(startDateTime).tz(getUserTimezone());
        const now = dayjs().tz(getUserTimezone());
        return now.diff(startTime, 'seconds');
    },

    /**
     * Formata a data de build da aplicação no timezone do usuário
     * @param date - Data/hora em ISO string (UTC)
     * @returns String formatada (ex: "31/12/2025 14:25")
     */
    formatBuildDate(date: string): string {
        return dayjs.utc(date).tz(getUserTimezone()).format('DD/MM/YYYY HH:mm');
    },

    /**
     * Obtém o timezone do usuário
     * Tenta pegar do Inertia page props primeiro, senão usa o timezone do browser
     * @returns Timezone do usuário
     */
    getUserTimezone,

    /**
     * Formata uma data para o formato YYYY-MM-DD (usado em DatePicker)
     * Evita problemas de timezone ao não criar objeto Date
     * @param date - Data no formato YYYY-MM-DD ou ISO string
     * @returns Data no formato YYYY-MM-DD
     */
    formatDateForInput(date: string): string {
        return dayjs(date).format('YYYY-MM-DD');
    },

    /**
     * Formata minutos para formato legível
     * @param minutes - Número de minutos
     * @returns String formatada (ex: "1h", "1h 41min", "30min")
     */
    formatMinutes(minutes: number): string {
        const duration = dayjs.duration(minutes, 'minutes');
        const h = duration.hours();
        const m = duration.minutes();

        if (h > 0 && m > 0) {
            return `${h}h ${m}min`;
        }
        if (h > 0) {
            return `${h}h`;
        }
        return `${m}min`;
    },
};
