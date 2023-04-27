export class SMDate {
    date: Date | null = null;
    dayString: string[] = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

    fullDayString: string[] = [
        "Sunday",
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
    ];

    monthString: string[] = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
    ];

    fullMonthString: string[] = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    ];

    constructor(
        dateOrString: string | Date = "",
        options: { format?: string; utc?: boolean } = {}
    ) {
        this.date = null;

        if (typeof dateOrString === "string") {
            if (dateOrString.length > 0) {
                this.parse(dateOrString, options);
            }
        } else if (
            dateOrString instanceof Date &&
            !Number.isNaN(dateOrString.getTime())
        ) {
            this.date = dateOrString;
        }
    }

    /**
     * Parse a string date into a Date object
     *
     * @param {string} dateString The date string.
     * @param {object} options (optional) Options object.
     * @param {string} options.format (optional) The format of the date string.
     * @param {boolean} options.utc (optional) The date string is UTC.
     * @returns {SMDate} SMDate object.
     */
    public parse(
        dateString: string,
        { format = "dmy", utc = false } = {}
    ): SMDate {
        const now = new Date();
        let time = "";

        if (dateString.toLowerCase() === "now") {
            this.date = now;
            return this;
        }

        // Cache regular expressions
        const isoDateRegex =
            /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,10})?Z$/i;
        const timeRegex =
            /^(\d+)(?::(\d+))?(?::(\d+))? ?(am?|a\.m\.|pm?|p\.m\.)?$/i;

        // Test if the dateString is in ISO 8601
        if (isoDateRegex.test(dateString)) {
            format = "YMd";
            [dateString, time] = dateString.split("T");
            time = time.slice(0, -8);
        }

        // Split the date string into an array of components based on the length of each date component
        const components = dateString.split(/[ /-]/);

        for (const component of components) {
            if (isNaN(parseInt(component))) {
                return this;
            }
            if (component.includes(":")) {
                time = component;
                const index = components.indexOf(component);
                if (
                    index < components.length - 1 &&
                    /^(am?|a\.m\.|pm?|p\.m\.)$/i.test(components[index + 1])
                ) {
                    time += " " + components[index + 1].toUpperCase();
                    components.splice(index + 1, 1);
                }
                components.splice(index, 1);
                break;
            }
        }

        const [day, month, year] =
            format === "dmy"
                ? components
                : format === "mdy"
                ? [components[1], components[0], components[2]]
                : [components[2], components[1], components[0]];

        if (year === undefined || year.length === 3 || year.length >= 5) {
            return this;
        }

        // numeric
        for (const component of [day, month, year]) {
            if (isNaN(parseInt(component))) {
                return this;
            }
        }

        const parsedDay = parseInt(day.padStart(2, "0"), 10);
        const parsedMonth = this.getMonthAsNumber(month);
        const parsedYear = parseInt(year.padStart(4, "20"), 10);
        let parsedHours: number = 0,
            parsedMinutes: number = 0,
            parsedSeconds: number = 0;

        const parsedTime = timeRegex.exec(time);
        if (time && parsedTime) {
            const [_, hourStr, minuteStr, secondStr, ampm] = parsedTime;
            parsedHours = parseInt(hourStr);
            parsedMinutes = parseInt(minuteStr || "0");
            parsedSeconds = parseInt(secondStr || "0");

            if (parsedHours < 0 || parsedHours > 23) {
                return this;
            }

            if (ampm) {
                if (/pm/i.test(ampm) && parsedHours < 12) {
                    parsedHours += 12;
                } else if (/am/i.test(ampm) && parsedHours === 12) {
                    parsedHours = 0;
                }
            }

            if (
                parsedMinutes < 0 ||
                parsedMinutes > 59 ||
                parsedSeconds < 0 ||
                parsedSeconds > 59
            ) {
                return this;
            }

            time = `${parsedHours.toString().padStart(2, "0")}:${parsedMinutes
                .toString()
                .padStart(2, "0")}:${parsedSeconds
                .toString()
                .padStart(2, "0")}`;
        } else {
            time = "00:00:00";
        }

        const date = utc
            ? new Date(
                  Date.UTC(
                      parsedYear,
                      parsedMonth - 1,
                      parsedDay,
                      parsedHours,
                      parsedMinutes,
                      parsedSeconds
                  )
              )
            : new Date(
                  parsedYear,
                  parsedMonth - 1,
                  parsedDay,
                  parsedHours,
                  parsedMinutes,
                  parsedSeconds
              );

        if (isNaN(date.getTime())) {
            return this;
        }

        if (utc) {
            const isoDate = date.toISOString();
            const checkYear = parseInt(isoDate.substring(0, 4), 10);
            const checkMonth = parseInt(isoDate.substring(5, 7), 10);
            const checkDay = new Date(isoDate).getUTCDate();
            const checkHours = parseInt(isoDate.substring(11, 13), 10);
            const checkMinutes = parseInt(isoDate.substring(14, 16), 10);
            const checkSeconds = parseInt(isoDate.substring(17, 19), 10);
            if (
                checkYear !== parsedYear ||
                checkMonth !== parsedMonth ||
                checkDay !== parsedDay ||
                checkHours !== parsedHours ||
                checkMinutes !== parsedMinutes ||
                checkSeconds !== parsedSeconds
            ) {
                return this;
            }
        } else {
            if (
                date.getFullYear() !== parsedYear ||
                date.getMonth() + 1 !== parsedMonth ||
                date.getDate() !== parsedDay ||
                date.getHours() !== parsedHours ||
                date.getMinutes() !== parsedMinutes ||
                date.getSeconds() !== parsedSeconds
            ) {
                return this;
            }
        }

        this.date = date;
        return this;
    }

    /**
     * Format the date to a string.
     *
     * @param {string} format The format to return.
     * @param {object} options (optional) Function options.
     * @param {boolean} options.utc (optional) Format the date to be as UTC instead of local.
     * @returns {string} The formatted date.
     */
    public format(format: string, options: { utc?: boolean } = {}): string {
        if (this.date == null) {
            return "";
        }

        let result = format;

        let year: string,
            month: string,
            date: string,
            day: number,
            hour: string,
            min: string,
            sec: string;
        if (options.utc) {
            const isoDate = this.date.toISOString();
            year = isoDate.substring(0, 4);
            month = isoDate.substring(5, 7);
            date = isoDate.substring(8, 10);
            day = new Date(isoDate).getUTCDay();
            hour = isoDate.substring(11, 13);
            min = isoDate.substring(14, 16);
            sec = isoDate.substring(17, 19);
        } else {
            year = this.date.getFullYear().toString();
            month = (this.date.getMonth() + 1).toString();
            date = this.date.getDate().toString();
            day = this.date.getDay();
            hour = this.date.getHours().toString();
            min = this.date.getMinutes().toString();
            sec = this.date.getSeconds().toString();
        }

        const apm = parseInt(hour, 10) >= 12 ? "pm" : "am";
        /* eslint-disable indent */
        const apmhours = (
            parseInt(hour, 10) > 12
                ? parseInt(hour, 10) - 12
                : parseInt(hour, 10) == 0
                ? 12
                : parseInt(hour, 10)
        ).toString();
        /* eslint-enable indent */

        // year
        result = result.replace(/\byy\b/g, year.slice(-2));
        result = result.replace(/\byyyy\b/g, year);

        // month
        result = result.replace(/\bM\b/g, month);
        result = result.replace(/\bMM\b/g, (0 + month).slice(-2));
        result = result.replace(
            /\bMMM\b/g,
            this.monthString[parseInt(month) - 1]
        );
        result = result.replace(
            /\bMMMM\b/g,
            this.fullMonthString[parseInt(month) - 1]
        );

        // day
        result = result.replace(/\bd\b/g, date);
        result = result.replace(/\bdd\b/g, (0 + date).slice(-2));
        result = result.replace(/\bEEE\b/g, this.dayString[day]);
        result = result.replace(/\bEEEE\b/g, this.fullDayString[day]);

        // hour
        result = result.replace(/\bH\b/g, hour);
        result = result.replace(/\bHH\b/g, (0 + hour).slice(-2));
        result = result.replace(/\bh\b/g, apmhours);
        result = result.replace(/\bhh\b/g, (0 + apmhours).slice(-2));

        // min
        result = result.replace(/\bm\b/g, min);
        result = result.replace(/\bmm\b/g, (0 + min).slice(-2));

        // sec
        result = result.replace(/\bs\b/g, sec);
        result = result.replace(/\bss\b/g, (0 + sec).slice(-2));

        // am/pm
        result = result.replace(/\baa\b/g, apm);

        return result;
    }

    /**
     * Return a relative date string from now.
     *
     * @returns {string} A relative date string.
     */
    public relative(): string {
        if (this.date === null) {
            return "";
        }

        const now = new Date();
        const dif = Math.round((now.getTime() - this.date.getTime()) / 1000);

        if (dif < 60) {
            return "Just now";
        } else if (dif < 3600) {
            const v = Math.round(dif / 60);
            return `${v} min${v != 1 ? "s" : ""} ago`;
        } else if (dif < 86400) {
            const v = Math.round(dif / 3600);
            return `${v} hour${v != 1 ? "s" : ""} ago`;
        } else if (dif < 604800) {
            const v = Math.round(dif / 86400);
            return `${v} day${v != 1 ? "s" : ""} ago`;
        } else if (dif < 2419200) {
            const v = Math.round(dif / 604800);
            return `${v} week${v != 1 ? "s" : ""} ago`;
        } else {
            return (
                this.monthString[this.date.getMonth()] +
                " " +
                this.date.getDate() +
                ", " +
                this.date.getFullYear()
            );
        }
    }

    /**
     * If the date is before the passed date.
     *
     * @param {Date|SMDate} d (optional) The date to check. If none, use now
     * @returns {boolean} If the date is before the passed date.
     */
    public isBefore(d: Date | SMDate = new SMDate("now")): boolean {
        const otherDate = d instanceof SMDate ? d.date : d;
        if (otherDate == null) {
            return false;
        }

        if (this.date == null) {
            return true;
        }

        return otherDate > this.date;
    }

    /**
     * If the date is after the passed date.
     *
     * @param {Date|SMDate} d (optional) The date to check. If none, use now
     * @returns {boolean} If the date is after the passed date.
     */
    public isAfter(d: Date | SMDate = new SMDate("now")): boolean {
        const otherDate = d instanceof SMDate ? d.date : d;
        if (otherDate == null) {
            return false;
        }

        if (this.date == null) {
            return true;
        }

        return otherDate < this.date;
    }

    /**
     * Return a month number from a string or a month number or month name
     *
     * @param {string} monthString The month string as number or name
     * @returns {number} The month number
     */
    private getMonthAsNumber(monthString: string): number {
        const months = this.fullMonthString.map((month) => month.toLowerCase());

        const shortMonths = months.map((month) => month.slice(0, 3));
        const monthIndex = months.indexOf(monthString.toLowerCase());
        if (monthIndex !== -1) {
            return monthIndex + 1;
        }
        const shortMonthIndex = shortMonths.indexOf(monthString.toLowerCase());
        if (shortMonthIndex !== -1) {
            return shortMonthIndex + 1;
        }
        const monthNumber = parseInt(monthString, 10);
        if (!isNaN(monthNumber) && monthNumber >= 1 && monthNumber <= 12) {
            return monthNumber;
        }

        return 0;
    }

    /**
     * Test if the current date is valid.
     *
     * @returns {boolean} If the current date is valid.
     */
    public isValid(): boolean {
        return this.date !== null;
    }

    /**
     * Return a string with only the first occurrence of characters
     *
     * @param {string} str The string to modify.
     * @param {string} characters The characters to use to test.
     * @returns {string} A string that only contains the first occurrence of the characters.
     */
    private onlyFirstOccurrence(
        str: string,
        characters: string = "dMy"
    ): string {
        let findCharacters = characters.split("");
        const replaceRegex = new RegExp("[^" + characters + "]", "g");
        let result = "";

        str = str.replace(replaceRegex, "");
        if (str.length > 0) {
            str.split("").forEach((strChar) => {
                if (
                    findCharacters.length > 0 &&
                    findCharacters.includes(strChar)
                ) {
                    result += strChar;

                    const index = findCharacters.findIndex(
                        (findChar) => findChar === strChar
                    );
                    if (index !== -1) {
                        findCharacters = findCharacters
                            .slice(0, index)
                            .concat(findCharacters.slice(index + 1));
                    }
                }
            });
        }

        return result;
    }
}
