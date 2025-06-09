<?php
/**
 * WordPress eXtended RSS file parser implementations
 *
 * @package Importer
 */

function vamtam_get_export_data( $file ) {
	$replaced_contents = file_get_contents( $file );
	return strtr( $replaced_contents, [
		'{WPV:SAMPLES_URI}' => VAMTAM_SAMPLES_URI,
		'{WPV:UPLOAD_DIR}' => wp_upload_dir()['basedir'],
	] );
}

/**
 * WordPress Importer class for managing parsing of WXR files.
 */
class VAMTAM_WXR_Parser {
	function parse( $file ) {
		// use regular expressions if nothing else available or this is bad XML
		$parser = new VAMTAM_WXR_Parser_Regex;
		return $parser->parse( $file );
	}
}

/**
 * WXR Parser that uses regular expressions. Fallback for installs without an XML parser.
 */
class VAMTAM_WXR_Parser_Regex {
	var $authors = array();
	var $posts = array();
	var $categories = array();
	var $tags = array();
	var $terms = array();
	var $base_url = '';

	function __construct() {
		$this->has_gzip = is_callable( 'gzopen' );
	}

	function parse( $file ) {
		$wxr_version = $frontpage = $woocommerce_pages = false;

		$in_multiline      = false;
		$multiline_content = '';

		$multiline_tags = array(
			'wp:term'     => array( 'terms', array( $this, 'process_term' ) ),
			'wp:category' => array( 'categories', array( $this, 'process_category' ) ),
			'wp:tag'      => array( 'tags', array( $this, 'process_tag' ) ),
			'item'        => array( 'posts', array( $this, 'process_post' ) ),
		);

		$fp = $this->fopen( $file, 'r' );
		if ( $fp ) {
			while ( ! $this->feof( $fp ) ) {
				$importline = $this->fgets( $fp );

				$importline = strtr( $importline, [
					'{WPV:SAMPLES_URI}' => VAMTAM_SAMPLES_URI,
					'{WPV:UPLOAD_DIR}' => wp_upload_dir()['basedir'],
				] );

				$importline = str_replace( '~~~VAMTAM~~~NULL~~~BYTE~~~', "\0" , $importline );

				if ( ! $frontpage ) {
					preg_match( '|<wpv:frontpage.*?>(\d*?)</wpv:frontpage>|is', $importline, $frontpage );

					if ( isset( $frontpage[1] ) ) {
						$frontpage = $frontpage[1];
					}
				}

				if ( ! $woocommerce_pages ) {
					preg_match( '|<wpv:woocommerce_pages.*?><!\[CDATA\[(.*?)\]\]></wpv:woocommerce_pages>|is', $importline, $woocommerce_pages );

					if ( isset( $woocommerce_pages[1] ) ) {
						$woocommerce_pages = $woocommerce_pages[1];
					}
				}

				if ( ! $wxr_version && preg_match( '|<wp:wxr_version>(\d+\.\d+)</wp:wxr_version>|', $importline, $version ) )
					$wxr_version = $version[1];

				if ( false !== strpos( $importline, '<wp:base_site_url>' ) ) {
					preg_match( '|<wp:base_site_url>(.*?)</wp:base_site_url>|is', $importline, $url );
					$this->base_url = $url[1];
					continue;
				}
				if ( false !== strpos( $importline, '<wp:author>' ) ) {
					preg_match( '|<wp:author>(.*?)</wp:author>|is', $importline, $author );
					$a = $this->process_author( $author[1] );
					$this->authors[$a['author_login']] = $a;
					continue;
				}

				foreach ( $multiline_tags as $tag => $handler ) {
					if ( false !== strpos( $importline, "<$tag>" ) ) {
						$multiline_content = '';
						$in_multiline      = true;

						break;
					}

					if ( false !== strpos( $importline, "</$tag>" ) ) {
						$in_multiline = false;

						$this->{$handler[0]}[] = call_user_func( $handler[1], $multiline_content );

						break;
					}
				}

				if ( $in_multiline ) {
					$multiline_content .= $importline;
				}
			}

			$this->fclose( $fp );

			foreach ( $this->posts as $post_index => $post ) {
				if ( ! isset( $post['postmeta'] ) || ! is_array( $post['postmeta'] ) ) {
					continue;
				}

				foreach ( $post['postmeta'] as $postmeta_index => $postmeta ) {
					if ( stristr( $postmeta['key'], '_elementor_page_settings' ) ) {
						$this->posts[ $post_index ]['postmeta'][ $postmeta_index ]['value'] = Vamtam_Importers_E::fix_serialized( $postmeta['value'] );
					}
				}
			}
		}

		if ( ! $wxr_version )
			return new WP_Error( 'WXR_parse_error', __( 'This does not appear to be a WXR file, missing/invalid WXR version number', 'wordpress-importer' ) );

		return array(
			'authors' => $this->authors,
			'posts' => $this->posts,
			'categories' => $this->categories,
			'tags' => $this->tags,
			'terms' => $this->terms,
			'base_url' => $this->base_url,
			'version' => $wxr_version,
			'frontpage' => $frontpage,
			'woocommerce_pages' => $woocommerce_pages,
		);
	}

