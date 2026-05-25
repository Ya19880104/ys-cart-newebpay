<?php
/**
 * Regression: marketplace UI supports platform filters and category-prefixed versions.
 */

$root     = dirname( __DIR__, 2 );
$main     = file_get_contents( $root . '/ys-plugin-hub-client.php' );
$js       = file_get_contents( $root . '/assets/js/ys-marketplace.js' );
$template = file_get_contents( $root . '/templates/marketplace-page.php' );
$css      = file_get_contents( $root . '/assets/css/ys-marketplace.css' );
$ajax     = file_get_contents( $root . '/src/Admin/YSHubAjaxHandler.php' );

$failures = 0;

function ys_hub_client_v201_assert( bool $condition, string $message, int &$failures ): void {
	if ( ! $condition ) {
		++$failures;
		fwrite( STDERR, "FAIL: {$message}\n" );
		return;
	}

	echo "PASS: {$message}\n";
}

ys_hub_client_v201_assert(
	str_contains( $main, 'Version:     2.0.2' )
		&& str_contains( $main, "define( 'YS_HUB_CLIENT_VERSION', '2.0.2' );" )
		&& str_contains( $js, 'v2.0.2' )
		&& str_contains( $css, 'v2.0.2' ),
	'Hub Client version metadata is consistent',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $js, 'currentPlatform' )
		&& str_contains( $js, '.ys-platform-tab' )
		&& str_contains( $js, 'renderPlatformTabs' )
		&& str_contains( $js, 'data-platform' ),
	'marketplace JS has first-level platform filtering',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $js, 'category_label' )
		&& str_contains( $js, 'findCategoryLabel' )
		&& str_contains( $js, 'plugin.platform_label' )
		&& str_contains( $js, 'ys-plugin-taxonomy-badge' )
		&& str_contains( $js, 'ys-plugin-platform-badge' )
		&& str_contains( $js, 'ys-plugin-category-badge' ),
	'plugin cards render platform and category labels as badges before the version',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $template, 'id="ys-platform-tabs"' )
		&& str_contains( $template, 'data-platform="all"' )
		&& str_contains( $template, "esc_html__( '全部'" )
		&& ! str_contains( $template, '?券' )
		&& ! str_contains( $template, '?賂' )
		&& ! str_contains( $template, '??' ),
	'marketplace template renders readable all-labels without mojibake',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $css, '.ys-platform-tabs' )
		&& str_contains( $css, '.ys-plugin-taxonomy-tags' )
		&& str_contains( $css, '.ys-plugin-taxonomy-badge' ),
	'marketplace CSS styles platform tabs and taxonomy badges',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $js, ".ys-filter-tab:not(.ys-platform-tab)" ),
	'category tab click handler excludes first-level platform tabs',
	$failures
);

ys_hub_client_v201_assert(
	str_contains( $ajax, "'platforms'" )
		&& str_contains( $ajax, '$platforms' ),
	'client AJAX preserves platform choices from the Hub response/cache',
	$failures
);

exit( $failures > 0 ? 1 : 0 );
