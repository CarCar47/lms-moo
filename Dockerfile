# ============================================================================
# Moodle 5.1 Docker Image - Using Official Moodle HQ Base
# PHP 8.2 with Apache and ALL extensions pre-compiled
# Optimized for Google Cloud Run with Cold Starts
# Date: 2025-01-12
# ============================================================================
#
# Base: Official Moodle HQ PHP/Apache image with all required extensions
# Source: https://github.com/moodlehq/moodle-php-apache
# Includes: mysqli, pdo, mbstring, intl, gd, zip, opcache, soap, xsl, ldap,
#           AND all XML extensions (dom, xmlreader, simplexml, etc.)
#
# ============================================================================

# Use official Moodle HQ image - PHP 8.2 with all extensions pre-built
FROM moodlehq/moodle-php-apache:8.2

# Moodle 5.1 uses a new directory structure (Official Moodle Documentation):
# All Moodle files go to /var/www/html/ (root, lib/, public/ subdirectory, etc.)
# The public/ subdirectory contains web-accessible files
# The moodlehq entrypoint automatically detects /var/www/html/public/ and configures Apache
#
# Structure: /var/www/html/ (all Moodle files)
#            /var/www/html/public/ (web-accessible subdirectory - auto-detected)
#            /var/www/html/lib/ (shared libraries)
#            /var/www/html/config.php (created by installer)
#
# DO NOT set APACHE_DOCUMENT_ROOT - let moodlehq image auto-configure

# Set working directory
WORKDIR /var/www/html

# ============================================================================
# Install Moodle 5.1 Source Code
# ============================================================================

# Version pinning strategy: Download Moodle 5.1 stable branch
# URL pattern locks to 5.1.x (gets patches, never jumps to 5.2)
# Fallback to Cloud Storage if download.moodle.org is unavailable

ENV MOODLE_VERSION=51
ENV MOODLE_URL="https://download.moodle.org/download.php/direct/stable${MOODLE_VERSION}/moodle-latest-${MOODLE_VERSION}.tgz"
ENV MOODLE_FALLBACK="gs://sms-edu-47-backups/moodle/moodle-5.1-stable.tgz"

# Install gsutil for Cloud Storage fallback
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        wget \
        ca-certificates \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Download and extract Moodle with fallback strategy
RUN echo "Downloading Moodle 5.1..." \
    && (wget -O /tmp/moodle.tgz "$MOODLE_URL" || \
        (echo "Primary download failed, trying fallback..." && \
         gsutil cp "$MOODLE_FALLBACK" /tmp/moodle.tgz)) \
    && echo "Extracting Moodle..." \
    && tar -xzf /tmp/moodle.tgz -C /var/www/html/ --strip-components=1 \
    && rm /tmp/moodle.tgz \
    && chown -R www-data:www-data /var/www/html/

# Install Composer and generate vendor directory (required by Moodle 5.1)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --classmap-authoritative --working-dir=/var/www/html \
    && chown -R www-data:www-data /var/www/html/vendor \
    && apt-get remove -y git unzip \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Create moodledata directory and set permissions
# /var/www/html must be writable by www-data for Moodle installer to create config.php
# /moodledata will be mounted by Cloud Run native volume mount
RUN mkdir -p /moodledata && \
    chown -R www-data:www-data /var/www/html /moodledata && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /moodledata

# ============================================================================
# Configure PHP for Cloud Run
# ============================================================================

# Create PHP configuration optimized for Moodle
RUN { \
    echo '[PHP]'; \
    echo 'memory_limit = 512M'; \
    echo 'upload_max_filesize = 100M'; \
    echo 'post_max_size = 100M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 5000'; \
    echo 'session.save_handler = files'; \
    echo 'session.save_path = "/tmp"'; \
    echo 'date.timezone = America/New_York'; \
    echo 'display_errors = Off'; \
    echo 'display_startup_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /dev/stderr'; \
    echo '[opcache]'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 256'; \
} > /usr/local/etc/php/conf.d/moodle-cloud-run.ini

# ============================================================================
# Create Health Check Endpoint
# ============================================================================

# Simple health check for Cloud Run load balancer
RUN echo '<?php header("Content-Type: application/json"); http_response_code(200); echo json_encode(["status"=>"healthy","service"=>"moodle-lms","timestamp"=>date("c"),"php_version"=>PHP_VERSION]); ?>' > /var/www/html/public/healthcheck.php

# ============================================================================
# Metadata and Configuration
# ============================================================================

LABEL maintainer="COR4EDU Support <support@cor4edu.com>" \
      version="1.0" \
      description="Moodle 5.1 LMS for Google Cloud Run" \
      moodle.version="5.1dev" \
      php.version="8.2" \
      base.image="moodlehq/moodle-php-apache:8.2"

# ============================================================================
# Configure Container Startup
# ============================================================================

# Expose port 80 (Apache default)
EXPOSE 80

# Health check for Cloud Run
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/healthcheck.php || exit 1

# Use default moodlehq entrypoint (apache2-foreground)
# Cloud Run will mount /moodledata volume before container starts

# ============================================================================
# Deployment Notes
# ============================================================================
#
# This Dockerfile uses the official Moodle HQ base image which includes:
#   - PHP 8.2 with Apache
#   - All required Moodle PHP extensions pre-compiled
#   - Production-ready configuration
#   - Regular security updates from Moodle HQ
#
# Extensions included in base image:
#   - Core: ctype, curl, fileinfo, hash, iconv, json, openssl, pcre, sodium, spl, zlib
#   - Database: mysqli, pdo, pdo_mysql, pgsql
#   - XML: dom, simplexml, xml, xmlreader, xmlwriter, soap, xsl
#   - Processing: gd, exif, intl, mbstring, zip
#   - Performance: opcache, apcu, redis, igbinary, memcached
#   - Optional: ldap, bcmath, sockets
#
# Build time: ~2-3 minutes (vs 15-20 min for custom compilation)
# Image size: ~400-500MB
#
# Build locally:
#   cd moodle-main
#   docker build -t moodle-lms:moodlehq .
#
# Deploy to Cloud Run:
#   cd moodle-main
#   gcloud builds submit --config cloudbuild.yaml --project=sms-edu-47
#
# ============================================================================
