/**
 * WHMCS Client Area Block — Modular InnerBlocks Editor & Save.
 */
( function () {
	'use strict';

	var el = window.wp.element.createElement;
	var registerBlockType = window.wp.blocks.registerBlockType;
	var InnerBlocks = window.wp.blockEditor.InnerBlocks;
	var useBlockProps = window.wp.blockEditor.useBlockProps;
	var __ = window.wp.i18n.__;

	var CLIENT_AREA_TEMPLATE = [
		[
			'core/heading',
			{
				level: 3,
				content: __( 'Client Area & Services', 'whmcs-connector' ),
				className: 'whmcs-connector-client-area-heading',
			},
		],
		[
			'core/buttons',
			{
				layout: {
					type: 'flex',
					justifyContent: 'flex-start',
					flexWrap: 'wrap',
				},
				className: 'whmcs-connector-client-area-buttons',
			},
			[
				[
					'core/button',
					{
						text: __( 'Client Area', 'whmcs-connector' ),
						url: '#whmcs-clientarea',
						className: 'is-style-fill',
					},
				],
				[
					'core/button',
					{
						text: __( 'Support Tickets', 'whmcs-connector' ),
						url: '#whmcs-tickets',
						className: 'is-style-outline',
					},
				],
				[
					'core/button',
					{
						text: __( 'Invoices', 'whmcs-connector' ),
						url: '#whmcs-invoices',
						className: 'is-style-outline',
					},
				],
				[
					'core/button',
					{
						text: __( 'Knowledgebase', 'whmcs-connector' ),
						url: '#whmcs-knowledgebase',
						className: 'is-style-outline',
					},
				],
			],
		],
	];

	registerBlockType( 'whmcs/client-area', {
		edit: function Edit() {
			var blockProps = useBlockProps( {
				className: 'whmcs-connector-client-area-block',
			} );

			return el(
				'div',
				blockProps,
				el( InnerBlocks, {
					template: CLIENT_AREA_TEMPLATE,
					templateLock: false,
				} )
			);
		},

		save: function Save() {
			var blockProps = useBlockProps.save( {
				className: 'whmcs-connector-client-area-block',
			} );

			return el(
				'div',
				blockProps,
				el( InnerBlocks.Content, null )
			);
		},
	} );
} )();
