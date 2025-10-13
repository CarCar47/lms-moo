# Changelog

All notable changes to COR4EDU Moodle LMS deployment configuration will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Automated backup scheduling
- Enhanced monitoring dashboard
- API integration with SMS for user provisioning
- Additional plugins (forum, quiz, assignment)
- Email notification templates

---

## [1.0.0] - 2025-01-13

### Summary
Initial production release of COR4EDU Moodle LMS deployment configuration. Golden template ready for multi-tenant deployment to Google Cloud Platform.

### Moodle Core
- **Version**: Moodle 5.1dev+ (Build: 20250919)
- **PHP**: 8.2 via official moodlehq/moodle-php-apache base image
- **Database**: MySQL 8.0 via Cloud SQL
- **Storage**: Cloud Storage native volume mount for /moodledata

### Added

#### Cloud Infrastructure
- **Cloud Run Deployment**:
  - Serverless container deployment
  - Auto-scaling (0-5 instances)
  - Min instances: 0 (cold starts enabled for cost savings)
  - Max instances: 5 (scales with demand)
  - Memory: 1Gi
  - CPU: 1 vCPU
  - Timeout: 300 seconds

- **Cloud SQL Integration**:
  - Shared Cloud SQL instance with SMS
  - Separate `moodle_lms` database
  - Unix socket connection for security
  - Automated daily backups (7-day retention)
  - Point-in-time recovery enabled

- **Cloud Storage Volume**:
  - Native volume mount (not FUSE)
  - Persistent /moodledata directory
  - Optimized for performance
  - Automatic failover and redundancy

- **Secret Manager Integration**:
  - Database password securely stored
  - Cron password securely stored
  - API tokens (for future use)

#### Moodle Configuration

**Base Image**:
- Official moodlehq/moodle-php-apache:8.2
- All required PHP extensions pre-compiled:
  - Core: ctype, curl, fileinfo, hash, iconv, json, openssl, pcre, sodium, spl, zlib
  - Database: mysqli, pdo, pdo_mysql
  - XML: dom, simplexml, xml, xmlreader, xmlwriter, soap, xsl
  - Processing: gd, exif, intl, mbstring, zip
  - Performance: opcache, apcu, redis, igbinary, memcached
  - Optional: ldap, bcmath, sockets

**Version Pinning**:
```dockerfile
ENV MOODLE_VERSION=51
ENV MOODLE_URL="https://download.moodle.org/download.php/direct/stable51/moodle-latest-51.tgz"
ENV MOODLE_FALLBACK="gs://sms-edu-47-backups/moodle/moodle-5.1-stable.tgz"
```
- Locks to Moodle 5.1 stable branch
- Automatically gets security patches (5.1.0 → 5.1.1 → 5.1.2)
- Never jumps major versions without manual intervention
- Fallback to Cloud Storage if download.moodle.org unavailable

**Directory Structure**:
- Moodle core: `/var/www/html/`
- Web accessible: `/var/www/html/public/` (auto-detected by moodlehq image)
- Data directory: `/moodledata` (mounted from Cloud Storage)
- Configuration: `/var/www/html/config.php` (created by installer)

#### Plugins Pre-Installed

**Attendance Plugin** (`public/mod/attendance/`):
- Session attendance tracking
- Multiple status types (Present, Absent, Late, Excused)
- Attendance reports
- Grade book integration
- Email notifications
- Export to Excel/CSV

#### Health & Monitoring

**Health Check Endpoint** (`/healthcheck.php`):
```json
{
  "status": "healthy",
  "service": "moodle-lms",
  "timestamp": "2025-01-13T12:00:00Z",
  "php_version": "8.2.x"
}
```

**Cloud Run Health Check**:
- Interval: 30 seconds
- Timeout: 10 seconds
- Start period: 60 seconds
- Retries: 3

#### CI/CD Pipeline

**Cloud Build Configuration** (`cloudbuild.yaml`):
1. **Build Step**: Download Moodle 5.1 with fallback
2. **Build Step**: Create Docker image with moodlehq base
3. **Push Step**: Push to Container Registry with build ID tag
4. **Push Step**: Push with 'latest' tag
5. **Deploy Step**: Deploy to Cloud Run with volume mounts
6. **Configure Step**: Set MOODLE_WWWROOT environment variable
7. **Test Step**: Verify health check and main page accessibility

**Build Time**: 8-12 minutes (vs 15-20 min for custom compilation)

**Build Machine**: N1_HIGHCPU_8 (fast builds)

#### Student Provisioning

**Manual Excel Upload** (Recommended):
- Export students from SMS to Excel
- Import to Moodle via Site Administration → Users → Upload users
- Map columns: username, email, firstname, lastname
- Set temporary passwords with force reset

**Integration Tables Ready** (Phase 0 - Future Use):
- `cor4edu_lms_user_mapping` - SMS ↔ Moodle user mapping
- `cor4edu_lms_courses` - Course catalog sync
- `cor4edu_lms_enrollments` - Enrollment sync
- `cor4edu_lms_grades` - Grade sync
- `cor4edu_lms_attendance` - Attendance sync
- `cor4edu_lms_sync_log` - Integration history

#### Documentation

**Comprehensive Documentation Created**:
- `README.md` - Overview and quick start
- `DEPLOYMENT.md` - Complete deployment guide
- `VERSIONING.md` - Version management strategy
- `CHANGELOG.md` - This file
- `Dockerfile` - Well-commented container configuration
- `cloudbuild.yaml` - Annotated CI/CD pipeline

