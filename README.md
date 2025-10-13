# COR4EDU Moodle LMS

Moodle 5.1 Learning Management System configured for Google Cloud Run with ultra-low-cost deployment.

## Overview

This repository contains the cloud-native configuration for deploying Moodle LMS alongside COR4EDU SMS. The configuration uses the official Moodle HQ Docker base image with version pinning for stability.

## Key Features

### Moodle 5.1
- Official Moodle HQ base image (moodlehq/moodle-php-apache:8.2)
- All required PHP extensions pre-compiled
- Attendance plugin pre-installed
- Version pinning to stable51 branch (auto-patches, no breaking changes)
- Download fallback to Cloud Storage

### Cloud Infrastructure
- **Cloud Run**: Serverless, auto-scaling (0-10 instances)
- **Cloud SQL**: MySQL 8.0 with automated backups
- **Cloud Storage**: Persistent volume mount for /moodledata
- **Secret Manager**: Secure credential storage
- **Ultra-low cost**: $1-5/month per school

### Integration Ready
- Separate database (`moodle_lms`) in shared Cloud SQL instance
- Manual student provisioning (Excel export/import from SMS)
- API user pre-configured for future integrations
- Attendance tracking with pre-installed plugin

## Quick Start

### Prerequisites
- Google Cloud Project (same as SMS deployment)
- SMS already deployed and operational
- `gcloud` CLI installed and authenticated

### Deploy Moodle

```bash
# Navigate to moodle-main directory
cd moodle-main

# Deploy to Cloud Run
gcloud builds submit --config cloudbuild.yaml --project=YOUR-PROJECT-ID

# Wait for deployment (8-12 minutes)
```

### Complete Installation

1. **Access Moodle URL** (provided after deployment)
2. **Run Installation Wizard**:
   - Choose language
   - Confirm paths (pre-configured)
   - Database connection (auto-configured)
   - License agreement
   - Server checks
   - Install database (~500 tables, 5 minutes)
   - Create admin account
   - Site configuration
3. **Enable Web Services** (optional, for future integration)
4. **Verify Attendance Plugin**

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions.

## Architecture

### Directory Structure
```
moodle-main/
├── Dockerfile              # Container image with Moodle download
├── cloudbuild.yaml         # Cloud Build CI/CD pipeline
├── .dockerignore           # Docker build exclusions
├── .gcloudignore           # Cloud Build exclusions
├── .gitignore              # Git exclusions
├── README.md               # This file
├── DEPLOYMENT.md           # Deployment guide
├── VERSIONING.md           # Version strategy
├── CHANGELOG.md            # Version history
├── public/
│   ├── healthcheck.php     # Health check endpoint
│   └── mod/
│       └── attendance/     # Attendance plugin (included)
└── config.php.template     # Configuration template
```

### Moodle Version Pinning

**Strategy**: Lock to Moodle 5.1 stable branch

```dockerfile
ENV MOODLE_VERSION=51
ENV MOODLE_URL="https://download.moodle.org/download.php/direct/stable51/moodle-latest-51.tgz"
ENV MOODLE_FALLBACK="gs://sms-edu-47-backups/moodle/moodle-5.1-stable.tgz"
```

