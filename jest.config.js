module.exports = {
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: [ '<rootDir>/tests/frontend/setup.js' ],
	testMatch: [
		'<rootDir>/tests/frontend/**/*.test.js'
	],
	collectCoverageFrom: [
		'assets/js/frontend/**/*.js',
		'!assets/js/frontend/**/*.min.js'
	],
	coverageDirectory: 'coverage',
	coverageReporters: [ 'text', 'lcov', 'html' ],
	moduleNameMapper: {
		'^@/(.*)$': '<rootDir>/assets/js/$1'
	},
	transform: {
		'^.+\\.js$': 'babel-jest'
	},
	globals: {
		window: {},
		document: {},
		jQuery: {},
		$: {}
	},
	// Configure jest-junit for GitLab CI integration
	reporters: [
		'default',
		[ 'jest-junit', {
			outputDirectory: '.',
			outputName: 'jest-junit.xml',
			classNameTemplate: '{classname}',
			titleTemplate: '{title}',
			ancestorSeparator: ' › ',
			usePathForSuiteName: true
		} ]
	]
};
