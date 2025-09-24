<?php
/**
 * Utility per pulizia completa cache e sessioni Naval EGT
 * ATTENZIONE: Usa questo codice solo per debug, poi rimuovilo!
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Funzione per pulizia forzata di tutte le cache
 */
function naval_egt_force_clear_all_cache() {
    error_log('Naval EGT: === PULIZIA FORZATA CACHE INIZIATA ===');
    
    // 1. Pulisci tutte le sessioni PHP
    if (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
    
    // 2. Pulisci cache WordPress
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        error_log('Naval EGT: WordPress cache flushed');
    }
    
    // 3. Pulisci cache object
    if (function_exists('wp_cache_delete_group')) {
        wp_cache_delete_group('naval_egt');
        error_log('Naval EGT: Object cache group deleted');
    }
    
    // 4. Pulisci transients WordPress
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%naval_egt%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%naval_egt%'");
    error_log('Naval EGT: Transients cleared');
    
    // 5. Pulisci cache plugin comuni
    
    // W3 Total Cache
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
        error_log('Naval EGT: W3TC cache cleared');
    }
    
    // WP Rocket
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
        error_log('Naval EGT: WP Rocket cache cleared');
    }
    
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
        error_log('Naval EGT: WP Super Cache cleared');
    }
    
    // LiteSpeed Cache
    if (class_exists('LiteSpeed_Cache_API')) {
        LiteSpeed_Cache_API::purge_all();
        error_log('Naval EGT: LiteSpeed cache cleared');
    }
    
    // Autoptimize
    if (class_exists('autoptimizeCache')) {
        autoptimizeCache::clearall();
        error_log('Naval EGT: Autoptimize cache cleared');
    }
    
    // 6. Forza pulizia sessioni Naval EGT
    naval_egt_destroy_all_sessions();
    
    error_log('Naval EGT: === PULIZIA CACHE COMPLETATA ===');
}

/**
 * Distruggi tutte le sessioni Naval EGT
 */
function naval_egt_destroy_all_sessions() {
    error_log('Naval EGT: Distruzione sessioni iniziata');
    
    // Distruggi sessione corrente
    if (session_status() !== PHP_SESSION_NONE) {
        $_SESSION = array();
        session_destroy();
        error_log('Naval EGT: Sessione corrente distrutta');
    }
    
    // Rimuovi cookie di sessione
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
        unset($_COOKIE[session_name()]);
        error_log('Naval EGT: Cookie sessione rimosso');
    }
    
    // Pulisci eventuali cookie Naval EGT
    $naval_cookies = array('naval_egt_user', 'naval_egt_session', 'naval_egt_remember');
    foreach ($naval_cookies as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time()-3600, '/');
            unset($_COOKIE[$cookie]);
            error_log('Naval EGT: Cookie ' . $cookie . ' rimosso');
        }
    }
}

/**
 * Versione migliorata del User Manager con pulizia forzata
 */
class Naval_EGT_User_Manager_Enhanced extends Naval_EGT_User_Manager {
    
