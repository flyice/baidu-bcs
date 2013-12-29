<?php
require_once BAIDU_BCS_SDK_DIR . '/bcs.class.php';

/**
 * 百度云存储插件。
 * 
 * 
 * @author flyice
 *
 */
class Baidu_BCS_Plugin {

	/**
	 * @var 选项组名
	 */
	const OPTION_GROUP = 'baidu-bcs-option-group';

	/**
	 * @var Bucket名选项
	 */
	const OPTION_BUCKET_NAME = 'baidu-bcs-backet-name';

	/**
	 * @var bucket名
	 */
	var $bucket_name = null;

	/**
	 * @var 百度云存储API实例
	 */
	var $baiduBCS = null;

	/**
	 * @var 插件名
	 */
	var $plugin_name = null;

	/**
	 * 构造函数
	 * 
	 * @param string $plugin_file 插件文件
	 */
	function __construct( $plugin_file ) {
		$this->plugin_name = plugin_basename( $plugin_file );
		$this->bucket_name = get_option( self::OPTION_BUCKET_NAME );
		
		if ( $this->bucket_name ) {
			add_filter( 'option_upload_url_path', array( $this, 'option_upload_url_path' ) );
		}
		
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_filter( 'plugin_action_links_' . $this->plugin_name, array( $this, 'plugin_action_links' ) );
			
			if ( $this->bucket_name ) {
				add_filter( 'wp_handle_upload', array( $this, 'wp_handle_upload' ) );
				add_filter( 'wp_delete_file', array( $this, 'wp_delete_file' ) );
				add_filter( 'wp_save_image_editor_file', array( $this, 'wp_save_image_editor_file' ), 10, 4 );
				add_filter( 'wp_update_attachment_metadata', array( $this, 'wp_update_attachment_metadata' ) );
				add_filter( 'wp_create_file_in_uploads', array( $this, 'wp_create_file_in_uploads' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}
	}

	/**
	 * wp_create_file_in_uploads filter
	 * 
	 * @param string $file
	 * @return string
	 */
	function wp_create_file_in_uploads( $file ) {
		$this->upload_file_to_bcs( $file );
		
		return $file;
	}

	/**
	 * admin_init action
	 */
	function admin_init() {
		$this->register_settings();
	}

	/**
	 * wp_save_image_editor_file filter
	 * 
	 * @param null $value
	 * @param string $filename
	 * @param WP_Image_Editor $image
	 * @param string $mime_type
	 * @param int $post_id
	 * @return boolean
	 */
	function wp_save_image_editor_file( $value, $filename, $image, $mime_type ) {
		$result = $image->save( $filename, $mime_type );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		$object = $this->upload_file_to_bcs( $filename );
		
		return ! ! $object;
	}

	/**
	 * wp_update_attachment_metadata filter
	 * 
	 * 上传中间级图片后删除本地图片
	 * 
	 * @param array $data
	 * @param int $post_id
	 */
	function wp_update_attachment_metadata( $data ) {
		$uploads = wp_upload_dir();
		$file = trailingslashit( $uploads['basedir'] ) . $data['file'];
		$info = pathinfo( $file );
		$dir = trailingslashit( $info['dirname'] );
		
		foreach ( $data['sizes'] as $key => $value ) {
			$sized_file_name = $value['file'];
			$sized_file = $dir . $sized_file_name;
			
			if ( file_exists( $sized_file ) ) {
				if ( $this->upload_file_to_bcs( $sized_file ) ) {
				} else {
					unset( $data['sizes'][$key] );
				}
				@unlink( $sized_file );
			}
		}
		
		@unlink( $file );
		
		return $data;
	}

	/**
	 * 添加插件设置链接
	 * 
	 * @param array $links
	 * @return array
	 */
	function plugin_action_links( $links ) {
		$links[] = '<a href="' . get_admin_url( null, 'options-general.php?page=' . $this->plugin_name . '">设置</a>' );
		
		return $links;
	}

	/**
	 * wp_delete_file filter
	 * 
	 * @param string $file
	 * @return string
	 */
	function wp_delete_file( $file ) {
		$object = _wp_relative_upload_path( $file );
		$object = '/' . ltrim( $object, '/' );
		$this->get_BaiduBCS()->delete_object( $this->bucket_name, $object );
		
		return $file;
	}

	/**
	 * admin_notices action
	 */
	function admin_notices() {
		global $pagenow;
		if ( $pagenow == 'plugins.php' ) {
			?>
<div class="updated">
	<p>
		请前往<a
			href="options-general.php?page=<?php echo $this->plugin_name; ?>">百度云存储设置</a>页面中设置bucket名
	</p>
</div>
<?php
		}
	}

	/**
	 * admin_menu action
	 */
	function admin_menu() {
		add_options_page( 
			'百度云存储设置', 
			'百度云存储', 
			'manage_options', 
			$this->plugin_name, 
			array( $this, 'display_settings_page' ) );
	}

	/**
	 * option_upload_url_path filter
	 * 
	 * @param string $url
	 * @return string
	 */
	function option_upload_url_path( $url ) {
		return 'http://' . BaiduBCS::DEFAULT_URL . '/' . $this->bucket_name;
	}

	/**
	 * 注册设置
	 */
	function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_BUCKET_NAME, array( $this, 'vaildate_bucket_name' ) );
	}

