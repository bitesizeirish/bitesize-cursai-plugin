# Audio Functionality Migration - Full SSR Implementation

## Overview
Migrate audio button functionality from cursai-astra theme to bitesize-cursai-plugin with full server-side rendering (SSR), caching, and admin dashboard for cache management.

## What You'll Get

### 1. Server-Side Rendering (SSR) - Metadata Only
- ✅ Sound **metadata** fetched server-side during page render
- ✅ Metadata embedded in HTML as JSON (prehydration)
- ✅ No client-side API calls for metadata
- ✅ Instant display - no loading states
- ✅ MP3 files loaded by browser from public CDN (no auth needed)
- 🔒 **NO client-side fallback** - API requires authentication that cannot be exposed to browser

### 2. Smart Caching System
- ✅ WordPress Transients API for caching
- ✅ 24-hour cache for recorded sounds
- ✅ 15-minute cache for not-yet-recorded sounds
- ✅ Per-request deduplication (same sound used multiple times on one page)

### 3. Admin Dashboard: "Bitesize Cúrsaí"
Located at: **WordPress Admin → Bitesize Cúrsaí → Audio Cache**

Features:
- ✅ View cache health overview (total cached sounds, API config status)
- ✅ Check individual sound cache status
- ✅ View recently cached sounds (20 most recent)
- ✅ Invalidate individual sound caches
- ✅ Clear all caches (bulk action)
- ✅ Debug information (WP_DEBUG_LOG status, cache type)

### 4. Modern Features
- ✅ Orange play buttons (consistent with marketing site)
- ✅ Share links to inirish.bitesize.irish
- ✅ Admin debug info (sound ID visible to admins)
- ✅ Better error handling
- ✅ No Axios dependency (uses Fetch API)

---

## Security Architecture

### Why SSR-Only (No Client-Side Fetch):

**Critical Security Requirement:**
- 🔒 API requires `X-API-Key` and `X-Client-Name` headers for authentication
- 🔒 These secrets CANNOT be exposed to browser JavaScript
- 🔒 API no longer accepts unauthenticated requests
- 🔒 ALL metadata API calls MUST go through WordPress PHP server

### Data Flow:

```
WordPress PHP Server:
├─ Fetches metadata from api.bitesize.irish (with auth headers)
├─ Caches in WordPress transients
├─ Embeds JSON in HTML page
└─ Sends HTML to browser

Browser:
├─ Receives HTML with embedded JSON
├─ Vue reads JSON (no API call)
├─ Displays audio button
└─ Loads MP3 from public CDN (DigitalOcean Spaces - no auth needed)
```

### What "Prehydration" Means:

**Prehydration = Server-side rendering of data into HTML**

Instead of:
```
Browser → Fetch metadata from API → Display button
```

We do:
```
Server → Fetch metadata from API → Embed in HTML → Browser reads HTML → Display button
```

The data is "pre-loaded" into the HTML before it reaches the browser.

---

## Files to Copy & Adapt

### PHP Classes

**1. `src/Audio/AudioButton.php`** (from marketing plugin)
- Main audio button handler
- Registers ACF block
- Enqueues scripts and styles
- Renders audio buttons with SSR
- **Changes**: Namespace → `BitesizeCursai\Audio`, remove Elementor widget

**2. `src/Audio/SoundService.php`** (from marketing plugin)
- Server-side API integration
- Caching with WordPress Transients
- TTL policy (24h for recorded, 15m for unrecorded)
- Uses wp-config constants (already configured!)
- **Changes**: Namespace → `BitesizeCursai\Audio`

**3. `src/Admin/AudioCacheManager.php`** (from marketing plugin)
- Admin menu registration
- Cache management interface
- Handles invalidation and bulk clear actions
- **Changes**: 
  - Namespace → `BitesizeCursai\Admin`
  - Menu parent: Create "Bitesize Cúrsaí" top-level menu
  - Text domain → `bitesize-cursai`

### Assets

**4. `assets/js/src/audio/audio-button.js`** (from marketing plugin)
- Vue component with prehydration support
- Reads embedded JSON data from HTML
- Loads MP3 file from public CDN
- **Changes**: Remove `fetchSoundObject()` fallback method (security requirement - API calls must go through PHP)

**5. `assets/scss/audio/audio-button.scss`** (from marketing plugin)
- Modern styling with orange buttons
- Share links, admin debug info
- **Changes**: None needed

### Templates

**6. `templates/admin/audio-cache.php`** (from marketing plugin)
- Admin dashboard template
- Cache viewer, invalidation forms
- **Changes**: Text domain → `bitesize-cursai`, constant names

---

## Implementation Steps

### Step 1: Create Directory Structure
```bash
mkdir -p src/Audio
mkdir -p src/Admin
mkdir -p assets/js/src/audio
mkdir -p assets/scss/audio
mkdir -p templates/admin
```

### Step 2: Copy & Adapt PHP Files
1. Copy files from marketing plugin
2. Find & replace:
   - `BitesizeMarketing` → `BitesizeCursai`
   - `bitesize-marketing` → `bitesize-cursai`
   - `BITESIZE_MARKETING_` → `BITESIZE_CURSAI_`
