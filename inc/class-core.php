<?php
namespace JB\FlyImages;

use WP_Post;

class Core {

	/**
	 * Properties.
	 */
	private static $_instance = null;
	private $_image_sizes     = array();
	private $_fly_dir         = '';
	private $_capability      = 'manage_options';

	/**
	 * Get current instance.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$_instance ) {
			$class           = __CLASS__;
			self::$_instance = new $class();
		}
		return self::$_instance;
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		$this->_fly_dir    = apply_filters( 'fly_dir_path', $this->get_fly_dir() );
		$this->_capability = apply_filters( 'fly_images_user_capability', $this->_capability );

		$this->check_fly_dir();

		add_action( 'admin_menu', array( $this, 'admin_menu_item' ) );
		add_filter( 'media_row_actions', array( $this, 'media_row_action' ), 10, 3 );
		add_action( 'delete_attachment', array( $this, 'delete_attachment_fly_images' ) );

		add_action( 'switch_blog', array( $this, 'blog_switched' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_style' ));

		add_action( 'wp_ajax_fly_override_cropping_page', array( $this, 'fly_override_cropping_page'), 0, 0 );
		add_action( 'wp_ajax_fly_override_cropping_save', array( $this, 'fly_override_cropping_save'), 0, 0 );

		add_filter( 'attachment_fields_to_edit', array( $this, 'media_add_action'), 10, 2 );
	}

	public function media_add_action( $form_fields, $post ) {
		if ( wp_attachment_is_image( $post->ID ) && current_user_can( 'edit-post', $post->ID ) && ! empty( array_filter( fly_get_all_image_sizes(), fn($size) => $size['crop'] ) ) ) {
			$buttonText = __( 'Override cropped Fly sizes', 'fly-images');
			$buttonUrl = admin_url('admin-ajax.php') . "?action=fly_override_cropping_page&postId=" . $post->ID;
			$form_fields[ 'fly_action' ] = array(
				'input' => 'html',
				'html' => "<button class='button button-small button-primary fly-edit-cropping' onclick='tb_show(\"$buttonText\", \"$buttonUrl\")'>$buttonText</button>"
			);
		}
		return $form_fields;
	}

	public function fly_override_cropping_page() {
		wp_enqueue_script('fly-images', plugins_url('/fly-dynamic-image-resizer/assets/src/js/image-cropping-editor.js' ), array( 'jquery', 'imgareaselect' ));
		$image_sizes = array_filter( fly_get_all_image_sizes(), fn($size) => $size['crop'] );
		$post_id = intval($_GET['postId']);
		$overridden_sizes = $this->get_image_overridden_cropping( $post_id );
		include WP_PLUGIN_DIR . '/fly-dynamic-image-resizer/admin/override-cropping-page.php';
		wp_die();
	}

	public function fly_override_cropping_save() {
		if ( ! isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'fly_override_cropping_save' ) ) {
			wp_send_json_error(__('Your are not allowed to do this action.', 'fly-images'), 403);
		}

		if ( ! isset( $_POST['postId'], $_POST['name'], $_POST['x'], $_POST['y'], $_POST['w'], $_POST['h'] ) ) {
			wp_send_json_error(__('Missing parameters', 'fly-images'), 400);
		}
		if ( $this->override_image_cropping(
			intval($_POST['postId']),
			$_POST['name'],
			intval($_POST['x']),
			intval($_POST['y']),
			intval($_POST['w']),
			intval($_POST['h'])
		)) {
			wp_send_json_success(__('Cropping overridden for the size : ', 'fly-images') . $_POST['name']);
		} else {
			wp_send_json_error(null, 500);
		}
	}

	public function enqueue_scripts_and_style() {
		global $pagenow;
		if ( in_array( $pagenow, array( 'upload.php', 'post.php' ) ) ) {
			add_thickbox();
			wp_enqueue_script('fly-images', plugins_url('/fly-dynamic-image-resizer/assets/src/js/admin.js' ), array( 'jquery', 'imgareaselect' ));
			wp_enqueue_style('fly-images', plugins_url('/fly-dynamic-image-resizer/assets/src/css/admin.css' ));
		}
	}

	/**
	 * @param int $postId
	 *
	 * @return array
	 */
	public function get_image_overridden_cropping( int $postId ): array {
		return get_post_meta( $postId, 'fly_cropping_override', true ) ?: array();
	}

