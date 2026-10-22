/**
 * Front end: "Read more" toggles and carousel buttons. No dependencies.
 */

function initTextToggles( root ) {
	root.querySelectorAll( '.isurev-more[hidden]' ).forEach( ( button ) => {
		const text = root.querySelector(
			`#${ button.getAttribute( 'aria-controls' ) }`
		);

		if ( ! text || text.scrollHeight <= text.clientHeight + 1 ) {
			return;
		}

		const labelMore = button.textContent;
		button.hidden = false;
		button.addEventListener( 'click', () => {
			const expanded = button.getAttribute( 'aria-expanded' ) === 'true';
			button.setAttribute( 'aria-expanded', String( ! expanded ) );
			button.textContent = expanded
				? labelMore
				: button.dataset.labelLess;
			text.classList.toggle( 'is-expanded', ! expanded );
		} );
	} );
}

function initSlider( root ) {
	const viewport = root.querySelector( '.isurev__viewport' );
	const buttons = root.querySelectorAll( '[data-isurev-nav]' );

	if ( ! viewport || ! buttons.length ) {
		return;
	}

	const update = () => {
		const max = viewport.scrollWidth - viewport.clientWidth - 1;
		buttons.forEach( ( button ) => {
			const direction = Number( button.dataset.isurevNav );
			button.disabled =
				direction < 0
					? viewport.scrollLeft <= 0
					: viewport.scrollLeft >= max;
		} );
	};

	buttons.forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			const item = viewport.querySelector( '.isurev__item' );
			const step = item ? item.getBoundingClientRect().width : 300;
			viewport.scrollBy( {
				left: Number( button.dataset.isurevNav ) * step,
			} );
		} )
	);

	viewport.addEventListener( 'scroll', update, { passive: true } );
	window.addEventListener( 'resize', update, { passive: true } );
	update();
}

function init() {
	document.querySelectorAll( '.isurev' ).forEach( ( root ) => {
		if ( root.dataset.isurevReady ) {
			return;
		}
		root.dataset.isurevReady = '1';
		initTextToggles( root );
		if ( root.classList.contains( 'isurev--slider' ) ) {
			initSlider( root );
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}
