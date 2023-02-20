import { expect, describe, it } from "vitest";
import { Email } from "../helpers/validate";

describe("Email()", () => {
    it("should return valid=false when an invalid email address is passed to the validate function", () => {
        const v = Email();
        const result = v.validate("invalid email");
        expect(result.valid).toBe(false);
    });

    it("should return valid=false when an invalid email address is passed to the validate function", () => {
        const v = Email();
        const result = v.validate("fake@outlook");
        expect(result.valid).toBe(false);
    });

    it("should return valid=true when an valid email address is passed to the validate function", () => {
        const v = Email();
        const result = v.validate("fake@outlook.com");
        expect(result.valid).toBe(true);
    });

    it("should return valid=true when an valid email address is passed to the validate function", () => {
        const v = Email();
        const result = v.validate("fake@outlook.com.au");
        expect(result.valid).toBe(true);
    });
});
