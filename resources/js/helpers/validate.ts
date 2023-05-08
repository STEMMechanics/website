import { bytesReadable } from "../helpers/types";
import { SMDate } from "./datetime";
import { isEmpty } from "../helpers/utils";

export interface ValidationObject {
    validate: (value: unknown) => Promise<ValidationResult>;
}

export interface ValidationResult {
    valid: boolean;
    invalidMessages: Array<string>;
}

export const defaultValidationResult: ValidationResult = {
    valid: true,
    invalidMessages: [],
};

export const createValidationResult = (
    valid: boolean,
    message: string | Array<string> = ""
) => {
    if (typeof message == "string") {
        message = [message];
    }

    return {
        valid: valid,
        invalidMessages: message,
    };
};

/**
 * Validation Min
 */
const VALIDATION_MIN_TYPE = ["String", "Number"];
type ValidationMinType = (typeof VALIDATION_MIN_TYPE)[number];

interface ValidationMinOptions {
    min: number;
    type?: ValidationMinType;
    invalidMessage?: string | ((options: ValidationMinOptions) => string);
}

interface ValidationMinObject extends ValidationMinOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationMinOptions: ValidationMinOptions = {
    min: 1,
    type: "String",
    invalidMessage: (options: ValidationMinOptions) => {
        return options.type == "String"
            ? `Required to be at least ${options.min} characters.`
            : `Required to be at least ${options.min}.`;
    },
};

export function Min(
    minOrOptions: number | ValidationMinOptions,
    options?: ValidationMinOptions
);
export function Min(options: ValidationMinOptions): ValidationMinObject;

/**
 * Validate field length or number is at minimum or higher/larger
 *
 * @param minOrOptions minimum number or options data
 * @param options options data
 * @returns ValidationMinObject
 */
