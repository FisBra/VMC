<?php
/**
 * Child Theme Functions for Enfold
 * @package WordPress
 */

/*
* Add your own functions here. 
* WordPress kommer att använda dessa istället för originalet från parent theme om de överlappar.
*/

/* ------------------------------
   Forgot password popup
--------------------------------*/
function add_custom_script(){
?>
<script type="text/javascript">
jQuery(window).on("load", function(){
    jQuery('#open-popup-link').click(function() {
        jQuery.magnificPopup.open({
            items: { src: '#forgot-popup' },
            type: 'inline'
        }, 0);
    });
});
</script>
<?php
}
add_action('wp_footer', 'add_custom_script');

/* ------------------------------
   WPForms: Attach dynamic PDF
--------------------------------*/
function wpforms_dynamic_pdf_attachment($fields, $entry, $form_data) {
    $pdf_url = !empty($fields['custom_pdf_link']['value']) ? $fields['custom_pdf_link']['value'] : '';
    if (!$pdf_url) return;

    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . '/' . basename($pdf_url);

    if (!file_exists($pdf_path) && filter_var($pdf_url, FILTER_VALIDATE_URL)) {
        copy($pdf_url, $pdf_path); 
    }

    if (file_exists($pdf_path)) {
        add_filter('wpforms_email_attachments', function ($attachments) use ($pdf_path) {
            $attachments[] = $pdf_path;
            return $attachments;
        });
    }
}
add_action('wpforms_process_complete', 'wpforms_dynamic_pdf_attachment', 10, 3);

function get_pdf_url_from_post_meta($field_value, $field_id, $form_data) {
    if ($field_id == '1') {
        $post_id = 9085;
        $pdf_url = get_post_meta($post_id, 'custom_pdf_link', true);
        if ($pdf_url) return $pdf_url;
    }
    return $field_value;
}
add_filter('wpforms_field_value', 'get_pdf_url_from_post_meta', 10, 3);

/* ------------------------------
   Enfold + LearnDash
--------------------------------*/
add_filter('avf_alb_supported_post_types', function ($array) {
    $array[] = 'sfwd-courses';
    $array[] = 'sfwd-lessons';
    $array[] = 'sfwd-topic';
    $array[] = 'sfwd-quiz';
    return $array;
}, 10, 1);

add_filter('avf_metabox_layout_post_types', function ($supported_post_types) {
    $supported_post_types[] = 'sfwd-courses';
    $supported_post_types[] = 'sfwd-lessons';
    $supported_post_types[] = 'sfwd-topic';
    $supported_post_types[] = 'sfwd-quiz';
    return $supported_post_types;
}, 10, 1);

/* ------------------------------
   Login tweaks
--------------------------------*/
function add_forgot_password_link_wpmem($form) {
    if ( get_current_blog_id() === 13 ) return $form; // skip for site 13
//    $custom_lost_password_url = home_url( '/reset-password/' );
	$custom_lost_password_url = wp_lostpassword_url();
    return $form . '<p class="wpmem-forgot-link"><a href="' . esc_url( $custom_lost_password_url ) . '">Glömt lösenord?</a></p>';
}
add_filter('wpmem_login_form', 'add_forgot_password_link_wpmem');

add_filter( 'wpmem_login_redirect', function( $redirect_to, $user_id ) {
    return home_url();
}, 10, 2 );

function custom_login_logo() {
    $logo_url = avia_get_option('logo');
    if ( $logo_url ) {
        echo "<style type='text/css'>
            #login h1 a {
                background-image: url('{$logo_url}');
                background-size: contain;
                width: 100%;
                height: 100px;
            }
        </style>";
    }
}
add_action( 'login_head', 'custom_login_logo' );

add_filter('login_headerurl', function() { return ''; });

/* ------------------------------
   Admin restrictions
--------------------------------*/
add_action( 'admin_init', function() {
    if ( ! is_user_logged_in() ) return;
    $user = wp_get_current_user();
    if ( in_array( 'subscriber', (array) $user->roles ) ) {
        $current_url = $_SERVER['REQUEST_URI'];
        if ( strpos( $current_url, 'wp-admin/index.php' ) !== false || strpos( $current_url, 'wp-admin/profile.php' ) !== false ) {
            wp_redirect( home_url() );
            exit;
        }
    }
});

add_action('init', function() {
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        if ( in_array('subscriber', (array) $user->roles) && !is_super_admin($user->ID) ) {
            show_admin_bar(false);
        }
    }
});

