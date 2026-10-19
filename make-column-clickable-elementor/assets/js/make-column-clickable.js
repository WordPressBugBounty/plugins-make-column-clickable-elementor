( function() {
  var wrapperSelector     = '.make-column-clickable-elementor',
      linkClass           = 'make-column-clickable-link',
      interactiveSelector = 'a, button, input, select, textarea, label, summary, iframe, video, audio, [role="button"], .no-link';

  function isEditor() {
    return document.body.classList.contains( 'elementor-editor-active' );
  }

  // The real link rendered by PHP, or a hidden one created on demand (legacy mode).
  function getLink( wrapper ) {
    for ( var i = 0; i < wrapper.children.length; i++ ) {
      if ( wrapper.children[ i ].classList.contains( linkClass ) ) {
        return wrapper.children[ i ];
      }
    }

    var url = wrapper.getAttribute( 'data-column-clickable' );

    if ( ! url ) {
      return null;
    }

    var link = document.createElement( 'a' );

    link.className     = linkClass;
    link.href          = url;
    link.target        = wrapper.getAttribute( 'data-column-clickable-blank' ) || '_self';
    link.style.display = 'none';

    if ( wrapper.hasAttribute( 'data-column-clickable-track' ) ) {
      link.setAttribute( 'data-column-clickable-track', '1' );
    }

    wrapper.appendChild( link );

    return link;
  }

  function track( link ) {
    if ( ! link.hasAttribute( 'data-column-clickable-track' ) ) {
      return;
    }

    var wrapper = link.closest( wrapperSelector ),
        params  = {
          link_url: link.href,
          link_text: ( link.textContent || '' ).trim(),
          element_id: wrapper ? wrapper.getAttribute( 'data-id' ) : ''
        };

    if ( typeof window.gtag === 'function' ) {
      window.gtag( 'event', 'column_click', params );
    } else if ( Array.isArray( window.dataLayer ) ) {
      params.event = 'column_click';
      window.dataLayer.push( params );
    }
  }

  function openInNewTab( link ) {
    track( link );
    window.open( link.href, '_blank', 'noopener' );
  }

  // Wrapper whose link should handle a click that didn't land on the link itself.
  function getClickedWrapper( e ) {
    if ( isEditor() || ! e.target.closest || e.target.closest( interactiveSelector ) ) {
      return null;
    }

    // Selecting text should not navigate away.
    if ( window.getSelection && window.getSelection().toString() ) {
      return null;
    }

    return e.target.closest( wrapperSelector );
  }

  function isHashLink( link ) {
    return '#' === ( link.getAttribute( 'href' ) || '' ).charAt( 0 );
  }

  document.addEventListener( 'click', function( e ) {
    var clickedLink = e.target.closest && e.target.closest( '.' + linkClass );

    if ( clickedLink ) {
      track( clickedLink );
      return;
    }

    var wrapper = getClickedWrapper( e ),
        link    = wrapper && getLink( wrapper );

    if ( ! link ) {
      return;
    }

    e.preventDefault();

    if ( ( e.ctrlKey || e.metaKey || e.shiftKey ) && ! isHashLink( link ) ) {
      openInNewTab( link );
      return;
    }

    // A real click lets Elementor handle popups, lightboxes and anchors natively.
    link.click();
  } );

  // Middle click outside the link area.
  document.addEventListener( 'auxclick', function( e ) {
    if ( 1 !== e.button ) {
      return;
    }

    var clickedLink = e.target.closest && e.target.closest( '.' + linkClass );

    if ( clickedLink ) {
      track( clickedLink );
      return;
    }

    var wrapper = getClickedWrapper( e ),
        link    = wrapper && getLink( wrapper );

    if ( link && ! isHashLink( link ) ) {
      e.preventDefault();
      openInNewTab( link );
    }
  } );

  // Keep inner links and buttons clickable on browsers without CSS :has() support.
  function raiseInteractiveWidgets() {
    if ( window.CSS && CSS.supports && CSS.supports( 'selector(:has(a))' ) ) {
      return;
    }

    var selector = interactiveSelector.split( ', ' ).map( function( item ) {
          return wrapperSelector + ' ' + item;
        } ).join( ', ' ),
        elements = document.querySelectorAll( selector );

    for ( var i = 0; i < elements.length; i++ ) {
      var widget = elements[ i ].classList.contains( linkClass ) ? null : elements[ i ].closest( '.elementor-widget' );

      if ( widget ) {
        widget.classList.add( 'make-column-clickable-raised' );
      }
    }
  }

  if ( 'loading' === document.readyState ) {
    document.addEventListener( 'DOMContentLoaded', raiseInteractiveWidgets );
  } else {
    raiseInteractiveWidgets();
  }
} )();
