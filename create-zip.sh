#!/bin/bash
# Create a WordPress-ready ZIP file for plugin installation

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Remove old ZIP if exists
rm -f ai1ec-rest-api.zip

# Create ZIP with only essential plugin files
zip -r ai1ec-rest-api.zip \
  ai1ec-rest-api.php \
  readme.txt \
  README.md \
  -x "*.git*" "*.DS_Store"

echo "✓ Created ai1ec-rest-api.zip"
echo ""
echo "Included files:"
echo "  - ai1ec-rest-api.php (main plugin)"
echo "  - readme.txt (WordPress.org format)"
echo "  - README.md (GitHub documentation)"
echo ""
echo "Installation instructions:"
echo "1. Upload ai1ec-rest-api.zip to WordPress"
echo "2. Go to Plugins → Add New → Upload Plugin"
echo "3. Choose ai1ec-rest-api.zip and click Install"
echo "4. Activate the plugin"
echo ""
echo "Or manually:"
echo "1. Upload the ai1ec-rest-api folder to /wp-content/plugins/"
echo "2. Activate via WordPress admin"
