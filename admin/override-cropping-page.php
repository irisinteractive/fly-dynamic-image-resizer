<?php
/** @var array $image_sizes */
/** @var int $post_id */
dump( $image_sizes );

?>
<div class="wrap">
	<h2 class="nav-tab-wrapper">
		<?php foreach ( $image_sizes as $name => $size ): ?>
			<a class="nav-tab" href="" onclick="switchTab('<?php print $name; ?>')"><?php print $name; ?></a>
		<?php endforeach; ?>
	</h2>
	<?php foreach ( $image_sizes as $name => $size ):
		$image = fly_get_attachment_image_src( $post_id, $name );
		?>
		<div id="<?php print $name; ?>">
			<h3><?php print $name; ?></h3>
			<img id="img-<?php print $name; ?>" src="<?php print $image['src'] ?>" alt="<?php print $name; ?>" width="<?php print $image['width'] ?>" height="<?php print $image['height'] ?>">
			<script>
                jQuery(document).ready(function() {
                    const width = <?php print $image['width']; ?>;
                    const height = <?php print $image['height']; ?>;

                    jQuery('#img-<?php print $name; ?>').Jcrop({
	                    aspectRatio: ( width / height ),
                        allowSelect: true,
                        allowMove: true,
                        allowResize: true,
                        setSelect: [
                            0, 0, width, height
                        ],
                        handleSize: 1,
                        drawBorders: true,
                        drawEdges: true,
                    });
                });
			</script>
		</div>
	<?php endforeach; ?>
</div>
