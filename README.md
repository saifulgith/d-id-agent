# D-ID Agent Integration

Complete solution for integrating D-ID AI Agents into WordPress sites with secure backend API.

## 🚀 What This Includes

- **Node.js Backend**: Secure API server for D-ID integration
- **WordPress Plugin**: Easy-to-use plugin with shortcode support
- **Frontend SDK**: Complete D-ID Agent SDK integration
- **Responsive UI**: Modern, mobile-friendly interface

## ✨ Features

- 🤖 **AI Agent Integration**: Embed D-ID agents in WordPress pages
- 🔐 **Secure Backend**: API keys stored safely on your server
- 💬 **Chat & Speak Modes**: Interactive conversation with AI agents
- 🎥 **Video Streaming**: Real-time video and audio streaming
- 📱 **Responsive Design**: Works on all devices
- 🎨 **Customizable**: Multiple themes and styling options
- ⚡ **Easy Setup**: Simple shortcode integration

## 🛠️ Quick Start

### Step 1: Deploy Backend to Render

1. **Fork this repository** to your GitHub account
2. **Get your D-ID API key** from [D-ID Studio](https://studio.d-id.com)
3. **Deploy to Render**:
   - Go to [Render.com](https://render.com)
   - Connect your GitHub account
   - Create new "Web Service"
   - Connect your `d-id-agent` repository
   - Set environment variables:
     - `DID_API_KEY`: Your D-ID server API key
     - `FRONTEND_ORIGIN`: Your WordPress domain
   - Deploy!

### Step 2: Install WordPress Plugin

1. **Download the plugin** from the `wordpress-plugin` folder
2. **Upload to WordPress**:
   - Go to WordPress Admin → Plugins → Add New
   - Upload the plugin ZIP file
   - Activate the plugin
3. **Configure the plugin**:
   - Go to Settings → D-ID Agent
   - Enter your Render backend URL
   - Save settings

### Step 3: Add Agent to Pages

Use the shortcode in any page or post:

```
[did_agent agent_id="your_agent_id"]
```

Get your Agent ID from [D-ID Studio](https://studio.d-id.com) → Your Agent → Embed button.

## API Endpoints

### Health Check
- `GET /` - Server health status

### Client Key Management
- `POST /api/client-key` - Get client key for frontend

### Agent Management
- `POST /api/agents` - Create new agent
- `GET /api/agents/:agentId` - Get agent details

### Stream Management
- `POST /api/agents/:agentId/streams` - Create stream for agent
- `GET /api/agents/:agentId/streams` - Get streams for agent

## Deployment

This backend is designed to be deployed on Render.com:

1. Connect your GitHub repository to Render
2. Set environment variables in Render dashboard
3. Deploy automatically on git push

## WordPress Integration

Use the provided WordPress plugin to integrate this backend with your WordPress site.

## Security

- API keys are stored securely in environment variables
- CORS is configured for your specific WordPress domain
- All D-ID API calls are made server-side

## License

MIT
