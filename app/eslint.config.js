import js from '@eslint/js'
import globals from 'globals'
import pluginVue from 'eslint-plugin-vue'

export default [
    { ignores: ['vendor/**', 'node_modules/**', 'public/build/**'] },
    js.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                route: 'readonly',
                Ziggy: 'readonly',
                L: 'readonly',
            },
        },
        rules: {
            'no-empty': ['warn', { allowEmptyCatch: true }],
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'no-useless-escape': 'warn',
            'vue/multi-word-component-names': 'off',
        },
    },
]
