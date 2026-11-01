<?php
/**
 * ابزار بازیابی کدهای آسیب‌دیده.
 *
 * نسخه ۳.۰.۰ یک اشکال داشت: کال‌بک پاک‌سازیِ ثبت‌شده در register_post_meta
 * روی هر بار نوشتن متا اجرا می‌شد و کد JavaScript را از طریق wp_kses_post
 * می‌گذراند. در نتیجه کدهایی که عملگر «<» یا «>» داشتند از همان نقطه
 * بریده می‌شدند و کد PHP در بافت بدون کاربر (مهاجرت/کرون) خالی می‌شد.
 *
 * این کلاس نسخه سالم را از بازنگری‌های وردپرس بازیابی می‌کند.
 *
 * @package AmolNovin\CHS
 */

declare( strict_types=1 );

namespace AmolNovin\CHS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * تشخیص و بازیابی کدهای ناقص.
 */
final class Repair {

    public const OPTION_DONE = 'chs_repair_done';

    /**
     * اتصال هوک‌ها.
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_post_chs_repair', array( $this, 'handle_repair' ) );
        add_action( 'admin_notices', array( $this, 'notice' ) );
    }

    /**
     * فیلدهایی که ممکن است آسیب دیده باشند.
     *
     * @return string[]
     */
    private static function fields(): array {
        return array( '_chs_js', '_chs_php', '_chs_html', '_chs_header', '_chs_footer' );
    }

    /**
     * آیا یک مقدار نشانه بریدگی دارد؟
     *
     * @param string $current  مقدار فعلی.
     * @param string $previous مقدار بازنگری.
     */
    private static function looks_truncated( string $current, string $previous ): bool {
        if ( '' === trim( $previous ) ) {
            return false;
        }

        // مقدار فعلی خالی شده ولی نسخه قبلی محتوا داشته.
        if ( '' === trim( $current ) ) {
            return true;
        }

        // مقدار فعلی به‌طور محسوس کوتاه‌تر است و پیشوند نسخه قبلی است.
        if ( strlen( $current ) < strlen( $previous ) && 0 === strpos( $previous, $current ) ) {
            return true;
        }

        return false;
    }

