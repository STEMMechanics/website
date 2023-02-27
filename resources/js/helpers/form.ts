import { ApiResponse } from "./api";
import {
    createValidationResult,
    defaultValidationResult,
    ValidationObject,
    ValidationResult,
} from "./validate";

type FormObjectValidateFunction = (item: string | null) => Promise<boolean>;
type FormObjectLoadingFunction = (state: boolean) => void;
type FormObjectMessageFunction = (
    message?: string,
    type?: string,
    icon?: string
) => void;
type FormObjectErrorFunction = (message: string) => void;
type FormObjectApiErrorsFunction = (apiErrors: ApiResponse) => void;

export interface FormObject {
    validate: FormObjectValidateFunction;
    loading: FormObjectLoadingFunction;
    message: FormObjectMessageFunction;
    error: FormObjectErrorFunction;
    apiErrors: FormObjectApiErrorsFunction;
    _loading: boolean;
    _message: string;
    _messageType: string;
    _messageIcon: string;
    controls: { [key: string]: FormControlObject };
}

const defaultFormObject: FormObject = {
    validate: async function (item = null) {
        const keys = item ? [item] : Object.keys(this.controls);
        let valid = true;

        await Promise.all(
            keys.map(async (key) => {
                if (
                    typeof this.controls[key] == "object" &&
                    Object.keys(this.controls[key]).includes("validation")
                ) {
                    const validationResult = await this.controls[
                        key
                    ].validation.validator.validate(this.controls[key].value);
                    this.controls[key].validation.result = validationResult;

                    if (!validationResult.valid) {
                        valid = false;
                    }
                }
            })
        );

        return valid;
    },
    loading: function (state = true) {
        this._loading = state;
    },
    message: function (message = "", type = "", icon = "") {
        this._message = message;

        if (type.length > 0) {
            this._messageType = type;
        }
        if (icon.length > 0) {
            this._messageIcon = icon;
        }
    },
    error: function (message = "") {
        if (message == "") {
            this.message("");
        } else {
            this.message(message, "error", "alert-circle-outline");
        }
    },
    apiErrors: function (apiResponse: ApiResponse) {
        let foundKeys = false;

        if (
            apiResponse.data &&
            typeof apiResponse.data === "object" &&
            apiResponse.data.errors
        ) {
            const errors = apiResponse.data.errors as Record<string, string>;
            Object.keys(errors).forEach((key) => {
                if (
                    typeof this.controls[key] === "object" &&
                    Object.keys(this.controls[key]).includes("validation")
                ) {
                    foundKeys = true;
                    this.controls[key].validation.result =
                        createValidationResult(false, errors[key]);
                }
            });
        }

        if (foundKeys == false) {
            this.error(
                apiResponse?.json?.message ||
                    "An unknown server error occurred.\nPlease try again later."
            );
        }
    },
    controls: {},

    _loading: false,
    _message: "",
    _messageType: "primary",
    _messageIcon: "",
};

/**
 * Create a new Form object.
 *
 * @param {Record<string, FormControlObject>} controls The controls included in the form.
 * @returns {FormObject} Returns a form object.
 */
export const Form = (
    controls: Record<string, FormControlObject>
): FormObject => {
    const form = defaultFormObject;
    form.controls = controls;

    return form;
};

interface FormControlValidation {
    validator: ValidationObject;
    result: ValidationResult;
}

const defaultFormControlValidation: FormControlValidation = {
    validator: {
        validate: (): ValidationResult => {
            return defaultValidationResult;
        },
    },
    result: defaultValidationResult,
};

type FormControlClearValidations = () => void;
type FormControlSetValidation = (
    valid: boolean,
    message?: string | Array<string>
) => ValidationResult;
type FormControlIsValid = () => boolean;

export interface FormControlObject {
    value: string;
    validate: () => ValidationResult;
    validation: FormControlValidation;
    clearValidations: FormControlClearValidations;
    setValidationResult: FormControlSetValidation;
    isValid: FormControlIsValid;
}

/**
 * Create a new form control object.
 *
 * @param {string} value The control name.
 * @param {ValidationObject | null} validator The control validation rules.
 * @returns {FormControlObject} The form control object.
 */
export const FormControl = (
    value: string = "",
    validator: ValidationObject | null = null
): FormControlObject => {
    return {
        value: value,
        validation:
            validator == null
                ? defaultFormControlValidation
                : {
                      validator: validator,
                      result: defaultValidationResult,
                  },
        clearValidations: function () {
            this.validation.result = defaultValidationResult;
        },
        setValidationResult: createValidationResult,
        validate: function () {
            if (this.validation.validator) {
                this.validation.result = this.validation.validator.validate(
                    this.value
                );
                return this.validation.result;
            }

            return defaultValidationResult;
        },
        isValid: function () {
            return this.validation.result.valid;
        },
    };
};
