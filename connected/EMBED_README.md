# Nexus Pay Embeddable Connected Account Page

The `embed.php` file provides a customizable, embeddable Sign In/Sign Up page that can be easily integrated into other websites using iframes or direct embedding.

## 🚀 Quick Start

### Basic Usage
```html
<iframe src="https://yourdomain.com/embed.php" width="400" height="600" frameborder="0"></iframe>
```

### Advanced Usage with Parameters
```html
<iframe 
    src="https://yourdomain.com/embed.php?title=My%20App&subtitle=Sign%20in%20to%20continue&theme=blue&width=450&height=700&logo=false" 
    width="450" 
    height="700" 
    frameborder="0">
</iframe>
```

## 📋 Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | "Nexus Pay" | Custom title for the embed |
| `subtitle` | string | "Sign in to your Connected Account" | Custom subtitle |
| `theme` | string | "default" | Color theme (see themes below) |
| `width` | number | 400 | Width in pixels |
| `height` | number | 600 | Height in pixels |
| `logo` | boolean | true | Show/hide Nexus Pay logo |
| `redirect` | string | "" | URL to redirect after successful login |

## 🎨 Available Themes

| Theme | Primary Color | Secondary Color | Background |
|-------|---------------|-----------------|------------|
| `default` | #1e3c72 | #667eea | #f5f7fa |
| `dark` | #2c3e50 | #34495e | #2c3e50 |
| `blue` | #3498db | #2980b9 | #ecf0f1 |
| `green` | #27ae60 | #2ecc71 | #f0f8f0 |
| `purple` | #9b59b6 | #8e44ad | #f8f0f8 |

## 📱 Responsive Design

The embed automatically adapts to different screen sizes:
- **Desktop**: Full width and height as specified
- **Mobile**: Responsive with max-width of 90vw
- **Auto-resize**: Automatically adjusts height based on content

## 🔧 Integration Examples

### 1. Simple Integration
```html
<div class="login-container">
    <iframe src="embed.php" width="400" height="600" frameborder="0"></iframe>
</div>
```

### 2. Custom Styled Integration
```html
<div class="login-container">
    <iframe 
        src="embed.php?title=Welcome%20Back&subtitle=Access%20your%20account&theme=blue&width=450&height=650" 
        width="450" 
        height="650" 
        frameborder="0"
        style="border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
    </iframe>
</div>
```

### 3. Redirect After Login
```html
<iframe 
    src="embed.php?redirect=https://yourapp.com/dashboard" 
    width="400" 
    height="600" 
    frameborder="0">
</iframe>
```

### 4. Minimal Design (No Logo)
```html
<iframe 
    src="embed.php?logo=false&title=Sign%20In&theme=dark" 
    width="400" 
    height="600" 
    frameborder="0">
</iframe>
```

## 🛡️ Security Features

- **Session Management**: Secure session handling
- **Password Hashing**: Bcrypt password encryption
- **Input Validation**: Comprehensive form validation
- **SQL Injection Protection**: Prepared statements
- **XSS Protection**: HTML escaping for all output

## 🔄 Features

### Sign In
- Username/password authentication
- Account status validation (banned users blocked)
- Redirect support after successful login
- "Forgot Password" link

### Sign Up
- Username validation (3-20 characters)
- Password strength requirements (6+ characters)
- Security question setup
- Automatic account creation with:
  - Checking account ($1,000 starting balance)
  - Savings account ($500 starting balance)
- Duplicate username prevention

## 📊 Browser Compatibility

- ✅ Chrome 60+
- ✅ Firefox 55+
- ✅ Safari 12+
- ✅ Edge 79+
- ✅ Mobile browsers

## 🎯 Use Cases

1. **E-commerce Sites**: Customer account access
2. **SaaS Applications**: User authentication
3. **Portals**: Member login systems
4. **Mobile Apps**: Web-based authentication
5. **Third-party Integrations**: White-label solutions

## 🔧 Customization

### CSS Customization
The embed uses CSS custom properties that can be overridden:

```css
/* Override theme colors */
.embed-container {
    --primary-color: #your-color;
    --secondary-color: #your-color;
    --background-color: #your-color;
}
```

### JavaScript Integration
The embed sends resize messages to parent window:

```javascript
// Listen for resize messages
window.addEventListener('message', function(event) {
    if (event.data.type === 'resize') {
        // Adjust iframe height
        document.getElementById('login-iframe').style.height = event.data.height + 'px';
    }
});
```

## 📞 Support

For technical support or customization requests, contact the Nexus Pay development team.

---

**Note**: Ensure your server has PHP 7.4+ and MySQL 5.7+ for optimal performance and security. 