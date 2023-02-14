export const isEmpty = (obj: object | string) => {
    if (obj) {
        if (typeof obj === "string") {
            return obj.length == 0;
        } else if (typeof obj == "object" && Object.keys(obj).length === 0) {
            return true;
        }
    }

    return false;
};
