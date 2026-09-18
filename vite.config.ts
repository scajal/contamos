import inertia from "@inertiajs/vite";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import babel from "@rolldown/plugin-babel";
import tailwindcss from "@tailwindcss/vite";
import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig, lazyPlugins } from "vite-plus";

export default defineConfig({
    /**
     * Formatting configuration.
     *
     * @see https://viteplus.dev/config/#configuring-vite
     */
    fmt: {
        sortImports: true,
        printWidth: 120,
        tabWidth: 4,
        singleQuote: false,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: "css",
        ignorePatterns: [
            ".agents/**",
            ".ai/**",
            ".claude/**",
            ".cursor/**",
            ".github/**",
            ".junie/**",
            ".vscode/**",
            "vendor/**",
            "node_modules/**",
            "public/**",
            "bootstrap/ssr/**",
            "composer.json",
            "pint.json",
            "skills-lock.json",
            "tailwind.config.js",
            "**/*.md",
            "resources/js/actions/**",
            "resources/js/routes/**",
            "resources/js/wayfinder/**",
        ],
        sortTailwindcss: {
            functions: ["clsx", "cn", "cva"],
            entryPoint: "resources/css/app.css",
        },
    },

    /**
     * Lint configuration.
     *
     * @see https://viteplus.dev/config/#configuring-vite
     */
    lint: {
        ignorePatterns: [
            ".agents/**",
            ".ai/**",
            ".claude/**",
            ".cursor/**",
            ".github/**",
            ".junie/**",
            ".vscode/**",
            "vendor/**",
            "node_modules/**",
            "public/**",
            "bootstrap/ssr/**",
            "tailwind.config.js",
            "resources/js/actions/**",
            "resources/js/routes/**",
            "resources/js/wayfinder/**",
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },

    /**
     * Plugins configuration.
     *
     * @see https://viteplus.dev/config/#configuring-vite
     */
    plugins: lazyPlugins(() => [
        babel({
            presets: [reactCompilerPreset()],
        }),
        inertia(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.tsx", "resources/css/administration/theme.css"],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        wayfinder({ formVariants: true }),
    ]),

    /**
     * Server configuration.
     *
     * @see https://viteplus.dev/config/#configuring-vite
     */
    server: {
        watch: {
            ignored: [
                "**/.agents/**",
                "**/.claude/**",
                "**/.cursor/**",
                "**/.junie/**",
                "**/storage/**",
                "**/vendor/**",
            ],
        },
    },
});