/* ------------------------------
   Menu: profile & logout icons
--------------------------------*/
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( is_user_logged_in() && $args->theme_location === 'avia' ) {
        $logout_url = wp_logout_url( home_url() );
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $is_learndash_active = is_plugin_active( 'sfwd-lms/sfwd_lms.php' );

        if ( $is_learndash_active ) {
            $profile_url = home_url( '/profil/' );
            $items .= '<li class="menu-item profile-icon"><a href="' . esc_url( $profile_url ) . '" title="Min profil"><span class="dashicons dashicons-admin-users"></span></a></li>';
        }

        $items .= '<li class="menu-item logout-icon"><a href="' . esc_url( $logout_url ) . '" title="Logga ut"><span class="dashicons dashicons-migrate"></span></a></li>';
    }
    return $items;
}, 10, 2);

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'dashicons' );
    $custom_css = "
        .profile-icon a, .logout-icon a {
            padding: 0 10px;
            font-size: 20px;
            line-height: 1;
        }
        .profile-icon .dashicons,
        .logout-icon .dashicons {
            vertical-align: middle;
        }
    ";
    wp_add_inline_style( 'dashicons', $custom_css );
});

/* ------------------------------
   Special för Duvyzat (site 13)
--------------------------------*/
add_filter( 'retrieve_password_message', function( $message, $key, $user_login, $user_data ) {
    if ( get_current_blog_id() !== 13 ) return $message;
    $url = "https://duvyzat.outbb.com/wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login);
    $message  = "Welcome to Duvyzat LMS launch program.\n\n<br><br>";
    $message .= "Please follow the link to set your password:\n\n<br><br>";
    $message .= "$url\n\n<br><br>";
    $message .= "After you have logged in you can take part of the training.";
    return $message;
}, 10, 4 );

add_filter( 'wp_mail_from', function( $from ) {
    if ( get_current_blog_id() === 13 ) return 'noreply@duvyzat.outbb.com';
    return $from;
});

add_filter( 'wp_mail_from_name', function( $name ) {
    if ( get_current_blog_id() === 13 ) return 'Duvyzat';
    return $name;
});

add_filter( 'wp_new_user_notification_email', function( $wp_new_user_notification_email, $user, $blogname ) {
    if ( get_current_blog_id() !== 13 ) return $wp_new_user_notification_email;
    $key = get_password_reset_key( $user );
    $url = "https://duvyzat.outbb.com/wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login);
    $wp_new_user_notification_email['subject'] = 'Registration for Duvyzat LMS';
    $wp_new_user_notification_email['headers'] = ['Content-Type: text/plain; charset=UTF-8'];
    $wp_new_user_notification_email['message']  = "Welcome to Duvyzat LMS launch program.\n\n";
    $wp_new_user_notification_email['message'] .= "Please follow the link to set your password:\n\n";
    $wp_new_user_notification_email['message'] .= "$url\n\n";
    $wp_new_user_notification_email['message'] .= "After you have logged in you can take part of the training.";
    return $wp_new_user_notification_email;
}, 10, 3 );

/* ------------------------------
   Årshjul (site 3)
--------------------------------*/
add_action('wp_enqueue_scripts', function() {
    if (get_current_blog_id() !== 3) return;
    wp_enqueue_script(
        'yearwheel-js',
        get_stylesheet_directory_uri() . '/js/yearwheel.js',
        array(),
        filemtime(get_stylesheet_directory() . '/js/yearwheel.js'),
        true
    );
    $post_id = 2903;
    $grouped = array();
    if( have_rows('aktiviteter_', $post_id) ) {
        while( have_rows('aktiviteter_', $post_id) ) { the_row();
            $manad = get_sub_field('manad');
            $grouped[$manad][] = array(
                'aktivitet'   => get_sub_field('aktivitet'),
                'ansvarig'    => get_sub_field('ansvarig'),
                'klart_datum' => get_sub_field('klart_datum'),
                'klart'       => get_sub_field('klart')
            );
        }
    }
    wp_add_inline_script(
        'yearwheel-js',
        'var monthTablesData = ' . json_encode($grouped, JSON_UNESCAPED_UNICODE) . ';',
        'before'
    );
});

add_action('init', function() {
    register_post_type('arshjul', array(
        'labels' => array(
            'name'          => __('Årshjul', 'textdomain'),
            'singular_name' => __('Årshjulsaktivitet', 'textdomain'),
            'add_new'       => __('Lägg till aktivitet', 'textdomain'),
            'add_new_item'  => __('Ny aktivitet', 'textdomain'),
            'edit_item'     => __('Redigera aktivitet', 'textdomain'),
            'new_item'      => __('Ny aktivitet', 'textdomain'),
            'all_items'     => __('Alla aktiviteter', 'textdomain'),
            'menu_name'     => __('Årshjul', 'textdomain')
        ),
        'public'        => true,
        'menu_icon'     => 'dashicons-calendar-alt',
        'supports'      => array('title'),
        'show_in_rest'  => true,
    ));
});

