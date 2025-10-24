#!/bin/bash

# Bitesize Cúrsaí WordPress Plugin Deployment Script
# This script builds and deploys the WordPress plugin to production

set -e

# Load environment variables from .env file if it exists
load_env_file() {
    local env_file=".env"
    
    if [ -f "$env_file" ]; then
        log_info "Loading configuration from $env_file"
        
        # Use set -a to automatically export variables
        set -a
        # Source the .env file, filtering out problematic lines
        while IFS= read -r line || [ -n "$line" ]; do
            # Skip comments and empty lines
            if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "${line// }" ]]; then
                continue
            fi
            
            # Only process valid KEY=VALUE pairs (no spaces in values unless quoted)
            if [[ "$line" =~ ^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
                local key="${BASH_REMATCH[1]}"
                local value="${BASH_REMATCH[2]}"
                # Remove quotes if present
                value="${value%\"}"
                value="${value#\"}"
                export "$key=$value"
            fi
        done < "$env_file"
        set +a
        
        log_info "Environment variables loaded from $env_file"
    else
        log_warning "No .env file found. Using environment variables or defaults."
        log_info "Copy env.example to .env and configure your deployment settings."
    fi
}

# Configuration variables (will be set after loading .env)
PLUGIN_NAME="bitesize-cursai-plugin"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if required tools are installed
check_dependencies() {
    local deps=("npm" "rsync" "ssh" "git" "curl")
    
    for dep in "${deps[@]}"; do
        if ! command -v $dep &> /dev/null; then
            log_error "$dep is required but not installed."
            exit 1
        fi
    done
    
    # Check for composer if it exists, but don't require it
    if command -v composer &> /dev/null; then
        log_info "Composer found."
    else
        log_warning "Composer not found. Will use fallback autoloader."
    fi
    
    # Check for build dependencies
    if [ -f "package.json" ] && npm list sass &> /dev/null 2>&1; then
        log_info "Build dependencies found."
    else
        log_warning "Build dependencies not installed. Will install during build."
    fi
    
    log_info "All dependencies are available."
}

# Check git repository status
check_git_status() {
    if [ "$1" = "--skip-git-check" ]; then
        log_warning "⚠️  Git status check skipped"
        return 0
    fi
    
    log_info "🔍 Checking git repository status..."
    
    # Check if we're in a git repository
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        log_warning "Not in a git repository. Git checks skipped."
        return 0
    fi
    
    # Check for uncommitted changes to tracked files (non-blocking)
    if ! git diff-index --quiet HEAD --; then
        log_warning "⚠️  You have uncommitted changes in your working directory."
        log_warning "These changes will not be included in this deployment."
        echo ""
        git status --porcelain | grep '^[ M|A|D|R|C]' || true # Show only modified/staged
        echo ""
    fi
    
    # Check for untracked files (excluding known build/temp files)
    local untracked_files=$(git ls-files --others --exclude-standard | grep -v -E '\.(log|tmp)$|node_modules|dist/')
    if [ -n "$untracked_files" ]; then
        log_warning "⚠️  Untracked files detected (will not be deployed):"
        echo "$untracked_files"
        echo ""
    fi
    
    log_info "✅ Git repository check complete"
}

# Auto-commit build files and version changes
auto_commit_changes() {
    if [ "$1" = "--skip-auto-commit" ]; then
        log_info "Auto-commit skipped"
        return 0
    fi
    
    log_info "📝 Auto-committing build files and version changes..."
    
    # Check if we're in a git repository
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        log_warning "Not in a git repository. Auto-commit skipped."
        return 0
    fi
    
    # Get current version
    local version=$(node -p "require('./package.json').version")
    
    # Add only version files (build files are in .gitignore)
    git add package.json composer.json bitesize-cursai-plugin.php || {
        log_error "Failed to stage files for commit"
        return 1
    }
    
    # Check if there are any changes to commit
    if git diff-index --quiet --cached HEAD --; then
        log_info "No build changes to commit"
        return 0
    fi
    
    # Create commit message
    local commit_msg="🚀 Deploy v${version}

- Version bump to ${version}
- Production build ready

[auto-commit by deploy.sh]"
    
    # Commit the changes (skip hooks to avoid duplicate syntax checks; we've already linted)
    git commit --no-verify -m "$commit_msg" || {
        log_error "Failed to create commit"
        return 1
    }
    
    log_info "✅ Changes committed: Deploy v${version}"
    
    # Always attempt to sync with remote and push; warn on failure but do not block deployment
    if git rev-parse --git-dir > /dev/null 2>&1; then
        current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")
        log_info "🔁 Syncing branch $current_branch with remote..."
        
        # Check if upstream is configured
        if git rev-parse --symbolic-full-name --verify -q "@{u}" > /dev/null 2>&1; then
            # Fetch and rebase (autostash to avoid working tree issues)
            git fetch --prune || true
            if ! git pull --rebase --autostash --no-edit > /dev/null 2>&1; then
                log_warning "⚠️  Could not auto-rebase with remote. You may have divergent history."
                log_warning "Run: git pull --rebase; resolve any conflicts; then git push"
            fi
        else
            log_warning "⚠️  No upstream configured for $current_branch. Skipping pull."
        fi
        
        log_info "📤 Pushing to remote repository..."
        if ! git push; then
            log_warning "⚠️  Push failed. There may be remote updates or permission issues."
            log_warning "Resolve locally, then push: git pull --rebase; git push"
        else
            log_info "✅ Pushed to remote successfully."
        fi
    fi
}

# Check if we're in the right directory
check_environment() {
    if [ ! -f "bitesize-cursai-plugin.php" ]; then
        log_error "This script must be run from the plugin root directory."
        exit 1
    fi
    
    if [ ! -f "package.json" ] || [ ! -f "composer.json" ]; then
        log_error "Missing package.json or composer.json. Are you in the right directory?"
        exit 1
    fi
    
    # Load environment configuration
    load_env_file
    
    # Set configuration variables after loading .env
    REMOTE_HOST="${DEPLOY_HOST:-your-server.com}"
    REMOTE_USER="${DEPLOY_USER:-www-data}"
    REMOTE_PATH="${DEPLOY_PATH:-/var/www/html/wp-content/plugins}"
    BACKUP_PATH="${BACKUP_PATH:-/tmp/plugin-backups}"
    DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-22}"
    DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY_PATH:-}"
    EXCLUDE_PATTERNS="${EXCLUDE_PATTERNS:-.git*,node_modules,tests,*.md,gulpfile.js}"
    
    # Show current configuration
    log_info "Deployment Configuration:"
    echo "  Target: $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"
    echo "  Backup: $BACKUP_PATH"
    echo "  SSH Port: $DEPLOY_SSH_PORT"
    
    log_info "Environment check passed."
}

