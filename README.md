# Bitesize Cúrsaí Plugin

A custom WordPress plugin for the Bitesize Irish Cúrsaí membership site.

## About

This plugin provides custom functionality and features for the Cúrsaí platform at [cursai.bitesize.irish](https://cursai.bitesize.irish), which offers Irish language lessons through a membership-based model.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/bitesize-cursai-plugin` directory
2. Activate the plugin through the 'Plugins' screen in WordPress

## Development

### Setup

1. Clone the repository
2. Run `npm install` to install build dependencies
3. Run `composer install` for PHP dependencies (optional)
4. Copy `env.example` to `.env` and configure for your environment

### Building

```bash
# Build for production (auto-increments version)
npm run build

# Run PHP syntax check
npm run lint:php

# Create deployment ZIP
npm run zip
```

### Deployment

The plugin includes automated deployment to SiteGround:

1. Configure your `.env` file with server credentials
2. Run the deployment script:

```bash
# Full deployment (build + deploy)
./deploy.sh

# Build only (no deploy)
./deploy.sh --build-only

# Deploy only (assumes already built)
./deploy.sh --deploy-only

# Show current configuration
./deploy.sh --config
```

The deployment script will:
- Run PHP syntax checks
- Auto-increment version numbers
- Build assets
- Create deployment ZIP
- Auto-commit version changes
- Deploy to production via SSH
- Run health checks

### Version Management

Version numbers are automatically managed:
- `npm run build` increments the patch version
- Updates `package.json`, `bitesize-cursai-plugin.php`
- Creates versioned ZIP file in `dist/`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Node.js 14+ (for development)
- Composer (optional, for PHP dependencies)

## License

GPL v2 or later

## Support

For support, please contact Bitesize Irish.

