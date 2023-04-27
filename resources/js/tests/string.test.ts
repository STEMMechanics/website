import { expect, describe, it } from "vitest";
import { toTitleCase } from "../helpers/string";

describe("toTitleCase()", () => {
    it("should return a converted title case string", () => {
        const result = toTitleCase("titlecase");
        expect(result).toEqual("Titlecase");
    });

    it("should return a converted title case string and spaces", () => {
        const result = toTitleCase("titlecase_and_more");
        expect(result).toEqual("Titlecase And More");
    });
});
