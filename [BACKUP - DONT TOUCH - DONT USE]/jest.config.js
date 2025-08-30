module.exports = {
    testEnvironment: 'jsdom',
    setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
    moduleNameMapper: {
        '\\.(css|less|scss|sass)$': 'identity-obj-proxy',
        '^@/(.*)$': '<rootDir>/widget-src/src/$1'
    },
    testMatch: [
        '<rootDir>/widget-src/**/*.test.{js,jsx}',
        '<rootDir>/widget-src/**/*.spec.{js,jsx}'
    ],
    collectCoverageFrom: [
        'widget-src/src/**/*.{js,jsx}',
        '!widget-src/src/index.js',
        '!widget-src/src/admin.js',
        '!widget-src/src/**/*.test.{js,jsx}',
        '!widget-src/src/**/*.spec.{js,jsx}'
    ],
    coverageThreshold: {
        global: {
            branches: 80,
            functions: 80,
            lines: 80,
            statements: 80
        }
    },
    coverageReporters: ['text', 'lcov', 'html'],
    transform: {
        '^.+\\.(js|jsx)$': ['babel-jest', {
            presets: [
                '@babel/preset-env',
                ['@babel/preset-react', { runtime: 'automatic' }]
            ]
        }]
    },
    moduleFileExtensions: ['js', 'jsx', 'json'],
    testTimeout: 10000,
    verbose: true
};