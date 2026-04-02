#!/bin/bash
# MediaShield Documentation Migration Script
# Moves planning docs from docs/ to plan/ and cleans up Pro .md files
#
# Run from the mediashield (free) plugin directory:
#   cd /path/to/wp-content/plugins/mediashield && bash plan/migrate-docs.sh
#
# What this script does:
# 1. Moves DESIGN_SPEC*.md, IMPLEMENTATION_PLAN*.md, RELEASE_FIX_PLAN.md from docs/ to plan/
# 2. Moves docs/architecture/ and docs/audit/ content to plan/
# 3. Copies Pro's CLAUDE.md and docs/ into plan/pro-docs/
# 4. Deletes ALL .md files from Pro plugin (except vendor/node_modules)
# 5. Removes emptied docs/ subdirectories

set -euo pipefail

FREE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PRO_DIR="$(dirname "$FREE_DIR")/mediashield-pro"

echo "Free plugin: $FREE_DIR"
echo "Pro plugin:  $PRO_DIR"
echo ""

# Step 1: Move planning docs from docs/ to plan/
echo "=== Step 1: Moving planning docs to plan/ ==="

for f in DESIGN_SPEC.md DESIGN_SPEC_v2.md IMPLEMENTATION_PLAN.md IMPLEMENTATION_PLAN_v2.md RELEASE_FIX_PLAN.md; do
    if [ -f "$FREE_DIR/docs/$f" ]; then
        echo "  Moving docs/$f -> plan/$f"
        mv "$FREE_DIR/docs/$f" "$FREE_DIR/plan/$f"
    fi
done

# Step 2: Move architecture and audit
echo ""
echo "=== Step 2: Moving architecture/ and audit/ ==="

if [ -d "$FREE_DIR/docs/architecture" ]; then
    echo "  Moving docs/architecture/ -> plan/architecture/"
    cp -r "$FREE_DIR/docs/architecture/"* "$FREE_DIR/plan/architecture/"
    rm -rf "$FREE_DIR/docs/architecture"
fi

if [ -d "$FREE_DIR/docs/audit" ]; then
    echo "  Moving docs/audit/ -> plan/audit/"
    cp -r "$FREE_DIR/docs/audit/"* "$FREE_DIR/plan/audit/"
    rm -rf "$FREE_DIR/docs/audit"
fi

# Step 3: Copy Pro docs to plan/pro-docs/
echo ""
echo "=== Step 3: Copying Pro docs to plan/pro-docs/ ==="

if [ -d "$PRO_DIR" ]; then
    if [ -f "$PRO_DIR/CLAUDE.md" ]; then
        echo "  Copying Pro CLAUDE.md -> plan/PRO_CLAUDE.md"
        cp "$PRO_DIR/CLAUDE.md" "$FREE_DIR/plan/PRO_CLAUDE.md"
    fi

    if [ -d "$PRO_DIR/docs" ]; then
        mkdir -p "$FREE_DIR/plan/pro-docs"
        echo "  Copying Pro docs/ -> plan/pro-docs/"
        cp -r "$PRO_DIR/docs/"* "$FREE_DIR/plan/pro-docs/"
    fi
else
    echo "  WARNING: Pro plugin directory not found at $PRO_DIR"
fi

# Step 4: Delete .md files from Pro plugin
echo ""
echo "=== Step 4: Deleting .md files from Pro plugin ==="

if [ -d "$PRO_DIR" ]; then
    # Delete CLAUDE.md
    if [ -f "$PRO_DIR/CLAUDE.md" ]; then
        echo "  Deleting Pro CLAUDE.md"
        rm "$PRO_DIR/CLAUDE.md"
    fi

    # Delete docs/ directory entirely
    if [ -d "$PRO_DIR/docs" ]; then
        echo "  Deleting Pro docs/ directory"
        rm -rf "$PRO_DIR/docs"
    fi

    # Delete README.md if it exists
    if [ -f "$PRO_DIR/README.md" ]; then
        echo "  Deleting Pro README.md"
        rm "$PRO_DIR/README.md"
    fi

    echo "  Pro plugin .md cleanup complete (readme.txt preserved)"
else
    echo "  WARNING: Pro plugin directory not found, skipping cleanup"
fi

# Step 5: Clean up temporary .gitkeep
echo ""
echo "=== Step 5: Cleanup ==="

if [ -f "$FREE_DIR/plan/.gitkeep" ]; then
    rm "$FREE_DIR/plan/.gitkeep"
    echo "  Removed plan/.gitkeep"
fi

echo ""
echo "=== Migration complete ==="
echo ""
echo "Free plugin structure:"
echo "  docs/free/    - User-facing free docs"
echo "  docs/pro/     - User-facing pro docs"
echo "  plan/         - Planning docs, specs, QA checklists"
echo ""
echo "Pro plugin: All .md files removed (only readme.txt remains)"
