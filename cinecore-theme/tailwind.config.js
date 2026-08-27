/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		'./*.php',
		'./**/*.php',
		'./assets/js/**/*.js',
		'./src/**/*.js',
		'!./node_modules/**',
	],
	theme: {
		extend: {
			colors: {
				cinecore: {
					dark: '#0a0a0a',
					card: '#141414',
					surface: '#1f1f1f',
					accent: '#e50914',
					muted: '#9ca3af',
				},
			},
			fontFamily: {
				sans: [ 'Inter', 'system-ui', 'sans-serif' ],
			},
		},
	},
	plugins: [],
};
