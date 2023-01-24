module.exports = {
    env: {
        node: true,
    },
    extends: [
        "eslint:recommended",
        "plugin:vue/vue3-recommended",
        "prettier",
        "plugin:jsdoc/recommended",
        "plugin:@typescript-eslint/recommended",
    ],
    rules: {
        "vue/multi-word-component-names": "off",
        indent: ["error", 4],
    },
    plugins: ["jsdoc", "@typescript-eslint"],
    parser: "vue-eslint-parser",
    parserOptions: {
        parser: "@typescript-eslint/parser",
    },
};
