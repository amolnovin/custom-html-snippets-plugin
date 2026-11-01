/**
 * موتور اجرای با تاخیر قطعه کدها.
 *
 * دو حالت:
 *  - interaction : اجرا پس از اولین تعامل کاربر (یا ۶ ثانیه).
 *  - viewport    : اجرا هنگام ورود عنصر به دید کاربر (IntersectionObserver).
 */
( function () {
	'use strict';

	function activate( template ) {
		if ( ! template || ! template.parentNode ) {
			return;
		}

		var target = template.getAttribute( 'data-target' );
		var range = document.createRange();

		range.selectNode( document.body || document.documentElement );

		var fragment = range.createContextualFragment( template.innerHTML );

		if ( 'head' === target ) {
			document.head.appendChild( fragment );
		} else if ( 'footer' === target ) {
			document.body.appendChild( fragment );
		} else {
			template.parentNode.insertBefore( fragment, template );
		}

		template.remove();
	}

	function initInteraction( templates ) {
		var fired = false;
		var events = [ 'mousemove', 'touchstart', 'scroll', 'keydown', 'click' ];

		function fire() {
			if ( fired ) {
				return;
			}

			fired = true;

			events.forEach( function ( name ) {
				window.removeEventListener( name, fire );
			} );

			templates.forEach( activate );
		}

		events.forEach( function ( name ) {
			window.addEventListener( name, fire, { passive: true } );
		} );

		window.setTimeout( fire, 6000 );
	}

	function boot() {
		var all = Array.prototype.slice.call(
			document.querySelectorAll( 'template.chs-delay-run' )
		);

		if ( ! all.length ) {
			return;
		}

		var viewport = all.filter( function ( t ) {
			return 'viewport' === t.getAttribute( 'data-mode' );
		} );

		var interaction = all.filter( function ( t ) {
			return 'viewport' !== t.getAttribute( 'data-mode' );
		} );

		if ( viewport.length ) {
			initViewportSimple( viewport );
		}

		if ( interaction.length ) {
			initInteraction( interaction );
		}
	}

	/**
	 * نسخه ساده و قابل اتکای رصد دید.
	 */
	function initViewportSimple( templates ) {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			templates.forEach( activate );
			return;
		}

		templates.forEach( function ( template ) {
			var anchor = template.parentNode;

			if ( ! anchor ) {
				activate( template );
				return;
			}

			var observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							observer.disconnect();
							activate( template );
						}
					} );
				},
				{ rootMargin: '200px 0px' }
			);

			observer.observe( anchor );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