**Benefits:**
- Gets security patches automatically (5.1.0 → 5.1.1 → 5.1.2)
- Never jumps major versions (5.1 → 5.2 won't happen)
- Fallback ensures builds work if download.moodle.org is down

**Upgrading to 5.2:**
1. Test in staging environment
2. Update `MOODLE_VERSION=52` in Dockerfile
3. Update fallback tgz in Cloud Storage
4. Deploy and test thoroughly
5. Roll out to production schools

## Configuration

### Environment Variables

Set via Cloud Run (auto-configured during deployment):

```bash
# Database (Cloud SQL via Unix socket)
DB_HOST=/cloudsql/PROJECT:REGION:INSTANCE
DB_NAME=moodle_lms
DB_USER=moodle_user

# Moodle settings
DATAROOT=/moodledata
WWWROOT=https://moodle-lms-[hash].run.app

# Secrets (from Secret Manager)
DB_PASSWORD=moodle-db-password:latest
CRON_PASSWORD=moodle-cron-password:latest
```

### Cloud Storage Volume

Persistent `/moodledata` directory:

```yaml
--add-volume=name=moodledata,type=cloud-storage,bucket=moodle-lms-data-PROJECT
--add-volume-mount=volume=moodledata,mount-path=/moodledata
```

**Contents:**
- Course files
- User uploads
- Site data
- Cache files

## Student Provisioning

### Manual Excel Upload (Recommended)

**From SMS:**
1. Export students to Excel
2. Include: username, email, firstname, lastname, program

**To Moodle:**
1. Site Administration → Users → Upload users
2. Select Excel file
3. Map columns
4. Upload and create accounts

**Benefits:**
- Simple and reliable
- No API integration needed
- School controls timing
- Handles 10-500 students easily

### Future API Integration (Optional)

Tables ready for future SMS-LMS API integration:
- `cor4edu_lms_user_mapping`
- `cor4edu_lms_courses`
- `cor4edu_lms_enrollments`
- `cor4edu_lms_grades`
- `cor4edu_lms_attendance`

## Attendance Plugin

Pre-installed attendance plugin at `public/mod/attendance/`:

### Features
- Session attendance tracking
- Multiple status types (Present, Absent, Late, Excused)
- Attendance reports
- Grade book integration
- Email notifications

### Usage
1. Add "Attendance" activity to course
2. Create attendance sessions
3. Take attendance
4. Generate reports

## Cost Optimization

### Current Configuration
- **Min instances**: 0 (cold starts enabled, save $$$)
- **Max instances**: 5 (scales automatically)
- **Memory**: 1Gi (sufficient for 10-100 concurrent users)
- **CPU**: 1 vCPU
- **Database**: Shared Cloud SQL instance with SMS

### Estimated Costs

| Usage | Cloud Run | Storage | Total |
|-------|-----------|---------|-------|
| Development (idle) | $0 | $0.20 | **~$0.20** |
| 10-50 students | $1-2 | $0.50 | **~$2** |
| 50-100 students | $2-4 | $1.00 | **~$5** |

**Note:** Cloud SQL cost shared with SMS ($7.67/month for both)

### Cost Reduction Tips
1. **Min instances: 0** - Already configured (cold starts OK for LMS)
2. **Shared database** - Single Cloud SQL for SMS + LMS
3. **Native volume mount** - Cheaper than Cloud Storage API calls
4. **Version pinning** - Avoid unexpected upgrade costs

## Security

### Best Practices
- Admin passwords: Minimum 16 characters
- Force password change after installation
- Enable two-factor authentication (optional)
- Regular security updates (via version pinning)
- HTTPS enforced (Cloud Run default)

### Credentials Management
- Database password: Secret Manager
- Cron password: Secret Manager
- Admin password: Set during installation wizard

## Monitoring

### Health Checks

```bash
# Cloud Run health endpoint
curl https://your-service-url.run.app/healthcheck.php

# Expected response:
{
  "status": "healthy",
  "service": "moodle-lms",
  "timestamp": "2025-01-13T12:00:00Z",
  "php_version": "8.2.x"
}
```

### Logs

```bash
# View logs
gcloud run services logs read moodle-lms \
  --region=us-central1 \
  --project=YOUR-PROJECT-ID

# Follow logs in real-time
gcloud run services logs tail moodle-lms \
  --region=us-central1 \
  --project=YOUR-PROJECT-ID
```

### Performance Metrics

Access via Cloud Console:
- Request latency
- Instance count
- Memory usage
- CPU utilization
- Error rates

## Troubleshooting

### Common Issues

**Installation wizard doesn't appear:**
```bash
# Check database connection
gcloud sql databases describe moodle_lms --instance=sms-edu-db

# Verify environment variables
gcloud run services describe moodle-lms --region=us-central1
```

**Files not persisting:**
```bash
# Verify Cloud Storage mount
gcloud storage ls --project=YOUR-PROJECT-ID | grep moodledata

# Check volume mount in Cloud Run
gcloud run services describe moodle-lms --format='yaml(spec.template.spec.volumes)'
```

**Slow performance:**
```bash
# Increase resources
gcloud run services update moodle-lms \
  --memory=2Gi \
  --cpu=2 \
  --max-instances=10
```

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed troubleshooting.

## Updating Moodle

### Minor Updates (5.1.x → 5.1.y)

Automatic via version pinning. Just redeploy:

```bash
gcloud builds submit --config cloudbuild.yaml
```

### Major Updates (5.1 → 5.2)

1. **Backup database** first
2. Test in staging environment
3. Update `MOODLE_VERSION` in Dockerfile
4. Update fallback tgz in Cloud Storage
5. Deploy and run Moodle upgrade
6. Test thoroughly before production rollout

## Documentation

- **DEPLOYMENT.md** - Complete deployment guide
- **VERSIONING.md** - Version management strategy
- **CHANGELOG.md** - Version history
- [Official Moodle Docs](https://docs.moodle.org/)
- [Moodle HQ Docker Images](https://github.com/moodlehq/moodle-php-apache)

## Support

**Issues or Questions:**
- GitHub Issues: [lms-moo repository](https://github.com/CarCar47/lms-moo/issues)
- Moodle Forums: https://moodle.org/forums
- Email: support@cor4edu.com

## Related Repositories

- **SMS Repository**: [sms-moo](https://github.com/CarCar47/sms-moo)
- **Moodle Official**: [moodle/moodle](https://github.com/moodle/moodle)
- **Moodle HQ Images**: [moodlehq/moodle-php-apache](https://github.com/moodlehq/moodle-php-apache)

## License

- **Moodle**: GPLv3 (Official Moodle License)
- **COR4EDU Configuration**: Copyright © 2024 COR4EDU

## Acknowledgments

- [Moodle HQ](https://moodle.com/) for the official base images
- [Moodle Community](https://moodle.org/) for the open-source LMS
- Attendance plugin developers

---

**Version**: 1.0.0
**Moodle Version**: 5.1 (stable branch)
**Last Updated**: 2025-01-13
**Status**: Production Ready
