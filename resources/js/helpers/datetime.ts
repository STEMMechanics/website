import { transformWithEsbuild } from "vite";

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
        options: { format?: string; utc?: boolean } = {}
    ): SMDate {
        const now = new Date();

        if (dateString.toLowerCase() == "now") {
            this.date = now;
            return this;
        }

        // Parse the date format to determine the order of the date components
        const order = (options.format || "dmy").toLowerCase().split("");
        options.utc = options.utc || false;

        // Split the date string into an array of components based on the length of each date component
        const components = dateString.split(/[ /-]/);
        let time = "";

        for (let i = 0; i < components.length; i++) {
            if (components[i].includes(":")) {
                time = components[i];
                components.splice(i, 1);
                if (
                    i < components.length &&
                    /^(am?|a\.m\.|pm?|p\.m\.)$/i.test(components[i])
                ) {
                    time += " " + components[i].toUpperCase();
                    components.splice(i, 1);
                }
                break;
            }
        }

        if (components.every((v) => !isNaN(parseInt(v))) == false) {
            return this;
        }

        if (components.length > 3) {
            return this;
        }

        // Map the date components to the expected order based on the format
        const [day, month, year] =
            order[0] === "d"
                ? [components[0], components[1], components[2]]
                : order[0] === "m"
                ? [components[1], components[0], components[2]]
                : [components[2], components[1], components[0]];

        let parsedDay: number = 0,
            parsedMonth: number = 0,
            parsedYear: number = 0;

        if (year.length == 3 || year.length >= 5) {
            return this;
        }

        if (day && day.length != 0 && month && month.length != 0) {
            // Parse the day, month, and year components
            parsedDay = parseInt(day.padStart(2, "0"), 10);
            parsedMonth = this.getMonthAsNumber(month);
            parsedYear = year
                ? parseInt(year.padStart(4, "20"), 10)
                : now.getFullYear();
        } else {
            parsedDay = now.getDate();
            parsedMonth = now.getMonth() + 1;
            parsedYear = now.getFullYear();
        }

        let parsedHours: number = 0,
            parsedMinutes: number = 0,
            parsedSeconds: number = 0;
        if (time) {
            const regEx = new RegExp(
                /^(\d+)(?::(\d+))?(?::(\d+))? ?(am?|a\.m\.|pm?|p\.m\.)?$/,
                "i"
            );
            if (regEx.test(time)) {
                const match = time.match(regEx);
                if (match) {
                    parsedHours = parseInt(match[1]);
                    parsedMinutes = match[2] ? parseInt(match[2]) : 0;
                    parsedSeconds = match[3] ? parseInt(match[3]) : 0;
                    if (match[4] && /pm/i.test(match[4])) {
                        parsedHours += 12;
                    }
                    if (
                        match[4] &&
                        /am/i.test(match[4]) &&
                        parsedHours === 12
                    ) {
                        parsedHours = 0;
                    }
                } else {
                    return this;
                }
            } else {
                return this;
            }
        }

        // Create a date object with the parsed components
        let date: Date | null = null;
        if (options.utc) {
            date = new Date(
                Date.UTC(
                    parsedYear,
                    parsedMonth - 1,
                    parsedDay,
                    parsedHours,
                    parsedMinutes,
                    parsedSeconds
                )
            );
        } else {
            date = new Date(
                parsedYear,
                parsedMonth - 1,
                parsedDay,
                parsedHours,
                parsedMinutes,
                parsedSeconds
            );
        }

        // Test created date object
        let checkYear: number,
            checkMonth: number,
            checkDay: number,
            checkHours: number,
            checkMinutes: number,
            checkSeconds: number;
        if (options.utc) {
            const isoDate = date.toISOString();
            checkYear = parseInt(isoDate.substring(0, 4), 10);
            checkMonth = parseInt(isoDate.substring(5, 7), 10);
            checkDay = new Date(isoDate).getUTCDate();
            checkHours = parseInt(isoDate.substring(11, 13), 10);
            checkMinutes = parseInt(isoDate.substring(14, 16), 10);
            checkSeconds = parseInt(isoDate.substring(17, 18), 10);
        } else {
            checkYear = date.getFullYear();
            checkMonth = date.getMonth() + 1;
            checkDay = date.getDate();
            checkHours = date.getHours();
            checkMinutes = date.getMinutes();
            checkSeconds = date.getSeconds();
        }

        if (
            Number.isNaN(date.getTime()) == false &&
            checkYear == parsedYear &&
            checkMonth == parsedMonth &&
            checkDay == parsedDay &&
            checkHours == parsedHours &&
            checkMinutes == parsedMinutes &&
            checkSeconds == parsedSeconds
        ) {
            this.date = date;
        } else {
            this.date = null;
        }

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
            sec = isoDate.substring(17, 18);
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
            // let v = dif;
            // return v + " sec" + (v != 1 ? "s" : "") + " ago";
            return "Just now";
        } else if (dif < 3600) {
            const v = Math.round(dif / 60);
            return v + " min" + (v != 1 ? "s" : "") + " ago";
        } else if (dif < 86400) {
            const v = Math.round(dif / 3600);
            return v + " hour" + (v != 1 ? "s" : "") + " ago";
        } else if (dif < 604800) {
            const v = Math.round(dif / 86400);
            return v + " day" + (v != 1 ? "s" : "") + " ago";
        } else if (dif < 2419200) {
            const v = Math.round(dif / 604800);
            return v + " week" + (v != 1 ? "s" : "") + " ago";
        }

        return (
            this.monthString[this.date.getMonth()] +
            " " +
            this.date.getDate() +
            ", " +
            this.date.getFullYear()
        );
    }

    /**
     * If the date is before the passed date.
     *
     * @param {Date|SMDate} d (optional) The date to check. If none, use now
     * @returns {boolean} If the date is before the passed date.
     */
    public isBefore(d: Date | SMDate = new Date()): boolean {
        const otherDate = d instanceof SMDate ? d.date : d;
        if (otherDate == null) {
            return false;
        }

        return otherDate < otherDate;
    }

    /**
     * If the date is after the passed date.
     *
     * @param {Date|SMDate} d (optional) The date to check. If none, use now
     * @returns {boolean} If the date is after the passed date.
     */
    public isAfter(d: Date | SMDate = new Date()): boolean {
        const otherDate = d instanceof SMDate ? d.date : d;
        if (otherDate == null) {
            return false;
        }

        return otherDate > otherDate;
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
