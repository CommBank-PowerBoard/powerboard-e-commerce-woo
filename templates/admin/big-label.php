<?php
declare( strict_types=1 );
/**
 * This file uses a function (esc_html) from WordPress
 *
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $data['value'] ) ) {
	$value = $data['value'];
}

?>
<tr>
	<th scope="row" class="titledesc">
		<h1>
		<?php
		echo isset( $value['title'] ) ? esc_html( $value['title'] ) : '';
		?>
		</h1>
	</th>
	<td class="forminp">
	</td>
</tr>
