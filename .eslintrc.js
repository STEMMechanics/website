module.exports = {
    env: {
        node: true,
    },
    extends: [
        "eslint:recommended",
        "plugin:vue/vue3-strongly-recommended",
        "prettier",
        "plugin:jsdoc/recommended",
        "plugin:@typescript-eslint/recommended",
    ],
    rules: {
        "vue/multi-word-component-names": "off",
        indent: ["off", 4, { ignoredNodes: ["ConditionalExpression"] }],
        "@typescript-eslint/no-inferrable-types": "off",
    },
    plugins: ["jsdoc", "@typescript-eslint"],
    parser: "vue-eslint-parser",
    parserOptions: {
        parser: "@typescript-eslint/parser",
    },
};
