import { isString } from "../helpers/common";

export const dayString = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

export const fullDayString = [
    "Sunday",
    "Monday",
    "Tueday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
];

export const monthString = [
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

export const fullMonthString = [
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

export const format = (objDate: Date, format: string): string => {
    const result = format;

    const year = objDate.getFullYear().toString();
    const month = (objDate.getMonth() + 1).toString();
    const date = objDate.getDate().toString();
    const day = objDate.getDay().toString();
    const hour = objDate.getHours().toString();
    const min = objDate.getMinutes().toString();
    const sec = objDate.getSeconds().toString();

    const apm = objDate.getHours() >= 12 ? "am" : "pm";
    /* eslint-disable indent */
    const apmhours = (
        objDate.getHours() > 12
            ? objDate.getHours() - 12
            : objDate.getHours() == 0
            ? 12
            : objDate.getHours()
    ).toString();
    /* eslint-enable indent */

    // year
    result.replace(/\byy\b/g, year.slice(-2));
    result.replace(/\byyyy\b/g, year);

    // month
    result.replace(/\bM\b/g, month);
    result.replace(/\bMM\b/g, (0 + month).slice(-2));
    result.replace(/\bMMM\b/g, monthString[month]);
    result.replace(/\bMMMM\b/g, fullMonthString[month]);

    // day
    result.replace(/\bd\b/g, date);
    result.replace(/\bdd\b/g, (0 + date).slice(-2));
    result.replace(/\bddd\b/g, dayString[day]);
    result.replace(/\bdddd\b/g, fullDayString[day]);

    // hour
    result.replace(/\bH\b/g, hour);
    result.replace(/\bHH\b/g, (0 + hour).slice(-2));
    result.replace(/\bh\b/g, apmhours);
    result.replace(/\bhh\b/g, (0 + apmhours).slice(-2));

    // min
    result.replace(/\bm\b/g, min);
    result.replace(/\bmm\b/g, (0 + min).slice(-2));

    // sec
    result.replace(/\bs\b/g, sec);
    result.replace(/\bss\b/g, (0 + sec).slice(-2));

    // am/pm
    result.replace(/\baa\b/g, apm);

    return result;
};

export const timestampUtcToLocal = (utc: string): string => {
    try {
        const iso = new Date(
            utc.replace(
                /([0-9]{4}-[0-9]{2}-[0-9]{2}),? ([0-9]{2}:[0-9]{2}:[0-9]{2})/,
                "$1T$2.000Z"
            )
        );
        return format(iso, "yyyy/MM/dd HH:mm:ss");
    } catch (error) {
        /* empty */
    }

    return "";
};

export const timestampLocalToUtc = (local) => {
    try {
        const d = new Date(local);
        return d
            .toISOString()
            .replace(
                /([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}:[0-9]{2}:[0-9]{2}).*/,
                "$1 $2"
            );
    } catch (error) {
        /* empty */
    }

    return "";
};

export const timestampNowLocal = () => {
    const d = new Date();
    return (
        d.getFullYear() +
        "-" +
        ("0" + (d.getMonth() + 1)).slice(-2) +
        "-" +
        ("0" + d.getDate()).slice(-2) +
        " " +
        ("0" + d.getHours()).slice(-2) +
        ":" +
        ("0" + d.getMinutes()).slice(-2) +
        ":" +
        ("0" + d.getSeconds()).slice(-2)
    );
};

export const timestampNowUtc = () => {
    try {
        const d = new Date();
        return d
            .toISOString()
            .replace(
                /([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}:[0-9]{2}:[0-9]{2}).*/,
                "$1 $2"
            );
    } catch (error) {
        /* empty */
    }

    return "";
};

export const timestampBeforeNow = (timestamp) => {
    try {
        return new Date(timestamp) < new Date();
    } catch (error) {
        /* empty */
    }

    return false;
};

export const timestampAfterNow = (timestamp) => {
    try {
        return new Date(timestamp) > new Date();
    } catch (error) {
        /* empty */
    }

    return false;
};

export const relativeDate = (d) => {
    if (isString(d)) {
        d = new Date(d);
    }

    // const d = new Date(0);
    // // d.setUTCSeconds(parseInt(epoch));
    // d.setUTCSeconds(epoch);

    const now = new Date();
    const dif = Math.round((now.getTime() - d.getTime()) / 1000);

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
        monthString[d.getMonth()] + " " + d.getDate() + ", " + d.getFullYear()
    );
};

export const isValidAusDate = (dateString: string): boolean => {
    const [day, month, year] = dateString.split("/");
    const date = new Date(`${year}-${month}-${day}`);
    return (
        !isNaN(date.getTime()) &&
        date.toISOString().slice(0, 10) === `${year}-${month}-${day}`
    );
};

export const parseAusDate = (dateString: string): Date | null => {
    const [day, month, year] = dateString.split("/");
    const date = new Date(`${year}-${month}-${day}`);
    if (
        !isNaN(date.getTime()) &&
        date.toISOString().slice(0, 10) === `${year}-${month}-${day}`
    ) {
        return null;
    }

    return date;
};

export const isValidTime = (timeString: string): boolean => {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(timeString);
};

export const convertTimeToMinutes = (timeString: string): number => {
    if (isValidTime(timeString)) {
        const [hour, minute] = timeString
            .split(":")
            .map((str) => parseInt(str, 10));
        return hour * 60 + minute;
    }

    return -1;
};

export const createAusDateTimeObject = (
    dateString: string,
    timeString: string
): Date | null => {
    const dateRegex =
        /^(0?[1-9]|[1-2][0-9]|3[0-1])\/(0?[1-9]|1[0-2])\/(19|20)\d{2}$/;
    const timeRegex = /^([01]\d|2[0-3]):[0-5]\d$/;

    if (!dateRegex.test(dateString) || !timeRegex.test(timeString)) {
        return null;
    }

    const [day, month, year] = dateString
        .split("/")
        .map((str) => parseInt(str, 10));
    const [hour, minute] = timeString
        .split(":")
        .map((str) => parseInt(str, 10));
    return new Date(year, month - 1, day, hour, minute);
};

export const parseAusDateTime = (dateTimeStr: string): Date | null => {
    const dateStr = dateTimeStr.split(" ")[0];
    const timeStr = dateTimeStr.split(" ")[1];

    let year: number, month: number, day: number;
    const dateParts = dateStr.split("/");
    if (dateParts[2] && dateParts[2].length === 4) {
        // If year is in yyyy format
        year = +dateParts[2];
        month = +dateParts[1];
        day = +dateParts[0];
    } else {
        // If year is in yy format
        year = +(
            new Date().getFullYear().toString().substr(0, 2) + dateParts[2]
        );
        month = +dateParts[1];
        day = +dateParts[0];
    }

    let hour = 0,
        minute = 0,
        second = 0;
    if (timeStr) {
        const timeParts = timeStr.split(":");
        hour = +timeParts[0];
        minute = +timeParts[1];

        if (timeParts[2]) {
            second = +timeParts[2];
        }

        if (dateTimeStr.toLowerCase().includes("pm") && hour < 12) {
            hour += 12;
        }
    }

    return new Date(year, month - 1, day, hour, minute, second);
};
