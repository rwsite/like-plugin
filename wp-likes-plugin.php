<?php
/**
 * Plugin Name: Post likes - WordPress plugin.
 * Plugin URL: https://rwsite.ru
 * Description: Wordpress simple plugin for post likes. Require font awesome. How to use. Add shortcode [like] in your theme, before post output.
 * Version: 1.0.0
 * Text Domain: like
 * Domain Path: /languages
 * Author: Aleksey Tikhomirov
 *
 * Requires at least: 4.6
 * Tested up to: 5.3.1
 * Requires PHP: 7.0+
 * How to use:
 */

define( 'LIKE_URL', plugin_dir_url( __FILE__));

final class PostLike
{
    public static $inst = 0;
    public $settings;
    private $text;

    public function __construct($settings = null)
    {
        self::$inst++;
        $this->settings = $settings; // stars
        $this->add_actions();
    }

    public function add_actions(){

        if(self::$inst !== 1) {
            return;
        }

        load_plugin_textdomain( 'like', false, dirname(plugin_basename(__FILE__)) . '/languages' );

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'] );
        add_shortcode('like', [$this, 'get_post_likes']);

        add_action('wp_ajax_nopriv_process_simple_like', [$this, 'process_simple_like'] );
        add_action('wp_ajax_process_simple_like', [$this, 'process_simple_like'] );

        add_action('show_user_profile', [$this, 'show_user_likes']);
        add_action('edit_user_profile', [$this, 'show_user_likes']);

        if($this->settings === 'stars'){
            $this->stars_settings();
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style( 'likes', LIKE_URL . '/css/simple-likes-public.css' );
        wp_enqueue_script( 'likes', LIKE_URL . '/js/simple-likes-public.js', ['jquery'], '0.5',false );

        wp_localize_script( 'likes', 'simpleLikes', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'like'    => __( 'Like', 'like' ),
            'unlike'  => __( 'Unlike', 'like' )
        ] );
        
        if($this->settings === 'stars'){
            wp_enqueue_script( 'stars-js', LIKE_URL . '/js/jquery.barrating.min.js');
            wp_enqueue_style( 'stars-css', LIKE_URL . '/css/fontawesome-stars.css');
        }
    }

