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
/**
 * Formularz konta: rejestracja, logowanie i wylogowanie.
 */

function csr_account_shortcode() {
    $message = '';
    $message_type = 'error';

    if (
        isset( $_SERVER['REQUEST_METHOD'] ) &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset( $_POST['csr_account_action'] )
    ) {
        $nonce = isset( $_POST['csr_account_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['csr_account_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'csr_account_action' ) ) {
            $message = 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.';
        } else {
            $action = sanitize_key(
                wp_unslash( $_POST['csr_account_action'] )
            );

            if ( $action === 'change_password' && is_user_logged_in() ) {
                $user = wp_get_current_user();

                $current_password = isset( $_POST['csr_current_password'] )
                    ? (string) wp_unslash( $_POST['csr_current_password'] )
                    : '';

                $new_password = isset( $_POST['csr_new_password'] )
                    ? (string) wp_unslash( $_POST['csr_new_password'] )
                    : '';

                $confirm_password = isset( $_POST['csr_confirm_password'] )
                    ? (string) wp_unslash( $_POST['csr_confirm_password'] )
                    : '';

                if (
                    $current_password === '' ||
                    ! wp_check_password(
                        $current_password,
                        $user->user_pass,
                        $user->ID
                    )
                ) {
                    $message = 'Obecne hasło jest nieprawidłowe.';
                } elseif ( strlen( $new_password ) < 8 ) {
                    $message = 'Nowe hasło musi mieć co najmniej 8 znaków.';
                } elseif ( $new_password !== $confirm_password ) {
                    $message = 'Nowe hasła nie są identyczne.';
                } elseif ( $new_password === $current_password ) {
                    $message = 'Nowe hasło musi różnić się od obecnego.';
                } else {
                    $result = wp_update_user( array(
                        'ID'        => $user->ID,
                        'user_pass' => $new_password,
                    ) );

                    if ( is_wp_error( $result ) ) {
                        $message = 'Nie udało się zmienić hasła. Spróbuj ponownie.';
                    } else {
                        $message = 'Hasło zostało zmienione.';
                        $message_type = 'success';
                    }
                }
} elseif ( $action === 'update_profile' && is_user_logged_in() ) {
    $user = wp_get_current_user();

    $display_name = isset( $_POST['csr_display_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['csr_display_name'] ) )
        : '';

    $email = isset( $_POST['csr_profile_email'] )
        ? sanitize_email( wp_unslash( $_POST['csr_profile_email'] ) )
        : '';

    if ( $display_name === '' ) {
        $message = 'Nazwa wyświetlana nie może być pusta.';
    } elseif ( ! is_email( $email ) ) {
        $message = 'Podaj prawidłowy adres e-mail.';
    } elseif (
        email_exists( $email ) &&
        strtolower( $email ) !== strtolower( $user->user_email )
    ) {
        $message = 'Ten adres e-mail jest już używany przez inne konto.';
    } else {
        $result = wp_update_user( array(
            'ID'           => $user->ID,
            'display_name' => $display_name,
            'user_email'   => $email,
        ) );

        if ( is_wp_error( $result ) ) {
            $message = 'Nie udało się zapisać profilu.';
        } else {
            $message = 'Profil został zaktualizowany.';
            $message_type = 'success';
        }
    }
            } elseif ( $action === 'register' && ! is_user_logged_in() ) {
                $username = isset( $_POST['csr_username'] )
                    ? sanitize_user( wp_unslash( $_POST['csr_username'] ) )
                    : '';

                $email = isset( $_POST['csr_email'] )
                    ? sanitize_email( wp_unslash( $_POST['csr_email'] ) )
                    : '';

                $password = isset( $_POST['csr_password'] )
                    ? (string) wp_unslash( $_POST['csr_password'] )
                    : '';

                if ( ! validate_username( $username ) ) {
                    $message = 'Podaj prawidłową nazwę użytkownika.';
                } elseif ( ! is_email( $email ) ) {
                    $message = 'Podaj prawidłowy adres e-mail.';
                } elseif ( username_exists( $username ) ) {
                    $message = 'Ta nazwa użytkownika jest już zajęta.';
                } elseif ( email_exists( $email ) ) {
                    $message = 'Ten adres e-mail jest już używany.';
                } elseif ( strlen( $password ) < 8 ) {
                    $message = 'Hasło musi mieć co najmniej 8 znaków.';
                } else {
                    $user_id = wp_insert_user( array(
                        'user_login' => $username,
                        'user_email' => $email,
                        'user_pass'  => $password,
                        'role'       => 'subscriber',
                    ) );

                    if ( is_wp_error( $user_id ) ) {
                        $message = 'Nie udało się utworzyć konta.';
                    } else {
                        $message = 'Konto utworzone! Możesz się teraz zalogować.';
                        $message_type = 'success';
                    }
                }
            } elseif ( $action === 'login' && ! is_user_logged_in() ) {
                $username = isset( $_POST['csr_username'] )
                    ? sanitize_text_field( wp_unslash( $_POST['csr_username'] ) )
                    : '';

                $password = isset( $_POST['csr_password'] )
                    ? (string) wp_unslash( $_POST['csr_password'] )
                    : '';

                $user = wp_signon( array(
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => true,
                ), is_ssl() );

                if ( is_wp_error( $user ) ) {
                    $message = 'Nie udało się zalogować. Sprawdź dane.';
                } else {
                    wp_set_current_user( $user->ID );
                    $message = 'Zalogowano pomyślnie!';
                    $message_type = 'success';
                }
            }
        }
    }

    ob_start();

    if ( $message !== '' ) {
        $class = $message_type === 'success'
            ? 'csr-message-success'
            : 'csr-message-error';

        echo '<p class="' . esc_attr( $class ) . '">'
            . esc_html( $message )
            . '</p>';
    }

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        ?>

        <h2>Moje konto</h2>

        <p>
            <strong>Nazwa użytkownika:</strong>
            <?php echo esc_html( $user->user_login ); ?>
        </p>

        <p>
            <strong>Adres e-mail:</strong>
            <?php echo esc_html( $user->user_email ); ?>
        </p>

        <p>
            <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
                Wyloguj się
            </a>
        </p>

        <hr>

	<h2>Edytuj profil</h2>

	<form method="post">
    		<?php wp_nonce_field( 'csr_account_action', 'csr_account_nonce' ); ?>
            <p>
                            <label>
                                Nazwa wyświetlana<br>
                                <input type="text"
                                    name="csr_display_name"
                                    value="<?php echo esc_attr( $user->display_name ); ?>"
                                    required>
                            </label>
                        </p>
    		<p>
        		<label>
           			 Adres e-mail<br>
            			<input type="email"
               				name="csr_profile_email"
                			value="<?php echo esc_attr( $user->user_email ); ?>"
                			required>
        		</label>
    		</p>

    		<input type="hidden"
        		name="csr_account_action"
        		value="update_profile">

    		<button type="submit">Zapisz profil</button>
	</form>

	<hr>

        <h2>Zmiana hasła</h2>

        <form method="post">
            <?php wp_nonce_field( 'csr_account_action', 'csr_account_nonce' ); ?>

            <p>
                <label>
                    Obecne hasło<br>
                    <input type="password"
                        name="csr_current_password"
                        autocomplete="current-password"
                        required>
                </label>
            </p>

            <p>
                <label>
                    Nowe hasło (minimum 8 znaków)<br>
                    <input type="password"
                        name="csr_new_password"
                        minlength="8"
                        autocomplete="new-password"
                        required>
                </label>
            </p>

            <p>
                <label>
                    Powtórz nowe hasło<br>
                    <input type="password"
                        name="csr_confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required>
                </label>
            </p>

            <input type="hidden"
                name="csr_account_action"
                value="change_password">

            <button type="submit">Zmień hasło</button>
        </form>

        <?php
    } else {
        ?>

        <h2>Logowanie</h2>

        <form method="post">
            <?php wp_nonce_field( 'csr_account_action', 'csr_account_nonce' ); ?>

            <p>
                <label>
                    Nazwa użytkownika<br>
                    <input type="text" name="csr_username"
                        autocomplete="username" required>
                </label>
            </p>

            <p>
                <label>
                    Hasło<br>
                    <input type="password" name="csr_password"
                        autocomplete="current-password" required>
                </label>
            </p>

            <input type="hidden" name="csr_account_action" value="login">
            <button type="submit">Zaloguj się</button>
        </form>

        <hr>

        <h2>Rejestracja</h2>

        <form method="post">
            <?php wp_nonce_field( 'csr_account_action', 'csr_account_nonce' ); ?>

            <p>
                <label>
                    Nazwa użytkownika<br>
                    <input type="text" name="csr_username"
                        autocomplete="username" required>
                </label>
            </p>

            <p>
                <label>
                    Adres e-mail<br>
                    <input type="email" name="csr_email"
                        autocomplete="email" required>
                </label>
            </p>

            <p>
                <label>
                    Hasło (minimum 8 znaków)<br>
                    <input type="password" name="csr_password"
                        minlength="8" autocomplete="new-password" required>
                </label>
            </p>

            <input type="hidden" name="csr_account_action" value="register">
            <button type="submit">Utwórz konto</button>
        </form>

        <?php
    }

    return ob_get_clean();
}
add_shortcode( 'csr_account', 'csr_account_shortcode' );
function csr_account_styles() {
    if ( is_page( 'konto' ) ) {
        wp_enqueue_style(
            'csr-account-style',
            plugin_dir_url( __FILE__ ) . 'roulette.css',
            array(),
            '0.2'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'csr_account_styles' );
