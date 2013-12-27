<?php
// AK 公钥
if ( ! defined( 'BCS_AK' ) )
	define( 'BCS_AK', DB_USER );
	// SK 私钥
if ( ! defined( 'BCS_SK' ) )
	define( 'BCS_SK', DB_PASSWORD );
	// superfile 每个object分片后缀
define( 'BCS_SUPERFILE_POSTFIX', '_bcs_superfile_' );
// sdk superfile分片大小 ，单位 B（字节）
define( 'BCS_SUPERFILE_SLICE_SIZE', 1024 * 1024 );