    /**
     * یافتن قطعه کدهای آسیب‌دیده.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function scan(): array {
        $ids = get_posts(
            array(
                'post_type'      => POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 500,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        $damaged = array();

        foreach ( (array) $ids as $id ) {
            $id        = (int) $id;
            $revisions = wp_get_post_revisions( $id, array( 'numberposts' => 20 ) );

            if ( empty( $revisions ) ) {
                continue;
            }

            $found = array();

            foreach ( self::fields() as $field ) {
                $current = (string) get_post_meta( $id, $field, true );

                foreach ( $revisions as $revision ) {
                    $previous = (string) get_metadata( 'post', $revision->ID, $field, true );

                    if ( self::looks_truncated( $current, $previous ) ) {
                        $found[ $field ] = array(
                            'revision' => (int) $revision->ID,
                            'current'  => strlen( $current ),
                            'restored' => strlen( $previous ),
                        );
                        break;
                    }
                }
            }

            if ( ! empty( $found ) ) {
                $damaged[ $id ] = array(
                    'title'  => get_the_title( $id ),
                    'fields' => $found,
                );
            }
        }

        return $damaged;
    }

    /**
     * افزودن صفحه ابزار.
     */
    public function add_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . POST_TYPE,
            __( 'بازیابی کدهای ناقص', TEXTDOMAIN ),
            __( 'بازیابی کدهای ناقص', TEXTDOMAIN ),
            manage_cap(),
            'chs-repair',
            array( $this, 'render_page' )
        );
    }

    /**
     * نمایش صفحه ابزار.
     */
    public function render_page(): void {
        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ) );
        }

        $damaged = self::scan();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'بازیابی کدهای ناقص', TEXTDOMAIN ); ?></h1>

            <p class="description">
                <?php esc_html_e( 'این ابزار قطعه کدهایی را که در نسخه ۳.۰.۰ ناقص ذخیره شده‌اند پیدا کرده و نسخه سالم را از بازنگری‌های وردپرس بازمی‌گرداند.', TEXTDOMAIN ); ?>
            </p>

            <?php if ( empty( $damaged ) ) : ?>
                <div class="notice notice-success inline">
                    <p><?php esc_html_e( 'هیچ کد ناقصی یافت نشد. همه چیز سالم است.', TEXTDOMAIN ); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: تعداد */
                            esc_html__( '%s قطعه کد با محتوای ناقص پیدا شد:', TEXTDOMAIN ),
                            esc_html( number_format_i18n( count( $damaged ) ) )
                        );
                        ?>
                    </p>
                </div>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'قطعه کد', TEXTDOMAIN ); ?></th>
                            <th><?php esc_html_e( 'فیلد', TEXTDOMAIN ); ?></th>
                            <th><?php esc_html_e( 'اندازه فعلی', TEXTDOMAIN ); ?></th>
                            <th><?php esc_html_e( 'اندازه قابل بازیابی', TEXTDOMAIN ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $damaged as $id => $info ) : ?>
                            <?php foreach ( $info['fields'] as $field => $detail ) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>">
                                            <?php echo esc_html( $info['title'] ?: sprintf( '#%d', $id ) ); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo esc_html( $field ); ?></code></td>
                                    <td><?php echo esc_html( (string) $detail['current'] ); ?></td>
                                    <td><strong><?php echo esc_html( (string) $detail['restored'] ); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
                    <input type="hidden" name="action" value="chs_repair" />
                    <?php wp_nonce_field( 'chs_repair' ); ?>
                    <?php submit_button( __( 'بازیابی همه', TEXTDOMAIN ) ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * انجام بازیابی.
     */
    public function handle_repair(): void {
        check_admin_referer( 'chs_repair' );

        if ( ! current_user_can( manage_cap() ) ) {
            wp_die( esc_html__( 'دسترسی غیرمجاز.', TEXTDOMAIN ), '', array( 'response' => 403 ) );
        }

        $damaged  = self::scan();
        $restored = 0;

        foreach ( $damaged as $id => $info ) {
            foreach ( $info['fields'] as $field => $detail ) {
                $value = get_metadata( 'post', (int) $detail['revision'], $field, true );

                if ( '' !== $value ) {
                    update_post_meta( (int) $id, $field, $value );
                    $restored++;
                }
            }

            // کد PHP بازیابی‌شده باید دوباره امضا شود.
            $php = (string) get_post_meta( (int) $id, '_chs_php', true );

            if ( '' !== trim( $php ) && Security::can_edit_php() ) {
                Security::sign_php( (int) $id, $php );
            }

            Audit_Log::record( (int) $id, 'restored', __( 'کد ناقص از بازنگری بازیابی شد.', TEXTDOMAIN ) );
        }

        Cache::flush();
        update_option( self::OPTION_DONE, time(), false );

        wp_safe_redirect(
            add_query_arg(
                array( 'chs_msg' => 'repaired', 'count' => $restored ),
                admin_url( 'edit.php?post_type=' . POST_TYPE . '&page=chs-repair' )
            )
        );
        exit;
    }

    /**
     * اطلاع‌رسانی وجود کد ناقص.
     */
    public function notice(): void {
        if ( ! current_user_can( manage_cap() ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || POST_TYPE !== $screen->post_type ) {
            return;
        }

        // فقط یک‌بار و تا وقتی بازیابی انجام نشده.
        if ( get_option( self::OPTION_DONE ) ) {
            return;
        }

        // برای پرهیز از هزینه، فقط روی صفحه فهرست بررسی می‌کنیم.
        if ( ! isset( $screen->base ) || 'edit' !== $screen->base ) {
            return;
        }

        $damaged = self::scan();

        if ( empty( $damaged ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s" class="button button-small">%s</a></p></div>',
            esc_html__( 'کد ناقص شناسایی شد:', TEXTDOMAIN ),
            esc_html(
                sprintf(
                    /* translators: %s: تعداد */
                    __( '%s قطعه کد ممکن است در اثر اشکال نسخه ۳.۰.۰ ناقص ذخیره شده باشد.', TEXTDOMAIN ),
                    number_format_i18n( count( $damaged ) )
                )
            ),
            esc_url( admin_url( 'edit.php?post_type=' . POST_TYPE . '&page=chs-repair' ) ),
            esc_html__( 'بررسی و بازیابی', TEXTDOMAIN )
        );
    }
}
