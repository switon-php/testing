#!/bin/bash
set -e

# Get script directory (absolute path)
# Note: This script may be executed directly or via symlink
# ${BASH_SOURCE[0]} resolves to the actual script location (even if symlinked)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# If SCRIPT_DIR is packages/testing, we're being called via symlink from packages/*/tests
# In that case, use current working directory (which should be packages/*/tests)
SCRIPT_PARENT="$(cd "$SCRIPT_DIR/.." && pwd)"
if [ "$(basename "$SCRIPT_DIR")" = "testing" ] && [ "$(basename "$SCRIPT_PARENT")" = "packages" ]; then
    # Called via symlink, use current working directory
    TESTS_DIR="$(pwd)"
else
    # Normal case: SCRIPT_DIR is packages/*/tests or standalone package
    TESTS_DIR="$SCRIPT_DIR"
fi

# Package root directory (where composer.json is located)
PACKAGE_ROOT="$(cd "$TESTS_DIR/.." && pwd)"

# Part 1: Detect environment and setup PHPUnit
# 
# Detection Strategy:
# - Check if parent directory (../../) is named "packages" to detect monorepo
# - This works for:
#   * Monorepo: packages/core/testing/run.sh -> ../../ = packages/ ✓
#   * Standalone/Installed: my-package/testing/run.sh or vendor/my-package/testing/run.sh -> ../../ ≠ packages/ ✓
# - Must use absolute path (cd && pwd) to avoid relative path issues
# - Both standalone and composer-installed packages use same logic (local PHPUnit)
#
PARENT_DIR="$(cd "$TESTS_DIR/../.." && pwd)"
IS_MONOREPO=false
if [ -d "$PARENT_DIR" ] && [ "$(basename "$PARENT_DIR")" = "packages" ]; then
    IS_MONOREPO=true
    SHARED_TESTS_DIR="$PARENT_DIR/testing"
    REPO_ROOT="$(cd "$PARENT_DIR/.." && pwd)"
fi

if [ "$IS_MONOREPO" = true ] && [ -d "$SHARED_TESTS_DIR" ]; then
    # Monorepo: use packages/testing/vendor/bin/phpunit
    PHPUNIT_PATH="$SHARED_TESTS_DIR/vendor/bin/phpunit"
    if [ ! -f "$PHPUNIT_PATH" ]; then
        echo "Installing PHPUnit in shared tests package..."
        (cd "$SHARED_TESTS_DIR" && composer install)
        if [ ! -f "$PHPUNIT_PATH" ]; then
            echo "Error: Failed to install PHPUnit"
            exit 1
        fi
    fi
else
    # Standalone/Installed package: use package root vendor/bin/phpunit
    PHPUNIT_PATH="$PACKAGE_ROOT/vendor/bin/phpunit"
    if [ ! -f "$PHPUNIT_PATH" ]; then
        echo "Installing PHPUnit..."
        (cd "$PACKAGE_ROOT" && composer install)
        if [ ! -f "$PHPUNIT_PATH" ]; then
            echo "Error: Failed to install PHPUnit"
            exit 1
        fi
    fi
fi

PHPUNIT_CMD="$PHPUNIT_PATH"
# Suppress Xdebug step debug warnings (only show when coverage is needed)
export XDEBUG_MODE=${XDEBUG_MODE:-off}
echo "Using PHPUnit: $($PHPUNIT_CMD --version)"

# Part 2: Ensure test dependencies exist
if [ "$IS_MONOREPO" = true ]; then
    # Monorepo: use repo-root vendor/autoload.php
    VENDOR_AUTOLOAD="$REPO_ROOT/vendor/autoload.php"
    if [ ! -f "$VENDOR_AUTOLOAD" ]; then
        echo "Installing dependencies in repo-root..."
        (cd "$REPO_ROOT" && composer install)
        if [ ! -f "$VENDOR_AUTOLOAD" ]; then
            echo "Error: Failed to install dependencies"
            exit 1
        fi
    fi
else
    # Standalone/Installed package: use package root vendor/autoload.php
    VENDOR_AUTOLOAD="$PACKAGE_ROOT/vendor/autoload.php"
    if [ ! -f "$VENDOR_AUTOLOAD" ]; then
        echo "Installing test dependencies..."
        (cd "$PACKAGE_ROOT" && composer install)
        if [ ! -f "$VENDOR_AUTOLOAD" ]; then
            echo "Error: Failed to install dependencies"
            exit 1
        fi
    fi
fi

# Part 3: Build PHPUnit arguments
# phpunit.xml.dist MUST be located in tests directory
PHPUNIT_XML="$TESTS_DIR/phpunit.xml.dist"
if [ ! -f "$PHPUNIT_XML" ]; then
    echo "Error: phpunit.xml.dist not found in $TESTS_DIR"
    echo "All packages MUST have tests/phpunit.xml.dist configuration file"
    exit 1
fi
PHPUNIT_ARGS=(
    "--configuration" "$PHPUNIT_XML"
    "--display-deprecations"
    "--display-phpunit-notices"
)

# Convert simple suite names to --testsuite argument
COVERAGE_MODE=false
if [ $# -gt 0 ]; then
    case "$1" in
        unit|all|integration)
            PHPUNIT_ARGS+=("--testsuite=$1")
            shift
            ;;
        --fast|-f)
            # Fast mode: skip integration tests
            PHPUNIT_ARGS+=("--testsuite=unit")
            shift
            ;;
        --coverage|-c)
            # Coverage mode: generate HTML coverage report
            COVERAGE_MODE=true
            COVERAGE_DIR="$TESTS_DIR/coverage"
            mkdir -p "$COVERAGE_DIR"
            PHPUNIT_ARGS+=("--coverage-html" "$COVERAGE_DIR")
            # Enable Xdebug for coverage
            export XDEBUG_MODE=coverage
            echo "Coverage report will be generated in: $COVERAGE_DIR"
            shift
            ;;
    esac
    # Add remaining arguments
    PHPUNIT_ARGS+=("$@")
fi

# Part 4: Display and execute command
echo ""
echo "Executing: $PHPUNIT_CMD ${PHPUNIT_ARGS[*]}"
echo ""

# Run PHPUnit
exec "$PHPUNIT_CMD" "${PHPUNIT_ARGS[@]}"

# Note: If coverage mode was enabled, open the report
if [ "$COVERAGE_MODE" = true ]; then
    echo ""
    echo "Coverage report generated at: $COVERAGE_DIR/index.html"
    echo "Open it with: open $COVERAGE_DIR/index.html"
fi
