/**
 * WHMCS Pricing Block — Gutenberg Inspector & Live Preview.
 */
( function () {
	'use strict';

	var el = window.wp.element.createElement;
	var registerBlockType = window.wp.blocks.registerBlockType;
	var InspectorControls = window.wp.blockEditor.InspectorControls;
	var useBlockProps = window.wp.blockEditor.useBlockProps;
	var PanelBody = window.wp.components.PanelBody;
	var TextControl = window.wp.components.TextControl;
	var SelectControl = window.wp.components.SelectControl;
	var __ = window.wp.i18n.__;

	registerBlockType( 'whmcs/pricing', {
		edit: function Edit( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: 'whmcs-connector-pricing-grid-editor whmcs-cols-' + ( attributes.columns || '3' ),
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Pricing Grid Settings', 'whmcs-connector' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Product Group ID (GID)', 'whmcs-connector' ),
						help: __( 'Optional: Filter products by WHMCS product group ID.', 'whmcs-connector' ),
						value: attributes.gid,
						onChange: function ( value ) {
							setAttributes( { gid: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Single Product ID (PID)', 'whmcs-connector' ),
						help: __( 'Optional: Display only a specific product ID.', 'whmcs-connector' ),
						value: attributes.pid,
						onChange: function ( value ) {
							setAttributes( { pid: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Billing Cycle Filter', 'whmcs-connector' ),
						value: attributes.billing_cycle,
						options: [
							{ label: __( 'All Available Cycles', 'whmcs-connector' ), value: '' },
							{ label: __( 'Monthly', 'whmcs-connector' ), value: 'monthly' },
							{ label: __( 'Quarterly', 'whmcs-connector' ), value: 'quarterly' },
							{ label: __( 'Semi-Annually', 'whmcs-connector' ), value: 'semiannually' },
							{ label: __( 'Annually', 'whmcs-connector' ), value: 'annually' },
							{ label: __( 'Biennially', 'whmcs-connector' ), value: 'biennially' },
							{ label: __( 'Triennially', 'whmcs-connector' ), value: 'triennially' },
						],
						onChange: function ( value ) {
							setAttributes( { billing_cycle: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Columns', 'whmcs-connector' ),
						value: attributes.columns || '3',
						options: [
							{ label: '1 Column', value: '1' },
							{ label: '2 Columns', value: '2' },
							{ label: '3 Columns', value: '3' },
							{ label: '4 Columns', value: '4' },
						],
						onChange: function ( value ) {
							setAttributes( { columns: value } );
						},
					} )
				)
			);

			// Editor preview mockup (clean card grid matching Free philosophy)
			var previewCards = [ 1, 2, 3 ].map( function ( num ) {
				return el(
					'div',
					{ key: num, className: 'whmcs-connector-pricing-card' },
					el( 'h3', { className: 'whmcs-connector-product-title' }, __( 'Sample Plan ', 'whmcs-connector' ) + num ),
					el(
						'div',
						{ className: 'whmcs-connector-product-desc' },
						__( 'Live WHMCS product description and pricing will display here on the frontend.', 'whmcs-connector' )
					),
					el(
						'div',
						{ className: 'whmcs-connector-product-pricing' },
						el(
							'ul',
							{ className: 'whmcs-connector-price-list' },
							el(
								'li',
								null,
								el( 'span', { className: 'whmcs-cycle-label' }, __( 'Monthly: ', 'whmcs-connector' ) ),
								el( 'span', { className: 'whmcs-cycle-amount' }, '$9.99' )
							),
							el(
								'li',
								null,
								el( 'span', { className: 'whmcs-cycle-label' }, __( 'Annually: ', 'whmcs-connector' ) ),
								el( 'span', { className: 'whmcs-cycle-amount' }, '$99.00' )
							)
						)
					),
					el(
						'div',
						{ className: 'whmcs-connector-pricing-action' },
						el( 'span', { className: 'whmcs-connector-btn wp-element-button' }, __( 'Order Now →', 'whmcs-connector' ) )
					)
				);
			} );

			return el(
				'div',
				blockProps,
				inspector,
				el( 'div', { className: 'whmcs-connector-pricing-grid whmcs-cols-' + ( attributes.columns || '3' ) }, previewCards )
			);
		},

		save: function () {
			return null; // Dynamic render via render_callback
		},
	} );
} )();