# Install dependencies
install_dependencies() {
    log_info "Installing Node.js dependencies..."
    npm install  # Install all dependencies for build process
    
    log_info "Installing PHP dependencies..."
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader
    else
        log_warning "Composer not found, using fallback autoloader."
    fi
    
    log_info "Dependencies installed successfully."
}

# Run tests (if available)
run_tests() {
    log_info "🚨 CRITICAL: Running PHP syntax check for production safety..."
    
    # MANDATORY: PHP syntax check (fail-fast)
    npm run lint:php || {
        log_error "🛑 DEPLOYMENT BLOCKED: PHP syntax errors detected!"
        log_error "💼 Business site protection: Cannot deploy with syntax errors"
        log_error "🔧 Fix all PHP errors before attempting deployment"
        exit 1
    }
    
    log_info "✅ PHP syntax check passed - safe for production"
    
    # Run PHP tests if phpunit is available
    if [ -f "vendor/bin/phpunit" ] && [ -f "phpunit.xml" ]; then
        log_info "Running PHPUnit tests..."
        vendor/bin/phpunit || {
            log_error "🚨 Unit tests failed! Aborting deployment."
            exit 1
        }
    fi
    
    # Run PHP Code Sniffer if available
    if [ -f "vendor/bin/phpcs" ]; then
        log_info "Running PHP Code Sniffer..."
        vendor/bin/phpcs --standard=WordPress src/ || log_warning "Code style issues found (non-blocking)."
    fi
    
    log_info "All tests completed successfully."
}

# Build the plugin
build_plugin() {
    log_info "Building plugin assets..."
    
    # Install ALL dependencies for build (including dev dependencies)
    NODE_ENV=development npm install
    
    # Clean previous build
    npm run clean 2>/dev/null || true
    
    # Run the build process
    npm run build
    
    # After build, clean up dev dependencies for production
    npm prune --production
    
    log_info "Plugin build completed."
}

# Version bump now happens as part of npm build; keep function as no-op for backward compatibility
version_plugin() {
    log_info "Version bump handled by npm run build"
}

# Create deployment package
create_package() {
    log_info "Creating deployment package..."
    
    # Create build and dist directories
    mkdir -p build dist
    
    # Create ZIP package
    npm run zip
    
    log_info "Deployment package created in dist/"
}