    /**
     * Autenticazione con pulizia forzata cache
     */
    public static function authenticate_with_cache_clear($username_or_email, $password) {
        error_log('Naval EGT Enhanced: === AUTENTICAZIONE CON PULIZIA CACHE ===');
        
        // STEP 1: Pulizia forzata di tutto
        naval_egt_force_clear_all_cache();
        
        // STEP 2: Avvia sessione completamente pulita
        @session_start();
        error_log('Naval EGT Enhanced: Nuova sessione ID: ' . session_id());
        
        // STEP 3: Autenticazione normale
        $result = parent::authenticate($username_or_email, $password);
        
        if ($result['success']) {
            $user = $result['user'];
            
            error_log('Naval EGT Enhanced: Login riuscito per ' . $user['nome'] . ' ' . $user['cognome'] . ' (ID: ' . $user['id'] . ')');
            
            // STEP 4: Imposta sessione con verifica multipla
            self::set_current_user_verified($user);
            
            // STEP 5: Verifica finale che tutto sia corretto
            $verify = self::get_current_user_verified();
            if ($verify && $verify['id'] == $user['id']) {
                error_log('Naval EGT Enhanced: Verifica finale OK - Utente corretto in sessione');
                
                // STEP 6: Forza invalidazione cache browser
                self::force_browser_cache_clear();
                
                return $result;
            } else {
                error_log('Naval EGT Enhanced: ERRORE - Verifica finale fallita!');
                if ($verify) {
                    error_log('Naval EGT Enhanced: Utente in sessione: ' . $verify['nome'] . ' ' . $verify['cognome'] . ' (ID: ' . $verify['id'] . ')');
                    error_log('Naval EGT Enhanced: Utente atteso: ' . $user['nome'] . ' ' . $user['cognome'] . ' (ID: ' . $user['id'] . ')');
                }
                
                return array(
                    'success' => false,
                    'message' => 'Errore nella gestione della sessione. Riprova.'
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Imposta utente con verifica multipla
     */
    private static function set_current_user_verified($user) {
        error_log('Naval EGT Enhanced: === SET USER VERIFIED ===');
        error_log('Naval EGT Enhanced: Impostando utente: ' . $user['nome'] . ' ' . $user['cognome'] . ' (ID: ' . $user['id'] . ')');
        
        // Distruggi sessione precedente
        $_SESSION = array();
        session_regenerate_id(true);
        
        // Imposta nuovi dati con timestamp
        $_SESSION['naval_egt_user_id'] = $user['id'];
        $_SESSION['naval_egt_user_code'] = $user['user_code'];
        $_SESSION['naval_egt_user_data'] = $user;
        $_SESSION['naval_egt_login_time'] = time();
        $_SESSION['naval_egt_session_hash'] = md5($user['id'] . $user['email'] . time());
        
        error_log('Naval EGT Enhanced: Dati sessione impostati: ' . print_r($_SESSION, true));
        
        // Forza scrittura sessione
        session_write_close();
        
        // Riapri sessione per verifica
        @session_start();
        
        error_log('Naval EGT Enhanced: Sessione riaperta - ID: ' . session_id());
        error_log('Naval EGT Enhanced: Dati dopo riapertura: ' . print_r($_SESSION, true));
    }
    
    /**
     * Ottieni utente con verifica multipla
     */
    public static function get_current_user_verified() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        error_log('Naval EGT Enhanced: === GET USER VERIFIED ===');
        error_log('Naval EGT Enhanced: Session ID: ' . session_id());
        error_log('Naval EGT Enhanced: Session data: ' . print_r($_SESSION, true));
        
        if (isset($_SESSION['naval_egt_user_id'])) {
            $user_id = $_SESSION['naval_egt_user_id'];
            $user = parent::get_user_by_id($user_id);
            
            if ($user) {
                error_log('Naval EGT Enhanced: Utente trovato: ' . $user['nome'] . ' ' . $user['cognome'] . ' (ID: ' . $user['id'] . ')');
                
                // Verifica che i dati siano consistenti
                if (isset($_SESSION['naval_egt_user_code']) && 
                    $_SESSION['naval_egt_user_code'] == $user['user_code']) {
                    
                    return $user;
                } else {
                    error_log('Naval EGT Enhanced: INCONSISTENZA - Codice utente non corrispondente');
                    self::force_logout_verified();
                }
            } else {
                error_log('Naval EGT Enhanced: ERRORE - Utente non trovato nel DB per ID: ' . $user_id);
                self::force_logout_verified();
            }
        }
        
        return null;
    }
    
    /**
     * Logout forzato con pulizia totale
     */
    public static function force_logout_verified() {
        error_log('Naval EGT Enhanced: === FORCE LOGOUT VERIFIED ===');
        
        // Pulizia cache completa
        naval_egt_force_clear_all_cache();
        
        // Headers per forzare refresh browser
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        error_log('Naval EGT Enhanced: Logout forzato completato');
    }
    
    /**
     * Forza invalidazione cache browser
     */
    private static function force_browser_cache_clear() {
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Vary: *');
            
            error_log('Naval EGT Enhanced: Headers cache browser impostati');
        }
    }
}

/**
 * Sostituisci temporaneamente la classe originale
 * ATTENZIONE: Rimuovi dopo aver risolto il problema!
 */
function naval_egt_use_enhanced_manager() {
    // Modifica gli handler AJAX per usare la versione enhanced
    remove_action('wp_ajax_nopriv_naval_egt_login', 'naval_egt_handle_login');
    remove_action('wp_ajax_naval_egt_login', 'naval_egt_handle_login');
    
    add_action('wp_ajax_nopriv_naval_egt_login', 'naval_egt_handle_login_enhanced');
    add_action('wp_ajax_naval_egt_login', 'naval_egt_handle_login_enhanced');
}

/**
 * Handler login enhanced con pulizia cache
 */
function naval_egt_handle_login_enhanced() {
    // Pulizia output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            ob_clean();
            wp_send_json_error('Richiesta non valida');
            return;
        }
        
        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            ob_clean();
            wp_send_json_error('Username e password sono obbligatori');
            return;
        }
        
