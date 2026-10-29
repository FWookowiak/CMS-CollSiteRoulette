<?php
/**
 * Plugin Name: Cool Site Roulette
 * Description: Ruletka losująca ciekawe strony z wybranej kategorii.
 * Version: 0.1
 * Author: Zespół Cool Site Roulette
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function csr_register_types() {
    register_post_type( 'csr_site', array(
        'labels' => array(
            'name'          => 'Linki ruletki',
            'singular_name' => 'Link ruletki',
            'menu_name'     => 'Ruletka',
            'all_items'     => 'Wszystkie linki',
            'add_new'       => 'Dodaj link',
            'add_new_item'  => 'Dodaj link',
            'edit_item'     => 'Edytuj link',
        ),
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-randomize',
        'supports'  => array( 'title' ),
    ) );

    register_taxonomy( 'csr_category', 'csr_site', array(
        'labels' => array(
            'name'          => 'Kategorie',
            'singular_name' => 'Kategoria',
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'hierarchical'      => true,
    ) );
}
add_action( 'init', 'csr_register_types' );


function csr_add_url_box() {
    add_meta_box( 'csr_url_box', 'Adres URL', 'csr_url_box_html', 'csr_site' );
}
add_action( 'add_meta_boxes', 'csr_add_url_box' );

function csr_url_box_html( $post ) {
    $url = get_post_meta( $post->ID, 'csr_url', true );
    wp_nonce_field( 'csr_save_url', 'csr_nonce' ); // ukryte pole zabezpieczające formularz
    echo '<input type="url" name="csr_url" value="' . esc_attr( $url ) . '" style="width:100%" placeholder="https://...">';
}

function csr_save_url( $post_id ) {
    if ( ! isset( $_POST['csr_nonce'] ) || ! wp_verify_nonce( $_POST['csr_nonce'], 'csr_save_url' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['csr_url'] ) ) {
        update_post_meta( $post_id, 'csr_url', esc_url_raw( $_POST['csr_url'] ) );
    }
}
add_action( 'save_post_csr_site', 'csr_save_url' );


function csr_activate() {
    csr_register_types();
    $existing = get_posts( array(
        'post_type'   => 'csr_site',
        'post_status' => 'any',
        'numberposts' => 1,
    ) );
    if ( ! empty( $existing ) ) {
        return;
    }

    $sites = array(
        array( 'Losowy artykuł z polskiej Wikipedii', 'https://pl.wikipedia.org/wiki/Specjalna:Losowa_strona', 'Wiki' ),
        array( 'Losowy artykuł z angielskiej Wikipedii', 'https://en.wikipedia.org/wiki/Special:Random', 'Wiki' ),
        array( 'Losowy plik z Wikimedia Commons', 'https://commons.wikimedia.org/wiki/Special:Random/File', 'Wiki' ),
        array( 'Radio Garden – radio z całego świata', 'https://radio.garden', 'Świat' ),
        array( 'Window Swap – widok z cudzego okna', 'https://www.window-swap.com', 'Świat' ),
        array( 'Mapa wiatrów na żywo', 'https://earth.nullschool.net', 'Świat' ),
        array( 'The Useless Web', 'https://theuselessweb.com', 'Dziwne' ),
        array( 'Pointer Pointer', 'https://pointerpointer.com', 'Dziwne' ),
        array( 'Neal.fun', 'https://neal.fun', 'Dziwne' ),
        array( 'Losowy komiks xkcd', 'https://c.xkcd.com/random/comic/', 'Dziwne' ),
    );

    foreach ( $sites as $site ) {
        $post_id = wp_insert_post( array(
            'post_type'   => 'csr_site',
            'post_title'  => $site[0],
            'post_status' => 'publish',
        ) );
        update_post_meta( $post_id, 'csr_url', $site[1] );
        wp_set_object_terms( $post_id, $site[2], 'csr_category' ); // sama tworzy kategorię, jeśli jej nie ma
    }
}
register_activation_hook( __FILE__, 'csr_activate' );


function csr_shortcode() {
    $posts = get_posts( array(
        'post_type'   => 'csr_site',
        'numberposts' => -1,
    ) );

    $sites = array();
    foreach ( $posts as $p ) {
        $terms   = get_the_terms( $p->ID, 'csr_category' );
        $sites[] = array(
            'name'     => $p->post_title,
            'url'      => get_post_meta( $p->ID, 'csr_url', true ),
            'category' => $terms ? $terms[0]->name : 'Inne',
        );
    }

    $categories = get_terms( array( 'taxonomy' => 'csr_category' ) );

    wp_enqueue_style( 'csr-style', plugin_dir_url( __FILE__ ) . 'roulette.css', array(), '0.1' );
    wp_enqueue_script( 'csr-script', plugin_dir_url( __FILE__ ) . 'roulette.js', array(), '0.1', true );

    wp_localize_script( 'csr-script', 'csrData', array( 'sites' => $sites ) );

    ob_start();
    ?>
    <div class="csr-roulette">
        <select id="csr-category">
            <option value="">Wszystkie kategorie</option>
            <?php foreach ( $categories as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat->name ); ?>"><?php echo esc_html( $cat->name ); ?></option>
            <?php endforeach; ?>
        </select>

        <div id="csr-display">Spin it baby!</div>

        <button type="button" id="csr-spin">Spin! 🎰</button>

        <a id="csr-link" href="#" target="_blank" rel="noopener" hidden>Odbierz wygraną →</a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'ruletka', 'csr_shortcode' );