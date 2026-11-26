( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! config ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var ExternalLink = wp.components.ExternalLink;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var createElement = wp.element.createElement;
	var __ = wp.i18n.__;

	function ContentGatePanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( ! config.postTypes || config.postTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;
		var isProtected = meta[ config.metaKey ] === '1';
		var scopedMode = config.unlockMode === 'scoped';

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'wnrcg-content-gate',
				title: __( 'Content gate', 'wp-no-registration-content-gate' ),
				className: 'wnrcg-editor-panel',
			},
			createElement( ToggleControl, {
				label: __( 'Require unlock form', 'wp-no-registration-content-gate' ),
				help: __(
					'Restrict the whole page (like Ultimate Member page restriction). For part of a page only, leave this off and insert the “Gated content” block around that section.',
					'wp-no-registration-content-gate'
				),
				checked: isProtected,
				onChange: function ( value ) {
					var patch = {};
					patch[ config.metaKey ] = value ? '1' : '0';
					editPost( { meta: patch } );
				},
			} ),
			scopedMode
				? createElement( TextControl, {
						label: __( 'Gate scope', 'wp-no-registration-content-gate' ),
						help: __(
							'Same scope on related pages shares one unlock. One email always maps to one WordPress user.',
							'wp-no-registration-content-gate'
						),
						value: meta[ config.scopeMetaKey ] || '',
						onChange: function ( value ) {
							var patch = {};
							patch[ config.scopeMetaKey ] = value;
							editPost( { meta: patch } );
						},
				  } )
				: null,
			config.settingsUrl
				? createElement(
						'p',
						{ className: 'wnrcg-editor-panel__link' },
						createElement( ExternalLink, { href: config.settingsUrl }, __( 'Gate settings', 'wp-no-registration-content-gate' ) )
				  )
				: null
		);
	}

	registerPlugin( 'wnrcg-content-gate-panel', {
		render: ContentGatePanel,
		icon: 'lock',
	} );
} )( window.wp, window.wnrcgEditor );