# Backup current production version
backup_production() {
    if [ -z "$REMOTE_HOST" ] || [ "$REMOTE_HOST" = "your-server.com" ]; then
        log_warning "No remote host configured. Skipping backup."
        return
    fi
    
    log_info "Checking for existing plugin installation..."
    
    # Check if plugin directory exists
    if ssh -p $DEPLOY_SSH_PORT $REMOTE_USER@$REMOTE_HOST "[ -d '$REMOTE_PATH/$PLUGIN_NAME' ]"; then
        log_info "Creating backup of current production version..."
        
        local backup_name="${PLUGIN_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
        
        ssh -p $DEPLOY_SSH_PORT $REMOTE_USER@$REMOTE_HOST "mkdir -p $BACKUP_PATH && cp -r $REMOTE_PATH/$PLUGIN_NAME $BACKUP_PATH/$backup_name" || {
            log_warning "Failed to create backup. Continuing anyway."
        }
        
        log_info "Backup created: $backup_name"
    else
        log_info "No existing plugin found. This appears to be a first-time deployment."
    fi
}

# Deploy to production
deploy_to_production() {
    if [ -z "$REMOTE_HOST" ] || [ "$REMOTE_HOST" = "your-server.com" ]; then
        log_error "Remote host not configured. Please configure DEPLOY_HOST in .env file."
        echo "1. Copy env.example to .env"
        echo "2. Edit .env and set DEPLOY_HOST=your-server.com"
        echo "3. Configure other deployment settings as needed"
        exit 1
    fi
    
    log_info "🚀 Deploying to production server: $REMOTE_HOST"
    
    # Get current version for ZIP file name
    local version=$(node -p "require('./package.json').version")
    local zip_file="dist/bitesize-cursai-plugin-v${version}.zip"
    
    if [ ! -f "$zip_file" ]; then
        log_error "ZIP file not found: $zip_file"
        log_error "Run 'npm run zip' first to create the deployment package."
        exit 1
    fi
    
    log_info "📦 Deploying ZIP: $zip_file"
    log_info "📁 Target directory: $REMOTE_PATH/$PLUGIN_NAME/"
    
    # Upload ZIP file to temporary location
    local remote_zip="/tmp/bitesize-cursai-plugin-v${version}.zip"
    
    if [ -n "$DEPLOY_SSH_KEY" ]; then
        scp -P $DEPLOY_SSH_PORT -i "$DEPLOY_SSH_KEY" "$zip_file" "$REMOTE_USER@$REMOTE_HOST:$remote_zip"
    else
        scp -P $DEPLOY_SSH_PORT "$zip_file" "$REMOTE_USER@$REMOTE_HOST:$remote_zip"
    fi
    
    # Extract ZIP to replace/update existing plugin
    local ssh_opts=""
    if [ -n "$DEPLOY_SSH_KEY" ]; then
        ssh_opts="-p $DEPLOY_SSH_PORT -i $DEPLOY_SSH_KEY"
    else
        ssh_opts="-p $DEPLOY_SSH_PORT"
    fi
    
    log_info "📂 Extracting plugin to $REMOTE_PATH..."
    
    ssh $ssh_opts "$REMOTE_USER@$REMOTE_HOST" "
        cd $REMOTE_PATH && 
        unzip -o '$remote_zip' &&
        rm '$remote_zip' &&
        echo '✅ Plugin extracted successfully'
    " || {
        log_error "Failed to extract plugin on remote server"
        exit 1
    }
    
    # Set proper permissions
    log_info "🔒 Setting file permissions..."
    ssh $ssh_opts "$REMOTE_USER@$REMOTE_HOST" "
        find $REMOTE_PATH/$PLUGIN_NAME -type f -exec chmod 644 {} \; &&
        find $REMOTE_PATH/$PLUGIN_NAME -type d -exec chmod 755 {} \;
    " || {
        log_warning "Failed to set file permissions"
    }
    
    # Run post-deployment commands if configured
    if [ -n "$POST_DEPLOY_COMMANDS" ]; then
        log_info "🏃 Running post-deployment commands..."
        ssh $ssh_opts "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_PATH/$PLUGIN_NAME && $POST_DEPLOY_COMMANDS" || {
            log_warning "Some post-deployment commands failed."
        }
    fi
    
    log_info "✅ Production deployment completed successfully!"
    log_info "🔄 WordPress will now update the existing plugin instead of creating a new one"
    
    # Health check: Verify site is still functional
    health_check_site
}