        ob_clean();
        
        // USA LA VERSIONE ENHANCED CON PULIZIA CACHE
        $result = Naval_EGT_User_Manager_Enhanced::authenticate_with_cache_clear($login, $password);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'redirect_url' => $_POST['redirect_to'] ?? home_url('/area-riservata/'),
                'cache_clear' => true // Flag per forzare reload lato client
            ));
        } else {
            wp_send_json_error($result['message']);
        }
        
    } catch (Exception $e) {
        error_log('Naval EGT Enhanced: Exception durante login: ' . $e->getMessage());
        ob_clean();
        wp_send_json_error('Errore durante il login');
    }
}

/**
 * Aggiungi endpoint per pulizia manuale cache (solo per admin)
 */
function naval_egt_manual_cache_clear() {
    if (!current_user_can('manage_options')) {
        wp_die('Permessi insufficienti');
    }
    
    naval_egt_force_clear_all_cache();
    
    wp_redirect(admin_url('admin.php?page=naval-egt&cache_cleared=1'));
    exit;
}

// ATTIVA LA VERSIONE ENHANCED TEMPORANEAMENTE
add_action('init', 'naval_egt_use_enhanced_manager', 999);

// Aggiungi azione per pulizia manuale
add_action('wp_ajax_naval_egt_clear_cache', 'naval_egt_manual_cache_clear');

/**
 * EMERGENCY RESET - Funzione per reset completo
 * Chiama questa funzione dall'admin per reset totale
 */
function naval_egt_emergency_reset() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    error_log('Naval EGT: === EMERGENCY RESET INIZIATO ===');
    
    // 1. Pulisci tutte le cache
    naval_egt_force_clear_all_cache();
    
    // 2. Distruggi tutte le sessioni nel sistema
    if (ini_get('session.save_handler') == 'files') {
        $path = session_save_path();
        if ($path && is_dir($path)) {
            $files = glob($path . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            error_log('Naval EGT: File sessioni eliminati: ' . count($files));
        }
    }
    
    // 3. Reset opzioni WordPress correlate
    delete_option('naval_egt_cache_version');
    update_option('naval_egt_last_reset', time());
    
    // 4. Forza refresh di tutto
    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    error_log('Naval EGT: === EMERGENCY RESET COMPLETATO ===');
}

// Aggiungi parametro URL per emergency reset (solo admin)
if (isset($_GET['naval_emergency_reset']) && current_user_can('manage_options')) {
    naval_egt_emergency_reset();
    wp_redirect(remove_query_arg('naval_emergency_reset'));
    exit;
}
?>