<?php
/**
 * Plugin Name: Naval EGT - Area Riservata Clienti
 * Plugin URI: https://metrikmind.it
 * Description: Plugin completo per gestione area riservata clienti con integrazione Dropbox
 * Version: 1.0.21
 * Author: Metrikmind
 * Author URI: https://metrikmind.it
 * Requires at least: 6.8.2
 * Tested up to: 6.8.2
 * License: GPL v2 or later
 * Text Domain: naval-egt
 * Domain Path: /languages
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('NAVAL_EGT_VERSION', '1.0.21');
define('NAVAL_EGT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_FILE', __FILE__);

/**
 * Classe principale del plugin Naval EGT - VERSIONE CORRETTA
 */
class Naval_EGT_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Inizializza gli hooks del plugin
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Naval_EGT_Plugin', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'handle_dropbox_callback'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Shortcode per area riservata
        add_shortcode('naval_egt_area_riservata', array($this, 'area_riservata_shortcode'));
        
        // AJAX handlers per configurazione Dropbox
        add_action('wp_ajax_naval_egt_configure_dropbox', array($this, 'handle_dropbox_config'));
    }
    
    /**
     * Carica le dipendenze del plugin
     */
    private function load_dependencies() {
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-database.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-user-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-dropbox.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-file-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-activity-logger.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-email.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-export.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'admin/class-naval-egt-admin.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'public/class-naval-egt-public.php';
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inizializza le classi principali
        Naval_EGT_Database::get_instance();
        Naval_EGT_User_Manager::get_instance();
        Naval_EGT_Dropbox::get_instance();
        Naval_EGT_File_Manager::get_instance();
        Naval_EGT_Activity_Logger::get_instance();
        Naval_EGT_Email::get_instance();
        Naval_EGT_Admin::get_instance();
        Naval_EGT_Public::get_instance();
        
        // Inizializza frontend pubblico
        Naval_EGT_Public::init();
    }
    
    /**
     * Gestisce la configurazione Dropbox via AJAX
     */
    public function handle_dropbox_config() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->auto_configure();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'auth_url' => $result['auth_url']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce il callback OAuth di Dropbox
     */
    public function handle_dropbox_callback() {
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'naval-egt-settings') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'callback') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error"><p><strong>Errore Dropbox:</strong> ' . esc_html($error_message) . '</p></div>';
            });
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->handle_authorization_callback();
            
            if ($result['success']) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>🎉 Dropbox configurato con successo!</strong></p>';
                    echo '<p>✅ La connessione è attiva e funzionante.</p>';
                    echo '</div>';
                });
            } else {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>❌ Errore durante l\'ottenimento del token:</strong></p>';
                    echo '<p>' . esc_html($result['message']) . '</p>';
                    echo '</div>';
                });
            }
            
            // Redirect per pulire l'URL
            $redirect_url = admin_url('admin.php?page=naval-egt-settings&tab=dropbox');
            if ($result['success']) {
                $redirect_url .= '&auth_result=success';
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Attivazione del plugin
     */
    public function activate() {
        Naval_EGT_Database::create_tables();
        
        // Crea la pagina dell'area riservata se non esiste
        $page_exists = get_page_by_path('area-riservata-naval-egt');
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Area Riservata Naval EGT',
                'post_content' => '[naval_egt_area_riservata]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'area-riservata-naval-egt'
            ));
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Disinstallazione del plugin
     */
    public static function uninstall() {
        Naval_EGT_Database::drop_tables();
        
        // Rimuove le opzioni del plugin
        delete_option('naval_egt_settings');
        delete_option('naval_egt_dropbox_access_token');
        delete_option('naval_egt_dropbox_refresh_token');
        
        // Rimuove la pagina dell'area riservata
        $page = get_page_by_path('area-riservata-naval-egt');
        if ($page) {
            wp_delete_post($page->ID, true);
        }
    }
    
    /**
     * Enqueue scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_script('naval-egt-admin-js', NAVAL_EGT_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-admin-css', NAVAL_EGT_PLUGIN_URL . 'admin/css/admin.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-admin-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'error' => __('Si è verificato un errore', 'naval-egt')
                )
            ));
        }
    }
    
    /**
     * Enqueue scripts frontend - VERSIONE CORRETTA
     */
    public function frontend_enqueue_scripts() {
        global $post;
        
        // Carica script solo se necessario
        $should_load = false;
        
        // Su pagina area riservata
        if (is_page('area-riservata-naval-egt')) {
            $should_load = true;
        }
        
        // Su pagina con shortcode
        if ($post && has_shortcode($post->post_content, 'naval_egt_area_riservata')) {
            $should_load = true;
        }
        
        if ($should_load) {
            // Script principale
            wp_enqueue_script('naval-egt-public-js', NAVAL_EGT_PLUGIN_URL . 'public/js/public.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-public-css', NAVAL_EGT_PLUGIN_URL . 'public/css/public.css', array(), NAVAL_EGT_VERSION);
            
            // Localizzazione JavaScript - IMPORTANTE
            wp_localize_script('naval-egt-public-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'login_error' => __('Credenziali non valide', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'upload_error' => __('Errore durante il caricamento', 'naval-egt'),
                    'file_deleted' => __('File eliminato con successo', 'naval-egt'),
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo file?', 'naval-egt'),
                    'logout_confirm' => __('Sei sicuro di voler uscire?', 'naval-egt')
                )
            ));
            
            // Aggiungi nonce anche come meta tag per maggiore compatibilità
            add_action('wp_head', function() {
                echo '<meta name="naval-egt-nonce" content="' . wp_create_nonce('naval_egt_nonce') . '">';
            });
        }
    }
    
    /**
     * Shortcode per l'area riservata - VERSIONE CORRETTA
     */
    public function area_riservata_shortcode($atts) {
        $atts = shortcode_atts(array(
            'template' => 'default'
        ), $atts, 'naval_egt_area_riservata');
        
        ob_start();
        
        // Assicurati che Naval_EGT_Public sia inizializzato
        $public_instance = Naval_EGT_Public::get_instance();
        
        // Includi il template
        if (file_exists(NAVAL_EGT_PLUGIN_DIR . 'public/views/area-riservata.php')) {
            include NAVAL_EGT_PLUGIN_DIR . 'public/views/area-riservata.php';
        } else {
            echo '<div class="naval-egt-error">Template area riservata non trovato.</div>';
        }
        
        return ob_get_clean();
    }
}

// Inizializza il plugin
Naval_EGT_Plugin::get_instance();