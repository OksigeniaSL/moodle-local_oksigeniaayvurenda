// ESLint for the plugin's own scripts in app/ (otr-codigo 6): they are not AMD modules, for the same player runs on
// pages without Moodle's (the door, a self-loan post, a frame on a website). What is bundled or comes from others stays
// out. Run from the plugin's root: npx eslint -c .github/eslint.config.mjs app
import js from "@eslint/js";
import globals from "globals";

export default [
    {ignores: ["app/zxing/**", "app/epubjs/**", "app/isla3d.js", "app/isla3d/**"]},
    js.configs.recommended,
    {
        files: ["app/**/*.js"],
        languageOptions: {ecmaVersion: 2020, sourceType: "script", globals: {...globals.browser}},
        rules: {"no-unused-vars": ["error", {args: "none", caughtErrors: "none"}]},
    },
    {
        // The app's own script runs inside the Moodle app, where «this» is what the app gives it.
        files: ["app/mobile-links.js"],
        languageOptions: {sourceType: "module"},
    },
];
