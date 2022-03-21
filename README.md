# Robo Backups

Utility for handling backups for a self-hosted website.

## Requirements

* S3 bucket w/ API credentials
* Server access

## Setup

* Create S3 bucket and IAM user (see below).
* Get this software on the server. Likely global install for the user.
* Copy appropriate `robo.example-*.yml` file to `~/.robo/robo.yml`
* Complete configuration file w/ S3 details and paths to app, code, & files.

### S3 Setup

1. Create a new S3 bucket named `backups-[WEBSITE]`. 
1. Create a new IAM user named `S3-backups-[WEBSITE]`. API access, does not need Login/Console access.
1. Save the credentials to LastPass.
1. Attach a new Access Policy (see below) to the new IAM user.

#### Access Policy

* `backups-[WEBSITE]` - Replace this with the S3 bucket name to limit access appropriately.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObjectAcl",
                "s3:GetObject",
                "s3:DeleteObjectVersion",
                "s3:ListBucketVersions",
                "s3:ListBucket",
                "s3:DeleteObject",
                "s3:PutObjectAcl",
                "s3:GetObjectVersion"
            ],
            "Resource": [
                "arn:aws:s3:::backups-[WEBSITE]/*",
                "arn:aws:s3:::backups-[WEBSITE]"
            ]
        }
    ]
}
```
