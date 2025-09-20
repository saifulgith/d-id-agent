<?php
/**
 * Plugin Name: D-ID Agent Debug
 * Description: Debug version to identify the fetch issue
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DIDAgentDebug {
    private $backend_url;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('did_agent_debug', array($this, 'render_agent_shortcode'));
    }
    
    public function init() {
        $this->backend_url = 'https://d-id-agent-1sdx.onrender.com';
    }
    
    public function enqueue_scripts() {
        wp_add_inline_script('jquery', '
            console.log("🚀 D-ID Agent Debug - Starting");
            
            // Load SDK as ES module
            const script = document.createElement("script");
            script.type = "module";
            script.innerHTML = `
                import * as sdk from "https://cdn.jsdelivr.net/npm/@d-id/client-sdk@latest/dist/index.js";
                
                // Make SDK available globally
                window.DID = sdk;
                window.createAgentManager = sdk.createAgentManager;
                
                console.log("✅ D-ID SDK loaded successfully");
                
                // Initialize agent
                initializeAgent();
                
                async function initializeAgent() {
                    try {
                        console.log("🔑 Getting client key...");
                        const backendUrl = "https://d-id-agent-1sdx.onrender.com";
                        
                        // Test backend connectivity first
                        console.log("🔍 Testing backend connectivity...");
                        const healthResponse = await fetch(backendUrl + "/", { method: "GET" });
                        console.log("🏥 Backend health check status:", healthResponse.status);
                        
                        const response = await fetch(backendUrl + "/api/client-key", {
                            method: "POST",
                            headers: { 
                                "Content-Type": "application/json",
                                "Accept": "application/json"
                            },
                            body: JSON.stringify({ capabilities: ["streaming", "ws"] })
                        });
                        
                        console.log("📡 Client key response status:", response.status);
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error("❌ Client key fetch failed:", response.status, response.statusText);
                            console.error("❌ Error response:", errorText);
                            throw new Error("Client key fetch failed: " + response.status + " " + response.statusText);
                        }
                        
                        const data = await response.json();
                        console.log("📦 Client key response data:", data);
                        const clientKey = data.client_key || data.clientKey;
                        
                        if (!clientKey) {
                            console.error("❌ No client key in response:", data);
                            throw new Error("No client key received");
                        }
                        
                        console.log("✅ Client key received");
                        
                        // Test the agent endpoint directly
                        console.log("🧪 Testing agent endpoint directly...");
                        const agentResponse = await fetch(backendUrl + "/api/agents/v2_agt_aKkqeO6X", {
                            method: "GET",
                            headers: {
                                "Content-Type": "application/json",
                                "Accept": "application/json",
                                "Client-Key": clientKey,
                                "Authorization": "Basic " + btoa(clientKey + ":")
                            }
                        });
                        
                        console.log("🧪 Agent endpoint response status:", agentResponse.status);
                        
                        if (agentResponse.ok) {
                            const agentData = await agentResponse.json();
                            console.log("🧪 Agent data received:", agentData);
                        } else {
                            const errorText = await agentResponse.text();
                            console.error("🧪 Agent endpoint failed:", agentResponse.status, errorText);
                        }
                        
                        // Now try with the SDK
                        console.log("🎨 Creating agent manager...");
                        const agentManager = await window.createAgentManager("v2_agt_aKkqeO6X", {
                            auth: { type: "key", clientKey: clientKey },
                            callbacks: {
                                onSrcObjectReady: (stream) => {
                                    console.log("📹 Video stream ready");
                                    const video = document.getElementById("did-agent-container").querySelector("video");
                                    if (video) {
                                        video.srcObject = stream;
                                    } else {
                                        const videoElement = document.createElement("video");
                                        videoElement.srcObject = stream;
                                        videoElement.autoplay = true;
                                        videoElement.playsInline = true;
                                        videoElement.muted = true;
                                        videoElement.style.width = "100%";
                                        videoElement.style.height = "100%";
                                        videoElement.style.objectFit = "cover";
                                        document.getElementById("did-agent-container").appendChild(videoElement);
                                    }
                                    document.getElementById("loading").style.display = "none";
                                },
                                onVideoStateChange: (state) => {
                                    console.log("📹 Video state:", state);
                                },
                                onConnectionStateChange: (state) => {
                                    console.log("🔗 Connection state:", state);
                                    if (state === "connected") {
                                        console.log("✅ Agent connected!");
                                        agentManager.chat("Hello, how can I help you today?");
                                    }
                                },
                                onNewMessage: (messages, type) => {
                                    console.log("💬 New message:", messages, type);
                                },
                                onError: (error, errorData) => {
                                    console.error("❌ Agent error:", error, errorData);
                                }
                            }
                        });
                        
                        console.log("✅ Agent manager created successfully");
                        
                    } catch (error) {
                        console.error("❌ Failed to initialize agent:", error);
                        console.error("❌ Error stack:", error.stack);
                    }
                }
            `;
            document.head.appendChild(script);
        ');
    }
    
    public function render_agent_shortcode($atts) {
        ob_start();
        ?>
        <div id="did-agent-container" style="width: 800px; height: 600px; background: #000; border-radius: 16px; margin: 20px auto; position: relative;">
            <div id="loading" style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 18px;">
                Loading D-ID Agent...
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new DIDAgentDebug();
