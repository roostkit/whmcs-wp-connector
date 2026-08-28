/**
 * WHMCS Login Form Block — Gutenberg Native Inline Editing.
 */
( function () {
	'use strict';

	var el = window.wp.element.createElement;
	var registerBlockType = window.wp.blocks.registerBlockType;
	var InspectorControls = window.wp.blockEditor.InspectorControls;
	var RichText = window.wp.blockEditor.RichText;
	var useBlockProps = window.wp.blockEditor.useBlockProps;
	var PanelBody = window.wp.components.PanelBody;
	var TextControl = window.wp.components.TextControl;
	var __ = window.wp.i18n.__;

	registerBlockType( 'whmcs/login-form', {
		edit: function Edit( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: 'whmcs-connector-login whmcs-connector-login-editor-mode',
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Login Form Settings', 'whmcs-connector' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Form Heading', 'whmcs-connector' ),
						value: attributes.title,
						onChange: function ( value ) {
							setAttributes( { title: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Email Label', 'whmcs-connector' ),
						value: attributes.email_label,
						onChange: function ( value ) {
							setAttributes( { email_label: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Password Label', 'whmcs-connector' ),
						value: attributes.password_label,
						onChange: function ( value ) {
							setAttributes( { password_label: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Submit Button Text', 'whmcs-connector' ),
						value: attributes.submit_label,
						onChange: function ( value ) {
							setAttributes( { submit_label: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Redirect URL', 'whmcs-connector' ),
						help: __( 'Optional URL to redirect to after successful login. Must match your WHMCS domain.', 'whmcs-connector' ),
						value: attributes.redirect,
						onChange: function ( value ) {
							setAttributes( { redirect: value } );
						},
					} )
				)
			);

			var formContent = el(
				'div',
				{ className: 'whmcs-connector-login-preview-container' },
				el( RichText, {
					tagName: 'h3',
					className: 'whmcs-connector-login-title',
					value: attributes.title,
					onChange: function ( value ) {
						setAttributes( { title: value } );
					},
					placeholder: __( 'Sign in to your account', 'whmcs-connector' ),
				} ),
				el(
					'div',
					{ className: 'whmcs-connector-login-form' },
					el(
						'div',
						{ className: 'whmcs-connector-field' },
						el( RichText, {
							tagName: 'label',
							className: 'whmcs-connector-field-label',
							value: attributes.email_label,
							onChange: function ( value ) {
								setAttributes( { email_label: value } );
							},
							placeholder: __( 'Email address', 'whmcs-connector' ),
						} ),
						el( 'input', {
							type: 'email',
							placeholder: 'name@example.com',
							disabled: true,
							className: 'whmcs-connector-input-mock',
						} )
					),
					el(
						'div',
						{ className: 'whmcs-connector-field' },
						el( RichText, {
							tagName: 'label',
							className: 'whmcs-connector-field-label',
							value: attributes.password_label,
							onChange: function ( value ) {
								setAttributes( { password_label: value } );
							},
							placeholder: __( 'Password', 'whmcs-connector' ),
						} ),
						el( 'input', {
							type: 'password',
							placeholder: '••••••••',
							disabled: true,
							className: 'whmcs-connector-input-mock',
						} )
					),
					el(
						'div',
						{ className: 'whmcs-connector-field whmcs-connector-submit' },
						el( RichText, {
							tagName: 'span',
							className: 'whmcs-connector-btn wp-element-button wp-block-button__link is-style-fill',
							value: attributes.submit_label,
							onChange: function ( value ) {
								setAttributes( { submit_label: value } );
							},
							placeholder: __( 'Sign in', 'whmcs-connector' ),
						} )
					)
				)
			);

			return el( 'div', blockProps, inspector, formContent );
		},

		save: function () {
			return null; // Rendered dynamically in PHP with nonce & CSRF protection
		},
	} );
} )();