add_shortcode('arshjul_cpt', function() {
    $posts = get_posts([
        'post_type'      => 'arshjul',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => 'klart_datum',
        'order'          => 'ASC',
    ]);
    $grouped = [];
    foreach ($posts as $post) {
        $manad = get_field('manad', $post->ID);
        $grouped[$manad][] = [
            'aktivitet'   => get_field('aktivitet', $post->ID),
            'ansvarig'    => get_field('ansvarig', $post->ID),
            'klart_datum' => get_field('klart_datum', $post->ID),
            'klart'       => get_field('klart', $post->ID),
        ];
    }
    ob_start(); ?>
    <script>
      var monthTablesDataCPT = <?php echo json_encode($grouped, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <div id="arshjul-cpt-container"></div>
    <?php
    return ob_get_clean();
});

function render_arshjul_cpt() {
    ob_start(); ?>
    <div class="year-container">
      <svg id="yearWheel" viewBox="0 0 200 200"></svg>
      <div id="table-container">
        <div id="month-title"></div>
        <div id="tables-wrapper"></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('arshjul_cpt', 'render_arshjul_cpt');

/* ------------------------------
   Styles: Load parent + child CSS
--------------------------------*/
add_action( 'wp_enqueue_scripts', 'enfold_child_enqueue_styles' );
function enfold_child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 
        'child-style', 
        get_stylesheet_directory_uri() . '/style.css', 
        array('parent-style'), 
        filemtime( get_stylesheet_directory() . '/style.css' ) 
    );
}


function child_enqueue_scripts() {
    // Ladda popup-skriptet
    wp_enqueue_script(
        'coach-popup', 
        get_stylesheet_directory_uri() . '/js/coach-popup.js', 
        array(), 
        null, 
        true
    );
}
add_action('wp_enqueue_scripts', 'child_enqueue_scripts');

// Nyhetsflöde för intranätet – utan kategorifilter
function vmc_intranet_news_shortcode( $atts ) {
    $atts = shortcode_atts(
        [
            'posts_per_page' => 5,
        ],
        $atts
    );

    $q = new WP_Query(
        [
            'post_type'           => 'post',
            'posts_per_page'      => intval( $atts['posts_per_page'] ),
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]
    );

    if ( ! $q->have_posts() ) {
        return '<div class="vmc-news-box"><p>Inga nyheter just nu.</p></div>';
    }

    ob_start(); ?>
    <aside class="vmc-news-box">
        <h2 class="vmc-news-title">Nyheter</h2>
        <ul class="vmc-news-list">
            <?php while ( $q->have_posts() ) : $q->the_post(); ?>
                <li class="vmc-news-item">
                    <span class="vmc-news-date"><?php echo get_the_date( 'Y-m-d' ); ?></span>
                    <a href="<?php the_permalink(); ?>" class="vmc-news-link">
                        <?php the_title(); ?>
                    </a>
                </li>
            <?php endwhile; wp_reset_postdata(); ?>
        </ul>
    </aside>
    <?php
    return ob_get_clean();
}
add_shortcode( 'vmc_news', 'vmc_intranet_news_shortcode' );


/* ============================================
 * VMC – stäng av delning & kommentarer på inlägg
 * ============================================ */

/* 1. Ta bort delningsikoner ("Share this entry") i Enfold */
add_filter( 'avf_display_social_share_links', '__return_false' );

/* 2. Stäng av kommentarer och pingar på alla inlägg */
add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );

/* 3. Dölj kommentarsdelen i Enfolds template (så inget kommentarsblock visas) */
add_filter( 'avf_template_builder_comments', '__return_false' );

/* 4. Ta bort "0 kommentarer" i metaraden under rubriken */
add_filter( 'avf_post_meta_comments', '__return_false' );


add_action( 'wpforms_post_submissions_process', function( $fields, $entry, $form_data, $post_id ) {

    // ID på bildfältet i WPForms
    $image_field_id = 5; // ÄNDRA detta till rätt ID

    // featured image
    if ( ! empty( $fields[$image_field_id]['value'] ) ) {

        $image_url = $fields[$image_field_id]['value'];
        $attachment_id = attachment_url_to_postid( $image_url );

        if ( $attachment_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }

        // Lägg in bilden överst i inläggs-content
        $image_html = '<p><img src="' . esc_url( $image_url ) . '" style="max-width:100%;height:auto;border-radius:6px;"></p>';

        $current = get_post_field( 'post_content', $post_id );
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $image_html . $current
        ]);
    }

}, 10, 4 );




