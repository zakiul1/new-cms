export default {
    content: [
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.js",
        "./resources/js/**/*.vue",
        "./app/**/*.php",

        // ✅ theme blades
        "./themes/**/views/**/*.blade.php",

        // ✅ plugin blades (if plugins have UI)
        "./plugins/**/resources/views/**/*.blade.php",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
