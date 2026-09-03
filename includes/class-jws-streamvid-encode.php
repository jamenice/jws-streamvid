<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Fired during plugin activation
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class use fo function encode.
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Encode{
	protected $file_path 						=	'';
	protected $file_info 						=	array();
	protected $video_codec  					=	'h264';//libx264
	protected $h264_profile 					=	'baseline';
	protected $h264_crf							=	25;
	protected $segment_target_duration 			=	15;
	protected $max_bitrate_ratio				=	1.07;
	protected $rate_monitor_buffer_ratio		=	1.5;
	protected $renditions =	array(
		'426x240'	=>	array( '400k', '64k' ),
		'640x360'	=>	array( '800k', '96k' ),
		'854x480'	=>	array( '1400k', '128k' ),
		'1280x720'	=>	array( '2800k', '128k' ),
        '1921x818'	=>	array( '4800k', '128k' ), 
        '4096x1744'	=>	array( '10800k', '128k' ), 
	);

	protected $output_encode 					=	'';
	protected $check_code 						=	array();
	private $log_file 							=	'code_log.log';
	private $file_encoded_mu 						=	'playervideo_idc.m3u8';
	protected $ffmpeg_path							=	'/usr/bin/';
    
	public function __construct( $file_path = '', $ffmpeg_path = '/usr/bin/' ){
        $this->file_info 		= pathinfo( $file_path );
		$this->file_path 		= $file_path;
		$this->ffmpeg_path 		= $ffmpeg_path;
	}


	public function get_file_folder(){
 
		return trailingslashit( dirname( $this->file_path ) ) . sanitize_file_name( $this->file_info['filename'] );
        
	}

	public function create_file_folder(){

		$folder = $this->get_file_folder();

		if( ! file_exists( $folder ) ){
		   
	       	mkdir( $folder,0777,true);
		}

		return file_exists( $folder ) ? $folder : false;
	}

	public function delete_file_folder(){

            $src = $this->get_file_folder();
            
            if(file_exists( $src ) ){ 
                
                $dir = opendir($src);
                while(false !== ( $file = readdir($dir)) ) {
                    if (( $file != '.' ) && ( $file != '..' )) {
                        $full = $src . '/' . $file;
                        if ( is_dir($full) ) {
                            rmdir($full);
                        }
                        else {
                            unlink($full);
                        }
                    }
                }
                closedir($dir);
                rmdir($src); 
                
            }

	}
    
    
    public function get_result_code(){
		return $this->check_code;
	}

	public function get_log_file(){
		return trailingslashit( $this->get_file_folder() ) . $this->log_file;
	}

	public function get_log_file_content(){

		$log_file = $this->get_log_file();

		return file_exists( $log_file ) ? trim( file_get_contents( $log_file ) ) : false;
	}

	public function get_encode_playervideo_idc_file(){
		return trailingslashit( $this->get_file_folder() ) . $this->file_encoded_mu;
	}
    
    
	
	public function get_encoded_percentage(){

		$log_file = $this->get_log_file_content();

		if( ! $log_file ){
			return 0;
		}

		if( ! function_exists( 'wp_read_video_metadata' ) ){
			include( ABSPATH . 'wp-admin/includes/media.php' );
		}

		$matches = array();

		$file_metadata = wp_read_video_metadata( $this->file_path );

		preg_match_all("/time=(.*?) bitrate=/", $log_file, $matches );

		if( $matches[1] ){

			$lengthed = 0;

			$last = count( $matches[1] ) - 1;

			$encoded_length = explode( ":", trim( $matches[1][ $last ] ) );

			$lengthed += $encoded_length[0]*60*60;
			$lengthed += $encoded_length[1]*60;
			$lengthed += ceil($encoded_length[2]);

			return min( 100, round( $lengthed*100/ absint( $file_metadata['length'] ) ) );
		}

		return 0;
	}



	private function reexec( $cmd, $bin = true ){

		if( ! function_exists( 'exec' ) ){
			return new WP_Error(
				'exec_not_found',
				esc_html__( 'Exec function was not found.', 'jws_streamvid' )
			);
		}

		if( ! empty( $this->ffmpeg_path ) ){
			$this->ffmpeg_path = trailingslashit( $this->ffmpeg_path );
		}

		if( $bin ){
			$cmd = $this->ffmpeg_path . $cmd;
		}

		return exec( $cmd, $this->output_encode, $this->check_code );
	}


	public function generate_video_hls(){

		$folder = $this->create_file_folder();

		if( ! $folder ){
			return new WP_Error(
				'cannot_create_folder',
				esc_html__( 'Cannot create folder.', 'jws_streamvid' )
			);
		}

		$master_playervideo_idc = "#EXTM3U\n#EXT-X-VERSION:3\n";
		$misc_params = "";
		
		$params = " -c:a aac";
		$params .= " -vcodec {$this->video_codec}";
		$params .= " -hls_time {$this->segment_target_duration}";
        $params .= " -sc_threshold 0";
        $params .= " -f hls";
        $params .= " -g 48 -keyint_min 48";
        $params .= " -ar 48000";
        if( $this->video_codec == 'h264' ){
			$params .= " -profile:v {$this->h264_profile}";
			$params .= " -crf {$this->h264_crf}";
		}
 		$cmd = "";
		foreach ( $this->renditions as $rendition => $value ) {
			$resolution 	= 	explode( 'x', $rendition );
			$bitrate 		=	$value[0];
			$audiorate 		=	$value[1];
			$width 			=	$resolution[0];
			$height 		=	$resolution[1];
			$maxrate 		=	absint( (int)$bitrate*(int)$this->max_bitrate_ratio );
			$bandwidth  	=	(int)$bitrate * 1000;

			$cmd .=" {$params} -vf 'scale=w={$width}:h={$height}:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2'";
			$cmd .=" -b:v {$bitrate} -maxrate {$maxrate}k -bufsize {$maxrate}k -b:a {$audiorate}";
			$cmd .=" -hls_segment_filename {$folder}/{$rendition}_%03d.ts -f hls -strict experimental {$folder}/{$rendition}.m3u8";

			$master_playervideo_idc .= "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$rendition}\n{$rendition}.m3u8\n";
		}

		$cmd .= " >/dev/null 2>&1 2> {$folder}/{$this->log_file} & echo $!";

		$cmd = "ffmpeg -i {$this->file_path} {$cmd}";

      
		$exec = $this->reexec( $cmd );
    
		if( is_wp_error( $exec ) ){
			return $exec;
		}

		if( $this->check_code != 0 ){
			return new WP_Error(
				$this->check_code,
				$this->output_encode
			);
		}

		if( ! function_exists( 'fopen' ) || ! function_exists( 'fwrite' ) || ! function_exists( 'fclose' ) ){
			return new WP_Error( 'cannot_read_file', esc_html__( 'Cannot read/write file playervideo_idc.m3u8', 'jws_streamvid' ) );
		}

		$playervideo_idc = fopen( $this->get_encode_playervideo_idc_file() , 'w' );

		if( $playervideo_idc ){
			fwrite( $playervideo_idc, $master_playervideo_idc );
		}

		fclose( $playervideo_idc );
		return $this->get_encode_playervideo_idc_file();
	}
    
  
    
}
