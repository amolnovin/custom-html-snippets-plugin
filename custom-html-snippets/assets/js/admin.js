/**
 * اسکریپت پیشخوان: ادیتور کد، جستجوی صفحات، سازنده شرط‌ها،
 * اعتبارسنجی نگارش PHP و پیش‌نمایش زنده.
 */
( function ( $ ) {
	'use strict';

	var settings = window.chsAdmin || {};
	var editors = {};

	/* ------------------------------------------------------------------ */
	/* ادیتور کد                                                           */
	/* ------------------------------------------------------------------ */

	function initEditors() {
		if ( typeof wp === 'undefined' || ! wp.codeEditor || ! settings.editors ) {
			return;
		}

		$.each( settings.editors, function ( id, config ) {
			if ( ! config || ! document.getElementById( id ) ) {
				return;
			}

			try {
				editors[ id ] = wp.codeEditor.initialize( id, config );
			} catch ( e ) {
				window.console && console.warn( 'CHS editor failed:', id, e );
			}
		} );
	}

	/**
	 * خواندن مقدار یک فیلد (چه ادیتور باشد چه textarea ساده).
	 */
	function fieldValue( id ) {
		if ( editors[ id ] && editors[ id ].codemirror ) {
			return editors[ id ].codemirror.getValue();
		}

		var el = document.getElementById( id );

		return el ? el.value : '';
	}

	/* ------------------------------------------------------------------ */
	/* جستجوی صفحات (قوانین استثنا / اجرای خودکار)                        */
	/* ------------------------------------------------------------------ */

	function initPageSearch( $section ) {
		var $input = $section.find( '.chs-page-search' );
		var $results = $section.find( '.chs-page-results' );
		var $list = $section.find( '.chs-page-list' );
		var $hidden = $section.find( '.chs-page-ids' );
		var timer;

		function loadList() {
			var ids = $hidden.val();

			if ( ! ids ) {
				$list.empty();
				return;
			}

			$.post(
				settings.ajaxUrl,
				{ action: 'chs_get_page_titles', nonce: settings.nonce, ids: ids },
				function ( response ) {
					if ( response && response.success ) {
						$list.html( response.data.html );
					}
				}
			);
		}

		function addItem( id, title, typeLabel ) {
			var ids = $hidden.val();
			var arr = ids ? ids.split( ',' ) : [];

			if ( -1 !== arr.indexOf( String( id ) ) ) {
				return;
			}

			arr.push( id );
			$hidden.val( arr.join( ',' ) );

			$list.append(
				$( '<li/>' )
					.attr( 'data-id', id )
					.append( $( '<span class="chs-page-title"/>' ).text( title ) )
					.append( $( '<span class="chs-page-type"/>' ).text( typeLabel ) )
					.append( $( '<a class="chs-page-remove" href="#"/>' ).html( '&times;' ) )
			);

			$results.hide();
			$input.val( '' );
		}

		$input.on( 'input', function () {
			var q = $( this ).val();

			window.clearTimeout( timer );

			if ( q.length < 2 ) {
				$results.hide();
				return;
			}

			timer = window.setTimeout( function () {
				$.post(
					settings.ajaxUrl,
					{ action: 'chs_search_posts', nonce: settings.nonce, q: q },
					function ( response ) {
						$results.empty().hide();

						if ( ! response || ! response.success || ! response.data.length ) {
							return;
						}

						$.each( response.data, function ( i, item ) {
							$( '<a href="#"/>' )
								.attr( {
									'data-id': item.id,
									'data-title': item.title,
									'data-type': item.post_type_label
								} )
								.text( item.title )
								.append(
									$( '<span class="chs-result-type"/>' ).text( ' (' + item.post_type_label + ')' )
								)
								.appendTo( $results );
						} );

						$results.show();
					}
				);
			}, 300 );
		} );

		$results.on( 'click', 'a', function ( e ) {
			e.preventDefault();
			var $el = $( this );
			addItem( $el.data( 'id' ), $el.data( 'title' ), $el.data( 'type' ) );
		} );

		$list.on( 'click', '.chs-page-remove', function ( e ) {
			e.preventDefault();

			var $li = $( this ).closest( 'li' );
			var id = String( $li.data( 'id' ) );
			var arr = ( $hidden.val() || '' ).split( ',' ).filter( function ( v ) {
				return v !== id && '' !== v;
			} );

			$hidden.val( arr.join( ',' ) );
			$li.remove();
		} );

		loadList();
	}

	/* ------------------------------------------------------------------ */
	/* سازنده شرط‌های پیشرفته                                              */
	/* ------------------------------------------------------------------ */

	function initConditions() {
		var $wrap = $( '#chs-conditions-builder' );

		if ( ! $wrap.length ) {
			return;
		}

		var $data = $( '#chs-conditions-data' );
		var $rows = $wrap.find( '.chs-conditions-rows' );
		var types = settings.conditions || {};
		var rules = [];

		try {
			rules = JSON.parse( $data.val() || '[]' );
		} catch ( e ) {
			rules = [];
		}

		function sync() {
			var collected = [];

			$rows.find( '.chs-condition-row' ).each( function () {
				var $row = $( this );

				collected.push( {
					type: $row.find( '.chs-cond-type' ).val(),
					operator: $row.find( '.chs-cond-op' ).val(),
					value: $row.find( '.chs-cond-value' ).val()
				} );
			} );

			$data.val( JSON.stringify( collected ) );
		}

		function addRow( rule ) {
			rule = rule || { type: 'post_type', operator: 'is', value: '' };

			var $row = $( '<div class="chs-condition-row"/>' );
			var $type = $( '<select class="chs-cond-type"/>' );

			$.each( types, function ( key, label ) {
				$( '<option/>' ).val( key ).text( label ).appendTo( $type );
			} );

			$type.val( rule.type );

			var $op = $( '<select class="chs-cond-op"/>' )
				.append( $( '<option/>' ).val( 'is' ).text( settings.i18n.is ) )
				.append( $( '<option/>' ).val( 'is_not' ).text( settings.i18n.isNot ) );

			$op.val( rule.operator );

			var $value = $( '<input type="text" class="chs-cond-value"/>' ).val( rule.value );

			var $remove = $( '<button type="button" class="button-link chs-cond-remove"/>' )
				.text( settings.i18n.remove );

			$row.append( $type, $op, $value, $remove ).appendTo( $rows );

			sync();
		}

		$rows.on( 'change input', 'select, input', sync );

		$rows.on( 'click', '.chs-cond-remove', function () {
			$( this ).closest( '.chs-condition-row' ).remove();
			sync();
		} );

		$( '#chs-add-condition' ).on( 'click', function () {
			addRow();
		} );

		if ( rules.length ) {
			$.each( rules, function ( i, rule ) {
				addRow( rule );
			} );
		}
	}

	/* ------------------------------------------------------------------ */
	/* اعتبارسنجی نگارش PHP                                                */
	/* ------------------------------------------------------------------ */

	function initValidator() {
		var $button = $( '#chs-validate-php' );

		if ( ! $button.length ) {
			return;
		}

		var $result = $( '.chs-validate-result' );

		$button.on( 'click', function () {
			$result.removeClass( 'is-ok is-error' ).text( '…' );

			$.post(
				settings.ajaxUrl,
				{ action: 'chs_validate_php', nonce: settings.nonce, php: fieldValue( 'chs_php' ) },
				function ( response ) {
					if ( response && response.success ) {
						$result.addClass( 'is-ok' ).text( response.data.message );
					} else {
						$result
							.addClass( 'is-error' )
							.text( ( response && response.data && response.data.message ) || 'error' );
					}
				}
			);
		} );
	}

	/* ------------------------------------------------------------------ */
	/* پیش‌نمایش زنده                                                       */
	/* ------------------------------------------------------------------ */

	function initPreview() {
		var frame = document.getElementById( 'chs-preview-frame' );

		if ( ! frame ) {
			return;
		}

		var timer;

		function refresh() {
			$.post(
				settings.ajaxUrl,
				{
					action: 'chs_preview',
					nonce: settings.nonce,
					html: fieldValue( 'chs_html' ),
					css: fieldValue( 'chs_css' ),
					js: fieldValue( 'chs_js' )
				},
				function ( response ) {
					if ( response && response.success ) {
						frame.srcdoc = response.data.document;
					}
				}
			);
		}

		$( '#chs-refresh-preview' ).on( 'click', refresh );

		$( '#chs-auto-preview' ).on( 'change', function () {
			if ( ! this.checked ) {
				return;
			}

			refresh();

			$.each( [ 'chs_html', 'chs_css', 'chs_js' ], function ( i, id ) {
				if ( editors[ id ] && editors[ id ].codemirror ) {
					editors[ id ].codemirror.on( 'change', function () {
						if ( ! document.getElementById( 'chs-auto-preview' ).checked ) {
							return;
						}

						window.clearTimeout( timer );
						timer = window.setTimeout( refresh, 700 );
					} );
				}
			} );
		} );

		refresh();
	}

	/* ------------------------------------------------------------------ */

	$( function () {
		initEditors();

		$( '#chs_page_rules_box .chs-rules-section' ).each( function () {
			initPageSearch( $( this ) );
		} );

		initConditions();
		initValidator();
		initPreview();
	} );
} )( jQuery );