// VMC ENDAST, LÄGGS IN I FOOTER: 



/* ======================================================================
   Lägg till Kalender-popup + Murre-widget i footern (endast site ID 3)
   ====================================================================== */
add_action('wp_footer', function () {

    // Multisite-check
    if (!is_multisite() || get_current_blog_id() != 3) {
        return;
    }

    // HTML + JS
    ?>
    
    <!-- === Kalender Popup === -->
    <div id="calendarModal" class="calendar-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
      <div class="calendar-modal-content" style="background:#fff; border-radius:12px; width:90%; max-width:1000px; height:80%; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(0,0,0,0.4);">
        <div class="calendar-modal-header" style="padding:10px; background:#2d2d2d; color:#fff; text-align:right; display:flex; justify-content:space-between; align-items:center;">
          <h2 style="margin:0; font-size:16px; color:#fff;">Kalender</h2>
          <span id="closeCalendarPopup" class="close-btn" style="cursor:pointer; font-size:20px; font-weight:bold;">&times;</span>
        </div>
        <iframe src="https://calendar.google.com/calendar/embed?src=6302b0267f944c0ed0ce955b9797450d5d1ad89c8ef1e6b8bb9b89e183f21bb6%40group.calendar.google.com&ctz=Europe%2FStockholm&mode=WEEK" frameborder="0" scrolling="no" style="border:0; width:100%; height:90%;"></iframe>
      </div>
    </div>

    <!-- === VMC Chatbot Widget (Murre) === -->

    <!-- Startknapp nere till höger -->
    <button class="murre-launcher" onclick="openCoachPopup()" aria-label="Öppna Murre">
      <img src="/wp-content/uploads/sites/3/2025/09/Murre.jpg" alt="Murre" class="murre-launcher-avatar">
    </button>

    <!-- Widgeten -->
    <div id="coachPopup" class="murre-widget">
      <div class="murre-inner" role="dialog" aria-modal="true">
        <div class="murre-header">
          <div class="murre-header-main">
            <h2>Murre</h2>
            <p>Dokumentsallvetare</p>
          </div>
          <button type="button" class="murre-close" onclick="closeCoachPopup()" aria-label="Stäng chatbot">&times;</button>
        </div>

        <div class="murre-body">
          <div id="murreMessages" class="murre-messages"></div>

          <button type="button" class="murre-escalate-trigger" onclick="openMurreEscalate()">
            Skicka vidare till människa
          </button>

          <div id="coachStatus" class="murre-status"></div>

          <div class="murre-input-row">
            <textarea id="coachInput" rows="2" class="murre-input" placeholder="Skriv en fråga till Murre..."></textarea>
            <button type="button" class="murre-send" onclick="askAICoach()">Skicka</button>
          </div>
        </div>
      </div>

      <div id="murreEscalateModal" class="murre-escalate-backdrop">
        <div class="murre-escalate-dialog">
          <h3>Skicka vidare till människa</h3>
          <p>Vem ska få frågan?</p>

          <div class="murre-escalate-options">
            <label class="murre-escalate-option">
              <input type="radio" name="murre-escalate-target" value="IT">
              <span>IT</span>
            </label>
            <label class="murre-escalate-option">
              <input type="radio" name="murre-escalate-target" value="Ekonomi">
              <span>Ekonomi</span>
            </label>
            <label class="murre-escalate-option">
              <input type="radio" name="murre-escalate-target" value="Verkstad">
              <span>Verkstad</span>
            </label>
            <label class="murre-escalate-option">
              <input type="radio" name="murre-escalate-target" value="VD">
              <span>VD</span>
            </label>
          </div>

          <div class="murre-escalate-actions">
            <button type="button" class="murre-escalate-cancel" onclick="closeMurreEscalate()">Avbryt</button>
            <button type="button" class="murre-escalate-send" onclick="confirmMurreEscalate()">Skicka</button>
          </div>

          <div class="murre-escalate-status" id="murreEscalateStatus"></div>
        </div>
      </div>
    </div>

    <!-- === JS som hanterar kalender-popup === -->
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const calendarLink = document.querySelector("a[href='#calendar']");
        const modal = document.getElementById("calendarModal");
        const closeBtn = document.getElementById("closeCalendarPopup");

        if (calendarLink) {
          calendarLink.addEventListener("click", function(e) {
            e.preventDefault();
            modal.style.display = "flex";
          });
        }

        if (closeBtn) {
          closeBtn.addEventListener("click", function() {
            modal.style.display = "none";
          });
        }

        window.addEventListener("click", function(e) {
          if (e.target === modal) {
            modal.style.display = "none";
          }
        });
      });
    </script>

    <?php
});