    /**
     * Processes like/unlike
     */
    public function process_simple_like()
    {
        // Security
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( $_REQUEST['nonce'] ) : 0;
        if ( ! wp_verify_nonce( $nonce, 'simple-likes-nonce' )) {
            exit( __( 'Not permitted', 'like' ) );
        }
        // Test if javascript is disabled
        $disabled = isset( $_REQUEST['disabled'] ) && $_REQUEST['disabled'] == true;
        // Test if this is a comment
        $is_comment = (isset( $_REQUEST['is_comment'] ) && $_REQUEST['is_comment'] == 1) ? 1 : 0;
        // Base variables
        $post_id    = (isset( $_REQUEST['post_id'] ) && is_numeric( $_REQUEST['post_id'] )) ? $_REQUEST['post_id'] : '';

        $result     = [];
        $post_users = null;
        $like_count = 0;

        // Get plugin options
        if ($post_id !== '') {
            $count = ($is_comment == 1) ? get_comment_meta( $post_id, '_comment_like_count',
                true ) : get_post_meta( $post_id, "_post_like_count", true ); // like count
            $count = (isset( $count ) && is_numeric( $count )) ? $count : 0;
            if ( ! $this->already_liked( $post_id, $is_comment )) { // Like the post
                if (is_user_logged_in()) { // user is logged in
                    $user_id    = get_current_user_id();
                    $post_users = $this->post_user_likes( $user_id, $post_id, $is_comment );
                    if ($is_comment == 1) {
                        // Update User & Comment
                        $user_like_count = get_user_option( "_comment_like_count", $user_id );
                        $user_like_count = (isset( $user_like_count ) && is_numeric( $user_like_count )) ? $user_like_count : 0;
                        update_user_option( $user_id, "_comment_like_count", ++$user_like_count );
                        if ($post_users) {
                            update_comment_meta( $post_id, "_user_comment_liked", $post_users );
                        }
                    } else {
                        // Update User & Post
                        $user_like_count = get_user_option( "_user_like_count", $user_id );
                        $user_like_count = (isset( $user_like_count ) && is_numeric( $user_like_count )) ? $user_like_count : 0;
                        update_user_option( $user_id, "_user_like_count", ++$user_like_count );
                        if ($post_users) {
                            update_post_meta( $post_id, "_user_liked", $post_users );
                        }
                    }
                } else { // user is anonymous
                    $user_ip    = $this->sl_get_ip();
                    $post_users = $this->post_ip_likes( $user_ip, $post_id, $is_comment );
                    // Update Post
                    if ($post_users) {
                        if ($is_comment == 1) {
                            update_comment_meta( $post_id, "_user_comment_IP", $post_users );
                        } else {
                            update_post_meta( $post_id, "_user_IP", $post_users );
                        }
                    }
                }
                $like_count         = ++$count;
                $response['status'] = "liked";
                $response['icon']   = $this->get_liked_icon();
            } else { // Unlike the post
                if (is_user_logged_in()) { // user is logged in
                    $user_id    = get_current_user_id();
                    $post_users = $this->post_user_likes( $user_id, $post_id, $is_comment );
                    // Update User
                    if ($is_comment == 1) {
                        $user_like_count = get_user_option( "_comment_like_count", $user_id );
                        $user_like_count = (isset( $user_like_count ) && is_numeric( $user_like_count )) ? $user_like_count : 0;
                        if ($user_like_count > 0) {
                            update_user_option( $user_id, "_comment_like_count", --$user_like_count );
                        }
                    } else {
                        $user_like_count = get_user_option( "_user_like_count", $user_id );
                        $user_like_count = (isset( $user_like_count ) && is_numeric( $user_like_count )) ? $user_like_count : 0;
                        if ($user_like_count > 0) {
                            update_user_option( $user_id, '_user_like_count', --$user_like_count );
                        }
                    }
                    // Update Post
                    if ($post_users) {
                        $uid_key = array_search( $user_id, $post_users );
                        unset( $post_users[$uid_key] );
                        if ($is_comment == 1) {
                            update_comment_meta( $post_id, "_user_comment_liked", $post_users );
                        } else {
                            update_post_meta( $post_id, "_user_liked", $post_users );
                        }
                    }
                } else { // user is anonymous
                    $user_ip    = $this->sl_get_ip();
                    $post_users = $this->post_ip_likes( $user_ip, $post_id, $is_comment );
                    // Update Post
                    if ($post_users) {
                        $uip_key = array_search( $user_ip, $post_users );
                        unset( $post_users[$uip_key] );
                        if ($is_comment == 1) {
                            update_comment_meta( $post_id, "_user_comment_IP", $post_users );
                        } else {
                            update_post_meta( $post_id, "_user_IP", $post_users );
                        }
                    }
                }
                $like_count         = ($count > 0) ? --$count : 0; // Prevent negative number
                $response['status'] = "unliked";
                $response['icon']   = $this->get_unliked_icon();
            }
            if ($is_comment == 1) {
                update_comment_meta( $post_id, "_comment_like_count", $like_count );
                update_comment_meta( $post_id, "_comment_like_modified", date( 'Y-m-d H:i:s' ) );
            } else {
                update_post_meta( $post_id, "_post_like_count", $like_count );
                update_post_meta( $post_id, "_post_like_modified", date( 'Y-m-d H:i:s' ) );
            }
            $response['count']   = $this->get_like_count( $like_count, $post_id );
            $response['testing'] = $is_comment;
            if ($disabled == true) {
                if ($is_comment == 1) {
                    wp_redirect( get_permalink( get_the_ID() ) );
                    exit();
                } else {
                    wp_redirect( get_permalink( $post_id ) );
                    exit();
                }
            } else {
                wp_send_json( $response );
            }
        }
    }

    /**
     * Utility to test if the post is already liked
     *
     * @param $post_id
     * @param $is_comment
     *
     * @return bool
     */
    public function already_liked($post_id, $is_comment)
    {
        $post_users = null;
        $user_id    = null;
        if (is_user_logged_in()) { // user is logged in
            $user_id         = get_current_user_id();
            $post_meta_users = ($is_comment == 1) ? get_comment_meta( $post_id,
                "_user_comment_liked" ) : get_post_meta( $post_id, "_user_liked" );
            if (count( $post_meta_users ) != 0) {
                $post_users = $post_meta_users[0];
            }
        } else { // user is anonymous
            $user_id         = $this->sl_get_ip();
            $post_meta_users = ($is_comment == 1) ? get_comment_meta( $post_id,
                "_user_comment_IP" ) : get_post_meta( $post_id, "_user_IP" );
            if (count( $post_meta_users ) != 0) { // meta exists, set up values
                $post_users = $post_meta_users[0];
            }
        }

        if (is_array( $post_users ) && in_array( $user_id, $post_users )) {
            return true;
        }

        return false;
    }

