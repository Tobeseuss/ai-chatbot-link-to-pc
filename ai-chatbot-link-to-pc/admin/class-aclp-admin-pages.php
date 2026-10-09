<?php
/**
 * رندر صفحات پنل مدیریت.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Admin_Pages {

        /**
         * شروع صفحه با هدر استاندارد.
         *
         * @param string $title عنوان.
         */
        private static function header( $title ) {
                echo '<div class="wrap aclp-wrap"><h1>' . esc_html( $title ) . '</h1>';
        }

        /** پایان صفحه. */
        private static function footer() {
                echo '</div>';
        }

        /**
         * nonce مخفی برای فرم‌ها.
         */
        private static function nonce() {
                wp_nonce_field( 'aclp_action', '_aclp_nonce' );
        }

        /* =====================================================================
         * داشبورد
         * =================================================================== */
        public static function render_dashboard() {
                self::header( 'AI-PC Link — داشبورد' );

                $cmd_stats  = ACLP_Commands::stats();
                $file_stats = ACLP_Files::stats();
                $keys       = (array) ACLP_API_Keys::all();
                $active_keys = count( array_filter( $keys, function ( $k ) { return (int) $k->is_active; } ) );
                $clients    = (array) ACLP_Clients::all();
                $online     = count( array_filter( $clients, array( 'ACLP_Utils', 'client_is_online' ) ) );

                echo '<div class="aclp-cards">';
                self::card( $active_keys . ' / ' . count( $keys ), 'کلیدهای فعال', '🔑' );
                self::card( $online . ' / ' . count( $clients ), 'سیستم‌های آنلاین', '💻' );
                self::card( number_format_i18n( $cmd_stats['today'] ), 'فرمان‌های امروز', '⚡' );
                self::card( number_format_i18n( $cmd_stats['pending'] ), 'در صف ارسال', '⏳' );
                self::card( number_format_i18n( $file_stats['count'] ), 'فایل منتقل‌شده', '📁' );
                self::card( ACLP_Utils::human_size( $file_stats['size'] ), 'حجم فایل‌ها', '💾' );
                echo '</div>';

                // راهنمای اتصال سریع.
                $rest_url = rest_url( 'aclp/v1' );
                echo '<div class="card aclp-guide"><h2>اتصال سریع</h2><ol>';
                echo '<li>در بخش <a href="' . esc_url( admin_url( 'admin.php?page=aclp-keys' ) ) . '">کلیدهای API</a> یک کلید بسازید و آن را کپی کنید (فقط یک‌بار نمایش داده می‌شود).</li>';
                echo '<li>برنامه ایجنت (Python) را روی سیستم خود (ویندوز/لینوکس) دانلود و اجرا کنید و همین آدرس و کلید را وارد کنید:<br><code>' . esc_html( $rest_url ) . '</code></li>';
                echo '<li>چت‌بات هوش مصنوعی شما با استفاده از <a href="https://github.com/Tobeseuss/ai-chatbot-link-to-pc/blob/main/docs/AGENT-API.md" target="_blank">مستندات API</a> می‌تواند فرمان‌ها را به سیستم شما ارسال کند.</li>';
                echo '</ol></div>';

                // آخرین فرمان‌ها.
                $q = ACLP_Commands::query( array( 'per_page' => 15 ) );
                echo '<h2>آخرین تعاملات</h2>';
                self::commands_table( $q['rows'], false );
                echo '<p><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=aclp-history' ) ) . '">مشاهده کل تاریخچه</a></p>';

                self::footer();
        }

        /**
         * کارت آماری داشبورد.
         *
         * @param string $value مقدار.
         * @param string $label برچسب.
         * @param string $icon  آیکون.
         */
        private static function card( $value, $label, $icon ) {
                echo '<div class="aclp-card"><div class="aclp-card-icon">' . esc_html( $icon ) . '</div><div class="aclp-card-value">' . esc_html( $value ) . '</div><div class="aclp-card-label">' . esc_html( $label ) . '</div></div>';
        }

        /* =====================================================================
         * کلیدهای API
         * =================================================================== */
        public static function render_keys() {
                self::header( 'کلیدهای API' );
                $keys = (array) ACLP_API_Keys::all();

                echo '<p class="description">هر کلید API مستقل است و می‌تواند به یک یا چند سیستم متصل شود. با هر کلید می‌توانید سقف تعداد سیستم‌ها را هم مشخص کنید (۰ = نامحدود).</p>';

                // فرم ساخت کلید.
                echo '<div class="card aclp-form-card"><h2>ساخت کلید جدید</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                self::nonce();
                echo '<input type="hidden" name="action" value="aclp_add_key">';
                echo '<table class="form-table"><tbody>';
                echo '<tr><th><label for="key_name">نام کلید</label></th><td><input name="key_name" id="key_name" type="text" class="regular-text" required placeholder="مثلاً: کلید چت‌بات شخصی"></td></tr>';
                echo '<tr><th><label for="max_clients">حداکثر سیستم‌ها</label></th><td><input name="max_clients" id="max_clients" type="number" min="0" value="0" class="small-text"> <span class="description">۰ = نامحدود</span></td></tr>';
                echo '<tr><th><label for="key_notes">یادداشت</label></th><td><input name="key_notes" id="key_notes" type="text" class="regular-text" placeholder="اختیاری"></td></tr>';
                echo '</tbody></table>';
                submit_button( 'ساخت کلید API', 'primary', 'submit', true );
                echo '</form></div>';

                // جدول کلیدها.
                echo '<h2>کلیدهای موجود (' . count( $keys ) . ')</h2>';
                echo '<table class="widefat striped aclp-table"><thead><tr>
                        <th>نام</th><th>کلید</th><th>وضعیت</th><th>سقف سیستم‌ها</th><th>آخرین استفاده</th><th>تاریخ ساخت</th><th>عملیات</th>
                </tr></thead><tbody>';

                if ( empty( $keys ) ) {
                        echo '<tr><td colspan="7">هنوز کلیدی ساخته نشده است.</td></tr>';
                }
                foreach ( $keys as $k ) {
                        $active = (int) $k->is_active;
                        echo '<tr>';
                        echo '<td><strong>' . esc_html( $k->name ) . '</strong>' . ( $k->notes ? '<br><span class="description">' . esc_html( $k->notes ) . '</span>' : '' ) . '</td>';
                        echo '<td><code>' . esc_html( $k->key_prefix ) . '</code></td>';
                        echo '<td>' . ( $active ? '<span class="aclp-badge aclp-badge-green">فعال</span>' : '<span class="aclp-badge aclp-badge-red">غیرفعال</span>' ) . '</td>';
                        echo '<td>' . ( (int) $k->max_clients ? (int) $k->max_clients : 'نامحدود' ) . '</td>';
                        echo '<td>' . esc_html( $k->last_used_at ? mysql2date( 'Y/m/d H:i', $k->last_used_at ) : '—' ) . '</td>';
                        echo '<td>' . esc_html( mysql2date( 'Y/m/d', $k->created_at ) ) . '</td>';
                        echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px">';
                        self::nonce();
                        echo '<input type="hidden" name="action" value="aclp_key_action"><input type="hidden" name="key_id" value="' . (int) $k->id . '">';
                        if ( $active ) {
                                echo '<button class="button button-small" type="submit" name="subaction" value="deactivate">غیرفعال‌سازی</button>';
                        } else {
                                echo '<button class="button button-small" type="submit" name="subaction" value="activate">فعال‌سازی</button>';
                        }
                        echo '<button class="button button-small aclp-confirm" data-confirm="با حذف کلید، سیستم‌های متصل به آن هم حذف می‌شوند. مطمئن هستید؟" type="submit" name="subaction" value="delete">حذف</button>';
                        echo '</form></td></tr>';
                }
                echo '</tbody></table>';

                self::footer();
        }

        /* =====================================================================
         * سیستم‌های متصل
         * =================================================================== */
        public static function render_clients() {
                self::header( 'سیستم‌های متصل' );
                $clients = (array) ACLP_Clients::all();

                echo '<p class="description">هر خط یک سیستم (ویندوز یا لینوکس) است که با برنامه ایجنت به یکی از کلیدهای شما متصل شده. چند سیستم می‌توانند همزمان از یک کلید استفاده کنند.</p>';

                echo '<table class="widefat striped aclp-table"><thead><tr>
                        <th>نام</th><th>سیستم‌عامل</th><th>نام میزبان</th><th>IP</th><th>وضعیت</th><th>نسخه ایجنت</th><th>تعداد فرمان‌ها</th><th>آخرین حضور</th><th>عملیات</th>
                </tr></thead><tbody>';

                if ( empty( $clients ) ) {
                        echo '<tr><td colspan="9">هنوز سیستمی متصل نشده است. ایجنت را روی سیستم خود اجرا کنید.</td></tr>';
                }
                foreach ( $clients as $c ) {
                        $online = ACLP_Utils::client_is_online( $c );
                        $key    = ACLP_API_Keys::get( $c->key_id );
                        echo '<tr>';
                        echo '<td><strong>' . esc_html( $c->name ?: 'بدون نام' ) . '</strong><br><span class="description">کلید: ' . esc_html( $key ? $key->name : 'حذف‌شده' ) . '</span></td>';
                        echo '<td>' . esc_html( trim( $c->os . ' ' . $c->os_version ) ) . '</td>';
                        echo '<td>' . esc_html( $c->hostname ) . '</td>';
                        echo '<td>' . esc_html( $c->ip ) . '</td>';
                        echo '<td>' . ( $online ? '<span class="aclp-badge aclp-badge-green">آنلاین</span>' : '<span class="aclp-badge aclp-badge-gray">آفلاین</span>' ) . '</td>';
                        echo '<td>' . esc_html( $c->agent_version ?: '—' ) . '</td>';
                        echo '<td>' . number_format_i18n( (int) $c->commands_total ) . '</td>';
                        echo '<td>' . esc_html( $c->last_seen_at ? mysql2date( 'Y/m/d H:i:s', $c->last_seen_at ) . ' UTC' : '—' ) . '</td>';
                        echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                        self::nonce();
                        echo '<input type="hidden" name="action" value="aclp_client_delete"><input type="hidden" name="client_id" value="' . (int) $c->id . '">';
                        echo '<button class="button button-small aclp-confirm" data-confirm="این سیستم حذف شود؟ فرمان‌های در صف آن هم پاک می‌شوند." type="submit">حذف</button>';
                        echo '</form></td></tr>';
                }
                echo '</tbody></table>';

                self::footer();
        }

        /* =====================================================================
         * تاریخچه تعاملات
         * =================================================================== */
        public static function render_history() {
                self::header( 'تاریخچه تعاملات' );

                // نمای جزئیات فرمان.
                if ( isset( $_GET['view'] ) && 'command' === $_GET['view'] && ! empty( $_GET['cmd'] ) ) {
                        self::command_detail( (int) $_GET['cmd'] );
                        self::footer();
                        return;
                }

                $filters = array(
                        'key_id'    => isset( $_GET['key_id'] ) ? (int) $_GET['key_id'] : 0,
                        'client_id' => isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0,
                        'status'    => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
                        'type'      => isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '',
                        'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
                        'paged'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
                        'per_page'  => 25,
                );
                $q = ACLP_Commands::query( $filters );

                // فرم فیلتر.
                echo '<form method="get" class="aclp-filters"><input type="hidden" name="page" value="aclp-history">';
                echo '<input type="search" name="s" placeholder="جستجو در UID / payload / نتیجه..." value="' . esc_attr( $filters['search'] ) . '">';
                echo '<select name="status"><option value="">همه وضعیت‌ها</option>';
                foreach ( array( 'pending', 'sent', 'running', 'completed', 'failed' ) as $st ) {
                        echo '<option value="' . $st . '" ' . selected( $filters['status'], $st, false ) . '>' . esc_html( ACLP_Utils::status_label( $st ) ) . '</option>';
                }
                echo '</select>';
                echo '<select name="type"><option value="">همه انواع</option>';
                foreach ( array( 'shell', 'file_read', 'file_write', 'file_list', 'file_delete', 'file_mkdir', 'file_move', 'file_download', 'upload_file', 'open_url', 'http_request', 'screenshot', 'sysinfo', 'process_list', 'kill_process', 'install', 'run_python', 'ping' ) as $tp ) {
                        echo '<option value="' . $tp . '" ' . selected( $filters['type'], $tp, false ) . '>' . esc_html( ACLP_Utils::type_label( $tp ) ) . '</option>';
                }
                echo '</select>';
                echo '<button class="button" type="submit">اعمال فیلتر</button>';
                echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=aclp-history' ) ) . '">حذف فیلترها</a>';
                echo '</form>';

                echo '<p class="description">مجموع: ' . number_format_i18n( $q['total'] ) . ' رکورد — روی «جزئیات» بزنید تا payload کامل، نتیجه و فایل‌های منتقل‌شده را ببینید.</p>';

                self::commands_table( $q['rows'], true );

                // صفحه‌بندی.
                $pages = (int) ceil( $q['total'] / $q['per_page'] );
                if ( $pages > 1 ) {
                        echo '<div class="tablenav"><div class="tablenav-pages">';
                        for ( $i = 1; $i <= min( $pages, 50 ); $i++ ) {
                                $url  = add_query_arg( 'paged', $i, admin_url( 'admin.php?page=aclp-history' ) );
                                $cls  = $i === $q['paged'] ? 'button button-primary' : 'button';
                                echo '<a class="' . $cls . '" href="' . esc_url( $url ) . '">' . $i . '</a> ';
                        }
                        echo '</div></div>';
                }

                self::footer();
        }

        /**
         * جدول فرمان‌ها (مشترک بین داشبورد و تاریخچه).
         *
         * @param array $rows   ردیف‌ها.
         * @param bool  $detail نمایش ستون جزئیات.
         */
        private static function commands_table( $rows, $detail = true ) {
                echo '<table class="widefat striped aclp-table"><thead><tr>
                        <th>#</th><th>زمان</th><th>نوع</th><th>کلاینت</th><th>منبع</th><th>وضعیت</th><th>مدت اجرا</th>' . ( $detail ? '<th>جزئیات</th>' : '' ) . '
                </tr></thead><tbody>';
                if ( empty( $rows ) ) {
                        echo '<tr><td colspan="8">رکوردی یافت نشد.</td></tr>';
                }
                foreach ( (array) $rows as $r ) {
                        $cls = 'aclp-badge aclp-badge-gray';
                        if ( 'completed' === $r->status ) { $cls = 'aclp-badge aclp-badge-green'; }
                        if ( 'failed' === $r->status ) { $cls = 'aclp-badge aclp-badge-red'; }
                        if ( 'pending' === $r->status ) { $cls = 'aclp-badge aclp-badge-orange'; }
                        if ( 'running' === $r->status || 'sent' === $r->status ) { $cls = 'aclp-badge aclp-badge-blue'; }
                        echo '<tr>';
                        echo '<td>' . (int) $r->id . '</td>';
                        echo '<td>' . esc_html( mysql2date( 'Y/m/d H:i:s', $r->created_at ) ) . '</td>';
                        echo '<td>' . esc_html( ACLP_Utils::type_label( $r->type ) ) . '</td>';
                        echo '<td>' . esc_html( $r->client_name ?: ( 'حذف‌شده #' . $r->client_id ) ) . '</td>';
                        echo '<td>' . esc_html( $r->source ) . '</td>';
                        echo '<td><span class="' . $cls . '">' . esc_html( ACLP_Utils::status_label( $r->status ) ) . '</span>' . ( $r->error ? '<br><span class="description">' . esc_html( wp_trim_words( $r->error, 8 ) ) . '</span>' : '' ) . '</td>';
                        echo '<td>' . ( (int) $r->duration_ms ? esc_html( round( $r->duration_ms / 1000, 2 ) . ' ثانیه' ) : '—' ) . '</td>';
                        if ( $detail ) {
                                $url = add_query_arg( array( 'view' => 'command', 'cmd' => (int) $r->id ), admin_url( 'admin.php?page=aclp-history' ) );
                                echo '<td><a class="button button-small" href="' . esc_url( $url ) . '">جزئیات</a></td>';
                        }
                        echo '</tr>';
                }
                echo '</tbody></table>';
        }

        /**
         * نمایش جزئیات کامل یک فرمان.
         *
         * @param int $id شناسه فرمان.
         */
        private static function command_detail( $id ) {
                $cmd = ACLP_Commands::get( $id );
                if ( ! $cmd ) {
                        echo '<p>فرمان یافت نشد.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=aclp-history' ) ) . '">بازگشت</a></p>';
                        return;
                }

                echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=aclp-history' ) ) . '">← بازگشت به تاریخچه</a></p>';
                echo '<div class="card"><h2>فرمان #' . (int) $cmd->id . ' — ' . esc_html( ACLP_Utils::type_label( $cmd->type ) ) . '</h2>';
                echo '<table class="widefat striped"><tbody>';
                echo '<tr><th>UID</th><td><code>' . esc_html( $cmd->command_uid ) . '</code></td></tr>';
                echo '<tr><th>وضعیت</th><td>' . esc_html( ACLP_Utils::status_label( $cmd->status ) ) . '</td></tr>';
                echo '<tr><th>زمان‌ها</th><td>ساخت: ' . esc_html( $cmd->created_at ) . ' UTC';
                if ( $cmd->sent_at ) { echo ' — ارسال: ' . esc_html( $cmd->sent_at ); }
                if ( $cmd->completed_at ) { echo ' — پایان: ' . esc_html( $cmd->completed_at ); }
                echo ' — مدت اجرا: ' . esc_html( round( $cmd->duration_ms / 1000, 2 ) ) . ' ثانیه</td></tr>';
                echo '<tr><th>منبع</th><td>' . esc_html( $cmd->source ) . '</td></tr>';
                if ( $cmd->error ) {
                        echo '<tr><th>خطا</th><td style="color:#b32d2e"><pre style="white-space:pre-wrap">' . esc_html( $cmd->error ) . '</pre></td></tr>';
                }
                echo '</tbody></table>';

                echo '<h3>Payload ارسالی (چت‌بات → سیستم)</h3>';
                echo '<pre class="aclp-json">' . esc_html( wp_json_encode( json_decode( (string) $cmd->payload, true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre>';

                echo '<h3>نتیجه دریافتی (سیستم → چت‌بات)</h3>';
                echo '<pre class="aclp-json">' . esc_html( wp_json_encode( json_decode( (string) $cmd->result, true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre>';

                $files = ACLP_Files::for_command( $cmd->id );
                echo '<h3>فایل‌های منتقل‌شده (' . count( $files ) . ')</h3>';
                if ( empty( $files ) ) {
                        echo '<p>فایلی منتقل نشده است.</p>';
                } else {
                        echo '<table class="widefat striped"><thead><tr><th>نام فایل</th><th>جهت</th><th>حجم</th><th>زمان</th><th>عملیات</th></tr></thead><tbody>';
                        foreach ( $files as $f ) {
                                $dl = wp_nonce_url( admin_url( 'admin-post.php?action=aclp_download_file&file_id=' . (int) $f->id ), 'aclp_download', '_aclp_nonce' );
                                echo '<tr><td>' . esc_html( $f->original_name ) . '</td>';
                                echo '<td>' . ( 'to_pc' === $f->direction ? '⬇ چت‌بات → سیستم' : '⬆ سیستم → چت‌بات' ) . '</td>';
                                echo '<td>' . esc_html( ACLP_Utils::human_size( $f->size ) ) . '</td>';
                                echo '<td>' . esc_html( $f->created_at ) . '</td>';
                                echo '<td><a class="button button-small" href="' . esc_url( $dl ) . '">دانلود</a></td></tr>';
                        }
                        echo '</tbody></table>';
                }
                echo '</div>';
        }

        /* =====================================================================
         * تنظیمات
         * =================================================================== */
        public static function render_settings() {
                self::header( 'تنظیمات AI-PC Link' );
                $s = ACLP_Settings::all();

                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                self::nonce();
                echo '<input type="hidden" name="action" value="aclp_save_settings">';

                echo '<h2 class="title">اتصال و اجرا</h2>';
                echo '<table class="form-table"><tbody>';
                self::number_field( 'poll_interval', 'فاصله Polling ایجنت (ثانیه)', $s['poll_interval'], 'هر چند ثانیه ایجنت فرمان‌های جدید را بررسی کند (پیشنهاد: ۳ تا ۱۰)' );
                self::number_field( 'online_timeout', 'آستانه آفلاین (ثانیه)', $s['online_timeout'], 'اگر سیستم در این مدت خبری ندهد، آفلاین محسوب می‌شود' );
                self::number_field( 'command_timeout', 'تایم‌اوت پیش‌فرض اجرای دستور (ثانیه)', $s['command_timeout'], 'تایم‌اوت پیش‌فرض اجرای دستورات در ایجنت؛ هر فرمان می‌تواند مقدار خودش را override کند' );
                self::number_field( 'rate_limit_per_min', 'حداکثر درخواست در دقیقه (هر کلید)', $s['rate_limit_per_min'], 'محدودیت نرخ برای جلوگیری از سوءاستفاده' );
                self::number_field( 'max_pending_per_client', 'حداکثر فرمان در صف هر سیستم', $s['max_pending_per_client'], '' );
                echo '</tbody></table>';

                echo '<h2 class="title">نگهداری تاریخچه و فایل‌ها</h2>';
                echo '<table class="form-table"><tbody>';
                self::number_field( 'history_retention_days', 'مدت نگهداری تاریخچه (روز)', $s['history_retention_days'], 'پس از این مدت، فرمان‌ها و لاگ‌ها به‌طور خودکار حذف می‌شوند. ۰ = همیشه نگه‌داشتن' );
                self::number_field( 'file_retention_days', 'مدت نگهداری فایل‌ها (روز)', $s['file_retention_days'], 'فایل‌های منتقل‌شده پس از این مدت از سرور حذف می‌شوند. ۰ = همیشه نگه‌داشتن' );
                self::number_field( 'max_log_entries', 'حداکثر ردیف‌های لاگ', $s['max_log_entries'], 'قدیمی‌ترین ردیف‌ها حذف می‌شوند' );
                self::number_field( 'max_commands_rows', 'حداکثر ردیف‌های فرمان', $s['max_commands_rows'], '' );
                self::number_field( 'max_file_size_mb', 'حداکثر حجم هر فایل آپلودی (مگابایت)', $s['max_file_size_mb'], 'به محدودیت post_max_size سرور هم توجه کنید' );
                echo '</tbody></table>';

                echo '<h2 class="title">خطرناک</h2>';
                echo '<table class="form-table"><tbody>';
                echo '<tr><th>حذف کامل داده‌ها هنگام حذف افزونه</th><td><label><input type="checkbox" name="delete_data_on_uninstall" value="1" ' . checked( $s['delete_data_on_uninstall'], 1, false ) . '> هنگام حذف افزونه، همه جداول و فایل‌ها پاک شود</label></td></tr>';
                echo '</tbody></table>';

                submit_button( 'ذخیره تنظیمات' );
                echo '</form>';

                // پاک‌سازی دستی.
                echo '<h2 class="title">پاک‌سازی دستی</h2>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aclp-inline-form">';
                self::nonce();
                echo '<input type="hidden" name="action" value="aclp_purge_history">';
                echo '<select name="purge_mode">';
                echo '<option value="old">حذف رکوردهای قدیمی‌تر از:</option>';
                echo '<option value="files">حذف همه فایل‌های منتقل‌شده</option>';
                echo '<option value="all">حذف کل تاریخچه (فرمان‌ها + لاگ‌ها)</option>';
                echo '</select> ';
                echo '<input type="number" name="purge_days" min="0" value="7" class="small-text"> روز ';
                echo '<button class="button aclp-confirm" data-confirm="عملیات پاک‌سازی بازگشت‌پذیر نیست. ادامه می‌دهید؟" type="submit">اجرای پاک‌سازی</button>';
                echo '</form>';

                self::footer();
        }

        /**
         * فیلد عددی استاندارد.
         *
         * @param string $name  نام.
         * @param string $label برچسب.
         * @param mixed  $value مقدار.
         * @param string $desc  توضیح.
         */
        private static function number_field( $name, $label, $value, $desc = '' ) {
                echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
                echo '<input type="number" min="0" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text">';
                if ( $desc ) {
                        echo ' <span class="description">' . esc_html( $desc ) . '</span>';
                }
                echo '</td></tr>';
        }
}