	function get_tag( $string, $tag ) {
		preg_match( "|<$tag.*?>\s*(.*?)\s*</$tag>|is", $string, $return );

		$value = '';
		if ( isset( $return[1] ) ) {
			$value = $return[1];
		} elseif (
			preg_match( "|<$tag.*?>\s*|is", $string, $opening, PREG_OFFSET_CAPTURE ) &&
			preg_match( "|</$tag>|is", $string, $closing, PREG_OFFSET_CAPTURE )
		) {
			$from = $opening[0][1] + strlen( $opening[0][0] );

			$value = substr( $string, $from, $closing[0][1] - $from );
		} else {
			return '';
		}

		$value = trim( $value );
		if ( substr( $value, 0, 9 ) == '<![CDATA[' ) {
			if ( strpos( $value, ']]]]><![CDATA[>' ) !== false ) {
				preg_match_all( '|<!\[CDATA\[(.*?)\]\]>|s', $value, $matches );
				$value = '';
				foreach( $matches[1] as $match )
					$value .= $match;
			} else {
				$value = preg_replace( '|^<!\[CDATA\[(.*)\]\]>$|s', '$1', $value );
			}
		}

		return $value;
	}

	function process_category( $c ) {
		$term = array(
			'term_id' => $this->get_tag( $c, 'wp:term_id' ),
			'cat_name' => $this->get_tag( $c, 'wp:cat_name' ),
			'category_nicename'	=> $this->get_tag( $c, 'wp:category_nicename' ),
			'category_parent' => $this->get_tag( $c, 'wp:category_parent' ),
			'category_description' => $this->get_tag( $c, 'wp:category_description' ),
		);

		$term_meta = $this->process_meta( $c, 'wp:termmeta' );
		if ( ! empty( $term_meta ) ) {
			$term['termmeta'] = $term_meta;
		}

		return $term;	}

	function process_tag( $t ) {
		$term = array(
			'term_id' => $this->get_tag( $t, 'wp:term_id' ),
			'tag_name' => $this->get_tag( $t, 'wp:tag_name' ),
			'tag_slug' => $this->get_tag( $t, 'wp:tag_slug' ),
			'tag_description' => $this->get_tag( $t, 'wp:tag_description' ),
		);

		$term_meta = $this->process_meta( $t, 'wp:termmeta' );
		if ( ! empty( $term_meta ) ) {
			$term['termmeta'] = $term_meta;
		}

		return $term;
	}

	function process_term( $t ) {
		$term = array(
			'term_id' => $this->get_tag( $t, 'wp:term_id' ),
			'term_taxonomy' => $this->get_tag( $t, 'wp:term_taxonomy' ),
			'slug' => $this->get_tag( $t, 'wp:term_slug' ),
			'term_parent' => $this->get_tag( $t, 'wp:term_parent' ),
			'term_name' => $this->get_tag( $t, 'wp:term_name' ),
			'term_description' => $this->get_tag( $t, 'wp:term_description' ),
		);

		$term_meta = $this->process_meta( $t, 'wp:termmeta' );
		if ( ! empty( $term_meta ) ) {
			$term['termmeta'] = $term_meta;
		}

		return $term;
	}

	function process_meta( $string, $tag ) {
		$parsed_meta = array();

		preg_match_all( "|<$tag>(.+?)</$tag>|is", $string, $meta );

		if ( ! isset( $meta[1] ) ) {
			return $parsed_meta;
		}

		foreach ( $meta[1] as $m ) {
			$parsed_meta[] = array(
				'key'   => $this->get_tag( $m, 'wp:meta_key' ),
				'value' => $this->get_tag( $m, 'wp:meta_value' ),
			);
		}

		return $parsed_meta;
	}

	function process_author( $a ) {
		return array(
			'author_id' => $this->get_tag( $a, 'wp:author_id' ),
			'author_login' => $this->get_tag( $a, 'wp:author_login' ),
			'author_email' => $this->get_tag( $a, 'wp:author_email' ),
			'author_display_name' => $this->get_tag( $a, 'wp:author_display_name' ),
			'author_first_name' => $this->get_tag( $a, 'wp:author_first_name' ),
			'author_last_name' => $this->get_tag( $a, 'wp:author_last_name' ),
		);
	}

