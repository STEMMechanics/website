/** @type {import('tailwindcss').Config} */
export default {
    content: ["./resources/**/*.blade.php", "./resources/**/*.js"],
    theme: {
        extend: {
            colors: {
                "blue-lighter": "#cce5ff",
                "blue-light": "#33ccff",
                blue: "#00a5f1",
                "blue-dark": "#007bb4",

                "red-lighter": "#f8d7da",
                "red-light": "#ff6666",
                red: "#e00000",
                "red-dark": "#cc0000",

                "green-lighter": "#d4edda",
                "green-light": "#35D100",
                green: "#21af00",
                "green-dark": "#188300",

                "orange-light": "#ffcc33",
                orange: "#f89e00",
                "orange-dark": "#ba7600",

                "yellow-lighter": "#f8d7da",
                "yellow-light": "#ffea66",
                yellow: "#ffdb05",
                "yellow-dark": "#cc9900",

                "pink-light": "#ff33cc",
                pink: "#ff02ad",
                "pink-dark": "#cc0099",
            },
            transitionDuration: {
                DEFAULT: "50ms",
            },
            fontFamily: {
                sans: "Poppins,Roboto,Open Sans,ui-sans-serif,system-ui,sans-serif",
            },
        },
    },
    variants: {
        extend: {
            backgroundColor: ["disabled"],
            textColor: ["disabled"],
        },
    },
};