	/**
	 * 验证bucket名
	 * 
	 * @param string $value
	 * @return string
	 */
	function vaildate_bucket_name( $value ) {
		$res = $this->get_BaiduBCS()->list_object( $value );
		
		return $res->isOK() ? $value : '';
	}

	/**
	 * wp_handle_upload filter
	 * 
	 * 将上传至本地的文件传至百度云存储
	 * 
	 * @param array $data
	 * @return array
	 */
	function wp_handle_upload( $data ) {
		$file = $data['file'];
		
		$object = $this->upload_file_to_bcs( $file );
		
		if ( $object ) {
			$data['url'] = $this->get_object_url( $object );
		} else {
			$file['error'] = '上传文件失败,请检查';
			@unlink( $file );
		}
		
		return $data;
	}

	/**
	 * 上传文件至百度云存储
	 * 
	 * @param string $file 本地文件路径
	 * @param boolean $public 是否为公开文件
	 * @return null | string 成功返回对象名，否则返回null
	 */
	function upload_file_to_bcs( $file ) {
		$object = _wp_relative_upload_path( $file );
		$object = '/' . ltrim( $object, '/' );
		
		$res = $this->get_BaiduBCS()->create_object( 
			$this->bucket_name, 
			$object, 
			$file, 
			array( 'acl' => BaiduBCS::BCS_SDK_ACL_TYPE_PUBLIC_READ ) );
		
		return $res->isOK() ? $object : null;
	}

	/**
	 * 获取文件的URL
	 * 
	 * @param string $object
	 * @return string
	 */
	function get_object_url( $object ) {
		return 'http://' . BaiduBCS::DEFAULT_URL . '/' . $this->bucket_name . $object;
	}

	/**
	 * 获取百度云存储API实例
	 * 
	 * @return BaiduBCS
	 */
	function get_BaiduBCS() {
		if ( ! $this->baiduBCS ) {
			$this->baiduBCS = new BaiduBCS();
		}
		
		return $this->baiduBCS;
	}

	/**
	 * 获取bucket名列表
	 * 
	 * @return array|boolean
	 */
	function get_bucket_names() {
		$responeCore = $this->get_BaiduBCS()->list_bucket();
		if ( $responeCore->isOK() ) {
			$list = json_decode( $responeCore->body );
			$names = array();
			foreach ( $list as $value ) {
				$names[] = $value->bucket_name;
			}
			return $names;
		} else {
			return false;
		}
	}

	/**
	 * 设置页面输出回调
	 */
	function display_settings_page() {
		?>
<div class="wrap">
	<h2>百度云存储设置</h2>
	<?php
		$names = $this->get_bucket_names();
		if ( $names === false ) {
			echo '<p>获取bucket名列表失败,请检查</p>';
		} else {
			?>
	<form method="post" action="options.php">
        <?php settings_fields(self::OPTION_GROUP)?>
                <table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label
						for="<?php echo self::OPTION_BUCKET_NAME; ?>">bucket名</label></th>
					<td><select name="<?php echo self::OPTION_BUCKET_NAME; ?>"
						id="<?php echo self::OPTION_BUCKET_NAME; ?>">
        <?php
			foreach ( $names as $value ) {
				$selected = ( $value == $this->bucket_name ) ? 'selected="selected"' : '';
				?><option value="">请选择</option>
							<option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $value; ?></option><?php
			}
			?>
                                </select></td>
				</tr>
			</tbody>
		</table>
        <?php submit_button()?>
            </form>
</div>
<?php
		}
	}
}
