export interface ValidationObject {
    validate: (value: string) => ValidationResult;
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
    validate: (value: string) => ValidationResult;
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
        validate: function (value: string): ValidationResult {
            return {
                valid:
                    this.type == "String"
                        ? value.toString().length >= this.min
                        : parseInt(value) >= this.min,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };
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
    validate: (value: string) => ValidationResult;
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
        validate: function (value: string): ValidationResult {
            return {
                valid:
                    this.type == "String"
                        ? value.toString().length <= this.max
                        : parseInt(value) <= this.max,
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };
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
    validate: (value: string) => ValidationResult;
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
        validate: function (value: string): ValidationResult {
            return {
                valid: /(?=.*[A-Za-z])(?=.*\d)(?=.*[.@$!%*#?&])[A-Za-z\d.@$!%*#?&]{1,}$/.test(
                    value
                ),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };
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
    validate: (value: string) => ValidationResult;
}

const defaultValidationEmailOptions: ValidationEmailOptions = {
    invalidMessage: "Your Email is not in a supported format.",
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
        validate: function (value: string): ValidationResult {
            return {
                valid: /^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,3})+$/.test(
                    value
                ),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };
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
    validate: (value: string) => ValidationResult;
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
        validate: function (value: string): ValidationResult {
            return {
                valid: /^(\+|00)?[0-9][0-9 \-().]{7,32}$/.test(value),
                invalidMessages: [
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
                ],
            };
        },
    };
}

/**
 * CUSTOM
 */
type ValidationCustomCallback = (value: string) => boolean | string;

interface ValidationCustomOptions {
    callback: ValidationCustomCallback;
    invalidMessage?: string | ((options: ValidationCustomOptions) => string);
}

interface ValidationCustomObject extends ValidationCustomOptions {
    validate: (value: string) => ValidationResult;
}

const defaultValidationCustomOptions: ValidationCustomOptions = {
    callback: () => {
        return true;
    },
    invalidMessage: "Your Custom number is not in a supported format.",
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
        validate: function (value: string): ValidationResult {
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
                    ? this.callback(value)
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
        validate: function (value: string) {
            const validationResult: ValidationResult = {
                valid: true,
                invalidMessages: [],
            };

            this.list.every((item: ValidationObject) => {
                const validationItemResult = item.validate(value);
                if (validationItemResult.valid == false) {
                    validationResult.valid = false;
                    validationResult.invalidMessages =
                        validationResult.invalidMessages.concat(
                            validationItemResult.invalidMessages
                        );
                }

                return true;
            });

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
    validate: (value: string) => ValidationResult;
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
        validate: function (value: string): ValidationResult {
            return {
                valid: value.length > 0,
                invalidMessages:
                    typeof this.invalidMessage === "string"
                        ? this.invalidMessage
                        : this.invalidMessage(this),
            };
        },
    };
}
