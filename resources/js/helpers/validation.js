import { watch } from "vue";
import { parseISO } from "date-fns";

let oldFormData = {};

const bytesReadable = (bytes) => {
    if (Math.abs(bytes) < 1024) {
        return bytes + "B";
    }

    const units = ["kB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
    let u = -1;
    const r = 10 ** 1;

    do {
        bytes /= 1024;
        ++u;
    } while (
        Math.round(Math.abs(bytes) * r) / r >= 1024 &&
        u < units.length - 1
    );

    return bytes.toFixed(1) + "" + units[u];
};

const validateMessage = (ruleData, rule, defMessage) => {
    let msg =
        ruleData[rule + "_message"] !== undefined
            ? ruleData[rule + "_message"]
            : defMessage;
    return msg
        .replaceAll("%d", ruleData[rule])
        .replaceAll("%b", bytesReadable(ruleData[rule]));
};

const validateSingle = (fieldData) => {
    let error = "";

    if (
        fieldData.rules &&
        (fieldData.enabled == undefined || fieldData.enabled == true)
    ) {
        // Fill in the type declaration
        if (Object.keys(fieldData.rules).includes("type")) {
            if (typeof fieldData.rules.type === "string") {
                fieldData.rules[fieldData.rules.type] = true;
            } else if (
                typeof fieldData.rules.type === "object" &&
                "type" in fieldData.rules.type
            ) {
                fieldData.rules[fieldData.rules.type.type] = true;
            }
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("required") &&
            (fieldData.rules.required == true ||
                (typeof fieldData.rules.required == "function" &&
                    fieldData.rules.required() == true)) &&
            (fieldData.value == null || fieldData.value.length == 0)
        ) {
            error = validateMessage(
                fieldData.rules,
                "required",
                "This item is required"
            );
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("min") &&
            (fieldData.value == null ||
                fieldData.value.length < fieldData.rules.min)
        ) {
            error = validateMessage(
                fieldData.rules,
                "min",
                "This item is required to be at least %d characters"
            );
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("max") &&
            fieldData.value != null &&
            fieldData.value.length > fieldData.rules.max
        ) {
            error = validateMessage(
                fieldData.rules,
                "max",
                "This item is required to be at no longer than %d characters"
            );
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("email") &&
            /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(
                fieldData.value
            ) == false
        ) {
            error = validateMessage(
                fieldData.rules,
                "email",
                "This item is required to be a valid email address"
            );
        }

        if (error.length == 0 && Object.keys(fieldData.rules).includes("url")) {
            try {
                new URL(fieldData.value);
            } catch (e) {
                error = validateMessage(
                    fieldData.rules,
                    "url",
                    "This item is required to be a valid URL"
                );
            }
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("password")
        ) {
            if (
                (fieldData.rules.password == true ||
                    fieldData.rules.password == "basic") &&
                /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{1,}$/.test(
                    fieldData.value
                ) == false
            ) {
                error = validateMessage(
                    fieldData.rules,
                    "password",
                    "Your password needs to have at least 1 letter and 1 number"
                );
            } else if (
                fieldData.rules.password == "special" &&
                /(?=.*[A-Za-z])(?=.*\d)(?=.*[.@$!%*#?&])[A-Za-z\d.@$!%*#?&]{1,}$/.test(
                    fieldData.value
                ) == false
            ) {
                error = validateMessage(
                    fieldData.rules,
                    "password",
                    "Your password needs to have at least a letter, a number and a special character"
                );
            } else if (
                fieldData.rules.password == "uppercase_special" &&
                /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{1,}$/.test(
                    fieldData.value
                ) == false
            ) {
                error = validateMessage(
                    fieldData.rules,
                    "password",
                    "Your password needs to have at least a lowercase and uppercase letter, 1 number and 1 special character"
                );
            }
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("phone") &&
            fieldData.value != null &&
            fieldData.value.length > 0 &&
            /^(\+|00)?[0-9][0-9 \-\(\)\.]{7,32}$/.test(fieldData.value) == false
        ) {
            error = validateMessage(
                fieldData.rules,
                "phone",
                "This item is required to be a valid phone number"
            );
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("datetime") &&
            fieldData.value != null &&
            fieldData.value.length > 0
        ) {
            try {
                parseISO(fieldData.value);
            } catch (e) {
                error = validateMessage(
                    fieldData.rules,
                    "datetime",
                    "A valid date/time is required"
                );
            }
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("fileSize") &&
            fieldData.value != null &&
            fieldData.value.size > fieldData.rules.fileSize
        ) {
            error = validateMessage(
                fieldData.rules,
                "fileSize",
                "The file size larger than the allowed size"
            );
        }

        if (
            error.length == 0 &&
            Object.keys(fieldData.rules).includes("custom")
        ) {
            error = fieldData.rules.custom(fieldData.value);
        }

        fieldData.error = error;
    }
};

const validateRules = (
    formData,
    force = false,
    pageRef = null,
    showMessages = false
) => {
    Object.keys(oldFormData).forEach((key) => {
        if (
            key in formData &&
            (oldFormData[key].value != parseValue(formData[key].value) ||
                force == true) &&
            formData[key].rules !== undefined &&
            (pageRef == null || formData[key].page == pageRef.value)
        ) {
            oldFormData[key].value = parseValue(formData[key].value);
            if (showMessages == true) {
                validateSingle(formData[key]);
            } else {
                formData[key].error = "";
            }
        }
    });
};

const parseValue = (val) => {
    if (val instanceof File) {
        return JSON.stringify({
            name: val.name,
            size: val.size,
            lastModified: val.lastModified,
            lastModifiedDate: val.lastModifiedDate,
            type: val.type,
        });
    }

    return val;
};

export const useValidation = (
    formData,
    pageRef = null,
    showMessages = false
) => {
    watch(formData, (newFormData) => {
        if (newFormData) {
            validateRules(newFormData, false, pageRef, showMessages);
        }
    });
};

export const isValidated = (formData, pageRef = null) => {
    let result = true;

    oldFormData = JSON.parse(JSON.stringify(formData));
    validateRules(formData, true, pageRef, true);

    Object.keys(formData).forEach((key) => {
        if (
            formData[key].error !== undefined &&
            formData[key].error.length > 0 &&
            (pageRef == null || pageRef.value == formData[key].page)
        ) {
            result = false;
        }
    });

    return result;
};

export const fieldValidate = (fieldData) => {
    validateSingle(fieldData);
};

export const restParseErrors = (formData, formErrorRef, response) => {
    let foundKeys = false;

    if (response.response?.data?.errors) {
        Object.keys(response.response.data.errors).forEach((key) => {
            if (formData[key] !== undefined) {
                foundKeys = true;
                if (Array.isArray(response.response.data.errors[key])) {
                    formData[key].error = response.response.data.errors[key][0];
                } else {
                    formData[key].error = response.response.data.errors[key];
                }
            }
        });
    }

    if (foundKeys == false) {
        const msg = response.response?.data?.message
            ? response.response?.data?.message
            : "An unknown server error occurred. Please try again later";
        if (Array.isArray(formErrorRef)) {
            formErrorRef[0][formErrorRef[1]] = msg;
        } else if (formErrorRef.value !== undefined) {
            formErrorRef.value = msg;
        }
    }
};

export const clearFormData = (formData) => {
    Object.keys(formData).forEach((key) => {
        if ("value" in formData[key]) {
            formData[key]["value"] = "";
        }
        if ("error" in formData[key]) {
            formData[key]["error"] = "";
        }
    });
};