	function process_post( $post ) {
		$post_id        = $this->get_tag( $post, 'wp:post_id' );
		$post_title     = $this->get_tag( $post, 'title' );
		$post_date      = $this->get_tag( $post, 'wp:post_date' );
		$post_date_gmt  = $this->get_tag( $post, 'wp:post_date_gmt' );
		$comment_status = $this->get_tag( $post, 'wp:comment_status' );
		$ping_status    = $this->get_tag( $post, 'wp:ping_status' );
		$status         = $this->get_tag( $post, 'wp:status' );
		$post_name      = $this->get_tag( $post, 'wp:post_name' );
		$post_parent    = $this->get_tag( $post, 'wp:post_parent' );
		$menu_order     = $this->get_tag( $post, 'wp:menu_order' );
		$post_type      = $this->get_tag( $post, 'wp:post_type' );
		$post_password  = $this->get_tag( $post, 'wp:post_password' );
		$is_sticky      = $this->get_tag( $post, 'wp:is_sticky' );
		$guid           = $this->get_tag( $post, 'guid' );
		$post_author    = $this->get_tag( $post, 'dc:creator' );

		$post_excerpt = $this->get_tag( $post, 'excerpt:encoded' );
		$post_excerpt = preg_replace_callback( '|<(/?[A-Z]+)|', array( $this, '_normalize_tag' ), $post_excerpt );
		$post_excerpt = str_replace( '<br>', '<br />', $post_excerpt );
		$post_excerpt = str_replace( '<hr>', '<hr />', $post_excerpt );

		$post_content = $this->get_tag( $post, 'content:encoded' );
		$post_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( $this, '_normalize_tag' ), $post_content );
		$post_content = str_replace( '<br>', '<br />', $post_content );
		$post_content = str_replace( '<hr>', '<hr />', $post_content );

		$postdata = compact( 'post_id', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_excerpt',
			'post_title', 'status', 'post_name', 'comment_status', 'ping_status', 'guid', 'post_parent',
			'menu_order', 'post_type', 'post_password', 'is_sticky'
		);

		$attachment_url = $this->get_tag( $post, 'wp:attachment_url' );
		if ( $attachment_url )
			$postdata['attachment_url'] = $attachment_url;

		preg_match_all( '|<category domain="([^"]+?)" nicename="([^"]+?)">(.+?)</category>|is', $post, $terms, PREG_SET_ORDER );
		foreach ( $terms as $t ) {
			$post_terms[] = array(
				'slug' => $t[2],
				'domain' => $t[1],
				'name' => str_replace( array( '<![CDATA[', ']]>' ), '', $t[3] ),
			);
		}
		if ( ! empty( $post_terms ) ) $postdata['terms'] = $post_terms;

		preg_match_all( '|<wp:comment>(.+?)</wp:comment>|is', $post, $comments );
		$comments = $comments[1];
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$post_comments[] = array(
					'comment_id' => $this->get_tag( $comment, 'wp:comment_id' ),
					'comment_author' => $this->get_tag( $comment, 'wp:comment_author' ),
					'comment_author_email' => $this->get_tag( $comment, 'wp:comment_author_email' ),
					'comment_author_IP' => $this->get_tag( $comment, 'wp:comment_author_IP' ),
					'comment_author_url' => $this->get_tag( $comment, 'wp:comment_author_url' ),
					'comment_date' => $this->get_tag( $comment, 'wp:comment_date' ),
					'comment_date_gmt' => $this->get_tag( $comment, 'wp:comment_date_gmt' ),
					'comment_content' => $this->get_tag( $comment, 'wp:comment_content' ),
					'comment_approved' => $this->get_tag( $comment, 'wp:comment_approved' ),
					'comment_type' => $this->get_tag( $comment, 'wp:comment_type' ),
					'comment_parent' => $this->get_tag( $comment, 'wp:comment_parent' ),
					'comment_user_id' => $this->get_tag( $comment, 'wp:comment_user_id' ),
					'commentmeta' => $this->process_meta( $comment, 'wp:commentmeta' ),
				);
			}
		}

		if ( ! empty( $post_comments ) ) {
			$postdata['comments'] = $post_comments;
		}

		$post_meta = $this->process_meta( $post, 'wp:postmeta' );
		if ( ! empty( $post_meta ) ) {
			$postdata['postmeta'] = $post_meta;
		}

		return $postdata;
	}

	function _normalize_tag( $matches ) {
		return '<' . strtolower( $matches[1] );
	}

	function fopen( $filename, $mode = 'r' ) {
		if ( $this->has_gzip )
			return gzopen( $filename, $mode );
		return fopen( $filename, $mode );
	}

	function feof( $fp ) {
		if ( $this->has_gzip )
			return gzeof( $fp );
		return feof( $fp );
	}

	function fgets( $fp ) {
		if ( $this->has_gzip )
			return gzgets( $fp );
		return fgets( $fp );
	}

	function fclose( $fp ) {
		if ( $this->has_gzip )
			return gzclose( $fp );
		return fclose( $fp );
	}
}