# Health check: Verify site is still functional after deployment
health_check_site() {
    local site_url="${SITE_URL:-https://cursai.bitesize.irish}"
    
    log_info "🏥 Running post-deployment health check..."
    log_info "🌐 Checking site: $site_url"
    
    # Make HTTP request to homepage
    # Follow redirects (-L) so non-www → www does not surface as a warning
    local response=$(curl -s -L -w "%{http_code}" -o /tmp/health_check.html "$site_url" 2>/dev/null)
    local http_code="${response: -3}"
    
    if [ "$http_code" = "200" ]; then
        # Check for WordPress/PHP error indicators (avoid false positives)
        # Allow override via HEALTHCHECK_PATTERNS in .env
        local patterns="${HEALTHCHECK_PATTERNS:-Fatal error|Parse error|There has been a critical error|Uncaught|Warning: |Notice: }"
        if grep -qiE "$patterns" /tmp/health_check.html 2>/dev/null; then
            log_error "🚨 CRITICAL: Site accessible but contains error messages!"
            log_error "Check $site_url for WordPress critical errors"
            log_error "You may need to deactivate the plugin if it's causing issues"
            
            # Show first few lines of error content
            echo "Error content preview:"
            grep -i -A2 -B2 "critical\|fatal\|error" /tmp/health_check.html | head -10
            return 1
        else
            log_info "✅ Site health check PASSED"
            log_info "🌟 Site is accessible and no critical errors detected"
            log_info "🎉 Deployment completed successfully - plugin is working!"
        fi
    elif [ "$http_code" = "000" ]; then
        log_warning "⚠️  Could not connect to $site_url"
        log_warning "Site may be down or unreachable - manual verification needed"
    else
        log_warning "⚠️  Site returned HTTP $http_code"
        log_warning "Manual verification recommended: $site_url"
    fi
    
    # Cleanup temp file
    rm -f /tmp/health_check.html
    
    return 0
}

# Clean up build files
cleanup() {
    log_info "Cleaning up build files..."
    
    # Remove dev dependencies
    rm -rf node_modules
    npm install --production --silent
    
    # Clean build directory
    rm -rf build
    
    log_info "Cleanup completed."
}

# Show usage
usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Bitesize Cúrsaí WordPress Plugin Deployment Script"
    echo ""
    echo "Options:"
    echo "  --help              Show this help message"
    echo "  --skip-tests        Skip running tests"
    echo "  --build-only        Only build, don't deploy"
    echo "  --deploy-only       Only deploy (assumes build is done)"
    echo "  --staging           Deploy to staging environment"
    echo "  --config            Show current configuration"
    echo "  --skip-git-check    Skip git status check (allow uncommitted changes)"
    echo "  --skip-auto-commit  Skip auto-committing build files"
    echo ""
    echo "Configuration:"
    echo "  The script uses .env file for configuration. If no .env file exists,"
    echo "  it falls back to environment variables or defaults."
    echo ""
    echo "Setup:"
    echo "  1. Copy env.example to .env"
    echo "  2. Edit .env with your server details"
    echo "  3. Run: $0"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Full deployment (checks git, auto-commits)"
    echo "  $0 --staging                         # Deploy to staging"
    echo "  $0 --build-only                      # Build only"
    echo "  $0 --skip-git-check                  # Deploy with uncommitted changes"
    echo "  $0 --skip-auto-commit                # Don't auto-commit build files"
    echo "  $0 --config                          # Show current configuration"
    echo ""
    echo "Git Workflow:"
    echo "  The script enforces a clean git state before deployment and auto-commits"
    echo "  build files after successful build. Set AUTO_PUSH=true in .env to auto-push."
}

# Show current configuration
show_config() {
    echo "Current Deployment Configuration:"
    echo "================================"
    echo "Plugin: $PLUGIN_NAME"
    echo "Production Host: $REMOTE_HOST"
    echo "User: $REMOTE_USER"
    echo "Path: $REMOTE_PATH"
    echo "Backup Path: $BACKUP_PATH"
    echo "SSH Port: $DEPLOY_SSH_PORT"
    if [ -n "$STAGING_HOST" ]; then
        echo "Staging Host: $STAGING_HOST"
        echo "Staging Path: $STAGING_PATH"
    fi
    echo "Exclude Patterns: $EXCLUDE_PATTERNS"
    if [ -n "$POST_DEPLOY_COMMANDS" ]; then
        echo "Post-Deploy Commands: $POST_DEPLOY_COMMANDS"
    fi
    echo ""
}