    /**
     * Output the like button
     *
     * @param int $post_id
     * @param null $is_comment
     *
     * @return string
     */
    public function get_simple_likes_button($post_id, $is_comment = null): string
    {
        $is_comment = (null === $is_comment) ? 0 : 1;
        $nonce      = wp_create_nonce( 'simple-likes-nonce' ); // Security
        if ($is_comment == 1) {
            $post_id_class = esc_attr( ' sl-comment-button-' . $post_id );
            $comment_class = esc_attr( ' sl-comment' );
            $like_count    = get_comment_meta( $post_id, "_comment_like_count", true );
            $like_count    = (isset( $like_count ) && is_numeric( $like_count )) ? $like_count : 0;
        } else {
            $post_id_class = esc_attr( ' sl-button-' . $post_id );
            $comment_class = esc_attr( '' );
            $like_count    = get_post_meta( $post_id, "_post_like_count", true );
            $like_count    = (isset( $like_count ) && is_numeric( $like_count )) ? $like_count : 0;
        }
        $count      = $this->get_like_count( $like_count, $post_id );
        $icon_empty = $this->get_unliked_icon();
        $icon_full  = $this->get_liked_icon();
        // Loader
        $loader = '<span id="sl-loader"></span>';
        // Liked/Unliked Variables
        if ($this->already_liked( $post_id, $is_comment )) {
            $class = esc_attr( ' liked' );
            $title = __( 'Unlike', 'like' );
            $icon  = $icon_full;
        } else {
            $class = '';
            $title = __( 'Like', 'like' );
            $icon  = $icon_empty;
        }

        $output = '<span class="sl-wrapper"><a href="' . admin_url( 'admin-ajax.php?action=process_simple_like' . '&post_id=' . $post_id . '&nonce=' . $nonce . '&is_comment=' . $is_comment . '&disabled=true' ) .
        '" class="sl-button' . $post_id_class . $class . $comment_class . '" data-nonce="' . $nonce . '" data-post-id="' . $post_id . '" data-iscomment="' . $is_comment . '" title="' . $title . '">'
        . $icon . $count . '</a> ' . $loader . '</span>';

        return $output;
    }


    /**
     * Processes shortcode to manually add the button to posts
     * @return string
     */
    public function get_post_likes($post = null)
    {
        return $this->get_simple_likes_button( $post ?? get_the_ID(), 0 );
    }

    /**
     * Utility retrieves post meta user likes (user id array),
     * then adds new user id to retrieved array
     * @since    0.5
     */
    public function post_user_likes($user_id, $post_id, $is_comment)
    {
        $post_users      = '';
        $post_meta_users = ($is_comment == 1) ? get_comment_meta( $post_id,
            "_user_comment_liked" ) : get_post_meta( $post_id, "_user_liked" );
        if (count( $post_meta_users ) != 0) {
            $post_users = $post_meta_users[0];
        }
        if ( ! is_array( $post_users )) {
            $post_users = [];
        }
        if ( ! in_array( $user_id, $post_users )) {
            $post_users['user-' . $user_id] = $user_id;
        }

        return $post_users;
    }

    /**
     * Utility retrieves post meta ip likes (ip array),
     * then adds new ip to retrieved array
     */
    public function post_ip_likes($user_ip, $post_id, $is_comment)
    {
        $post_users      = '';
        $post_meta_users = ($is_comment === 1) ? get_comment_meta( $post_id,
            '_user_comment_IP' ) : get_post_meta( $post_id, '_user_IP' );
        // Retrieve post information
        if (count( $post_meta_users ) !== 0) {
            $post_users = $post_meta_users[0];
        }
        if ( ! is_array( $post_users )) {
            $post_users = [];
        }
        if ( ! in_array($user_ip, $post_users, true)) {
            $post_users['ip-' . $user_ip] = $user_ip;
        }

        return $post_users;
    }

