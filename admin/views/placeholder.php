<?php
/**
 * Vista: marcador de módulos de fases futuras.
 * Variables disponibles: $info = array( titulo, fase, descripcion ).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap zair-wrap">
	<h1>ZEROX AI · <?php echo esc_html( $info[0] ); ?></h1>
	<div class="zair-placeholder">
		<p><span class="zair-badge zair-badge-pending"><?php echo esc_html( $info[1] ); ?></span></p>
		<p><?php echo esc_html( $info[2] ); ?></p>
		<p class="zair-meta">Este módulo forma parte de la arquitectura definitiva y se implementará en la fase indicada.</p>
	</div>
</div>
