import globals from "globals";
import pluginJs from "@eslint/js";
import babelParser from "@babel/eslint-parser";

/** @type {import('eslint').Linter.Config[]} */

export default [
  pluginJs.configs.recommended,
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2018,
      sourceType: 'module',
      parser: babelParser,
      parserOptions: {
        requireConfigFile: false,
        babelOptions: {
          babelrc: false,
          configFile: false,
          presets: ["@babel/preset-react"],
        }
      },
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jquery,
        "wp": "readonly",
        "__dirname": "readonly",
        "orderData": "readonly",
        "PowerBoardAjaxCheckout": "readonly",
        "PowerBoardAjaxError": "readonly",
        "cba": "readonly",
        "alert": "readonly",
        "confirm": "readonly",
        "location": "readonly",
        "history": "readonly",
        "setTimeout": "readonly",
        "clearTimeout": "readonly",
        "setInterval": "readonly",
        "clearInterval": "readonly"
      }
    },
    rules: {
      // WordPress JavaScript Coding Standards
      'indent': ['error', 'tab', {
        'SwitchCase': 1,
        'MemberExpression': 1,
        'FunctionDeclaration': { 'parameters': 1, 'body': 1 },
        'FunctionExpression': { 'parameters': 1, 'body': 1 },
        'CallExpression': { 'arguments': 1 },
        'ArrayExpression': 1,
        'ObjectExpression': 1,
        'ImportDeclaration': 1,
        'flatTernaryExpressions': false
      }],
      'quotes': ['error', 'single', { 'allowTemplateLiterals': true }],
      'semi': ['error', 'always'],
      'comma-dangle': ['error', 'never'],
      'space-in-parens': ['error', 'always'],
      'array-bracket-spacing': ['error', 'always'],
      'object-curly-spacing': ['error', 'always'],
      'computed-property-spacing': ['error', 'always'],
      'space-before-function-paren': ['error', 'never'],
      'keyword-spacing': ['error', { 'before': true, 'after': true }],
      'space-unary-ops': ['error', {
        'words': true,
        'nonwords': false
      }],
      'no-mixed-spaces-and-tabs': 'error',
      'no-trailing-spaces': 'error',
      'eol-last': 'error',
      'brace-style': ['error', '1tbs', { 'allowSingleLine': true }],
      'curly': ['error', 'all'],
      'max-len': ['warn', { 'code': 100, 'ignoreComments': true, 'ignoreUrls': true }],

      // WordPress specific preferences
      'camelcase': 'off', // WordPress allows snake_case
      'no-console': 'warn', // Allow console for debugging
      'no-unused-vars': ['error', { 'args': 'none' }],
      'func-names': 'off',
      'prefer-arrow-callback': 'off',
      'object-shorthand': 'off'
    }
  },
  // Test files specific rules
  {
    files: ["tests/**/*.js", "tests/**/*.test.js", "**/*.test.js", "**/*.spec.js"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jquery,
        ...globals.jest,
        // Jest globals
        "describe": "readonly",
        "it": "readonly",
        "test": "readonly",
        "expect": "readonly",
        "beforeEach": "readonly",
        "afterEach": "readonly",
        "beforeAll": "readonly",
        "afterAll": "readonly",
        "jest": "readonly",
        // Project globals
        "wp": "readonly",
        "__dirname": "readonly",
        "orderData": "readonly",
        "PowerBoardAjaxCheckout": "readonly",
        "PowerBoardAjaxError": "readonly",
        "cba": "readonly"
      }
    },
    rules: {
      "max-len": "off",
      "no-unused-vars": "warn"
    }
  },
  // Frontend source files
  {
    files: ["resources/js/**/*.js", "assets/js/**/*.js"],
    ignores: ["**/*.min.js"],
    rules: {}
  },
  // Global ignores
  {
    ignores: [
      "node_modules/",
      "vendor/",
      "assets/build/",
      "**/*.min.js",
      ".phpcs.cache"
    ]
  }
];
