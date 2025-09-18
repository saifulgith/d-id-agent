<?php
/**
 * Plugin Name: D-ID Agent Integration
 * Plugin URI: https://github.com/saifulgith/d-id-agent
 * Description: Integrate D-ID AI Agents into your WordPress site with secure backend API.
 * Version: 1.0.0
 * Author: Saiful
 * License: MIT
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentPlugin {
    
    private $backend_url;
    
    public function __construct() {
        $this->backend_url = get_option('did_backend_url', '');
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function enqueue_scripts() {
        // Only load on pages that have the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'did_agent')) {
            
            // Enqueue D-ID SDK from CDN
            wp_enqueue_script(
                'did-client-sdk',
                'https://unpkg.com/@d-id/client-sdk@latest/dist/index.js',
                array(),
                '1.0.0',
                true
            );
            
            // Enqueue our custom integration script
            wp_enqueue_script(
                'did-agent-integration',
                plugin_dir_url(__FILE__) . 'js/did-agent-integration.js',
                array('did-client-sdk'),
                '1.0.0',
                true
            );
            
            // Enqueue styles
            wp_enqueue_style(
                'did-agent-styles',
                plugin_dir_url(__FILE__) . 'css/did-agent-styles.css',
                array(),
                '1.0.0'
            );
            
            // Pass backend URL to JavaScript
            wp_localize_script('did-agent-integration', 'didAgentConfig', array(
                'backendUrl' => $this->backend_url,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('did_agent_nonce')
            ));
        }
    }
    
    public function render_agent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'agent_id' => '',
            'width' => '100%',
            'height' => '500px',
            'theme' => 'default'
        ), $atts);
        
        if (empty($atts['agent_id'])) {
            return '<div class="did-agent-error">Error: Agent ID is required. Use [did_agent agent_id="your_agent_id"]</div>';
        }
        
        if (empty($this->backend_url)) {
            return '<div class="did-agent-error">Error: Backend URL not configured. Please set it in the admin settings.</div>';
        }
        
        $agent_id = esc_attr($atts['agent_id']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        $theme = esc_attr($atts['theme']);
        
        ob_start();
        ?>
        <div class="did-agent-container" 
             data-agent-id="<?php echo $agent_id; ?>"
             data-theme="<?php echo $theme; ?>"
             style="width: <?php echo $width; ?>; height: <?php echo $height; ?>;">
            
            <!-- Loading State -->
            <div class="did-agent-loading" id="did-loading-<?php echo $agent_id; ?>">
                <div class="loading-spinner"></div>
                <p>Connecting to AI Agent...</p>
            </div>
            
            <!-- Error State -->
            <div class="did-agent-error" id="did-error-<?php echo $agent_id; ?>" style="display: none;">
                <p>Failed to connect to AI Agent. Please try again later.</p>
            </div>
            
            <!-- Agent Interface -->
            <div class="did-agent-interface" id="did-interface-<?php echo $agent_id; ?>" style="display: none;">
                
                <!-- Video Container -->
                <div class="video-container" id="video-container-<?php echo $agent_id; ?>">
                    <video id="streamVideoElement-<?php echo $agent_id; ?>" autoplay muted playsinline></video>
                    <video id="idleVideoElement-<?php echo $agent_id; ?>" autoplay muted playsinline loop></video>
                </div>
                
                <!-- Connection Status -->
                <div class="connection-status" id="connectionLabel-<?php echo $agent_id; ?>">
                    Connecting...
                </div>
                
                <!-- Chat Interface -->
                <div class="chat-interface">
                    <div class="chat-messages" id="answers-<?php echo $agent_id; ?>"></div>
                    
                    <!-- Input Controls -->
                    <div class="input-controls">
                        <div class="mode-selection">
                            <label>
                                <input type="radio" name="option-<?php echo $agent_id; ?>" value="chat" checked>
                                Chat
                            </label>
                            <label>
                                <input type="radio" name="option-<?php echo $agent_id; ?>" value="speak">
                                Speak
                            </label>
                        </div>
                        
                        <div class="input-group">
                            <textarea 
                                id="textArea-<?php echo $agent_id; ?>" 
                                placeholder="Type your message here..."
                                rows="2"></textarea>
                            <button id="actionButton-<?php echo $agent_id; ?>" class="send-button">
                                Send
                            </button>
                        </div>
                        
                        <div class="action-buttons">
                            <button id="speechButton-<?php echo $agent_id; ?>" class="speech-button" disabled>
                                🎤 Speech
                            </button>
                            <button id="interruptButton-<?php echo $agent_id; ?>" class="interrupt-button" style="display: none;">
                                ⏹️ Interrupt
                            </button>
                            <button id="reconnectButton-<?php echo $agent_id; ?>" class="reconnect-button">
                                🔄 Reconnect
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'D-ID Agent Settings',
            'D-ID Agent',
            'manage_options',
            'did-agent-settings',
            array($this, 'admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('did_agent_settings', 'did_backend_url');
        add_settings_section(
            'did_agent_main',
            'Backend Configuration',
            null,
            'did-agent-settings'
        );
        add_settings_field(
            'did_backend_url',
            'Backend URL',
            array($this, 'backend_url_callback'),
            'did-agent-settings',
            'did_agent_main'
        );
    }
    
    public function backend_url_callback() {
        $value = get_option('did_backend_url', '');
        echo '<input type="url" name="did_backend_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://your-backend.onrender.com" />';
        echo '<p class="description">Enter your Render backend URL (e.g., https://your-backend.onrender.com)</p>';
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>D-ID Agent Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('did_agent_settings');
                do_settings_sections('did_agent_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>How to Use</h2>
                <ol>
                    <li>Set your backend URL above (from Render deployment)</li>
                    <li>Get your Agent ID from <a href="https://studio.d-id.com" target="_blank">D-ID Studio</a></li>
                    <li>Use the shortcode: <code>[did_agent agent_id="your_agent_id"]</code></li>
                    <li>Customize with options: <code>[did_agent agent_id="your_agent_id" width="100%" height="600px" theme="dark"]</code></li>
                </ol>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new DIDAgentPlugin();
