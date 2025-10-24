# Deployment Setup Complete

## Overview

The Bitesize Cúrsaí plugin now has a complete deployment infrastructure adapted from the marketing plugin. You can now use `npm run build` and `./deploy.sh` to deploy to production on SiteGround.

## What Was Added

### Build Scripts (`scripts/`)
- **version-bump.js** - Auto-increments version in package.json and PHP file
- **create-zip.js** - Creates deployment ZIP with proper WordPress structure
- **php-lint.js** - Validates PHP syntax before deployment

### Configuration Files
- **package.json** - Build scripts and npm dependencies
- **composer.json** - PHP autoloading configuration
- **env.example** - Template for deployment configuration
- **deploy.sh** - Main deployment script (executable)

### Updated Files
- **bitesize-cursai-plugin.php** - Added version constant
- **.gitignore** - Added build artifacts
- **README.md** - Documented deployment process

## Quick Start

### 1. Initial Setup (Already Done)
```bash
npm install  # Install build dependencies
```

### 2. Build for Production
```bash
npm run build  # Version bump + build assets + create ZIP
```

This will:
- Increment version from 1.0.1 to 1.0.2
- Update package.json and plugin PHP file
- Create dist/bitesize-cursai-plugin-v1.0.2.zip

### 3. Deploy to Production
```bash
./deploy.sh  # Full deployment with git checks and health checks
```

Or use options:
```bash
./deploy.sh --build-only        # Just build, no deploy
./deploy.sh --deploy-only       # Deploy existing build
./deploy.sh --skip-git-check    # Allow uncommitted changes
./deploy.sh --skip-auto-commit  # Don't auto-commit version bump
./deploy.sh --config            # Show configuration
```

## Current State

✅ Version: 1.0.1 (bumped from 1.0.0)
✅ Build system: Working
✅ ZIP creation: Working  
✅ PHP linting: Passing
✅ Deployment script: Configured and ready
✅ Git hooks: Configured

## Configuration

The `.env` file is already configured with:
- SiteGround host: ams7.siteground.eu
- SSH port: 18765
- Deploy path: cursai.bitesize.irish
- Site URL: https://cursai.bitesize.irish

## Deployment Flow

1. **Pre-deployment**
   - Check git status
   - Verify dependencies
   
2. **Build**
   - Install npm packages
   - Run PHP syntax check (fail-fast)
   - Bump version
   - Build assets (currently none, ready for future)
   - Create ZIP package
   
3. **Auto-commit**
   - Stage version files
   - Commit with version number
   - Push to GitHub
   
4. **Deploy**
   - Backup existing plugin on server
   - Upload ZIP via SCP
   - Extract on server
   - Set permissions
   - Run post-deploy commands (if configured)
   
5. **Verification**
   - Health check cursai.bitesize.irish
   - Verify no PHP errors
   - Confirm site is accessible

## Testing the Setup

The build system has been tested and verified:
```bash
✅ npm install - Dependencies installed
✅ npm run lint:php - PHP syntax check passing
✅ npm run build - Version bumped to 1.0.1
✅ npm run zip - ZIP created successfully
✅ ./deploy.sh --config - Configuration loaded from .env
```

## Next Steps

You can now:
1. Add custom functionality to the plugin
2. Run `npm run build` to create deployment packages
3. Run `./deploy.sh` to deploy to production

When you add CSS/JS assets in the future, update the build scripts in package.json to compile them (similar to the marketing plugin).

## Differences from Marketing Plugin

- Plugin name: `bitesize-cursai-plugin`
- Site URL: `cursai.bitesize.irish` 
- Version constant: `BITESIZE_CURSAI_VERSION`
- No assets yet (CSS/JS build steps are placeholders)
- Cleaner initial setup without legacy files

## Notes

- Build artifacts (dist/, node_modules/) are in .gitignore
- Version files (package.json, PHP file) are tracked in git
- ZIP files use consistent plugin directory name for WordPress
- Deployment script handles SiteGround-specific SSH configuration