3. Remove Elementor widget references in AudioButton.php
4. Update AudioCacheManager menu structure

### Step 3: Copy Assets
1. Copy `audio-button.js` (no changes)
2. Copy `audio-button.scss` (no changes)
3. Copy `audio-cache.php` template (update text domain)

### Step 4: Update Build System

Edit `package.json`:
```json
{
  "scripts": {
    "build": "npm run version:bump && npm run build:audio",
    "build:audio": "npm run build:audio:js && npm run build:audio:css",
    "build:audio:js": "cp assets/js/src/audio/audio-button.js assets/js/audio-button.js",
    "build:audio:css": "sass assets/scss/audio/audio-button.scss assets/css/audio-button.css --style=compressed --no-source-map"
  }
}
```

### Step 5: Initialize in Main Plugin

Edit `bitesize-cursai-plugin.php`:
```php
// Load classes
require_once BITESIZE_CURSAI_PLUGIN_DIR . 'src/Audio/AudioButton.php';
require_once BITESIZE_CURSAI_PLUGIN_DIR . 'src/Admin/AudioCacheManager.php';

// Initialize
function run_bitesize_cursai() {
    // Audio button functionality
    \BitesizeCursai\Audio\AudioButton::get_instance();
    
    // Admin dashboard (only in admin)
    if (is_admin()) {
        new \BitesizeCursai\Admin\AudioCacheManager();
    }
}
add_action('plugins_loaded', 'run_bitesize_cursai');
```

### Step 6: Build & Deploy
```bash
npm run build      # Compile audio assets
./deploy.sh        # Deploy to production
```

---

## API Configuration (Already Done! ✅)

Your wp-config.php already has the required constants:
```php
define('BITESIZE_API_URL', 'api.bitesize.irish');
define('BITESIZE_API_KEY', 'key-here');
define('BITESIZE_API_CLIENT_NAME', 'cursai');
```

SoundService reads these automatically - no code changes needed!

---

## Menu Structure

```
WordPress Admin
├── Bitesize Cúrsaí  (NEW top-level menu)
    └── Audio Cache  (cache management interface)
```

---

## Testing Checklist

### Before Deployment
- [ ] Build assets: `npm run build`
- [ ] Run PHP lint: `npm run lint:php`
- [ ] Check ZIP created: `ls -lh dist/`

### After Deployment
- [ ] Visit a page with audio blocks
- [ ] Verify audio buttons display (orange, not purple)
- [ ] Click play button - audio should work
- [ ] Check admin: WordPress Admin → Bitesize Cúrsaí → Audio Cache
- [ ] View cached sounds list
- [ ] Check individual sound status
- [ ] Verify API configuration shows as "Configured"
- [ ] Test invalidating a single cache
- [ ] View page again - should re-cache
- [ ] Create new post with audio block
- [ ] Verify existing ACF field still works

### SSR Verification
- [ ] View page source HTML
- [ ] Find: `<script type="application/json" id="bitesize-audio--XXX-data">`
- [ ] Verify sound metadata is embedded in JSON (Irish text, translation, pronunciation)
- [ ] Check browser console - should say "Using prehydrated data"
- [ ] Should NOT see any fetch() calls to api.bitesize.irish (metadata already in HTML)
- [ ] Browser Network tab should show MP3 loading from DigitalOcean CDN only
- [ ] If metadata missing, should show error message (not attempt API fetch)

---

## Rollback Plan

If issues occur:
1. Deactivate plugin in WordPress admin
2. Theme's audio functionality still works (unchanged)
3. Or: Deploy previous plugin version
4. No data loss - ACF fields stay in database

---

## Future Enhancements

Once deployed and tested:
- [ ] Add more admin pages (settings, stats)
- [ ] Add cache warming feature
- [ ] Add cache preload for popular sounds
- [ ] Add audio usage analytics
- [ ] Add bulk cache refresh

---

## Important Notes

- **No theme changes required initially** - plugin and theme can coexist
- **ACF field definition** stays in WordPress UI (not in code)
- **API shape** is different from classic.bitesize.irish, but SoundService handles it
- **Audio CDN** changes from CloudFront to DigitalOcean Spaces
- **Button color** changes from purple to orange (can customize if needed)

### Security Notes

- 🔒 **API credentials never exposed** - X-API-Key and X-Client-Name stay server-side
- 🔒 **No CORS issues** - browser never calls api.bitesize.irish for metadata
- 🔒 **Client-side API fallback removed** - all metadata must come from prehydrated JSON
- ✅ **MP3 files** still loaded by browser from public CDN (no authentication required)
- ✅ **Rate limiting controlled** - all API requests go through WordPress server
- ✅ **Audit trail** - all API calls logged server-side if WP_DEBUG_LOG enabled

### Error Handling

If prehydration fails (API down, auth error, etc.):
- Vue component displays error message
- User sees: "Audio data not available. Please refresh the page."
- Admin can check: WordPress Admin → Bitesize Cúrsaí → Audio Cache
- Check API configuration status and debug logs