#### Configuration Files

**Docker Configuration**:
- `Dockerfile` - Optimized multi-stage build with version pinning
- `.dockerignore` - Minimal exclusions (Moodle needs most files)
- `.gcloudignore` - Cloud Build optimizations

**Moodle Configuration**:
- `config.php.template` - Configuration template
- Environment variables for database, paths, secrets
- Auto-configuration via installer or Bitnami entrypoint

### Changed
- Updated from copying entire Moodle directory to downloading during build
- Optimized Docker image size (~500MB vs potentially larger)
- Improved build reliability with fallback download strategy

### Fixed
- XML extensions now included via moodlehq base image
- Apache DocumentRoot auto-detection for Moodle 5.1 structure
- Cloud Storage volume mount properly configured
- Healthcheck endpoint returns JSON format

### Security
- HTTPS enforced (Cloud Run default)
- Database passwords in Secret Manager
- Cron password protection
- Unix socket database connection (more secure than TCP)
- No sensitive data in Docker image
- Regular security updates via version pinning

### Infrastructure

**Cost Optimization**:
- **Min instances: 0** - Saves $$ when idle
- **Shared Cloud SQL** - One instance for SMS + LMS
- **Native volume mount** - More efficient than FUSE
- **Version pinning** - Avoid unexpected costs from major upgrades

**Estimated Monthly Costs**:
- Development (idle): ~$0.20
- 10-50 students: ~$2
- 50-100 students: ~$5

**Note**: Cloud SQL shared with SMS ($7.67/month total for both)

**Performance**:
- OPcache enabled
- Moodle caching configured
- Cloud Run auto-scaling
- Cloud Storage CDN-ready

**Reliability**:
- Automated database backups (7-day retention)
- Point-in-time recovery
- Multi-region storage redundancy
- Health checks with automatic restarts
- Zero-downtime deployments

### Dependencies

**Base Image**:
- moodlehq/moodle-php-apache:8.2 (official Moodle HQ image)

**Runtime**:
- PHP 8.2
- Apache 2.4
- MySQL 8.0 (Cloud SQL)

**Build Tools**:
- wget/curl (Moodle download)
- gsutil (Cloud Storage fallback)
- tar (extraction)
- Composer (dependency management)

### System Requirements

**Production Environment**:
- Google Cloud Run (serverless)
- Cloud SQL MySQL 8.0+
- Cloud Storage bucket
- 1Gi RAM minimum
- 1 vCPU minimum
- HTTPS required

**Browser Compatibility**:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers supported

### Known Issues
- Cold starts: 5-10 seconds when scaling from zero (acceptable trade-off for cost savings)
- Large file uploads: Limited by Cloud Run request timeout (300s)
- Moodle mobile app: Requires additional configuration (not covered in v1.0.0)

### Breaking Changes
- None (initial release)

### Deprecations
- None (initial release)

### Migration Notes
- Fresh installations: Use Cloud Build deployment
- Existing Moodle: Export data, deploy clean instance, import data
- No migrations needed (initial release)

### Multi-Tenant Architecture
- Separate Cloud Run service per school (independent scaling)
- Shared Cloud SQL instance (cost savings)
- Separate databases per school
- Independent backups per school
- Golden template deployment (~30 minutes per school)

### Contributors
- Carlos Rivera (CarCar47) - Lead Developer
- Claude Code - Development Assistant

### Acknowledgments
- [Moodle HQ](https://moodle.com/) for official Docker base images
- [Moodle Community](https://moodle.org/) for open-source LMS
- Attendance plugin developers
- Google Cloud Platform for infrastructure

---

## Version History

| Version | Date | Moodle Version | Type | Description |
|---------|------|----------------|------|-------------|
| 1.0.0 | 2025-01-13 | 5.1dev+ | Major | Initial production release, golden template |

---

## Upgrade Instructions

### From Development to v1.0.0
1. Pull latest code from GitHub
2. Deploy using `cloudbuild.yaml`
3. Complete Moodle installation wizard
4. Configure site settings
5. Verify attendance plugin
6. Test student provisioning

### Moodle Core Updates

**Automatic (Minor)**: 5.1.x → 5.1.y
```bash
# Just redeploy - fetches latest 5.1.x
gcloud builds submit --config cloudbuild.yaml
```

**Manual (Major)**: 5.1 → 5.2
1. Backup database and /moodledata
2. Test in staging environment
3. Update `MOODLE_VERSION` in Dockerfile
4. Update fallback tgz in Cloud Storage
5. Deploy to production
6. Run Moodle upgrade wizard
7. Verify all functionality

---

## Support & Feedback

**Issues or Questions:**
- GitHub Issues: [https://github.com/CarCar47/lms-moo/issues](https://github.com/CarCar47/lms-moo/issues)
- Moodle Forums: [https://moodle.org/forums](https://moodle.org/forums)
- Email: support@cor4edu.com

**Related Resources:**
- SMS Repository: [https://github.com/CarCar47/sms-moo](https://github.com/CarCar47/sms-moo)
- Moodle Documentation: [https://docs.moodle.org/](https://docs.moodle.org/)
- Cloud Run Documentation: [https://cloud.google.com/run/docs](https://cloud.google.com/run/docs)

---

**Note**: This is the initial production release. Future versions will follow semantic versioning as documented in VERSIONING.md.

**Version Pinning**: Configuration v1.0.0 deploys Moodle 5.1dev+ (stable51 branch). Minor Moodle updates applied automatically via version pinning strategy.
