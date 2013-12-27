<?php

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
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_filter( 'wp_handle_upload', array( $this, 'wp_handle_upload' ) );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'wp_generate_attachment_metadata' ) );
		}
	}

	/**
	 * admin_menu动作
	 */
	function admin_menu() {
		add_options_page( 
			'百度云存储设置', 
			'百度云存储', 
			'manage_options', 
			self::PLUGIN_SLUG, 
			array( $this, 'display_settings_page' ) );
	}

	/**
	 * admin_init动作
	 */
	function admin_init() {
		$this->register_settings();
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
	 * @param array $data
	 * @param int $post_id
	 */
	function wp_generate_attachment_metadata( $data, $post_id ) {
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

	function get_object_url( $object ) {
		return 'http://' . BaiduBCS::DEFAULT_URL . '/' . $this->get_bucket_name() . $object;
	}

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
			require_once BAIDU_BCS_SDK_DIR . '/bcs.class.php';
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
				?><option>请选择</option>
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
