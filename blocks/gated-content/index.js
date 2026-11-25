( function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var createElement = wp.element.createElement;
	var __ = wp.i18n.__;

	registerBlockType( 'wnrcg/gated-content', {
		edit: function Edit( props ) {
			var blockProps = useBlockProps( {
				className: 'wnrcg-gated-content-editor',
			} );
			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				template: [
					[
						'core/paragraph',
						{
							placeholder: __(
								'Add gated content inside this section…',
								'wp-no-registration-content-gate'
							),
						},
					],
				],
			} );

			return createElement(
				wp.element.Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __( 'Gated section', 'wp-no-registration-content-gate' ), initialOpen: true },
						createElement( TextControl, {
							label: __( 'Admin label (optional)', 'wp-no-registration-content-gate' ),
							help: __(
								'Not shown on the front end. Helps identify sections in the editor.',
								'wp-no-registration-content-gate'
							),
							value: props.attributes.sectionTitle || '',
							onChange: function ( value ) {
								props.setAttributes( { sectionTitle: value } );
							},
						} )
					)
				),
				createElement(
					'div',
					{ className: 'wnrcg-gated-content-editor__badge' },
					__( 'Gated — unlock required', 'wp-no-registration-content-gate' )
				),
				createElement( 'div', innerBlocksProps )
			);
		},
		save: function () {
			return wp.element.createElement( wp.blockEditor.InnerBlocks.Content, null );
		},
	} );
} )( window.wp );