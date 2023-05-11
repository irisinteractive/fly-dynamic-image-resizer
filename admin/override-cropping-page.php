<?php
/** @var array $image_sizes */
/** @var int $post_id */
/** @var array $overridden_sizes */
?>
<div class="fly-cropping-editor-container">
	<h2 class="nav-tab-wrapper">
		<?php foreach ( $image_sizes as $name => $size ): ?>
			<a class="nav-tab <?php print (array_key_first($image_sizes) !== $name) ?: 'nav-tab-active'; ?>" data-size-name="<?php print $name; ?>" onclick="switchTab('<?php print $name; ?>', event)">
				<?php print $name; ?>
				<small>(<?php print $size['size'][0] . "x" . $size['size'][1]; ?>)</small>
			</a>
		<?php endforeach; ?>
	</h2>
	<?php foreach ( $image_sizes as $name => $size ):
		$image = wp_get_attachment_image_src( $post_id, 'full' );
		?>
		<div id="<?php print $name; ?>" class="fly-cropping-editor" style="<?php print (array_key_first($image_sizes) === $name) ?: 'display:none'; ?>">
			<h3 class="fly-cropping-editor-title"><?php print $name; ?></h3>
			<div class="fly-cropping-editor-img">
				<img
					id="img-<?php print $name; ?>"
					src="<?php print $image[0]; ?>"
					width="<?php print $image[1]; ?>"
					height="<?php print $image[2]; ?>"
					data-post-id="<?php print $post_id; ?>"
					data-crop-width="<?php print $size['size'][0]; ?>"
					data-crop-height="<?php print $size['size'][1]; ?>"
					<?php if ( isset( $overridden_sizes[$name] ) ): ?>
					data-select-x="<?php print $overridden_sizes[$name]->x; ?>"
					data-select-y="<?php print $overridden_sizes[$name]->y; ?>"
					data-select-w="<?php print $overridden_sizes[$name]->w; ?>"
					data-select-h="<?php print $overridden_sizes[$name]->h; ?>"
					<?php endif; ?>
					data-size-name="<?php print $name; ?>"
					alt=""
				>
			</div>
			<div class="fly-cropping-editor-info">
				<span><?php _e('Width', 'fly-images'); ?> : <samp id="img-<?php print $name; ?>-w"></samp></span>
				<span><?php _e('Height', 'fly-images'); ?> : <samp id="img-<?php print $name; ?>-h"></samp></span>
				<span><?php _e('Top offset', 'fly-images'); ?> : <samp id="img-<?php print $name; ?>-to"></samp></span>
				<span><?php _e('Left offset', 'fly-images'); ?> : <samp id="img-<?php print $name; ?>-lo"></samp></span>
			</div>
		</div>
	<?php endforeach; ?>
	<div class="fly-cropping-editor-save">
		<button class="button button-primary button-large" data-nonce="<?php print wp_create_nonce('fly_override_cropping_save'); ?>" onclick="flySaveCropping(this)"><?php _e('Save', 'fly-images'); ?></button>
	</div>
</div>
<script>
    jQuery(function() {
        overrideImageCropping('<?php print array_key_first($image_sizes); ?>');
    });
	document.querySelector('#TB_overlay,#TB_closeWindowButton').addEventListener('click', event => {
        window.iasInstances.forEach(ias => ias.remove());
        window.iasInstances.clear();
        event.preventDefault();
	}, false)
</script>
