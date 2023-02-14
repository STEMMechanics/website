import {
    ValidationObject,
    ValidationResult,
    defaultValidationResult,
    createValidationResult,
} from "./validate";

export const FormObject = (controls) => {
    controls.validate = function (item = null) {
        const keys = item ? [item] : Object.keys(this);
        let valid = true;

        keys.every((key) => {
            if (
                typeof this[key] == "object" &&
                Object.keys(this[key]).includes("validation")
            ) {
                this[key].validation.result = this[
                    key
                ].validation.validator.validate(this[key].value);

                if (!this[key].validation.result.valid) {
                    valid = false;
                }
            }

            return true;
        });

        return valid;
    };

    controls._loading = false;
    controls.loading = function (state = true) {
        this._loading = state;
    };

    controls._message = "";
    controls._messageType = "primary";
    controls._messageIcon = "";

    controls.message = function (message = "", type = "", icon = "") {
        this._message = message;

        if (type.length > 0) {
            this._messageType = type;
        }
        if (icon.length > 0) {
            this._messageIcon = icon;
        }
    };

    controls.error = function (message = "") {
        if (message == "") {
            this.message("");
        } else {
            this.message(message, "error", "alert-circle-outline");
        }
    };

    controls.apiErrors = function (apiResponse) {
        let foundKeys = false;

        if (apiResponse?.json?.errors) {
            Object.keys(apiResponse.json.errors).forEach((key) => {
                if (
                    typeof this[key] == "object" &&
                    Object.keys(this[key]).includes("validation")
                ) {
                    foundKeys = true;
                    this[key].validation.result = createValidationResult(
                        false,
                        apiResponse.json.errors[key]
                    );
                }
            });
        }

        if (foundKeys == false) {
            this.error(
                apiResponse?.json?.message ||
                    "An unknown server error occurred.\nPlease try again later."
            );
        }
    };

    return controls;
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

type FormClearValidations = () => void;
type FormSetValidation = (
    valid: boolean,
    message?: string | Array<string>
) => ValidationResult;

interface FormControlObject {
    value: string;
    validation: FormControlValidation;
    clearValidations: FormClearValidations;
    setValidationResult: FormSetValidation;
}

/* eslint-disable indent */
export const FormControl = (
    value = "",
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
    };
};
/* eslint-enable indent */