export function Min(
    minOrOptions: number | ValidationMinOptions,
    options?: ValidationMinOptions
): ValidationMinObject {
    if (typeof minOrOptions === "number") {
        options = { ...defaultValidationMinOptions, ...(options || {}) };
        options.min = minOrOptions;
    } else {
        options = { ...defaultValidationMinOptions, ...(minOrOptions || {}) };
    }

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid:
                    this.type == "String"
                        ? value.toString().length >= this.min
                        : parseInt(value) >= this.min,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * Validation Max
 */
const VALIDATION_MAX_TYPE = ["String", "Number"];
type ValidationMaxType = (typeof VALIDATION_MAX_TYPE)[number];

interface ValidationMaxOptions {
    max: number;
    type?: ValidationMaxType;
    invalidMessage?: string | ((options: ValidationMaxOptions) => string);
}

interface ValidationMaxObject extends ValidationMaxOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationMaxOptions: ValidationMaxOptions = {
    max: 1,
    type: "String",
    invalidMessage: (options: ValidationMaxOptions) => {
        return options.type == "String"
            ? `Required to be less than ${options.max + 1} characters.`
            : `Required to be less than ${options.max + 1}.`;
    },
};

export function Max(
    maxOrOptions: number | ValidationMaxOptions,
    options?: ValidationMaxOptions
): ValidationMaxObject;
export function Max(options: ValidationMaxOptions): ValidationMaxObject;

/**
 * Validate field length or number is at maximum or smaller
 *
 * @param maxOrOptions maximum number or options data
 * @param options options data
 * @returns ValidationMaxObject
 */
export function Max(
    maxOrOptions: number | ValidationMaxOptions,
    options?: ValidationMaxOptions
): ValidationMaxObject {
    if (typeof maxOrOptions === "number") {
        options = { ...defaultValidationMaxOptions, ...(options || {}) };
        options.max = maxOrOptions;
    } else {
        options = { ...defaultValidationMaxOptions, ...(maxOrOptions || {}) };
    }

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid:
                    this.type == "String"
                        ? value.toString().length <= this.max
                        : parseInt(value) <= this.max,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * Validation Length
 */
interface ValidationLengthOptions {
    length: number;
    invalidMessage?: string | ((options: ValidationLengthOptions) => string);
}

interface ValidationLengthObject extends ValidationLengthOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationLengthOptions: ValidationLengthOptions = {
    length: 1,
    invalidMessage: (options: ValidationLengthOptions) => {
        return `Required to be ${options.length} characters.`;
    },
};

export function Length(
    lengthOrOptions: number | ValidationLengthOptions,
    options?: ValidationLengthOptions
): ValidationLengthObject;
export function Length(
    options: ValidationLengthOptions
): ValidationLengthObject;

/**
 * Validate field length
 *
 * @param lengthOrOptions string length or options data
 * @param options options data
 * @returns ValidationLengthObject
 */
export function Length(
    lengthOrOptions: number | ValidationLengthOptions,
    options?: ValidationLengthOptions
): ValidationLengthObject {
    if (typeof lengthOrOptions === "number") {
        options = { ...defaultValidationLengthOptions, ...(options || {}) };
        options.length = lengthOrOptions;
    } else {
        options = {
            ...defaultValidationLengthOptions,
            ...(lengthOrOptions || {}),
        };
    }

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid: value.toString().length == this.length,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * PASSWORD
 */
interface ValidationPasswordOptions {
    invalidMessage?: string | ((options: ValidationPasswordOptions) => string);
}

interface ValidationPasswordObject extends ValidationPasswordOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationPasswordOptions: ValidationPasswordOptions = {
    invalidMessage:
        "Your password needs to have at least a letter, a number and a special character.",
};

/**
 * Validate field is in a valid password format
 *
 * @param options options data
 * @returns ValidationPasswordObject
 */
export function Password(
    options?: ValidationPasswordOptions
): ValidationPasswordObject {
    options = { ...defaultValidationPasswordOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid: /(?=.*[A-Za-z])(?=.*\d)(?=.*[.@$!%*#?&])[A-Za-z\d.@$!%*#?&]{1,}$/.test(
                    value
                ),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * EMAIL
 */
interface ValidationEmailOptions {
    invalidMessage?: string | ((options: ValidationEmailOptions) => string);
}

interface ValidationEmailObject extends ValidationEmailOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationEmailOptions: ValidationEmailOptions = {
    invalidMessage: "Your email is not in a supported format.",
};

/**
 * Validate field is in a valid Email format
 *
 * @param options options data
 * @returns ValidationEmailObject
 */
export function Email(options?: ValidationEmailOptions): ValidationEmailObject {
    options = { ...defaultValidationEmailOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid:
                    value.length == 0 ||
                    /^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,3})+$/.test(value),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * PHONE
 */
interface ValidationPhoneOptions {
    invalidMessage?: string | ((options: ValidationPhoneOptions) => string);
}

interface ValidationPhoneObject extends ValidationPhoneOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationPhoneOptions: ValidationPhoneOptions = {
    invalidMessage: "Your Phone number is not in a supported format.",
};

/**
 * Validate field is in a valid Phone format
 *
 * @param options options data
 * @returns ValidationPhoneObject
 */
export function Phone(options?: ValidationPhoneOptions): ValidationPhoneObject {
    options = { ...defaultValidationPhoneOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid:
                    value.length == 0 ||
                    /^(\+|00)?[0-9][0-9 \-().]{7,32}$/.test(value),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * NUMBER
 */
interface ValidationNumberOptions {
    invalidMessage?: string | ((options: ValidationNumberOptions) => string);
}

interface ValidationNumberObject extends ValidationNumberOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationNumberOptions: ValidationNumberOptions = {
    invalidMessage: "Must be a number.",
};

/**
 * Validate field is in a valid Whole number format
 *
 * @param options options data
 * @returns ValidationNumberObject
 */
export function Number(
    options?: ValidationNumberOptions
): ValidationNumberObject {
    options = { ...defaultValidationNumberOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid: value.length == 0 || /^0?\d+$/.test(value),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * DATE
 */
interface ValidationDateOptions {
    before?: string | ((value: string) => string);
    after?: string | ((value: string) => string);
    invalidMessage?: string | ((options: ValidationDateOptions) => string);
    invalidBeforeMessage?:
        | string
        | ((options: ValidationDateOptions) => string);
    invalidAfterMessage?: string | ((options: ValidationDateOptions) => string);
}

interface ValidationDateObject extends ValidationDateOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationDateOptions: ValidationDateOptions = {
    before: "",
    after: "",
    invalidMessage: "Must be a valid date.",
    invalidBeforeMessage: (options: ValidationDateOptions) => {
        return `Must be a date before ${options.before}.`;
    },
    invalidAfterMessage: (options: ValidationDateOptions) => {
        return `Must be a date after ${options.after}.`;
    },
};

/**
 * Validate field is in a valid Date format
 *
 * @param options options data
 * @returns ValidationDateObject
 */
export function Date(options?: ValidationDateOptions): ValidationDateObject {
    options = { ...defaultValidationDateOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            let valid = true;
            let invalidMessageType = "invalidMessage";

            const parsedDate = new SMDate(value);

            if (parsedDate.isValid() == true) {
                const beforeDate = new SMDate(
                    typeof (options["before"] = options?.before || "") ===
                    "function"
                        ? options.before(value)
                        : options.before
                );
                const afterDate = new SMDate(
                    typeof (options["after"] = options?.after || "") ===
                    "function"
                        ? options.after(value)
                        : options.after
                );
                if (
                    beforeDate.isValid() == true &&
                    parsedDate.isBefore(beforeDate) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidBeforeMessage";
                }
                if (
                    afterDate.isValid() == true &&
                    parsedDate.isAfter(afterDate) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidAfterMessage";
                }
            } else {
                valid = false;
            }

            return Promise.resolve({
                valid: valid,
                invalidMessages: [
                    typeof this[invalidMessageType] === "string"
                        ? this[invalidMessageType]
                        : this[invalidMessageType](this),
                ],
            });
        },
    };
}

/**
 * TIME
 */
interface ValidationTimeOptions {
    before?: string | ((value: string) => string);
    after?: string | ((value: string) => string);
    invalidMessage?: string | ((options: ValidationTimeOptions) => string);
    invalidBeforeMessage?:
        | string
        | ((options: ValidationTimeOptions) => string);
    invalidAfterMessage?: string | ((options: ValidationTimeOptions) => string);
}

interface ValidationTimeObject extends ValidationTimeOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationTimeOptions: ValidationTimeOptions = {
    before: "",
    after: "",
    invalidMessage: "Must be a valid time.",
    invalidBeforeMessage: (options: ValidationTimeOptions) => {
        return `Must be a time before ${options.before}.`;
    },
    invalidAfterMessage: (options: ValidationTimeOptions) => {
        return `Must be a time after ${options.after}.`;
    },
};

/**
 * Validate field is in a valid Time format
 *
 * @param options options data
 * @returns ValidationTimeObject
 */
export function Time(options?: ValidationTimeOptions): ValidationTimeObject {
    options = { ...defaultValidationTimeOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            let valid = true;
            let invalidMessageType = "invalidMessage";

            const parsedTime = new SMDate(value);
            if (parsedTime.isValid() == true) {
                const beforeTime = new SMDate(
                    typeof (options["before"] = options?.before || "") ===
                    "function"
                        ? options.before(value)
                        : options.before
                );
                const afterTime = new SMDate(
                    typeof (options["after"] = options?.after || "") ===
                    "function"
                        ? options.after(value)
                        : options.after
                );

                if (
                    beforeTime.isValid() == true &&
                    parsedTime.isBefore(beforeTime) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidBeforeMessage";
                }
                if (
                    afterTime.isValid() == true &&
                    parsedTime.isAfter(afterTime) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidAfterMessage";
                }
            } else {
                valid = false;
            }

            return Promise.resolve({
                valid: valid,
                invalidMessages: [
                    typeof this[invalidMessageType] === "string"
                        ? this[invalidMessageType]
                        : this[invalidMessageType](this),
                ],
            });
        },
    };
}

/**
 * DATETIME
 */
interface ValidationDateTimeOptions {
    before?: string | ((value: string) => string);
    after?: string | ((value: string) => string);
    invalidMessage?: string | ((options: ValidationDateTimeOptions) => string);
    invalidBeforeMessage?:
        | string
        | ((options: ValidationDateTimeOptions) => string);
    invalidAfterMessage?:
        | string
        | ((options: ValidationDateTimeOptions) => string);
}

interface ValidationDateTimeObject extends ValidationDateTimeOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationDateTimeOptions: ValidationDateTimeOptions = {
    before: "",
    after: "",
    invalidMessage: "Must be a valid date and time.",
    invalidBeforeMessage: (options: ValidationDateTimeOptions) => {
        return `Must be a date/time before ${options.before}.`;
    },
    invalidAfterMessage: (options: ValidationDateTimeOptions) => {
        return `Must be a date/time after ${options.after}.`;
    },
};

/**
 * Validate field is in a valid Date format
 *
 * @param options options data
 * @returns ValidationDateObject
 */
export function DateTime(
    options?: ValidationDateTimeOptions
): ValidationDateTimeObject {
    options = { ...defaultValidationDateTimeOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            let valid = true;
            let invalidMessageType = "invalidMessage";

            const parsedDate = new SMDate(value);

            if (parsedDate.isValid() == true) {
                const beforeDate = new SMDate(
                    typeof (options["before"] = options?.before || "") ===
                    "function"
                        ? options.before(value)
                        : options.before
                );
                const afterDate = new SMDate(
                    typeof (options["after"] = options?.after || "") ===
                    "function"
                        ? options.after(value)
                        : options.after
                );
                if (
                    beforeDate.isValid() == true &&
                    parsedDate.isBefore(beforeDate) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidBeforeMessage";
                }
                if (
                    afterDate.isValid() == true &&
                    parsedDate.isAfter(afterDate) == false
                ) {
                    valid = false;
                    invalidMessageType = "invalidAfterMessage";
                }
            } else {
                valid = false;
            }

            return Promise.resolve({
                valid: valid,
                invalidMessages: [
                    typeof this[invalidMessageType] === "string"
                        ? this[invalidMessageType]
                        : this[invalidMessageType](this),
                ],
            });
        },
    };
}

/**
 * CUSTOM
 */
type ValidationCustomCallback = (value: string) => Promise<boolean | string>;

interface ValidationCustomOptions {
    callback: ValidationCustomCallback;
    invalidMessage?: string | ((options: ValidationCustomOptions) => string);
}

interface ValidationCustomObject extends ValidationCustomOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationCustomOptions: ValidationCustomOptions = {
    callback: async () => {
        return true;
    },
    invalidMessage: "This field is invalid.",
};

export function Custom(
    callbackOrOptions: ValidationCustomCallback | ValidationCustomOptions,
    options?: ValidationCustomOptions
);
export function Custom(
    options: ValidationCustomOptions
): ValidationCustomObject;

/**
 * Validate field is in a valid Custom format
 *
 * @param callbackOrOptions
 * @param options options data
 * @returns ValidationCustomObject
 */
export function Custom(
    callbackOrOptions: ValidationCustomCallback | ValidationCustomOptions,
    options?: ValidationCustomOptions
): ValidationCustomObject {
    if (typeof callbackOrOptions === "function") {
        options = { ...defaultValidationCustomOptions, ...(options || {}) };
        options.callback = callbackOrOptions;
    } else {
        options = {
            ...defaultValidationCustomOptions,
            ...(callbackOrOptions || {}),
        };
    }

    return {
        ...options,
        validate: async function (value: string): Promise<ValidationResult> {
            const validateResult = {
                valid: true,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };

            const callbackResult =
                typeof this.callback === "function"
                    ? await this.callback(value)
                    : true;

            if (typeof callbackResult === "string") {
                if (callbackResult.length > 0) {
                    validateResult.valid = false;
                    validateResult.invalidMessages = [callbackResult];
                }
            } else if (callbackResult !== true) {
                validateResult.valid = false;
            }

            return validateResult;
        },
    };
}

/**
 * And
 *
 * @param list
 */
export const And = (list: Array<ValidationObject>) => {
    return {
        list: list,
        validate: async function (value: string) {
            const validationResult: ValidationResult = {
                valid: true,
                invalidMessages: [],
            };

            await Promise.all(
                this.list.map(async (item: ValidationObject) => {
                    const validationItemResult = await item.validate(value);
                    if (validationItemResult.valid == false) {
                        validationResult.valid = false;
                        validationResult.invalidMessages =
                            validationResult.invalidMessages.concat(
                                validationItemResult.invalidMessages
                            );
                    }
                })
            );

            return validationResult;
        },
    };
};

/**
 * Required
 */
interface ValidationRequiredOptions {
    invalidMessage?: string | ((options: ValidationRequiredOptions) => string);
}

interface ValidationRequiredObject extends ValidationRequiredOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationRequiredOptions: ValidationRequiredOptions = {
    invalidMessage: "This field is required.",
};

/**
 * Validate field contains value
 *
 * @param options options data
 * @returns ValidationRequiredObject
 */
export function Required(
    options?: ValidationRequiredOptions
): ValidationRequiredObject {
    options = { ...defaultValidationRequiredOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: unknown): Promise<ValidationResult> {
            return Promise.resolve({
                valid: !isEmpty(value),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * Url
 */
interface ValidationUrlOptions {
    invalidMessage?: string | ((options: ValidationUrlOptions) => string);
}

interface ValidationUrlObject extends ValidationUrlOptions {
    validate: (value: string) => Promise<ValidationResult>;
}

const defaultValidationUrlOptions: ValidationUrlOptions = {
    invalidMessage: "Not a supported Url format.",
};

/**
 * Validate field is in a valid Email format
 *
 * @param options options data
 * @returns ValidationEmailObject
 */
export function Url(options?: ValidationUrlOptions): ValidationUrlObject {
    options = { ...defaultValidationUrlOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: string): Promise<ValidationResult> {
            return Promise.resolve({
                valid: /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*(:\d+)?([/?#][^\s]*)?$/.test(
                    value
                ),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}

/**
 * FileSize
 */
interface ValidationFileSizeOptions {
    size: number;
    invalidMessage?: string | ((options: ValidationFileSizeOptions) => string);
}

interface ValidationFileSizeObject extends ValidationFileSizeOptions {
    validate: (value: File) => Promise<ValidationResult>;
}

const defaultValidationFileSizeOptions: ValidationFileSizeOptions = {
    size: 1024 * 1024 * 1024, // 1 Mb
    invalidMessage: (options) => {
        return `The file size must be less than ${bytesReadable(options.size)}`;
    },
};

/**
 * Validate file is equal or less than size.
 *
 * @param options options data
 * @returns ValidationEmailObject
 */
export function FileSize(
    options?: ValidationFileSizeOptions
): ValidationFileSizeObject {
    options = { ...defaultValidationFileSizeOptions, ...(options || {}) };

    return {
        ...options,
        validate: function (value: File): Promise<ValidationResult> {
            const isValid =
                value instanceof File ? value.size < options.size : true;

            return Promise.resolve({
                valid: isValid,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            });
        },
    };
}
