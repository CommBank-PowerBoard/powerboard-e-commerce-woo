module.exports = {
	presets: [
		[
			'@babel/preset-env',
			{
				targets: {
					node: 'current',
				},
	},
		],
		'@babel/preset-react'
	],
	env: {
		test: {
			presets: [
				[
					'@babel/preset-env',
					{
						targets: {
							node: 'current',
						},
			},
				],
			],
		},
	},
};