    /**
     * Utility to retrieve IP address
     */
    public function sl_get_ip()
    {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] )) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] )) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = (isset( $_SERVER['REMOTE_ADDR'] )) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        }
        $ip = filter_var( $ip, FILTER_VALIDATE_IP );
        return ($ip === false) ? '0.0.0.0' : $ip;
    }

    /**
     * Utility returns the button icon for "like" action
     */
    public function get_liked_icon()
    {
        return apply_filters('sl_icon_liked', '<i class="fa fa-heart"></i> ');
    }

    /**
     * Utility returns the button icon for "unlike" action
     */
    public function get_unliked_icon()
    {
        return apply_filters('sl_icon_unliked', '<i class="fa fa-heart-o"></i> ');
    }

    /**
     * Utility function to format the button count,
     * appending "K" if one thousand or greater,
     * "M" if one million or greater,
     * and "B" if one billion or greater (unlikely).
     * $precision = how many decimal points to display (1.25K)
     * @since    0.5
     */
    public function sl_format_count($number)
    {
        $precision = 2;
        if ($number >= 1000 && $number < 1000000) {
            $formatted = number_format( $number / 1000, $precision ) . 'K';
        } elseif ($number >= 1000000 && $number < 1000000000) {
            $formatted = number_format( $number / 1000000, $precision ) . 'M';
        } elseif ($number >= 1000000000) {
            $formatted = number_format( $number / 1000000000, $precision ) . 'B';
        } else {
            $formatted = $number; // Number is less than 1000
        }
        $formatted = str_replace( '.00', '', $formatted );

        return $formatted;
    } // sl_format_count()




    public function stars_settings(){
        $this->text = __('Stars', 'like');
        add_action('wp_head', [$this, 'stars_script'], 99);
    }


    public function stars_script()
    {
        ?>
        <script type="text/javascript">
            jQuery(function ($) {
                $('.like-stars').barrating({
                    theme: 'fontawesome-stars'
                });
            });
        </script>
        <?php
    }

    /**
     * Utility retrieves count plus count options,
     * returns appropriate format based on options
     * @since    0.5
     */
    public function get_like_count($like_count, $post_id)
    {
        $like_text = __( 'Like', 'like' );
        if (is_numeric( $like_count ) && $like_count > 0) {
            $number = $this->sl_format_count( $like_count );
        } else {
            $number = $like_text;
        }
        $count = '<span class="sl-count">' . $number . '</span>';

        if($this->settings ==='stars'){
            $count = '<span class="like-stars">' . $this->view_stars($post_id) . '</span>';
        }

        return $count;
    }

    private function view_stars($post_id){
        $rating = 5;
        ?>
        <input type="hidden" name="rating" id="rating" value="<?php echo $rating; ?>" />
        <ul onMouseOut="resetRating(<?php echo $post_id; ?>);">
          <?php
          for($i=1;$i<=5;$i++) {
          $selected = '';
          if( !empty($rating) && $i <= $rating) {
            $selected = 'selected';
          }
          ?>
          <li class='<?php echo $selected; ?>' onmouseover="highlightStar(this,<?php echo $post_id; ?>);" onmouseout="removeHighlight(<?php echo $post_id; ?>);" onClick="addRating(this,<?php echo $post_id; ?>);">&#9733;</li>
          <?php }  ?>
        <ul>
        <?php

    }

    /**
     * User Profile Likes List
     *
     * @param $user
     */
    public function show_user_likes($user)
    {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="user_likes"><?php _e( 'You Like:', 'like' ); ?></label></th>
                <td>
                    <?php
                    $types      = get_post_types( ['public' => true] );
                    $args       = [
                        'numberposts' => -1,
                        'post_type'   => $types,
                        'meta_query'  => [
                            [
                                'key'     => '_user_liked',
                                'value'   => $user->ID,
                                'compare' => 'LIKE'
                            ]
                        ]
                    ];
                    $sep        = '';
                    $like_query = new \WP_Query( $args );

                    if ($like_query->have_posts()) : ?>
                        <p>
                            <?php while ($like_query->have_posts()) : ?>
                                <?php $like_query->the_post(); echo $sep; ?>
                                <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
                                <?php $sep = ' &middot; ';
                            endwhile; ?>
                        </p>
                    <?php else : ?>
                        <p><?php _e( 'You do not like anything yet.', 'like' ); ?></p>
                    <?php endif; ?>

                    <?php wp_reset_postdata();  ?>
                </td>
            </tr>
        </table>
        <?php
    }
}

new PostLike();

/**
 * Get post likes
 *
 * @param int|\WP_Post $post
 * @return string likes
 */
function get_post_likes($post = null)
{
    return (new PostLike())->get_post_likes($post);
}