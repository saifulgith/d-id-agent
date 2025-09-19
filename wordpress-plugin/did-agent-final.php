<?php
/**
 * Plugin Name: D-ID Agent Final
 * Description: Final working version of D-ID Agent integration
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentFinal {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_final', array($this, 'render_agent_shortcode'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function init() {
        $this->backend_url = get_option('did_agent_backend_url', 'https://d-id-agent-1sdx.onrender.com');
    }
    
    public function enqueue_scripts() {
        // Load D-ID SDK as UMD build
        wp_add_inline_script('jquery', '
            // Load D-ID SDK as UMD build
            const script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.umd.js";
            script.onload = function() {
                console.log("✅ D-ID SDK loaded as UMD");
                console.log("✅ Available classes:", {
                    createAgentManager: !!window.createAgentManager,
                    StreamType: !!window.StreamType,
                    AgentsUI: !!window.AgentsUI,
                    DID: !!window.DID
                });
            };
            document.head.appendChild(script);
            
            // Configure D-ID SDK
            window.didAgentConfig = {
                backendUrl: "' . esc_js($this->backend_url) . '",
                ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                nonce: "' . wp_create_nonce('did_agent_nonce') . '"
            };
            
            console.log("🎯 D-ID Agent Final - Using Official SDK");
        ');
    }
    
    public function render_agent_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 'v2_agt_aKkqeO6X',
            'width' => '800',
            'height' => '600'
        ), $atts);
        
        $agent_id = esc_attr($atts['id']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        
        ob_start();
        ?>
        <div id="did-agent-final-<?php echo $agent_id; ?>" style="width: <?php echo $width; ?>px; height: <?php echo $height; ?>px; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); position: relative; background: #000; margin: 20px auto;">
            <!-- Loading State -->
            <div id="loading-<?php echo $agent_id; ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: absolute; inset: 0; z-index: 10;">
                <div style="width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid white; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px;"></div>
                <p style="margin: 0; font-size: 16px; font-weight: 500;">Connecting to AI Agent...</p>
            </div>
            
            <!-- Error State -->
            <div id="error-<?php echo $agent_id; ?>" style="display: none; padding: 20px; background: #f8d7da; color: #721c24; text-align: center; border-radius: 8px; margin: 20px; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;">
                <p style="margin: 0;">Failed to connect to AI Agent. Please try again later.</p>
            </div>
            
            <!-- D-ID SDK Container - Black background to see video -->
            <div id="sdk-container-<?php echo $agent_id; ?>" style="width: 100%; height: 100%; position: relative; background: #000; display: flex; align-items: center; justify-content: center; visibility: visible; opacity: 1;">
                <!-- Video will be injected here by SDK -->
            </div>
        </div>
        
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Initializing D-ID Agent Final:', '<?php echo $agent_id; ?>');
            
            // Wait for SDK to load
            const waitForSDK = () => {
                if ((window.createAgentManager || window.AgentsUI || window.DID?.AgentsUI) && window.StreamType) {
                    console.log('✅ D-ID SDK loaded successfully');
                    console.log('✅ Available classes:', {
                        createAgentManager: !!window.createAgentManager,
                        AgentsUI: !!window.AgentsUI,
                        DID: !!window.DID,
                        StreamType: !!window.StreamType
                    });
                    initializeAgent('<?php echo $agent_id; ?>');
                } else {
                    console.log('⏳ Waiting for D-ID SDK...');
                    setTimeout(waitForSDK, 100);
                }
            };
            
            const initializeAgent = async (agentId) => {
                try {
                    console.log('🔑 Getting API key from backend...');
                    const response = await fetch(window.didAgentConfig.backendUrl + '/api/client-key', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            capabilities: ['streaming', 'ws']
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    const clientKey = data.client_key || data.clientKey;
                    
                    if (!clientKey) {
                        throw new Error('No client key received from backend');
                    }
                    
                    console.log('✅ API key received successfully');
                    
                    // Redirect all D-ID API calls to our backend
                    const originalFetch = window.fetch;
                    window.fetch = function(url, options = {}) {
                        if (typeof url === 'string' && url.includes('api.d-id.com')) {
                            const newUrl = url.replace('https://api.d-id.com', window.didAgentConfig.backendUrl + '/api');
                            console.log('🔄 Redirecting API call:', url, '->', newUrl);
                            
                            // Add client key to authorization header
                            if (!options.headers) options.headers = {};
                            options.headers['Client-Key'] = clientKey;
                            options.headers['Authorization'] = 'Basic ' + btoa(clientKey + ':');
                        }
                        return originalFetch.call(this, newUrl || url, options);
                    };
                    
                    // Redirect WebSocket connections
                    const originalWebSocket = window.WebSocket;
                    window.WebSocket = function(url, protocols) {
                        if (url.includes('notifications.d-id.com')) {
                            const newUrl = url.replace('wss://notifications.d-id.com', window.didAgentConfig.backendUrl.replace('https://', 'wss://') + '/api/notifications');
                            console.log('🔄 Redirecting WebSocket:', url, '->', newUrl);
                            return new originalWebSocket(newUrl, protocols);
                        }
                        return new originalWebSocket(url, protocols);
                    };
                    
                    console.log('🎨 Creating D-ID Agent Manager...');
                    
                    const container = document.getElementById('sdk-container-' + agentId);
                    if (!container) {
                        throw new Error('Container not found');
                    }
                    
                    // Use createAgentManager (the correct approach)
                    const agentManager = await window.createAgentManager({
                        agentId: agentId,
                        container: container,
                        clientKey: clientKey,
                        onSrcObjectReady: (stream) => {
                            console.log('📹 Video stream ready');
                            hideLoading(agentId);
                        },
                        onVideoStateChange: (state) => {
                            console.log('📹 Video state:', state);
                            if (state === 'START') {
                                hideLoading(agentId);
                            }
                        },
                        onConnectionStateChange: (state) => {
                            console.log('🔗 Connection state:', state);
                            if (state === 'connected') {
                                console.log('✅ Agent connected successfully!');
                                // Send greeting after connection
                                setTimeout(() => {
                                    console.log('👋 Sending greeting message...');
                                    agentManager.chat('Hello, how can I help you today?');
                                }, 1000);
                            }
                        },
                        onNewMessage: (message) => {
                            console.log('💬 New message:', message);
                        },
                        onError: (error) => {
                            console.error('❌ Agent error:', error);
                            showError(agentId);
                        }
                    });
                    
                    console.log('✅ Agent manager created successfully');
                    console.log('🎉 D-ID Agent Final is ready!');
                    
                } catch (error) {
                    console.error('❌ Failed to initialize agent:', error);
                    showError(agentId);
                }
            };
            
            const hideLoading = (agentId) => {
                const loading = document.getElementById('loading-' + agentId);
                if (loading) {
                    loading.style.display = 'none';
                    console.log('✅ Loading hidden');
                }
            };
            
            const showError = (agentId) => {
                const loading = document.getElementById('loading-' + agentId);
                const error = document.getElementById('error-' + agentId);
                if (loading) loading.style.display = 'none';
                if (error) error.style.display = 'block';
            };
            
            // Start initialization
            waitForSDK();
        });
        </script>
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
    
    public function admin_init() {
        register_setting('did_agent_settings', 'did_agent_backend_url');
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('did_agent_backend_url', sanitize_text_field($_POST['backend_url']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $backend_url = get_option('did_agent_backend_url', 'https://d-id-agent-1sdx.onrender.com');
        ?>
        <div class="wrap">
            <h1>D-ID Agent Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Backend URL</th>
                        <td>
                            <input type="url" name="backend_url" value="<?php echo esc_attr($backend_url); ?>" class="regular-text" required />
                            <p class="description">Enter your Render backend URL (e.g., https://your-app.onrender.com)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Usage</h2>
            <p>Use the shortcode <code>[did_agent_final id="v2_agt_aKkqeO6X"]</code> to display the D-ID agent on any page or post.</p>
        </div>
        <?php
    }
}

// Initialize the plugin
new DIDAgentFinal();