	/**
	 * @param int $postId
	 * @param string $size
	 * @param int $x
	 * @param int $y
	 * @param int $w
	 * @param int $h
	 *
	 * @return bool
	 */
	public function override_image_cropping( int $postId, string $size, int $x, int $y, int $w, int $h): bool {
		if ( !isset( $this->_image_sizes[$size] ) ) {
			return false;
		}
		$overridden_image_cropping = $this->get_image_overridden_cropping( $postId );
		$cropping = (object) array(
			'x' => $x,
			'y' => $y,
			'w' => $w,
			'h' => $h,
		);
		if ( isset($overridden_image_cropping[$size]) && $overridden_image_cropping[$size] == $cropping ) {
			return true;
		}
		$overridden_image_cropping[$size] = $cropping;
		return false !== update_post_meta( $postId, 'fly_cropping_override', $overridden_image_cropping);
	}

	/**
	 * Get the path to the directory where all Fly images are stored.
	 *
	 * @param  string $path
	 * @return string
	 */
	public function get_fly_dir( $path = '' ) {
		if ( empty( $this->_fly_dir ) ) {
			$wp_upload_dir = wp_upload_dir();
			return $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'fly-images' . ( '' !== $path ? DIRECTORY_SEPARATOR . $path : '' );
		} else {
			return $this->_fly_dir . ( '' !== $path ? DIRECTORY_SEPARATOR . $path : '' );
		}
	}

	/**
	 * Create fly images directory if it doesn't already exist.
	 */
	function check_fly_dir() {
		if ( ! is_dir( $this->_fly_dir ) ) {
			wp_mkdir_p( $this->_fly_dir );
		}
	}

	/**
	 * Check if the Fly images folder exists and is writeable.
	 *
	 * @return boolean
	 */
	public function fly_dir_writable() {
		return is_dir( $this->_fly_dir ) && wp_is_writable( $this->_fly_dir );
	}

	/**
	 * Add admin menu item.
	 */
	public function admin_menu_item() {
		add_management_page(
			__( 'Fly Images', 'fly-images' ),
			__( 'Fly Images', 'fly-images' ),
			$this->_capability,
			'fly-images',
			array( $this, 'options_page' )
		);
	}

	/**
	 * Add a new rows action to media library items.
	 *
	 * @param array $actions
	 * @param WP_Post $post
	 * @param bool $detached
	 *
	 * @return array
	 */
	public function media_row_action( array $actions, WP_Post $post, bool $detached ) {
		if ( 'image/' !== substr( $post->post_mime_type, 0, 6 ) || ! current_user_can( $this->_capability ) ) {
			return $actions;
		}

		$url                         = wp_nonce_url( admin_url( 'tools.php?page=fly-images&delete-fly-image&ids=' . $post->ID ), 'delete_fly_image', 'fly_nonce' );
		$actions['fly-image-delete'] = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( __( 'Delete all cached image sizes for this image', 'fly-images' ) ) . '">' . __( 'Delete Fly Images', 'fly-images' ) . '</a>';

		if ( wp_attachment_is_image( $post->ID ) && current_user_can( 'edit-post', $post->ID ) && ! empty( array_filter( fly_get_all_image_sizes(), fn($size) => $size['crop'] ) ) ) {
			add_thickbox();
			$actions['fly-edit-cropping'] = "<a class='fly-edit-cropping' href='" . admin_url('admin-ajax.php') . "?action=fly_override_cropping_page&postId=" . $post->ID . "'>" . __( 'Override cropped Fly sizes', 'fly-images') . "</a>";
		}

		return $actions;
	}

	/**
	 * Delete all fly images for an attachment.
	 *
	 * @param  integer $attachment_id
	 * @return boolean
	 */
	public function delete_attachment_fly_images( $attachment_id = 0 ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			return false;
		}

