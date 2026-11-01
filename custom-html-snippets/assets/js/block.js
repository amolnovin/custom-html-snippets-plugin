/**
 * بلوک گوتنبرگ «قطعه کد سفارشی».
 *
 * بدون نیاز به مرحله ساخت (build step) نوشته شده تا مستقیماً قابل استفاده باشد.
 */
( function ( blocks, element, components, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var data = window.chsBlock || { snippets: [], i18n: {} };
	var text = data.i18n || {};

	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var PanelBody = components.PanelBody;
	var Placeholder = components.Placeholder;
	var Notice = components.Notice;

	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;

	function options() {
		var list = [ { value: 0, label: text.none || '—' } ];

		( data.snippets || [] ).forEach( function ( item ) {
			list.push( {
				value: item.value,
				label: item.active ? item.label : item.label + ' (' + ( text.inactive || 'inactive' ) + ')'
			} );
		} );

		return list;
	}

	function findSnippet( id ) {
		var found = null;

		( data.snippets || [] ).forEach( function ( item ) {
			if ( item.value === id ) {
				found = item;
			}
		} );

		return found;
	}

	blocks.registerBlockType( 'chs/snippet', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var snippetId = attributes.snippetId || 0;
			var selected = findSnippet( snippetId );

			var blockProps = useBlockProps ? useBlockProps() : {};

			var inspector = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					PanelBody,
					{ title: text.settings || 'Settings', initialOpen: true },
					el( SelectControl, {
						label: text.select || 'Select snippet',
						value: snippetId,
						options: options(),
						onChange: function ( value ) {
							setAttributes( { snippetId: parseInt( value, 10 ) || 0 } );
						}
					} ),
					el( ToggleControl, {
						label: text.replace || 'Replace page content',
						checked: !! attributes.replace,
						onChange: function ( value ) {
							setAttributes( { replace: !! value } );
						}
					} )
				)
			);

			var body;

			if ( ! ( data.snippets || [] ).length ) {
				body = el(
					Placeholder,
					{ icon: 'editor-code', label: text.title || 'Custom snippet' },
					el( 'p', {}, text.empty || 'No snippets yet.' )
				);
			} else if ( ! snippetId ) {
				body = el(
					Placeholder,
					{ icon: 'editor-code', label: text.title || 'Custom snippet' },
					el( SelectControl, {
						value: snippetId,
						options: options(),
						onChange: function ( value ) {
							setAttributes( { snippetId: parseInt( value, 10 ) || 0 } );
						}
					} ),
					el( 'p', {}, text.placeholder || '' )
				);
			} else {
				body = el(
					'div',
					{ className: 'chs-block-preview' },
					el( 'strong', {}, ( text.title || 'Snippet' ) + ': ' ),
					el( 'code', {}, '[my_snippet id="' + snippetId + '"]' ),
					selected && ! selected.active
						? el( Notice, { status: 'warning', isDismissible: false }, text.inactive || '' )
						: null
				);
			}

			return el( Fragment, {}, inspector, el( 'div', blockProps, body ) );
		},

		// رندر سمت سرور انجام می‌شود.
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n
);