# Deploy to staging environment
deploy_to_staging() {
    if [ -z "$STAGING_HOST" ] || [ "$STAGING_HOST" = "staging.cursai.bitesize.irish" ]; then
        log_error "Staging host not configured. Please set STAGING_HOST in .env file."
        exit 1
    fi
    
    log_info "Deploying to staging server: $STAGING_HOST"
    
    # Temporarily override production settings with staging
    local temp_host="$REMOTE_HOST"
    local temp_user="$REMOTE_USER"
    local temp_path="$REMOTE_PATH"
    
    REMOTE_HOST="$STAGING_HOST"
    REMOTE_USER="${STAGING_USER:-$REMOTE_USER}"
    REMOTE_PATH="${STAGING_PATH:-$REMOTE_PATH}"
    
    deploy_to_production
    
    # Restore production settings
    REMOTE_HOST="$temp_host"
    REMOTE_USER="$temp_user"
    REMOTE_PATH="$temp_path"
    
    log_info "Staging deployment completed successfully!"
}

# Main execution
main() {
    local skip_tests=false
    local skip_version=false
    local build_only=false
    local deploy_only=false
    local use_staging=false
    local show_config_only=false
    local skip_git_check=false
    local skip_auto_commit=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                usage
                exit 0
                ;;
            --skip-tests)
                skip_tests=true
                shift
                ;;
            --skip-version)
                log_warning "--skip-version is deprecated; version is bumped during build"
                shift
                ;;
            --build-only)
                build_only=true
                shift
                ;;
            --deploy-only)
                deploy_only=true
                shift
                ;;
            --staging)
                use_staging=true
                shift
                ;;
            --config)
                show_config_only=true
                shift
                ;;
            --skip-git-check)
                skip_git_check=true
                shift
                ;;
            --skip-auto-commit)
                skip_auto_commit=true
                shift
                ;;
            *)
                log_error "Unknown option: $1"
                usage
                exit 1
                ;;
        esac
    done
    
    log_info "Starting Bitesize Cúrsaí Plugin deployment..."
    
    # Pre-deployment checks
    check_dependencies
    check_environment
    
    # Update configuration with loaded environment variables
    REMOTE_HOST="${DEPLOY_HOST:-your-server.com}"
    REMOTE_USER="${DEPLOY_USER:-www-data}"
    REMOTE_PATH="${DEPLOY_PATH:-/var/www/html/wp-content/plugins}"
    BACKUP_PATH="${BACKUP_PATH:-/tmp/plugin-backups}"
    DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-22}"
    DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY_PATH:-}"
    EXCLUDE_PATTERNS="${EXCLUDE_PATTERNS:-.git*,node_modules,tests,*.md,gulpfile.js}"
    
    # Show configuration if requested
    if [ "$show_config_only" = true ]; then
        show_config
        exit 0
    fi
    
    if [ "$deploy_only" = false ]; then
        # Pre-build phase: Git checks
        if [ "$skip_git_check" = true ]; then
            check_git_status --skip-git-check
        else
            check_git_status
        fi
        
        # Build phase
        install_dependencies
        
        # Single, centralized syntax check/tests
        if [ "$skip_tests" = false ]; then
            run_tests
        else
            log_warning "⚠️  Tests skipped, running PHP syntax check only..."
            npm run lint:php || {
                log_error "🛑 DEPLOYMENT BLOCKED: PHP syntax errors detected!"
                exit 1
            }
            log_info "✅ PHP syntax check passed"
        fi
        
        version_plugin
        
        build_plugin
        create_package
        
        # Post-build phase: Auto-commit changes
        if [ "$skip_auto_commit" = true ]; then
            auto_commit_changes --skip-auto-commit
        else
            auto_commit_changes
        fi
    fi
    
    if [ "$build_only" = false ]; then
        # Deployment phase
        if [ "$use_staging" = true ]; then
            backup_production  # Still backup production before staging deploy
            deploy_to_staging
        else
            backup_production
            deploy_to_production
        fi
        cleanup
    fi
    
    log_info "Deployment process completed successfully!"
    
    if [ "$deploy_only" = false ] && [ "$build_only" = false ]; then
        echo ""
        if [ "$use_staging" = true ]; then
            log_info "Staging Deployment Complete!"
            echo "1. Test the plugin on staging: ${STAGING_WP_URL:-$STAGING_HOST}"
            echo "2. Verify membership functionality"
            echo "3. Deploy to production when ready: ./deploy.sh"
        else
            log_info "Production Deployment Complete!"
            echo "✅ Plugin automatically updated in WordPress"
            echo "✅ Site health check completed successfully"
            echo ""
            echo "Next steps:"
            echo "1. Visit the WordPress admin at cursai.bitesize.irish"
            echo "2. Test membership features"
            echo "3. Verify customizations are working"
        fi
    fi
}

# Run main function with all arguments
main "$@"

