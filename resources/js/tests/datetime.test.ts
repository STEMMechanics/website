import { expect, describe, it } from "vitest";
import { format } from "../helpers/datetime";

describe("format()", () => {
    it("should return an empty string when the first argument is not a Date object", () => {
        const result = format("not a date", "yyyy-MM-dd");
        expect(result).toEqual("");
    });

    it("should format the date correctly", () => {
        const date = new Date("2022-02-19T12:34:56");
        const result = format(date, "yyyy-MM-dd HH:mm:ss");
        expect(result).toEqual("2022-02-19 12:34:56");
    });

    it("should handle single-digit month and day", () => {
        const date = new Date("2022-01-01T00:00:00");
        const result = format(date, "yy-M-d");
        expect(result).toEqual("22-1-1");
    });

    it("should handle day of week and month name abbreviations", () => {
        const date = new Date("2022-03-22T00:00:00");
        const result = format(date, "EEE, MMM dd, yyyy");
        expect(result).toEqual("Tue, Mar 22, 2022");
    });

    it("should handle 12-hour clock with am/pm", () => {
        const date = new Date("2022-01-01T12:34:56");
        const result = format(date, "hh:mm:ss aa");
        expect(result).toEqual("12:34:56 pm");
    });
});
