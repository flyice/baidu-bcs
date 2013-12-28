<?php
require_once BAIDU_BCS_SDK_DIR . '/bcs.class.php';

/**
 * 百度云存储插件类。
 * 
 * 使用百度云存储API上传媒体文件。
 * 
 * @author Coda
 *
 */
class Baidu_BCS_Plugin {

	/**
	 * @var 插件slug
	 */
	const PLUGIN_SLUG = 'baidu-bcs';

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
	 * 构造函数
	 */
	function __construct() {
		$bucket_name = $this->get_bucket_name();
		
		if ( $bucket_name ) {
			add_filter( 'option_upload_url_path', array( $this, 'option_upload_url_path' ) );
		}
		
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			if ( $bucket_name ) {
				add_filter( 'wp_handle_upload', array( $this, 'wp_handle_upload' ) );
				add_filter( 'wp_generate_attachment_metadata', array( $this, 'wp_generate_attachment_metadata' ) );
				add_filter( 'wp_delete_file', array( $this, 'wp_delete_file' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}
	}

	/**
	 * wp_delete_file过滤器
	 * 
	 * @param string $file
	 * @return string
	 */
	function wp_delete_file( $file ) {
		$object = _wp_relative_upload_path( $file );
		$object = '/' . ltrim( $object, '/' );
		$this->delete_file_from_bcs( $object );
		return $file;
	}

	/**
	 * 删除bcs文件
	 * 
	 * @param string $object
	 */
	function delete_file_from_bcs( $object ) {
		$this->get_BaiduBCS()->delete_object( $this->get_bucket_name(), $object );
	}

	function admin_notices() {
		?>
<div class="updated">
	<p>请先设置bucket名</p>
</div>
<?php
	}

	/**
	 * 添加设置菜单
	 */
	function add_admin_menu() {
		add_options_page( 
			'百度云存储设置', 
			'百度云存储', 
			'manage_options', 
			self::PLUGIN_SLUG, 
			array( $this, 'display_settings_page' ) );
	}

	/**
	 * 获取bucket路径
	 * @param string $url
	 * @return string
	 */
	function option_upload_url_path( $url ) {
		return 'http://' . BaiduBCS::DEFAULT_URL . '/' . $this->get_bucket_name();
	}

	/**
	 * 注册设置
	 */
	function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_BUCKET_NAME );
	}

	/**
	 * wp_handle_upload过滤器
	 * 
	 * 将上传至本地的文件传至百度云存储
	 * 
	 * @param array $file
	 * @return array
	 */
	function wp_handle_upload( $file ) {
		$file_path = $file['file'];
		$object = _wp_relative_upload_path( $file_path );
		$object = '/' . ltrim( $object, '/' );
		
		if ( $this->upload_file_to_bcs( $object, $file_path ) ) {
			$file['url'] = $this->get_object_url( $object );
		} else {
			$file['error'] = '上传文件失败,请检查';
		}
		
		return $file;
	}

	/**
	 * wp_generate_attachment_metadata过滤器
	 * 
	 * 将生成的中间级图片上传至百度云存储
	 * 删除本地文件
	 * 
	 * @param array $data
	 * @param int $post_id
	 */
	function wp_generate_attachment_metadata( $data ) {
		$uploads = wp_upload_dir();
		$file = trailingslashit( $uploads['basedir'] ) . $data['file'];
		$info = pathinfo( $file );
		$dir = trailingslashit( $info['dirname'] );
		
		foreach ( $data['sizes'] as $key => $value ) {
			$sized_file_name = $value['file'];
			$sized_file = $dir . $sized_file_name;
			$object = _wp_relative_upload_path( $sized_file );
			$object = '/' . ltrim( $object, '/' );
			if ( $this->upload_file_to_bcs( $object, $sized_file ) ) {
				unlink( $sized_file );
			} else {
				unset( $data['sizes'][$key] );
			}
		}
		unlink( $file );
		
		return $data;
	}

	/**
	 * 上传文件至百度云存储
	 * @param string $object 文件名
	 * @param string $file 本地文件路径
	 * @param boolean $public 是否为公开文件
	 * @return boolean 成功返回 true, 否则为false
	 */
	function upload_file_to_bcs( $object, $file, $public = true ) {
		$res = $this->get_BaiduBCS()->create_object( 
			$this->get_bucket_name(), 
			$object, 
			$file, 
			array( 'acl' => BaiduBCS::BCS_SDK_ACL_TYPE_PUBLIC_READ ) );
		
		return $res->isOK();
	}

	/**
	 * 获取文件的URL
	 * 
	 * @param string $object
	 * @return string
	 */
	function get_object_url( $object ) {
		return 'http://' . BaiduBCS::DEFAULT_URL . '/' . $this->get_bucket_name() . $object;
	}

	/**
	 * 获取bucket名
	 * 
	 * @return string
	 */
	function get_bucket_name() {
		if ( ! $this->bucket_name ) {
			$this->bucket_name = get_option( self::OPTION_BUCKET_NAME );
		}
		
		return $this->bucket_name;
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
				$selected = ( $value == $this->get_bucket_name() ) ? 'selected="selected"' : '';
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
