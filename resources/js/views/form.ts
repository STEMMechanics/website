import { reactive } from "vue";
import { Form, FormControl, FormObject } from "../helpers/form";

export const form: FormObject = reactive(
    Form({
        password: FormControl("", Required()),
    }),
);
