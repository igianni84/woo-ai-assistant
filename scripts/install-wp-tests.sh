#!/usr/bin/env bash
#
# WordPress Test Environment Setup Script
#
# Sets up WordPress testing environment for plugin integration tests.
# This script is used by both local development and CI/CD pipelines.
#
# @package WooAiAssistant
# @subpackage Scripts
# @since 1.0.0
# @author Claude Code Assistant

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\.[0] ]]; then
		# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
		WP_TESTS_TAG="branches/${WP_VERSION%.[0-9]*}"
	else
		# otherwise, strip off the minor version and download version x.x.x
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$LATEST_VERSION" ]]; then
		log_error "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

log_info "Installing WordPress $WP_VERSION test environment..."
log_info "WP_TESTS_DIR: $WP_TESTS_DIR"
log_info "WP_CORE_DIR: $WP_CORE_DIR"

install_wp() {

	if [ -d $WP_CORE_DIR ]; then
		log_info "WordPress core directory already exists, skipping download"
		return;
	fi

	log_info "Downloading WordPress core..."
	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip  $TMPDIR/wordpress-nightly/wordpress-nightly.zip
		unzip -q $TMPDIR/wordpress-nightly/wordpress-nightly.zip -d $TMPDIR/wordpress-nightly/
		mv $TMPDIR/wordpress-nightly/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			# https serves multiple offers, whereas http serves single.
			download https://wordpress.org/wordpress-${WP_VERSION}.tar.gz  $TMPDIR/wordpress.tar.gz
			ARCHIVE_NAME="$TMPDIR/wordpress.tar.gz"
		else
			# https serves multiple offers, whereas http serves single.
			download https://wordpress.org/${WP_VERSION}.tar.gz  $TMPDIR/wordpress.tar.gz
			ARCHIVE_NAME="$TMPDIR/wordpress.tar.gz"
		fi

		if [ $WP_VERSION == 'latest' ]; then
			download https://wordpress.org/latest.tar.gz  $TMPDIR/wordpress.tar.gz
			ARCHIVE_NAME="$TMPDIR/wordpress.tar.gz"
		fi

		tar --strip-components=1 -zxmf $ARCHIVE_NAME -C $WP_CORE_DIR
	fi

	log_success "WordPress core downloaded to $WP_CORE_DIR"

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		log_info "Installing WordPress test suite..."
		# set up testing suite
		mkdir -p $WP_TESTS_DIR
		svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
		svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
		log_success "WordPress test suite downloaded to $WP_TESTS_DIR"
	else
		log_info "WordPress test suite already exists, skipping download"
	fi

	if [ ! -f wp-tests-config.php ]; then
		log_info "Creating wp-tests-config.php..."
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# remove all forward slashes in the end
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
		log_success "wp-tests-config.php created"
	else
		log_info "wp-tests-config.php already exists, skipping creation"
	fi

}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		log_success "Test database recreated"
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		log_info "Skipping database creation as requested"
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# Test database connection
	log_info "Testing database connection..."
	if ! mysqladmin ping --user="$DB_USER" --password="$DB_PASS"$EXTRA > /dev/null 2>&1; then
		log_error "Database connection failed. Please check your credentials and database server."
		exit 1
	fi
	log_success "Database connection successful"

	# Create database
	log_info "Creating test database '$DB_NAME'..."
	if mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA > /dev/null 2>&1; then
		log_success "Test database '$DB_NAME' created"
	else
		log_warning "Database '$DB_NAME' already exists or could not be created"
		log_info "Attempting to drop and recreate database..."
		if mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA > /dev/null 2>&1; then
			log_info "Dropped existing database"
			create_db
			log_success "Test database recreated"
		else
			log_warning "Could not drop/recreate database, proceeding with existing database"
		fi
	fi
}

install_woocommerce() {
	log_info "Installing WooCommerce for integration tests..."
	
	# Download WooCommerce
	WC_DIR="$WP_CORE_DIR/wp-content/plugins/woocommerce"
	if [ ! -d "$WC_DIR" ]; then
		log_info "Downloading WooCommerce..."
		mkdir -p "$WC_DIR"
		download "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip" "$TMPDIR/woocommerce.zip"
		unzip -q "$TMPDIR/woocommerce.zip" -d "$WP_CORE_DIR/wp-content/plugins/"
		log_success "WooCommerce downloaded"
	else
		log_info "WooCommerce already exists, skipping download"
	fi
}

# Main execution
log_info "WordPress test environment setup starting..."
log_info "Parameters:"
log_info "  DB_NAME: $DB_NAME"
log_info "  DB_USER: $DB_USER"
log_info "  DB_HOST: $DB_HOST"
log_info "  WP_VERSION: $WP_VERSION"
log_info "  SKIP_DB_CREATE: $SKIP_DB_CREATE"

install_wp
install_test_suite

if [[ "$SKIP_DB_CREATE" != "true" ]]; then
	install_db
fi

# Install WooCommerce if this is a WooCommerce plugin
if [[ -f "woo-ai-assistant.php" ]]; then
	install_woocommerce
fi

log_success "WordPress test environment setup completed!"
log_info "You can now run your tests with PHPUnit"
log_info "Example: composer run test"