		WP_Filesystem();
		global $wp_filesystem;
		return $wp_filesystem->rmdir( $this->get_fly_dir( $attachment_id ), true );
	}

	/**
	 * Delete all the fly images.
	 *
	 * @return boolean
	 */
	public function delete_all_fly_images() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			return false;
		}

		WP_Filesystem();
		global $wp_filesystem;

		if ( $wp_filesystem->rmdir( $this->get_fly_dir(), true ) ) {
			$this->check_fly_dir();
			return true;
		}

		return false;
	}

	/**
	 * Options page.
	 */
	public function options_page() {
		// Check for actions
		if (
			isset( $_POST['fly_nonce'] ) // Input var okay.
			&& wp_verify_nonce( sanitize_key( $_POST['fly_nonce'] ), 'delete_all_fly_images' ) // Input var okay.
		) {
			// Delete all fly images.
			$this->delete_all_fly_images();
			echo '<div class="updated"><p>' . esc_html__( 'All cached images created on the fly have been deleted.', 'fly-images' ) . '</p></div>';
		} elseif (
			isset( $_GET['delete-fly-image'], $_GET['ids'], $_GET['fly_nonce'] ) // Input var okay.
			&& wp_verify_nonce( sanitize_key( $_GET['fly_nonce'] ), 'delete_fly_image' ) // Input var okay.
		) {
			// Delete all fly images for certain attachments.
			$ids = array_map( 'intval', array_map( 'trim', explode( ',', sanitize_key( $_GET['ids'] ) ) ) ); // Input var okay.
			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					$this->delete_attachment_fly_images( $id );
				}
				echo '<div class="updated"><p>' . esc_html__( 'Deleted all fly images for this image.', 'fly-images' ) . '</p></div>';
			}
		}

		// Show the template
		load_template( JB_FLY_PLUGIN_PATH . '/admin/options.php' );
	}

	/**
	 * Add image sizes to be created on the fly.
	 *
	 * @param  string   $size_name
	 * @param  integer  $width
	 * @param  integer  $height
	 * @param  boolean|array  $crop
	 * @return boolean
	 */
	public function add_image_size( $size_name, $width = 0, $height = 0, $crop = false ) {
		if ( empty( $size_name ) || ! $width || ! $height ) {
			return false;
		}

		$this->_image_sizes[ $size_name ] = [
			'size' => [ $width, $height ],
			'crop' => $crop,
		];

		return true;
	}

	/**
	 * Gets a previously declared image size.
	 *
	 * @param  string $size_name
	 * @return array
	 */
	public function get_image_size( $size_name = '' ) {
		if ( empty( $size_name ) || ! isset( $this->_image_sizes[ $size_name ] ) ) {
			return array();
		} else {
			return $this->_image_sizes[ $size_name ];
		}
	}

	/**
	 * Get all declared images sizes.
	 *
	 * @return array
	 */
	public function get_all_image_sizes() {
		return $this->_image_sizes;
	}

	/**
	 * Gets a dynamically generated image URL from the Fly_Images class.
	 *
	 * @param  integer  $attachment_id
	 * @param  mixed    $size
	 * @param  boolean|array  $crop
	 * @return array
	 */
	public function get_attachment_image_src( $attachment_id = 0, $size = '', $crop = null ) {
		if ( $attachment_id < 1 || empty( $size ) ) {
			return array();
		}

		// If size is 'full', we don't need a fly image
		if ( 'full' === $size ) {
			return wp_get_attachment_image_src( $attachment_id, 'full' );
		}

		// Get the attachment image
		$image = wp_get_attachment_metadata( $attachment_id );
		if ( false !== $image && $image ) {
			// Determine width and height based on size
			switch ( gettype( $size ) ) {
				case 'string':
					$image_size = $this->get_image_size( $size );
					if ( empty( $image_size ) ) {
						return array();
					}
					$width  = $image_size['size'][0];
					$height = $image_size['size'][1];
					$crop   = isset( $crop ) ? $crop : $image_size['crop'];
					break;
				case 'array':
					$width  = $size[0];
					$height = $size[1];
					break;
				default:
					return array();
			}

			$overriddenImageCropPos = $this->get_image_overridden_cropping( $attachment_id );
			if ( $crop === true && is_string( $size ) && isset( $overriddenImageCropPos[ $size ] ) ) {
				$crop = $overriddenImageCropPos[ $size ];
			}
			// Get file path
			$fly_dir       = $this->get_fly_dir( $attachment_id );
			$fly_file_path = $fly_dir . DIRECTORY_SEPARATOR . $this->get_fly_file_name( basename( $image['file'] ), $width, $height, $crop );

			// Check if file exsists
			if ( file_exists( $fly_file_path ) ) {
				$image_size = getimagesize( $fly_file_path );
				if ( ! empty( $image_size ) ) {
					return array(
						'src'    => $this->get_fly_path( $fly_file_path ),
						'width'  => $image_size[0],
						'height' => $image_size[1],
					);
				} else {
					return array();
				}
			}

			// Check if images directory is writeable
			if ( ! $this->fly_dir_writable() ) {
				return array();
			}

			// File does not exist, lets check if directory exists
			$this->check_fly_dir();

			// Get WP Image Editor Instance
			$image_path   = apply_filters(
				'fly_attached_file',
				get_attached_file( $attachment_id ),
				$attachment_id,
				$size,
				$crop
			);
			$image_editor = wp_get_image_editor( $image_path );
			if ( ! is_wp_error( $image_editor ) ) {

				if ( is_object( $crop ) ) {
					$image_editor->crop( $crop->x, $crop->y, $crop->w, $crop->h );
					$crop = false;
				}

				// Create new image
				$image_editor->resize( $width, $height, $crop );
				$image_editor->save( $fly_file_path );

				// Trigger action
				do_action( 'fly_image_created', $attachment_id, $fly_file_path );

				// Image created, return its data
				$image_dimensions = $image_editor->get_size();
				return array(
					'src'    => $this->get_fly_path( $fly_file_path ),
					'width'  => $image_dimensions['width'],
					'height' => $image_dimensions['height'],
				);
			}
		}

		// Something went wrong
		return array();
	}

	/**
	 * Get a dynamically generated image HTML from the Fly_Images class.
	 *
	 * Based on /wp-includes/media.php -> wp_get_attachment_image()
	 *
	 * @param  integer  $attachment_id
	 * @param  mixed    $size
	 * @param  boolean|array  $crop
	 * @param  array    $attr
	 * @return string
	 */
	public function get_attachment_image( $attachment_id = 0, $size = '', $crop = null, $attr = array() ) {
		if ( $attachment_id < 1 || empty( $size ) ) {
			return '';
		}

		// If size is 'full', we don't need a fly image
		if ( 'full' === $size ) {
			return wp_get_attachment_image( $attachment_id, $size, $attr );
		}

		$html  = '';
		$image = $this->get_attachment_image_src( $attachment_id, $size, $crop );
		if ( $image ) {
			$hwstring   = image_hwstring( $image['width'], $image['height'] );
			$size_class = $size;
			if ( is_array( $size_class ) ) {
				$size_class = join( 'x', $size );
			}
			$attachment   = get_post( $attachment_id );
			$default_attr = array(
				'src'   => $image['src'],
				'class' => "attachment-$size_class",
				'alt'   => trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
			);
			if ( empty( $default_attr['alt'] ) ) {
				$default_attr['alt'] = trim( strip_tags( $attachment->post_excerpt ) );
			}
			if ( empty( $default_attr['alt'] ) ) {
				$default_attr['alt'] = trim( strip_tags( $attachment->post_title ) );
			}

			$attr = wp_parse_args( $attr, $default_attr );
			$attr = apply_filters( 'fly_get_attachment_image_attributes', $attr, $attachment, $size );
			$attr = array_map( 'esc_attr', $attr );
			$html = rtrim( "<img $hwstring" );
			foreach ( $attr as $name => $value ) {
				$html .= " $name=" . '"' . $value . '"';
			}
			$html .= ' />';
		}

		return $html;
	}

	/**
	 * Get a file name based on parameters.
	 *
	 * @param  string  $file_name
	 * @param  string  $width
	 * @param  string  $height
	 * @param  boolean|array $crop
	 * @return string
	 */
	public function get_fly_file_name( $file_name, $width, $height, $crop ) {
		$file_name_only = pathinfo( $file_name, PATHINFO_FILENAME );
		$file_extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		$crop_extension = '';
		if ( true === $crop ) {
			$crop_extension = '-c';
		} elseif ( is_array( $crop ) ) {
			$crop_extension = '-' . implode( '', array_map( function( $position ) {
				return $position[0];
			}, $crop ) );
		} elseif ( is_object( $crop ) ) {
			$crop_extension = '-x' . $crop->x . '-y' . $crop->y . '-w' . $crop->w . '-h' . $crop->h;
		}

		/**
		 * Note: intval() for width and height is based on Image_Processor::resize()
		 */
		return $file_name_only . '-' . intval( $width ) . 'x' . intval( $height ) . $crop_extension . '.' . $file_extension;
	}

	/**
	 * Get the full path of an image based on it's absolute path.
	 *
	 * @param  string $absolute_path
	 * @return string
	 */
	public function get_fly_path( $absolute_path = '' ) {
		$wp_upload_dir = wp_upload_dir();
		$path          = $wp_upload_dir['baseurl'] . str_replace( $wp_upload_dir['basedir'], '', $absolute_path );
		return str_replace( DIRECTORY_SEPARATOR, '/', $path );
	}

	/**
	 * Get the absolute path of an image based on it's full path.
	 *
	 * @param  string $path
	 * @return string
	 */
	public function get_fly_absolute_path( $path = '' ) {
		$wp_upload_dir = wp_upload_dir();
		return $wp_upload_dir['basedir'] . str_replace( $wp_upload_dir['baseurl'], '', $path );
	}

	/**
	 * Update Fly Dir when a blog is switched.
	 *
	 * @return void
	 */
	public function blog_switched() {
		$this->_fly_dir = '';
		$this->_fly_dir = apply_filters( 'fly_dir_path', $this->get_fly_dir() );
	}
}
