import { Directive } from "vue";

const bodyClass: Directive = {
    mounted(el, binding) {
        document.body.classList.add(binding.value as string);
    },
    unmounted(el, binding) {
        document.body.classList.remove(binding.value as string);
    },
};

export default bodyClass;
