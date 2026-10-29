<?php
/**
 * Earner-Pro: "Check this machine's viability" button on Advanced Product listings.
 *
 * Installed in WordPress as a WPCode snippet: Code Type "PHP Snippet", Auto Insert, Location "Frontend Only".
 * Paste everything BELOW the opening <?php line; WPCode adds its own.
 *
 * Opens /earner-pro/ pre-filled with the listing's machine, price, hours and year.
 * The machine is read from the listing TITLE, not the Make/Model fields: on the site every used listing has
 * Make "Caterpiller" (John Deere and Komatsu too) and some Model fields are wrong. Only an exact
 * machines.json name is sent; a listing with no match gets price, hours and year only.
 *
 * The theme doesn't render listings through the_content, so a small script places the button:
 * under the "Call our seller" box on used listings, or in the action bar on Rippa pages.
 */
add_action( 'wp_footer', function () {
	if ( ! is_singular( 'ap_product' ) ) return;
	$id = get_queried_object_id();
	if ( ! $id ) return;

	$title = trim( (string) get_post_field( 'post_title', $id ) );

	// Attachments and forklifts: the tool has no data for them. The title check also catches
	// attachments filed under a machine category (e.g. "CAT D6N XL Dozer Sweep and Screens").
	$skip = array( 'stick-rakes', 'rippers', 'tree-pushers', 'sweeps-and-screens', 'afe-mulchers', 'rippa-forklift' );
	if ( has_term( $skip, 'ap_category', $id ) ) return;
	if ( preg_match( '/\b(sweeps?|screens?|rakes?|pushers?|mulchers?|rippers?)\b/i', $title ) ) return;

	$norm = function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };

	// Site name → exact machines.json key, for names that differ between the two.
	$aliases = array(
		'Rippa RL06' => 'Rippa RL06 (wheel loader)',
		// 'Rippa R18'  => 'Rippa R18 PRO',   // enable once the client confirms they're the same machine
		// 'Rippa RS06' => 'Rippa RS06 PRO',
	);
	$alias_keys = array();
	foreach ( $aliases as $site_name => $tool_key ) $alias_keys[ $norm( $site_name ) ] = $tool_key;

	$keys = array();
	$file = ABSPATH . 'earner-pro/machines.json';
	$data = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : null;
	if ( is_array( $data ) ) {
		foreach ( array_keys( $data ) as $key ) $keys[ $norm( $key ) ] = $key;
	}

	// "2018 CAT D6K2 XL with 6,725 hrs" → year 2018, words [Caterpillar, D6K2, XL, with, …].
	// Some titles use non-breaking spaces, so split on those too.
	$words = preg_split( '/[\s\x{00A0}\x{202F}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $words ) ) $words = array();
	$year  = '';
	while ( $words && preg_match( '/^(new|(?:19|20)\d{2})$/i', $words[0] ) ) {
		if ( preg_match( '/^\d{4}$/', $words[0] ) ) $year = $words[0];
		array_shift( $words );
	}
	$brands = array( 'cat' => 'Caterpillar', 'caterpiller' => 'Caterpillar', 'jd' => 'John Deere' );
	if ( $words && isset( $brands[ strtolower( $words[0] ) ] ) ) $words[0] = $brands[ strtolower( $words[0] ) ];

	// Longest run of leading words that is exactly a machine name, matched on whole words only,
	// so "D3K2" never becomes "D3" and "D5K2" never becomes "D5".
	$machine = '';
	$joined  = '';
	foreach ( array_slice( $words, 0, 6 ) as $word ) {
		$joined .= $norm( $word );
		if ( isset( $alias_keys[ $joined ] ) ) $machine = $alias_keys[ $joined ];
		elseif ( isset( $keys[ $joined ] ) ) $machine = $keys[ $joined ];
	}

	$args = array( 'src' => wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) );
	if ( $machine ) $args['machine'] = $machine;

	$price = get_post_meta( $id, 'ap_price', true );
	if ( is_numeric( $price ) && $price > 0 ) $args['price'] = $price;

	$hours = get_post_meta( $id, 'hours', true );
	if ( is_numeric( $hours ) && $hours >= 0 ) $args['hours'] = (int) $hours;

	if ( $year ) $args['year'] = $year;

	$url = home_url( '/earner-pro/' ) . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

	echo '<style>'
		. '.ap-templaza-sidebar a.earner-pro-cta--sidebar{display:flex;align-items:center;justify-content:center;gap:12px;'
		.   'height:60px;margin-top:20px;padding:0 30px;background:#FDC71F;color:#1d1d1d;border:0;border-radius:0;'
		.   'font-family:"Kanit",Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;text-transform:uppercase;'
		.   'text-decoration:none;transition:background .3s,color .3s}'
		. '.ap-templaza-sidebar a.earner-pro-cta--sidebar:hover,.ap-templaza-sidebar a.earner-pro-cta--sidebar:focus{background:#1d1d1d;color:#FDC71F}'
		. '</style>';

	echo '<script>(function () {'
		. 'var url = ' . wp_json_encode( esc_url_raw( $url ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';'
		. 'function addButton(el, where, className, icon) {'
		.   'var a = document.createElement("a");'
		.   'a.href = url; a.className = className + " earner-pro-cta";'
		.   'if (icon) { var i = document.createElement("i"); i.className = icon; i.setAttribute("aria-hidden", "true"); a.appendChild(i); }'
		.   'a.appendChild(document.createTextNode("Check this machine’s viability"));'
		.   'el.insertAdjacentElement(where, a);'
		. '}'
		. 'document.querySelectorAll(".ap-templaza-sidebar .call-to-buy").forEach(function (el) {'
		.   'addButton(el, "afterend", "earner-pro-cta--sidebar", "fas fa-calculator");'
		. '});'
		. 'document.querySelectorAll(".rippa-action-bar").forEach(function (el) {'
		.   'addButton(el, "beforeend", "rippa-btn rippa-btn-outline", "");'
		. '});'
		. '})();</script>';
} );
