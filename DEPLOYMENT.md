# Deployment Guide

Complete step-by-step guide to deploy D-ID Agent integration.

## 🚀 Backend Deployment (Render)

### 1. Prepare Your Repository

Make sure all files are committed and pushed to GitHub:

```bash
git add .
git commit -m "Initial D-ID Agent Backend setup"
git push origin main
```

### 2. Deploy to Render

1. **Go to [Render.com](https://render.com)** and sign up/login
2. **Connect GitHub** account when prompted
3. **Create New Web Service**:
   - Click "New" → "Web Service"
   - Connect your `d-id-agent` repository
   - Choose "Deploy from GitHub"
4. **Configure Service**:
   - **Name**: `d-id-agent-backend` (or your preferred name)
   - **Environment**: `Node`
   - **Build Command**: `npm install`
   - **Start Command**: `npm start`
   - **Instance Type**: `Free` (for testing) or `Starter` (for production)

### 3. Set Environment Variables

In Render dashboard → Your Service → Environment:

- **DID_API_KEY**: Your D-ID server API key (starts with `sk_`)
- **FRONTEND_ORIGIN**: Your WordPress domain (e.g., `https://yourdomain.com`)

### 4. Deploy

Click "Create Web Service" and wait for deployment.

Your backend will be available at: `https://your-service-name.onrender.com`

## 🔌 WordPress Plugin Installation

### 1. Create Plugin ZIP

1. Navigate to the `wordpress-plugin` folder
2. Select all files and folders inside
3. Create a ZIP file named `did-agent-integration.zip`

### 2. Install in WordPress

1. **Go to WordPress Admin** → Plugins → Add New
2. **Upload Plugin**:
   - Click "Upload Plugin"
   - Choose the `did-agent-integration.zip` file
   - Click "Install Now"
3. **Activate Plugin**:
   - Click "Activate Plugin"

### 3. Configure Plugin

1. **Go to Settings** → D-ID Agent
2. **Enter Backend URL**: `https://your-service-name.onrender.com`
3. **Save Changes**

## 🎯 Testing the Integration

### 1. Get Your Agent ID

1. Go to [D-ID Studio](https://studio.d-id.com)
2. Create or select your agent
3. Click the **Embed** button (</>)
4. Copy the `data-agent-id` value

### 2. Add Agent to Page

1. **Edit any page/post** in WordPress
2. **Add shortcode**:
   ```
   [did_agent agent_id="your_agent_id"]
   ```
3. **Publish/Update** the page

### 3. Test the Agent

1. **Visit the page** with the shortcode
2. **Check for loading** - should show "Connecting to AI Agent..."
3. **Wait for connection** - should show "Connected"
4. **Test chat** - type a message and click Send
5. **Test video** - should see agent video streaming

## 🔧 Troubleshooting

### Backend Issues

**Service not starting:**
- Check environment variables are set correctly
- Check logs in Render dashboard
- Verify D-ID API key is valid

**CORS errors:**
- Ensure FRONTEND_ORIGIN matches your WordPress domain exactly
- Check that your WordPress site uses HTTPS

### WordPress Issues

**Plugin not loading:**
- Check that backend URL is correct
- Verify backend is running (visit backend URL directly)
- Check browser console for errors

**Agent not connecting:**
- Verify agent ID is correct
- Check that agent is active in D-ID Studio
- Ensure your domain is allowed in D-ID Studio settings

### Video Issues

**Video not playing:**
- Ensure your site uses HTTPS (required for WebRTC)
- Check browser supports WebRTC
- Try different browser

## 📊 Monitoring

### Backend Monitoring

- **Render Dashboard**: Check service status and logs
- **Health Check**: Visit `https://your-backend.onrender.com/`
- **Logs**: Monitor real-time logs in Render

### WordPress Monitoring

- **Browser Console**: Check for JavaScript errors
- **Network Tab**: Verify API calls to backend
- **Plugin Settings**: Ensure configuration is correct

## 🚀 Production Considerations

### Security

- Use HTTPS for both WordPress and backend
- Set specific FRONTEND_ORIGIN (not wildcard)
- Regularly rotate D-ID API keys
- Monitor backend logs for suspicious activity

### Performance

- Consider upgrading Render plan for better performance
- Implement caching for static assets
- Monitor backend response times
- Use CDN for WordPress assets

### Scaling

- Monitor backend usage in Render
- Consider multiple backend instances for high traffic
- Implement rate limiting if needed
- Monitor D-ID API usage and limits

## 📞 Support

If you encounter issues:

1. **Check logs** in Render dashboard
2. **Verify configuration** in WordPress admin
3. **Test backend** directly via browser
4. **Check D-ID Studio** for agent status
5. **Review browser console** for frontend errors

For additional help, check the main README.md file or create an issue in the repository.
